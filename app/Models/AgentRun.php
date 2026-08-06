<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AgentRun extends Model
{
    use HasUuids, BelongsToWorkspace;

    protected $fillable = [
        'id','workspace_id','agent_id','parent_run_id','status','input','output','stop_reason',
        'step_count','input_tokens','output_tokens','estimated_cost_usd','started_at','finished_at',
        'cancelled_at','context',
    ];

    protected function casts(): array
    {
        return [
            'context'=>'array','started_at'=>'datetime','finished_at'=>'datetime','cancelled_at'=>'datetime',
            'estimated_cost_usd'=>'decimal:6',
        ];
    }

    public function agent(){return $this->belongsTo(Agent::class);}
    public function steps(){return $this->hasMany(AgentRunStep::class,'run_id')->orderBy('sequence');}
    public function messages(){return $this->hasMany(AgentMessage::class,'run_id');}
    public function parent(){return $this->belongsTo(self::class,'parent_run_id');}
    public function children(){return $this->hasMany(self::class,'parent_run_id');}
}
