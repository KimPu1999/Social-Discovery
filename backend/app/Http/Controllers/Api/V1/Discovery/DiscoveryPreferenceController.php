<?php

namespace App\Http\Controllers\Api\V1\Discovery;

use App\Http\Controllers\Controller;
use App\Http\Requests\Discovery\DiscoveryPreferencesRequest;
use App\Http\Resources\Discovery\DiscoveryPreferenceResource;
use App\Services\AuditLogger;
use App\Services\Discovery\DiscoveryEligibilityService;
use App\Services\Discovery\DiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Canonical discovery-consent settings (single source of truth), with the
 * Phase 02 privacy row mirrored on write so legacy settings stays valid.
 */
class DiscoveryPreferenceController extends Controller
{
    public function __construct(
        private readonly DiscoveryService $discovery,
        private readonly AuditLogger $audit,
    ) {}

    /** GET /discovery/preferences */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['preferences' => new DiscoveryPreferenceResource($this->discovery->preferencesFor($request->user()))]);
    }

    /** PUT /discovery/preferences */
    public function update(DiscoveryPreferencesRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        $preference = $user->discoveryPreference()->firstOrNew(['user_id' => $user->getKey()]);
        $preference->show_in_recommendations = $validated['show_in_recommendations'] ?? ($preference->exists ? $preference->show_in_recommendations : true);
        $preference->preferred_interest_categories = array_key_exists('preferred_interest_categories', $validated)
            ? $validated['preferred_interest_categories']
            : ($preference->preferred_interest_categories ?? []);
        $preference->save();

        // Keep consent authoritative in exactly one place; mirror when written.
        if (array_key_exists('discovery_enabled', $validated)) {
            app(DiscoveryEligibilityService::class)->syncConsent($user, (bool) $validated['discovery_enabled']);
        }
        $preference->refresh();

        $this->audit->record('discovery_preferences_changed', $user, $preference, ['discovery_enabled' => $preference->discovery_enabled], $request->ip());

        return response()->json(['preferences' => new DiscoveryPreferenceResource($preference)]);
    }
}
