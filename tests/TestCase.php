<?php

namespace AltDesign\AltGoogleMapsAddon\Tests;

use AltDesign\AltGoogleMapsAddon\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;
}
