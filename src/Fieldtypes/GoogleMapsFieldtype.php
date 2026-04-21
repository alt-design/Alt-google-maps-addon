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
            'lat'     => null,
            'lng'     => null,
            'address' => '',
            'zoom'    => config('statamic-google-maps.defaults.zoom', 13),
        ];
    }

    public function preProcess($data)
    {
        if (is_null($data) || ! is_array($data)) {
            return $this->defaultValue();
        }

        return [
            'lat'     => isset($data['lat']) ? (float) $data['lat'] : null,
            'lng'     => isset($data['lng']) ? (float) $data['lng'] : null,
            'address' => $data['address'] ?? '',
            'zoom'    => (int) ($data['zoom'] ?? 13),
        ];
    }

    public function process($data)
    {
        if (empty($data['lat']) || empty($data['lng'])) {
            return null;
        }

        return [
            'lat'     => round((float) $data['lat'], 7),
            'lng'     => round((float) $data['lng'], 7),
            'address' => trim($data['address'] ?? ''),
            'zoom'    => (int) ($data['zoom'] ?? 13),
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
        $zoom = (int) ($value['zoom'] ?? 13);
        $key  = config('statamic-google-maps.api_key', '');

        return [
            'lat'             => $lat,
            'lng'             => $lng,
            'address'         => $value['address'] ?? '',
            'zoom'            => $zoom,
            'google_maps_url' => "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}",
            'directions_url'  => "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}",
            'static_map_url'  => $key
                ? "https://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lng}&zoom={$zoom}&size=600x300&markers=color:red%7C{$lat},{$lng}&key={$key}"
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
                'instructions' => 'Nível de zoom inicial (1 = mundo, 20 = edifício).',
                'type'         => 'integer',
                'default'      => 13,
                'width'        => 33,
            ],
            'default_lat' => [
                'display'      => 'Default Latitude',
                'type'         => 'text',
                'default'      => '-23.5505',
                'width'        => 33,
            ],
            'default_lng' => [
                'display'      => 'Default Longitude',
                'type'         => 'text',
                'default'      => '-46.6333',
                'width'        => 33,
            ],
            'map_height' => [
                'display'      => 'Map Height',
                'instructions' => 'Altura do mapa em pixels.',
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
        ];
    }
}