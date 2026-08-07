<?php

namespace App\Http\Middleware;

use App\Models\ChatThread;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serializes chat submissions long enough for the controller to persist/start a run.
 * This closes the race where two browser requests both observe "no active run".
 */
final class LockChatSubmission
{
    public function handle(Request $request, Closure $next): Response
    {
        $thread = $request->route('thread');
        $key = $thread instanceof ChatThread
            ? 'enverif:chat-submit:thread:'.$thread->id
            : 'enverif:chat-submit:new-user:'.(int) $request->user()?->id;
        $lock = Cache::lock($key, 30);
        if (!$lock->get()) abort(409, 'Another message is already being submitted for this chat.');

        try {
            return $next($request);
        } finally {
            $lock->release();
        }
    }
}
