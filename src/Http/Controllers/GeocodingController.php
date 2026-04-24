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
        $status = $data['status'] ?? 'UNKNOWN';

        if ($status !== 'OK' || empty($data['results'])) {
            $message = match ($status) {
                'ZERO_RESULTS'                   => 'No results found.',
                'REQUEST_DENIED'                 => 'Google Geocoding API request denied. Ensure the Geocoding API is enabled in Google Cloud Console and the API key is not restricted to browser requests only.',
                'OVER_QUERY_LIMIT', 'OVER_DAILY_LIMIT' => 'Google Geocoding quota exceeded. Try again later, or switch to OpenStreetMap in Tools → Google Maps.',
                'INVALID_REQUEST'                => 'Invalid address.',
                default                          => "Geocoding failed ({$status}). Check your Google API key configuration.",
            };
            return response()->json(['success' => false, 'message' => $message]);
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

        if ($response->status() === 429) {
            return ['success' => false, 'message' => 'Too many requests to the geocoding service. Please wait a moment and try again.'];
        }

        if (! $response->successful()) {
            return ['success' => false, 'message' => "Geocoding service error (HTTP {$response->status()}). Please try again."];
        }

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

        if (! $response->successful()) {
            return ['success' => false, 'message' => 'Reverse geocoding failed.'];
        }

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
