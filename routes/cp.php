<?php

use AltDesign\AltGoogleMapsAddon\Http\Controllers\GeocodingController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:use alt-google-maps geocoding')->group(function () {
    Route::post('google-maps/geocode', [GeocodingController::class, 'geocode']);
    Route::post('google-maps/reverse-geocode', [GeocodingController::class, 'reverseGeocode']);
});
