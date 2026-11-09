<?php

namespace App\Http\Controllers\Api\V1\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\DiscoveryFeedbackRequest;
use App\Models\User;
use App\Services\Discovery\DiscoveryFeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscoveryFeedbackController extends Controller
{
    public function __construct(private readonly DiscoveryFeedbackService $feedback) {}

    /** POST /discovery/feedback — not interested / hide profile. */
    public function store(DiscoveryFeedbackRequest $request): JsonResponse
    {
        $target = User::query()->find((int) $request->validated('target_user_id'));
        if ($target === null) {
            // Service rules keep the response terse to avoid enumeration.
            return response()->json(['message' => 'Profile unavailable.'], 404);
        }

        if (! $this->feedback->canGiveFeedback($request->user(), $target)) {
            // Denial is deliberately indistinguishable from missing.
            return response()->json(['message' => 'Profile unavailable.'], 404);
        }
        $feedback = $this->feedback->save($request->user(), (int) $target->getKey(), $request->validated('feedback_type'));

        return response()->json(['message' => $request->validated('feedback_type') === 'hide_profile' ? 'Profile hidden.' : 'Feedback saved.', 'feedback' => ['target_user_id' => (int) $feedback->target_user_id, 'feedback_type' => $feedback->feedback_type]], 201);
    }

    /** DELETE /discovery/feedback/{targetUserId} — undo hide / clear feedback. */
    public function destroy(Request $request, int $targetUserId): JsonResponse
    {
        $removed = $this->feedback->remove($request->user(), $targetUserId);

        return response()->json(['message' => $removed > 0 ? 'Profile unhidden.' : 'No feedback to undo.', 'removed' => $removed]);
    }
}
