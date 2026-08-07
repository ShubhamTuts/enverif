<?php

namespace App\Support;

use Closure;
use LogicException;

final class WorkspaceContext
{
    private ?int $workspaceId = null;

    public function set(?int $id): void
    {
        $this->workspaceId = $id !== null ? (int) $id : null;
    }

    public function clear(): void
    {
        $this->workspaceId = null;
    }

    public function id(): ?int
    {
        return $this->workspaceId;
    }

    public function requireId(): int
    {
        if ($this->workspaceId === null) {
            throw new LogicException('Workspace context is required for tenant-scoped queries. Use withoutGlobalScopes() only from an explicit administrative path.');
        }

        return $this->workspaceId;
    }

    public function has(): bool
    {
        return $this->workspaceId !== null;
    }

    public function run(int $workspaceId, Closure $callback): mixed
    {
        $previous = $this->workspaceId;
        $this->workspaceId = $workspaceId;

        try {
            return $callback();
        } finally {
            $this->workspaceId = $previous;
        }
    }
}
