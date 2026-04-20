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
            $nav->create('Google Maps')
                ->section('Tools')
                ->route('google-maps.settings')
                ->icon('pin');
        });

        if (! $this->app->runningInConsole()) {
            $storageFile = storage_path('app/statamic/google-maps-settings.json');
            $storedDefaults = [];
            if (file_exists($storageFile)) {
                $data = json_decode(file_get_contents($storageFile), true);
                if (is_array($data)) {
                    $storedDefaults = $data;
                }
            }

            $defaults = array_merge(
                (array) config('statamic-google-maps.defaults'),
                $storedDefaults
            );

            \Statamic\Statamic::provideToScript([
                'googleMaps' => [
                    'api_key'    => config('statamic-google-maps.api_key'),
                    'defaults'   => $defaults,
                    'map_height' => config('statamic-google-maps.map_height', 420),
                    'map_type'   => config('statamic-google-maps.map_type', 'roadmap'),
                ],
            ]);
        }
    }
}
