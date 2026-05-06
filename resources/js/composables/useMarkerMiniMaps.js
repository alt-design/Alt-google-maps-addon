import { ref, nextTick, onBeforeUnmount } from 'vue';
import { loadGoogleMapsScript, loadLeaflet, getLeaflet } from './useMapLoader.js';

/**
 * useMarkerMiniMaps — per-marker mini-maps inside MarkerListRow.
 *
 * Each marker row in multi mode can be expanded to show its own small map for
 * picking/dragging the location. This composable owns the lifecycle of all
 * those mini-map instances.
 *
 * The DOM elements are reported via `setMarkerMapRef(id, el)` (called from a
 * Vue `:ref` callback in the parent template). Mutation of marker data on
 * pick is delegated to `onPick` (typically from useMultiMarkerData).
 */
export function useMarkerMiniMaps({ props, config, multi, onPick }) {
    const { mapProvider, globalConfig, defaultZoom, defaultLat, defaultLng } = config;
    const { markersData } = multi;

    const markerMapInstances = {}; // _id → mapInstance
    const markerMapMarkers = {};   // _id → markerInstance
    let markerMapRefs = {};        // _id → DOM el
    const markerMapType = ref({}); // _id → 'roadmap' | 'satellite'

    function setMarkerMapRef(id, el) {
        markerMapRefs[id] = el;
    }

    async function initMarkerMiniMap(id) {
        await nextTick();
        const el = markerMapRefs[id];
        if (!el || markerMapInstances[id]) return;

        const m = markersData.value.find((x) => x._id === id);
        if (!m) return;

        const lat = m.lat ?? (defaultLat.value ?? 0);
        const lng = m.lng ?? (defaultLng.value ?? 0);
        const zoom = m.lat != null ? (m.zoom || defaultZoom.value) : 2;

        if (mapProvider.value === 'osm') {
            const L = await loadLeaflet();
            const mini = L.map(el, { center: [lat, lng], zoom });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 21,
            }).addTo(mini);
            markerMapInstances[id] = mini;

            if (m.lat != null) {
                const pin = L.marker([lat, lng], { draggable: !props.readOnly }).addTo(mini);
                markerMapMarkers[id] = pin;
                if (!props.readOnly) {
                    pin.on('dragend', (e) => {
                        const p = e.target.getLatLng();
                        onPick(id, p.lat, p.lng);
                    });
                }
            }
            if (!props.readOnly) {
                mini.on('click', (e) => {
                    placeOrMovePin(id, e.latlng.lat, e.latlng.lng);
                    onPick(id, e.latlng.lat, e.latlng.lng);
                });
            }
        } else {
            await loadGoogleMapsScript(globalConfig.value.api_key);
            const mapType = markerMapType.value[id] || 'roadmap';
            const mini = new window.google.maps.Map(el, {
                center: { lat, lng },
                zoom,
                mapTypeId: mapType,
                gestureHandling: props.readOnly ? 'none' : 'auto',
            });
            markerMapInstances[id] = mini;

            if (m.lat != null) {
                const pin = new window.google.maps.Marker({
                    position: { lat, lng },
                    map: mini,
                    draggable: !props.readOnly,
                });
                markerMapMarkers[id] = pin;
                if (!props.readOnly) {
                    pin.addListener('dragend', () => {
                        const pos = pin.getPosition();
                        onPick(id, pos.lat(), pos.lng());
                    });
                }
            }
            if (!props.readOnly) {
                mini.addListener('click', (e) => {
                    placeOrMovePin(id, e.latLng.lat(), e.latLng.lng());
                    onPick(id, e.latLng.lat(), e.latLng.lng());
                });
            }
        }
    }

    function destroyMarkerMiniMap(id) {
        const mini = markerMapInstances[id];
        if (mini) {
            try {
                if (mapProvider.value === 'osm') mini.remove();
                else if (markerMapMarkers[id]) markerMapMarkers[id].setMap(null);
            } catch (err) {
                console.warn('[useMarkerMiniMaps] mini-map teardown failed:', err?.message);
            }
        }
        delete markerMapInstances[id];
        delete markerMapMarkers[id];
        delete markerMapRefs[id];
    }

    function placeOrMovePin(id, lat, lng) {
        if (mapProvider.value === 'osm') {
            const L = getLeaflet();
            if (!markerMapMarkers[id]) {
                const pin = L.marker([lat, lng], { draggable: !props.readOnly })
                    .addTo(markerMapInstances[id]);
                markerMapMarkers[id] = pin;
                if (!props.readOnly) {
                    pin.on('dragend', (e) => {
                        const p = e.target.getLatLng();
                        onPick(id, p.lat, p.lng);
                    });
                }
            } else {
                markerMapMarkers[id].setLatLng([lat, lng]);
            }
            markerMapInstances[id].panTo([lat, lng]);
        } else {
            if (!markerMapMarkers[id]) {
                const pin = new window.google.maps.Marker({
                    position: { lat, lng },
                    map: markerMapInstances[id],
                    draggable: !props.readOnly,
                });
                markerMapMarkers[id] = pin;
                if (!props.readOnly) {
                    pin.addListener('dragend', () => {
                        const pos = pin.getPosition();
                        onPick(id, pos.lat(), pos.lng());
                    });
                }
            } else {
                markerMapMarkers[id].setPosition({ lat, lng });
            }
            markerMapInstances[id].panTo({ lat, lng });
        }
    }

    function syncMarkerMiniMapPin(id) {
        const m = markersData.value.find((x) => x._id === id);
        if (!m || m.lat == null || !markerMapInstances[id]) return;
        placeOrMovePin(id, m.lat, m.lng);
        if (m.zoom) markerMapInstances[id].setZoom(m.zoom);
    }

    function setMarkerMapType(id, type) {
        markerMapType.value = { ...markerMapType.value, [id]: type };
        const mini = markerMapInstances[id];
        if (mini && window.google?.maps) {
            mini.setMapTypeId(type);
        }
    }

    function destroyAllMiniMaps() {
        Object.keys(markerMapInstances).forEach((id) => destroyMarkerMiniMap(id));
        markerMapRefs = {};
    }

    onBeforeUnmount(() => {
        destroyAllMiniMaps();
    });

    return {
        markerMapType,
        setMarkerMapRef,
        initMarkerMiniMap,
        destroyMarkerMiniMap,
        syncMarkerMiniMapPin,
        setMarkerMapType,
        destroyAllMiniMaps,
    };
}
