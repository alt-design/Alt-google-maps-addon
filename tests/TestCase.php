<?php

namespace AltDesign\AltGoogleMapsAddon\Tests;

use AltDesign\AltGoogleMapsAddon\ServiceProvider;
use Illuminate\Support\Facades\File;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    private ?string $scratchDirectory = null;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $this->scratchDirectory = sys_get_temp_dir() . '/alt-google-maps-tests-' . uniqid();

        $app['config']->set('statamic.editions.pro', true);
        $app['config']->set('statamic.stache.stores.users.directory', $this->scratchDirectory . '/users');
        $app['config']->set('statamic.stache.stores.asset-containers.directory', $this->scratchDirectory . '/assets');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->scratchDirectory) {
            File::deleteDirectory($this->scratchDirectory);
        }
    }
}
