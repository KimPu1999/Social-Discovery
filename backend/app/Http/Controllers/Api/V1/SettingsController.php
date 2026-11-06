<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PrivacyUpdateRequest;
use App\Http\Requests\SettingsUpdateRequest;
use App\Http\Resources\SettingsResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function show(Request $request): JsonResponse { return response()->json(['settings' => new SettingsResource($request->user()->settings()->firstOrCreate(['user_id' => $request->user()->id]))]); }
    public function update(SettingsUpdateRequest $request): JsonResponse { $settings = $request->user()->settings()->updateOrCreate([], $request->validated()); return response()->json(['settings' => new SettingsResource($settings)]); }
    public function privacy(Request $request): JsonResponse { return response()->json(['privacy' => $request->user()->privacySettings()->firstOrCreate(['user_id' => $request->user()->id])]); }
    public function updatePrivacy(PrivacyUpdateRequest $request): JsonResponse { $privacy = $request->user()->privacySettings()->updateOrCreate([], $request->validated()); $this->audit->record('privacy_settings_changed', $request->user(), $privacy, [], $request->ip()); return response()->json(['privacy' => $privacy]); }
}
