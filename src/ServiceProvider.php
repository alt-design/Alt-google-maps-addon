<?php

namespace AltDesign\AltGoogleMapsAddon;

use AltDesign\AltGoogleMapsAddon\Settings\MapSettings;
use AltDesign\AltGoogleMapsAddon\Settings\SettingsBlueprint;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Permission;
use Statamic\Providers\AddonServiceProvider;
use Statamic\Statamic;
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

        $this->registerSettingsBlueprint(
            fn () => SettingsBlueprint::contents(__DIR__ . '/../resources/blueprints/settings.yaml')
        );
        $this->registerPermissions();
        $this->addToNav();
        $this->applySavedSettings();
        $this->provideSettingsToScript();
    }

    /**
     * Register the permission that allows a control panel user to use the address search.
     *
     * @return void
     */
    protected function registerPermissions(): void
    {
        Permission::register('use alt-google-maps geocoding')
            ->label('Use the Alt Google Maps address search');
    }

    /**
     * Add a Tools link to the addon settings page for users who may edit those settings.
     *
     * @return void
     */
    protected function addToNav(): void
    {
        Nav::extend(function ($nav) {
            $addon = $this->getAddon();

            $nav->create('Alt Google Maps')
                ->section('Tools')
                ->url($addon->settingsUrl())
                ->can("edit {$addon->package()} settings")
                ->icon('pin');
        });
    }

    /**
     * Override the runtime config with the values saved on the settings page, so that
     * config(), Antlers and the tags all read the same resolved value.
     *
     * @return void
     */
    public function applySavedSettings(): void
    {
        $saved = $this->getAddon()->settings()->raw();

        foreach (MapSettings::overrides($saved) as $key => $value) {
            config(["alt-google-maps.$key" => $value]);
        }
    }

    /**
     * Expose the resolved settings to the control panel JavaScript.
     *
     * The Maps JavaScript API needs the key in the script URL, so it is sent to the browser
     * even when the global provider is OSM, because a field can override the provider.
     *
     * @return void
     */
    protected function provideSettingsToScript(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        Statamic::provideToScript(['googleMaps' => [
            'api_key'            => (string) config('alt-google-maps.api_key', ''),
            'map_provider'       => config('alt-google-maps.map_provider', 'google'),
            'defaults'           => config('alt-google-maps.defaults'),
            'map_height'         => config('alt-google-maps.map_height', 420),
            'map_type'           => config('alt-google-maps.map_type', 'roadmap'),
            'infobox_title'      => config('alt-google-maps.infobox_title', ''),
            'infobox_content'    => config('alt-google-maps.infobox_content', ''),
            'map_style'          => config('alt-google-maps.map_style', ''),
            'marker_icon_url'    => config('alt-google-maps.marker_icon_url', ''),
            'marker_icon_width'  => config('alt-google-maps.marker_icon_width', 40),
            'marker_icon_height' => config('alt-google-maps.marker_icon_height', 40),
        ]]);
    }
}
