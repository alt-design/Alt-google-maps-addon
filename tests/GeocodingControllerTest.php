<?php

namespace AltDesign\AltGoogleMapsAddon\Tests;

use Illuminate\Support\Facades\Http;

class GeocodingControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['statamic-google-maps.api_key' => 'test-google-key']);
        $user = \Statamic\Facades\User::make()->makeSuper();
        $user->save();
        $this->actingAs($user);
    }

    private function postCp(string $uri, array $payload)
    {
        return $this->postJson($uri, $payload);
    }

    public function test_google_geocode_success(): void
    {
        Http::fake([
            'maps.googleapis.com/*' => Http::response([
                'status'  => 'OK',
                'results' => [[
                    'geometry'          => ['location' => ['lat' => 51.5074, 'lng' => -0.1278]],
                    'formatted_address' => 'London, UK',
                ]],
            ], 200),
        ]);

        $this->postCp('/cp/google-maps/geocode', ['address' => 'London'])
            ->assertOk()
            ->assertJson(['success' => true, 'result' => ['lat' => 51.5074, 'lng' => -0.1278]]);
    }

    public function test_google_geocode_request_denied_returns_helpful_message(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'REQUEST_DENIED'], 200)]);

        $response = $this->postCp('/cp/google-maps/geocode', ['address' => 'foo']);
        $response->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('request denied', $response->json('message'));
    }

    public function test_google_geocode_over_quota_suggests_osm(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response(['status' => 'OVER_QUERY_LIMIT'], 200)]);

        $response = $this->postCp('/cp/google-maps/geocode', ['address' => 'foo']);
        $this->assertStringContainsString('OpenStreetMap', $response->json('message'));
    }

    public function test_google_geocode_handles_non_json_response(): void
    {
        Http::fake(['maps.googleapis.com/*' => Http::response('<html>500 error</html>', 500)]);

        $this->postCp('/cp/google-maps/geocode', ['address' => 'foo'])
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_osm_geocode_success(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([[
                'lat' => '51.5074', 'lon' => '-0.1278', 'display_name' => 'London',
            ]], 200),
        ]);

        $this->postCp('/cp/google-maps/geocode', ['address' => 'London', 'provider' => 'osm'])
            ->assertOk()
            ->assertJson(['success' => true, 'result' => ['lat' => 51.5074, 'lng' => -0.1278]]);
    }

    public function test_osm_geocode_handles_429_rate_limit(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('Too Many Requests', 429)]);

        $response = $this->postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm']);
        $response->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('Too many requests', $response->json('message'));
    }

    public function test_osm_geocode_handles_5xx_error(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('oops', 503)]);

        $response = $this->postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm']);
        $response->assertOk()->assertJson(['success' => false]);
        $this->assertStringContainsString('HTTP 503', $response->json('message'));
    }

    public function test_osm_geocode_handles_non_json_html_response(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('<html>bad</html>', 200)]);

        $this->postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm'])
            ->assertOk()
            ->assertJson(['success' => false, 'message' => 'No results found.']);
    }

    public function test_osm_geocode_empty_array_returns_no_results(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

        $this->postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm'])
            ->assertOk()
            ->assertJson(['success' => false, 'message' => 'No results found.']);
    }

    public function test_osm_geocode_sends_user_agent_header(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([], 200)]);

        $this->postCp('/cp/google-maps/geocode', ['address' => 'x', 'provider' => 'osm']);

        Http::assertSent(fn ($req) =>
            str_contains($req->url(), 'nominatim.openstreetmap.org')
            && str_contains($req->header('User-Agent')[0] ?? '', 'AltGoogleMapsAddon')
        );
    }

    public function test_osm_reverse_geocode_handles_error_body(): void
    {
        Http::fake(['nominatim.openstreetmap.org/reverse*' => Http::response(['error' => 'Unable to geocode'], 200)]);

        $this->postCp('/cp/google-maps/reverse-geocode', ['lat' => 0, 'lng' => 0, 'provider' => 'osm'])
            ->assertOk()
            ->assertJson(['success' => false, 'message' => 'No address found.']);
    }

    public function test_osm_reverse_geocode_http_failure(): void
    {
        Http::fake(['nominatim.openstreetmap.org/reverse*' => Http::response('err', 500)]);

        $this->postCp('/cp/google-maps/reverse-geocode', ['lat' => 0, 'lng' => 0, 'provider' => 'osm'])
            ->assertOk()
            ->assertJson(['success' => false, 'message' => 'Reverse geocoding failed.']);
    }

    public function test_geocode_requires_address(): void
    {
        $this->postCp('/cp/google-maps/geocode', [])->assertStatus(422);
    }

    public function test_reverse_geocode_requires_numeric_coords(): void
    {
        $this->postCp('/cp/google-maps/reverse-geocode', ['lat' => 'abc', 'lng' => 'def'])
            ->assertStatus(422);
    }
}
