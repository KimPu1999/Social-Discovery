<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Discovery\DiscoveryEligibilityService;
use App\Services\Discovery\DiscoveryFeedbackService;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

/**
 * Policy for discovery interactions (viewing and feedback). Used by the
 * discovery controllers so business access rules stay declarative. Denials
 * inside discovery are answered as generic "profile unavailable" responses
 * by the controllers to avoid an account-existence oracle.
 */
#[UsePolicy(User::class)]
class DiscoveryPolicy
{
    /** Whether the viewer may view a target's discovery profile. */
    public function view(User $viewer, User $target): bool
    {
        if ($viewer->is($target)) {
            return false;
        }

        return app(DiscoveryEligibilityService::class)->canView($viewer, $target);
    }

    public function giveFeedback(User $viewer, User $target): bool
    {
        return app(DiscoveryFeedbackService::class)->canGiveFeedback($viewer, $target);
    }
}
