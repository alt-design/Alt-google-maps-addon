<template>
    <div class="flex flex-col gap-0 bg-white dark:bg-gray-800 border border-violet-200 dark:border-violet-800/50 rounded-xl overflow-hidden shadow-sm">

        <!-- ── Panel header ── -->
        <div class="flex items-start gap-3 px-4 py-3 bg-violet-50 dark:bg-violet-900/20 border-b border-violet-200 dark:border-violet-800/50">
            <div class="flex-shrink-0 mt-0.5 size-7 rounded-lg bg-violet-100 dark:bg-violet-800/50 flex items-center justify-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4 text-violet-600 dark:text-violet-400"
                     viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                </svg>
            </div>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-violet-900 dark:text-violet-100">Edit all markers at once</p>
                <p class="text-xs text-violet-600/80 dark:text-violet-400 mt-0.5">Changes apply to every marker in this field</p>
            </div>
        </div>

        <div class="flex flex-col gap-4 p-4">

            <!-- ── Scope + Fields row ── -->
            <div class="grid grid-cols-2 gap-4">
                <!-- Scope -->
                <div class="flex flex-col gap-2">
                    <label class="text-2xs uppercase font-semibold tracking-wide text-gray-400 dark:text-gray-500">Apply changes to</label>
                    <div class="flex gap-1.5">
                        <button type="button"
                                class="flex-1 py-1.5 px-3 rounded-lg text-xs font-medium border transition-colors"
                                :class="bulkScope === 'all'
                                    ? 'bg-violet-600 text-white border-violet-600'
                                    : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-violet-400'"
                                @click.prevent="bulkScope = 'all'">All markers</button>
                        <button type="button"
                                class="flex-1 py-1.5 px-3 rounded-lg text-xs font-medium border transition-colors"
                                :class="bulkScope === 'active'
                                    ? 'bg-violet-600 text-white border-violet-600'
                                    : 'bg-white dark:bg-gray-700 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-600 hover:border-violet-400'"
                                @click.prevent="bulkScope = 'active'">Active only</button>
                    </div>
                </div>

                <!-- Fields to overwrite -->
                <div class="flex flex-col gap-2 items-end">
                    <label class="text-2xs uppercase font-semibold tracking-wide text-gray-400 dark:text-gray-500 self-end">Fields to overwrite</label>
                    <div class="flex flex-col gap-1.5 items-end">
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="bulkFields.zoom" class="accent-violet-600 size-3.5 flex-shrink-0 cursor-pointer" />
                            <span class="text-xs text-gray-600 dark:text-gray-300 group-hover:text-gray-800 dark:group-hover:text-gray-100 transition-colors">Zoom level</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="bulkFields.infobox" class="accent-violet-600 size-3.5 flex-shrink-0 cursor-pointer" />
                            <span class="text-xs text-gray-600 dark:text-gray-300 group-hover:text-gray-800 dark:group-hover:text-gray-100 transition-colors">Infobox title &amp; content</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer group">
                            <input type="checkbox" v-model="bulkFields.icon" class="accent-violet-600 size-3.5 flex-shrink-0 cursor-pointer" />
                            <span class="text-xs text-gray-600 dark:text-gray-300 group-hover:text-gray-800 dark:group-hover:text-gray-100 transition-colors">Marker icon</span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- ── Bulk map ── -->
            <div ref="bulkMapEl"
                 class="gmf-map rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600"
                 :style="{ height: Math.round(mapHeight * 0.65) + 'px' }"></div>

            <!-- ── Search ── -->
            <div class="flex flex-col gap-1">
                <div class="flex gap-2">
                    <input v-model="bulkSearchQuery" type="text" class="gmf-input flex-1"
                           placeholder="Search for a location…"
                           :disabled="bulkSearching"
                           @keydown.enter.prevent="searchBulk" />
                    <button type="button" class="gmf-search-btn"
                            :disabled="bulkSearching || !bulkSearchQuery.trim()"
                            @click.prevent="searchBulk">
                        <svg v-if="bulkSearching" xmlns="http://www.w3.org/2000/svg"
                             class="size-4 gmf-spin" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        {{ bulkSearching ? 'Searching…' : 'Search' }}
                    </button>
                </div>
                <p v-if="bulkSearchError" class="text-xs text-red-500 dark:text-red-400">{{ bulkSearchError }}</p>
            </div>

            <!-- ── Lat / Lng / Zoom ── -->
            <div class="grid grid-cols-3 gap-2">
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Lat</label>
                    <input type="number" step="any" class="gmf-input" placeholder="-"
                           v-model.number="bulkTemplate.lat" />
                </div>
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Lng</label>
                    <input type="number" step="any" class="gmf-input" placeholder="-"
                           v-model.number="bulkTemplate.lng" />
                </div>
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Zoom</label>
                    <input type="number" min="1" max="21" class="gmf-input" placeholder="13"
                           v-model.number="bulkTemplate.zoom" :disabled="!bulkFields.zoom" />
                </div>
            </div>

            <!-- ── Infobox ── -->
            <template v-if="bulkFields.infobox">
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Infobox Title</label>
                    <input type="text" class="gmf-input" placeholder="Shared title for all markers"
                           v-model="bulkTemplate.infobox_title" />
                </div>
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">
                        Infobox Content
                        <span class="font-normal normal-case text-gray-400 ml-1">HTML supported</span>
                    </label>
                    <textarea class="gmf-input" placeholder="Shared content or HTML" rows="3"
                              v-model="bulkTemplate.infobox_content"></textarea>
                </div>
            </template>

            <!-- ── Marker icon ── -->
            <template v-if="bulkFields.icon">
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Marker icon applied to all</label>
                    <assets-fieldtype
                        v-if="assetMeta.container"
                        :value="bulkAssetIds"
                        :config="assetConfig"
                        :meta="assetMeta"
                        handle="bulk_marker_icon"
                        @input="onBulkAssetUpdate"
                        @update:value="onBulkAssetUpdate"
                    />
                    <p v-else class="text-xs text-gray-400 italic mt-1">No asset containers configured.</p>
                </div>
                <div v-if="bulkTemplate.marker_icon_url" class="grid grid-cols-2 gap-2">
                    <div class="gmf-field">
                        <label class="text-2xs uppercase font-semibold text-gray-500">Icon Width (px)</label>
                        <input type="number" min="1" class="gmf-input" placeholder="40"
                               v-model.number="bulkTemplate.marker_icon_width" />
                    </div>
                    <div class="gmf-field">
                        <label class="text-2xs uppercase font-semibold text-gray-500">Icon Height (px)</label>
                        <input type="number" min="1" class="gmf-input" placeholder="40"
                               v-model.number="bulkTemplate.marker_icon_height" />
                    </div>
                </div>
            </template>

            <!-- ── Apply button ── -->
            <button type="button" class="gmf-apply-btn" @click.prevent="applyBulk">
                Apply to {{ bulkScope === 'active' ? 'active' : 'all' }} markers ↓
            </button>

        </div><!-- /inner padding -->
    </div><!-- /panel -->
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue';
import { loadGoogleMapsScript, loadLeaflet, getLeaflet } from '../../../composables/useMapLoader.js';
import { postCp, geocodeAddress } from '../../../composables/useGeocoder.js';

