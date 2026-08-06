<?php

declare(strict_types=1);

namespace App\Core\Plugins;

final class PluginManifestValidator
{
    private const CAPABILITIES = ['read','internal_write','external_write','network','secrets','destructive'];

    /** @param array<string,mixed> $manifest @return array<string,mixed> */
    public static function validate(array $manifest): array
    {
        foreach (['schema','name','slug','version','type','driver','license'] as $required) {
            if (!isset($manifest[$required]) || trim((string) $manifest[$required]) === '') {
                throw new \InvalidArgumentException("Plugin manifest is missing {$required}.");
            }
        }
        if ($manifest['schema'] !== 'enverif.plugin/v1') throw new \InvalidArgumentException('Unsupported plugin manifest schema.');
        if ($manifest['type'] !== 'connector') throw new \InvalidArgumentException('Only connector plugins are supported by this release.');
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $manifest['slug'])) throw new \InvalidArgumentException('Plugin slug must use lowercase kebab-case.');
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][0-9A-Za-z.-]+)?$/', (string) $manifest['version'])) throw new \InvalidArgumentException('Plugin version must be semantic versioning.');
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', (string) $manifest['driver'])) throw new \InvalidArgumentException('Plugin driver must be a fully qualified PHP class.');

        $capabilities = $manifest['capabilities'] ?? [];
        if (!is_array($capabilities)) throw new \InvalidArgumentException('Plugin capabilities must be an array.');
        foreach ($capabilities as $capability) if (!in_array($capability, self::CAPABILITIES, true)) throw new \InvalidArgumentException("Unknown plugin capability: {$capability}");

        if (isset($manifest['bootstrap'])) {
            $bootstrap = (string) $manifest['bootstrap'];
            if ($bootstrap === '' || str_starts_with($bootstrap, '/') || str_contains($bootstrap, '..') || !str_ends_with($bootstrap, '.php')) throw new \InvalidArgumentException('Plugin bootstrap must be a safe relative PHP path.');
        }
        if (isset($manifest['icon'])) {
            $icon = trim((string) $manifest['icon']);
            $remote = preg_match('#^https://#i', $icon) === 1;
            $local = $icon !== '' && !str_starts_with($icon, '/') && !str_contains($icon, '..') && preg_match('/\.(?:svg|png|webp|jpg|jpeg)$/i', $icon);
            if (!$remote && !$local) throw new \InvalidArgumentException('Plugin icon must be an HTTPS URL or a safe relative SVG/PNG/WebP/JPG path.');
        }
        foreach (['developer_url','homepage','docs_url'] as $urlKey) {
            if (!empty($manifest[$urlKey]) && preg_match('#^https://#i', (string) $manifest[$urlKey]) !== 1) throw new \InvalidArgumentException("Plugin {$urlKey} must be an HTTPS URL.");
        }
        $manifest['developer'] = trim((string) ($manifest['developer'] ?? 'Third-party')) ?: 'Third-party';
        $manifest['category'] = trim((string) ($manifest['category'] ?? 'Integration')) ?: 'Integration';
        return $manifest;
    }
}
