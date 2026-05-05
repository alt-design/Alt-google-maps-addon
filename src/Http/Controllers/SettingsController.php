<?php

namespace AltDesign\AltGoogleMapsAddon\Http\Controllers;

use AltDesign\AltGoogleMapsAddon\Fieldtypes\GoogleMapsFieldtype;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Statamic\CP\PublishForm;
use Statamic\Fields\BlueprintRepository;

class SettingsController extends Controller
{
    private string $storageFile;

    public function __construct()
    {
        $this->storageFile = storage_path('app/statamic/google-maps-settings.json');
    }

    public function index()
    {
        $blueprint = $this->getBlueprint();
        $fields = $blueprint->fields()->addValues($this->getDefaults())->preProcess();

        return PublishForm::make($blueprint)
            ->title('Google Maps')
            ->icon('pin')
            ->values($fields->values()->toArray())
            ->submittingTo(cp_route('google-maps.settings.store'), 'POST');
    }

    public function store(Request $request): bool
    {
        $blueprint = $this->getBlueprint();
        $fields = $blueprint->fields()->addValues($request->all());
        $fields->validate();

        $values = $fields->process()->values();

        $data = [
            'lat'             => $values->get('lat') !== null && $values->get('lat') !== '' ? (float) $values->get('lat') : null,
            'lng'             => $values->get('lng') !== null && $values->get('lng') !== '' ? (float) $values->get('lng') : null,
            'zoom'            => $values->get('zoom') !== null ? (int) $values->get('zoom') : 13,
            'map_provider'    => in_array($values->get('map_provider'), ['google', 'osm']) ? $values->get('map_provider') : 'google',
            'infobox_title'      => GoogleMapsFieldtype::sanitizePlainText($values->get('infobox_title') ?? ''),
            'infobox_content'    => GoogleMapsFieldtype::sanitizeHtml($values->get('infobox_content') ?? ''),
            'map_style'          => trim((string) ($values->get('map_style') ?? '')),
            'marker_icon_asset'  => $this->resolveAssetId($values->get('marker_icon_asset')),
            'marker_icon_url'    => GoogleMapsFieldtype::sanitizeUrl($this->resolveAssetUrl($values->get('marker_icon_asset'))),
            'marker_icon_width'  => $values->get('marker_icon_width') !== null ? (int) $values->get('marker_icon_width') : 32,
            'marker_icon_height' => $values->get('marker_icon_height') !== null ? (int) $values->get('marker_icon_height') : 40,
        ];

        $dir = dirname($this->storageFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($this->storageFile, json_encode($data), LOCK_EX) === false) {
            throw new \RuntimeException('Failed to save Google Maps settings. Check that storage/app/statamic/ is writable.');
        }

        return true;
    }

    private function resolveAssetId(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }
        $id = is_array($value) ? ($value[0] ?? null) : $value;
        if (! $id) {
            return null;
        }
        $id = (string) $id;

        // Normalise to full 'container::path' format so Asset::find() works reliably.
        if (! str_contains($id, '::')) {
            foreach (\Statamic\Facades\AssetContainer::all() as $container) {
                if (\Statamic\Facades\Asset::find($container->handle() . '::' . $id)) {
                    return $container->handle() . '::' . $id;
                }
            }
        }

        return $id;
    }

    private function resolveAssetUrl(mixed $value): string
    {
        $id = $this->resolveAssetId($value);
        if (! $id) {
            return '';
        }

        // Try the ID as-is first (may already include container, e.g. 'assets::pin.png')
        $asset = \Statamic\Facades\Asset::find($id);
        if ($asset) {
            return (string) $asset->url();
        }

        // Statamic's assets fieldtype sometimes returns just the path without the container
        // prefix (e.g. 'pin.png' instead of 'assets::pin.png'). Try all containers.
        foreach (\Statamic\Facades\AssetContainer::all() as $container) {
            $asset = \Statamic\Facades\Asset::find($container->handle() . '::' . $id);
            if ($asset) {
                return (string) $asset->url();
            }
        }

        return '';
    }

    private function getBlueprint()
    {
        return with(new BlueprintRepository)
            ->setDirectory(__DIR__ . '/../../../resources/blueprints')
            ->find('google_maps_settings');
    }

    private function getDefaults(): array
    {
        if (file_exists($this->storageFile)) {
            try {
                $data = json_decode(file_get_contents($this->storageFile), true, 512, JSON_THROW_ON_ERROR);
                if (is_array($data)) {
                    return $data;
                }
            } catch (\JsonException $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Corrupted google-maps-settings.json — using config defaults',
                    ['error' => $e->getMessage()]
                );
            }
        }

        return [
            'lat'             => config('statamic-google-maps.defaults.lat'),
            'lng'             => config('statamic-google-maps.defaults.lng'),
            'zoom'            => config('statamic-google-maps.defaults.zoom', 13),
            'map_provider'    => config('statamic-google-maps.map_provider', 'google'),
            'infobox_title'      => config('statamic-google-maps.infobox_title', ''),
            'infobox_content'    => config('statamic-google-maps.infobox_content', ''),
            'map_style'          => config('statamic-google-maps.map_style', ''),
            'marker_icon_asset'  => null,
            'marker_icon_url'    => config('statamic-google-maps.marker_icon_url', ''),
            'marker_icon_width'  => config('statamic-google-maps.marker_icon_width', 40),
            'marker_icon_height' => config('statamic-google-maps.marker_icon_height', 40),
        ];
    }
}