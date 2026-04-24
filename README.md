# Alt Google Maps Addon for Statamic

A location picker fieldtype for [Statamic CMS](https://statamic.com) with support for both Google Maps and OpenStreetMap. Adds a `google_maps` fieldtype with an interactive map, address search, click-to-place, and drag-to-move, plus a CP settings page to configure defaults.

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

Optionally publish the views if you need to customise the CP settings page:

```bash
php artisan vendor:publish --tag=statamic-google-maps-views
```

## Configuration

Add to your `.env`:

```env
# Required for Google Maps provider only
GOOGLE_MAPS_API_KEY=your_api_key_here

# Map provider: 'google' (requires API key) or 'osm' (OpenStreetMap, no key needed)
GOOGLE_MAPS_PROVIDER=google

# Optional: default coordinates for new fields (leave blank for no default)
GOOGLE_MAPS_DEFAULT_LAT=51.5074
GOOGLE_MAPS_DEFAULT_LNG=-0.1278
GOOGLE_MAPS_DEFAULT_ZOOM=13
```

You can also configure these settings via the CP under **Tools → Google Maps**. Values saved there take priority over `.env` — to reset, delete `storage/app/statamic/google-maps-settings.json`.

### Config file

After publishing, `config/statamic-google-maps.php` exposes additional options:

| Key             | Default     | Description                                                           |
| --------------- | ----------- | --------------------------------------------------------------------- |
| `api_key`       | `''`        | Google Maps API key (reads `GOOGLE_MAPS_API_KEY`)                     |
| `map_provider`  | `'google'`  | `'google'` or `'osm'` (reads `GOOGLE_MAPS_PROVIDER`)                  |
| `defaults.lat`  | `null`      | Default latitude                                                      |
| `defaults.lng`  | `null`      | Default longitude                                                     |
| `defaults.zoom` | `13`        | Default zoom level                                                    |
| `map_height`    | `420`       | Map height in pixels (CP fieldtype default)                           |
| `map_type`      | `'roadmap'` | Google Maps type: `'roadmap'`, `'satellite'`, `'terrain'`, `'hybrid'` |

## Override Hierarchy

Settings are resolved from **highest to lowest priority** — a higher source always wins.

### Map provider

| Priority    | Source                                   | How to set                                          |
| ----------- | ---------------------------------------- | --------------------------------------------------- |
| 1 — highest | Per-field config in blueprint / fieldset | `map_provider: osm` on the field                    |
| 2           | CP Settings page                         | **Tools → Google Maps** → save                      |
| 3           | `.env`                                   | `GOOGLE_MAPS_PROVIDER=google`                       |
| 4 — lowest  | Config file                              | `map_provider` in `config/statamic-google-maps.php` |

The CP Settings page writes to `storage/app/statamic/google-maps-settings.json`. This file overrides the runtime config at boot, so anything reading `config('statamic-google-maps.map_provider')` sees the CP value, not `.env`. To revert to `.env`, delete that JSON file.

The resolved provider is also exposed as an **augmented variable** (`map_provider`) on each field value — your Antlers templates can read it directly without touching global config (see [Available variables](#available-variables)).

### Default coordinates and zoom

| Priority    | Source                                   | How to set                                                                       |
| ----------- | ---------------------------------------- | -------------------------------------------------------------------------------- |
| 1 — highest | Per-field config in blueprint / fieldset | `default_lat`, `default_lng`, `default_zoom` on the field                        |
| 2           | CP Settings page                         | **Tools → Google Maps** → Default Location                                       |
| 3           | `.env`                                   | `GOOGLE_MAPS_DEFAULT_LAT`, `GOOGLE_MAPS_DEFAULT_LNG`, `GOOGLE_MAPS_DEFAULT_ZOOM` |
| 4 — lowest  | Config file                              | `defaults.lat`, `defaults.lng`, `defaults.zoom`                                  |

Leaving a per-field value **blank** means "inherit from the next level down". For example, an empty `default_lat` on the field falls through to the CP / `.env` site default. Each value is independent — you can override only the zoom and let lat/lng come from the site default.

> **Tip:** Per-field defaults only apply to **new (empty) entries**. Once a location is saved, those stored coordinates are used instead.

## Map Provider

### Google Maps (`GOOGLE_MAPS_PROVIDER=google`)

Requires a Google Maps API key with the **Maps JavaScript API** and **Geocoding API** enabled. Renders a full Google Map in the CP fieldtype and on the frontend.

> **Note:** Address search, click-to-place, and drag-to-move geocoding are all performed **client-side** via `google.maps.Geocoder`. This means **browser-restricted API keys work correctly** — no additional server-side configuration is needed.

### OpenStreetMap (`GOOGLE_MAPS_PROVIDER=osm`)

No API key required. Uses [Leaflet](https://leafletjs.com) to render the map in the browser (canvas, markers, zoom, pan), with [OpenStreetMap](https://www.openstreetmap.org) as the tile provider (the map imagery). Uses [Nominatim](https://nominatim.org) for address search and reverse geocoding. Leaflet (~44 kB gzipped) is loaded on demand — only when the OSM provider is active.

> **Note:** OpenStreetMap provides the map imagery (tiles) via `https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png`. Leaflet is the JavaScript library that handles rendering, events, markers, and zoom in the browser. The two are separate — you could swap the tile provider without changing Leaflet.

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
- handle: location
  field:
      type: google_maps
      display: Location
```

All field-level config options are optional and override the global defaults for that specific field:

| Option          | Type    | Default          | Description                                                              |
| --------------- | ------- | ---------------- | ------------------------------------------------------------------------ |
| `map_provider`  | select  | _(site default)_ | `'google'` or `'osm'` — overrides the global setting for this field only |
| `default_lat`   | float   | _(site default)_ | Starting latitude for new entries — blank to inherit from site default   |
| `default_lng`   | float   | _(site default)_ | Starting longitude for new entries — blank to inherit from site default  |
| `default_zoom`  | integer | _(site default)_ | Initial zoom level (1 = world, 20 = building) — blank = site default     |
| `map_height`    | integer | `420`            | Map height in pixels                                                     |
| `enable_search` | toggle  | `true`           | Show/hide the address search bar                                         |
| `enable_click`  | toggle  | `true`           | Enable click-to-place on the map                                         |

Example with custom options:

```yaml
- handle: location
  field:
      type: google_maps
      display: Location
      map_provider: osm
      default_lat: -23.5505
      default_lng: -46.6333
      default_zoom: 12
      map_height: 300
      enable_search: true
      enable_click: true
```

### In Antlers Templates (standalone field)

The frontend map respects the active `map_provider`. Inside a `{{ location }}` tag pair, `map_provider` is an **augmented variable** that already reflects any per-field override — use it directly rather than reading the global config:

```antlers
{{ if location }}
    {{ location }}
        <div id="map-{{ lat }}-{{ lng }}" style="height: 420px; width: 100%;"></div>

        {{ if map_provider == 'osm' }}
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

`map_provider` is also available via colon notation as `google_maps_field:map_provider` — it already reflects any per-field override, so prefer it over the global config tag.

> **Note:** Antlers does not support ternary expressions (`? :`) with colon-notation variable names. Use `{{ if }}...{{ else }}...{{ /if }}` instead when branching on `google_maps_field:lat` inside a Replicator set.

```antlers
{{ if google_maps_field:lat }}
    <div id="gmap-{{ id }}" style="height: 420px; width: 100%;"></div>

    {{ if google_maps_field:map_provider == 'osm' }}
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

### In a Page Builder `form_row` Set (map alongside a form)

If your page builder has a `form_row` Replicator set, you can add a `google_maps_field` directly to that set. The template will then render the map and the form side by side in a two-column grid — the map on the left, the form on the right. When no location is set, the layout collapses to a single-column form with a narrower `max-width`.

Add the field to the set in your fieldset YAML:

```yaml
form_row:
    display: "Form Row"
    fields:
        - import: heading
          prefix: form_
        - handle: form
          field:
              type: form
              max_items: 1
              display: Form
        - handle: google_maps_field
          field:
              type: google_maps
              display: "Map (optional)"
              instructions: "Show a map alongside the form. Leave empty to hide."
        - import: button
```

> **Note:** Antlers does not support ternary expressions (`? :`) with colon-notation variable names. Use `{{ if }}...{{ else }}...{{ /if }}` instead when branching on `google_maps_field:lat` inside a Replicator set.

### Available variables

| Variable          | Type   | Description                                                                        |
| ----------------- | ------ | ---------------------------------------------------------------------------------- |
| `lat`             | float  | Latitude                                                                           |
| `lng`             | float  | Longitude                                                                          |
| `zoom`            | int    | Zoom level (1–21)                                                                  |
| `address`         | string | Address string (if set via search, click, or drag)                                 |
| `map_provider`    | string | Resolved provider for this field: `'google'` or `'osm'` (per-field → site default) |
| `map_height`      | int    | Resolved map height in pixels (per-field → site default)                           |
| `google_maps_url` | string | Link to Google Maps at this location                                               |
| `directions_url`  | string | Link to Google Maps directions to this location                                    |
| `static_map_url`  | string | Static map image URL (Google provider only; requires API key)                      |

## License

MIT — see [LICENSE](LICENSE).
