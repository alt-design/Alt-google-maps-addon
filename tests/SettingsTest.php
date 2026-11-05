<?php

use AltDesign\AltGoogleMapsAddon\Settings\MapSettings;
use AltDesign\AltGoogleMapsAddon\Settings\SettingsBlueprint;
use AltDesign\AltGoogleMapsAddon\Tests\TestCase;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Addon;

uses(TestCase::class);

beforeEach(function () {
    $this->settingsPath = resource_path('addons/alt-google-maps.yaml');
});

afterEach(function () {
    File::delete($this->settingsPath);
});

function savedSettings(array $values): void
{
    File::ensureDirectoryExists(dirname(test()->settingsPath));
    File::put(test()->settingsPath, \Statamic\Facades\YAML::dump($values));
}

function bootProviderWith(array $values): void
{
    savedSettings($values);
    app()->getProvider(\AltDesign\AltGoogleMapsAddon\ServiceProvider::class)->applySavedSettings();
}

it('registers the addon settings blueprint', function () {
    $addon = Addon::get('alt-design/alt-google-maps');

    expect($addon)->not->toBeNull();
    expect($addon->hasSettingsBlueprint())->toBeTrue();
    expect($addon->settingsBlueprint()->hasField('map_provider'))->toBeTrue();
});

it('overrides the runtime config from the saved settings', function () {
    config(['alt-google-maps.map_provider' => 'google']);

    bootProviderWith(['map_provider' => 'osm', 'lat' => 40.7128, 'lng' => -74.006, 'zoom' => 11]);

    expect(config('alt-google-maps.map_provider'))->toBe('osm');
    expect(config('alt-google-maps.defaults.lat'))->toBe(40.7128);
    expect(config('alt-google-maps.defaults.lng'))->toBe(-74.006);
    expect(config('alt-google-maps.defaults.zoom'))->toBe(11);
});

it('keeps the config value when a saved setting is blank', function () {
    config(['alt-google-maps.map_provider' => 'osm', 'alt-google-maps.defaults.zoom' => 9]);

    bootProviderWith(['map_provider' => null, 'zoom' => '', 'lat' => null]);

    expect(config('alt-google-maps.map_provider'))->toBe('osm');
    expect(config('alt-google-maps.defaults.zoom'))->toBe(9);
});

it('ignores a provider that is not google or osm', function () {
    config(['alt-google-maps.map_provider' => 'osm']);

    bootProviderWith(['map_provider' => 'bing']);

    expect(config('alt-google-maps.map_provider'))->toBe('osm');
});

it('sanitises infobox values read from the saved settings', function () {
    $overrides = MapSettings::overrides([
        'infobox_title'   => '<b>Head office</b>',
        'infobox_content' => '<p>Open</p><script>alert(1)</script>',
    ]);

    expect($overrides['infobox_title'])->toBe('Head office');
    expect($overrides['infobox_content'])->not->toContain('<script');
});

it('casts the marker icon size to integers', function () {
    $overrides = MapSettings::overrides(['marker_icon_width' => '32', 'marker_icon_height' => '48']);

    expect($overrides['marker_icon_width'])->toBe(32);
    expect($overrides['marker_icon_height'])->toBe(48);
});

it('returns no marker icon URL when no asset is selected', function () {
    expect(MapSettings::markerIconUrl(null))->toBe('');
    expect(MapSettings::markerIconUrl([]))->toBe('');
    expect(MapSettings::markerIconUrl(['missing::pin.png']))->toBe('');
});

function settingsUserWith(array $permissions): \Statamic\Contracts\Auth\User
{
    $role = \Statamic\Facades\Role::make('role-' . uniqid())->permissions($permissions);
    $role->save();
    $user = \Statamic\Facades\User::make()->email(uniqid() . '@example.com');
    $user->assignRole($role);
    $user->save();

    return $user;
}

it('turns away a user without the settings permission', function () {
    $url = Addon::get('alt-design/alt-google-maps')->settingsUrl();

    $this->actingAs(settingsUserWith(['access cp']))->get($url)->assertRedirect();
});

it('lets a user with the settings permission open the settings page', function () {
    $url = Addon::get('alt-design/alt-google-maps')->settingsUrl();

    $this->actingAs(settingsUserWith(['access cp', 'edit alt-design/alt-google-maps settings']))->get($url)->assertOk();
});

it('refuses a settings save from a user without the settings permission', function () {
    $url = cp_route('addons.settings.update', 'alt-google-maps');

    $this->actingAs(settingsUserWith(['access cp']))->patchJson($url, ['map_provider' => 'osm'])->assertForbidden();
    expect(File::exists($this->settingsPath))->toBeFalse();
});

it('saves settings through the native addon settings endpoint', function () {
    $url = cp_route('addons.settings.update', 'alt-google-maps');
    $user = settingsUserWith(['access cp', 'edit alt-design/alt-google-maps settings']);

    $this->actingAs($user)->patchJson($url, ['map_provider' => 'osm', 'zoom' => 7])->assertOk();

    expect(File::get($this->settingsPath))->toContain('map_provider: osm');
});

it('drops the marker icon picker when the site has no asset container', function () {
    $blueprint = Addon::get('alt-design/alt-google-maps')->settingsBlueprint();

    expect($blueprint->hasField('marker_icon_asset'))->toBeFalse();
});

it('names an asset container on the marker icon picker when the site has several', function () {
    foreach (['first', 'second'] as $handle) {
        \Statamic\Facades\AssetContainer::make($handle)->disk('local')->save();
    }

    $contents = SettingsBlueprint::contents(__DIR__ . '/../resources/blueprints/settings.yaml');
    $field = collect($contents['tabs']['main']['sections'])
        ->flatMap(fn ($section) => $section['fields'])
        ->firstWhere('handle', 'marker_icon_asset');

    \Statamic\Facades\AssetContainer::all()->each->delete();

    expect($field['field']['container'])->toBe('first');
});
