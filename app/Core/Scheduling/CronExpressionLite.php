<?php

declare(strict_types=1);

namespace App\Core\Scheduling;

use InvalidArgumentException;

final class CronExpressionLite
{
    private function __construct(private readonly string $expression) {}

    public static function fromString(string $expression): self
    {
        $expression = trim(preg_replace('/\s+/', ' ', $expression) ?? $expression);
        $parts = explode(' ', $expression);
        if (count($parts) !== 5) {
            throw new InvalidArgumentException('Cron expression must contain exactly five fields.');
        }
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/^[0-9*?,\/\-]+$/', $part) !== 1) {
                throw new InvalidArgumentException('Cron expression contains invalid characters.');
            }
        }
        return new self($expression);
    }

    public function expression(): string
    {
        return $this->expression;
    }
}
