<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

final class Workflow extends Model
{
    use BelongsToWorkspace;
    protected $fillable=['workspace_id','name','description','status','definition','settings','webhook_secret','version'];
    protected $hidden=['webhook_secret'];
    protected function casts():array{return ['definition'=>'array','settings'=>'array','webhook_secret'=>'encrypted'];}
    public function runs(){return $this->hasMany(WorkflowRun::class);}
    public function schedules(){return $this->hasMany(AgentSchedule::class);}
}
