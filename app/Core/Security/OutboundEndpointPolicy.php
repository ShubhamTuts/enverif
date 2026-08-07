<?php

namespace App\Core\Security;

use Illuminate\Validation\ValidationException;

/**
 * Central allow/deny policy for user-configurable outbound network destinations.
 * Blocks credential exfiltration and common SSRF targets by default.
 */
final class OutboundEndpointPolicy
{
    public function assertAllowed(string $url, bool $httpsRequired = true): void
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw ValidationException::withMessages(['endpoint' => 'Enter a valid outbound endpoint URL.']);
        }
        $scheme = strtolower((string) $parts['scheme']);
        if ($httpsRequired && $scheme !== 'https' && !app()->environment('local')) {
            throw ValidationException::withMessages(['endpoint' => 'Remote endpoints must use HTTPS.']);
        }
        if (!in_array($scheme, ['https','http'], true)) {
            throw ValidationException::withMessages(['endpoint' => 'Only HTTP(S) outbound endpoints are supported.']);
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['endpoint' => 'Credentials are not allowed inside endpoint URLs.']);
        }

        $host = rtrim(strtolower((string) $parts['host']), '.');
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            $this->deny();
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $this->assertPublicIp($host);
            return;
        }

        $ips = $this->resolve($host);
        if ($ips === []) {
            throw ValidationException::withMessages(['endpoint' => 'The endpoint hostname could not be resolved safely.']);
        }
        foreach ($ips as $ip) $this->assertPublicIp($ip);
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        $ips = [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                    if (is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP)) $ips[] = $ip;
                }
            }
        }
        if ($ips === []) {
            $ipv4 = @gethostbynamel($host);
            if (is_array($ipv4)) foreach ($ipv4 as $ip) if (filter_var($ip, FILTER_VALIDATE_IP)) $ips[] = $ip;
        }
        return array_values(array_unique($ips));
    }

    private function assertPublicIp(string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) $this->deny();

        // Explicit metadata/link-local blocks for runtimes whose PHP filters vary.
        if (in_array($ip, ['169.254.169.254','100.100.100.200'], true)) $this->deny();
    }

    private function deny(): never
    {
        throw ValidationException::withMessages(['endpoint' => 'Private, loopback, link-local and metadata network destinations are blocked.']);
    }
}
