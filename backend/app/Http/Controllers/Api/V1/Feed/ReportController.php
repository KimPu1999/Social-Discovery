<?php

namespace App\Http\Controllers\Api\V1\Feed;

use App\Http\Controllers\Controller;
use App\Http\Requests\Feed\ReportContentRequest;
use App\Models\Post;
use App\Models\PostComment;
use App\Services\Feed\PostAccessService;
use App\Services\Feed\ContentModerationService;
use Illuminate\Http\JsonResponse;

/**
 * Viewer-facing report endpoints. Reports are private to authorized
 * reviewers; the reporter only ever receives an opaque id back.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ContentModerationService $reports,
        private readonly PostAccessService $access,
    ) {}

    /** POST /posts/{post}/report */
    public function reportPost(ReportContentRequest $request, Post $post): JsonResponse
    {
        // Reporting something you cannot see reveals nothing — 404 keeps parity.
        if (! $this->access->canView($request->user(), $post)) {
            abort(404);
        }

        $result = $this->reports->report($request->user(), $post, $request->validated('reason'), $request->validated('details'));

        return response()->json([
            'message' => $result['duplicate'] ? 'You already reported this post.' : 'Report received. Thank you for keeping the community safe.',
            'report_id' => $result['id'],
            'duplicate' => $result['duplicate'],
        ], $result['duplicate'] ? 200 : 201);
    }

    /** POST /comments/{comment}/report */
    public function reportComment(ReportContentRequest $request, PostComment $comment): JsonResponse
    {
        if (! $this->access->canViewComment($request->user(), $comment)) {
            abort(404);
        }

        $result = $this->reports->report($request->user(), $comment, $request->validated('reason'), $request->validated('details'));

        return response()->json([
            'message' => $result['duplicate'] ? 'You already reported this comment.' : 'Report received. Thank you for keeping the community safe.',
            'report_id' => $result['id'],
            'duplicate' => $result['duplicate'],
        ], $result['duplicate'] ? 200 : 201);
    }
}
