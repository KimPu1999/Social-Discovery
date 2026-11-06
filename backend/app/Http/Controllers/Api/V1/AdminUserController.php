<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RoleUpdateRequest;
use App\Http\Requests\StatusUpdateRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController
{
    public function __construct(private readonly AuditLogger $audit) {}
    public function index(Request $request): JsonResponse { return response()->json(['users' => UserResource::collection(User::query()->latest()->paginate($request->integer('per_page', 20)))]); }
    public function show(int $id): JsonResponse { return response()->json(['user' => new UserResource(User::with('profile')->findOrFail($id))]); }
    public function status(StatusUpdateRequest $request, User $user): JsonResponse { abort_if($user->isSuperAdmin(), 403, 'Super Admin accounts require a separate security process.'); $user->forceFill(['status' => $request->string('status')->toString()])->save(); $this->audit->record('account_status_changed', $request->user(), $user, ['status' => $user->status], $request->ip()); return response()->json(['user' => new UserResource($user)]); }
    public function role(RoleUpdateRequest $request, User $user): JsonResponse { abort_if($user->isSuperAdmin(), 403, 'Super Admin accounts cannot be changed here.'); $user->forceFill(['role' => $request->string('role')->toString()])->save(); $this->audit->record('role_changed', $request->user(), $user, ['role' => $user->role], $request->ip()); return response()->json(['user' => new UserResource($user)]); }
}
