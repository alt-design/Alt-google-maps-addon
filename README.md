# Alt Google Maps for Statamic

A location picker fieldtype for [Statamic](https://statamic.com) that draws its maps with Google Maps or OpenStreetMap. It adds a `google_maps` fieldtype (single or multiple markers, popups, clustering, custom icons), three Antlers tags for the front end and a settings page in the control panel.

Installation, configuration, usage, permissions and the list of external services are in [DOCUMENTATION.md](DOCUMENTATION.md). Changes are in [CHANGELOG.md](CHANGELOG.md).

```bash
composer require alt-design/alt-google-maps
```

Requires PHP 8.2+ and Statamic 6.40+.

## How it fits together

| Part | Where |
| --- | --- |
| Fieldtype (PHP) | `src/Fieldtypes/GoogleMapsFieldtype.php` |
| Fieldtype (control panel, Vue) | `resources/js/components/fieldtypes`, built to `resources/dist` |
| Antlers tags | `src/Tags/GoogleMapTag.php`, views in `resources/views` |
| Settings blueprint | `resources/blueprints/settings.yaml`, applied by `src/Settings` |
| Geocoding proxy for OpenStreetMap | `src/Http/Controllers/GeocodingController.php`, routes in `routes/cp.php` |
| HTML sanitiser for popup content | `src/Support/HtmlSanitizer.php` |

Settings saved on the addon settings page live in `resources/addons/alt-google-maps.yaml` and override the config at boot, so `config('alt-google-maps.map_provider')` always holds the resolved value.

## Development

The control panel code is built with Vite and needs Node 22.

```bash
composer install
npm install
npm run build
```

`resources/dist` is committed, so rebuild it and commit the result after changing anything under `resources/js` or `resources/css`.

### Tests

```bash
./vendor/bin/pest
npm test
```

The Pest suite covers the geocoding controller and its permission, the settings and config override order, the augmented values, the tags (including the missing key message) and the sanitiser. The Vitest suite covers the geocoding and script loading helpers. Pest writes its temporary users and containers to the system temp directory, not into the repository.

## Security

The Maps JavaScript API needs the API key in the page, so the key is visible in the source of pages that use Google Maps. Restrict it by HTTP referrer and by API in Google Cloud Console. Control panel routes are protected by the `use alt-google-maps geocoding` permission and by Statamic's addon settings permission. Popup HTML is reduced to a small allowlist of tags when it is saved and again when it is read.

## Licence

MIT, see [LICENSE](LICENSE). Bundled third-party code is listed in [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).
