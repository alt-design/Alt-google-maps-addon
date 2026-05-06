import { watch, onBeforeUnmount } from 'vue';
import { MarkerClusterer } from '@googlemaps/markerclusterer';
import { getLeaflet } from './useMapLoader.js';

/**
 * useMultiMarkerRender — render the full marker set on the main map (multi mode).
 *
 * Owns the array of provider-specific marker instances and the optional
 * MarkerClusterer (Google only). Subscribes to mapReady; when ready, attaches
 * the click-to-add handler and does the first refresh.
 *
 * `refreshMultiMapMarkers` is exposed so that data mutations (handled by
 * useMultiMarkerData) can request a re-render; callers wire it into `cbs.refreshMap`.
 */
export function useMultiMarkerRender({ props, map, multi, config }) {
    const { mapProvider, defaultZoom } = config;
    const { mapInstance, mapReady } = map;
    const { markersData, zoomOverride, addMarkerAtPosition, onMultiMapPick } = multi;

    let multiMarkerInstances = [];
    let markerClustererInstance = null;

    // ─── Refresh ──────────────────────────────────────────────────────────────
    function refreshMultiMapMarkers() {
        if (!mapInstance.value) return;
        if (mapProvider.value === 'osm') refreshLeaflet();
        else refreshGoogle();
    }

    function refreshLeaflet() {
        const L = getLeaflet();
        multiMarkerInstances.forEach((m) => { try { m.remove(); } catch {} });
        multiMarkerInstances = [];

        const valid = markersData.value.filter(
            (m) => m.lat != null && m.lng != null && m.enabled !== false
        );
        if (!valid.length) return;

        valid.forEach((m) => {
            const marker = L.marker([m.lat, m.lng], { draggable: !props.readOnly })
                .addTo(mapInstance.value);
            if (!props.readOnly) {
                const id = m._id;
                marker.on('dragend', (e) => {
                    const p = e.target.getLatLng();
                    onMultiMapPick(id, p.lat, p.lng);
                });
            }
            multiMarkerInstances.push(marker);
        });

        if (valid.length === 1) {
            mapInstance.value.setView(
                [valid[0].lat, valid[0].lng],
                zoomOverride.value || defaultZoom.value
            );
        } else {
            const group = L.featureGroup(multiMarkerInstances);
            try { mapInstance.value.fitBounds(group.getBounds(), { padding: [40, 40] }); } catch {}
            if (zoomOverride.value) mapInstance.value.setZoom(zoomOverride.value);
        }
    }

    function refreshGoogle() {
        // Tear down clusterer if any
        if (markerClustererInstance) {
            try { markerClustererInstance.clearMarkers(); markerClustererInstance.setMap(null); }
            catch (err) { console.warn('[useMultiMarkerRender] clusterer teardown failed:', err?.message); }
            markerClustererInstance = null;
        }
        multiMarkerInstances.forEach((m) => { try { m.setMap(null); } catch {} });
        multiMarkerInstances = [];

        const valid = markersData.value.filter(
            (m) => m.lat != null && m.lng != null && m.enabled !== false
        );
        if (!valid.length) return;

        const clustering = !!props.config.enable_clustering;
        const bounds = new window.google.maps.LatLngBounds();

        valid.forEach((m) => {
            const marker = new window.google.maps.Marker({
                position: { lat: m.lat, lng: m.lng },
                // When clustering, the clusterer attaches markers — we set null here
                map: clustering ? null : mapInstance.value,
                draggable: !props.readOnly,
            });
            if (!props.readOnly) {
                const id = m._id;
                marker.addListener('dragend', () => {
                    const pos = marker.getPosition();
                    onMultiMapPick(id, pos.lat(), pos.lng());
                });
            }
            bounds.extend({ lat: m.lat, lng: m.lng });
            multiMarkerInstances.push(marker);
        });

        if (clustering) {
            markerClustererInstance = new MarkerClusterer({
                map: mapInstance.value,
                markers: multiMarkerInstances,
            });
        }

        if (valid.length === 1) {
            mapInstance.value.setCenter({ lat: valid[0].lat, lng: valid[0].lng });
            mapInstance.value.setZoom(zoomOverride.value || defaultZoom.value);
        } else {
            mapInstance.value.fitBounds(bounds);
            if (zoomOverride.value) mapInstance.value.setZoom(zoomOverride.value);
        }
    }

    // ─── Setup once map is ready ──────────────────────────────────────────────
    function setup() {
        if (!mapInstance.value) return;
        refreshMultiMapMarkers();
        if (props.readOnly) return;

        if (mapProvider.value === 'osm') {
            mapInstance.value.on('click', (e) => addMarkerAtPosition(e.latlng.lat, e.latlng.lng));
        } else {
            mapInstance.value.addListener('click', (e) =>
                addMarkerAtPosition(e.latLng.lat(), e.latLng.lng())
            );
        }
    }

    watch(mapReady, (ready) => { if (ready) setup(); }, { immediate: true });

    // ─── Cleanup ──────────────────────────────────────────────────────────────
    onBeforeUnmount(() => {
        if (markerClustererInstance) {
            try {
                markerClustererInstance.clearMarkers();
                markerClustererInstance.setMap(null);
            } catch (err) {
                console.warn('[useMultiMarkerRender] clusterer teardown failed:', err?.message);
            }
            markerClustererInstance = null;
        }
        multiMarkerInstances.forEach((m) => {
            try {
                if (mapProvider.value === 'osm') m.remove();
                else m.setMap(null);
            } catch (err) {
                console.warn('[useMultiMarkerRender] marker teardown failed:', err?.message);
            }
        });
        multiMarkerInstances = [];
    });

    return { refreshMultiMapMarkers };
}