// ─── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    mapProvider: { type: String, default: 'google' },
    mapHeight: { type: Number, default: 300 },
    defaultLat: { type: Number, default: null },
    defaultLng: { type: Number, default: null },
    apiKey: { type: String, default: '' },
    assetMeta: { type: Object, default: () => ({}) },
    assetConfig: { type: Object, default: () => ({}) },
    defaultZoom: { type: Number, default: 13 },
});

// ─── Emits ─────────────────────────────────────────────────────────────────────
const emit = defineEmits([
    /**
     * Fired when the user clicks "Apply".
     * Payload: { template: object, fields: object, scope: string }
     * The parent is responsible for mutating markersData and markerAssetIdsMap.
     */
    'apply',
]);

// ─── Axios ─────────────────────────────────────────────────────────────────────
const _instance = getCurrentInstance();
const $axios = _instance?.proxy?.$axios ?? null;

// ─── Internal state ─────────────────────────────────────────────────────────────
const bulkMapEl = ref(null);
let bulkMapInstance = null;
let bulkMapMarker = null;
const bulkMapType = ref('roadmap');

const bulkTemplate = ref({
    lat: null, lng: null, zoom: props.defaultZoom,
    infobox_title: '', infobox_content: '',
    marker_icon_url: '', marker_icon_width: 0, marker_icon_height: 0, marker_asset_id: '',
});
const bulkFields = ref({ zoom: true, infobox: false, icon: false });
const bulkScope = ref('all');
const bulkAssetIds = ref([]);
const bulkSearchQuery = ref('');
const bulkSearching = ref(false);
const bulkSearchError = ref('');

// ─── Lifecycle ──────────────────────────────────────────────────────────────────
onMounted(() => initBulkMap());
onBeforeUnmount(() => destroyBulkMap());

