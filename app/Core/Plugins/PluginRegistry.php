<?php

namespace App\Core\Plugins;

use App\Core\Connectors\Contracts\ConnectorDriver;
use Illuminate\Support\Facades\Log;

final class PluginRegistry
{
    /** @var array<string,array<string,mixed>> */
    private array $metadata = [];

    private bool $manifestMetadataLoaded = false;

    /** @return array<string,ConnectorDriver> */
    public function connectorDrivers(): array
    {
        $this->ensureManifestMetadata();

        $drivers = [];
        foreach ($this->metadata as $manifestKey => $manifest) {
            // Driver aliases are added to $metadata below. Only instantiate the canonical
            // manifest entry keyed by its declared slug so a driver is never bootstrapped twice.
            if (($manifest['slug'] ?? null) !== $manifestKey) {
                continue;
            }

            try {
                $dir = (string) ($manifest['_directory'] ?? '');
                if (!empty($manifest['bootstrap'])) {
                    $bootstrap = $dir . DIRECTORY_SEPARATOR . $manifest['bootstrap'];
                    $realDir = realpath($dir);
                    $realBootstrap = realpath($bootstrap);
                    if (!$realDir || !$realBootstrap || !str_starts_with($realBootstrap, $realDir . DIRECTORY_SEPARATOR)) {
                        throw new \RuntimeException('Plugin bootstrap escapes its plugin directory.');
                    }
                    require_once $realBootstrap;
                }

                $class = (string) $manifest['driver'];
                if (!class_exists($class)) {
                    throw new \RuntimeException("Plugin driver class not found: {$class}");
                }

                $driver = app($class);
                if (!$driver instanceof ConnectorDriver) {
                    throw new \RuntimeException("Plugin driver must implement ConnectorDriver: {$class}");
                }

                $id = $driver->id();
                $drivers[$id] = $driver;
                $this->metadata[$id] = $manifest;
            } catch (\Throwable $e) {
                Log::warning('Enverif plugin was skipped', [
                    'manifest' => $manifest['_manifest'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $drivers;
    }

    /** @return array<string,mixed> */
    public function metadata(string $id): array
    {
        $this->ensureManifestMetadata();
        return $this->metadata[$id] ?? [];
    }

    public function assetPath(string $slug, string $file): ?string
    {
        $this->ensureManifestMetadata();

        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)
            || !preg_match('/^[A-Za-z0-9._-]+$/', $file)) {
            return null;
        }

        $manifest = $this->metadata[$slug] ?? null;
        if (!$manifest) {
            return null;
        }

        $icon = (string) ($manifest['icon'] ?? '');
        if ($icon === '' || preg_match('#^https://#i', $icon) || basename($icon) !== $file) {
            return null;
        }

        $directory = (string) ($manifest['_directory'] ?? '');
        $dir = realpath($directory);
        $path = realpath($directory . DIRECTORY_SEPARATOR . $icon);
        if (!$dir || !$path || !str_starts_with($path, $dir . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return null;
        }

        return $path;
    }

    private function ensureManifestMetadata(): void
    {
        if ($this->manifestMetadataLoaded) {
            return;
        }
        $this->manifestMetadataLoaded = true;

        foreach ($this->manifestPaths() as $manifestPath) {
            try {
                $raw = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
                $manifest = PluginManifestValidator::validate($raw);
                $manifest['_directory'] = dirname($manifestPath);
                $manifest['_manifest'] = $manifestPath;
                $this->metadata[(string) $manifest['slug']] = $manifest;
            } catch (\Throwable $e) {
                Log::warning('Enverif plugin manifest was skipped', [
                    'manifest' => $manifestPath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /** @return list<string> */
    private function manifestPaths(): array
    {
        $paths = [];
        foreach ([base_path('plugins/builtin'), base_path('plugins/external')] as $root) {
            if (!is_dir($root)) {
                continue;
            }
            foreach (glob($root . '/*/enverif.json') ?: [] as $path) {
                $paths[] = $path;
            }
        }
        sort($paths);
        return $paths;
    }
}
