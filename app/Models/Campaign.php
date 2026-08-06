<?php
namespace App\Models;use App\Models\Concerns\BelongsToWorkspace;use Illuminate\Database\Eloquent\Model;
class Campaign extends Model {use BelongsToWorkspace;protected $fillable=['workspace_id','name','description','status','settings'];protected function casts():array{return ['settings'=>'array'];}public function steps(){return $this->hasMany(CampaignStep::class)->orderBy('position');}public function leads(){return $this->belongsToMany(Lead::class,'campaign_members')->withPivot(['status','current_step','next_action_at'])->withTimestamps();}}
