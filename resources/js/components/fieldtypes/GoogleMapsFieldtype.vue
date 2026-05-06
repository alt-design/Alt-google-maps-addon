<template>
    <div class="flex flex-col gap-3 p-3 bg-gray-100 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl">

        <!-- ══════════════ SINGLE-MARKER MODE ══════════════ -->
        <template v-if="!isMulti">

        <!-- Location header -->
        <div class="flex items-center gap-2 min-w-0">
            <span class="gmf-pin-badge">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 2a7 7 0 0 1 7 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 0 1 7-7Zm0 4.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z"/>
                </svg>
            </span>
            <span v-if="internalValue.address" class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">
                {{ internalValue.address }}
            </span>
            <span v-else-if="hasLocation" class="text-sm font-medium text-gray-600 dark:text-gray-300 truncate font-mono">
                {{ internalValue.lat }}, {{ internalValue.lng }}
            </span>
            <span v-else class="text-sm italic text-gray-400 dark:text-gray-500">
                No location set
            </span>
        </div>

        <!-- Search bar -->
        <div v-if="!props.readOnly" class="flex flex-col gap-1">
            <div class="flex gap-2">
                <input
                    v-model="searchQuery"
                    type="text"
                    class="gmf-input flex-1"
                    placeholder="Search for a location…"
                    :disabled="isSearching"
                    @keydown.enter.prevent="search"
                />
                <button
                    type="button"
                    class="gmf-search-btn"
                    :disabled="isSearching || !searchQuery.trim()"
                    @click.prevent="search"
                >
                    <svg v-if="isSearching" xmlns="http://www.w3.org/2000/svg" class="size-4 gmf-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    {{ isSearching ? 'Searching…' : 'Search' }}
                </button>
            </div>
            <p v-if="searchError" class="text-xs text-red-500 dark:text-red-400">{{ searchError }}</p>
        </div>

        <!-- Map container -->
        <div
            ref="mapEl"
            class="gmf-map rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600"
            :style="{ height: mapHeight + 'px' }"
        ></div>

        <!-- Coordinate inputs -->
        <div class="grid grid-cols-3 gap-2">
            <div class="gmf-field">
                <label class="text-2xs uppercase font-semibold text-gray-500">Lat</label>
                <input
                    type="number"
                    step="any"
                    class="gmf-input"
                    placeholder="0.000000"
                    :value="internalValue.lat"
                    :disabled="props.readOnly"
                    @change="updateCoord('lat', $event.target.value)"
                />
            </div>
            <div class="gmf-field">
                <label class="text-2xs uppercase font-semibold text-gray-500">Lng</label>
                <input
                    type="number"
                    step="any"
                    class="gmf-input"
                    placeholder="0.000000"
                    :value="internalValue.lng"
                    :disabled="props.readOnly"
                    @change="updateCoord('lng', $event.target.value)"
                />
            </div>
            <div class="gmf-field">
                <label class="text-2xs uppercase font-semibold text-gray-500">Zoom</label>
                <input
                    type="number"
                    min="1"
                    max="21"
                    class="gmf-input"
                    placeholder="13"
                    :value="internalValue.zoom"
                    :disabled="props.readOnly"
                    @change="updateCoord('zoom', $event.target.value)"
                />
            </div>
        </div>

        <!-- Infobox inputs -->
        <template v-if="hasLocation && !props.readOnly">
            <div class="gmf-field">
                <label class="text-2xs uppercase font-semibold text-gray-500">Infobox Title</label>
                <input
                    type="text"
                    class="gmf-input"
                    placeholder="Leave empty for no popup"
                    :value="internalValue.infobox_title || ''"
                    @input="update({ ...internalValue, infobox_title: $event.target.value })"
                />
            </div>
            <div class="gmf-field">
                <label class="text-2xs uppercase font-semibold text-gray-500">
                    Infobox Content
                    <span class="font-normal normal-case text-gray-400 ml-1">HTML supported</span>
                </label>
                <textarea
                    class="gmf-input"
                    placeholder="Leave empty for no popup"
                    rows="3"
                    :value="internalValue.infobox_content || ''"
                    @input="update({ ...internalValue, infobox_content: $event.target.value })"
                ></textarea>
            </div>
            <div class="gmf-field">
                <label class="text-2xs uppercase font-semibold text-gray-500">
                    Marker Icon
                    <span class="font-normal normal-case text-gray-400 ml-1">Leave empty to use the field / global default</span>
                </label>
                <assets-fieldtype
                    v-if="markerAssetMeta.container"
                    :value="markerAssetIds"
                    :config="markerAssetConfig"
                    :meta="markerAssetMeta"
                    handle="marker_icon"
                    @input="onMarkerAssetUpdate"
                    @update:value="onMarkerAssetUpdate"
                />
                <p v-else class="text-xs text-gray-400 italic mt-1">No asset containers configured.</p>
            </div>
            <div v-if="internalValue.marker_icon_url" class="grid grid-cols-2 gap-2">
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Icon Width (px)</label>
                    <input
                        type="number"
                        min="1"
                        class="gmf-input"
                        placeholder="40"
                        :value="internalValue.marker_icon_width || ''"
                        @change="update({ ...internalValue, marker_icon_width: parseInt($event.target.value) || 0 })"
                    />
                </div>
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Icon Height (px)</label>
                    <input
                        type="number"
                        min="1"
                        class="gmf-input"
                        placeholder="40"
                        :value="internalValue.marker_icon_height || ''"
                        @change="update({ ...internalValue, marker_icon_height: parseInt($event.target.value) || 0 })"
                    />
                </div>
            </div>
        </template>

        <!-- Footer: clear (single) -->
        <div v-if="hasLocation && !props.readOnly" class="flex justify-end">
            <button type="button" class="gmf-clear-btn" @click.prevent="clearLocation">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                </svg>
                Clear location
            </button>
        </div>

        </template><!-- /single -->

        <!-- ══════════════ MULTI-MARKER MODE ══════════════ -->
        <template v-else>

            <!-- ── Header: count + global toggle ── -->
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="gmf-pin-badge">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2a7 7 0 0 1 7 7c0 5.25-7 13-7 13S5 14.25 5 9a7 7 0 0 1 7-7Zm0 4.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5Z"/>
                        </svg>
                    </span>
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                        {{ markersData.length }} marker{{ markersData.length !== 1 ? 's' : '' }}
                    </span>
                    <span v-if="!props.readOnly" class="text-xs text-gray-400">· drag rows to reorder</span>
                </div>
                <button v-if="!props.readOnly" type="button"
                    class="gmf-toggle-btn"
                    :class="fieldEnabled ? 'gmf-toggle-on' : 'gmf-toggle-off'"
                    :title="fieldEnabled ? 'Disable field' : 'Enable field'"
                    @click.prevent="fieldEnabled = !fieldEnabled">
                    <span class="gmf-toggle-knob"></span>
                </button>
            </div>

            <!-- ── Tab switcher: Individual / Edit all ── -->
            <div v-if="!props.readOnly" class="flex gap-1 p-1 bg-gray-200 dark:bg-gray-800 rounded-lg">
                <button type="button"
                    class="gmf-tab-btn flex-1"
                    :class="editMode === 'individual' ? 'gmf-tab-active' : 'gmf-tab-inactive'"
                    @click.prevent="editMode = 'individual'">
                    Individual
                </button>
                <button type="button"
                    class="gmf-tab-btn flex-1"
                    :class="editMode === 'bulk' ? 'gmf-tab-active gmf-tab-bulk-active' : 'gmf-tab-inactive'"
                    @click.prevent="editMode = 'bulk'">
                    Edit all
                </button>
            </div>

            <!-- ── Apply feedback toast (outside panels so it persists after tab switch) ── -->
            <div v-if="bulkToast" class="gmf-toast">✓ {{ bulkToast }}</div>

            <!-- ══ EDIT ALL PANEL ══ -->
            <BulkEditPanel
                v-if="editMode === 'bulk'"
                :map-provider="mapProvider"
                :map-height="mapHeight"
                :default-lat="defaultLat"
                :default-lng="defaultLng"
                :api-key="globalConfig.api_key"
                :asset-meta="markerAssetMeta"
                :asset-config="markerAssetConfig"
                :default-zoom="defaultZoom"
                @apply="onBulkApply"
            />

            <!-- ══ MARKER LIST (always visible) ══ -->
            <sortable-list
                v-model="markersData"
                item-class="gmf-marker-item"
                handle-class="gmf-marker-handle"
                :distance="5"
                :mirror="false"
                :vertical="true"
                @update:model-value="onMarkersReordered"
            >
                <div class="flex flex-col gap-2">
                    <MarkerListRow
                        v-for="(item, index) in markersData"
                        :key="item._id"
                        :item="item"
                        :expanded="expandedMarker === item._id"
                        :read-only="props.readOnly"
                        :map-provider="mapProvider"
                        :map-height="mapHeight"
                        :asset-meta="markerAssetMeta"
                        :asset-config="markerAssetConfig"
                        :asset-ids="markerAssetIdsMap[item._id] || []"
                        :is-searching="markerSearching[item._id] || false"
                        :search-error="markerSearchErrors[item._id] || ''"
                        :map-type="markerMapType[item._id] || 'roadmap'"
                        @toggle-expanded="toggleExpanded(item._id)"
                        @toggle-enabled="toggleMarkerEnabled(item._id)"
                        @remove="removeMarker(item._id)"
                        @update-field="(field, value) => updateMarkerField(item._id, field, value)"
                        @update-coord="(field, raw) => updateMarkerCoord(item._id, field, raw)"
                        @clear-location="clearMarkerLocation(item._id)"
                        @search="(query) => searchMarker(item._id, query)"
                        @asset-update="(ids) => onMultiMarkerAssetUpdate(item._id, ids)"
                        @set-map-type="(type) => setMarkerMapType(item._id, type)"
                        @map-ref="(el) => setMarkerMapRef(item._id, el)"
                    />
                </div><!-- /v-for wrapper -->
            </sortable-list>

            <!-- ── Footer ── -->
            <div v-if="!props.readOnly" class="flex items-center gap-3 pt-3 border-t border-gray-200 dark:border-gray-700">
                <!-- Add marker (left) -->
                <button type="button" class="gmf-add-btn flex-shrink-0" @click.prevent="addMarker">
                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 5v14M5 12h14"/>
                    </svg>
                    Add Marker
                </button>

                <!-- Zoom (center) -->
                <div class="flex flex-1 self-stretch items-center justify-center gap-0 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                    <span class="flex items-center gap-1.5 px-3 bg-gray-50 dark:bg-gray-700 self-stretch border-r border-gray-200 dark:border-gray-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5 text-gray-400 dark:text-gray-400 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35M11 8v6M8 11h6"/>
                        </svg>
                        <span class="text-2xs uppercase font-semibold text-gray-400 dark:text-gray-500 whitespace-nowrap">Zoom</span>
                    </span>
                    <input type="number" min="1" max="21" class="gmf-input flex-1 !border-0 !ring-0 text-center bg-white dark:bg-gray-800" style="max-width:5rem;" placeholder="auto"
                        :value="zoomOverride || ''"
                        :disabled="props.readOnly"
                        @change="onZoomOverrideChange($event.target.value)" />
                    <span class="flex items-center self-stretch px-3 bg-gray-50 dark:bg-gray-700 border-l border-gray-200 dark:border-gray-700 text-xs text-gray-400 dark:text-gray-500 whitespace-nowrap">auto-fits</span>
                </div>

                <!-- Right: clear data + clear all -->
                <div v-if="markersData.length > 0" class="flex items-center gap-2 flex-shrink-0">
                    <button type="button" class="gmf-clear-btn" @click.prevent="clearAllMarkersData">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/>
                            <line x1="4" y1="4" x2="20" y2="20"/>
                        </svg>
                        Clear all data
                    </button>
                    <button type="button" class="gmf-clear-btn" @click.prevent="clearAllMarkers">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                        </svg>
                        Clear all
                    </button>
                </div>
                <!-- Spacer to keep zoom centered when no markers -->
                <div v-else class="flex-shrink-0" style="width:5rem;"></div>
            </div>

        </template><!-- /multi -->

    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick, getCurrentInstance } from 'vue';

