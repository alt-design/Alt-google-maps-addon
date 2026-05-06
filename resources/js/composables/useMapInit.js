import { ref, onBeforeUnmount } from 'vue';
import { loadGoogleMapsScript, loadLeaflet } from './useMapLoader.js';
import { useElementVisibility } from './useElementVisibility.js';

/**
 * useMapInit — owns the bare map instance lifecycle.
 *
 * Responsibility (and ONLY this):
 *   1. Wait for `mapEl` to become visible (Bard set expansion, accordions, …)
 *   2. Load the provider library (Leaflet via npm import / Google Maps script)
 *   3. Create the map instance with tile layer (OSM) or API options (Google)
 *   4. Expose `mapInstance` + `mapReady` reactive refs
 *   5. Tear the instance down on unmount
 *
 * Marker rendering, click handlers, watchers, picks — all live in
 * useSingleMarkerRender / useMultiMarkerRender. This composable does not know
 * about markers.
 */
export function useMapInit({ props, mapEl, config }) {
    const { mapProvider, globalConfig, defaultZoom, defaultLat, defaultLng } = config;

    const mapInstance = ref(null);
    const mapReady = ref(false);
    let _initStarted = false;

    async function initMap() {
        if (_initStarted || !mapEl.value) return;
        _initStarted = true;
        try {
            if (mapProvider.value === 'osm') {
                await initLeaflet();
            } else {
                await initGoogleMaps();
            }
            mapReady.value = true;
        } catch (err) {
            console.error('[useMapInit] Map init failed:', err);
            _initStarted = false;
        }
    }

    async function initLeaflet() {
        const L = await loadLeaflet();

        // Leaflet's default icon paths break under Vite — re-point them at
        // bundled URLs (resolved at build time relative to this file).
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconUrl:       new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
            iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
            shadowUrl:     new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
        });

        const lat = defaultLat.value ?? 0;
        const lng = defaultLng.value ?? 0;
        const zoom = defaultZoom.value ?? 2;

        const map = L.map(mapEl.value, { center: [lat, lng], zoom });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 21,
        }).addTo(map);

        if (props.readOnly) {
            map.dragging.disable();
            map.scrollWheelZoom.disable();
            map.touchZoom.disable();
            map.doubleClickZoom.disable();
            map.keyboard.disable();
            map.boxZoom.disable();
        }

        mapInstance.value = map;
    }

    async function initGoogleMaps() {
        if (!globalConfig.value.api_key) {
            mapEl.value.innerHTML =
                '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6b7280;font-size:0.875rem;text-align:center;padding:1rem;">' +
                'No Google Maps API key configured.<br>Add <code>GOOGLE_MAPS_API_KEY</code> to your <code>.env</code>,<br>' +
                'or switch to OpenStreetMap in Tools → Google Maps.</div>';
            throw new Error('GOOGLE_MAPS_API_KEY not configured');
        }
        await loadGoogleMapsScript(globalConfig.value.api_key);

        const lat = defaultLat.value ?? 0;
        const lng = defaultLng.value ?? 0;
        const zoom = defaultZoom.value ?? 2;

        mapInstance.value = new window.google.maps.Map(mapEl.value, {
            center: { lat, lng },
            zoom,
            mapTypeId: globalConfig.value.map_type || 'roadmap',
            gestureHandling: props.readOnly ? 'none' : 'auto',
        });
    }

    function setMapZoom(zoom) {
        if (mapInstance.value) mapInstance.value.setZoom(zoom);
    }

    // Defer init until the map element is actually visible to the user.
    useElementVisibility(mapEl, () => initMap());

    onBeforeUnmount(() => {
        if (mapProvider.value === 'osm' && mapInstance.value) {
            try { mapInstance.value.remove(); }
            catch (err) { console.warn('[useMapInit] map teardown failed:', err?.message); }
        }
        mapInstance.value = null;
        mapReady.value = false;
    });

    return { mapInstance, mapReady, setMapZoom };
}
