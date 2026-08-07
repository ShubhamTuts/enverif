<?php

namespace App\Http\Controllers;

use App\Core\Audit\AuditLogger;
use App\Core\Plugins\{ExternalPluginUninstaller,PluginDependencyInspector,PluginRegistry};
use Illuminate\Http\Request;

final class PluginController extends Controller
{
    public function dependencies(string $plugin, PluginDependencyInspector $dependencies)
    {
        return response()->json($dependencies->forPlugin($plugin));
    }

    public function destroy(
        Request $request,
        string $plugin,
        PluginRegistry $registry,
        PluginDependencyInspector $dependencies,
        ExternalPluginUninstaller $uninstaller,
        AuditLogger $audit,
    ) {
        $meta = $registry->metadata($plugin);
        abort_if($meta === [], 404);
        $summary = $dependencies->forPlugin($plugin);
        if ((int) ($summary['blocking_count'] ?? 0) > 0) {
            $message = 'Remove configured connections and live dependencies before uninstalling this plugin.';
            if ($request->expectsJson()) return response()->json(['message'=>$message,'dependencies'=>$summary], 409);
            abort(409, $message);
        }

        $name = (string) ($meta['name'] ?? $plugin);
        $uninstaller->uninstall($plugin);
        $audit->record(
            (int) session('workspace_id'),
            'plugin.uninstalled',
            'plugin',
            $plugin,
            ['name'=>$name,'version'=>$meta['version']??null],
            null,
            $request->user()?->id,
        );

        if ($request->expectsJson()) return response()->json(['ok'=>true]);
        return redirect()->route('connectors.index')->with('status', "{$name} uninstalled.");
    }
}
