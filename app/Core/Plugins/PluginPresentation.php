<?php

namespace App\Core\Plugins;

final class PluginPresentation
{
    /** @var array<string,string> */
    private const ICONS = [
        'gmail' => 'https://www.google.com/s2/favicons?domain=mail.google.com&sz=128',
        'outlook' => 'https://www.google.com/s2/favicons?domain=outlook.com&sz=128',
        'smtp' => 'https://www.google.com/s2/favicons?domain=smtp.com&sz=128',
        'apollo' => 'https://www.google.com/s2/favicons?domain=apollo.io&sz=128',
        'apify' => 'https://www.google.com/s2/favicons?domain=apify.com&sz=128',
        'google-search-console' => 'https://www.google.com/s2/favicons?domain=search.google.com&sz=128',
        'google-analytics' => 'https://www.google.com/s2/favicons?domain=analytics.google.com&sz=128',
        'google-maps' => 'https://www.google.com/s2/favicons?domain=maps.google.com&sz=128',
        'calendly' => 'https://www.google.com/s2/favicons?domain=calendly.com&sz=128',
        'automation-webhook' => 'https://www.google.com/s2/favicons?domain=n8n.io&sz=128',
    ];

    public static function iconFor(string $driver): string
    {
        return self::ICONS[$driver] ?? asset('assets/enverif-mark.svg');
    }

    public static function developerUrl(string $developer): ?string
    {
        return strcasecmp(trim($developer), 'Codefreex') === 0 ? 'https://codefreex.com/' : null;
    }
}
