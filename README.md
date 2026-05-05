# Alt Google Maps Addon for Statamic

A location picker fieldtype for [Statamic CMS](https://statamic.com) with support for both Google Maps and OpenStreetMap. Adds a `google_maps` fieldtype with an interactive map, address search, click-to-place, and drag-to-move, plus a CP settings page to configure defaults.

## Features

- **`google_maps` fieldtype** — interactive map with search bar, click-to-place pin, drag-to-move marker, and editable coordinate inputs (Lat/Lng/Zoom); shows address or coordinates in the CP header
- **Multiple markers** — enable `enable_multiple` on a field to add, reorder, and delete multiple pins from a single field; each pin can have its own address, infobox, and custom icon
- **Marker clustering** — enable `enable_clustering` to group nearby markers into interactive clusters on the frontend; powered by [`@googlemaps/markerclusterer`](https://github.com/googlemaps/js-markerclusterer) (Google) and [`leaflet.markercluster`](https://github.com/Leaflet/Leaflet.markercluster) (OSM)
- **Dual map provider** — choose **Google Maps** (requires API key) or **OpenStreetMap / Leaflet** (no API key needed)
- **Infobox / popup** — configurable marker popup (title + HTML body); opens on marker click; powered by [Snazzy Info Window](https://snazzymaps.com/about/info-window) (Google) and Leaflet's native `bindPopup` (OSM)
- **Map styles** — apply custom map styles from [snazzymaps.com](https://snazzymaps.com) (Google Maps only); paste the style JSON in the CP or per-fieldset
- **Custom marker icon** — replace the default pin with any PNG/SVG image; override per-entry (with built-in asset browser picker), per-field, via CP Settings, or via `.env`
- **CP Settings page** — configure default coordinates, map provider, infobox defaults, map style, and marker icon via Tools → Alt Google Maps
- **Augmented data** — automatically generates `google_maps_url`, `directions_url`, `static_map_url`, `markers`, `markers_json`, and `enable_clustering` for use in Antlers templates
- **Adapter pattern** — frontend rendering uses a shared `window.AltMap` adapter; Leaflet, Google Maps SDK, MarkerClusterer, and Snazzy Info Window are all lazy-loaded from CDN only when needed
- **Antlers tags** — `{{ google_map }}`, `{{ google_map:multi }}`, and `{{ google_map:static }}` for rendering maps directly in templates without writing JS
- **`.env` configuration** — API key, map provider, and defaults via environment variables

## Security

### API key visibility in the browser

> **⚠️ The API key is visible in the page source of any site using the Google Maps provider.** This is unavoidable — the Maps JavaScript API requires the key to be embedded in a `<script src=…>` URL loaded by the browser. There is no server-side proxy alternative for the client SDK.

Three things to understand:

1. **The key is intentionally sent to the frontend.** The addon always passes `api_key` from your `.env` to the JavaScript layer — even when the global map provider is set to `osm`. This is necessary because individual fields can override the provider to `google` independently of the site-wide default. Without this, a field set to Google Maps would fail on an OSM-default site.

2. **The risk is quota abuse, not credential theft.** Anyone visiting your published site can read the key from the page source or DevTools. They cannot, however, read your `.env` file, your server credentials, or any other secrets. The threat is someone copying the key and using it in their own application, consuming your Maps API quota (and potentially triggering billing).

3. **The mitigation is key restrictions in Google Cloud Console — not hiding the key.** A properly restricted key is safe to be public. An unrestricted key is a liability even if you try to hide it.

### Restricting your API key (required)

In [Google Cloud Console → Credentials](https://console.cloud.google.com/apis/credentials):

1. **Application restrictions → HTTP referrers** — add your production domain(s):
    ```
    https://example.com/*
    https://*.example.com/*
    ```
    A key restricted to your domain(s) cannot be used by anyone else — requests from other origins are rejected by Google, regardless of whether someone has copied the key.  
    For local development, add `http://localhost:*/*` to a **separate development key** (see point 3).

2. **API restrictions** — restrict the key to **Maps JavaScript API** and **Geocoding API** only. An unrestricted key grants access to every Google Cloud API your project has enabled.

3. **Use separate keys for development and production.** Never commit or share your production key. If a key was ever published unrestricted (e.g. pushed to a public repo), rotate it immediately.

### What is and isn't in your Git history

The addon's source code contains `config('statamic-google-maps.api_key')` — that is what gets committed. The **value** of the key lives only in your `.env` file, which is listed in `.gitignore` and is never committed. Anyone cloning your repository will see the code that reads the key, not the key itself.

### Server-side protections

The addon also enforces the following server-side:

- **Nominatim ToS compliance** — geocoding requests are proxied through the CP (never browser-direct), cached for 24h, and throttled to 1 request/sec across the whole app.
- **HTML sanitization on `infobox_content`** — `<script>`, `<style>`, `<iframe>`, `<object>`, `<embed>`, `on*=` event handlers, and `javascript:` / `data:` URLs are stripped on save.
- **HTTP timeouts and retries** on Google Geocoding (10s timeout, 2 retries) so a slow upstream cannot stall the CP.

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

# Optional: custom marker icon (leave blank to use the provider default pin)
GOOGLE_MAPS_MARKER_ICON=https://example.com/pin.png
GOOGLE_MAPS_MARKER_WIDTH=40
GOOGLE_MAPS_MARKER_HEIGHT=40
```

You can also configure these settings via the CP under **Tools → Alt Google Maps**. Values saved there take priority over `.env` — to reset, delete `storage/app/statamic/google-maps-settings.json`.

### Config file

After publishing, `config/statamic-google-maps.php` exposes additional options:

**General**

| Key            | Default     | Description                                                           |
| -------------- | ----------- | --------------------------------------------------------------------- |
| `api_key`      | `''`        | Google Maps API key (reads `GOOGLE_MAPS_API_KEY`)                     |
| `map_provider` | `'google'`  | `'google'` or `'osm'` (reads `GOOGLE_MAPS_PROVIDER`)                 |
| `map_height`   | `420`       | Default map height in pixels                                          |
| `map_type`     | `'roadmap'` | Google Maps type: `'roadmap'`, `'satellite'`, `'terrain'`, `'hybrid'` |

**Default coordinates**

| Key             | Default | Description                              |
| --------------- | ------- | ---------------------------------------- |
| `defaults.lat`  | `null`  | Default latitude                         |
| `defaults.lng`  | `null`  | Default longitude                        |
| `defaults.zoom` | `13`    | Default zoom level                       |

**Appearance**

| Key           | Default | Description                                              |
| ------------- | ------- | -------------------------------------------------------- |
| `map_style`   | `''`    | Snazzy Maps style JSON for Google Maps                   |
| `infobox_title`   | `''`  | Default popup title (reads `GOOGLE_MAPS_INFOBOX_TITLE`)   |
| `infobox_content` | `''`  | Default popup body HTML (reads `GOOGLE_MAPS_INFOBOX_CONTENT`) |

**Marker icon**

| Key                  | Default | Description                                                           |
| -------------------- | ------- | --------------------------------------------------------------------- |
| `marker_icon_url`    | `''`    | Custom marker image URL (reads `GOOGLE_MAPS_MARKER_ICON`); empty = default pin |
| `marker_icon_width`  | `40`    | Marker icon width in pixels (reads `GOOGLE_MAPS_MARKER_WIDTH`)        |
| `marker_icon_height` | `40`    | Marker icon height in pixels (reads `GOOGLE_MAPS_MARKER_HEIGHT`)      |

## Override Hierarchy

Settings are resolved from **highest to lowest priority** — a higher source always wins.

### Map provider

| Priority    | Source                                   | How to set                                          |
| ----------- | ---------------------------------------- | --------------------------------------------------- |
| 1 — highest | Per-field config in blueprint / fieldset | `map_provider: osm` on the field                    |
| 2           | CP Settings page                         | **Tools → Alt Google Maps** → save                  |
| 3           | `.env`                                   | `GOOGLE_MAPS_PROVIDER=google`                       |
| 4 — lowest  | Config file                              | `map_provider` in `config/statamic-google-maps.php` |

The CP Settings page writes to `storage/app/statamic/google-maps-settings.json`. This file overrides the runtime config at boot, so anything reading `config('statamic-google-maps.map_provider')` sees the CP value, not `.env`. To revert to `.env`, delete that JSON file.

The resolved provider is also exposed as an **augmented variable** (`map_provider`) on each field value — your Antlers templates can read it directly without touching global config (see [Available variables](#available-variables)).

### Default coordinates and zoom

| Priority    | Source                                   | How to set                                                                       |
| ----------- | ---------------------------------------- | -------------------------------------------------------------------------------- |
| 1 — highest | Per-field config in blueprint / fieldset | `default_lat`, `default_lng`, `default_zoom` on the field                        |
| 2           | CP Settings page                         | **Tools → Alt Google Maps** → Default Location                                   |
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

**Multiple markers**

| Option              | Type   | Default | Description                                             |
| ------------------- | ------ | ------- | ------------------------------------------------------- |
| `enable_multiple`   | toggle | `false` | Allow adding multiple markers to this field             |
| `enable_clustering` | toggle | `false` | Group nearby markers into clusters (requires `enable_multiple`) |

**Map**

| Option          | Type    | Default          | Description                                                          |
| --------------- | ------- | ---------------- | -------------------------------------------------------------------- |
| `map_provider`  | select  | _(site default)_ | `'google'` or `'osm'` — overrides the global setting for this field |
| `map_height`    | integer | `420`            | Map height in pixels                                                 |
| `default_lat`   | float   | _(site default)_ | Starting latitude for new entries                                    |
| `default_lng`   | float   | _(site default)_ | Starting longitude for new entries                                   |
| `default_zoom`  | integer | _(site default)_ | Initial zoom level (1 = world, 20 = building)                        |
| `map_style`     | textarea | _(site default)_ | Snazzy Maps style JSON (Google only)                                |

**Infobox**

| Option                    | Type     | Default          | Description                                                    |
| ------------------------- | -------- | ---------------- | -------------------------------------------------------------- |
| `default_infobox_title`   | text     | _(site default)_ | Popup title for all entries using this field                   |
| `default_infobox_content` | textarea | _(site default)_ | Popup body HTML                                                |

**Marker icon**

| Option               | Type    | Default          | Description                                                                 |
| -------------------- | ------- | ---------------- | --------------------------------------------------------------------------- |
| `marker_icon_asset`  | assets  | _(site default)_ | Custom marker image — opens the asset browser / drag & drop picker          |
| `marker_icon_width`  | integer | `40`             | Marker icon width in pixels                                                 |
| `marker_icon_height` | integer | `40`             | Marker icon height in pixels                                                |

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
```

### Frontend Adapter (`window.AltMap`)

The addon ships a lightweight `window.AltMap` adapter partial that handles lazy-loading of Leaflet, Google Maps SDK, MarkerClusterer, and Snazzy Info Window from CDN — only when needed, and only once per page even if multiple maps are present. The recommended pattern for all map templates is:

1. Include the adapter partial once (the guard `if (!window.AltMap)` makes it safe to include multiple times)
2. Add a `<div>` with a unique `id` and `data-style` (HTML-encoded JSON)
3. Add a hidden popup `<div>` for the infobox HTML (optional)
4. Call `AltMap.initLeaflet(...)` or `AltMap.initGoogle(...)` for single-marker, or `AltMap.initLeafletMulti(...)` / `AltMap.initGoogleMulti(...)` for multiple markers

> **Important — `| entities` on `data-style`:** Map style JSON contains double-quotes. When written into an HTML attribute, those quotes must be HTML-encoded (`"` → `&quot;`) using Antlers' `| entities` modifier. Without it the browser truncates the attribute at the first `"` and `JSON.parse` silently receives an empty or malformed string, so the style has no effect.

### Antlers Tags

The addon also provides ready-made Antlers tags that emit the full map HTML and JS without needing the adapter partial directly:

| Tag                       | Description                                                   |
| ------------------------- | ------------------------------------------------------------- |
| `{{ google_map }}`        | Single-marker map from explicit `lat`/`lng` params            |
| `{{ google_map:multi }}`  | Multi-marker map from a `markers_json` string; supports clustering |
| `{{ google_map:static }}` | Returns a static map image URL (Google only)                  |

#### `{{ google_map }}` — single marker

```antlers
{{ google_map
    :lat="location:lat"
    :lng="location:lng"
    :zoom="location:zoom"
    height="400"
}}
```

#### `{{ google_map:static }}` — static image URL

```antlers
<img src="{{ google_map:static :lat='location:lat' :lng='location:lng' :zoom='location:zoom' width='600' height='300' }}" alt="Map">
```

### In Antlers Templates (standalone field)

The frontend map respects the active `map_provider`. Inside a `{{ location }}` tag pair, `map_provider` is an **augmented variable** that already reflects any per-field override — use it directly rather than reading the global config:

```antlers
{{ if location }}
    {{ location }}
        {{ partial src="_parts/map-adapter" }}

        <div
            id="map-{{ lat }}-{{ lng }}"
            data-style="{{ map_style | entities }}"
            style="height: {{ map_height }}px; width: 100%; border-radius: 8px; position: relative; isolation: isolate;"
        ></div>

        {{# Hidden div holds popup HTML — omit if you don't use infoboxes #}}
        <div id="map-{{ lat }}-{{ lng }}-popup" style="display:none">
            {{ if infobox_title }}<p class="alt-map-popup__title">{{ infobox_title }}</p>{{ /if }}
            {{ if infobox_content }}<div class="alt-map-popup__body">{{ infobox_content }}</div>{{ /if }}
        </div>

        {{ if map_provider == 'osm' }}
        <script>
        (function() {
            var el  = document.getElementById('map-{{ lat }}-{{ lng }}');
            var pEl = document.getElementById('map-{{ lat }}-{{ lng }}-popup');
            AltMap.initLeaflet(el, {{ lat }}, {{ lng }}, {{ zoom }}, pEl ? pEl.innerHTML.trim() : '');
        })();
        </script>
        {{ else }}
        <script>
        (function() {
            var el  = document.getElementById('map-{{ lat }}-{{ lng }}');
            var pEl = document.getElementById('map-{{ lat }}-{{ lng }}-popup');
            AltMap.initGoogle(el, {{ lat }}, {{ lng }}, {{ zoom }}, pEl ? pEl.innerHTML.trim() : '', '{{ config:statamic-google-maps:api_key }}', '{{ config:statamic-google-maps:map_type }}');
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
    {{ partial src="_parts/map-adapter" }}

    <div
        id="gmap-{{ id }}"
        data-style="{{ google_maps_field:map_style | entities }}"
        style="height: {{ google_maps_field:map_height ?? 420 }}px; width: 100%; border-radius: 8px; position: relative; isolation: isolate;"
    ></div>

    {{# Hidden div holds popup HTML — omit if you don't use infoboxes #}}
    <div id="gmap-{{ id }}-popup" style="display:none">
        {{ if google_maps_field:infobox_title }}<p class="alt-map-popup__title">{{ google_maps_field:infobox_title }}</p>{{ /if }}
        {{ if google_maps_field:infobox_content }}<div class="alt-map-popup__body">{{ google_maps_field:infobox_content }}</div>{{ /if }}
    </div>

    {{ if google_maps_field:map_provider == 'osm' }}
    <script>
    (function() {
        var el  = document.getElementById('gmap-{{ id }}');
        var pEl = document.getElementById('gmap-{{ id }}-popup');
        AltMap.initLeaflet(el, {{ google_maps_field:lat }}, {{ google_maps_field:lng }}, {{ google_maps_field:zoom }}, pEl ? pEl.innerHTML.trim() : '');
    })();
    </script>
    {{ else }}
    <script>
    (function() {
        var el  = document.getElementById('gmap-{{ id }}');
        var pEl = document.getElementById('gmap-{{ id }}-popup');
        AltMap.initGoogle(el, {{ google_maps_field:lat }}, {{ google_maps_field:lng }}, {{ google_maps_field:zoom }}, pEl ? pEl.innerHTML.trim() : '', '{{ config:statamic-google-maps:api_key }}', '{{ config:statamic-google-maps:map_type ?? "roadmap" }}');
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

#### Single-marker mode

| Variable           | Type        | Description                                                                    |
| ------------------ | ----------- | ------------------------------------------------------------------------------ |
| `lat`              | float       | Latitude                                                                       |
| `lng`              | float       | Longitude                                                                      |
| `zoom`             | int         | Zoom level (1–21)                                                              |
| `address`          | string      | Address string (if set via search, click, or drag)                             |
| `map_provider`     | string      | Resolved provider: `'google'` or `'osm'`                                       |
| `map_height`       | int         | Resolved map height in pixels                                                  |
| `infobox_title`    | string      | Resolved popup title (per-entry → per-field → site default)                    |
| `infobox_content`  | string      | Resolved popup body HTML (per-entry → per-field → site default)                |
| `map_style`        | string      | Resolved Snazzy Maps style JSON (Google only)                                  |
| `marker_icon`      | array\|null | Resolved icon as `{ url, width, height }`, or `null`. Access as `{{ marker_icon:url }}` etc. |
| `marker_icon_json` | string      | `marker_icon` pre-encoded as JSON — use in `data-marker` with `\| entities`    |
| `google_maps_url`  | string      | Link to Google Maps at this location                                           |
| `directions_url`   | string      | Link to Google Maps directions to this location                                |
| `static_map_url`   | string      | Static map image URL (Google only; requires API key)                           |

#### Multiple-markers mode

When `enable_multiple: true`, the field augments to an object with these top-level variables plus a `markers` array. The first marker's values are also surfaced at the top level for backward compatibility.

| Variable            | Type        | Description                                                                       |
| ------------------- | ----------- | --------------------------------------------------------------------------------- |
| `lat`               | float       | Latitude of the first marker                                                      |
| `lng`               | float       | Longitude of the first marker                                                     |
| `address`           | string      | Address of the first marker                                                       |
| `zoom`              | int\|null   | Manual zoom override (`null` = auto-fit all markers)                              |
| `map_provider`      | string      | Resolved provider: `'google'` or `'osm'`                                          |
| `map_height`        | int         | Resolved map height in pixels                                                     |
| `map_style`         | string      | Resolved Snazzy Maps style JSON (Google only)                                     |
| `markers`           | array       | Array of marker objects — each has `lat`, `lng`, `address`, `infobox_title`, `infobox_content`, `marker_icon`, `marker_icon_json` |
| `markers_json`      | string      | `markers` pre-encoded as JSON — use with `AltMap.initGoogleMulti` or `{{ google_map:multi }}` |
| `enable_clustering` | bool        | Whether marker clustering is enabled for this field                               |

## Multiple Markers

Enable `enable_multiple: true` on any field to turn it into a multi-marker map. In this mode the CP shows an "Add Marker" button and a list of pins, each with their own address, infobox, and optional custom icon. A zoom override input is also available to lock the frontend zoom instead of auto-fitting all markers.

### Blueprint config

```yaml
- handle: location
  field:
      type: google_maps
      display: Locations
      enable_multiple: true
      enable_clustering: true   # optional — groups nearby pins on the frontend
```

### Marker Clustering

When `enable_clustering: true` the frontend automatically groups nearby markers into a single circle showing the count. Zooming in splits the cluster into individual pins.

- **Google Maps** — uses [`@googlemaps/markerclusterer`](https://github.com/googlemaps/js-markerclusterer), loaded on demand from `unpkg.com`
- **OSM / Leaflet** — uses [`leaflet.markercluster`](https://github.com/Leaflet/Leaflet.markercluster), loaded on demand from `unpkg.com`

Clustering only activates when two or more markers are close enough at the current zoom level. If all your markers are in different cities, zoom out until they're close enough to trigger a cluster.

### In Antlers templates (multi-marker)

#### Using the `window.AltMap` adapter (Replicator / page-builder set)

```antlers
{{ if google_maps_field:markers_json }}
    {{ partial src="_parts/map-adapter" }}

    <div
        id="gmap-{{ id }}"
        data-style="{{ google_maps_field:map_style | entities }}"
        data-markers="{{ google_maps_field:markers_json | entities }}"
        style="height: {{ google_maps_field:map_height ?? 420 }}px; width: 100%; border-radius: 8px; position: relative; isolation: isolate;"
    ></div>

    {{ if google_maps_field:map_provider == 'osm' }}
    <script>
    (function() {
        var el = document.getElementById('gmap-{{ id }}');
        AltMap.initLeafletMulti(el, el.dataset.markers, {{ google_maps_field:zoom ?? 'null' }}, {{ google_maps_field:enable_clustering ? 'true' : 'false' }});
    })();
    </script>
    {{ else }}
    <script>
    (function() {
        var el = document.getElementById('gmap-{{ id }}');
        AltMap.initGoogleMulti(el, el.dataset.markers, '{{ config:statamic-google-maps:api_key }}', '{{ config:statamic-google-maps:map_type ?? "roadmap" }}', {{ google_maps_field:zoom ?? 'null' }}, {{ google_maps_field:enable_clustering ? 'true' : 'false' }});
    })();
    </script>
    {{ /if }}
{{ /if }}
```

> **Note — `| entities` on `data-markers`:** `markers_json` contains double-quotes. Always use `| entities` when writing it into an HTML attribute.

#### Using the `{{ google_map:multi }}` Antlers tag (standalone)

The addon ships a `{{ google_map:multi }}` tag that renders the full map HTML + JS in one line, with no need to include the adapter partial manually:

```antlers
{{ google_map:multi
    :markers="location:markers_json"
    :clustering="location:enable_clustering"
    :zoom="location:zoom"
    height="400"
}}
```

| Parameter      | Required | Default         | Description                                                              |
| -------------- | -------- | --------------- | ------------------------------------------------------------------------ |
| `markers`      | yes      | —               | JSON string of markers — use `:markers="location:markers_json"`          |
| `clustering`   | no       | `false`         | Enable clustering — use `:clustering="location:enable_clustering"`       |
| `zoom`         | no       | auto-fit        | Fixed zoom level; omit to fit all markers                                |
| `default_zoom` | no       | site default    | Zoom used when there is only one marker                                  |
| `height`       | no       | `420`           | Map height in pixels                                                     |
| `width`        | no       | `100%`          | Map width (CSS value)                                                    |
| `id`           | no       | auto-generated  | HTML `id` attribute for the map container                                |
| `provider`     | no       | site default    | `'google'` or `'osm'`                                                    |
| `key`          | no       | from config     | Google Maps API key (reads config automatically)                         |
| `map_type`     | no       | `'roadmap'`     | `'roadmap'`, `'satellite'`, `'terrain'`, or `'hybrid'`                   |

## Infobox / Popup

When a location has an **Infobox Title** or **Infobox Content** set, clicking the map marker opens a styled popup. The popup has a close button and supports HTML in the body.

**Override hierarchy** (same pattern as lat/lng):

| Priority    | Source                   | How                                                                        |
| ----------- | ------------------------ | -------------------------------------------------------------------------- |
| 1 — highest | Per-entry (stored value) | Editor fills in the fields when saving the entry                           |
| 2           | Per-field config         | `default_infobox_title` / `default_infobox_content` on the blueprint field |
| 3           | CP Settings page         | **Tools → Alt Google Maps** → Infobox section                              |
| 4 — lowest  | Config / `.env`          | `infobox_title` / `infobox_content` in `config/statamic-google-maps.php`   |

Leaving any level blank means "inherit from the next level down". Blank means an **empty string** — the fallback chain treats empty strings the same as null, so setting the CP default and leaving the per-entry field empty will correctly surface the CP default. If both `infobox_title` and `infobox_content` are empty after full resolution, no popup is attached — the marker exists but clicking it does nothing.

**Rendering:**

- **Google Maps** — uses [Snazzy Info Window](https://snazzymaps.com/about/info-window), loaded on demand from CDN
- **OSM / Leaflet** — uses Leaflet's native `bindPopup`, styled with CSS variables

Both providers use the same HTML template:

```html
<p class="alt-map-popup__title">Title here</p>
<div class="alt-map-popup__body">Body HTML here</div>
```

Override the look by setting CSS variables in your stylesheet (see `site.css`):

```css
:root {
    --map-popup-bg: #ffffff;
    --map-popup-radius: 8px;
    --map-popup-title-size: 0.9375rem;
    --map-popup-title-weight: 600;
    --map-popup-title-color: #111827;
    --map-popup-body-size: 0.875rem;
    --map-popup-body-color: #374151;
}
```

### Custom HTML and the close button

The **Infobox Content** field supports arbitrary HTML. You can build any layout you like — headings, links, images, action buttons. The `window.AltMap` adapter passes the content to Snazzy Info Window as a real DOM element (not a raw string), so **direct JavaScript listeners work**.

If your HTML includes a close button, give it the class `popup-close` and the adapter will automatically wire it up so clicking it closes the popup:

```html
<div class="info-popup">
    <div class="popup-header">
        <span class="popup-title">My Location</span>
        <button class="popup-close" aria-label="Close">✕</button>
    </div>
    <div class="popup-body">
        <p>Some description here.</p>
        <a href="/contact">Get in touch</a>
    </div>
</div>
```

> **Note:** This automatic wiring only applies to the **Google Maps** provider. For Leaflet, the native popup already has its own close button provided by the library.

> **Note:** Any `<style>` blocks inside the Infobox Content are also supported — they will be injected into the page alongside the popup HTML.

## Custom Marker Icon

You can replace the default provider pin with any PNG or SVG image. The icon URL, width (px), and height (px) are resolved through a **4-level hierarchy** — the same pattern as the infobox:

| Priority    | Source                   | How                                                                                         |
| ----------- | ------------------------ | ------------------------------------------------------------------------------------------- |
| 1 — highest | Per-entry (stored value) | Editor uses the **Browse assets** / drag & drop / choose file picker in the CP fieldtype    |
| 2           | Per-field config         | `marker_icon_asset` / `marker_icon_width` / `marker_icon_height` on the blueprint field     |
| 3           | CP Settings page         | **Tools → Alt Google Maps** → Marker section (asset picker)                                 |
| 4 — lowest  | Config / `.env`          | `marker_icon_url/width/height` in `config/statamic-google-maps.php` / `.env`                |

Leaving any level empty means "inherit from the next level down". If the resolved URL is empty after all fallbacks, the provider's default pin is used.

### Asset browser picker

The Marker Icon field uses Statamic's native **Assets fieldtype** component — the same UI used by all other asset fields in the CP — at **every level**:

- **Per-entry** — appears inside the CP fieldtype for both single and multi-marker modes
- **Per-field config** — the `marker_icon_asset` option in the blueprint / fieldset editor
- **CP Settings page** — **Tools → Alt Google Maps** → Marker section

In all three places the editor gets:

- A **Browse Assets** button that opens the full asset selector modal
- **Drag & drop** file upload directly onto the field
- A **Choose a file** link to open the OS file picker
- A thumbnail row showing the selected image with filename, filesize, and a remove button

When an asset is selected or uploaded, the addon automatically resolves its public URL and stores it alongside the asset ID. The width/height inputs appear below once an icon is selected.

### `marker_icon` and `marker_icon_json` augmented variables

After augmentation, two variables are available:

| Variable           | Type        | Description                                           |
| ------------------ | ----------- | ----------------------------------------------------- |
| `marker_icon`      | array\|null | `{ url, width, height }` or `null` if not set         |
| `marker_icon_json` | string      | Pre-encoded JSON string — or `''` if not set          |

Use `marker_icon` for direct property access in Antlers:

```antlers
{{ if marker_icon }}
    <img src="{{ marker_icon:url }}" width="{{ marker_icon:width }}" height="{{ marker_icon:height }}">
{{ /if }}
```

Use `marker_icon_json` for `data-marker` HTML attributes — it is already JSON-encoded so only needs `| entities` to HTML-escape the quotes:

```antlers
{{# Standalone field #}}
<div id="map-{{ lat }}-{{ lng }}"
     data-marker="{{ marker_icon_json | entities }}"
     style="height: {{ map_height }}px; width: 100%;">
</div>

{{# Inside a Replicator / Bard set #}}
<div id="gmap-{{ id }}"
     data-marker="{{ google_maps_field:marker_icon_json | entities }}"
     style="height: {{ google_maps_field:map_height ?? 420 }}px; width: 100%;">
</div>
```

> **Important — `| entities`:** `marker_icon_json` contains double-quotes. When written into an HTML `data-*` attribute you **must** use `| entities` so the quotes are HTML-encoded (`"` → `&quot;`). Without it, the browser truncates the attribute at the first inner `"` and `JSON.parse` fails silently.

### In the map adapter

The `window.AltMap` adapter reads `mapEl.dataset.marker`, parses it, and builds the appropriate icon object for each provider:

- **Leaflet** — `L.icon({ iconUrl, iconSize: [width, height] })`
- **Google Maps** — `{ url, scaledSize: new google.maps.Size(width, height) }`

## Map Styles (Snazzy Maps)

The `map_style` setting accepts a **style JSON array** from [snazzymaps.com](https://snazzymaps.com). It is applied to Google Maps via `google.maps.Map({ styles: [...] })`. The OSM/Leaflet provider ignores this setting.

**To apply a style:**

1. Browse [snazzymaps.com](https://snazzymaps.com), click a style, and copy the **JavaScript Style Array** JSON
2. Paste it in the CP: **Tools → Alt Google Maps** → Map Style JSON (applies site-wide)
3. Or paste it in the blueprint field config under `map_style` (applies to that field only)

> **Important — `| entities` in templates:** Map style JSON contains double-quote characters. When written into an HTML `data-style` attribute you **must** use the `| entities` Antlers modifier: `data-style="{{ map_style | entities }}"`. Without it the browser truncates the attribute value at the first `"` inside the JSON, `el.dataset.style` is malformed, and `JSON.parse` silently fails — the style has no visible effect even though the JSON was saved correctly.

**Override hierarchy:**

| Priority    | Source           | How                                           |
| ----------- | ---------------- | --------------------------------------------- |
| 1 — highest | Per-field config | `map_style` in the blueprint fieldtype config |
| 2 — lowest  | CP Settings page | **Tools → Alt Google Maps** → Map Style JSON  |

There is no per-entry style override — style is a fieldset-level concern.

> **Note:** If `map_style` is empty, no `styles:` option is passed to Google Maps and the default map style is used.

## Testing

The addon ships with a [Pest](https://pestphp.com) test suite covering the geocoding controller, the override hierarchy (per-field → CP settings → `.env` → config file), the augmented variables, and the settings storage layer.

### Running the suite

```bash
cd addons/alt-design/alt-google-maps-addon
composer install
./vendor/bin/pest
```

### Useful options

```bash
./vendor/bin/pest --filter "OSM"   # only tests matching "OSM"
./vendor/bin/pest --parallel       # run in parallel
./vendor/bin/pest --coverage       # with code coverage report
```

### Test layout

| File                                 | Purpose                                                                                  |
| ------------------------------------ | ---------------------------------------------------------------------------------------- |
| `tests/TestCase.php`                 | Base test case extending `Statamic\Testing\AddonTestCase`                                |
| `tests/SettingsTestCase.php`         | Variant that seeds `google-maps-settings.json` before the addon boots                   |
| `tests/Pest.php`                     | Per-file `uses()` declarations (no global binding)                                       |
| `tests/GeocodingControllerTest.php`  | Google + OSM geocode/reverse-geocode, HTTP errors, User-Agent header, request validation |
| `tests/ProviderHierarchyTest.php`    | Provider override hierarchy, augmented variables, `google_map` Antlers tag rendering     |
| `tests/SettingsHierarchyTest.php`    | CP Settings page → runtime config propagation (provider, lat/lng/zoom)                   |

### Writing new tests

Most tests use `it('describes behaviour', function () { ... })` with `expect()` assertions. Network calls are stubbed with `Http::fake()`. Example:

```php
use AltDesign\AltGoogleMapsAddon\Tests\TestCase;

uses(TestCase::class);

it('falls back to global provider when per-field is blank', function () {
    config(['statamic-google-maps.map_provider' => 'osm']);

    $ft = (new GoogleMapsFieldtype)->setField(new \Statamic\Fields\Field('map', [
        'type'         => 'google_maps',
        'map_provider' => '',
    ]));

    expect($ft->augment(['lat' => 1, 'lng' => 2, 'zoom' => 10])['map_provider'])->toBe('osm');
});
```

## License

MIT — see [LICENSE](LICENSE).
