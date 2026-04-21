# Alt Google Maps Addon for Statamic

A location picker fieldtype for [Statamic CMS](https://statamic.com) with support for both Google Maps and OpenStreetMap. Adds a `google_maps` fieldtype with an interactive map, address search, click-to-place, and drag-to-move — plus a CP settings page to configure defaults.

## Features

- **`google_maps` fieldtype** — interactive map with search bar, click-to-place pin, drag-to-move marker, and editable coordinate inputs (Lat/Lng/Zoom); shows address or coordinates in the CP header
- **Dual map provider** — choose **Google Maps** (requires API key) or **OpenStreetMap / Leaflet** (no API key needed)
- **CP Settings page** — configure default coordinates and map provider via Tools → Google Maps
- **Augmented data** — automatically generates `google_maps_url`, `directions_url`, and `static_map_url` for use in Antlers templates
- **`.env` configuration** — API key, map provider, and defaults via environment variables

## Requirements

- PHP 8.1+
- Statamic 5 or 6
- **Google Maps** provider: a Google Maps API key with these APIs enabled:
  - **Maps JavaScript API** — renders the interactive map in the CP and on the frontend
  - **Geocoding API** — address search and reverse geocoding (click-to-place, drag-to-move)
- **OpenStreetMap** provider: no API key required (uses Nominatim for geocoding)

## Installation

Run the following command in the **root of your Statamic project**:

```bash
composer require alt-design/alt-google-maps-addon
```

Then publish the config file:

```bash
php artisan vendor:publish --tag=statamic-google-maps-config
```

## Configuration

Add to your `.env`:

```env
# Required for Google Maps provider only
GOOGLE_MAPS_API_KEY=your_api_key_here

# Map provider: 'google' (default) or 'osm' (no API key needed)
GOOGLE_MAPS_PROVIDER=google

# Optional: default coordinates for new fields (leave blank for no default)
GOOGLE_MAPS_DEFAULT_LAT=51.5074
GOOGLE_MAPS_DEFAULT_LNG=-0.1278
GOOGLE_MAPS_DEFAULT_ZOOM=13
```

You can also configure these settings via the CP under **Tools → Google Maps**. Values saved there take priority over `.env` — to reset, delete `storage/app/statamic/google-maps-settings.json`.

## Map Provider

### Google Maps (`GOOGLE_MAPS_PROVIDER=google`)

Requires a Google Maps API key with the Maps JavaScript API and Geocoding API enabled. Renders a full Google Map in the CP fieldtype and on the frontend.

### OpenStreetMap (`GOOGLE_MAPS_PROVIDER=osm`)

No API key required. Uses [Leaflet](https://leafletjs.com) with OpenStreetMap tiles for the map, and [Nominatim](https://nominatim.org) for address search and reverse geocoding. Leaflet (~44 kB gzipped) is loaded on demand — only when the OSM provider is active.

> **Note:** Nominatim calls are always proxied server-side (never from the browser) to comply with Nominatim's terms of service.

Both providers support the same fieldtype interactions:
- **Address search** — type a query and click Search (or press Enter) to pan the map and fill all fields
- **Click-to-place** — click anywhere on the map to drop a pin; coordinates and address fill automatically
- **Drag-to-move** — drag the marker to adjust position; address updates on drop
- **Manual input** — edit Lat/Lng/Zoom directly; the map pin moves to match
- **Read-only mode** — map is visible but interaction is disabled

## Usage

### In a Blueprint or Fieldset

```yaml
-
  handle: location
  field:
    type: google_maps
    display: Location
```

### In Antlers Templates (standalone field)

The frontend map respects the active `map_provider`. Use `{{ config:statamic-google-maps:map_provider }}` to conditionally render Leaflet (OSM) or Google Maps (Google):

```antlers
{{ if location }}
    {{ location }}
        <div id="map-{{ lat }}-{{ lng }}" style="height: 420px; width: 100%;"></div>
        {{ if address }}<p>{{ address }}</p>{{ /if }}

        {{ if config:statamic-google-maps:map_provider == 'osm' }}
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1/dist/leaflet.css" crossorigin="">
            <script>
                (function() {
                    var mapEl = document.getElementById('map-{{ lat }}-{{ lng }}');
                    var lat = {{ lat }}, lng = {{ lng }}, zoom = {{ zoom }};
                    function initLeaflet(L) {
                        var map = L.map(mapEl, { center: [lat, lng], zoom: zoom });
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                        }).addTo(map);
                        L.marker([lat, lng]).addTo(map);
                    }
                    if (window.L) { initLeaflet(window.L); } else {
                        var s = document.createElement('script');
                        s.src = 'https://unpkg.com/leaflet@1/dist/leaflet.js';
                        s.crossOrigin = '';
                        s.addEventListener('load', function() { initLeaflet(window.L); });
                        document.head.appendChild(s);
                    }
                })();
            </script>
        {{ else }}
            <script>
                (function() {
                    var mapEl = document.getElementById('map-{{ lat }}-{{ lng }}');
                    var lat = {{ lat }}, lng = {{ lng }}, zoom = {{ zoom }};
                    function initMap() {
                        var map = new google.maps.Map(mapEl, {
                            center: { lat: lat, lng: lng }, zoom: zoom,
                            mapTypeId: 'roadmap', streetViewControl: false,
                        });
                        new google.maps.Marker({ map: map, position: { lat: lat, lng: lng } });
                    }
                    if (window.google && window.google.maps) { initMap(); }
                    else if (!window._gmapsFrontendLoading) {
                        window._gmapsFrontendCallbacks = window._gmapsFrontendCallbacks || [];
                        window._gmapsFrontendCallbacks.push(initMap);
                        window._gmapsFrontendLoading = true;
                        window._gmapsFrontendReady = function() {
                            (window._gmapsFrontendCallbacks || []).forEach(function(fn) { fn(); });
                        };
                        var s = document.createElement('script');
                        s.src = 'https://maps.googleapis.com/maps/api/js?key={{ config:statamic-google-maps:api_key }}&callback=_gmapsFrontendReady';
                        s.async = true;
                        document.head.appendChild(s);
                    } else {
                        window._gmapsFrontendCallbacks = window._gmapsFrontendCallbacks || [];
                        window._gmapsFrontendCallbacks.push(initMap);
                    }
                })();
            </script>
        {{ /if }}
    {{ /location }}
{{ /if }}
```

### In Replicator or Bard Sets

When the `google_maps` field is inside a Replicator or Bard set, use **colon notation** to access its properties — the tag pair scope does not work in this context. Use `{{ id }}` (the set's unique ID) as the map element's `id` to avoid conflicts when multiple maps appear on the same page.

```antlers
{{ if google_maps_field:lat }}
    <div id="gmap-{{ id }}" style="height: 420px; width: 100%;"></div>
    {{ if google_maps_field:address }}<p>{{ google_maps_field:address }}</p>{{ /if }}

    {{ if config:statamic-google-maps:map_provider == 'osm' }}
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1/dist/leaflet.css" crossorigin="">
        <script>
            (function() {
                var mapEl = document.getElementById('gmap-{{ id }}');
                var lat = {{ google_maps_field:lat }}, lng = {{ google_maps_field:lng }}, zoom = {{ google_maps_field:zoom }};
                function initLeaflet(L) {
                    var map = L.map(mapEl, { center: [lat, lng], zoom: zoom });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    }).addTo(map);
                    L.marker([lat, lng]).addTo(map);
                }
                if (window.L) { initLeaflet(window.L); } else {
                    var s = document.createElement('script');
                    s.src = 'https://unpkg.com/leaflet@1/dist/leaflet.js';
                    s.crossOrigin = '';
                    s.addEventListener('load', function() { initLeaflet(window.L); });
                    document.head.appendChild(s);
                }
            })();
        </script>
    {{ else }}
        <script>
            (function() {
                var mapEl = document.getElementById('gmap-{{ id }}');
                var lat = {{ google_maps_field:lat }}, lng = {{ google_maps_field:lng }}, zoom = {{ google_maps_field:zoom }};
                function initMap() {
                    var map = new google.maps.Map(mapEl, {
                        center: { lat: lat, lng: lng }, zoom: zoom,
                        mapTypeId: 'roadmap', streetViewControl: false,
                    });
                    new google.maps.Marker({ map: map, position: { lat: lat, lng: lng } });
                }
                if (window.google && window.google.maps) { initMap(); }
                else if (!window._gmapsFrontendLoading) {
                    window._gmapsFrontendCallbacks = window._gmapsFrontendCallbacks || [];
                    window._gmapsFrontendCallbacks.push(initMap);
                    window._gmapsFrontendLoading = true;
                    window._gmapsFrontendReady = function() {
                        (window._gmapsFrontendCallbacks || []).forEach(function(fn) { fn(); });
                    };
                    var s = document.createElement('script');
                    s.src = 'https://maps.googleapis.com/maps/api/js?key={{ config:statamic-google-maps:api_key }}&callback=_gmapsFrontendReady';
                    s.async = true;
                    document.head.appendChild(s);
                } else {
                    window._gmapsFrontendCallbacks = window._gmapsFrontendCallbacks || [];
                    window._gmapsFrontendCallbacks.push(initMap);
                }
            })();
        </script>
    {{ /if }}
{{ /if }}
```

### Available variables

| Variable | Type | Description |
|---|---|---|
| `lat` | float | Latitude |
| `lng` | float | Longitude |
| `zoom` | int | Zoom level (1–21) |
| `address` | string | Address string (if set via search, click, or drag) |
| `google_maps_url` | string | Link to Google Maps at this location |
| `directions_url` | string | Link to Google Maps directions to this location |
| `static_map_url` | string | Static map image URL (Google provider only; requires API key) |

## License

MIT — see [LICENSE](LICENSE).
