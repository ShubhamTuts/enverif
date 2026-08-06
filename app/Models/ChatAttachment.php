<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

final class ChatAttachment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'user_id', 'thread_id', 'message_id', 'disk', 'path',
        'original_name', 'mime_type', 'size_bytes', 'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function thread() { return $this->belongsTo(ChatThread::class, 'thread_id'); }
    public function message() { return $this->belongsTo(ChatMessage::class, 'message_id'); }
}
