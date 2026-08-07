<?php

namespace App\Http\Controllers;

use App\Core\Mcp\McpClient;
use App\Core\Security\OutboundEndpointPolicy;
use App\Models\McpServer;
use App\Support\EncryptedCredentials;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class McpServerController extends Controller
{
    public function index()
    {
        return view('mcp.index', ['servers' => McpServer::latest()->paginate(20)]);
    }

    public function create()
    {
        return view('mcp.form', ['server' => new McpServer(['transport' => 'http'])]);
    }

    public function store(Request $r, OutboundEndpointPolicy $endpoints)
    {
        McpServer::create($this->data($r, $endpoints));
        return redirect()->route('mcp.index')->with('status', __('ui.saved'));
    }

    public function edit(McpServer $mcp)
    {
        return view('mcp.form', ['server' => $mcp]);
    }

    public function update(Request $r, McpServer $mcp, OutboundEndpointPolicy $endpoints)
    {
        $mcp->update($this->data($r, $endpoints, $mcp));
        return redirect()->route('mcp.index')->with('status', __('ui.saved'));
    }

    public function test(McpServer $mcp, OutboundEndpointPolicy $endpoints)
    {
        try {
            $endpoints->assertAllowed((string) $mcp->endpoint);
            $data = (new McpClient($mcp))->discover();
            return back()->with('status', 'MCP connected: '.($data['serverInfo']['name'] ?? $mcp->name));
        } catch (\Throwable $e) {
            $message = EncryptedCredentials::isDecryptFailure($e)
                ? EncryptedCredentials::MCP_DECRYPT_MESSAGE
                : ('MCP connection failed: '.$e->getMessage());
            return back()->with('error', $message);
        }
    }

    public function destroy(McpServer $mcp)
    {
        $mcp->delete();
        return back()->with('status', __('ui.deleted'));
    }

    private function data(Request $r, OutboundEndpointPolicy $endpoints, ?McpServer $existing = null): array
    {
        $d = $r->validate([
            'name' => 'required|max:120',
            'transport' => 'required|in:http',
            'endpoint' => 'required|url|max:1000',
            'token' => 'nullable|max:1000',
            'protocol_version' => 'required|in:2025-11-25,2026-07-28',
            'enabled' => 'nullable|boolean',
        ]);
        $endpoints->assertAllowed((string) $d['endpoint']);
        if ($existing && trim((string) $existing->endpoint) !== trim((string) $d['endpoint']) && empty($d['token'])) {
            throw ValidationException::withMessages([
                'token' => 'Re-enter the bearer token when changing the endpoint so a stored secret cannot be silently forwarded to a new host.',
            ]);
        }

        $credentials = [];
        if ($existing) {
            try {
                $credentials = $existing->decryptedCredentials();
            } catch (\Throwable $e) {
                if (! EncryptedCredentials::isDecryptFailure($e)) throw $e;
                if (empty($d['token'])) throw ValidationException::withMessages(['token' => EncryptedCredentials::MCP_DECRYPT_MESSAGE]);
                $credentials = [];
            }
        }
        if (! empty($d['token'])) $credentials['token'] = $d['token'];

        return [
            'name' => $d['name'],
            'transport' => $d['transport'],
            'endpoint' => $d['endpoint'],
            'credentials' => $credentials,
            'configuration' => ['protocol_version' => $d['protocol_version']],
            'enabled' => $r->boolean('enabled', true),
        ];
    }
}
