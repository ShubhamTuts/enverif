<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class AgentMessage extends Model {protected $fillable=['run_id','role','content','meta'];protected function casts():array{return ['meta'=>'array'];}}
