<?php

namespace AppSupport\Tests\Support;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Logger for the tests. The real one ends the process on critical errors (Log::critical), so this one
 * throws a LoggedError instead, and keeps the other messages to check them.
 */
final class TestLogger extends AbstractLogger
{
    /** @var array<int, array{string, string}> level and message of what was logged without failing */
    public array $messages = [];

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL], true)) {
            throw new LoggedError((string)$message, $level);
        }

        $this->messages[] = [$level, (string)$message];
    }

    /** Whether a message of the level that contains the text has been logged */
    public function has(string $level, string $text): bool
    {
        foreach ($this->messages as [$messageLevel, $message]) {
            if ($messageLevel === $level && str_contains($message, $text)) {
                return true;
            }
        }

        return false;
    }

    public function incTab(string $level = LogLevel::EMERGENCY): void
    {
    }

    public function decTab(string $level = LogLevel::EMERGENCY): void
    {
    }
}
