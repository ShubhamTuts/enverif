<?php

namespace App\Core\Plugins;

final class PluginPresentation
{
    /** @var array<string,string> */
    private const ICONS = [
        'gmail' => 'gmail.svg',
        'outlook' => 'outlook.svg',
        'smtp' => 'smtp.svg',
        'apollo' => 'apollo.svg',
        'apify' => 'apify.svg',
        'google-search-console' => 'google-search-console.svg',
        'google-analytics' => 'google-analytics.svg',
        'google-maps' => 'google-maps.svg',
        'calendly' => 'calendly.svg',
        'automation-webhook' => 'automation-webhook.svg',
        'slack' => 'slack.svg',
        'buffer' => 'buffer.svg',
        'google-sheets' => 'google-sheets.svg',
        'google_sheets' => 'google-sheets.svg',
    ];

    public static function iconFor(string $driver): string
    {
        $file = self::ICONS[$driver] ?? null;
        return $file ? asset('assets/integrations/'.$file) : asset('assets/enverif-mark.svg');
    }

    public static function developerUrl(string $developer): ?string
    {
        return strcasecmp(trim($developer), 'Codefreex') === 0 ? 'https://codefreex.com/' : null;
    }
}
