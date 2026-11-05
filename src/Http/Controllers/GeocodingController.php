<?php

namespace AltDesign\AltGoogleMapsAddon\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

class GeocodingController extends Controller
{
    private string $apiKey;
    private string $googleBaseUrl = 'https://maps.googleapis.com/maps/api/geocode/json';
    // Nominatim ToS: max 1 req/sec, must identify app via User-Agent, no bulk/automated requests.
    // We enforce ToS server-side via Cache (24h TTL) + RateLimiter (1 req/s) below.
    private string $nominatimBaseUrl = 'https://nominatim.openstreetmap.org';
    private string $userAgent = 'AltGoogleMapsAddon/1.0 (Statamic addon; alt-design/alt-google-maps-addon)';
    private const HTTP_TIMEOUT = 10;
    private const NOMINATIM_CACHE_TTL = 86400;
    private const NOMINATIM_RATE_KEY = 'alt-gmaps:nominatim';

    public function __construct()
    {
        $this->apiKey = config('alt-google-maps.api_key', '');
    }

    public function geocode(Request $request)
    {
        $request->validate([
            'address'  => 'required|string|max:255',
            'provider' => 'nullable|in:google,osm',
        ]);

        if ($request->input('provider') === 'osm') {
            return response()->json($this->geocodeOsm($request->input('address')));
        }

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->retry(2, 100, throw: false)
                ->get($this->googleBaseUrl, [
                    'address' => $request->input('address'),
                    'key'     => $this->apiKey,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Google Geocoding HTTP failure', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Geocoding service unreachable. Please try again.']);
        }

        $data = $response->json();
        $status = $data['status'] ?? 'UNKNOWN';

        if ($status !== 'OK' || empty($data['results'])) {
            if (! in_array($status, ['ZERO_RESULTS', 'OK'], true)) {
                Log::warning('Google Geocoding non-OK status', ['status' => $status]);
            }
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
            'lat'      => 'required|numeric|between:-90,90',
            'lng'      => 'required|numeric|between:-180,180',
            'provider' => 'nullable|in:google,osm',
        ]);

        if ($request->input('provider') === 'osm') {
            return response()->json($this->reverseGeocodeOsm(
                (float) $request->input('lat'),
                (float) $request->input('lng')
            ));
        }

        try {
            $response = Http::timeout(self::HTTP_TIMEOUT)
                ->retry(2, 100, throw: false)
                ->get($this->googleBaseUrl, [
                    'latlng' => $request->input('lat') . ',' . $request->input('lng'),
                    'key'    => $this->apiKey,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Google reverse-geocoding HTTP failure', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Reverse geocoding service unreachable.']);
        }

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
        $cacheKey = 'alt-gmaps:nominatim:search:' . sha1(strtolower(trim($address)));

        return Cache::remember($cacheKey, self::NOMINATIM_CACHE_TTL, function () use ($address) {
            return $this->throttledNominatim(fn () => $this->doNominatimSearch($address));
        });
    }

    private function reverseGeocodeOsm(float $lat, float $lng): array
    {
        $cacheKey = 'alt-gmaps:nominatim:reverse:' . sha1(round($lat, 6) . ',' . round($lng, 6));

        return Cache::remember($cacheKey, self::NOMINATIM_CACHE_TTL, function () use ($lat, $lng) {
            return $this->throttledNominatim(fn () => $this->doNominatimReverse($lat, $lng));
        });
    }

    /**
     * Enforce Nominatim's 1-req/sec ToS via Laravel's RateLimiter.
     *
     * Why: Cache::remember above prevents repeats, but distinct queries from many CP
     * users could still burst beyond the allowed rate. Without this throttle a busy
     * site risks getting the addon's User-Agent banned.
     */
    private function throttledNominatim(\Closure $request): array
    {
        $result = RateLimiter::attempt(self::NOMINATIM_RATE_KEY, 1, $request, 1);

        if ($result === false) {
            return ['success' => false, 'message' => 'Too many requests to the geocoding service. Please wait a moment and try again.'];
        }

        return $result;
    }

    private function doNominatimSearch(string $address): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->timeout(self::HTTP_TIMEOUT)
                ->get("{$this->nominatimBaseUrl}/search", [
                    'q'              => $address,
                    'format'         => 'json',
                    'limit'          => 1,
                    'addressdetails' => 0,
                ]);
        } catch (\Throwable $e) {
            Log::warning('Nominatim search HTTP failure', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Geocoding service unreachable. Please try again.'];
        }

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

    private function doNominatimReverse(float $lat, float $lng): array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->timeout(self::HTTP_TIMEOUT)
                ->get("{$this->nominatimBaseUrl}/reverse", [
                    'lat'    => $lat,
                    'lon'    => $lng,
                    'format' => 'json',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Nominatim reverse HTTP failure', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Reverse geocoding failed.'];
        }

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
