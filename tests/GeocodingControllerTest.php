<?php

use AltDesign\AltGoogleMapsAddon\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

uses(TestCase::class);

beforeEach(function () {
    config(['statamic-google-maps.api_key' => 'test-google-key']);
    Cache::flush();
    RateLimiter::clear('alt-gmaps:nominatim');
    $user = \Statamic\Facades\User::make()->makeSuper();
    $user->save();
    $this->actingAs($user);
});

function postCp(string $uri, array $payload)
{
    return test()->postJson($uri, $payload);
}

it('returns Google geocode result on success', function () {
    Http::fake([
        'maps.googleapis.com/*' => Http::response([
            'status'  => 'OK',
            'results' => [[
                'geometry'          => ['location' => ['lat' => 51.5074, 'lng' => -0.1278]],
                'formatted_address' => 'London, UK',
            ]],
        ], 200),
    ]);

    postCp('/cp/google-maps/geocode', ['address' => 'London'])
        ->assertOk()
        ->assertJson(['success' => true, 'result' => ['lat' => 51.5074, 'lng' => -0.1278]]);
});

it('returns helpful message on Google REQUEST_DENIED', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'REQUEST_DENIED'], 200)]);

    $response = postCp('/cp/google-maps/geocode', ['address' => 'foo']);
    $response->assertOk()->assertJson(['success' => false]);
    expect($response->json('message'))->toContain('request denied');
});

it('suggests OSM when Google quota is exceeded', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'OVER_QUERY_LIMIT'], 200)]);

    $response = postCp('/cp/google-maps/geocode', ['address' => 'foo']);
    expect($response->json('message'))->toContain('OpenStreetMap');
});

it('handles Google non-JSON 500 response without crashing', function () {
    Http::fake(['maps.googleapis.com/*' => Http::response('<html>500 error</html>', 500)]);

    postCp('/cp/google-maps/geocode', ['address' => 'foo'])
        ->assertOk()
        ->assertJson(['success' => false]);
});

it('returns OSM geocode result on success', function () {
    Http::fake([
        'nominatim.openstreetmap.org/search*' => Http::response([[
            'lat' => '51.5074', 'lon' => '-0.1278', 'display_name' => 'London',
        ]], 200),
    ]);

    postCp('/cp/google-maps/geocode', ['address' => 'London', 'provider' => 'osm'])
        ->assertOk()
        ->assertJson(['success' => true, 'result' => ['lat' => 51.5074, 'lng' => -0.1278]]);
});

it('returns friendly message on Nominatim 429', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response('Too Many Requests', 429)]);

    $response = postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm']);
    $response->assertOk()->assertJson(['success' => false]);
    expect($response->json('message'))->toContain('Too many requests');
});

it('reports HTTP code on Nominatim 5xx', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response('oops', 503)]);

    $response = postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm']);
    $response->assertOk()->assertJson(['success' => false]);
    expect($response->json('message'))->toContain('HTTP 503');
});

it('handles Nominatim non-JSON HTML response', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response('<html>bad</html>', 200)]);

    postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm'])
        ->assertOk()
        ->assertJson(['success' => false, 'message' => 'No results found.']);
});

it('returns no-results when Nominatim returns empty array', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

    postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm'])
        ->assertOk()
        ->assertJson(['success' => false, 'message' => 'No results found.']);
});

it('sends User-Agent header to Nominatim per ToS', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

    postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm']);

    Http::assertSent(fn ($req) =>
        str_contains($req->url(), 'nominatim.openstreetmap.org')
        && str_contains($req->header('User-Agent')[0] ?? '', 'AltGoogleMapsAddon')
    );
});

it('handles Nominatim error body in reverse geocode', function () {
    Http::fake(['nominatim.openstreetmap.org/reverse*' => Http::response(['error' => 'Unable to geocode'], 200)]);

    postCp('/cp/google-maps/reverse-geocode', ['lat' => 0, 'lng' => 0, 'provider' => 'osm'])
        ->assertOk()
        ->assertJson(['success' => false, 'message' => 'No address found.']);
});

it('handles Nominatim HTTP failure in reverse geocode', function () {
    Http::fake(['nominatim.openstreetmap.org/reverse*' => Http::response('err', 500)]);

    postCp('/cp/google-maps/reverse-geocode', ['lat' => 0, 'lng' => 0, 'provider' => 'osm'])
        ->assertOk()
        ->assertJson(['success' => false, 'message' => 'Reverse geocoding failed.']);
});

it('validates address is required', function () {
    postCp('/cp/google-maps/geocode', [])->assertStatus(422);
});

it('validates lat/lng are numeric in reverse geocode', function () {
    postCp('/cp/google-maps/reverse-geocode', ['lat' => 'abc', 'lng' => 'def'])
        ->assertStatus(422);
});
