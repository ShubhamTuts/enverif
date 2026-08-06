<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class Skill extends Model {protected $fillable=['workspace_id','name','slug','description','version','source_type','source_url','source_ref','checksum','license','capabilities','body','status','built_in'];protected function casts():array{return ['capabilities'=>'array','built_in'=>'boolean'];}public function agents(){return $this->belongsToMany(Agent::class);}}
