<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

final class WorkflowRun extends Model
{
    use HasUuids, BelongsToWorkspace;

    protected $fillable = [
        'id','workspace_id','workflow_id','status','trigger','mode','retry_of','input','output',
        'current_node_id','context','error','started_at','finished_at','cancelled_at',
    ];

    protected function casts(): array
    {
        return ['input'=>'array','output'=>'array','context'=>'array','started_at'=>'datetime','finished_at'=>'datetime','cancelled_at'=>'datetime'];
    }

    public function workflow(){return $this->belongsTo(Workflow::class);}
    public function steps(){return $this->hasMany(WorkflowRunStep::class)->orderBy('id');}
    public function retrySource(){return $this->belongsTo(self::class,'retry_of');}
}