import MarkerListRow from './partials/MarkerListRow.vue';
import BulkEditPanel from './partials/BulkEditPanel.vue';

import { useFieldConfig }       from '../../composables/useFieldConfig.js';
import { useSingleMarker }      from '../../composables/useSingleMarker.js';
import { useMultiMarkerData }   from '../../composables/useMultiMarkerData.js';
import { useMarkerSearch }      from '../../composables/useMarkerSearch.js';
import { useAssetResolver }     from '../../composables/useAssetResolver.js';
import { useMapInit }           from '../../composables/useMapInit.js';
import { useSingleMarkerRender } from '../../composables/useSingleMarkerRender.js';
import { useMultiMarkerRender } from '../../composables/useMultiMarkerRender.js';
import { useMarkerMiniMaps }    from '../../composables/useMarkerMiniMaps.js';

const props = defineProps({
    value: { default: null },
    config: { type: Object, default: () => ({}) },
    handle: { type: String, default: '' },
    meta: { type: Object, default: () => ({}) },
    readOnly: { type: Boolean, default: false },
});
const emit = defineEmits(['update:value']);

const _instance = getCurrentInstance();
const _axios = _instance?.proxy?.$axios ?? null;

// ─── Layer 1: Config ──────────────────────────────────────────────────────────
const config = useFieldConfig(props);
const { isMulti, globalConfig, mapProvider, mapHeight, defaultZoom, defaultLat, defaultLng } = config;

