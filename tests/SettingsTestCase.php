<?php

namespace AltDesign\AltGoogleMapsAddon\Tests;

abstract class SettingsTestCase extends TestCase
{
    protected string $storageFile;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->storageFile = $app->storagePath('app/statamic/google-maps-settings.json');
        @mkdir(dirname($this->storageFile), 0755, true);

        file_put_contents($this->storageFile, json_encode([
            'lat'          => 40.7128,
            'lng'          => -74.0060,
            'zoom'         => 11,
            'map_provider' => 'osm',
        ]));
    }

    protected function tearDown(): void
    {
        @unlink($this->storageFile);
        parent::tearDown();
    }
}
