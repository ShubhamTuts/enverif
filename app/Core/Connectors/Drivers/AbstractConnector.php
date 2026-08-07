<?php

namespace App\Core\Connectors\Drivers;

use App\Core\Connectors\Contracts\ConnectorDriver;
use App\Models\ConnectorConnection;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

abstract class AbstractConnector implements ConnectorDriver
{
    public function developer(): string
    {
        return 'Codefreex';
    }

    /**
     * Base connector client intentionally does not retry.
     *
     * The same client is used by many mutation actions. Blind transport retries can
     * repeat an email, post, webhook or paid provider run after an ambiguous network
     * failure. Read-only drivers may opt into readClient() explicitly.
     */
    protected function client(array $headers = []): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withHeaders($headers)
            ->timeout(90);
    }

    protected function readClient(array $headers = []): PendingRequest
    {
        return $this->client($headers)->retry(2, 750, throw: false);
    }

    protected function bearer(ConnectorConnection $connection, string $key = 'api_key'): string
    {
        $token = $connection->credential($key);
        if (!$token) {
            throw new \RuntimeException("Missing {$key} credential.");
        }
        return $token;
    }

    protected function action(string $action): void
    {
        $names = array_map(fn ($item) => $item->name, $this->actions());
        if (!in_array($action, $names, true)) {
            throw new \InvalidArgumentException("Unknown connector action: {$action}");
        }
    }
}
