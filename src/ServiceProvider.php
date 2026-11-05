<?php

namespace AltDesign\AltGoogleMapsAddon;

use Statamic\Providers\AddonServiceProvider;
use AltDesign\AltGoogleMapsAddon\Fieldtypes\GoogleMapsFieldtype;
use AltDesign\AltGoogleMapsAddon\Tags\GoogleMapTag;

class ServiceProvider extends AddonServiceProvider
{
    protected $vite = [
        'input' => [
            'resources/js/cp.js',
            'resources/css/cp.css',
        ],
        'publicDirectory' => 'resources/dist',
    ];

    protected $routes = [
        'cp' => __DIR__ . '/../routes/cp.php',
    ];

    protected $fieldtypes = [
        GoogleMapsFieldtype::class,
    ];

    protected $tags = [
        GoogleMapTag::class,
    ];

    public function bootAddon()
    {
        $this->publishes([
            __DIR__ . '/../config/alt-google-maps.php'
                => config_path('alt-google-maps.php'),
        ], 'alt-google-maps-config');

        $this->publishes([
            __DIR__ . '/../resources/views'
                => resource_path('views/vendor/alt-google-maps'),
        ], 'alt-google-maps-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'alt-google-maps');
        $this->mergeConfigFrom(
            __DIR__ . '/../config/alt-google-maps.php',
            'alt-google-maps'
        );

        \Statamic\Facades\CP\Nav::extend(function ($nav) {
            $nav->create('Alt Google Maps')
                ->section('Tools')
                ->route('google-maps.settings')
                ->icon('pin');
        });

        // Load settings saved via Tools → Google Maps and override the runtime config.
        // This ensures {{ config:alt-google-maps:map_provider }} in Antlers templates,
        // config() calls in PHP, and the Antlers tag all read the same resolved value —
        // regardless of what GOOGLE_MAPS_PROVIDER is set to in .env.
        $storageFile = storage_path('app/statamic/google-maps-settings.json');
        $stored = [];
        if (file_exists($storageFile)) {
            try {
                $data = json_decode(file_get_contents($storageFile), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data)) {
                    $stored = $data;
                }
            } catch (\JsonException $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Corrupted google-maps-settings.json — falling back to config defaults',
                    ['error' => $e->getMessage(), 'path' => $storageFile]
                );
            }
        }

        if (isset($stored['map_provider']) && in_array($stored['map_provider'], ['google', 'osm'], true)) {
            config(['alt-google-maps.map_provider' => $stored['map_provider']]);
        }

        foreach (['lat', 'lng', 'zoom'] as $key) {
            if (array_key_exists($key, $stored) && $stored[$key] !== null) {
                config(["alt-google-maps.defaults.$key" => $stored[$key]]);
            }
        }

        foreach (['infobox_title', 'infobox_content', 'map_style', 'marker_icon_url'] as $key) {
            if (array_key_exists($key, $stored)) {
                config(["alt-google-maps.{$key}" => $stored[$key]]);
            }
        }

        foreach (['marker_icon_width', 'marker_icon_height'] as $key) {
            if (array_key_exists($key, $stored) && $stored[$key] !== null) {
                config(["alt-google-maps.{$key}" => (int) $stored[$key]]);
            }
        }

        if (! $this->app->runningInConsole()) {
            // The Google Maps JavaScript API requires the key in the <script src=…> URL —
            // there is no server-side proxy alternative for the client SDK. Restrict the
            // key in Google Cloud Console (HTTP referrers + API restrictions) — see README.
            // The key must be available even when the global provider is OSM, because individual
            // fields may override map_provider to 'google' (per-field config beats global).
            $apiKey = (string) config('alt-google-maps.api_key', '');
            $payload = [
                'api_key'         => $apiKey,
                'map_provider'    => config('alt-google-maps.map_provider', 'google'),
                'defaults'        => config('alt-google-maps.defaults'),
                'map_height'      => config('alt-google-maps.map_height', 420),
                'map_type'        => config('alt-google-maps.map_type', 'roadmap'),
                'infobox_title'      => config('alt-google-maps.infobox_title', ''),
                'infobox_content'    => config('alt-google-maps.infobox_content', ''),
                'map_style'          => config('alt-google-maps.map_style', ''),
                'marker_icon_url'    => config('alt-google-maps.marker_icon_url', ''),
                'marker_icon_width'  => config('alt-google-maps.marker_icon_width', 40),
                'marker_icon_height' => config('alt-google-maps.marker_icon_height', 40),
            ];

            \Statamic\Statamic::provideToScript(['googleMaps' => $payload]);
        }
    }
}