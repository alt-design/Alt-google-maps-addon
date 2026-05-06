import { watch, onBeforeUnmount, nextTick } from 'vue';
import { getLeaflet } from './useMapLoader.js';

/**
 * useSingleMarkerRender — render and interact with the single marker on the
 * main map. Subscribes to `mapReady` from useMapInit; when ready, places the
 * marker (if any), attaches click/drag handlers, and starts the lat/lng/zoom
 * sync watchers.
 *
 * Responsibility: bridge between single-marker data and the map DOM. Contains
 * all the provider-specific marker DOM code for single mode.
 *
 * Wires up `onMapPick` internally (map click/drag → update internalValue +
 * reverse-geocode → update again). Returns helpers used by the search flow
 * (placeOrMoveMarker, setMapZoom).
 */
export function useSingleMarkerRender({ props, map, single, config, markerSearch }) {
    const { mapProvider, defaultZoom } = config;
    const { internalValue, hasLocation, update } = single;
    const { mapInstance, mapReady, setMapZoom } = map;

    let markerInstance = null;
    let _fromMapInteraction = false;

    // ─── Marker placement ────────────────────────────────────────────────────
    function placeOrMoveLeafletMarker(lat, lng) {
        if (!markerInstance) {
            const L = getLeaflet();
            markerInstance = L.marker([lat, lng], { draggable: !props.readOnly }).addTo(mapInstance.value);
            if (!props.readOnly) {
                markerInstance.on('dragend', (e) => {
                    const p = e.target.getLatLng();
                    onMapPick(p.lat, p.lng);
                });
            }
        } else {
            markerInstance.setLatLng([lat, lng]);
        }
    }

    function placeOrMoveGoogleMarker(lat, lng) {
        if (!markerInstance) {
            markerInstance = new window.google.maps.Marker({
                position: { lat, lng },
                map: mapInstance.value,
                draggable: !props.readOnly,
            });
            if (!props.readOnly) {
                markerInstance.addListener('dragend', () => {
                    const pos = markerInstance.getPosition();
                    onMapPick(pos.lat(), pos.lng());
                });
            }
        } else {
            markerInstance.setPosition({ lat, lng });
        }
    }

    function placeOrMoveMarker(lat, lng) {
        if (mapProvider.value === 'osm') placeOrMoveLeafletMarker(lat, lng);
        else placeOrMoveGoogleMarker(lat, lng);
    }

    /**
     * Place the marker AND pan the map to it. Used by the search flow on the
     * very first search (where moveMarker's watcher path is a no-op because
     * markerInstance doesn't exist yet).
     */
    function placeAndPan(lat, lng) {
        placeOrMoveMarker(lat, lng);
        if (!mapInstance.value) return;
        if (mapProvider.value === 'osm') mapInstance.value.panTo([lat, lng]);
        else mapInstance.value.panTo({ lat, lng });
    }

    function moveMarker(lat, lng) {
        if (!mapInstance.value || !markerInstance) return;
        if (mapProvider.value === 'osm') {
            markerInstance.setLatLng([lat, lng]);
            mapInstance.value.panTo([lat, lng]);
        } else {
            const pos = new window.google.maps.LatLng(lat, lng);
            markerInstance.setPosition(pos);
            mapInstance.value.panTo(pos);
        }
    }

    // ─── User picks (map click / marker drag) ─────────────────────────────────
    async function onMapPick(lat, lng) {
        _fromMapInteraction = true;
        update({ ...internalValue.value, lat, lng });
        await nextTick();
        _fromMapInteraction = false;

        const address = await markerSearch.reverseGeocode(lat, lng);
        if (address) update({ ...internalValue.value, lat, lng, address });
    }

    // ─── Setup once the map is ready ──────────────────────────────────────────
    function setup() {
        if (!mapInstance.value) return;

        const lat = internalValue.value.lat ?? (config.defaultLat.value ?? 0);
        const lng = internalValue.value.lng ?? (config.defaultLng.value ?? 0);

        if (hasLocation.value) {
            if (mapProvider.value === 'osm') placeOrMoveLeafletMarker(lat, lng);
            else placeOrMoveGoogleMarker(lat, lng);

            // Re-center + zoom to the saved location
            const zoom = internalValue.value.zoom || defaultZoom.value;
            if (mapProvider.value === 'osm') {
                mapInstance.value.setView([lat, lng], zoom);
            } else {
                mapInstance.value.setCenter({ lat, lng });
                mapInstance.value.setZoom(zoom);
            }
        }

        if (props.readOnly) return;

        if (mapProvider.value === 'osm') {
            mapInstance.value.on('click', (e) => {
                placeOrMoveLeafletMarker(e.latlng.lat, e.latlng.lng);
                onMapPick(e.latlng.lat, e.latlng.lng);
            });
        } else {
            mapInstance.value.addListener('click', (e) => {
                placeOrMoveGoogleMarker(e.latLng.lat(), e.latLng.lng());
                onMapPick(e.latLng.lat(), e.latLng.lng());
            });
        }
    }

    watch(mapReady, (ready) => { if (ready) setup(); }, { immediate: true });

    // ─── Reactive sync: data → map ────────────────────────────────────────────
    watch(
        () => [internalValue.value.lat, internalValue.value.lng],
        ([lat, lng]) => {
            if (!mapReady.value || lat == null || lng == null || _fromMapInteraction) return;
            moveMarker(lat, lng);
        }
    );

    watch(
        () => internalValue.value.zoom,
        (zoom) => {
            if (!mapReady.value || !zoom) return;
            setMapZoom(zoom);
        }
    );

    // ─── Cleanup ──────────────────────────────────────────────────────────────
    onBeforeUnmount(() => {
        if (markerInstance) {
            try {
                if (mapProvider.value === 'osm') markerInstance.remove?.();
                else markerInstance.setMap?.(null);
            } catch (err) {
                console.warn('[useSingleMarkerRender] marker teardown failed:', err?.message);
            }
            markerInstance = null;
        }
    });

    return {
        placeOrMoveMarker,
        placeAndPan,
        moveMarker,
        onMapPick,
    };
}
