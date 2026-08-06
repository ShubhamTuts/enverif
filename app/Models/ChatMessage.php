<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    protected $fillable = ['thread_id', 'run_id', 'role', 'kind', 'status', 'content', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    public function thread() { return $this->belongsTo(ChatThread::class, 'thread_id'); }
    public function run() { return $this->belongsTo(AgentRun::class, 'run_id'); }
    public function attachments() { return $this->hasMany(ChatAttachment::class, 'message_id'); }
}
