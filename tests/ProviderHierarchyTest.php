<?php

use AltDesign\AltGoogleMapsAddon\Fieldtypes\GoogleMapsFieldtype;
use AltDesign\AltGoogleMapsAddon\Tags\GoogleMapTag;
use AltDesign\AltGoogleMapsAddon\Tests\TestCase;
use Statamic\Tags\Context;

uses(TestCase::class);

function tag(array $params): GoogleMapTag
{
    $tag = new GoogleMapTag;
    $tag->setContext(new Context([]));
    $tag->setParameters($params);
    return $tag;
}

it('uses per-field provider over global', function () {
    config(['alt-google-maps.map_provider' => 'google']);

    $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
        'type'         => 'google_maps',
        'map_provider' => 'osm',
    ]));

    $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
    expect($out['map_provider'])->toBe('osm');
});

it('falls back to global provider when blank', function () {
    config(['alt-google-maps.map_provider' => 'osm']);

    $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
        'type'         => 'google_maps',
        'map_provider' => '',
    ]));

    $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
    expect($out['map_provider'])->toBe('osm');
});

it('returns null from augment when lat is missing', function () {
    expect((new GoogleMapsFieldtype)->augment(['lat' => null, 'lng' => null]))->toBeNull();
});

it('returns null from process when coords are empty', function () {
    expect((new GoogleMapsFieldtype)->process(['lat' => null, 'lng' => null, 'address' => '']))->toBeNull();
});

it('rounds coords to seven decimals on process', function () {
    $out = (new GoogleMapsFieldtype)->process([
        'lat' => 51.12345678912, 'lng' => -0.12345678912, 'zoom' => 10, 'address' => ' foo ',
    ]);
    expect($out['lat'])->toEqualWithDelta(51.1234568, 1e-7);
    expect($out['lng'])->toEqualWithDelta(-0.1234568, 1e-7);
    expect($out['address'])->toBe('foo');
});

it('truncates long address in preProcessIndex', function () {
    $out = (new GoogleMapsFieldtype)->preProcessIndex(['lat' => 1, 'lng' => 2, 'address' => str_repeat('a', 80)]);
    expect(strlen($out))->toBe(50 + strlen('…'));
});

it('renders OSM branch in google_map tag', function () {
    config(['alt-google-maps.map_provider' => 'osm']);
    $out = tag(['lat' => '51.5', 'lng' => '-0.12', 'zoom' => '10'])->index();
    expect($out)->toContain('leaflet')
        ->not->toContain('maps.googleapis.com/maps/api/js');
});

it('renders Google branch with API key in google_map tag', function () {
    config(['alt-google-maps.map_provider' => 'google', 'alt-google-maps.api_key' => 'XYZ']);
    $out = tag(['lat' => '1', 'lng' => '2'])->index();
    expect($out)->toContain('maps.googleapis.com/maps/api/js')
        ->toContain('key=XYZ');
});

it('warns when Google API key is missing in tag', function () {
    config(['alt-google-maps.api_key' => '', 'alt-google-maps.map_provider' => 'google']);
    expect(tag(['lat' => '1', 'lng' => '2'])->index())
        ->toContain('no Google Maps API key has been configured');
});

it('warns when coords are missing in tag', function () {
    expect(tag([])->index())->toContain('lat and lng are required');
});

it('exposes per-field map_height in augment', function () {
    config(['alt-google-maps.map_height' => 420]);
    $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
        'type'       => 'google_maps',
        'map_height' => 250,
    ]));
    $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
    expect($out['map_height'])->toBe(250);
});

it('falls back to global map_height in augment', function () {
    config(['alt-google-maps.map_height' => 600]);
    $out = (new GoogleMapsFieldtype)->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
    expect($out['map_height'])->toBe(600);
});

it('uses per-field default lat/lng/zoom in defaultValue', function () {
    config([
        'alt-google-maps.defaults.lat'  => 10.0,
        'alt-google-maps.defaults.lng'  => 20.0,
        'alt-google-maps.defaults.zoom' => 5,
    ]);

    $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
        'type'         => 'google_maps',
        'default_lat'  => -23.5,
        'default_lng'  => -46.6,
        'default_zoom' => 12,
    ]));

    $defaults = $ft->defaultValue();
    expect($defaults['lat'])->toBe(-23.5);
    expect($defaults['lng'])->toBe(-46.6);
    expect($defaults['zoom'])->toBe(12);
});

it('falls back to global defaults in defaultValue', function () {
    config([
        'alt-google-maps.defaults.lat'  => 10.0,
        'alt-google-maps.defaults.lng'  => 20.0,
        'alt-google-maps.defaults.zoom' => 7,
    ]);

    $defaults = (new GoogleMapsFieldtype)->defaultValue();
    expect($defaults['lat'])->toBe(10.0);
    expect($defaults['lng'])->toBe(20.0);
    expect($defaults['zoom'])->toBe(7);
});

it('urlencodes the API key in static_map_url', function () {
    config(['alt-google-maps.api_key' => 'abc&def=xyz']);
    $out = (new GoogleMapsFieldtype)->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
    expect($out['static_map_url'])->toContain('key=abc%26def%3Dxyz');
});

it('returns marker_icon as array when URL is set', function () {
    config([
        'alt-google-maps.marker_icon_url'    => 'https://example.com/pin.png',
        'alt-google-maps.marker_icon_width'  => 32,
        'alt-google-maps.marker_icon_height' => 40,
    ]);
    $out = (new GoogleMapsFieldtype)->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
    expect($out['marker_icon'])->toBeArray()
        ->and($out['marker_icon']['url'])->toBe('https://example.com/pin.png')
        ->and($out['marker_icon']['width'])->toBe(32)
        ->and($out['marker_icon']['height'])->toBe(40);
});

it('returns marker_icon as null when no URL is set', function () {
    config(['alt-google-maps.marker_icon_url' => '']);
    $out = (new GoogleMapsFieldtype)->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
    expect($out['marker_icon'])->toBeNull();
});

it('overrides config provider with tag param', function () {
    config(['alt-google-maps.map_provider' => 'google', 'alt-google-maps.api_key' => 'X']);
    $out = tag(['lat' => '1', 'lng' => '2', 'provider' => 'osm'])->index();
    expect($out)->toContain('leaflet');
});
