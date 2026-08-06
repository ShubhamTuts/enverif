<?php

declare(strict_types=1);

namespace App\Core\Connectors;

final class ConnectorConfigurationValidator
{
    /**
     * @param array<string,mixed> $schema
     * @param array<string,mixed> $credentials
     * @param array<string,mixed> $configuration
     * @param array<string,mixed> $existingCredentials
     * @return list<string>
     */
    public static function missing(
        array $schema,
        array $credentials,
        array $configuration,
        array $existingCredentials = [],
    ): array {
        $missing = [];
        $mergedCredentials = array_merge($existingCredentials, self::nonEmpty($credentials));

        foreach ((array) ($schema['credentials'] ?? []) as $key => $meta) {
            if (($meta['required'] ?? false) && self::blank($mergedCredentials[$key] ?? null)) {
                $missing[] = 'credentials.' . $key;
            }
        }

        foreach ((array) ($schema['fields'] ?? []) as $key => $meta) {
            if (($meta['required'] ?? false) && self::blank($configuration[$key] ?? $meta['default'] ?? null)) {
                $missing[] = 'configuration.' . $key;
            }
        }

        return $missing;
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    private static function nonEmpty(array $values): array
    {
        return array_filter($values, static fn (mixed $value): bool => !self::blank($value));
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
