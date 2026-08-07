<?php

namespace App\Core\Plugins;

use Illuminate\Support\Facades\File;

final class ExternalPluginUninstaller
{
    public function __construct(
        private readonly PluginRegistry $plugins,
        private readonly PluginDependencyInspector $dependencies,
    ) {}

    public function uninstall(string $slug): void
    {
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) throw new \InvalidArgumentException('Invalid plugin slug.');

        $meta = $this->plugins->metadata($slug);
        if ($meta === []) throw new \InvalidArgumentException('Plugin not found.');

        $directory = realpath((string) ($meta['_directory'] ?? ''));
        $externalRoot = realpath(base_path('plugins/external'));
        $builtinRoot = realpath(base_path('plugins/builtin'));
        if ($directory === false) throw new \RuntimeException('Plugin directory is missing.');

        if ($builtinRoot && ($directory === $builtinRoot || str_starts_with($directory, $builtinRoot.DIRECTORY_SEPARATOR))) {
            throw new \RuntimeException('Built-in plugins cannot be uninstalled. Disable their configured connections instead.');
        }
        if (!$externalRoot || !str_starts_with($directory, $externalRoot.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('External plugin path is outside the managed plugins/external directory.');
        }

        $dependencies = $this->dependencies->forPlugin($slug);
        if ((int) ($dependencies['blocking_count'] ?? 0) > 0) {
            throw new \RuntimeException('Plugin cannot be uninstalled while configured connections or live dependencies still use it.');
        }

        if (!File::deleteDirectory($directory)) throw new \RuntimeException('Plugin directory could not be removed. Check filesystem permissions.');
    }
}
