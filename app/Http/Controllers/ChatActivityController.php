<?php

namespace App\Http\Controllers;

use App\Core\Agents\RunProjection;
use App\Models\ChatThread;
use Illuminate\Http\Request;

final class ChatActivityController extends Controller
{
    public function __invoke(Request $request, ChatThread $thread, RunProjection $projection)
    {
        abort_unless((int) $thread->user_id === (int) $request->user()->id, 403);
        $runId = (string) $thread->messages()->whereNotNull('run_id')->latest('id')->value('run_id');

        return response()->json($projection->forRun($runId !== '' ? $runId : null));
    }
}
