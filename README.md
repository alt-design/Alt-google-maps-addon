# Alt Google Maps Addon for Statamic

A Google Maps location picker fieldtype for [Statamic CMS](https://statamic.com). Adds a `google_maps` fieldtype that stores latitude, longitude, address, and zoom level, plus a CP settings page to configure defaults.

## Features

- **`google_maps` fieldtype** — coordinate inputs (Lat/Lng/Zoom) with address display in the CP
- **CP Settings page** — configure default coordinates via Tools → Google Maps
- **Augmented data** — automatically generates `google_maps_url`, `directions_url`, and `static_map_url` for use in Antlers templates
- **`.env` configuration** — API key and defaults via environment variables

## Requirements

- PHP 8.1+
- Statamic 5 or 6
- Google Maps API key with the **Maps JavaScript API** enabled

## Installation

```bash
composer require alt-design/alt-google-maps-addon
```

Publish the config file:

```bash
php artisan vendor:publish --tag=statamic-google-maps-config
```

## Configuration

Add to your `.env`:

```env
GOOGLE_MAPS_API_KEY=your_api_key_here

# Optional: set default coordinates (leave blank for empty field)
GOOGLE_MAPS_DEFAULT_LAT=51.5074
GOOGLE_MAPS_DEFAULT_LNG=-0.1278
GOOGLE_MAPS_DEFAULT_ZOOM=13
```

You can also set defaults via the CP under **Tools → Google Maps**.

## Usage

### In a Blueprint or Fieldset

```yaml
-
  handle: location
  field:
    type: google_maps
    display: Location
```

### In Antlers Templates

The fieldtype augments the value with extra URLs:

```antlers
{{ if location }}
    {{ location }}
        <div id="map-{{ lat }}-{{ lng }}" style="height: 420px; width: 100%;"></div>

        <script>
            (function() {
                var mapEl = document.getElementById('map-{{ lat }}-{{ lng }}');
                var lat = {{ lat }};
                var lng = {{ lng }};
                var zoom = {{ zoom }};

                function initMap() {
                    var map = new google.maps.Map(mapEl, {
                        center: { lat: lat, lng: lng },
                        zoom: zoom,
                        mapTypeId: 'roadmap',
                        streetViewControl: false,
                    });
                    new google.maps.Marker({ map: map, position: { lat: lat, lng: lng } });
                }

                if (window.google && window.google.maps) {
                    initMap();
                } else if (!window._gmapsFrontendLoading) {
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

        {{# Extra augmented variables available: #}}
        {{# {{ google_maps_url }} — Google Maps search link #}}
        {{# {{ directions_url }} — Google Maps directions link #}}
        {{# {{ static_map_url }} — Static map image URL #}}
    {{ /location }}
{{ /if }}
```

### Available variables inside the tag pair

| Variable | Type | Description |
|---|---|---|
| `{{ lat }}` | float | Latitude |
| `{{ lng }}` | float | Longitude |
| `{{ zoom }}` | int | Zoom level (1–21) |
| `{{ address }}` | string | Address string (if set) |
| `{{ google_maps_url }}` | string | Link to Google Maps |
| `{{ directions_url }}` | string | Link to Google Maps directions |
| `{{ static_map_url }}` | string | Static map image URL (requires API key) |

## License

MIT — see [LICENSE](LICENSE).
