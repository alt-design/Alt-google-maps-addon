<?php

namespace AltDesign\AltGoogleMapsAddon\Settings;

use Statamic\Facades\AssetContainer;
use Statamic\Facades\YAML;

/**
 * Builds the addon settings blueprint for the containers that exist on the site.
 */
class SettingsBlueprint
{
    private const ICON_FIELD = 'marker_icon_asset';

    /**
     * Parse the blueprint file and point the marker icon field at an asset container.
     *
     * The assets fieldtype fails to render on a site with several containers unless one
     * is named, and on a site with none, so the field is configured or dropped here.
     *
     * @param  string  $path  Absolute path to the blueprint YAML.
     * @return array<string, mixed>
     */
    public static function contents(string $path): array
    {
        $contents = YAML::file($path)->parse();
        $container = self::containerHandle();

        foreach ($contents['tabs'] as &$tab) {
            foreach ($tab['sections'] as &$section) {
                $section['fields'] = self::withIconField($section['fields'], $container);
            }
        }

        return $contents;
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, array<string, mixed>>
     */
    private static function withIconField(array $fields, ?string $container): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (($field['handle'] ?? null) !== self::ICON_FIELD) {
                $result[] = $field;
                continue;
            }

            if ($container !== null) {
                $field['field']['container'] = $container;
                $result[] = $field;
            }
        }

        return $result;
    }

    private static function containerHandle(): ?string
    {
        $configured = config('alt-google-maps.marker_icon_container');

        if ($configured && AssetContainer::find($configured)) {
            return $configured;
        }

        return AssetContainer::all()->first()?->handle();
    }
}