// ─── Layer 3: Network ─────────────────────────────────────────────────────────
const markerSearch  = useMarkerSearch({ _axios, mapProvider });
const assetResolver = useAssetResolver({ _axios });

// ─── Layer 2: Data ────────────────────────────────────────────────────────────
const single = useSingleMarker(props, emit, config);
const { internalValue, hasLocation, markerAssetIds, update, updateCoord } = single;

// `cbs` is mutated below after every composable initialises. Functions inside
// useMultiMarkerData read these properties lazily (at user interaction time),
// not at setup time, so empty-at-creation is fine.
const cbs = {};
const multi = useMultiMarkerData(props, emit, config, cbs, markerSearch, assetResolver);
const {
    markersData, zoomOverride, fieldEnabled, editMode, expandedMarker,
    markerSearching, markerSearchErrors, markerAssetIdsMap, bulkToast,
    addMarker, removeMarker, toggleExpanded, toggleMarkerEnabled,
    updateMarkerField, updateMarkerCoord, clearMarkerLocation,
    onMarkersReordered, clearAllMarkersData, clearAllMarkers,
    onZoomOverrideChange, onBulkApply, searchMarker, onMultiMarkerAssetUpdate,
    onMarkerMiniMapPick,
} = multi;

// ─── Layer 4: Map rendering ───────────────────────────────────────────────────
const mapEl = ref(null);
const map = useMapInit({ props, mapEl, config });

