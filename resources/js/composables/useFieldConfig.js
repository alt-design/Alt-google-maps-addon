import { computed } from 'vue';

/**
 * useFieldConfig — resolve the runtime configuration of a Google Maps field.
 *
 * Resolution order (highest priority first):
 *   1. Per-field config (props.config.*)
 *   2. CP-saved global config (Statamic.$config.googleMaps — populated server-side
 *      by ServiceProvider::bootAddon())
 *   3. Hard-coded fallbacks
 *
 * Pure function: takes props, returns reactive computeds. No side effects.
 */
export function useFieldConfig(props) {
    const isMulti = computed(() => !!props.config.enable_multiple);

    const globalConfig = computed(() => Statamic.$config.get('googleMaps') || {});

    const mapProvider = computed(() =>
        props.config.map_provider || globalConfig.value.map_provider || 'google'
    );

    const mapHeight = computed(() =>
        props.config.map_height || globalConfig.value.map_height || 420
    );

    const defaultZoom = computed(() =>
        parseInt(props.config.default_zoom) || globalConfig.value.defaults?.zoom || 13
    );

    const defaultLat = computed(() =>
        parseFloat(props.config.default_lat) || globalConfig.value.defaults?.lat || null
    );

    const defaultLng = computed(() =>
        parseFloat(props.config.default_lng) || globalConfig.value.defaults?.lng || null
    );

    return {
        isMulti,
        globalConfig,
        mapProvider,
        mapHeight,
        defaultZoom,
        defaultLat,
        defaultLng,
    };
}
