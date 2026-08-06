<?php

namespace App\Core\Models;

/**
 * Ensures tool parameter schemas are valid JSON Schema objects for providers
 * that reject PHP empty-array encoding (`[]`) where an object (`{}`) is required.
 *
 * DeepSeek error example:
 * Invalid schema for function 'agents_list': [] is not of type 'object'
 */
final class ToolSchemaNormalizer
{
    /**
     * @param  mixed  $parameters
     * @return array<string, mixed>
     */
    public static function parameters(mixed $parameters): array
    {
        if (! is_array($parameters) || $parameters === [] || array_is_list($parameters)) {
            return [
                'type' => 'object',
                'properties' => new \stdClass,
            ];
        }

        $out = $parameters;
        $out['type'] = (string) ($out['type'] ?? 'object');
        if ($out['type'] === '') {
            $out['type'] = 'object';
        }

        if (! array_key_exists('properties', $out)
            || $out['properties'] === []
            || (is_array($out['properties']) && array_is_list($out['properties']))) {
            $out['properties'] = new \stdClass;
        } elseif (is_array($out['properties'])) {
            $props = [];
            foreach ($out['properties'] as $key => $schema) {
                $props[$key] = self::propertySchema($schema);
            }
            $out['properties'] = $props === [] ? new \stdClass : $props;
        }

        if (isset($out['required']) && (! is_array($out['required']) || ! array_is_list($out['required']))) {
            $out['required'] = array_values((array) $out['required']);
        }

        if (isset($out['additionalProperties']) && is_array($out['additionalProperties'])) {
            $out['additionalProperties'] = self::parameters($out['additionalProperties']);
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     * @return list<array<string, mixed>>
     */
    public static function tools(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            if (! is_array($tool)) {
                continue;
            }
            $tool['parameters'] = self::parameters($tool['parameters'] ?? null);
            $out[] = $tool;
        }

        return $out;
    }

    private static function propertySchema(mixed $schema): mixed
    {
        if (! is_array($schema)) {
            return $schema;
        }

        if (($schema['type'] ?? null) === 'object' || isset($schema['properties'])) {
            return self::parameters($schema);
        }

        if (($schema['type'] ?? null) === 'array' && array_key_exists('items', $schema) && is_array($schema['items'])) {
            $schema['items'] = self::propertySchema($schema['items']);
        }

        return $schema;
    }
}
