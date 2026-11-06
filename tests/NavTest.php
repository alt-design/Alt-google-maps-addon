<?php

use AltDesign\AltGoogleMapsAddon\ServiceProvider;
use AltDesign\AltGoogleMapsAddon\Tests\TestCase;
use Illuminate\Support\Collection;
use Statamic\CP\Navigation\Nav as Navigation;
use Statamic\CP\Navigation\NavItem;
use Statamic\Facades\Addon;
use Statamic\Facades\CP\Nav;
use Statamic\Facades\Role;
use Statamic\Facades\User;

uses(TestCase::class);

/**
 * Build the control panel nav with this addon's item registered.
 *
 * AddonTestCase swaps the nav for a mock after the addon has booted, so a real
 * nav is put back and the addon's own nav registration is run against it.
 */
function navSections(): Collection
{
    Nav::swap(new Navigation);

    (fn () => $this->addToNav())->call(app()->getProvider(ServiceProvider::class));

    return Nav::build(false);
}

/**
 * Find a top-level item in the Alt Tools section.
 */
function altToolsGroup(string $name): ?NavItem
{
    $section = navSections()->firstWhere('display', 'Alt Tools');

    return collect($section['items'] ?? [])->first(fn (NavItem $item) => $item->display() === $name);
}

afterEach(function () {
    Role::find('map-editor')?->delete();
});

it('lists Alt Google Maps in the Editing group under Alt Tools', function () {
    $this->actingAs(User::make()->id('nav-test')->makeSuper());

    $group = altToolsGroup('Editing');

    expect($group)->not->toBeNull()
        ->and($group->children()->map->display()->all())->toBe(['Alt Google Maps']);
});

it('links to the addon settings page', function () {
    $this->actingAs(User::make()->id('nav-test')->makeSuper());

    $settingsUrl = Addon::get('alt-design/alt-google-maps')->settingsUrl();

    expect($settingsUrl)->not->toBeNull()
        ->and(altToolsGroup('Editing')->children()->first()->url())->toBe($settingsUrl)
        ->and(altToolsGroup('Editing')->url())->toBe($settingsUrl);
});

it('uses icons that exist in the running Statamic version', function () {
    $this->actingAs(User::make()->id('nav-test')->makeSuper());

    $group = altToolsGroup('Editing');

    collect([$group, ...$group->children()])->each(
        fn (NavItem $item) => expect($item->svg())->toContain('<svg')
    );
});

it('shows the page to a user allowed to edit the addon settings', function () {
    Role::make('map-editor')->addPermission('access cp')->addPermission('edit alt-design/alt-google-maps settings')->save();

    $this->actingAs(User::make()->id('nav-editor')->assignRole('map-editor'));

    expect(altToolsGroup('Editing'))->not->toBeNull();
});

it('shows no Alt Tools section to a user without the permission', function () {
    $this->actingAs(User::make()->id('nav-editor'));

    expect(navSections()->firstWhere('display', 'Alt Tools'))->toBeNull();
});
