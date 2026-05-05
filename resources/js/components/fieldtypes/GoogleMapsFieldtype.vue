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
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick, getCurrentInstance } from 'vue';
import { MarkerClusterer } from '@googlemaps/markerclusterer';
import { loadGoogleMapsScript, loadLeaflet, getLeaflet } from '../../composables/useMapLoader.js';
import { postCp, googleGeocode, googleReverseGeocode, geocodeAddress, reverseGeocodeAddress } from '../../composables/useGeocoder.js';
import MarkerListRow from './partials/MarkerListRow.vue';
import BulkEditPanel from './partials/BulkEditPanel.vue';

const _instance = getCurrentInstance();
const _axios = _instance?.proxy?.$axios ?? null;

const props = defineProps({
    value: { default: null },
    config: { type: Object, default: () => ({}) },
    handle: { type: String, default: '' },
    meta: { type: Object, default: () => ({}) },
    readOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:value']);

// ─── Mode ──────────────────────────────────────────────────────────────────────
const isMulti = computed(() => !!props.config.enable_multiple);

// ─── Global config ─────────────────────────────────────────────────────────────
const globalConfig = computed(() => Statamic.$config.get('googleMaps') || {});
const mapProvider = computed(() =>
    props.config.map_provider || globalConfig.value.map_provider || 'google'
);
const mapHeight = computed(() => props.config.map_height || globalConfig.value.map_height || 420);
const defaultZoom = computed(() =>
    parseInt(props.config.default_zoom) || globalConfig.value.defaults?.zoom || 13
);
const defaultLat = computed(() =>
    parseFloat(props.config.default_lat) || globalConfig.value.defaults?.lat || null
);
const defaultLng = computed(() =>
    parseFloat(props.config.default_lng) || globalConfig.value.defaults?.lng || null
);

// ─── Single-marker state ───────────────────────────────────────────────────────
function makeSingleDefault() {
    return {
        lat: defaultLat.value, lng: defaultLng.value, address: '',
        zoom: defaultZoom.value, infobox_title: '', infobox_content: '',
        marker_icon_url: '', marker_icon_width: 0, marker_icon_height: 0, marker_asset_id: '',
    };
}

const internalValue = ref(
    props.value && typeof props.value === 'object' && props.value.lat != null
        ? { ...props.value }
        : makeSingleDefault()
);

const hasLocation = computed(() =>
    internalValue.value.lat != null && internalValue.value.lng != null
);

function update(value) {
    internalValue.value = { ...value };
    emit('update:value', value);
}

// ─── Multi-marker state ────────────────────────────────────────────────────────
let _nextId = 1;
function genId() { return 'gmf-' + (_nextId++) + '-' + Math.random().toString(36).slice(2, 7); }

function wrapMarker(m) {
    return {
        _id: genId(),
        lat: m.lat ?? null,
        lng: m.lng ?? null,
        address: m.address ?? '',
        zoom: m.zoom ?? defaultZoom.value,
        infobox_title: m.infobox_title ?? '',
        infobox_content: m.infobox_content ?? '',
        marker_icon_url: m.marker_icon_url ?? '',
        marker_icon_width: m.marker_icon_width ?? 0,
        marker_icon_height: m.marker_icon_height ?? 0,
        marker_asset_id: m.marker_asset_id ?? '',
        enabled: m.enabled !== false, // default on
    };
}

function initMarkersFromProp() {
    const v = props.value;
    if (!v || typeof v !== 'object') return [];
    if (Array.isArray(v.markers)) return v.markers.map(wrapMarker);
    return [];
}

const markersData = ref(initMarkersFromProp());
const zoomOverride = ref(props.value?.zoom_override != null ? props.value.zoom_override : null);

// Global field enabled toggle
const fieldEnabled = ref(true);

// Edit mode: 'individual' | 'bulk'
const editMode = ref('individual');

// Active count (enabled markers)
const activeMarkerCount = computed(() => markersData.value.filter(m => m.enabled !== false).length);

const expandedMarker = ref(null);
const markerSearching = ref({});
const markerSearchErrors = ref({});
const markerAssetIdsMap = ref({});

// Per-marker individual mini-map state
const markerMapInstances = {}; // _id -> mapInstance
const markerMapMarkers = {};   // _id -> markerInstance
let markerMapRefs = {};        // _id -> DOM el (populated by :ref callback)
const markerMapType = ref({});  // _id -> 'roadmap' | 'satellite'

// Toast shown above the marker list after a bulk-apply — stays in parent so
// it persists when the user switches back to the Individual tab.
const bulkToast = ref('');
let _bulkToastTimer = null;

markersData.value.forEach(m => {
    if (m.marker_asset_id) markerAssetIdsMap.value[m._id] = [m.marker_asset_id];
});

function emitMulti() {
    emit('update:value', {
        zoom_override: zoomOverride.value,
        markers: markersData.value.map(({ _id, ...rest }) => rest),
    });
}

function addMarker() {
    const m = wrapMarker({});
    markersData.value = [...markersData.value, m];
    expandedMarker.value = m._id;
    emitMulti();
    nextTick(() => {
        refreshMultiMapMarkers();
        initMarkerMiniMap(m._id);
    });
}

function removeMarker(id) {
    if (!window.confirm('Are you sure you want to remove this marker?')) return;
    destroyMarkerMiniMap(id);
    markersData.value = markersData.value.filter(m => m._id !== id);
    if (expandedMarker.value === id) expandedMarker.value = null;
    delete markerAssetIdsMap.value[id];
    delete markerSearching.value[id];
    delete markerSearchErrors.value[id];
    delete markerMapType.value[id];
    emitMulti();
    nextTick(() => refreshMultiMapMarkers());
}

function toggleExpanded(id) {
    const wasExpanded = expandedMarker.value === id;
    expandedMarker.value = wasExpanded ? null : id;
    if (!wasExpanded) {
        nextTick(() => initMarkerMiniMap(id));
    } else {
        destroyMarkerMiniMap(id);
    }
}

function toggleMarkerEnabled(id) {
    markersData.value = markersData.value.map(m =>
        m._id === id ? { ...m, enabled: m.enabled === false ? true : false } : m
    );
    emitMulti();
    nextTick(() => refreshMultiMapMarkers());
}

function updateMarkerField(id, field, value) {
    markersData.value = markersData.value.map(m => m._id === id ? { ...m, [field]: value } : m);
    emitMulti();
}

function updateMarkerCoord(id, field, rawValue) {
    const num = field === 'zoom' ? parseInt(rawValue) : parseFloat(rawValue);
    if (isNaN(num)) return;
    markersData.value = markersData.value.map(m => m._id === id ? { ...m, [field]: num } : m);
    emitMulti();
    nextTick(() => {
        refreshMultiMapMarkers();
        syncMarkerMiniMapPin(id);
    });
}

function clearMarkerLocation(id) {
    if (!window.confirm('Are you sure you want to clear this location?')) return;
    markersData.value = markersData.value.map(m =>
        m._id === id ? { ...m, lat: null, lng: null, address: '', zoom: defaultZoom.value } : m
    );
    emitMulti();
    nextTick(() => {
        refreshMultiMapMarkers();
        syncMarkerMiniMapPin(id);
    });
}

function onMarkersReordered(newList) {
    markersData.value = newList;
    emitMulti();
}

function clearAllMarkersData() {
    if (!window.confirm(`Are you sure you want to clear all data from ${markersData.value.length} marker${markersData.value.length !== 1 ? 's' : ''}? Locations, addresses and infoboxes will be reset.`)) return;
    markersData.value = markersData.value.map(m => ({
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
        refreshMultiMapMarkers();
        Object.keys(markerMapInstances).forEach(id => syncMarkerMiniMapPin(id));
    });
}

function clearAllMarkers() {
    if (!window.confirm(`Are you sure you want to delete all ${markersData.value.length} marker${markersData.value.length !== 1 ? 's' : ''}? This cannot be undone.`)) return;
    Object.keys(markerMapInstances).forEach(id => destroyMarkerMiniMap(id));
    markerMapRefs = {};
    markersData.value = [];
    expandedMarker.value = null;
    markerAssetIdsMap.value = {};
    markerSearching.value = {};
    markerSearchErrors.value = {};
    markerMapType.value = {};
    emitMulti();
    nextTick(() => refreshMultiMapMarkers());
}

function onZoomOverrideChange(rawValue) {
    const num = parseInt(rawValue);
    zoomOverride.value = isNaN(num) ? null : num;
    emitMulti();
    if (mapInstance && zoomOverride.value) mapInstance.setZoom(zoomOverride.value);
}

// ─── Per-marker mini-map helpers ───────────────────────────────────────────────
function setMarkerMapRef(id, el) {
    markerMapRefs[id] = el;
}

async function initMarkerMiniMap(id) {
    await nextTick();
    const el = markerMapRefs[id];
    if (!el || markerMapInstances[id]) return;

    const m = markersData.value.find(x => x._id === id);
    if (!m) return;

    const lat = m.lat ?? (defaultLat.value ?? 0);
    const lng = m.lng ?? (defaultLng.value ?? 0);
    const zoom = m.lat != null ? (m.zoom || defaultZoom.value) : 2;

    if (mapProvider.value === 'osm') {
        const L = await loadLeaflet();
        const mini = L.map(el, { center: [lat, lng], zoom });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors', maxZoom: 21,
        }).addTo(mini);
        markerMapInstances[id] = mini;
        if (m.lat != null) {
            const pin = L.marker([lat, lng], { draggable: !props.readOnly }).addTo(mini);
            markerMapMarkers[id] = pin;
            if (!props.readOnly) {
                pin.on('dragend', e => {
                    const p = e.target.getLatLng();
                    onMarkerMiniMapPick(id, p.lat, p.lng);
                });
            }
        }
        if (!props.readOnly) {
            mini.on('click', e => {
                placeOrMoveMarkerMiniMapPin(id, e.latlng.lat, e.latlng.lng);
                onMarkerMiniMapPick(id, e.latlng.lat, e.latlng.lng);
            });
        }
    } else {
        await loadGoogleMapsScript(globalConfig.value.api_key);
        const mapType = markerMapType.value[id] || 'roadmap';
        const mini = new window.google.maps.Map(el, {
            center: { lat, lng }, zoom, mapTypeId: mapType,
            gestureHandling: props.readOnly ? 'none' : 'auto',
        });
        markerMapInstances[id] = mini;
        if (m.lat != null) {
            const pin = new window.google.maps.Marker({
                position: { lat, lng }, map: mini, draggable: !props.readOnly,
            });
            markerMapMarkers[id] = pin;
            if (!props.readOnly) {
                pin.addListener('dragend', () => {
                    const pos = pin.getPosition();
                    onMarkerMiniMapPick(id, pos.lat(), pos.lng());
                });
            }
        }
        if (!props.readOnly) {
            mini.addListener('click', e => {
                placeOrMoveMarkerMiniMapPin(id, e.latLng.lat(), e.latLng.lng());
                onMarkerMiniMapPick(id, e.latLng.lat(), e.latLng.lng());
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
        } catch {}
    }
    delete markerMapInstances[id];
    delete markerMapMarkers[id];
    delete markerMapRefs[id];
}

function placeOrMoveMarkerMiniMapPin(id, lat, lng) {
    if (mapProvider.value === 'osm') {
        const L = getLeaflet();
        if (!markerMapMarkers[id]) {
            const pin = L.marker([lat, lng], { draggable: !props.readOnly }).addTo(markerMapInstances[id]);
            markerMapMarkers[id] = pin;
            if (!props.readOnly) {
                pin.on('dragend', e => {
                    const p = e.target.getLatLng();
                    onMarkerMiniMapPick(id, p.lat, p.lng);
                });
            }
        } else {
            markerMapMarkers[id].setLatLng([lat, lng]);
        }
        markerMapInstances[id].panTo([lat, lng]);
    } else {
        if (!markerMapMarkers[id]) {
            const pin = new window.google.maps.Marker({
                position: { lat, lng }, map: markerMapInstances[id], draggable: !props.readOnly,
            });
            markerMapMarkers[id] = pin;
            if (!props.readOnly) {
                pin.addListener('dragend', () => {
                    const pos = pin.getPosition();
                    onMarkerMiniMapPick(id, pos.lat(), pos.lng());
                });
            }
        } else {
            markerMapMarkers[id].setPosition({ lat, lng });
        }
        markerMapInstances[id].panTo({ lat, lng });
    }
}

function syncMarkerMiniMapPin(id) {
    const m = markersData.value.find(x => x._id === id);
    if (!m || m.lat == null || !markerMapInstances[id]) return;
    placeOrMoveMarkerMiniMapPin(id, m.lat, m.lng);
    if (m.zoom) markerMapInstances[id].setZoom(m.zoom);
}

async function onMarkerMiniMapPick(id, lat, lng) {
    markersData.value = markersData.value.map(m => m._id === id ? { ...m, lat, lng } : m);
    emitMulti();
    refreshMultiMapMarkers();
    try {
        let address = null;
        if (mapProvider.value === 'osm') {
            const data = await postCp('google-maps/reverse-geocode', { lat, lng, provider: 'osm' }, _axios);
            if (data.success) address = data.result.address;
        } else {
            address = await googleReverseGeocode(lat, lng);
        }
        if (address) {
            markersData.value = markersData.value.map(m => m._id === id ? { ...m, address } : m);
            emitMulti();
        }
    } catch {}
}

function setMarkerMapType(id, type) {
    markerMapType.value = { ...markerMapType.value, [id]: type };
    const mini = markerMapInstances[id];
    if (mini && window.google?.maps) {
        mini.setMapTypeId(type);
    }
}

// ─── Bulk apply handler ────────────────────────────────────────────────────────
function onBulkApply({ template: t, fields, scope }) {
    const targets = scope === 'active'
        ? markersData.value.filter(m => m.enabled !== false)
        : [...markersData.value];
    const targetIds = new Set(targets.map(x => x._id));
    markersData.value = markersData.value.map(m => {
        if (!targetIds.has(m._id)) return m;
        const patch = {};
        if (fields.zoom && t.zoom) patch.zoom = t.zoom;
        if (fields.infobox) {
            patch.infobox_title   = t.infobox_title;
            patch.infobox_content = t.infobox_content;
        }
        if (fields.icon) {
            patch.marker_icon_url    = t.marker_icon_url;
            patch.marker_icon_width  = t.marker_icon_width;
            patch.marker_icon_height = t.marker_icon_height;
            patch.marker_asset_id    = t.marker_asset_id;
        }
        return { ...m, ...patch };
    });
    if (fields.icon) {
        const newMap = { ...markerAssetIdsMap.value };
        targets.forEach(m => {
            newMap[m._id] = t.marker_asset_id ? [t.marker_asset_id] : [];
        });
        markerAssetIdsMap.value = newMap;
    }
    emitMulti();
    editMode.value = 'individual';
    if (_bulkToastTimer) clearTimeout(_bulkToastTimer);
    bulkToast.value = `\u2713 ${targets.length} marker${targets.length !== 1 ? 's' : ''} updated`;
    _bulkToastTimer = setTimeout(() => { bulkToast.value = ''; }, 4000);
}

// ─── Search state (single) ─────────────────────────────────────────────────────
const searchQuery = ref('');
const isSearching = ref(false);
const searchError = ref('');
let _searchDebounceTimer = null;

watch(searchQuery, (q) => {
    clearTimeout(_searchDebounceTimer);
    if (!q.trim()) { searchError.value = ''; return; }
    _searchDebounceTimer = setTimeout(search, 500);
});

// ─── Map state ─────────────────────────────────────────────────────────────────
const mapEl = ref(null);
let mapInstance = null;
let markerInstance = null;
let multiMarkerInstances = [];
let markerClustererInstance = null;
const mapReady = ref(false);

// ─── Asset picker (single) ─────────────────────────────────────────────────────
const markerAssetIds = ref(
    internalValue.value.marker_asset_id ? [internalValue.value.marker_asset_id] : []
);

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
    try {
        const assets = await postCp('assets-fieldtype', { assets: ids }, _axios);
        if (assets?.[0]?.url) {
            update({ ...internalValue.value, marker_icon_url: assets[0].url, marker_asset_id: ids[0] });
        }
    } catch (e) {
        console.error('[GoogleMapsFieldtype] Asset resolve failed:', e);
    }
}

async function onMultiMarkerAssetUpdate(markerId, ids) {
    markerAssetIdsMap.value = { ...markerAssetIdsMap.value, [markerId]: ids ?? [] };
    if (!ids?.length) {
        markersData.value = markersData.value.map(m =>
            m._id === markerId ? { ...m, marker_icon_url: '', marker_asset_id: '' } : m
        );
        emitMulti();
        return;
    }
    try {
        const assets = await postCp('assets-fieldtype', { assets: ids }, _axios);
        if (assets?.[0]?.url) {
            markersData.value = markersData.value.map(m =>
                m._id === markerId ? { ...m, marker_icon_url: assets[0].url, marker_asset_id: ids[0] } : m
            );
            emitMulti();
        }
    } catch (e) {
        console.error('[GoogleMapsFieldtype] Multi asset resolve failed:', e);
    }
}

// ─── Map init ──────────────────────────────────────────────────────────────────
async function initMap() {
    if (!mapEl.value) return;
    try {
        if (mapProvider.value === 'osm') await initLeaflet();
        else await initGoogleMaps();
        mapReady.value = true;
    } catch (err) {
        console.error('[GoogleMapsFieldtype] Map init failed:', err);
    }
}

async function initLeaflet() {
    const L = await loadLeaflet();

    delete L.Icon.Default.prototype._getIconUrl;
    L.Icon.Default.mergeOptions({
        iconUrl:       new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
        iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
        shadowUrl:     new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
    });

    const lat  = internalValue.value.lat  ?? (defaultLat.value ?? 0);
    const lng  = internalValue.value.lng  ?? (defaultLng.value ?? 0);
    const zoom = hasLocation.value ? (internalValue.value.zoom || defaultZoom.value) : 2;

    mapInstance = L.map(mapEl.value, { center: [lat, lng], zoom });
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 21,
    }).addTo(mapInstance);

    if (isMulti.value) {
        refreshMultiMapMarkersLeaflet();
        if (!props.readOnly) {
            mapInstance.on('click', e => addMarkerAtPosition(e.latlng.lat, e.latlng.lng));
        }
        return;
    }

    if (hasLocation.value) {
        markerInstance = L.marker([lat, lng], { draggable: !props.readOnly }).addTo(mapInstance);
        if (!props.readOnly) {
            markerInstance.on('dragend', e => {
                const p = e.target.getLatLng();
                onMapPick(p.lat, p.lng);
            });
        }
    }

    if (props.readOnly) {
        mapInstance.dragging.disable();
        mapInstance.scrollWheelZoom.disable();
        mapInstance.touchZoom.disable();
        mapInstance.doubleClickZoom.disable();
        mapInstance.keyboard.disable();
        mapInstance.boxZoom.disable();
        return;
    }

    mapInstance.on('click', e => {
        placeOrMoveLeafletMarker(e.latlng.lat, e.latlng.lng);
        onMapPick(e.latlng.lat, e.latlng.lng);
    });
}

