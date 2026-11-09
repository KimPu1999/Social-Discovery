<?php

namespace App\Models;

use App\Enums\ConnectionRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Friend (connection) request between two users. Declined and cancelled
 * rows are kept as history; only one pending request may exist per
 * unordered pair thanks to the partial unique pair indexes.
 */
class ConnectionRequest extends Model
{
    protected $fillable = ['sender_id', 'receiver_id', 'status', 'responded_at'];

    protected function casts(): array
    {
        return [
            'status' => ConnectionRequestStatus::class,
            'responded_at' => 'datetime',
        ];
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /** Is this request unresolved? */
    public function isPending(): bool
    {
        return $this->status === ConnectionRequestStatus::Pending;
    }
}
