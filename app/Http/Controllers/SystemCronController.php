<?php

namespace App\Http\Controllers;

use App\Core\Runtime\TickRunner;
use App\Core\Runtime\WebCronSignature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class SystemCronController extends Controller
{
    public function __invoke(Request $request, TickRunner $runner, ?string $token = null): JsonResponse
    {
        abort_unless((bool) config('enverif.runtime.web_cron.enabled', false), 404);
        $secret = (string) config('enverif.runtime.web_cron.secret', '');
        abort_unless(strlen($secret) >= 32, 403);

        if ($token !== null) {
            abort_unless((bool) preg_match('/^[a-f0-9]{64}$/', $token), 404);
            abort_unless(WebCronSignature::verifyStable($secret, $token), 403);
        } else {
            $timestamp = (int) $request->query('ts', 0);
            $nonce = (string) $request->query('nonce', '');
            $signature = (string) $request->query('sig', '');
            abort_unless(WebCronSignature::verify($secret, $timestamp, $nonce, $signature), 403);
            abort_unless((bool) preg_match('/^[A-Za-z0-9_-]{12,120}$/', $nonce), 422);
            abort_unless(Cache::add('enverif:webcron:nonce:' . hash('sha256', $nonce), 1, now()->addMinutes(10)), 409);
        }

        $runner->run();

        return response()->json(['ok' => true]);
    }
}