async function initGoogleMaps() {
    if (!globalConfig.value.api_key) {
        mapEl.value.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6b7280;font-size:0.875rem;text-align:center;padding:1rem;">No Google Maps API key configured.<br>Add <code>GOOGLE_MAPS_API_KEY</code> to your <code>.env</code>,<br>or switch to OpenStreetMap in Tools → Google Maps.</div>';
        return;
    }
    await loadGoogleMapsScript(globalConfig.value.api_key);

    const lat  = internalValue.value.lat  ?? (defaultLat.value ?? 0);
    const lng  = internalValue.value.lng  ?? (defaultLng.value ?? 0);
    const zoom = hasLocation.value ? (internalValue.value.zoom || defaultZoom.value) : 2;

    mapInstance = new window.google.maps.Map(mapEl.value, {
        center: { lat, lng }, zoom,
        mapTypeId: globalConfig.value.map_type || 'roadmap',
        gestureHandling: props.readOnly ? 'none' : 'auto',
    });

    if (isMulti.value) {
        refreshMultiMapMarkersGoogle();
        if (!props.readOnly) {
            mapInstance.addListener('click', e => addMarkerAtPosition(e.latLng.lat(), e.latLng.lng()));
        }
        return;
    }

    if (hasLocation.value) {
        markerInstance = new window.google.maps.Marker({
            position: { lat, lng }, map: mapInstance, draggable: !props.readOnly,
        });
        if (!props.readOnly) {
            markerInstance.addListener('dragend', () => {
                const pos = markerInstance.getPosition();
                onMapPick(pos.lat(), pos.lng());
            });
        }
    }

    if (props.readOnly) return;

    mapInstance.addListener('click', e => {
        placeOrMoveGoogleMarker(e.latLng.lat(), e.latLng.lng());
        onMapPick(e.latLng.lat(), e.latLng.lng());
    });
}

