<?php

namespace AltDesign\AltGoogleMapsAddon\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class GeocodingController extends Controller
{
    private string $apiKey;
    private string $baseUrl = 'https://maps.googleapis.com/maps/api/geocode/json';

    public function __construct()
    {
        $this->apiKey = config('statamic-google-maps.api_key', '');
    }

    public function geocode(Request $request)
    {
        $request->validate(['address' => 'required|string']);

        $response = Http::get($this->baseUrl, [
            'address' => $request->input('address'),
            'key'     => $this->apiKey,
        ]);

        $data = $response->json();

        if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
            return response()->json(['success' => false, 'message' => 'No results found.']);
        }

        $result = $data['results'][0];

        return response()->json([
            'success' => true,
            'result'  => [
                'lat'     => $result['geometry']['location']['lat'],
                'lng'     => $result['geometry']['location']['lng'],
                'address' => $result['formatted_address'],
            ],
        ]);
    }

    public function reverseGeocode(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $response = Http::get($this->baseUrl, [
            'latlng' => $request->input('lat') . ',' . $request->input('lng'),
            'key'    => $this->apiKey,
        ]);

        $data = $response->json();

        if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
            return response()->json(['success' => false, 'message' => 'No address found.']);
        }

        return response()->json([
            'success' => true,
            'result'  => [
                'address' => $data['results'][0]['formatted_address'],
            ],
        ]);
    }
}
