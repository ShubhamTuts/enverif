<?php

namespace App\Http\Controllers;

use App\Models\ChatAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

final class ChatAttachmentController extends Controller
{
    public function show(Request $request, ChatAttachment $attachment)
    {
        abort_unless((int) $attachment->user_id === (int) $request->user()->id, 403);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type ?: 'application/octet-stream'],
        );
    }
}
