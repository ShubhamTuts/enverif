<?php

namespace App\Core\Models\Providers;

use App\Core\Models\Contracts\ModelProvider;
use App\Core\Models\DTO\ModelRequest;
use App\Models\ModelConnection;
use App\Support\EncryptedCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class AbstractHttpProvider implements ModelProvider
{
    protected function client(ModelConnection $connection, array $headers = []): PendingRequest
    {
        return Http::acceptJson()->asJson()->withHeaders($headers)->timeout(90)->retry(2, 750, throw: false);
    }

    protected function apiKey(ModelConnection $connection): string
    {
        try {
            $key = $connection->credential('api_key');
        } catch (\Throwable $e) {
            if (EncryptedCredentials::isDecryptFailure($e)) {
                throw new \RuntimeException(EncryptedCredentials::DECRYPT_MESSAGE, 0, $e);
            }
            throw $e;
        }
        if (! $key) {
            throw new \RuntimeException('API key is missing for '.$connection->provider.'. Re-enter it under AI Models.');
        }

        return $key;
    }

    public function test(ModelConnection $connection): bool
    {
        try {
            return $this->complete(
                $connection,
                new ModelRequest(
                    $connection->default_model ?: $this->models()[0],
                    'Answer with OK only.',
                    [['role' => 'user', 'content' => 'ping']],
                    [],
                    8,
                ),
            )->content !== '';
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array{ok: bool, message: string} */
    public function testWithMessage(ModelConnection $connection): array
    {
        try {
            $ok = $this->complete(
                $connection,
                new ModelRequest(
                    $connection->default_model ?: $this->models()[0],
                    'Answer with OK only.',
                    [['role' => 'user', 'content' => 'ping']],
                    [],
                    8,
                ),
            )->content !== '';

            return [
                'ok' => $ok,
                'message' => $ok ? 'Connection OK.' : 'Provider returned an empty response.',
            ];
        } catch (\Throwable $e) {
            if (EncryptedCredentials::isDecryptFailure($e)) {
                return ['ok' => false, 'message' => EncryptedCredentials::DECRYPT_MESSAGE];
            }

            return ['ok' => false, 'message' => trim($e->getMessage()) ?: 'Connection test failed.'];
        }
    }
}
