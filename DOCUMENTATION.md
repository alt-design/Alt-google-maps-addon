# Alt Google Maps

Alt Google Maps adds a `google_maps` fieldtype to the Statamic control panel and three Antlers tags for the front end. Editors search for an address or click a map to place a pin. The field stores latitude, longitude, zoom and address, and can hold several markers with their own popups and icons.

The maps can be drawn by Google Maps (needs an API key) or by OpenStreetMap through Leaflet (no key needed). You can choose per site and override it per field.

## Requirements

- PHP 8.2 or later
- Statamic 6.40 or later (Statamic 5 is not supported)
- Google Maps provider only: a Google Maps API key with the **Maps JavaScript API** enabled ([how to create one](https://developers.google.com/maps/documentation/javascript/get-api-key)). The **Geocoding API** is needed only if you call the control panel geocode route with the Google provider, which the fieldtype itself does not do.
- OpenStreetMap provider: no key and no account.

Google Maps is a paid Google service with a free monthly allowance. Check [Google's current pricing](https://mapsplatform.google.com/pricing/) before using it on a busy site.

## Installation

```bash
composer require alt-design/alt-google-maps
```

The addon publishes its control panel assets when Composer installs it. If the maps do not appear in the control panel, publish them again:

```bash
php artisan vendor:publish --tag=alt-google-maps --force
```

Optionally publish the config file or the front end views:

```bash
php artisan vendor:publish --tag=alt-google-maps-config
php artisan vendor:publish --tag=alt-google-maps-views
```

## Configuration

Add what you need to `.env`:

```env
GOOGLE_MAPS_PROVIDER=osm
GOOGLE_MAPS_API_KEY=
GOOGLE_MAPS_DEFAULT_LAT=51.5074
GOOGLE_MAPS_DEFAULT_LNG=-0.1278
GOOGLE_MAPS_DEFAULT_ZOOM=13
```

`GOOGLE_MAPS_PROVIDER` is `google` (the default) or `osm`. With `google` and no key, the control panel field and the front end tags show a message instead of a map.

The same settings, plus popup defaults, a map style and a default marker icon, can be set in the control panel under **Alt Tools, Editing, Alt Google Maps** (also reachable from **Tools, Addons**). Values saved there are written to `resources/addons/alt-google-maps.yaml`, so they are committed and deployed with the site. A blank setting means "use the `.env` or config file value".

Settings are resolved in this order, highest first:

1. Options on the field in the blueprint (for example `map_provider: osm`)
2. The settings page
3. `.env`
4. `config/alt-google-maps.php`

### Permissions

The addon registers one permission, **Use the Alt Google Maps address search** (`use alt-google-maps geocoding`). Editors need it to use the search box and automatic address lookup when the OpenStreetMap provider is selected. Without it they can still place a pin by clicking the map or typing coordinates. Super users have it already.

Who may change the addon settings is controlled by Statamic's own addon settings permission, listed as **Alt Google Maps settings** under Addons when you edit a role.

### Google API key restrictions

The Maps JavaScript API needs the key in the page, so the key is visible to anyone who views the source of a page that uses Google Maps. In Google Cloud Console, restrict it to your domains under HTTP referrers and to the Maps JavaScript API (and the Geocoding API if you use it). Use a separate key for development.

## Using the fieldtype

Add a field of type **Google Maps** in a blueprint:

```yaml
-
  handle: location
  field:
    type: google_maps
    display: Office location
    default_lat: 51.5033
    default_lng: -0.1195
    default_zoom: 14
```

For several markers:

```yaml
-
  handle: branches
  field:
    type: google_maps
    display: Branch locations
    enable_multiple: true
    enable_clustering: true
```

Field options: `enable_multiple`, `enable_clustering`, `cluster_color`, `map_provider`, `default_zoom`, `default_lat`, `default_lng`, `map_height`, `default_infobox_title`, `default_infobox_content`, `map_style`, `marker_icon_asset`, `marker_icon_width` and `marker_icon_height`. Leave an option blank to inherit the site setting.

Each marker can have a popup title, popup content (a small set of HTML tags is kept, the rest is removed on save) and its own icon image.

## Using the tags

### Single marker

```antlers
{{ google_map :lat="location:lat" :lng="location:lng" :zoom="location:zoom" height="320" }}
```

Parameters: `lat` and `lng` (required), `zoom`, `height` (pixels), `width`, `id`, `provider`, `map_type`, `marker_icon`, `marker_icon_width`, `marker_icon_height`. Nothing is rendered if `lat` or `lng` is missing.

### Several markers, with optional clustering

```antlers
{{ google_map:multi :markers="branches:markers_json" :clustering="branches:enable_clustering" height="420" }}
```

Parameters: `markers` (required, the `markers_json` value), `clustering`, `cluster_color`, `zoom`, `default_zoom`, `height`, `width`, `id`, `provider`, `map_type`. Popups open when a marker is clicked. A single-marker field also has `markers_json`, so this tag is the way to show a popup for one marker.

### Static map image URL (Google only)

```antlers
<img src="{{ google_map:static :lat="location:lat" :lng="location:lng" zoom="14" width="600" height="300" }}" alt="Map of our office">
```

Returns an empty string when no API key is set.

### Variables on the field value

A single marker field provides `lat`, `lng`, `address`, `zoom`, `map_provider`, `map_height`, `infobox_title`, `infobox_content`, `map_style`, `marker_icon`, `markers`, `markers_json`, `google_maps_url`, `directions_url` and `static_map_url`. A multiple marker field provides `markers`, `markers_json`, `enable_clustering`, `cluster_color`, `map_provider`, `map_height` and `map_style`, plus `lat`, `lng` and `address` of the first marker. Disabled markers and markers without coordinates are left out.

```antlers
<a href="{{ location:directions_url }}">Get directions to {{ location:address }}</a>
```

### Customising the markup

The tags render the views `map`, `map-multi` and `map-unavailable` from the `alt-google-maps` view namespace. Publish them with the `alt-google-maps-views` tag and edit the copies in `resources/views/vendor/alt-google-maps`.

## When something is missing

- Google provider selected and no API key: the control panel field and both map tags show a message saying so. A warning is written to the Laravel log by the tags.
- A map script cannot be loaded (for example the visitor is offline or blocks the CDN): the map area shows "This map could not be loaded".
- Address search returns nothing: the field shows "No results found." and you can still click the map.

## External services

The addon contacts these services. Nothing is sent to Alt Design.

| Service | When | What is sent |
| --- | --- | --- |
| `maps.googleapis.com` (Maps JavaScript API) | Google provider, in the control panel and on the front end | The API key and the visitor's or editor's browser details, as with any Google Maps embed |
| `maps.googleapis.com/maps/api/geocode` | Server side, only if the control panel geocode route is called with the Google provider | The address or coordinates searched, and the API key |
| `maps.googleapis.com/maps/api/staticmap` | The browser loads the URL returned by `{{ google_map:static }}` | The coordinates and the API key |
| `tile.openstreetmap.org` | OpenStreetMap provider, in the browser | Map tile requests, so the visitor's IP address and the area viewed |
| `nominatim.openstreetmap.org` | OpenStreetMap provider, server side, when an editor searches or places a pin | The address or coordinates searched, with a User-Agent identifying this addon. Results are cached for 24 hours and requests are limited to one per second, as the Nominatim usage policy requires |
| `unpkg.com` | OpenStreetMap provider, on the front end | Requests for Leaflet 1.9.4, Leaflet.markercluster 1.5.3 (clustering only) and `@googlemaps/markerclusterer` 2.6.2 (Google clustering only), each with a subresource integrity hash |

The control panel bundles Leaflet and does not load anything from `unpkg.com`.

If your privacy policy or cookie consent set-up needs to cover these requests, list them there. The OpenStreetMap tile servers and Nominatim are public services run by the OpenStreetMap Foundation; read their usage policies before using the addon for high traffic.

## For contributors

| Part | Where |
| --- | --- |
| Fieldtype (PHP) | `src/Fieldtypes/GoogleMapsFieldtype.php` |
| Fieldtype (control panel, Vue) | `resources/js/components/fieldtypes`, built to `resources/dist` |
| Antlers tags | `src/Tags/GoogleMapTag.php`, views in `resources/views` |
| Settings blueprint | `resources/blueprints/settings.yaml`, applied by `src/Settings` |
| Geocoding proxy for OpenStreetMap | `src/Http/Controllers/GeocodingController.php`, routes in `routes/cp.php` |
| HTML sanitiser for popup content | `src/Support/HtmlSanitizer.php` |

Settings saved on the addon settings page override the config at boot, so `config('alt-google-maps.map_provider')` always holds the resolved value.

The control panel code is built with Vite and needs Node 22 or later. `resources/dist` is committed, so rebuild it with `npm run build` and commit the result after changing anything under `resources/js` or `resources/css`.

`./vendor/bin/pest` covers the geocoding controller and its permission, the settings and config override order, the augmented values, the tags and the sanitiser. `npm test` covers the geocoding and script loading helpers. Pest writes its temporary users and containers to the system temp directory, not into the repository.

## Support

Support is through GitHub issues: https://github.com/alt-design/Alt-google-maps-addon/issues

## Licence

MIT. See `LICENSE`. Bundled third-party code is listed in `THIRD-PARTY-NOTICES.md`.
