<?php

namespace App\Http\Controllers;

use App\Core\Runtime\{RuntimeHealth, RuntimeProfileDetector, WebCronSignature};
use App\Models\{ConnectorConnection, ModelConnection, Workspace};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class SettingsController extends Controller
{
    public function edit(Request $request, RuntimeHealth $health)
    {
        $snapshot = $health->snapshot();
        $heartbeatAt = data_get($snapshot, 'heartbeat.at');
        $heartbeatAge = null;
        if ($heartbeatAt) {
            try { $heartbeatAge = now()->diffInSeconds(\Carbon\Carbon::parse($heartbeatAt)); } catch (\Throwable) {}
        }
        $snapshot['heartbeat_age_seconds'] = $heartbeatAge;
        $snapshot['scheduler_healthy'] = $heartbeatAge !== null && $heartbeatAge <= 180;

        $webCronUrl = null;
        $webCronSecret = (string) config('enverif.runtime.web_cron.secret', '');
        if ((bool) config('enverif.runtime.web_cron.enabled', false) && strlen($webCronSecret) >= 32) {
            $webCronUrl = route('system.web-cron', ['token' => WebCronSignature::stableToken($webCronSecret)]);
        }

        return view('settings.index', [
            'workspace' => Workspace::findOrFail((int) session('workspace_id')),
            'health' => $snapshot,
            'models' => ModelConnection::where('enabled', true)->orderBy('provider')->get(),
            'emailConnections' => ConnectorConnection::where('enabled', true)->whereIn('driver', ['gmail', 'outlook', 'smtp'])->orderBy('name')->get(),
            'integrationCount' => ConnectorConnection::where('enabled', true)->count(),
            'cronCommand' => 'php ' . base_path('artisan') . ' enverif:tick',
            'webCronUrl' => $webCronUrl,
            'isCompatibilityMode' => $snapshot['runtime_mode'] === RuntimeProfileDetector::COMPATIBILITY,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'workspace_name' => 'required|max:120',
            'timezone' => 'required|max:64',
            'locale' => 'required|in:en,fr,nl',
            'user_name' => 'required|max:120',
            'user_locale' => 'required|in:en,fr,nl',
            'theme' => 'required|in:system,light,dark',
        ]);
        if (!in_array($data['timezone'], timezone_identifiers_list(), true)) {
            return back()->withErrors(['timezone' => 'Use a valid IANA timezone such as Europe/Amsterdam or Asia/Kolkata.'])->withInput();
        }
        $workspace = Workspace::findOrFail((int) session('workspace_id'));
        abort_unless($request->user()->workspaces()->where('workspaces.id', $workspace->id)->wherePivotIn('role', ['owner', 'admin'])->exists(), 403);
        $workspace->update(['name' => $data['workspace_name'], 'timezone' => $data['timezone'], 'locale' => $data['locale']]);
        $request->user()->update(['name' => $data['user_name'], 'locale' => $data['user_locale'], 'theme' => $data['theme']]);
        session(['locale' => $data['user_locale']]);
        return back()->with('status', __('ui.saved'));
    }

    public function password(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);
        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();
        return back()->with('status', __('ui.password_updated'));
    }

    public function switchWorkspace(Request $request)
    {
        $data = $request->validate(['workspace_id' => 'required|integer']);
        $workspace = $request->user()->workspaces()->where('workspaces.id', $data['workspace_id'])->firstOrFail();
        session(['workspace_id' => $workspace->id]);
        return redirect()->route('chat.index');
    }

    public function locale(Request $request)
    {
        $data = $request->validate(['locale' => 'required|in:en,fr,nl']);
        $request->user()->update(['locale' => $data['locale']]);
        session(['locale' => $data['locale']]);
        return back();
    }
}
