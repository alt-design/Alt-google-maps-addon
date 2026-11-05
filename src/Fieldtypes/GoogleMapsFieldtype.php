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

    // ── Single-marker default (used when enable_multiple is false) ───────────────
    private function singleDefault(): array
    {
        return [
            'lat'               => $this->config('default_lat') ?? config('alt-google-maps.defaults.lat'),
            'lng'               => $this->config('default_lng') ?? config('alt-google-maps.defaults.lng'),
            'address'           => '',
            'zoom'              => $this->config('default_zoom') ?? config('alt-google-maps.defaults.zoom', 13),
            'infobox_title'     => $this->config('default_infobox_title') ?? config('alt-google-maps.infobox_title', ''),
            'infobox_content'   => $this->config('default_infobox_content') ?? config('alt-google-maps.infobox_content', ''),
            'marker_icon_url'   => '',
            'marker_icon_width' => 0,
            'marker_icon_height'=> 0,
            'marker_asset_id'   => '',
        ];
    }

    // ── Multi-marker default ──────────────────────────────────────────────────
    private function multiDefault(): array
    {
        return [
            'zoom_override' => null,
            'markers'       => [],
        ];
    }

    // ── Empty marker row ──────────────────────────────────────────────────────
    private function emptyMarker(): array
    {
        return [
            'lat'               => null,
            'lng'               => null,
            'address'           => '',
            'zoom'              => $this->config('default_zoom') ?? config('alt-google-maps.defaults.zoom', 13),
            'infobox_title'     => '',
            'infobox_content'   => '',
            'marker_icon_url'   => '',
            'marker_icon_width' => 0,
            'marker_icon_height'=> 0,
            'marker_asset_id'   => '',
        ];
    }

    public function defaultValue()
    {
        return $this->config('enable_multiple') ? $this->multiDefault() : $this->singleDefault();
    }

    public function preProcess($data)
    {
        if ($this->config('enable_multiple')) {
            return $this->preProcessMulti($data);
        }

        if (is_null($data) || ! is_array($data)) {
            return $this->singleDefault();
        }

        // Migrate old single-marker entry when enable_multiple was just turned on
        // (handled in preProcessMulti via the 'lat' key check)

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

    private function preProcessMulti($data): array
    {
        if (is_null($data) || ! is_array($data)) {
            return $this->multiDefault();
        }

        // Migrate a legacy single-marker entry (has flat 'lat' key)
        if (array_key_exists('lat', $data) && ! array_key_exists('markers', $data)) {
            $migratedMarker = $this->preProcessMarkerRow($data);
            return [
                'zoom_override' => null,
                'markers'       => [$migratedMarker],
            ];
        }

        $markers = array_map(
            fn ($m) => $this->preProcessMarkerRow($m),
            is_array($data['markers'] ?? null) ? $data['markers'] : []
        );

        return [
            'zoom_override' => isset($data['zoom_override']) && $data['zoom_override'] !== '' && $data['zoom_override'] !== null
                ? (int) $data['zoom_override']
                : null,
            'markers' => $markers,
        ];
    }

    private function preProcessMarkerRow(array $m): array
    {
        return [
            'lat'               => isset($m['lat']) && $m['lat'] !== '' && $m['lat'] !== null ? (float) $m['lat'] : null,
            'lng'               => isset($m['lng']) && $m['lng'] !== '' && $m['lng'] !== null ? (float) $m['lng'] : null,
            'address'           => (string) ($m['address'] ?? ''),
            'zoom'              => (int) ($m['zoom'] ?? 13),
            'infobox_title'     => (string) ($m['infobox_title'] ?? ''),
            'infobox_content'   => (string) ($m['infobox_content'] ?? ''),
            'marker_icon_url'   => (string) ($m['marker_icon_url'] ?? ''),
            'marker_icon_width' => (int) ($m['marker_icon_width'] ?? 0),
            'marker_icon_height'=> (int) ($m['marker_icon_height'] ?? 0),
            'marker_asset_id'   => (string) ($m['marker_asset_id'] ?? ''),
            'enabled'           => isset($m['enabled']) ? (bool) $m['enabled'] : true,
        ];
    }

    public function process($data)
    {
        if ($this->config('enable_multiple')) {
            return $this->processMulti($data);
        }

        if (empty($data['lat']) || empty($data['lng'])) {
            return null;
        }

        return [
            'lat'               => round((float) $data['lat'], 7),
            'lng'               => round((float) $data['lng'], 7),
            'address'           => trim($data['address'] ?? ''),
            'zoom'              => (int) ($data['zoom'] ?? 13),
            'infobox_title'     => self::sanitizePlainText($data['infobox_title'] ?? ''),
            'infobox_content'   => self::sanitizeHtml($data['infobox_content'] ?? ''),
            'marker_icon_url'   => self::sanitizeUrl($data['marker_icon_url'] ?? ''),
            'marker_icon_width' => (int) ($data['marker_icon_width'] ?? 0),
            'marker_icon_height'=> (int) ($data['marker_icon_height'] ?? 0),
            'marker_asset_id'   => trim((string) ($data['marker_asset_id'] ?? '')),
        ];
    }

    /**
     * Strip script-injection vectors from admin-supplied HTML kept for popup rendering.
     *
     * Why: infobox_content is rendered as HTML in Leaflet/Google popups. Without this,
     * a CP user could store <script>, on*= handlers, or javascript: URLs that would
     * execute against any visitor of the public site.
     */
    public static function sanitizeHtml(mixed $html): string
    {
        $html = trim((string) $html);
        if ($html === '') return '';

        $html = preg_replace('#<(script|style|iframe|object|embed|link|meta)\b[^>]*>.*?</\1>#is', '', $html);
        $html = preg_replace('#<(script|style|iframe|object|embed|link|meta)\b[^>]*/?>#i', '', $html);
        $html = preg_replace('#\son[a-z]+\s*=\s*"[^"]*"#i', '', $html);
        $html = preg_replace("#\son[a-z]+\s*=\s*'[^']*'#i", '', $html);
        $html = preg_replace('#\son[a-z]+\s*=\s*[^\s>]+#i', '', $html);
        $html = preg_replace('#(href|src|formaction|xlink:href)\s*=\s*(["\']?)\s*(?:javascript|data|vbscript):[^"\'>\s]*\2#i', '$1=""', $html);

        return $html;
    }

    public static function sanitizePlainText(mixed $value): string
    {
        return trim(strip_tags((string) $value));
    }

    public static function sanitizeUrl(mixed $url): string
    {
        $url = trim((string) $url);
        if ($url === '') return '';
        if (preg_match('#^\s*(javascript|data|vbscript):#i', $url)) return '';
        return $url;
    }

    private function processMulti($data): ?array
    {
        if (! is_array($data)) return null;

        $markers = array_values(array_filter(
            array_map(fn ($m) => $this->processMarkerRow($m), $data['markers'] ?? []),
            fn ($m) => $m !== null
        ));

        if (empty($markers)) return null;

        return [
            'zoom_override' => isset($data['zoom_override']) && $data['zoom_override'] !== '' && $data['zoom_override'] !== null
                ? (int) $data['zoom_override']
                : null,
            'markers' => $markers,
        ];
    }

    private function processMarkerRow(array $m): ?array
    {
        if (empty($m['lat']) || empty($m['lng'])) return null;

        return [
            'lat'               => round((float) $m['lat'], 7),
            'lng'               => round((float) $m['lng'], 7),
            'address'           => trim((string) ($m['address'] ?? '')),
            'zoom'              => (int) ($m['zoom'] ?? 13),
            'infobox_title'     => self::sanitizePlainText($m['infobox_title'] ?? ''),
            'infobox_content'   => self::sanitizeHtml($m['infobox_content'] ?? ''),
            'marker_icon_url'   => self::sanitizeUrl($m['marker_icon_url'] ?? ''),
            'marker_icon_width' => (int) ($m['marker_icon_width'] ?? 0),
            'marker_icon_height'=> (int) ($m['marker_icon_height'] ?? 0),
            'marker_asset_id'   => trim((string) ($m['marker_asset_id'] ?? '')),
            'enabled'           => isset($m['enabled']) ? (bool) $m['enabled'] : true,
        ];
    }

    public function preProcessIndex($data)
    {
        // Multi-marker mode
        if ($this->config('enable_multiple')) {
            if (is_null($data) || empty($data['markers'])) return '—';
            $count = count($data['markers']);
            $first = $data['markers'][0] ?? [];
            $label = ! empty($first['address'])
                ? (strlen($first['address']) > 40 ? substr($first['address'], 0, 40) . '…' : $first['address'])
                : (isset($first['lat']) ? number_format($first['lat'], 4) . ', ' . number_format($first['lng'], 4) : '—');
            return $count > 1 ? "{$label} (+".($count - 1)." more)" : $label;
        }

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
        if ($this->config('enable_multiple')) {
            return $this->augmentMulti($value);
        }

        return $this->augmentSingle($value);
    }

    private function resolveConfigAssetUrl(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }
        $id = is_array($value) ? ($value[0] ?? null) : $value;
        if (! $id) {
            return '';
        }
        $id = (string) $id;
        $asset = \Statamic\Facades\Asset::find($id);
        if ($asset) {
            return (string) $asset->url();
        }
        // Fallback: try prefixing each container (handles bare path like 'pin.png')
        foreach (\Statamic\Facades\AssetContainer::all() as $container) {
            $asset = \Statamic\Facades\Asset::find($container->handle() . '::' . $id);
            if ($asset) {
                return (string) $asset->url();
            }
        }
        // Last resort: treat as a plain URL (backward compat with old text config)
        return filter_var($id, FILTER_VALIDATE_URL) ? $id : '';
    }

    private function resolveMarkerIcon(array $entry): ?array
    {
        $entryIconUrl    = $entry['marker_icon_url']    ?? '';
        $entryIconWidth  = (int) ($entry['marker_icon_width']  ?? 0);
        $entryIconHeight = (int) ($entry['marker_icon_height'] ?? 0);

        // Field-level config: prefer new asset picker (marker_icon_asset), fall back to legacy URL text
        $configAsset = $this->config('marker_icon_asset');
        $configUrl   = $configAsset
            ? $this->resolveConfigAssetUrl($configAsset)
            : (string) ($this->config('marker_icon_url') ?? '');

        $markerIconUrl    = (string) ($entryIconUrl  ?: ($configUrl ?: config('alt-google-maps.marker_icon_url', '')));
        $markerIconWidth  = $entryIconWidth  > 0 ? $entryIconWidth  : (int) ($this->config('marker_icon_width')   ?: config('alt-google-maps.marker_icon_width', 40));
        $markerIconHeight = $entryIconHeight > 0 ? $entryIconHeight : (int) ($this->config('marker_icon_height')  ?: config('alt-google-maps.marker_icon_height', 40));

        return $markerIconUrl
            ? ['url' => $markerIconUrl, 'width' => $markerIconWidth, 'height' => $markerIconHeight]
            : null;
    }

    private function augmentSingle(?array $value): ?array
    {
        if (is_null($value) || empty($value['lat'])) {
            return null;
        }

        $lat  = (float) $value['lat'];
        $lng  = (float) $value['lng'];
        $zoom = (int) ($value['zoom'] ?? $this->config('default_zoom') ?? config('alt-google-maps.defaults.zoom', 13));
        $key  = config('alt-google-maps.api_key', '');

        $provider   = $this->config('map_provider') ?: config('alt-google-maps.map_provider', 'google');
        $mapHeight  = (int) ($this->config('map_height') ?: config('alt-google-maps.map_height', 420));
        $infoboxTitle   = ($value['infobox_title']   ?? '') ?: ($this->config('default_infobox_title')   ?: config('alt-google-maps.infobox_title', ''));
        $infoboxContent = ($value['infobox_content'] ?? '') ?: ($this->config('default_infobox_content') ?: config('alt-google-maps.infobox_content', ''));
        $mapStyle = (string) ($this->config('map_style') ?: config('alt-google-maps.map_style', ''));
        $markerIcon = $this->resolveMarkerIcon($value);

        // Build a markers array so templates can use a unified API
        $markerEntry = [
            'lat'              => $lat,
            'lng'              => $lng,
            'address'          => $value['address'] ?? '',
            'infobox_title'    => $infoboxTitle,
            'infobox_content'  => $infoboxContent,
            'marker_icon'      => $markerIcon,
            'marker_icon_json' => $markerIcon ? json_encode($markerIcon) : '',
        ];
        $markersJson = json_encode([$this->markerForFrontend($markerEntry)]);

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
            'markers'          => [$markerEntry],
            'markers_json'     => $markersJson,
            'enable_clustering'=> false,
            'google_maps_url' => "https://www.google.com/maps/search/?api=1&query={$lat},{$lng}",
            'directions_url'  => "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}",
            'static_map_url'  => $key
                ? "https://maps.googleapis.com/maps/api/staticmap?center={$lat},{$lng}&zoom={$zoom}&size=600x300&markers=color:red%7C{$lat},{$lng}&key=" . urlencode($key)
                : '',
        ];
    }

    private function augmentMulti(?array $value): ?array
    {
        if (is_null($value) || empty($value['markers'])) {
            return null;
        }

        $provider   = $this->config('map_provider') ?: config('alt-google-maps.map_provider', 'google');
        $mapHeight  = (int) ($this->config('map_height') ?: config('alt-google-maps.map_height', 420));
        $mapStyle   = (string) ($this->config('map_style') ?: config('alt-google-maps.map_style', ''));
        $zoomOverride = isset($value['zoom_override']) && $value['zoom_override'] !== null && $value['zoom_override'] !== ''
            ? (int) $value['zoom_override']
            : null;
        $clustering    = (bool) $this->config('enable_clustering');
        $clusterColor  = (string) ($this->config('cluster_color') ?: '');

        $augmentedMarkers = [];
        foreach ($value['markers'] as $m) {
            if (isset($m['enabled']) && $m['enabled'] === false) continue;
            if (empty($m['lat']) || empty($m['lng'])) continue;

            $lat  = (float) $m['lat'];
            $lng  = (float) $m['lng'];
            $infoboxTitle   = ($m['infobox_title']   ?? '') ?: ($this->config('default_infobox_title')   ?: config('alt-google-maps.infobox_title', ''));
            $infoboxContent = ($m['infobox_content'] ?? '') ?: ($this->config('default_infobox_content') ?: config('alt-google-maps.infobox_content', ''));
            $markerIcon     = $this->resolveMarkerIcon($m);

            $augmentedMarkers[] = [
                'lat'              => $lat,
                'lng'              => $lng,
                'address'          => $m['address'] ?? '',
                'infobox_title'    => $infoboxTitle,
                'infobox_content'  => $infoboxContent,
                'marker_icon'      => $markerIcon,
                'marker_icon_json' => $markerIcon ? json_encode($markerIcon) : '',
            ];
        }

        if (empty($augmentedMarkers)) return null;

        $first = $augmentedMarkers[0];
        $markersJson = json_encode(array_map([$this, 'markerForFrontend'], $augmentedMarkers));

        return [
            // First-marker convenience (backward compat)
            'lat'             => $first['lat'],
            'lng'             => $first['lng'],
            'address'         => $first['address'],
            'zoom'            => $zoomOverride,
            'map_provider'    => $provider,
            'map_height'      => $mapHeight,
            'map_style'       => $mapStyle,
            'markers'          => $augmentedMarkers,
            'markers_json'     => $markersJson,
            'enable_clustering' => $clustering,
            'cluster_color'    => $clusterColor,
        ];
    }

    /** Build the lean JSON-serialisable shape used in data-markers attribute */
    private function markerForFrontend(array $m): array
    {
        return [
            'lat'            => $m['lat'],
            'lng'            => $m['lng'],
            'infobox_title'  => $m['infobox_title']   ?? '',
            'infobox_content'=> $m['infobox_content']  ?? '',
            'iconJson'       => $m['marker_icon_json'] ?? '',
        ];
    }

    protected function configFieldItems(): array
    {
        return [
            'enable_multiple' => [
                'display'      => 'Enable Multiple Markers',
                'instructions' => 'Allow adding multiple markers to this field.',
                'type'         => 'toggle',
                'default'      => false,
                'width'        => 50,
            ],
            'enable_clustering' => [
                'display'      => 'Enable Marker Clustering',
                'instructions' => 'Group nearby markers into clusters. Only applies when multiple markers are enabled.',
                'type'         => 'toggle',
                'default'      => false,
                'width'        => 50,
            ],
            'cluster_color' => [
                'display'      => 'Cluster Colour',
                'instructions' => 'Hex colour for the cluster circle (e.g. #4F46E5). Leave blank for the default indigo.',
                'type'         => 'color',
                'default'      => '',
                'width'        => 50,
                'if'           => ['enable_clustering' => 'equals true'],
            ],
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
            'marker_icon_asset' => [
                'display'      => 'Custom Marker Icon',
                'instructions' => 'Custom marker image (PNG or SVG). Overrides the global icon for this field. Leave blank to inherit.',
                'type'         => 'assets',
                'max_files'    => 1,
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