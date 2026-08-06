<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\EncryptedCredentials;
use Illuminate\Database\Eloquent\Model;

class McpServer extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'name', 'transport', 'endpoint',
        'credentials', 'configuration', 'enabled',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'configuration' => 'array',
            'enabled' => 'boolean',
        ];
    }

    /** @return array<string, mixed> */
    public function decryptedCredentials(): array
    {
        return EncryptedCredentials::read(
            fn () => $this->credentials ?? [],
            EncryptedCredentials::MCP_DECRYPT_MESSAGE,
        );
    }
}
