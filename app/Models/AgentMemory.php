<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class AgentMemory extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'agent_id', 'key', 'value', 'tags', 'importance', 'source_run_id', 'last_used_at',
    ];

    protected function casts(): array
    {
        return ['tags' => 'array', 'importance' => 'integer', 'last_used_at' => 'datetime'];
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }
}
