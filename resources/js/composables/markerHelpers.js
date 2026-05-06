/**
 * markerHelpers — pure helpers for marker objects.
 *
 * No state, no side effects. Used by useSingleMarker and useMultiMarkerData
 * to construct marker objects from raw input.
 */

let _nextId = 1;

/**
 * Generate a unique marker id (used as Vue :key and lookup handle).
 */
export function genId() {
    return 'gmf-' + (_nextId++) + '-' + Math.random().toString(36).slice(2, 7);
}

/**
 * Normalise a raw marker object: fill in missing fields with sensible defaults
 * and attach a stable internal _id.
 *
 * @param {object} m - Raw marker (may be partial or empty)
 * @param {number} defaultZoom - Field-level default zoom
 */
export function wrapMarker(m, defaultZoom) {
    return {
        _id: genId(),
        lat: m.lat ?? null,
        lng: m.lng ?? null,
        address: m.address ?? '',
        zoom: m.zoom ?? defaultZoom,
        infobox_title: m.infobox_title ?? '',
        infobox_content: m.infobox_content ?? '',
        marker_icon_url: m.marker_icon_url ?? '',
        marker_icon_width: m.marker_icon_width ?? 0,
        marker_icon_height: m.marker_icon_height ?? 0,
        marker_asset_id: m.marker_asset_id ?? '',
        enabled: m.enabled !== false,
    };
}

/**
 * Build the initial markersData array from props.value.
 * Returns [] when no markers are saved.
 */
export function initMarkersFromProp(rawValue, defaultZoom) {
    if (!rawValue || typeof rawValue !== 'object') return [];
    if (Array.isArray(rawValue.markers)) {
        return rawValue.markers.map((m) => wrapMarker(m, defaultZoom));
    }
    return [];
}
