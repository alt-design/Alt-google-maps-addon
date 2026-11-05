<?php

namespace AltDesign\AltGoogleMapsAddon\Tags;

use Illuminate\Support\Facades\Log;
use Statamic\Tags\Tags;

class GoogleMapTag extends Tags
{
    protected static $handle = 'google_map';

    /**
     * {{ google_map :lat="location:lat" :lng="location:lng" :zoom="location:zoom" height="400" }}
     */
    public function index(): string
    {
        $lat      = $this->params->get('lat');
        $lng      = $this->params->get('lng');
        $zoom     = (int) $this->params->get('zoom', config('alt-google-maps.defaults.zoom', 13));
        $height   = (int) $this->params->get('height', config('alt-google-maps.map_height', 420));
        $width    = $this->params->get('width', '100%');
        $id       = $this->params->get('id', 'gmap-' . uniqid());
        $provider = $this->params->get('provider', config('alt-google-maps.map_provider', 'google'));
        $key             = config('alt-google-maps.api_key', '');
        $mapType         = $this->params->get('map_type', config('alt-google-maps.map_type', 'roadmap'));
        $markerIconUrl   = $this->params->get('marker_icon', config('alt-google-maps.marker_icon_url', ''));
        $markerIconWidth = (int) $this->params->get('marker_icon_width', config('alt-google-maps.marker_icon_width', 40));
        $markerIconHeight= (int) $this->params->get('marker_icon_height', config('alt-google-maps.marker_icon_height', 40));

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return '<!-- google_map: lat and lng are required -->';
        }

        if ($provider === 'google' && empty($key)) {
            return $this->unavailable($id, $width, $height);
        }

        return view('alt-google-maps::map', [
            'lat'               => (float) $lat,
            'lng'               => (float) $lng,
            'zoom'              => $zoom,
            'height'            => $height,
            'width'             => $width,
            'id'                => $id,
            'provider'          => $provider,
            'key'               => $key,
            'map_type'          => $mapType,
            'marker_icon_url'   => $markerIconUrl,
            'marker_icon_width' => $markerIconWidth,
            'marker_icon_height'=> $markerIconHeight,
        ])->render();
    }

    /**
     * {{ google_map:multi :markers="location:markers_json" :clustering="location:enable_clustering" :zoom="location:zoom" height="400" }}
     * Renders a multi-marker map with optional clustering (Google Maps only).
     */
    public function multi(): string
    {
        $markersJson = $this->params->get('markers', '[]');
        $clustering   = filter_var($this->params->get('clustering', false), FILTER_VALIDATE_BOOLEAN);
        $clusterColor = (string) $this->params->get('cluster_color', '');
        $zoom        = $this->params->get('zoom') !== null ? (int) $this->params->get('zoom') : null;
        $height      = (int) $this->params->get('height', config('alt-google-maps.map_height', 420));
        $width       = $this->params->get('width', '100%');
        $id          = $this->params->get('id', 'gmap-multi-' . uniqid());
        $provider    = $this->params->get('provider', config('alt-google-maps.map_provider', 'google'));
        $key         = config('alt-google-maps.api_key', '');
        $mapType     = $this->params->get('map_type', config('alt-google-maps.map_type', 'roadmap'));
        $defaultZoom = (int) $this->params->get('default_zoom', config('alt-google-maps.defaults.zoom', 13));

        // Decode to validate; pass raw JSON string to the view
        $markers = json_decode($markersJson, true);
        if (! is_array($markers) || empty($markers)) {
            return '<!-- google_map:multi: no valid markers -->';
        }

        if ($provider === 'google' && empty($key)) {
            return $this->unavailable($id, $width, $height);
        }

        return view('alt-google-maps::map-multi', [
            'markers_json'  => $markersJson,
            'clustering'    => $clustering,
            'cluster_color' => $clusterColor,
            'zoom'         => $zoom,
            'default_zoom' => $defaultZoom,
            'height'       => $height,
            'width'        => $width,
            'id'           => $id,
            'provider'     => $provider,
            'key'          => $key,
            'map_type'     => $mapType,
        ])->render();
    }

    /**
     * {{ google_map:static :lat="location:lat" :lng="location:lng" }}
     * Returns a static map image URL (Google Maps only).
     */
    public function static(): string
    {
        $lat  = $this->params->get('lat');
        $lng  = $this->params->get('lng');
        $zoom = (int) $this->params->get('zoom', 13);
        $w    = (int) $this->params->get('width', 600);
        $h    = (int) $this->params->get('height', 300);
        $key  = config('alt-google-maps.api_key', '');

        if (! is_numeric($lat) || ! is_numeric($lng) || empty($key)) {
            return '';
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        return "https://maps.googleapis.com/maps/api/staticmap?"
            . "center={$lat},{$lng}&zoom={$zoom}&size={$w}x{$h}"
            . "&markers=color:red%7C{$lat},{$lng}&key=" . urlencode($key);
    }

    /**
     * Render a visible placeholder when the Google provider is selected but no API key is set.
     *
     * @param  string  $id
     * @param  string  $width
     * @param  int  $height
     * @return string
     */
    private function unavailable(string $id, string $width, int $height): string
    {
        Log::warning('Alt Google Maps: the Google provider is selected but GOOGLE_MAPS_API_KEY is not set.');

        return view('alt-google-maps::map-unavailable', [
            'id'      => $id,
            'width'   => $width,
            'height'  => $height,
            'message' => 'This map cannot be shown because no Google Maps API key has been configured.',
        ])->render();
    }
}
