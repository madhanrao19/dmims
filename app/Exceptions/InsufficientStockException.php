<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown instead of silently clamping stock to zero. A negative result means
 * overshipment, double-submission, a bad transfer, or a stale read — all of
 * which must surface, not get masked as a quietly wrong number.
 */
class InsufficientStockException extends RuntimeException {}
