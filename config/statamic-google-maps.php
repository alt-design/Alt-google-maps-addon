<?php

return [
    'api_key' => env('GOOGLE_MAPS_API_KEY', ''),
    
    'defaults' => [
        'lat'  => env('GOOGLE_MAPS_DEFAULT_LAT') !== null ? (float) env('GOOGLE_MAPS_DEFAULT_LAT') : null,
        'lng'  => env('GOOGLE_MAPS_DEFAULT_LNG') !== null ? (float) env('GOOGLE_MAPS_DEFAULT_LNG') : null,
        'zoom' => (int) env('GOOGLE_MAPS_DEFAULT_ZOOM', 13),
    ],
    
    'map_height' => 420,
    'map_type' => 'roadmap',

    // Map provider: 'google' (requires API key) or 'osm' (OpenStreetMap, no key needed)
    'map_provider' => env('GOOGLE_MAPS_PROVIDER', 'google'),
];