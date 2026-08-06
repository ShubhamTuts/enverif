<?php
namespace App\Models;use App\Models\Concerns\BelongsToWorkspace;use Illuminate\Database\Eloquent\Model;
class Approval extends Model {use BelongsToWorkspace;protected $fillable=['workspace_id','run_id','workflow_run_id','run_step_id','workflow_run_step_id','action','risk_level','summary','payload','status','decided_by','decision_note','decided_at'];protected function casts():array{return ['payload'=>'array','decided_at'=>'datetime'];}}