const singleRender = !isMulti.value
    ? useSingleMarkerRender({ props, map, single, config, markerSearch })
    : null;

const multiRender = isMulti.value
    ? useMultiMarkerRender({ props, map, multi, config })
    : null;

const miniMaps = isMulti.value
    ? useMarkerMiniMaps({ props, config, multi, onPick: onMarkerMiniMapPick })
    : null;

// Surface mini-map helpers as top-level identifiers for template bindings.
const markerMapType   = miniMaps?.markerMapType   ?? ref({});
const setMarkerMapRef = miniMaps?.setMarkerMapRef ?? (() => {});
const setMarkerMapType= miniMaps?.setMarkerMapType?? (() => {});

// Wire the lazy callbacks now that every composable exists.
cbs.refreshMap         = multiRender?.refreshMultiMapMarkers ?? (() => {});
cbs.initMiniMap        = miniMaps?.initMarkerMiniMap         ?? (() => {});
cbs.destroyMiniMap     = miniMaps?.destroyMarkerMiniMap      ?? (() => {});
cbs.syncMiniMap        = miniMaps?.syncMarkerMiniMapPin      ?? (() => {});
cbs.destroyAllMiniMaps = miniMaps?.destroyAllMiniMaps        ?? (() => {});
cbs.setMapZoom         = map.setMapZoom;

// ─── Single-marker asset picker ───────────────────────────────────────────────
const markerAssetMeta = computed(() => ({
    container: props.meta?.containers?.[0] ?? null,
    data: null,
    columns: props.meta?.columns ?? [],
    dynamicFolder: null,
    rename_folder: null,
}));

const markerAssetConfig = {
    allow_uploads: true,
    max_files: 1,
    mode: 'list',
    show_filename: true,
    show_set_alt: false,
    folder: '/',
};

async function onMarkerAssetUpdate(ids) {
    markerAssetIds.value = ids ?? [];
    if (!ids?.length) {
        update({ ...internalValue.value, marker_icon_url: '', marker_asset_id: '' });
        return;
    }
    const url = await assetResolver.resolveUrl(ids[0]);
    if (url) update({ ...internalValue.value, marker_icon_url: url, marker_asset_id: ids[0] });
}

// ─── Single-marker search ─────────────────────────────────────────────────────
const searchQuery = ref('');
const isSearching = ref(false);
const searchError = ref('');
let _searchDebounceTimer = null;

watch(searchQuery, (q) => {
    clearTimeout(_searchDebounceTimer);
    if (!q.trim()) { searchError.value = ''; return; }
    _searchDebounceTimer = setTimeout(search, 500);
});

async function search() {
    clearTimeout(_searchDebounceTimer);
    const q = searchQuery.value.trim();
    if (!q || isSearching.value) return;
    isSearching.value = true;
    searchError.value = '';

    try {
        const { lat, lng, address } = await markerSearch.geocode(q);
        const currentZoom = Math.max(internalValue.value.zoom || defaultZoom.value, 12);
        update({ ...internalValue.value, lat, lng, address, zoom: currentZoom });
        await nextTick();
        // The zoom watcher handles zoom; placeAndPan ensures the marker exists
        // AND the map is centred — the lat/lng watcher's panTo path is a no-op
        // on the very first search because markerInstance doesn't exist yet.
        singleRender?.placeAndPan(lat, lng);
    } catch (err) {
        searchError.value = err?.userMessage || err?.message || 'Search failed.';
    } finally {
        isSearching.value = false;
    }
}

function clearLocation() {
    single.clearLocation(() => {
        searchQuery.value = '';
        searchError.value = '';
    });
}
</script>

<!--
    Styles for this component and its sub-components (MarkerListRow, BulkEditPanel)
    live in resources/css/cp.css (gmf-* prefix). Scoped styles cannot reach child
    component elements, so all styles are global with the gmf- namespace.
-->
