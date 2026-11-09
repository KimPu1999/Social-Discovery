<?php

namespace App\Services\Feed;

use App\Models\Post;
use App\Models\PostComment;
use App\Models\PostMedia;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Content reporting + moderation, built on the Phase 02 `user_reports`
 * table (extended in Phase 06 with reportable targets and moderation
 * fields) — no duplicate reporting workflow.
 *
 * Report statuses: pending | under_review | resolved | dismissed.
 * Moderation decisions: remove_content | restore_content | none.
 */
class ContentModerationService
{
    public const REASONS = ['spam', 'harassment', 'hate_content', 'inappropriate_content', 'privacy_violation', 'other'];
    public const STATUSES = ['pending', 'under_review', 'resolved', 'dismissed'];

    public function __construct(private readonly AuditLogger $audit) {}

    /** A viewer reports a post or comment. Duplicate open reports collapse into details, never rows. */
    public function report(User $reporter, Post|PostComment $target, string $reason, ?string $details): array
    {
        if (! in_array($reason, self::REASONS, true)) {
            throw ValidationException::withMessages(['reason' => 'Choose a valid report reason.']);
        }

        return DB::transaction(function () use ($reporter, $target, $reason, $details): array {
            $type = $target::class;

            $existing = DB::table('user_reports')
                ->where('reporter_id', (int) $reporter->getKey())
                ->where('reportable_type', $type)
                ->where('reportable_id', $target->getKey())
                ->where('status', 'pending')
                ->first();

            if ($existing !== null) {
                // Collapse duplicates: append details instead of creating noise.
                $merged = trim(($details ?? '').'');

                DB::table('user_reports')->where('id', $existing->id)->update([
                    'details' => $merged !== '' ? trim(((string) $existing->details)." \n".$merged) : $existing->details,
                    'updated_at' => now(),
                ]);

                $this->audit->record($type === Post::class ? 'post.report.resent' : 'comment.report.resent', $reporter, $target);

                return ['id' => (int) $existing->id, 'duplicate' => true];
            }

            $id = (int) DB::table('user_reports')->insertGetId([
                'reporter_id' => (int) $reporter->getKey(),
                'reported_user_id' => (int) $target->user_id,
                'reportable_type' => $type,
                'reportable_id' => (int) $target->getKey(),
                'reason' => $reason,
                'details' => $details,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record($type === Post::class ? 'post.reported' : 'comment.reported', $reporter, $target, ['reason' => $reason]);

            return ['id' => $id, 'duplicate' => false];
        });
    }

    /** Moderator queue: pending + under_review first, filters by status. */
    public function queue(string $status = 'open', string $search = '', int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('user_reports as reports')
            ->join('users as reporter_users', 'reporter_users.id', '=', 'reports.reporter_id')
            ->leftJoin('user_profiles as reporter_profiles', 'reporter_profiles.user_id', '=', 'reports.reporter_id')
            ->leftJoin('users as reported_users', 'reported_users.id', '=', 'reports.reported_user_id')
            ->leftJoin('user_profiles as reported_profiles', 'reported_profiles.user_id', '=', 'reports.reported_user_id')
            ->select([
                'reports.*',
                'reporter_profiles.username as reporter_username',
                'reporter_profiles.display_name as reporter_name',
                'reported_profiles.username as reported_username',
                'reported_profiles.display_name as reported_name',
            ])->orderByRaw("case reports.status when 'pending' then 0 when 'under_review' then 1 else 2 end")
            ->orderByDesc('reports.id');

        if ($status === 'open') {
            $query->whereIn('reports.status', ['pending', 'under_review']);
        } elseif ($status !== 'all') {
            $query->where('reports.status', $status);
        }

        if ($search !== '') {
            $query->where(static function ($sub) use ($search): void {
                $sub->where('reports.reason', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($search)).'%')
                    ->orWhereRaw('lower(coalesce(reported_profiles.username, reported_users.name)) like ?', ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], mb_strtolower($search)).'%']);
            });
        }

        $page = $query->paginate($perPage);

        // Attach authorized content previews (moderator sees even removed content).
        $rows = collect($page->items())->groupBy('reportable_type');

        $posts = ($rows[Post::class] ?? collect())
            ->map(static fn ($row) => (int) $row->reportable_id)
            ->all();
        $comments = ($rows[PostComment::class] ?? collect())
            ->map(static fn ($row) => (int) $row->reportable_id)
            ->all();

        $postPreviews = collect();
        $commentPreviews = collect();

        if ($posts !== []) {
            $postPreviews = Post::query()->withTrashed()->with(['user.profile', 'media'])->whereIn('id', $posts)->get()->keyBy('id');
            $commentPreviews = PostComment::query()->withTrashed()->with(['post', 'user.profile'])->whereIn('id', $comments)->get()->keyBy('id');
        } elseif ($comments !== []) {
            $commentPreviews = PostComment::query()->withTrashed()->with(['post', 'user.profile'])->whereIn('id', $comments)->get()->keyBy('id');
        }

        $page->getCollection()->transform(function ($row) use ($postPreviews, $commentPreviews) {
            $preview = $row->reportable_type === Post::class ? $postPreviews[$row->reportable_id] ?? null : ($commentPreviews[$row->reportable_id] ?? null);

            $row->target = $preview === null
                ? ['type' => $row->reportable_type === Post::class ? 'post' : 'comment', 'unavailable' => true]
                : $this->previewPayload($preview);

            return $row;
        });

        return $page;
    }

    /**
     * Moderator decision. `remove_content` marks the target removed (author
     * keeps a soft-deleted trail); `restore_content` puts it back. Always
     * audited.
     */
    public function resolve(User $moderator, int $reportId, string $status, string $decision, string $note = ''): array
    {
        if (! in_array($status, self::STATUSES, true) || ! in_array($decision, ['remove_content', 'restore_content', 'none'], true)) {
            throw ValidationException::withMessages(['status' => 'Invalid review status or decision.']);
        }

        return DB::transaction(function () use ($moderator, $reportId, $status, $decision, $note): array {
            $report = DB::table('user_reports')->where('id', $reportId)->first();

            if ($report === null) {
                abort(404);
            }

            $action = null;

            if ($decision === 'remove_content') {
                $action = $report->reportable_type === Post::class
                    ? Post::query()->withTrashed()->whereKey($report->reportable_id)->first()
                    : PostComment::query()->withTrashed()->whereKey($report->reportable_id)->first();

                if ($action instanceof Post) {
                    $action->forceFill(['status' => 'removed', 'deleted_at' => $action->deleted_at])->save();
                } elseif ($action instanceof PostComment) {
                    $action->forceFill(['deleted_at' => now()])->save();
                }

                // Collapse every open report on this target.
                DB::table('user_reports')
                    ->where('reportable_type', $report->reportable_type)
                    ->where('reportable_id', $report->reportable_id)
                    ->whereIn('status', ['pending', 'under_review'])
                    ->where('id', '!=', $reportId)
                    ->update(['status' => 'resolved', 'resolved_at' => now(), 'resolved_by' => $moderator->getKey(), 'resolution_note' => $note !== '' ? $note : 'Content removed', 'updated_at' => now()]);
            } elseif ($decision === 'restore_content') {
                $action = $report->reportable_type === Post::class
                    ? Post::query()->withTrashed()->whereKey($report->reportable_id)->first()
                    : PostComment::query()->withTrashed()->whereKey($report->reportable_id)->first();

                if ($action instanceof Post) {
                    $action->forceFill(['status' => 'published', 'deleted_at' => null])->save();
                } elseif ($action instanceof PostComment) {
                    $action->forceFill(['deleted_at' => null])->save();
                }
            }

            DB::table('user_reports')->where('id', $reportId)->update([
                'status' => $status,
                'resolved_at' => now(),
                'resolved_by' => $moderator->getKey(),
                'resolution_note' => $note !== '' ? $note : null,
                'updated_at' => now(),
            ]);

            $this->audit->record('moderation.report.resolved', $moderator, null, [
                'report_id' => $reportId,
                'status' => $status,
                'decision' => $decision,
                'target_type' => $report->reportable_type,
                'target_id' => $report->reportable_id,
            ]);

            return ['id' => $reportId, 'status' => $status, 'decision' => $decision];
        });
    }

    /** Moderator starts review. */
    public function takeUnderReview(User $moderator, int $reportId): void
    {
        DB::table('user_reports')->where('id', $reportId)->where('status', 'pending')->update([
            'status' => 'under_review',
            'updated_at' => now(),
        ]);
        $this->audit->record('moderation.report.under_review', $moderator, null, ['report_id' => $reportId]);
    }

    private function previewPayload($preview): array
    {
        if ($preview instanceof Post) {
            $author = $preview->user?->profile?->username ?? $preview->user?->username;

            return [
                'type' => 'post',
                'id' => (int) $preview->getKey(),
                'body' => $preview->body,
                'status' => $preview->deleted_at !== null ? 'removed' : $preview->status,
                'author' => $author,
                'media' => $preview->media()->get()->map(static fn (PostMedia $media) => [
                    'url' => $media->authorizedUrl(),
                    'width' => $media->width,
                    'height' => $media->height,
                ])->values()->all(),
            ];
        }

        return [
            'type' => 'comment',
            'id' => (int) $preview->getKey(),
            'body' => $preview->body,
            'author' => $preview->user?->profile?->username ?? $preview->user?->username,
            'post_id' => (int) $preview->post_id,
        ];
    }
}
