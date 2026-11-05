<?php

namespace AltDesign\AltGoogleMapsAddon\Settings;

use AltDesign\AltGoogleMapsAddon\Fieldtypes\GoogleMapsFieldtype;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;

/**
 * Turns the values saved on the addon settings page into runtime config overrides.
 */
class MapSettings
{
    private const PROVIDERS = ['google', 'osm'];

    /**
     * Build the config overrides for the saved settings. Blank values are skipped so
     * that the .env and config file values still apply.
     *
     * @param  array<string, mixed>  $saved
     * @return array<string, mixed>  Config values keyed by their path under alt-google-maps.
     */
    public static function overrides(array $saved): array
    {
        $overrides = [];

        if (in_array($saved['map_provider'] ?? null, self::PROVIDERS, true)) {
            $overrides['map_provider'] = $saved['map_provider'];
        }

        foreach (['lat', 'lng'] as $key) {
            if (self::isFilled($saved[$key] ?? null)) {
                $overrides["defaults.$key"] = (float) $saved[$key];
            }
        }

        if (self::isFilled($saved['zoom'] ?? null)) {
            $overrides['defaults.zoom'] = (int) $saved['zoom'];
        }

        if (self::isFilled($saved['infobox_title'] ?? null)) {
            $overrides['infobox_title'] = GoogleMapsFieldtype::sanitizePlainText($saved['infobox_title']);
        }

        if (self::isFilled($saved['infobox_content'] ?? null)) {
            $overrides['infobox_content'] = GoogleMapsFieldtype::sanitizeHtml($saved['infobox_content']);
        }

        if (self::isFilled($saved['map_style'] ?? null)) {
            $overrides['map_style'] = trim((string) $saved['map_style']);
        }

        if ($iconUrl = self::markerIconUrl($saved['marker_icon_asset'] ?? null)) {
            $overrides['marker_icon_url'] = $iconUrl;
        }

        foreach (['marker_icon_width', 'marker_icon_height'] as $key) {
            if (self::isFilled($saved[$key] ?? null)) {
                $overrides[$key] = (int) $saved[$key];
            }
        }

        return $overrides;
    }

    /**
     * Resolve the saved marker icon asset to a URL.
     *
     * @param  mixed  $value  An asset ID, or the array of IDs the assets fieldtype returns.
     * @return string  An empty string when no usable asset is selected.
     */
    public static function markerIconUrl(mixed $value): string
    {
        $id = is_array($value) ? ($value[0] ?? null) : $value;

        if (! $id) {
            return '';
        }

        $asset = Asset::find((string) $id);

        if (! $asset) {
            foreach (AssetContainer::all() as $container) {
                $asset = Asset::find($container->handle() . '::' . $id);

                if ($asset) {
                    break;
                }
            }
        }

        return $asset ? GoogleMapsFieldtype::sanitizeUrl($asset->url()) : '';
    }

    private static function isFilled(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
