<?php

namespace AppSupport\Tests\Support;

/**
 * Thrown by TestLogger instead of ending the process, when the application logs a fatal error
 */
final class LoggedError extends \RuntimeException
{
    public function __construct(string $message, public readonly string $level)
    {
        parent::__construct($message);
    }
}
