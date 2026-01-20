<?php

declare(strict_types=1);

namespace Retina\Issue;

enum IssueSeverity: string
{
    case ERROR = 'error';
    case WARNING = 'warning';
    case INFO = 'info';
    case HINT = 'hint';

    public function getLabel(): string
    {
        return match ($this) {
            self::ERROR => 'Error',
            self::WARNING => 'Warning',
            self::INFO => 'Info',
            self::HINT => 'Hint',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::ERROR => '❌',
            self::WARNING => '⚠️',
            self::INFO => 'ℹ️',
            self::HINT => '💡',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::ERROR => 'red',
            self::WARNING => 'yellow',
            self::INFO => 'blue',
            self::HINT => 'gray',
        };
    }

    public function getPriority(): int
    {
        return match ($this) {
            self::ERROR => 4,
            self::WARNING => 3,
            self::INFO => 2,
            self::HINT => 1,
        };
    }
}
