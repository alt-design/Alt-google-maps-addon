<?php

namespace AltDesign\AltGoogleMapsAddon\Fieldtypes;

use Statamic\Assets\AssetContainer;
use Statamic\Facades\AssetContainer as AssetContainerFacade;
use Statamic\Facades\User;
use Statamic\Fields\Fieldtype;

class GoogleMapsFieldtype extends Fieldtype
{
    protected static $handle = 'google_maps';
    protected $icon = 'pin';
    protected $categories = ['special'];

    public function preload()
    {
        $user = User::current();

        $containers = AssetContainerFacade::all()->map(function ($c) use ($user) {
            return [
                'id'                => $c->id(),
                'handle'            => $c->handle(),
                'title'             => $c->title(),
                'edit_url'          => $c->editUrl(),
                'delete_url'        => $c->deleteUrl(),
                'blueprint_url'     => cp_route('blueprints.asset-containers.edit', $c->handle()),
                'can_view'          => $user ? $user->can('view', $c) : false,
                'can_edit'          => $user ? $user->can('edit', $c) : false,
                'can_delete'        => $user ? $user->can('delete', $c) : false,
                'can_upload'        => $user ? $user->can('store', [\Statamic\Contracts\Assets\Asset::class, $c]) : false,
                'can_create_folders'=> $user ? $user->can('create', [\Statamic\Contracts\Assets\AssetFolder::class, $c]) : false,
                'sort_field'        => $c->sortField(),
                'sort_direction'    => $c->sortDirection(),
            ];
        })->values()->all();

        return [
            'containers' => $containers,
            'columns'    => [],
        ];
    }

    public function defaultValue()
    {
        return [
            'lat'               => $this->config('default_lat') ?? config('statamic-google-maps.defaults.lat'),
            'lng'               => $this->config('default_lng') ?? config('statamic-google-maps.defaults.lng'),
            'address'           => '',
            'zoom'              => $this->config('default_zoom') ?? config('statamic-google-maps.defaults.zoom', 13),
            'infobox_title'     => $this->config('default_infobox_title') ?? config('statamic-google-maps.infobox_title', ''),
            'infobox_content'   => $this->config('default_infobox_content') ?? config('statamic-google-maps.infobox_content', ''),
            'marker_icon_url'   => '',
            'marker_icon_width' => 0,
            'marker_icon_height'=> 0,
            'marker_asset_id'   => '',
        ];
    }

    public function preProcess($data)
    {
        if (is_null($data) || ! is_array($data)) {
            return $this->defaultValue();
        }

        return [
            'lat'               => isset($data['lat']) ? (float) $data['lat'] : null,
            'lng'               => isset($data['lng']) ? (float) $data['lng'] : null,
            'address'           => $data['address'] ?? '',
            'zoom'              => (int) ($data['zoom'] ?? 13),
            'infobox_title'     => (string) ($data['infobox_title'] ?? ''),
            'infobox_content'   => (string) ($data['infobox_content'] ?? ''),
            'marker_icon_url'   => (string) ($data['marker_icon_url'] ?? ''),
            'marker_icon_width' => (int) ($data['marker_icon_width'] ?? 0),
            'marker_icon_height'=> (int) ($data['marker_icon_height'] ?? 0),
            'marker_asset_id'   => (string) ($data['marker_asset_id'] ?? ''),
        ];
    }

    public function process($data)
    {
        if (empty($data['lat']) || empty($data['lng'])) {
            return null;
        }

        return [
            'lat'               => round((float) $data['lat'], 7),
            'lng'               => round((float) $data['lng'], 7),
            'address'           => trim($data['address'] ?? ''),
            'zoom'              => (int) ($data['zoom'] ?? 13),
            'infobox_title'     => trim((string) ($data['infobox_title'] ?? '')),
            'infobox_content'   => trim((string) ($data['infobox_content'] ?? '')),
            'marker_icon_url'   => trim((string) ($data['marker_icon_url'] ?? '')),
            'marker_icon_width' => (int) ($data['marker_icon_width'] ?? 0),
            'marker_icon_height'=> (int) ($data['marker_icon_height'] ?? 0),
            'marker_asset_id'   => trim((string) ($data['marker_asset_id'] ?? '')),
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
        // ($value key may be absent on old entries — use ?? '' before ?: chain)
        $infoboxTitle   = ($value['infobox_title']   ?? '') ?: ($this->config('default_infobox_title')   ?: config('statamic-google-maps.infobox_title', ''));
        $infoboxContent = ($value['infobox_content'] ?? '') ?: ($this->config('default_infobox_content') ?: config('statamic-google-maps.infobox_content', ''));

        // Map style: per-field config → global config (no per-entry override — style is a fieldset concern)
        $mapStyle = (string) ($this->config('map_style') ?: config('statamic-google-maps.map_style', ''));

        // Marker icon: per-entry stored value → per-field config → global config
        $entryIconUrl    = $value['marker_icon_url']    ?? '';
        $entryIconWidth  = $value['marker_icon_width']  ?? 0;
        $entryIconHeight = $value['marker_icon_height'] ?? 0;
        $markerIconUrl    = (string) ($entryIconUrl  ?: ($this->config('marker_icon_url')   ?: config('statamic-google-maps.marker_icon_url', '')));
        $markerIconWidth  = (int)    ($entryIconWidth  > 0 ? $entryIconWidth  : ($this->config('marker_icon_width')   ?: config('statamic-google-maps.marker_icon_width', 40)));
        $markerIconHeight = (int)    ($entryIconHeight > 0 ? $entryIconHeight : ($this->config('marker_icon_height')  ?: config('statamic-google-maps.marker_icon_height', 40)));
        $markerIcon = $markerIconUrl
            ? ['url' => $markerIconUrl, 'width' => $markerIconWidth, 'height' => $markerIconHeight]
            : null;

        return [
            'lat'              => $lat,
            'lng'              => $lng,
            'address'          => $value['address'] ?? '',
            'zoom'             => $zoom,
            'map_provider'     => $provider,
            'map_height'       => $mapHeight,
            'infobox_title'    => $infoboxTitle,
            'infobox_content'  => $infoboxContent,
            'map_style'        => $mapStyle,
            'marker_icon'      => $markerIcon,
            'marker_icon_json' => $markerIcon ? json_encode($markerIcon) : '',
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
            'marker_icon_url' => [
                'display'      => 'Custom Marker Icon URL',
                'instructions' => 'URL to a custom marker image (PNG or SVG). Overrides the global icon for this field. Leave blank to inherit.',
                'type'         => 'text',
                'width'        => 100,
            ],
            'marker_icon_width' => [
                'display'      => 'Icon Width (px)',
                'instructions' => 'Width of the custom marker in pixels.',
                'type'         => 'integer',
                'default'      => 40,
                'width'        => 50,
            ],
            'marker_icon_height' => [
                'display'      => 'Icon Height (px)',
                'instructions' => 'Height of the custom marker in pixels.',
                'type'         => 'integer',
                'default'      => 40,
                'width'        => 50,
            ],
        ];
    }
}