<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'first_name', 'last_name', 'email', 'phone', 'title', 'company',
        'website', 'linkedin_url', 'city', 'country', 'status', 'outreach_readiness',
        'score', 'source', 'source_url', 'research_summary', 'data', 'last_contacted_at',
    ];

    protected function casts(): array
    {
        return ['data' => 'array', 'last_contacted_at' => 'datetime'];
    }

    public function activities() { return $this->hasMany(LeadActivity::class); }
    public function getNameAttribute(): string { return trim(($this->first_name ?? '').' '.($this->last_name ?? '')); }
}
