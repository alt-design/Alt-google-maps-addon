<?php

namespace AltDesign\AltGoogleMapsAddon\Tests;

use AltDesign\AltGoogleMapsAddon\Fieldtypes\GoogleMapsFieldtype;
use AltDesign\AltGoogleMapsAddon\Tags\GoogleMapTag;
use Statamic\Tags\Context;
use Statamic\Tags\Parameters;

class ProviderHierarchyTest extends TestCase
{
    public function test_fieldtype_per_field_provider_overrides_global(): void
    {
        config(['statamic-google-maps.map_provider' => 'google']);

        $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
            'type'         => 'google_maps',
            'map_provider' => 'osm',
        ]));

        $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
        $this->assertSame('osm', $out['map_provider']);
    }

    public function test_fieldtype_falls_back_to_global_when_blank(): void
    {
        config(['statamic-google-maps.map_provider' => 'osm']);

        $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
            'type'         => 'google_maps',
            'map_provider' => '',
        ]));

        $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
        $this->assertSame('osm', $out['map_provider']);
    }

    public function test_augment_returns_null_when_lat_missing(): void
    {
        $ft = new GoogleMapsFieldtype;
        $this->assertNull($ft->augment(['lat' => null, 'lng' => null]));
    }

    public function test_process_returns_null_when_coords_empty(): void
    {
        $ft = new GoogleMapsFieldtype;
        $this->assertNull($ft->process(['lat' => null, 'lng' => null, 'address' => '']));
    }

    public function test_process_rounds_coords_to_seven_decimals(): void
    {
        $ft = new GoogleMapsFieldtype;
        $out = $ft->process(['lat' => 51.12345678912, 'lng' => -0.12345678912, 'zoom' => 10, 'address' => ' foo ']);
        $this->assertEqualsWithDelta(51.1234568, $out['lat'], 1e-7);
        $this->assertEqualsWithDelta(-0.1234568, $out['lng'], 1e-7);
        $this->assertSame('foo', $out['address']);
    }

    public function test_preprocess_index_truncates_long_address(): void
    {
        $ft = new GoogleMapsFieldtype;
        $long = str_repeat('a', 80);
        $out = $ft->preProcessIndex(['lat' => 1, 'lng' => 2, 'address' => $long]);
        $this->assertSame(50 + strlen('…'), strlen($out));
    }

    private function tag(array $params): GoogleMapTag
    {
        $tag = new GoogleMapTag;
        $tag->setContext(new Context([]));
        $tag->setParameters($params);
        return $tag;
    }

    public function test_google_map_tag_renders_osm_branch(): void
    {
        config(['statamic-google-maps.map_provider' => 'osm']);
        $out = $this->tag(['lat' => '51.5', 'lng' => '-0.12', 'zoom' => '10'])->index();
        $this->assertStringContainsString('leaflet', $out);
        $this->assertStringNotContainsString('maps.googleapis.com/maps/api/js', $out);
    }

    public function test_google_map_tag_renders_google_branch(): void
    {
        config(['statamic-google-maps.map_provider' => 'google', 'statamic-google-maps.api_key' => 'XYZ']);
        $out = $this->tag(['lat' => '1', 'lng' => '2'])->index();
        $this->assertStringContainsString('maps.googleapis.com/maps/api/js', $out);
        $this->assertStringContainsString('key=XYZ', $out);
    }

    public function test_google_map_tag_warns_when_google_key_missing(): void
    {
        config(['statamic-google-maps.api_key' => '', 'statamic-google-maps.map_provider' => 'google']);
        $out = $this->tag(['lat' => '1', 'lng' => '2'])->index();
        $this->assertStringContainsString('GOOGLE_MAPS_API_KEY not configured', $out);
    }

    public function test_google_map_tag_warns_when_coords_missing(): void
    {
        $out = $this->tag([])->index();
        $this->assertStringContainsString('lat and lng are required', $out);
    }

    public function test_augment_exposes_map_height_from_field_config(): void
    {
        config(['statamic-google-maps.map_height' => 420]);
        $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
            'type'       => 'google_maps',
            'map_height' => 250,
        ]));
        $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
        $this->assertSame(250, $out['map_height']);
    }

    public function test_augment_falls_back_to_global_map_height(): void
    {
        config(['statamic-google-maps.map_height' => 600]);
        $ft = new GoogleMapsFieldtype;
        $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
        $this->assertSame(600, $out['map_height']);
    }

    public function test_default_value_uses_per_field_zoom_lat_lng(): void
    {
        config([
            'statamic-google-maps.defaults.lat'  => 10.0,
            'statamic-google-maps.defaults.lng'  => 20.0,
            'statamic-google-maps.defaults.zoom' => 5,
        ]);

        $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
            'type'         => 'google_maps',
            'default_lat'  => -23.5,
            'default_lng'  => -46.6,
            'default_zoom' => 12,
        ]));

        $defaults = $ft->defaultValue();
        $this->assertSame(-23.5, $defaults['lat']);
        $this->assertSame(-46.6, $defaults['lng']);
        $this->assertSame(12, $defaults['zoom']);
    }

    public function test_default_value_falls_back_to_global_defaults(): void
    {
        config([
            'statamic-google-maps.defaults.lat'  => 10.0,
            'statamic-google-maps.defaults.lng'  => 20.0,
            'statamic-google-maps.defaults.zoom' => 7,
        ]);

        $ft = new GoogleMapsFieldtype;
        $defaults = $ft->defaultValue();
        $this->assertSame(10.0, $defaults['lat']);
        $this->assertSame(20.0, $defaults['lng']);
        $this->assertSame(7, $defaults['zoom']);
    }

    public function test_static_map_url_urlencodes_api_key(): void
    {
        config(['statamic-google-maps.api_key' => 'abc&def=xyz']);
        $ft = new GoogleMapsFieldtype;
        $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
        $this->assertStringContainsString('key=abc%26def%3Dxyz', $out['static_map_url']);
    }

    public function test_augment_marker_icon_returns_array_when_url_set(): void
    {
        config([
            'statamic-google-maps.marker_icon_url'    => 'https://example.com/pin.png',
            'statamic-google-maps.marker_icon_width'  => 32,
            'statamic-google-maps.marker_icon_height' => 40,
        ]);
        $ft = new GoogleMapsFieldtype;
        $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
        $this->assertIsArray($out['marker_icon']);
        $this->assertSame('https://example.com/pin.png', $out['marker_icon']['url']);
        $this->assertSame(32, $out['marker_icon']['width']);
        $this->assertSame(40, $out['marker_icon']['height']);
    }

    public function test_augment_marker_icon_is_null_when_no_url(): void
    {
        config(['statamic-google-maps.marker_icon_url' => '']);
        $ft = new GoogleMapsFieldtype;
        $out = $ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10]);
        $this->assertNull($out['marker_icon']);
    }

    public function test_tag_param_provider_overrides_config(): void
    {
        config(['statamic-google-maps.map_provider' => 'google', 'statamic-google-maps.api_key' => 'X']);
        $out = $this->tag(['lat' => '1', 'lng' => '2', 'provider' => 'osm'])->index();
        $this->assertStringContainsString('leaflet', $out);
    }
}
