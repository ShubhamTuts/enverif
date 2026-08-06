<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class WorkflowRunStep extends Model
{
    protected $fillable=['workflow_run_id','node_id','node_type','status','input','output','error','started_at','finished_at'];
    protected function casts():array{return ['input'=>'array','output'=>'array','started_at'=>'datetime','finished_at'=>'datetime'];}
    public function run(){return $this->belongsTo(WorkflowRun::class,'workflow_run_id');}
}
