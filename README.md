# Alt Google Maps

> A location fieldtype with Google Maps or OpenStreetMap, plus tags to show the map on your site.

Alt Google Maps adds a location fieldtype to Statamic. Editors search for an address or click the map to drop a pin, and the field stores the coordinates, zoom and address, for one marker or many.

## Features

- A **Google Maps** fieldtype: search for an address or click the map to place a pin, then drag it to adjust
- One marker or many, each with its own popup and icon, with optional clustering
- Google Maps with your own API key, or OpenStreetMap with no key at all, chosen per site and per field
- `{{ google_map }}` tags that render the map on your site, with no JavaScript to write
- Coordinates, address, zoom, a directions link and, with Google, a static map URL on the field's value for your own templates
- A settings page for the site's default location, provider, popup text, map style and marker icon, saved with the site's files so it deploys with them
- Popup HTML is reduced to a short list of safe tags when it is saved and again when it is shown

## Requirements

- Statamic 6.40 or later
- PHP 8.2 or later
- For Google Maps, an API key with the Maps JavaScript API enabled, which you can [create in Google Cloud Console](https://developers.google.com/maps/documentation/javascript/get-api-key). OpenStreetMap needs no key and no account.

Google Maps is a paid Google service with a free monthly allowance. Check [Google's current pricing](https://mapsplatform.google.com/pricing/) before using it on a busy site.

## How to Install

Run the following command from your project root:

``` bash
composer require alt-design/alt-google-maps
```

## Basic usage

Choose a provider in `.env`. Use OpenStreetMap:

``` bash
GOOGLE_MAPS_PROVIDER=osm
```

Or Google Maps, with your key:

``` bash
GOOGLE_MAPS_PROVIDER=google
GOOGLE_MAPS_API_KEY=
```

You can also set these, along with the default location, popup text, map style and marker icon, in the control panel under **Alt Tools, Editing, Alt Google Maps** (also reachable from **Tools, Addons**).

Add a **Google Maps** field to a blueprint:

``` yaml
-
  handle: location
  field:
    type: google_maps
    display: Office location
```

Then show it in a template:

``` antlers
{{ google_map :lat="location:lat" :lng="location:lng" :zoom="location:zoom" height="320" }}
```

For several markers, turn on `enable_multiple` in the field settings and use the `multi` tag:

``` antlers
{{ google_map:multi :markers="branches:markers_json" :clustering="branches:enable_clustering" height="420" }}
```

The field's value has everything you need for your own markup too:

``` antlers
<a href="{{ location:directions_url }}">Get directions to {{ location:address }}</a>
```

## Permissions

The addon registers a **Use the Alt Google Maps address search** permission. Editors need it for the address search when the OpenStreetMap provider is selected. Without it they can still place a pin by clicking the map or typing coordinates. Who can change the addon settings is controlled by Statamic's own addon settings permission. Super users have both.

## External services

Nothing is sent to Alt Design. Depending on the provider, maps load from Google Maps or from OpenStreetMap's tile servers, and the OpenStreetMap address search uses Nominatim from your server. [DOCUMENTATION.md](DOCUMENTATION.md#external-services) lists every request and what it sends, for your privacy policy.

The Maps JavaScript API needs the key in the page, so restrict your key by HTTP referrer and by API in Google Cloud Console.

## Documentation

[DOCUMENTATION.md](DOCUMENTATION.md) covers every setting, field option, tag parameter and value, and what happens when a key or script is missing. Changes are listed in [CHANGELOG.md](CHANGELOG.md).

## Development

The control panel code is built with Vite and needs Node 22 or later. `resources/dist` is committed, so rebuild it and commit the result after changing anything under `resources/js` or `resources/css`.

``` bash
composer install
npm install
npm run build
```

## Testing

``` bash
./vendor/bin/pest
npm test
```

## Questions etc

Drop us a big shout-out if you have any questions, comments, or concerns. We're always looking to improve our addons, so if you have any feature requests, we'd love to hear them.

Bug reports and support requests go to [GitHub issues](https://github.com/alt-design/Alt-google-maps-addon/issues).

### Starter Kits
- [Alt Starter Kit](https://statamic.com/starter-kits/alt-design/alt-starter-kit)

### Addons
- [Alt Admin Bar Addon](https://github.com/alt-design/Alt-Admin-Bar-Addon)
- [Alt AI Addon](https://github.com/alt-design/Alt-AI-Addon)
- [Alt Akismet Addon](https://github.com/alt-design/Alt-Akismet-Addon)
- [Alt Cookies Addon](https://github.com/alt-design/Alt-Cookies-Addon)
- [Alt Google 2FA Addon](https://github.com/alt-design/Alt-Google-2fa-Addon)
- [Alt Google Maps Addon](https://github.com/alt-design/Alt-google-maps-addon)
- [Alt Inbound Addon](https://github.com/alt-design/Alt-Inbound-Addon)
- [Alt Live Editor Addon](https://github.com/alt-design/Alt-Live-Editor-Addon)
- [Alt Password Protect Addon](https://github.com/alt-design/Alt-Password-Protect-Addon)
- [Alt Redirect Addon](https://github.com/alt-design/Alt-Redirect-Addon)
- [Alt SEO Addon](https://github.com/alt-design/Alt-SEO-Addon)
- [Alt Sitemap Addon](https://github.com/alt-design/Alt-Sitemap-Addon)
- [Alt Tabs Addon](https://github.com/alt-design/Alt-Tabs-Addon)
- [RiffRaff Addon](https://github.com/alt-design/Alt-RiffRaff-Addon)

## Licence

MIT, see [LICENSE](LICENSE). Bundled third-party code is listed in [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

## Postcardware

Send us a postcard from your hometown if you like this addon. We love getting mail from other cool peeps!

Alt Design  
St Helens House  
Derby  
DE1 3EE  
UK
