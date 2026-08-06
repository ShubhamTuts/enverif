<?php
namespace App\Models;use App\Models\Concerns\BelongsToWorkspace;use Illuminate\Database\Eloquent\Model;
class AgentSchedule extends Model {use BelongsToWorkspace;protected $fillable=['workspace_id','agent_id','workflow_id','name','cron_expression','timezone','prompt','enabled','last_run_at','next_run_at'];protected function casts():array{return ['enabled'=>'boolean','last_run_at'=>'datetime','next_run_at'=>'datetime'];}public function agent(){return $this->belongsTo(Agent::class);}public function workflow(){return $this->belongsTo(Workflow::class);}}
