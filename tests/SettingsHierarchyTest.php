<?php

use AltDesign\AltGoogleMapsAddon\Tests\SettingsTestCase;

uses(SettingsTestCase::class);

it('overrides map_provider in runtime config from settings.json', function () {
    expect(config('statamic-google-maps.map_provider'))->toBe('osm');
});

it('overrides default lat/lng/zoom in runtime config from settings.json', function () {
    expect(config('statamic-google-maps.defaults.lat'))->toBe(40.7128);
    expect(config('statamic-google-maps.defaults.lng'))->toBe(-74.0060);
    expect(config('statamic-google-maps.defaults.zoom'))->toBe(11);
});
