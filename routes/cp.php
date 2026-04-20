<?php

use Illuminate\Support\Facades\Route;
use AltDesign\AltGoogleMapsAddon\Http\Controllers\GeocodingController;
use AltDesign\AltGoogleMapsAddon\Http\Controllers\SettingsController;

Route::post('google-maps/geocode', [GeocodingController::class, 'geocode']);
Route::post('google-maps/reverse-geocode', [GeocodingController::class, 'reverseGeocode']);
Route::get('google-maps/settings', [SettingsController::class, 'index'])->name('google-maps.settings');
Route::post('google-maps/settings', [SettingsController::class, 'store'])->name('google-maps.settings.store');
