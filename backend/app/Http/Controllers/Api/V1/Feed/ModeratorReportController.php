<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Services\Feed\ContentModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Moderator workspace (Phase 06 slice): the report queue, report detail
 * and review/resolve actions. Restricted by the existing role middleware.
 */
class ModeratorReportController extends Controller
{
    public function __construct(private readonly ContentModerationService $reports) {}

    /** GET /moderator/feed-reports?status=&search=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $page = $this->reports->queue(
            (string) $request->query('status', 'open'),
            trim((string) $request->query('search', '')),
            (int) $request->query('per_page', 15),
        );

        $reports = collect($page->items())->filter(static fn ($row) => $row->reportable_type === \App\Models\Post::class || $row->reportable_type === \App\Models\PostComment::class)->values();

        return response()->json([
            'data' => $reports->map(static fn ($row): array => [
                'id' => (int) $row->id,
                'reason' => $row->reason,
                'details' => $row->details,
                'status' => $row->status,
                'created_at' => $row->created_at,
                'resolved_at' => $row->resolved_at,
                'resolved_by' => $row->resolved_by,
                'resolution_note' => $row->resolution_note,
                'reporter' => ['username' => $row->reporter_username, 'display_name' => $row->reporter_name],
                'reported_user' => ['username' => $row->reported_username, 'display_name' => $row->reported_name],
                'target' => $row->target,
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /** POST /moderator/feed-reports/{id}/review — start review. */
    public function review(Request $request, int $reportId): JsonResponse
    {
        $this->reports->takeUnderReview($request->user(), $reportId);

        return response()->json(['message' => 'Report is under review.', 'status' => 'under_review']);
    }

    /** PATCH /moderator/feed-reports/{id} — resolve/dismiss with decision. */
    public function resolve(Request $request, int $reportId): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:under_review,resolved,dismissed'],
            'decision' => ['required', 'in:remove_content,restore_content,none'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $result = $this->reports->resolve($request->user(), $reportId, $data['status'], $data['decision'], $data['note'] ?? '');

        return response()->json(['message' => 'Report updated.', 'report' => $result]);
    }
}
