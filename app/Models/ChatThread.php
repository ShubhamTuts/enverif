<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class ChatThread extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'agent_id', // legacy compatibility for 1.1.x threads
        'default_agent_id',
        'default_model_connection_id',
        'default_model',
        'default_effort',
        'title',
        'summary',
        'last_message_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }
    public function agent() { return $this->belongsTo(Agent::class, 'default_agent_id'); }
    public function defaultAgent() { return $this->belongsTo(Agent::class, 'default_agent_id'); }
    public function modelConnection() { return $this->belongsTo(ModelConnection::class, 'default_model_connection_id'); }
    public function messages() { return $this->hasMany(ChatMessage::class, 'thread_id')->orderBy('id'); }
    public function attachments() { return $this->hasMany(ChatAttachment::class, 'thread_id'); }
}
