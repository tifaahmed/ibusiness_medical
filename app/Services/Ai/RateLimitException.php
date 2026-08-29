<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Thrown when the upstream AI provider answers 429 (per-minute rate limit or
 * quota reached). The bulk sweeps catch this specifically so they can pause and
 * retry the same slice instead of burning through the work list with errors.
 */
class RateLimitException extends RuntimeException {}
