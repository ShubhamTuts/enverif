<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Support\EncryptedCredentials;
use Illuminate\Database\Eloquent\Model;

class ModelConnection extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'provider', 'name', 'credentials', 'default_model',
        'base_url', 'pricing', 'enabled', 'last_tested_at', 'last_test_status',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'pricing' => 'array',
            'enabled' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }

    /** @return array<string, mixed> */
    public function decryptedCredentials(): array
    {
        return EncryptedCredentials::read(
            fn () => $this->credentials ?? [],
            EncryptedCredentials::DECRYPT_MESSAGE,
        );
    }

    public function credential(string $key): ?string
    {
        $data = $this->decryptedCredentials();

        return isset($data[$key]) ? (string) $data[$key] : null;
    }
}