// ─── Map init / destroy ─────────────────────────────────────────────────────────
async function initBulkMap() {
    await new Promise(r => setTimeout(r, 0)); // nextTick equivalent without import
    const el = bulkMapEl.value;
    if (!el || bulkMapInstance) return;

    const lat = props.defaultLat ?? 0;
    const lng = props.defaultLng ?? 0;

    if (props.mapProvider === 'osm') {
        const L = await loadLeaflet();
        bulkMapInstance = L.map(el, { center: [lat, lng], zoom: 2 });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors', maxZoom: 21,
        }).addTo(bulkMapInstance);
        bulkMapInstance.on('click', e => {
            placeBulkMapPin(e.latlng.lat, e.latlng.lng);
            bulkTemplate.value = { ...bulkTemplate.value, lat: e.latlng.lat, lng: e.latlng.lng };
        });
    } else {
        await loadGoogleMapsScript(props.apiKey);
        bulkMapInstance = new window.google.maps.Map(el, {
            center: { lat, lng }, zoom: 2, mapTypeId: bulkMapType.value,
        });
        bulkMapInstance.addListener('click', e => {
            placeBulkMapPin(e.latLng.lat(), e.latLng.lng());
            bulkTemplate.value = { ...bulkTemplate.value, lat: e.latLng.lat(), lng: e.latLng.lng() };
        });
    }
}

function destroyBulkMap() {
    if (bulkMapInstance) {
        try {
            if (props.mapProvider === 'osm') bulkMapInstance.remove();
            else if (bulkMapMarker) bulkMapMarker.setMap(null);
        } catch {}
    }
    bulkMapInstance = null;
    bulkMapMarker = null;
}

function placeBulkMapPin(lat, lng) {
    if (props.mapProvider === 'osm') {
        const L = getLeaflet();
        if (!L) return;
        if (!bulkMapMarker) {
            bulkMapMarker = L.marker([lat, lng], { draggable: true }).addTo(bulkMapInstance);
            bulkMapMarker.on('dragend', e => {
                const p = e.target.getLatLng();
                bulkTemplate.value = { ...bulkTemplate.value, lat: p.lat, lng: p.lng };
            });
        } else {
            bulkMapMarker.setLatLng([lat, lng]);
        }
        bulkMapInstance.panTo([lat, lng]);
    } else {
        if (!bulkMapMarker) {
            bulkMapMarker = new window.google.maps.Marker({
                position: { lat, lng }, map: bulkMapInstance, draggable: true,
            });
            bulkMapMarker.addListener('dragend', () => {
                const pos = bulkMapMarker.getPosition();
                bulkTemplate.value = { ...bulkTemplate.value, lat: pos.lat(), lng: pos.lng() };
            });
        } else {
            bulkMapMarker.setPosition({ lat, lng });
        }
        bulkMapInstance.panTo({ lat, lng });
    }
}

// ─── Search ─────────────────────────────────────────────────────────────────────
async function searchBulk() {
    const q = bulkSearchQuery.value.trim();
    if (!q || bulkSearching.value) return;
    bulkSearching.value = true;
    bulkSearchError.value = '';

    try {
        const result = await geocodeAddress(q, props.mapProvider, $axios);
        if (!result) { bulkSearchError.value = 'No results found.'; return; }
        bulkTemplate.value = { ...bulkTemplate.value, lat: result.lat, lng: result.lng };
        if (bulkMapInstance) placeBulkMapPin(result.lat, result.lng);
    } catch (err) {
        bulkSearchError.value = err?.message || 'Search failed.';
    } finally {
        bulkSearching.value = false;
    }
}

// ─── Asset update ────────────────────────────────────────────────────────────────
async function onBulkAssetUpdate(ids) {
    bulkAssetIds.value = ids ?? [];
    if (!ids?.length) {
        bulkTemplate.value = { ...bulkTemplate.value, marker_icon_url: '', marker_asset_id: '' };
        return;
    }
    try {
        const assets = await postCp('assets-fieldtype', { assets: ids }, $axios);
        if (assets?.[0]?.url) {
            bulkTemplate.value = {
                ...bulkTemplate.value,
                marker_icon_url: assets[0].url,
                marker_asset_id: ids[0],
            };
        }
    } catch (e) {
        console.error('[BulkEditPanel] Asset resolve failed:', e);
    }
}

// ─── Apply ───────────────────────────────────────────────────────────────────────
function applyBulk() {
    emit('apply', {
        template: { ...bulkTemplate.value },
        fields: { ...bulkFields.value },
        scope: bulkScope.value,
    });
}
</script>
