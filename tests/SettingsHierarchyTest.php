<?php

namespace AltDesign\AltGoogleMapsAddon\Tests;

class SettingsHierarchyTest extends TestCase
{
    private string $storageFile;

    protected function setUp(): void
    {
        $this->storageFile = storage_path('app/statamic/google-maps-settings.json');
        @mkdir(dirname($this->storageFile), 0755, true);

        file_put_contents($this->storageFile, json_encode([
            'lat'          => 40.7128,
            'lng'          => -74.0060,
            'zoom'         => 11,
            'map_provider' => 'osm',
        ]));

        parent::setUp();
    }

    protected function tearDown(): void
    {
        @unlink($this->storageFile);
        parent::tearDown();
    }

    public function test_settings_json_overrides_map_provider_in_runtime_config(): void
    {
        $this->assertSame('osm', config('statamic-google-maps.map_provider'));
    }

    public function test_settings_json_overrides_default_lat_lng_zoom_in_runtime_config(): void
    {
        $this->assertSame(40.7128, config('statamic-google-maps.defaults.lat'));
        $this->assertSame(-74.0060, config('statamic-google-maps.defaults.lng'));
        $this->assertSame(11, config('statamic-google-maps.defaults.zoom'));
    }

}
