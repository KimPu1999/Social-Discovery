<?php

namespace App\Enums;

/**
 * TitleCase-backed enum persisted as snake_case strings.
 */
enum ConnectionRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
}
