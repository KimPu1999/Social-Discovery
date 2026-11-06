<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function record(string $action, ?User $actor = null, ?object $auditable = null, array $metadata = [], ?string $ip = null): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $actor?->id,
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $ip,
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata ? json_encode($metadata, JSON_THROW_ON_ERROR) : null,
            'created_at' => now(),
        ]);
    }
}