// ─── Multi-marker map helpers ──────────────────────────────────────────────────
function refreshMultiMapMarkers() {
    if (!mapInstance) return;
    if (mapProvider.value === 'osm') refreshMultiMapMarkersLeaflet();
    else refreshMultiMapMarkersGoogle();
}

function refreshMultiMapMarkersLeaflet() {
    const L = getLeaflet();
    multiMarkerInstances.forEach(m => { try { m.remove(); } catch {} });
    multiMarkerInstances = [];

    const valid = markersData.value.filter(m => m.lat != null && m.lng != null && m.enabled !== false);
    if (!valid.length) return;

    valid.forEach(m => {
        const marker = L.marker([m.lat, m.lng], { draggable: !props.readOnly }).addTo(mapInstance);
        if (!props.readOnly) {
            const id = m._id;
            marker.on('dragend', e => {
                const p = e.target.getLatLng();
                onMultiMapPick(id, p.lat, p.lng);
            });
        }
        multiMarkerInstances.push(marker);
    });

    if (valid.length === 1) {
        mapInstance.setView([valid[0].lat, valid[0].lng], zoomOverride.value || defaultZoom.value);
    } else {
        const group = L.featureGroup(multiMarkerInstances);
        try { mapInstance.fitBounds(group.getBounds(), { padding: [40, 40] }); } catch {}
        if (zoomOverride.value) mapInstance.setZoom(zoomOverride.value);
    }
}

