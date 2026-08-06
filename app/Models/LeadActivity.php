<?php
namespace App\Models;use App\Models\Concerns\BelongsToWorkspace;use Illuminate\Database\Eloquent\Model;
class LeadActivity extends Model {use BelongsToWorkspace;protected $fillable=['workspace_id','lead_id','user_id','run_id','type','summary','data','occurred_at'];protected function casts():array{return ['data'=>'array','occurred_at'=>'datetime'];}}
