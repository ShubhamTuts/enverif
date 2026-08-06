<?php

declare(strict_types=1);

namespace App\Core\Runtime;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class InstallationState
{
    private ?array $cachedSnapshot = null;

    /** @return array{status:string,marker_exists:bool,schema_ready:bool,owner_membership_exists:bool,error:?string} */
    public function snapshot(bool $refresh = false): array
    {
        if (!$refresh && $this->cachedSnapshot !== null) {
            return $this->cachedSnapshot;
        }
        $marker = file_exists($this->markerPath());
        $schemaReady = false;
        $ownerExists = false;
        $error = null;

        try {
            $requiredTables = ['migrations', 'users', 'workspaces', 'workspace_user', 'sessions'];
            $schemaReady = collect($requiredTables)->every(fn (string $table): bool => Schema::hasTable($table));
            if ($schemaReady) {
                $ownerExists = DB::table('workspace_user')->where('role', 'owner')->exists();
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return $this->cachedSnapshot = [
            'status' => InstallationStatePolicy::classify($marker, $schemaReady, $ownerExists),
            'marker_exists' => $marker,
            'schema_ready' => $schemaReady,
            'owner_membership_exists' => $ownerExists,
            'error' => $error,
        ];
    }


    /** @return array{status:string,marker_exists:bool,schema_ready:bool,owner_membership_exists:bool,error:?string} */
    public function refresh(): array
    {
        return $this->snapshot(true);
    }

    public function isInstalled(): bool
    {
        return $this->snapshot()['status'] === InstallationStatePolicy::INSTALLED;
    }

    public function clearStaleMarker(): void
    {
        $snapshot = $this->snapshot();
        if ($snapshot['status'] === InstallationStatePolicy::STALE_MARKER && is_file($this->markerPath())) {
            @unlink($this->markerPath());
            $this->cachedSnapshot = null;
        }
    }

    public function writeMarker(string $runtimeMode, string $version): void
    {
        $directory = dirname($this->markerPath());
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($this->markerPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'version' => $version,
            'runtime_mode' => $runtimeMode,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        $this->cachedSnapshot = null;
    }

    public function removeMarker(): void
    {
        if (is_file($this->markerPath())) {
            @unlink($this->markerPath());
            $this->cachedSnapshot = null;
        }
    }

    private function markerPath(): string
    {
        return storage_path('app/installed');
    }
}
