<?php

namespace AltDesign\AltGoogleMapsAddon\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SettingsController extends Controller
{
    private string $storageFile;

    public function __construct()
    {
        $this->storageFile = storage_path('app/statamic/google-maps-settings.json');
    }

    public function index()
    {
        return view('alt-google-maps-addon::settings', [
            'defaults' => $this->getDefaults(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'lat'  => 'required|numeric|between:-90,90',
            'lng'  => 'required|numeric|between:-180,180',
            'zoom' => 'required|integer|between:1,21',
        ]);

        $dir = dirname($this->storageFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->storageFile, json_encode($validated));

        return response()->json(['success' => true]);
    }

    private function getDefaults(): array
    {
        if (file_exists($this->storageFile)) {
            $data = json_decode(file_get_contents($this->storageFile), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'lat'  => config('statamic-google-maps.defaults.lat'),
            'lng'  => config('statamic-google-maps.defaults.lng'),
            'zoom' => config('statamic-google-maps.defaults.zoom', 13),
        ];
    }
}
