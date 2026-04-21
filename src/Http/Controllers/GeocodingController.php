<?php

namespace AltDesign\AltGoogleMapsAddon\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

class GeocodingController extends Controller
{
    private string $apiKey;
    private string $googleBaseUrl = 'https://maps.googleapis.com/maps/api/geocode/json';
    // Nominatim ToS: max 1 req/sec, must identify app via User-Agent, no bulk/automated requests.
    private string $nominatimBaseUrl = 'https://nominatim.openstreetmap.org';
    private string $userAgent = 'AltGoogleMapsAddon/1.0 (Statamic addon; alt-design/alt-google-maps-addon)';

    public function __construct()
    {
        $this->apiKey = config('statamic-google-maps.api_key', '');
    }

    public function geocode(Request $request)
    {
        $request->validate(['address' => 'required|string']);

        if ($request->input('provider') === 'osm') {
            return response()->json($this->geocodeOsm($request->input('address')));
        }

        $response = Http::get($this->googleBaseUrl, [
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

        if ($request->input('provider') === 'osm') {
            return response()->json($this->reverseGeocodeOsm(
                (float) $request->input('lat'),
                (float) $request->input('lng')
            ));
        }

        $response = Http::get($this->googleBaseUrl, [
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

    private function geocodeOsm(string $address): array
    {
        $response = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->get("{$this->nominatimBaseUrl}/search", [
                'q'              => $address,
                'format'         => 'json',
                'limit'          => 1,
                'addressdetails' => 0,
            ]);

        $data = $response->json();

        if (empty($data)) {
            return ['success' => false, 'message' => 'No results found.'];
        }

        $result = $data[0];

        return [
            'success' => true,
            'result'  => [
                'lat'     => (float) $result['lat'],
                'lng'     => (float) $result['lon'],
                'address' => $result['display_name'],
            ],
        ];
    }

    private function reverseGeocodeOsm(float $lat, float $lng): array
    {
        $response = Http::withHeaders(['User-Agent' => $this->userAgent])
            ->get("{$this->nominatimBaseUrl}/reverse", [
                'lat'    => $lat,
                'lon'    => $lng,
                'format' => 'json',
            ]);

        $data = $response->json();

        if (empty($data) || isset($data['error'])) {
            return ['success' => false, 'message' => 'No address found.'];
        }

        return [
            'success' => true,
            'result'  => [
                'address' => $data['display_name'],
            ],
        ];
    }
}
