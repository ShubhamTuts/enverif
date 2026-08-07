<?php

namespace App\Core\Sales;

final class LeadContactNormalizer
{
    /** @param array<string,mixed> $fields @return array<string,mixed> */
    public function normalize(array $fields): array
    {
        $data = is_array($fields['data'] ?? null) ? $fields['data'] : [];
        $summary = trim((string) ($fields['research_summary'] ?? ''));

        $fields['email'] = $this->firstNonEmpty([
            $fields['email'] ?? null,
            $this->findDataValue($data, ['email', 'email_address', 'work_email', 'contact_email']),
            $this->labeled($summary, 'email'),
        ]);
        if (is_string($fields['email']) && $fields['email'] !== '') {
            $fields['email'] = strtolower(trim($fields['email']));
            if (! filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) unset($fields['email']);
        }

        $fields['phone'] = $this->firstNonEmpty([
            $fields['phone'] ?? null,
            $this->findDataValue($data, ['phone', 'telephone', 'tel', 'mobile', 'phone_number', 'contact_phone']),
            $this->labeled($summary, 'phone'),
        ]);
        if (isset($fields['phone'])) $fields['phone'] = trim((string) $fields['phone']);

        $fields['linkedin_url'] = $this->firstNonEmpty([
            $fields['linkedin_url'] ?? null,
            $this->findDataValue($data, ['linkedin', 'linkedin_url', 'linkedin_profile']),
            $this->labeled($summary, 'linkedin'),
        ]);
        if (isset($fields['linkedin_url'])) $fields['linkedin_url'] = trim((string) $fields['linkedin_url']);

        $fields['website'] = $this->firstNonEmpty([
            $fields['website'] ?? null,
            $this->findDataValue($data, ['website', 'homepage', 'company_url', 'domain', 'url']),
            $this->labeled($summary, 'website'),
        ]);
        if (isset($fields['website'])) $fields['website'] = trim((string) $fields['website']);

        $fields['outreach_readiness'] = $this->readiness($fields);

        return array_filter($fields, static fn ($value, $key) => $value !== null && !($value === '' && in_array($key, ['email', 'phone', 'linkedin_url', 'website'], true)), ARRAY_FILTER_USE_BOTH);
    }

    /** @param array<string,mixed> $fields */
    public function readiness(array $fields): string
    {
        foreach (['email', 'phone', 'linkedin_url'] as $key) {
            if (trim((string) ($fields[$key] ?? '')) !== '') return 'ready';
        }
        return 'needs_enrichment';
    }

    /** @param array<mixed> $values */
    private function firstNonEmpty(array $values): mixed
    {
        foreach ($values as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') return trim((string) $value);
        }
        return null;
    }

    /** @param array<string,mixed> $data @param list<string> $keys */
    private function findDataValue(array $data, array $keys): mixed
    {
        foreach ($data as $key => $value) {
            $normalized = strtolower(str_replace(['-', ' '], '_', (string) $key));
            if (in_array($normalized, $keys, true) && is_scalar($value) && trim((string) $value) !== '') return $value;
            if (is_array($value)) {
                $nested = $this->findDataValue($value, $keys);
                if ($nested !== null) return $nested;
            }
        }
        return null;
    }

    private function labeled(string $summary, string $label): ?string
    {
        if ($summary === '') return null;
        $patterns = match ($label) {
            'email' => '/(?:^|[\n\r;])\s*(?:email|email address)\s*:\s*([^\s,;<>]+@[^\s,;<>]+)/i',
            'phone' => '/(?:^|[\n\r;.]|\s)\s*(?:phone|telephone|tel|mobile)\s*:\s*([^\n\r;]+)/i',
            'linkedin' => '/(?:^|[\n\r;])\s*(?:linkedin|linkedin url)\s*:\s*(https?:\/\/[^\s,;]+)/i',
            'website' => '/(?:^|[\n\r;])\s*(?:website|site|url)\s*:\s*(https?:\/\/[^\s,;]+)/i',
            default => null,
        };
        if (! $patterns || preg_match($patterns, $summary, $matches) !== 1) return null;
        $value = trim((string) ($matches[1] ?? ''));
        if ($label === 'phone') {
            $value = preg_replace('/\s+(?:no\s+email|email|website|linkedin)\b.*$/i', '', $value) ?? $value;
            $value = rtrim(trim($value), '.');
        }
        return $value !== '' ? $value : null;
    }
}
