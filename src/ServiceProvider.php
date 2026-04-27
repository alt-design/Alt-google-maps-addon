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
            __DIR__ . '/../config/statamic-google-maps.php'
                => config_path('statamic-google-maps.php'),
        ], 'statamic-google-maps-config');

        $this->publishes([
            __DIR__ . '/../resources/views'
                => resource_path('views/vendor/statamic-google-maps'),
        ], 'statamic-google-maps-views');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'statamic-google-maps');
        $this->mergeConfigFrom(
            __DIR__ . '/../config/statamic-google-maps.php',
            'statamic-google-maps'
        );

        \Statamic\Facades\CP\Nav::extend(function ($nav) {
            $nav->create('Alt Google Maps')
                ->section('Tools')
                ->route('google-maps.settings')
                ->icon('pin');
        });

        // Load settings saved via Tools → Google Maps and override the runtime config.
        // This ensures {{ config:statamic-google-maps:map_provider }} in Antlers templates,
        // config() calls in PHP, and the Antlers tag all read the same resolved value —
        // regardless of what GOOGLE_MAPS_PROVIDER is set to in .env.
        $storageFile = storage_path('app/statamic/google-maps-settings.json');
        $stored = [];
        if (file_exists($storageFile)) {
            $data = json_decode(file_get_contents($storageFile), true);
            if (is_array($data)) {
                $stored = $data;
            }
        }

        if (isset($stored['map_provider']) && in_array($stored['map_provider'], ['google', 'osm'], true)) {
            config(['statamic-google-maps.map_provider' => $stored['map_provider']]);
        }

        foreach (['lat', 'lng', 'zoom'] as $key) {
            if (array_key_exists($key, $stored) && $stored[$key] !== null) {
                config(["statamic-google-maps.defaults.$key" => $stored[$key]]);
            }
        }

        foreach (['infobox_title', 'infobox_content', 'map_style'] as $key) {
            if (array_key_exists($key, $stored)) {
                config(["statamic-google-maps.{$key}" => $stored[$key]]);
            }
        }

        if (! $this->app->runningInConsole()) {
            \Statamic\Statamic::provideToScript([
                'googleMaps' => [
                    'api_key'         => config('statamic-google-maps.api_key'),
                    'map_provider'    => config('statamic-google-maps.map_provider'),
                    'defaults'        => config('statamic-google-maps.defaults'),
                    'map_height'      => config('statamic-google-maps.map_height', 420),
                    'map_type'        => config('statamic-google-maps.map_type', 'roadmap'),
                    'infobox_title'   => config('statamic-google-maps.infobox_title', ''),
                    'infobox_content' => config('statamic-google-maps.infobox_content', ''),
                    'map_style'       => config('statamic-google-maps.map_style', ''),
                ],
            ]);
        }
    }
}
