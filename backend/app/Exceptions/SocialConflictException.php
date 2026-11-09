<?php

namespace App\Exceptions;

use Exception;

/**
 * Domain-level conflict between two social actors (duplicate request,
 * already connected, blocking interference, state change mid-flight).
 * Rendered as a 409 JSON response with a safe message.
 */
class SocialConflictException extends Exception {}
