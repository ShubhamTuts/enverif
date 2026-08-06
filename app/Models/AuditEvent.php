<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class AuditEvent extends Model {public $timestamps=false;protected $fillable=['workspace_id','actor_user_id','run_id','event','subject_type','subject_id','data','previous_hash','hash','created_at'];protected function casts():array{return ['data'=>'array','created_at'=>'datetime'];}}
