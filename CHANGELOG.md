# Changelog

All notable changes to this project are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-06

### Added

- `google_maps` fieldtype with address search, click to place, drag to move, multiple markers, clustering, popups and custom marker icons.
- `{{ google_map }}`, `{{ google_map:multi }}` and `{{ google_map:static }}` Antlers tags.
- Google Maps and OpenStreetMap (Leaflet) providers, selectable per site and per field.
- Settings page using Statamic's addon settings, stored in `resources/addons/alt-google-maps.yaml`.
- `use alt-google-maps geocoding` permission for the control panel address search.
- Visible messages when the Google provider has no API key or a map script fails to load.
