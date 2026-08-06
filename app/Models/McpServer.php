<?php
namespace App\Models;use App\Models\Concerns\BelongsToWorkspace;use Illuminate\Database\Eloquent\Model;
class McpServer extends Model {use BelongsToWorkspace;protected $fillable=['workspace_id','name','transport','endpoint','credentials','configuration','enabled'];protected $hidden=['credentials'];protected function casts():array{return ['credentials'=>'encrypted:array','configuration'=>'array','enabled'=>'boolean'];}}
