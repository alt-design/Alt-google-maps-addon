<?php

namespace AltDesign\AltGoogleMapsAddon\Tags;

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
        $zoom     = (int) $this->params->get('zoom', config('statamic-google-maps.defaults.zoom', 13));
        $height   = (int) $this->params->get('height', config('statamic-google-maps.map_height', 420));
        $width    = $this->params->get('width', '100%');
        $id       = $this->params->get('id', 'gmap-' . uniqid());
        $provider = $this->params->get('provider', config('statamic-google-maps.map_provider', 'google'));
        $key      = config('statamic-google-maps.api_key', '');
        $mapType  = $this->params->get('map_type', config('statamic-google-maps.map_type', 'roadmap'));

        if ($lat === null || $lng === null) {
            return '<!-- google_map: lat and lng are required -->';
        }

        if ($provider === 'google' && empty($key)) {
            return '<!-- google_map: GOOGLE_MAPS_API_KEY not configured -->';
        }

        return view('statamic-google-maps::map', [
            'lat'      => (float) $lat,
            'lng'      => (float) $lng,
            'zoom'     => $zoom,
            'height'   => $height,
            'width'    => $width,
            'id'       => $id,
            'provider' => $provider,
            'key'      => $key,
            'map_type' => $mapType,
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
        $key  = config('statamic-google-maps.api_key', '');

        if ($lat === null || $lng === null || empty($key)) {
            return '';
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        return "https://maps.googleapis.com/maps/api/staticmap?"
            . "center={$lat},{$lng}&zoom={$zoom}&size={$w}x{$h}"
            . "&markers=color:red%7C{$lat},{$lng}&key={$key}";
    }
}
