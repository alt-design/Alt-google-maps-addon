<?php

use AltDesign\AltGoogleMapsAddon\Tags\GoogleMapTag;
use AltDesign\AltGoogleMapsAddon\Tests\TestCase;
use Statamic\Tags\Context;

uses(TestCase::class);

function renderTag(string $method, array $params): string
{
    $tag = new GoogleMapTag;
    $tag->setContext(new Context([]));
    $tag->setParameters($params);

    return $tag->{$method}();
}

beforeEach(function () {
    config(['alt-google-maps.api_key' => '', 'alt-google-maps.map_provider' => 'google']);
});

it('shows a visible message from the single map tag when the Google key is missing', function () {
    $html = renderTag('index', ['lat' => 51.5, 'lng' => -0.12]);

    expect($html)->toContain('no Google Maps API key has been configured');
    expect($html)->toContain('role="status"');
});

it('shows a visible message from the multi map tag when the Google key is missing', function () {
    $markers = json_encode([['lat' => 51.5, 'lng' => -0.12]]);

    $html = renderTag('multi', ['markers' => $markers]);

    expect($html)->toContain('no Google Maps API key has been configured');
});

it('renders the OpenStreetMap map without a Google key', function () {
    $html = renderTag('index', ['lat' => 51.5, 'lng' => -0.12, 'provider' => 'osm']);

    expect($html)->toContain('tile.openstreetmap.org');
    expect($html)->not->toContain('no Google Maps API key');
});

it('returns no static map URL without a key', function () {
    expect(renderTag('static', ['lat' => 51.5, 'lng' => -0.12]))->toBe('');
});

it('returns no static map URL for blank or non-numeric coordinates', function (array $params) {
    config(['alt-google-maps.api_key' => 'abc']);
    expect(renderTag('static', $params))->toBe('');
})->with([
    'blank strings' => [['lat' => '', 'lng' => '']],
    'not numbers' => [['lat' => 'north', 'lng' => 'west']],
]);
