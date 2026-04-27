<?php

namespace AltDesign\AltGoogleMapsAddon\Fieldtypes;

use Statamic\Fields\Fieldtype;

class GoogleMapsFieldtype extends Fieldtype
{
    protected static $handle = 'google_maps';
    protected $icon = 'pin';
    protected $categories = ['special'];

    public function defaultValue()
    {
        return [
            'lat'             => $this->config('default_lat') ?? config('statamic-google-maps.defaults.lat'),
            'lng'             => $this->config('default_lng') ?? config('statamic-google-maps.defaults.lng'),
            'address'         => '',
            'zoom'            => $this->config('default_zoom') ?? config('statamic-google-maps.defaults.zoom', 13),
            'infobox_title'   => $this->config('default_infobox_title') ?? config('statamic-google-maps.infobox_title', ''),
            'infobox_content' => $this->config('default_infobox_content') ?? config('statamic-google-maps.infobox_content', ''),
        ];
    }

    public function preProcess($data)
    {
        if (is_null($data) || ! is_array($data)) {
            return $this->defaultValue();
        }

        return [
            'lat'             => isset($data['lat']) ? (float) $data['lat'] : null,
            'lng'             => isset($data['lng']) ? (float) $data['lng'] : null,
            'address'         => $data['address'] ?? '',
            'zoom'            => (int) ($data['zoom'] ?? 13),
            'infobox_title'   => (string) ($data['infobox_title'] ?? ''),
            'infobox_content' => (string) ($data['infobox_content'] ?? ''),
        ];
    }

    public function process($data)
    {
        if (empty($data['lat']) || empty($data['lng'])) {
            return null;
        }

        return [
            'lat'             => round((float) $data['lat'], 7),
            'lng'             => round((float) $data['lng'], 7),
            'address'         => trim($data['address'] ?? ''),
            'zoom'            => (int) ($data['zoom'] ?? 13),
            'infobox_title'   => trim((string) ($data['infobox_title'] ?? '')),
            'infobox_content' => trim((string) ($data['infobox_content'] ?? '')),
        ];
    }

    public function preProcessIndex($data)
    {
        if (is_null($data) || empty($data['lat'])) {
            return '—';
        }

        if (! empty($data['address'])) {
            $addr = $data['address'];
            return strlen($addr) > 50 ? substr($addr, 0, 50) . '…' : $addr;
        }

        return number_format($data['lat'], 5) . ', ' . number_format($data['lng'], 5);
    }

    public function augment($value)
    {
        if (is_null($value) || empty($value['lat'])) {
            return null;
        }

        $lat  = (float) $value['lat'];
        $lng  = (float) $value['lng'];
        $zoom = (int) ($value['zoom'] ?? $this->config('default_zoom') ?? config('statamic-google-maps.defaults.zoom', 13));
        $key  = config('statamic-google-maps.api_key', '');

        // Per-field config overrides the global site default (see README).
        $provider   = $this->config('map_provider') ?: config('statamic-google-maps.map_provider', 'google');
        $mapHeight  = (int) ($this->config('map_height') ?: config('statamic-google-maps.map_height', 420));

        // Infobox: per-entry stored value → per-field config default → global config
        // Use ?: (Elvis) not ?? so that empty strings also fall through to the next level.
        $infoboxTitle   = ($value['infobox_title']   ?: null) ?: ($this->config('default_infobox_title')   ?: config('statamic-google-maps.infobox_title', ''));
        $infoboxContent = ($value['infobox_content'] ?: null) ?: ($this->config('default_infobox_content') ?: config('statamic-google-maps.infobox_content', ''));

        // Map style: per-field config → global config (no per-entry override — style is a fieldset concern)
        $mapStyle = (string) ($this->config('map_style') ?: config('statamic-google-maps.map_style', ''));

        return [
            'lat'             => $lat,
            'lng'             => $lng,
            'address'         => $value['address'] ?? '',
            'zoom'            => $zoom,
            'map_provider'    => $provider,
            'map_height'      => $mapHeight,
            'infobox_title'   => $infoboxTitle,
            'infobox_content' => $infoboxContent,
            'map_style'       => $mapStyle,
            'google_maps_url' => "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}",
            'directions_url'  => "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}",
            'static_map_url'  => $key
                ? "https://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lng}&zoom={$zoom}&size=600x300&markers=color:red%7C{$lat},{$lng}&key=" . urlencode($key)
                : '',
        ];
    }

    protected function configFieldItems(): array
    {
        return [
            'map_provider' => [
                'display'      => 'Map Provider',
                'instructions' => 'Override the global map provider for this field. Leave blank to use the site default (Tools → Google Maps).',
                'type'         => 'select',
                'options'      => [
                    ''      => 'Use site default',
                    'google' => 'Google Maps',
                    'osm'    => 'OpenStreetMap (no API key needed)',
                ],
                'default'      => '',
                'width'        => 50,
            ],
            'default_zoom' => [
                'display'      => 'Default Zoom',
                'instructions' => 'Leave blank to use the site default (Tools → Google Maps).',
                'type'         => 'integer',
                'width'        => 33,
            ],
            'default_lat' => [
                'display'      => 'Default Latitude',
                'instructions' => 'Leave blank to use the site default.',
                'type'         => 'float',
                'width'        => 33,
            ],
            'default_lng' => [
                'display'      => 'Default Longitude',
                'instructions' => 'Leave blank to use the site default.',
                'type'         => 'float',
                'width'        => 33,
            ],
            'map_height' => [
                'display'      => 'Map Height',
                'instructions' => 'Map height in pixels.',
                'type'         => 'integer',
                'default'      => 420,
                'width'        => 33,
            ],
            'enable_search' => [
                'display'      => 'Enable Search',
                'type'         => 'toggle',
                'default'      => true,
                'width'        => 33,
            ],
            'enable_click' => [
                'display'      => 'Enable Click to Place',
                'type'         => 'toggle',
                'default'      => true,
                'width'        => 33,
            ],
            'default_infobox_title' => [
                'display'      => 'Default Infobox Title',
                'instructions' => 'Shown as the popup title when the marker is clicked. Overrides the global default (Tools → Google Maps) for this field only. Leave blank to inherit.',
                'type'         => 'text',
                'width'        => 100,
            ],
            'default_infobox_content' => [
                'display'      => 'Default Infobox Content',
                'instructions' => 'Shown below the title in the popup. HTML is supported. Overrides the global default for this field only. Leave blank to inherit.',
                'type'         => 'textarea',
                'width'        => 100,
            ],
            'map_style' => [
                'display'      => 'Map Style JSON',
                'instructions' => 'Paste style JSON from snazzymaps.com. Applied to Google Maps only. Overrides the global style for this field. Leave blank to inherit.',
                'type'         => 'textarea',
                'width'        => 100,
            ],
        ];
    }
}