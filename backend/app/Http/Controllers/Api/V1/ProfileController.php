<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Resources\ProfileResource;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class ProfileController
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function show(Request $request): JsonResponse { return response()->json(['profile' => new ProfileResource($request->user()->profile()->with('user.interests')->firstOrFail())]); }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($request, $user): void { $user->update(['name' => $request->string('display_name')->toString(), 'username' => strtolower($request->string('username')->toString())]); $user->profile()->updateOrCreate([], $request->safe()->only(['display_name', 'username', 'bio', 'location', 'website_url'])); });
        return response()->json(['profile' => new ProfileResource($user->profile()->with('user.interests')->firstOrFail())]);
    }

    public function avatar(Request $request): JsonResponse { return $this->upload($request, 'avatar', 'avatar_path', 'profile.avatar'); }
    public function cover(Request $request): JsonResponse { return $this->upload($request, 'cover', 'cover_image_path', 'profile.cover'); }

    private function upload(Request $request, string $field, string $column, string $auditAction): JsonResponse
    {
        $request->validate([$field => [File::image()->max('5mb')]]);
        $profile = $request->user()->profile()->firstOrCreate(['user_id' => $request->user()->id], ['username' => $request->user()->username, 'display_name' => $request->user()->name]);
        if ($profile->{$column}) Storage::disk('public')->delete($profile->{$column});
        $path = $request->file($field)->store('profiles/'.$request->user()->id, 'public');
        $profile->update([$column => $path]);
        $this->audit->record($auditAction, $request->user(), $profile, [], $request->ip());
        return response()->json(['profile' => new ProfileResource($profile->fresh('user.interests'))]);
    }

    public function removeAvatar(Request $request): JsonResponse { return $this->removeMedia($request, 'avatar_path'); }
    public function removeCover(Request $request): JsonResponse { return $this->removeMedia($request, 'cover_image_path'); }
    private function removeMedia(Request $request, string $column): JsonResponse { $profile = $request->user()->profile()->firstOrFail(); if ($profile->{$column}) Storage::disk('public')->delete($profile->{$column}); $profile->update([$column => null]); return response()->json(['message' => 'Image removed.']); }
}
