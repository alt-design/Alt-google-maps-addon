import { ref, nextTick } from 'vue';
import { wrapMarker as makeMarker, initMarkersFromProp } from './markerHelpers.js';

/**
 * useMultiMarkerData — owns the multi-marker state and all mutations.
 *
 * Responsibility: the markersData array and every operation that changes it,
 * including search-then-update and reverse-geocode-then-update flows. Network
 * calls are delegated via injected `markerSearch` / `assetResolver`. Map
 * rendering side-effects are delegated via the lazily-populated `cbs` object.
 *
 * @param {object} props
 * @param {(event: string, payload: any) => void} emit
 * @param {object} config         - From useFieldConfig (defaultZoom, ...)
 * @param {object} cbs            - Map/render callbacks (mutated by parent
 *                                  AFTER all composables initialise; read
 *                                  lazily inside handlers).
 *                                  Keys: refreshMap, initMiniMap, destroyMiniMap,
 *                                        syncMiniMap, destroyAllMiniMaps, setMapZoom
 * @param {object} markerSearch   - From useMarkerSearch ({geocode, reverseGeocode})
 * @param {object} assetResolver  - From useAssetResolver ({resolveUrl})
 */
export function useMultiMarkerData(props, emit, config, cbs, markerSearch, assetResolver) {
    const { defaultZoom } = config;

    function wrapMarker(m) {
        return makeMarker(m, defaultZoom.value);
    }

    // ─── State ────────────────────────────────────────────────────────────────
    const markersData = ref(initMarkersFromProp(props.value, defaultZoom.value));
    const zoomOverride = ref(props.value?.zoom_override != null ? props.value.zoom_override : null);
    const fieldEnabled = ref(true);
    const editMode = ref('individual'); // 'individual' | 'bulk'
    const expandedMarker = ref(null);

    // Per-marker UI state (for MarkerListRow)
    const markerSearching = ref({});
    const markerSearchErrors = ref({});
    const markerAssetIdsMap = ref({});

    // Bulk-apply success toast — kept here so it survives a tab switch back
    const bulkToast = ref('');
    let _bulkToastTimer = null;

    // Initialise asset id map from any markers that already have an asset
    markersData.value.forEach((m) => {
        if (m.marker_asset_id) markerAssetIdsMap.value[m._id] = [m.marker_asset_id];
    });

    // ─── Sync to parent ───────────────────────────────────────────────────────
    function emitMulti() {
        emit('update:value', {
            zoom_override: zoomOverride.value,
            markers: markersData.value.map(({ _id, ...rest }) => rest),
        });
    }

    // ─── CRUD ─────────────────────────────────────────────────────────────────
    function addMarker() {
        const m = wrapMarker({});
        markersData.value = [...markersData.value, m];
        expandedMarker.value = m._id;
        emitMulti();
        nextTick(() => {
            cbs.refreshMap?.();
            cbs.initMiniMap?.(m._id);
        });
    }

    function removeMarker(id) {
        if (!window.confirm('Are you sure you want to remove this marker?')) return;
        cbs.destroyMiniMap?.(id);
        markersData.value = markersData.value.filter((m) => m._id !== id);
        if (expandedMarker.value === id) expandedMarker.value = null;
        delete markerAssetIdsMap.value[id];
        delete markerSearching.value[id];
        delete markerSearchErrors.value[id];
        emitMulti();
        nextTick(() => cbs.refreshMap?.());
    }

    function toggleExpanded(id) {
        const wasExpanded = expandedMarker.value === id;
        expandedMarker.value = wasExpanded ? null : id;
        if (!wasExpanded) {
            nextTick(() => cbs.initMiniMap?.(id));
        } else {
            cbs.destroyMiniMap?.(id);
        }
    }

    function toggleMarkerEnabled(id) {
        markersData.value = markersData.value.map((m) =>
            m._id === id ? { ...m, enabled: m.enabled === false ? true : false } : m
        );
        emitMulti();
        nextTick(() => cbs.refreshMap?.());
    }

    function updateMarkerField(id, field, value) {
        markersData.value = markersData.value.map((m) =>
            m._id === id ? { ...m, [field]: value } : m
        );
        emitMulti();
    }

    function updateMarkerCoord(id, field, rawValue) {
        const num = field === 'zoom' ? parseInt(rawValue) : parseFloat(rawValue);
        if (isNaN(num)) return;
        markersData.value = markersData.value.map((m) =>
            m._id === id ? { ...m, [field]: num } : m
        );
        emitMulti();
        nextTick(() => {
            cbs.refreshMap?.();
            cbs.syncMiniMap?.(id);
        });
    }

    function clearMarkerLocation(id) {
        if (!window.confirm('Are you sure you want to clear this location?')) return;
        markersData.value = markersData.value.map((m) =>
            m._id === id
                ? { ...m, lat: null, lng: null, address: '', zoom: defaultZoom.value }
                : m
        );
        emitMulti();
        nextTick(() => {
            cbs.refreshMap?.();
            cbs.syncMiniMap?.(id);
        });
    }

    function onMarkersReordered(newList) {
        markersData.value = newList;
        emitMulti();
    }

    function clearAllMarkersData() {
        if (
            !window.confirm(
                `Are you sure you want to clear all data from ${markersData.value.length} marker${
                    markersData.value.length !== 1 ? 's' : ''
                }? Locations, addresses and infoboxes will be reset.`
            )
        ) return;
        markersData.value = markersData.value.map((m) => ({
            ...m,
            lat: null, lng: null, address: '', zoom: defaultZoom.value,
            infobox_title: '', infobox_content: '',
            marker_icon_url: '', marker_icon_width: 0, marker_icon_height: 0, marker_asset_id: '',
        }));
        markerAssetIdsMap.value = {};
        markerSearching.value = {};
        markerSearchErrors.value = {};
        emitMulti();
        nextTick(() => {
            cbs.refreshMap?.();
            markersData.value.forEach((m) => cbs.syncMiniMap?.(m._id));
        });
    }

    function clearAllMarkers() {
        if (
            !window.confirm(
                `Are you sure you want to delete all ${markersData.value.length} marker${
                    markersData.value.length !== 1 ? 's' : ''
                }? This cannot be undone.`
            )
        ) return;
        cbs.destroyAllMiniMaps?.();
        markersData.value = [];
        expandedMarker.value = null;
        markerAssetIdsMap.value = {};
        markerSearching.value = {};
        markerSearchErrors.value = {};
        emitMulti();
        nextTick(() => cbs.refreshMap?.());
    }

    function onZoomOverrideChange(rawValue) {
        const num = parseInt(rawValue);
        zoomOverride.value = isNaN(num) ? null : num;
        emitMulti();
        if (zoomOverride.value) cbs.setMapZoom?.(zoomOverride.value);
    }

    // ─── Bulk apply ───────────────────────────────────────────────────────────
    function onBulkApply({ template: t, fields, scope }) {
        const targets = scope === 'active'
            ? markersData.value.filter((m) => m.enabled !== false)
            : [...markersData.value];
        const targetIds = new Set(targets.map((x) => x._id));

        markersData.value = markersData.value.map((m) => {
            if (!targetIds.has(m._id)) return m;
            const patch = {};
            if (fields.zoom && t.zoom) patch.zoom = t.zoom;
            if (fields.infobox) {
                patch.infobox_title = t.infobox_title;
                patch.infobox_content = t.infobox_content;
            }
            if (fields.icon) {
                patch.marker_icon_url = t.marker_icon_url;
                patch.marker_icon_width = t.marker_icon_width;
                patch.marker_icon_height = t.marker_icon_height;
                patch.marker_asset_id = t.marker_asset_id;
            }
            return { ...m, ...patch };
        });

        if (fields.icon) {
            const newMap = { ...markerAssetIdsMap.value };
            targets.forEach((m) => {
                newMap[m._id] = t.marker_asset_id ? [t.marker_asset_id] : [];
            });
            markerAssetIdsMap.value = newMap;
        }

        emitMulti();
        editMode.value = 'individual';

        if (_bulkToastTimer) clearTimeout(_bulkToastTimer);
        bulkToast.value = `✓ ${targets.length} marker${targets.length !== 1 ? 's' : ''} updated`;
        _bulkToastTimer = setTimeout(() => { bulkToast.value = ''; }, 4000);
    }

    // ─── Search (multi) ───────────────────────────────────────────────────────
    async function searchMarker(markerId, query) {
        const q = (query || '').trim();
        if (!q || markerSearching.value[markerId]) return;

        markerSearching.value = { ...markerSearching.value, [markerId]: true };
        markerSearchErrors.value = { ...markerSearchErrors.value, [markerId]: '' };

        try {
            const { lat, lng, address } = await markerSearch.geocode(q);
            markersData.value = markersData.value.map((m) =>
                m._id === markerId ? { ...m, lat, lng, address } : m
            );
            emitMulti();
            await nextTick();
            cbs.refreshMap?.();
        } catch (err) {
            markerSearchErrors.value = {
                ...markerSearchErrors.value,
                [markerId]: err?.userMessage || err?.message || 'Search failed.',
            };
        } finally {
            markerSearching.value = { ...markerSearching.value, [markerId]: false };
        }
    }

    // ─── Map-pick handlers (called by render layer on map click / drag) ───────
    async function addMarkerAtPosition(lat, lng) {
        const m = wrapMarker({ lat, lng });
        markersData.value = [...markersData.value, m];
        expandedMarker.value = m._id;
        emitMulti();
        await nextTick();
        cbs.refreshMap?.();

        const address = await markerSearch.reverseGeocode(lat, lng);
        if (address) {
            markersData.value = markersData.value.map((mk) =>
                mk._id === m._id ? { ...mk, address } : mk
            );
            emitMulti();
        }
    }

    async function onMultiMapPick(markerId, lat, lng) {
        markersData.value = markersData.value.map((m) =>
            m._id === markerId ? { ...m, lat, lng } : m
        );
        emitMulti();

        const address = await markerSearch.reverseGeocode(lat, lng);
        if (address) {
            markersData.value = markersData.value.map((m) =>
                m._id === markerId ? { ...m, address } : m
            );
            emitMulti();
        }
    }

    // ─── Mini-map pick (called by useMarkerMiniMaps) ──────────────────────────
    async function onMarkerMiniMapPick(id, lat, lng) {
        markersData.value = markersData.value.map((m) =>
            m._id === id ? { ...m, lat, lng } : m
        );
        emitMulti();
        cbs.refreshMap?.();

        const address = await markerSearch.reverseGeocode(lat, lng);
        if (address) {
            markersData.value = markersData.value.map((m) =>
                m._id === id ? { ...m, address } : m
            );
            emitMulti();
        }
    }

    // ─── Asset update (multi) ─────────────────────────────────────────────────
    async function onMultiMarkerAssetUpdate(markerId, ids) {
        markerAssetIdsMap.value = { ...markerAssetIdsMap.value, [markerId]: ids ?? [] };

        if (!ids?.length) {
            markersData.value = markersData.value.map((m) =>
                m._id === markerId ? { ...m, marker_icon_url: '', marker_asset_id: '' } : m
            );
            emitMulti();
            return;
        }

        const url = await assetResolver.resolveUrl(ids[0]);
        if (url) {
            markersData.value = markersData.value.map((m) =>
                m._id === markerId ? { ...m, marker_icon_url: url, marker_asset_id: ids[0] } : m
            );
            emitMulti();
        }
    }

    return {
        // State
        markersData,
        zoomOverride,
        fieldEnabled,
        editMode,
        expandedMarker,
        markerSearching,
        markerSearchErrors,
        markerAssetIdsMap,
        bulkToast,

        // Pure CRUD
        emitMulti,
        wrapMarker,
        addMarker,
        removeMarker,
        toggleExpanded,
        toggleMarkerEnabled,
        updateMarkerField,
        updateMarkerCoord,
        clearMarkerLocation,
        onMarkersReordered,
        clearAllMarkersData,
        clearAllMarkers,
        onZoomOverrideChange,
        onBulkApply,

        // Network-coordinated
        searchMarker,
        addMarkerAtPosition,
        onMultiMapPick,
        onMarkerMiniMapPick,
        onMultiMarkerAssetUpdate,
    };
}
