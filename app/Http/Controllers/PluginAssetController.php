<?php

namespace App\Http\Controllers;

use App\Core\Plugins\PluginRegistry;

final class PluginAssetController extends Controller
{
    public function __invoke(string $plugin, string $file, PluginRegistry $plugins)
    {
        $path = $plugins->assetPath($plugin, $file);
        abort_unless($path, 404);
        return response()->file($path, ['Cache-Control' => 'public, max-age=86400']);
    }
}
