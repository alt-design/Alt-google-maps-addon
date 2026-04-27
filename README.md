# Alt Google Maps Addon for Statamic

A location picker fieldtype for [Statamic CMS](https://statamic.com) with support for both Google Maps and OpenStreetMap. Adds a `google_maps` fieldtype with an interactive map, address search, click-to-place, and drag-to-move, plus a CP settings page to configure defaults.

## Features

- **`google_maps` fieldtype** — interactive map with search bar, click-to-place pin, drag-to-move marker, and editable coordinate inputs (Lat/Lng/Zoom); shows address or coordinates in the CP header
- **Dual map provider** — choose **Google Maps** (requires API key) or **OpenStreetMap / Leaflet** (no API key needed)
- **Infobox / popup** — configurable marker popup (title + HTML body); opens on marker click; powered by [Snazzy Info Window](https://snazzymaps.com/about/info-window) (Google) and Leaflet's native `bindPopup` (OSM)
- **Map styles** — apply custom map styles from [snazzymaps.com](https://snazzymaps.com) (Google Maps only); paste the style JSON in the CP or per-fieldset
- **CP Settings page** — configure default coordinates, map provider, infobox defaults, and map style via Tools → Alt Google Maps
- **Augmented data** — automatically generates `google_maps_url`, `directions_url`, and `static_map_url` for use in Antlers templates
- **Adapter pattern** — frontend rendering uses a shared `window.AltMap` adapter; Leaflet, Google Maps SDK, and Snazzy Info Window are all lazy-loaded from CDN only when needed
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

You can also configure these settings via the CP under **Tools → Alt Google Maps**. Values saved there take priority over `.env` — to reset, delete `storage/app/statamic/google-maps-settings.json`.

### Config file

After publishing, `config/statamic-google-maps.php` exposes additional options:

| Key               | Default     | Description                                                                              |
| ----------------- | ----------- | ---------------------------------------------------------------------------------------- |
| `api_key`         | `''`        | Google Maps API key (reads `GOOGLE_MAPS_API_KEY`)                                        |
| `map_provider`    | `'google'`  | `'google'` or `'osm'` (reads `GOOGLE_MAPS_PROVIDER`)                                     |
| `defaults.lat`    | `null`      | Default latitude                                                                         |
| `defaults.lng`    | `null`      | Default longitude                                                                        |
| `defaults.zoom`   | `13`        | Default zoom level                                                                       |
| `map_height`      | `420`       | Map height in pixels (CP fieldtype default)                                              |
| `map_type`        | `'roadmap'` | Google Maps type: `'roadmap'`, `'satellite'`, `'terrain'`, `'hybrid'`                    |
| `infobox_title`   | `''`        | Default popup title (reads `GOOGLE_MAPS_INFOBOX_TITLE`); overridden by CP Settings       |
| `infobox_content` | `''`        | Default popup body HTML (reads `GOOGLE_MAPS_INFOBOX_CONTENT`); overridden by CP Settings |
| `map_style`       | `''`        | Snazzy Maps style JSON for Google Maps; overridden by CP Settings                        |

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

| Option                    | Type     | Default          | Description                                                                  |
| ------------------------- | -------- | ---------------- | ---------------------------------------------------------------------------- |
| `map_provider`            | select   | _(site default)_ | `'google'` or `'osm'` — overrides the global setting for this field only     |
| `default_lat`             | float    | _(site default)_ | Starting latitude for new entries — blank to inherit from site default       |
| `default_lng`             | float    | _(site default)_ | Starting longitude for new entries — blank to inherit from site default      |
| `default_zoom`            | integer  | _(site default)_ | Initial zoom level (1 = world, 20 = building) — blank = site default         |
| `map_height`              | integer  | `420`            | Map height in pixels                                                         |
| `enable_search`           | toggle   | `true`           | Show/hide the address search bar                                             |
| `enable_click`            | toggle   | `true`           | Enable click-to-place on the map                                             |
| `default_infobox_title`   | text     | _(site default)_ | Popup title for all entries using this field — overrides CP/config default   |
| `default_infobox_content` | textarea | _(site default)_ | Popup body HTML — overrides CP/config default for this field only            |
| `map_style`               | textarea | _(site default)_ | Snazzy Maps style JSON (Google only) — overrides global style for this field |

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

### Frontend Adapter (`window.AltMap`)

The addon ships a lightweight `window.AltMap` adapter partial that handles lazy-loading of Leaflet, Google Maps SDK, and Snazzy Info Window from CDN — only when needed, and only once per page even if multiple maps are present. The recommended pattern for all map templates is:

1. Include the adapter partial once (the guard `if (!window.AltMap)` makes it safe to include multiple times)
2. Add a `<div>` with a unique `id` and `data-style` (HTML-encoded JSON)
3. Add a hidden popup `<div>` for the infobox HTML (optional)
4. Call `AltMap.initLeaflet(...)` or `AltMap.initGoogle(...)` depending on the resolved provider

> **Important — `| entities` on `data-style`:** Map style JSON contains double-quotes. When written into an HTML attribute, those quotes must be HTML-encoded (`"` → `&quot;`) using Antlers' `| entities` modifier. Without it the browser truncates the attribute at the first `"` and `JSON.parse` silently receives an empty or malformed string, so the style has no effect.

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

| Variable          | Type   | Description                                                                        |
| ----------------- | ------ | ---------------------------------------------------------------------------------- |
| `lat`             | float  | Latitude                                                                           |
| `lng`             | float  | Longitude                                                                          |
| `zoom`            | int    | Zoom level (1–21)                                                                  |
| `address`         | string | Address string (if set via search, click, or drag)                                 |
| `map_provider`    | string | Resolved provider for this field: `'google'` or `'osm'` (per-field → site default) |
| `map_height`      | int    | Resolved map height in pixels (per-field → site default)                           |
| `infobox_title`   | string | Resolved popup title (per-entry → per-field → site default)                        |
| `infobox_content` | string | Resolved popup body HTML (per-entry → per-field → site default)                    |
| `map_style`       | string | Resolved Snazzy Maps style JSON (per-field → site default; Google only)            |
| `google_maps_url` | string | Link to Google Maps at this location                                               |
| `directions_url`  | string | Link to Google Maps directions to this location                                    |
| `static_map_url`  | string | Static map image URL (Google provider only; requires API key)                      |

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

## License

MIT — see [LICENSE](LICENSE).
