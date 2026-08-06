<?php

declare(strict_types=1);

namespace App\Core\Agents\Contracts;

enum RiskLevel: string
{
    case Read = 'read';
    case InternalWrite = 'internal_write';
    case ExternalWrite = 'external_write';
    case Destructive = 'destructive';
    case Network = 'network';
    case Secrets = 'secrets';
}
