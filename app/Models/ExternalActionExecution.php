<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

final class ExternalActionExecution extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'run_type', 'run_id', 'step_key', 'action', 'arguments_hash',
        'status', 'result', 'external_id', 'error_class', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
