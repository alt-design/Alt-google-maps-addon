<?php

namespace AltDesign\AltGoogleMapsAddon\Http\Controllers;

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
            'infobox_title'   => trim((string) ($values->get('infobox_title') ?? '')),
            'infobox_content' => trim((string) ($values->get('infobox_content') ?? '')),
            'map_style'       => trim((string) ($values->get('map_style') ?? '')),
        ];

        $dir = dirname($this->storageFile);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->storageFile, json_encode($data));

        return true;
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
            $data = json_decode(file_get_contents($this->storageFile), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return [
            'lat'             => config('statamic-google-maps.defaults.lat'),
            'lng'             => config('statamic-google-maps.defaults.lng'),
            'zoom'            => config('statamic-google-maps.defaults.zoom', 13),
            'map_provider'    => config('statamic-google-maps.map_provider', 'google'),
            'infobox_title'   => config('statamic-google-maps.infobox_title', ''),
            'infobox_content' => config('statamic-google-maps.infobox_content', ''),
            'map_style'       => config('statamic-google-maps.map_style', ''),
        ];
    }
}