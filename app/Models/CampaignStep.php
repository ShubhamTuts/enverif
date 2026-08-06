<?php
namespace App\Models;use Illuminate\Database\Eloquent\Model;
class CampaignStep extends Model {protected $fillable=['campaign_id','position','channel','action','delay_minutes','content','requires_approval'];protected function casts():array{return ['content'=>'array','requires_approval'=>'boolean'];}}
