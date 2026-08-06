<?php

namespace App\Core\Email;

use App\Models\ConnectorConnection;
use Illuminate\Support\Facades\{Cache, Http};

final class OAuthTokenService
{
    public function accessToken(ConnectorConnection $connection): string
    {
        $credentials = $connection->decryptedCredentials();
        $token = (string) ($credentials['access_token'] ?? '');
        $expiresAt = (int) ($credentials['expires_at'] ?? 0);
        if ($token !== '' && ($expiresAt === 0 || $expiresAt > time() + 60)) return $token;

        return Cache::lock('enverif:oauth-refresh:' . $connection->id, 20)->block(5, function () use ($connection): string {
            $connection->refresh();
            $credentials = $connection->decryptedCredentials();
            $token = (string) ($credentials['access_token'] ?? '');
            $expiresAt = (int) ($credentials['expires_at'] ?? 0);
            if ($token !== '' && ($expiresAt === 0 || $expiresAt > time() + 60)) return $token;

            $refresh = (string) ($credentials['refresh_token'] ?? '');
            $clientSecret = (string) ($credentials['client_secret'] ?? '');
            $clientId = (string) data_get($connection->configuration, 'client_id', '');
            if ($refresh === '' || $clientId === '' || $clientSecret === '') {
                throw new \RuntimeException('Reconnect this connection to refresh OAuth access.');
            }

            $response = match ($connection->driver) {
                'gmail', 'google_sheets' => Http::asForm()->timeout(30)->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refresh,
                    'grant_type' => 'refresh_token',
                ]),
                'outlook' => Http::asForm()->timeout(30)->post('https://login.microsoftonline.com/' . rawurlencode((string) data_get($connection->configuration, 'tenant', 'common')) . '/oauth2/v2.0/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refresh,
                    'grant_type' => 'refresh_token',
                    'scope' => 'offline_access User.Read Mail.ReadWrite Mail.Send',
                ]),
                default => throw new \RuntimeException('This connector does not use OAuth tokens.'),
            };
            $payload = $response->throw()->json();
            $credentials['access_token'] = (string) ($payload['access_token'] ?? '');
            if (!empty($payload['refresh_token'])) $credentials['refresh_token'] = (string) $payload['refresh_token'];
            $credentials['expires_at'] = time() + max(60, (int) ($payload['expires_in'] ?? 3600) - 60);
            $connection->update(['credentials' => $credentials]);
            if ($credentials['access_token'] === '') throw new \RuntimeException('OAuth provider did not return an access token.');
            return $credentials['access_token'];
        });
    }
}
