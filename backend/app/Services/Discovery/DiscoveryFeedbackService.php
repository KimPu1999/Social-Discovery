<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryFeedback;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Owns discovery feedback: hide profiles, flag "not interested" results,
 * prevent duplicates and reverse decisions on request. Hidden targets
 * stay out of every discovery query through the eligibility service.
 */
class DiscoveryFeedbackService
{
    public function __construct(private readonly DiscoveryEligibilityService $eligibility) {}

    /**
     * Persist viewer feedback for a target. One row per viewer/target
     * pair: sending feedback again updates the type rather than
     * duplicating, and self-feedback is rejected.
     */
    public function save(User $viewer, int $targetUserId, string $feedbackType): DiscoveryFeedback
    {
        return DB::transaction(function () use ($viewer, $targetUserId, $feedbackType): DiscoveryFeedback {
            $feedback = DiscoveryFeedback::query()->updateOrCreate(
                ['user_id' => $viewer->getKey(), 'target_user_id' => $targetUserId],
                ['feedback_type' => $feedbackType],
            );

            return $feedback;
        });
    }

    /** Undo any feedback the viewer gave a target. Returns the number of removed rows. */
    public function remove(User $viewer, int $targetUserId): int
    {
        return DiscoveryFeedback::query()
            ->where('user_id', $viewer->getKey())
            ->where('target_user_id', $targetUserId)
            ->delete();
    }

    /** Whether the target can receive feedback from this viewer. */
    public function canGiveFeedback(User $viewer, User $target): bool
    {
        return ! $viewer->is($target)
            && $target->status === 'active'
            && $viewer->blockedBy()->where('user_id', $target->getKey())->doesntExist()
            && $viewer->blockedUsers()->where('blocked_user_id', $target->getKey())->doesntExist();
    }
}
