import { ref, computed } from 'vue';

/**
 * useSingleMarker — data layer for the single-marker mode.
 *
 * Owns the in-memory representation of the field value, exposes pure mutation
 * helpers, and emits 'update:value' on every change. No DOM, no map, no network.
 *
 * @param {object} props - Component props (.value, .config, .meta)
 * @param {(event: string, payload: any) => void} emit
 * @param {object} config - From useFieldConfig (defaultLat/Lng/Zoom)
 */
export function useSingleMarker(props, emit, config) {
    const { defaultLat, defaultLng, defaultZoom } = config;

    function makeDefault() {
        return {
            lat: defaultLat.value,
            lng: defaultLng.value,
            address: '',
            zoom: defaultZoom.value,
            infobox_title: '',
            infobox_content: '',
            marker_icon_url: '',
            marker_icon_width: 0,
            marker_icon_height: 0,
            marker_asset_id: '',
        };
    }

    const internalValue = ref(
        props.value && typeof props.value === 'object' && props.value.lat != null
            ? { ...props.value }
            : makeDefault()
    );

    const hasLocation = computed(
        () => internalValue.value.lat != null && internalValue.value.lng != null
    );

    /**
     * Asset picker state for the single marker icon.
     * Always reflects the currently selected asset id (or empty array).
     */
    const markerAssetIds = ref(
        internalValue.value.marker_asset_id ? [internalValue.value.marker_asset_id] : []
    );

    function update(value) {
        internalValue.value = { ...value };
        emit('update:value', value);
    }

    function updateCoord(field, rawValue) {
        const num = field === 'zoom' ? parseInt(rawValue) : parseFloat(rawValue);
        if (isNaN(num)) return;
        update({ ...internalValue.value, [field]: num });
    }

    function clearLocation(onAfter) {
        if (!window.confirm('Are you sure you want to clear this location?')) return;
        update({
            lat: null,
            lng: null,
            address: '',
            zoom: defaultZoom.value,
            infobox_title: '',
            infobox_content: '',
            marker_icon_url: '',
            marker_icon_width: 0,
            marker_icon_height: 0,
            marker_asset_id: '',
        });
        markerAssetIds.value = [];
        if (typeof onAfter === 'function') onAfter();
    }

    return {
        internalValue,
        hasLocation,
        markerAssetIds,
        update,
        updateCoord,
        clearLocation,
    };
}