function refreshMultiMapMarkersGoogle() {
    // Destroy existing clusterer
    if (markerClustererInstance) {
        markerClustererInstance.clearMarkers();
        markerClustererInstance.setMap(null);
        markerClustererInstance = null;
    }
    multiMarkerInstances.forEach(m => { try { m.setMap(null); } catch {} });
    multiMarkerInstances = [];

    const valid = markersData.value.filter(m => m.lat != null && m.lng != null && m.enabled !== false);
    if (!valid.length) return;

    const clustering = !!props.config.enable_clustering;
    const bounds = new window.google.maps.LatLngBounds();

    valid.forEach(m => {
        const marker = new window.google.maps.Marker({
            position: { lat: m.lat, lng: m.lng },
            // When clustering, don't attach to map directly — clusterer handles that
            map: clustering ? null : mapInstance,
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
            map: mapInstance,
            markers: multiMarkerInstances,
        });
    }

    if (valid.length === 1) {
        mapInstance.setCenter({ lat: valid[0].lat, lng: valid[0].lng });
        mapInstance.setZoom(zoomOverride.value || defaultZoom.value);
    } else {
        mapInstance.fitBounds(bounds);
        if (zoomOverride.value) mapInstance.setZoom(zoomOverride.value);
    }
}

async function addMarkerAtPosition(lat, lng) {
    const m = wrapMarker({ lat, lng });
    markersData.value = [...markersData.value, m];
    expandedMarker.value = m._id;
    emitMulti();
    await nextTick();
    refreshMultiMapMarkers();
    try {
        let address = null;
        if (mapProvider.value === 'osm') {
            const data = await postCp('google-maps/reverse-geocode', { lat, lng, provider: 'osm' }, _axios);
            if (data.success) address = data.result.address;
        } else {
            address = await googleReverseGeocode(lat, lng);
        }
        if (address) {
            markersData.value = markersData.value.map(mk => mk._id === m._id ? { ...mk, address } : mk);
            emitMulti();
        }
    } catch {}
}

async function onMultiMapPick(markerId, lat, lng) {
    markersData.value = markersData.value.map(m => m._id === markerId ? { ...m, lat, lng } : m);
    emitMulti();
    try {
        let address = null;
        if (mapProvider.value === 'osm') {
            const data = await postCp('google-maps/reverse-geocode', { lat, lng, provider: 'osm' }, _axios);
            if (data.success) address = data.result.address;
        } else {
            address = await googleReverseGeocode(lat, lng);
        }
        if (address) {
            markersData.value = markersData.value.map(m => m._id === markerId ? { ...m, address } : m);
            emitMulti();
        }
    } catch {}
}

// ─── Single-mode marker helpers ────────────────────────────────────────────────
function placeOrMoveLeafletMarker(lat, lng) {
    if (!markerInstance) {
        const L = getLeaflet();
        markerInstance = L.marker([lat, lng], { draggable: true }).addTo(mapInstance);
        markerInstance.on('dragend', e => {
            const p = e.target.getLatLng();
            onMapPick(p.lat, p.lng);
        });
    } else {
        markerInstance.setLatLng([lat, lng]);
    }
}

function placeOrMoveGoogleMarker(lat, lng) {
    if (!markerInstance) {
        markerInstance = new window.google.maps.Marker({
            position: { lat, lng }, map: mapInstance, draggable: true,
        });
        markerInstance.addListener('dragend', () => {
            const pos = markerInstance.getPosition();
            onMapPick(pos.lat(), pos.lng());
        });
    } else {
        markerInstance.setPosition({ lat, lng });
    }
}

function moveMarker(lat, lng) {
    if (!mapInstance || !markerInstance) return;
    if (mapProvider.value === 'osm') {
        markerInstance.setLatLng([lat, lng]);
        mapInstance.panTo([lat, lng]);
    } else {
        const pos = new window.google.maps.LatLng(lat, lng);
        markerInstance.setPosition(pos);
        mapInstance.panTo(pos);
    }
}

function setMapZoom(zoom) {
    if (!mapInstance) return;
    mapInstance.setZoom(zoom);
}

// ─── Single-mode actions ───────────────────────────────────────────────────────
let _fromMapInteraction = false;

async function onMapPick(lat, lng) {
    _fromMapInteraction = true;
    update({ ...internalValue.value, lat, lng });
    await nextTick();
    _fromMapInteraction = false;

    try {
        let address = null;
        if (mapProvider.value === 'osm') {
            const data = await postCp('google-maps/reverse-geocode', { lat, lng, provider: 'osm' }, _axios);
            if (data.success) address = data.result.address;
        } else {
            address = await googleReverseGeocode(lat, lng);
        }
        if (address) update({ ...internalValue.value, lat, lng, address });
    } catch {}
}

async function search() {
    clearTimeout(_searchDebounceTimer);
    const q = searchQuery.value.trim();
    if (!q || isSearching.value) return;
    isSearching.value = true;
    searchError.value = '';

    try {
        let lat, lng, address;
        if (mapProvider.value === 'osm') {
            const data = await postCp('google-maps/geocode', { address: q, provider: 'osm' }, _axios);
            if (!data.success) { searchError.value = data.message || 'Location not found.'; return; }
            ({ lat, lng, address } = data.result);
        } else {
            const result = await googleGeocode(q);
            if (!result) { searchError.value = 'No results found.'; return; }
            ({ lat, lng, address } = result);
        }

        const currentZoom = Math.max(internalValue.value.zoom || defaultZoom.value, 12);
        update({ ...internalValue.value, lat, lng, address, zoom: currentZoom });
        await nextTick();

        if (mapProvider.value === 'osm' && mapInstance) {
            placeOrMoveLeafletMarker(lat, lng);
            mapInstance.setView([lat, lng], currentZoom);
        } else if (mapInstance) {
            placeOrMoveGoogleMarker(lat, lng);
            mapInstance.panTo({ lat, lng });
            if (mapInstance.getZoom() < 12) mapInstance.setZoom(12);
        }
    } catch (err) {
        searchError.value = err?.message || 'Search failed. Please try again.';
    } finally {
        isSearching.value = false;
    }
}

// ─── Multi-mode search ─────────────────────────────────────────────────────────
async function searchMarker(markerId, query) {
    const q = (query || '').trim();
    if (!q || markerSearching.value[markerId]) return;

    markerSearching.value = { ...markerSearching.value, [markerId]: true };
    markerSearchErrors.value = { ...markerSearchErrors.value, [markerId]: '' };

    try {
        let lat, lng, address;
        if (mapProvider.value === 'osm') {
            const data = await postCp('google-maps/geocode', { address: q, provider: 'osm' }, _axios);
            if (!data.success) {
                markerSearchErrors.value = { ...markerSearchErrors.value, [markerId]: data.message || 'Location not found.' };
                return;
            }
            ({ lat, lng, address } = data.result);
        } else {
            const result = await googleGeocode(q);
            if (!result) {
                markerSearchErrors.value = { ...markerSearchErrors.value, [markerId]: 'No results found.' };
                return;
            }
            ({ lat, lng, address } = result);
        }

        markersData.value = markersData.value.map(m =>
            m._id === markerId ? { ...m, lat, lng, address } : m
        );
        emitMulti();
        await nextTick();
        refreshMultiMapMarkers();
    } catch (err) {
        markerSearchErrors.value = { ...markerSearchErrors.value, [markerId]: err?.message || 'Search failed.' };
    } finally {
        markerSearching.value = { ...markerSearching.value, [markerId]: false };
    }
}

// ─── Coord inputs (single) ─────────────────────────────────────────────────────
function updateCoord(field, rawValue) {
    const num = field === 'zoom' ? parseInt(rawValue) : parseFloat(rawValue);
    if (isNaN(num)) return;
    update({ ...internalValue.value, [field]: num });
}

function clearLocation() {
    if (!window.confirm('Are you sure you want to clear this location?')) return;
    update({
        lat: null, lng: null, address: '', zoom: defaultZoom.value,
        infobox_title: '', infobox_content: '',
        marker_icon_url: '', marker_icon_width: 0, marker_icon_height: 0, marker_asset_id: '',
    });
    markerAssetIds.value = [];
    searchQuery.value = '';
    searchError.value = '';
}

// ─── Watchers (single only) ────────────────────────────────────────────────────
watch(
    () => [internalValue.value.lat, internalValue.value.lng],
    ([lat, lng]) => {
        if (!mapReady.value || lat == null || lng == null || _fromMapInteraction || isMulti.value) return;
        moveMarker(lat, lng);
    }
);

watch(
    () => internalValue.value.zoom,
    zoom => {
        if (!mapReady.value || !zoom || isMulti.value) return;
        setMapZoom(zoom);
    }
);

// ─── Lifecycle ─────────────────────────────────────────────────────────────────

let _mapObserver = null;

function tryInitMapWhenVisible(el) {
    if (!el || mapReady.value) return;
    // If the element already has dimensions, init immediately
    if (el.offsetWidth > 0 && el.offsetHeight > 0) {
        initMap();
        return;
    }
    // Otherwise wait until it becomes visible (handles two-column layouts,
    // accordions, tabs, and any lazy container in Statamic CP)
    if (_mapObserver) _mapObserver.disconnect();
    _mapObserver = new IntersectionObserver(
        (entries) => {
            const entry = entries[0];
            if (entry.isIntersecting && entry.target.offsetWidth > 0 && !mapReady.value) {
                _mapObserver.disconnect();
                _mapObserver = null;
                initMap();
            }
        },
        { threshold: 0 }
    );
    _mapObserver.observe(el);
}

// Watch the mapEl ref so the map initialises whenever the element actually
// enters the DOM (handles Statamic accordions / tabs / lazy containers where
// mapEl.value is null at onMounted time).
watch(mapEl, async (el) => {
    if (el && !mapReady.value) {
        await nextTick();
        tryInitMapWhenVisible(el);
    }
});

onMounted(async () => {
    await nextTick();
    tryInitMapWhenVisible(mapEl.value);
});

onBeforeUnmount(() => {
    if (_mapObserver) { _mapObserver.disconnect(); _mapObserver = null; }
    // Clean up per-marker mini-maps
    Object.keys(markerMapInstances).forEach(id => destroyMarkerMiniMap(id));
    markerMapRefs = {};

    if (markerClustererInstance) {
        try {
            markerClustererInstance.clearMarkers();
            markerClustererInstance.setMap(null);
        } catch (err) {
            console.warn('[GoogleMapsFieldtype] clusterer teardown failed:', err?.message);
        }
        markerClustererInstance = null;
    }
    multiMarkerInstances.forEach(m => {
        try {
            if (mapProvider.value === 'osm') m.remove();
            else m.setMap(null);
        } catch (err) {
            console.warn('[GoogleMapsFieldtype] marker teardown failed:', err?.message);
        }
    });
    multiMarkerInstances = [];
    if (mapProvider.value === 'osm' && mapInstance) {
        try { mapInstance.remove(); }
        catch (err) { console.warn('[GoogleMapsFieldtype] map teardown failed:', err?.message); }
    }
    mapInstance = null;
    markerInstance = null;
});
</script>

<!--
    Styles for this component and its sub-components (MarkerListRow, BulkEditPanel)
    live in resources/css/cp.css (gmf-* prefix). Scoped styles cannot reach child
    component elements, so all styles are global with the gmf- namespace.
-->
