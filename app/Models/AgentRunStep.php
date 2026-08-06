<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class AgentRunStep extends Model {protected $fillable=['run_id','sequence','type','status','tool','risk_level','input','output','usage','started_at','finished_at'];protected function casts():array{return ['input'=>'array','usage'=>'array','started_at'=>'datetime','finished_at'=>'datetime'];}public function run(){return $this->belongsTo(AgentRun::class,'run_id');}}
