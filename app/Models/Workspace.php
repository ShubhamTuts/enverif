<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;use Illuminate\Database\Eloquent\Factories\HasFactory;
class Workspace extends Model {use HasFactory;protected $fillable=['name','slug','timezone','locale','settings'];protected function casts():array{return ['settings'=>'array'];}public function users(){return $this->belongsToMany(User::class,'workspace_user')->withPivot('role')->withTimestamps();}public function agents(){return $this->hasMany(Agent::class);} }
