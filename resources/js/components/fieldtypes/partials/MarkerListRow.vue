<template>
    <!-- Marker item wrapper -->
    <div class="gmf-marker-item rounded-lg overflow-hidden"
         :class="item.enabled !== false
             ? 'border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800'
             : 'border border-dashed border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900'">

        <!-- ── Row header ── -->
        <div class="flex items-center gap-2 px-3 py-2 select-none">
            <!-- Drag handle -->
            <span v-if="!readOnly"
                  class="gmf-marker-handle cursor-grab active:cursor-grabbing text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M8 6a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm8 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM8 13.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm8 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3ZM8 21a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Zm8 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3Z"/>
                </svg>
            </span>

            <!-- Individual on/off toggle -->
            <button v-if="!readOnly" type="button"
                    class="gmf-toggle-btn gmf-toggle-sm flex-shrink-0"
                    :class="item.enabled !== false ? 'gmf-toggle-on' : 'gmf-toggle-off'"
                    :title="item.enabled !== false ? 'Disable marker' : 'Enable marker'"
                    @click.prevent="emit('toggle-enabled')">
                <span class="gmf-toggle-knob"></span>
            </button>

            <!-- Name + coordinates -->
            <span class="flex-1 min-w-0 leading-tight">
                <span class="block text-sm font-medium truncate"
                      :class="item.enabled !== false ? 'text-gray-700 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500'">
                    {{ item.address || (item.lat != null ? (item.lat.toFixed(4) + ', ' + item.lng.toFixed(4)) : 'New marker') }}
                </span>
                <span v-if="item.lat != null"
                      class="block text-xs font-mono text-gray-400 dark:text-gray-500 truncate">
                    {{ item.lat.toFixed(7) }}, {{ item.lng.toFixed(7) }}
                </span>
                <span v-else class="block text-xs italic text-gray-400 dark:text-gray-500">
                    click map to set location
                </span>
            </span>

            <!-- Status badge -->
            <span class="gmf-status-badge flex-shrink-0"
                  :class="item.enabled !== false ? 'gmf-status-on' : 'gmf-status-off'">
                {{ item.enabled !== false ? 'on' : 'off' }}
            </span>

            <!-- Edit / Close button -->
            <button v-if="!readOnly" type="button"
                    class="gmf-edit-btn flex-shrink-0"
                    :class="expanded ? 'gmf-edit-btn-open' : ''"
                    @click.prevent="emit('toggle-expanded')">
                {{ expanded ? 'Close ▴' : 'Edit ▾' }}
            </button>

            <!-- Remove -->
            <button v-if="!readOnly" type="button"
                    class="gmf-remove-btn flex-shrink-0"
                    title="Remove marker"
                    @click.stop="emit('remove')">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- ── Expanded edit panel ── -->
        <div v-if="expanded"
             class="flex flex-col gap-3 px-3 pb-3 border-t border-gray-100 dark:border-gray-700 pt-3">

            <!-- Map type tabs (Google only) -->
            <div v-if="mapProvider === 'google'" class="flex gap-1">
                <button type="button" class="gmf-maptab-btn"
                        :class="(mapType || 'roadmap') === 'roadmap' ? 'gmf-maptab-active' : 'gmf-maptab-inactive'"
                        @click.prevent="emit('set-map-type', 'roadmap')">Map</button>
                <button type="button" class="gmf-maptab-btn"
                        :class="(mapType || 'roadmap') === 'satellite' ? 'gmf-maptab-active' : 'gmf-maptab-inactive'"
                        @click.prevent="emit('set-map-type', 'satellite')">Satellite</button>
            </div>

            <!-- Mini-map — the parent receives the DOM element via @map-ref to manage
                 the map instance lifecycle (init / destroy) outside of this component. -->
            <div :ref="onMapDivRef"
                 class="gmf-map rounded-lg overflow-hidden border border-gray-300 dark:border-gray-600"
                 :style="{ height: Math.round(mapHeight * 0.65) + 'px' }"></div>

            <!-- Search -->
            <div v-if="!readOnly" class="flex flex-col gap-1">
                <div class="flex gap-2">
                    <input v-model="localSearchQuery" type="text" class="gmf-input flex-1"
                           placeholder="Search for a location…"
                           :disabled="isSearching"
                           @keydown.enter.prevent="onSearch" />
                    <button type="button" class="gmf-search-btn"
                            :disabled="isSearching || !localSearchQuery.trim()"
                            @click.prevent="onSearch">
                        <svg v-if="isSearching" xmlns="http://www.w3.org/2000/svg"
                             class="size-4 gmf-spin" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                        </svg>
                        <svg v-else xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                        </svg>
                        {{ isSearching ? 'Searching…' : 'Search' }}
                    </button>
                </div>
                <p v-if="searchError" class="text-xs text-red-500 dark:text-red-400">{{ searchError }}</p>
            </div>

            <!-- Coordinates: lat / lng / zoom -->
            <div class="grid grid-cols-3 gap-2">
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Lat</label>
                    <input type="number" step="any" class="gmf-input" placeholder="0.000000"
                           :value="item.lat" :disabled="readOnly"
                           @change="emit('update-coord', 'lat', $event.target.value)" />
                </div>
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Lng</label>
                    <input type="number" step="any" class="gmf-input" placeholder="0.000000"
                           :value="item.lng" :disabled="readOnly"
                           @change="emit('update-coord', 'lng', $event.target.value)" />
                </div>
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Zoom</label>
                    <input type="number" min="1" max="21" class="gmf-input" placeholder="13"
                           :value="item.zoom" :disabled="readOnly"
                           @change="emit('update-coord', 'zoom', $event.target.value)" />
                </div>
            </div>

            <!-- Infobox + icon (only when location is set and not readOnly) -->
            <template v-if="item.lat != null && !readOnly">
                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">Infobox Title</label>
                    <input type="text" class="gmf-input" placeholder="Leave empty for no popup"
                           :value="item.infobox_title || ''"
                           @input="emit('update-field', 'infobox_title', $event.target.value)" />
                </div>

                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">
                        Infobox Content
                        <span class="font-normal normal-case text-gray-400 ml-1">HTML supported</span>
                    </label>
                    <textarea class="gmf-input" placeholder="Leave empty for no popup" rows="3"
                              :value="item.infobox_content || ''"
                              @input="emit('update-field', 'infobox_content', $event.target.value)"></textarea>
                </div>

                <div class="gmf-field">
                    <label class="text-2xs uppercase font-semibold text-gray-500">
                        Marker Icon
                        <span class="font-normal normal-case text-gray-400 ml-1">Leave empty for default</span>
                    </label>
                    <assets-fieldtype
                        v-if="assetMeta.container"
                        :value="assetIds"
                        :config="assetConfig"
                        :meta="assetMeta"
                        :handle="'marker_icon_' + item._id"
                        @input="(ids) => emit('asset-update', ids)"
                        @update:value="(ids) => emit('asset-update', ids)"
                    />
                    <p v-else class="text-xs text-gray-400 italic mt-1">No asset containers configured.</p>
                </div>

                <div v-if="item.marker_icon_url" class="grid grid-cols-2 gap-2">
                    <div class="gmf-field">
                        <label class="text-2xs uppercase font-semibold text-gray-500">Icon Width (px)</label>
                        <input type="number" min="1" class="gmf-input" placeholder="40"
                               :value="item.marker_icon_width || ''"
                               @change="emit('update-field', 'marker_icon_width', parseInt($event.target.value) || 0)" />
                    </div>
                    <div class="gmf-field">
                        <label class="text-2xs uppercase font-semibold text-gray-500">Icon Height (px)</label>
                        <input type="number" min="1" class="gmf-input" placeholder="40"
                               :value="item.marker_icon_height || ''"
                               @change="emit('update-field', 'marker_icon_height', parseInt($event.target.value) || 0)" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="button" class="gmf-clear-btn" @click.prevent="emit('clear-location')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-3.5" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2"
                             stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>
                        </svg>
                        Clear location
                    </button>
                </div>
            </template>

        </div><!-- /expanded -->
    </div><!-- /marker-item -->
</template>

<script setup>
import { ref, watch } from 'vue';

// ─── Props ─────────────────────────────────────────────────────────────────────
const props = defineProps({
    /** The marker data object */
    item: { type: Object, required: true },
    /** Whether this row's edit panel is open */
    expanded: { type: Boolean, default: false },
    readOnly: { type: Boolean, default: false },
    mapProvider: { type: String, default: 'google' },
    mapHeight: { type: Number, default: 300 },
    /** Passed to the assets-fieldtype component */
    assetMeta: { type: Object, default: () => ({}) },
    assetConfig: { type: Object, default: () => ({}) },
    /** Currently selected asset IDs for this marker's icon */
    assetIds: { type: Array, default: () => [] },
    /** Searching state (controlled by parent after emitting 'search') */
    isSearching: { type: Boolean, default: false },
    /** Error message from the last search attempt */
    searchError: { type: String, default: '' },
    /** Current Google Maps map type for this marker's mini-map */
    mapType: { type: String, default: 'roadmap' },
});

// ─── Emits ─────────────────────────────────────────────────────────────────────
const emit = defineEmits([
    /** Toggle the expanded/collapsed state of this row */
    'toggle-expanded',
    /** Toggle marker enabled/disabled */
    'toggle-enabled',
    /** Remove this marker entirely */
    'remove',
    /** Update a named field on the marker object — (field: string, value: any) */
    'update-field',
    /** Update a numeric coordinate field — (field: 'lat'|'lng'|'zoom', rawValue: string) */
    'update-coord',
    /** Clear all location data for this marker */
    'clear-location',
    /** Trigger a geocode search — (query: string) */
    'search',
    /** New asset IDs selected for the marker icon — (ids: string[]) */
    'asset-update',
    /** Switch the Google Maps map type — (type: 'roadmap'|'satellite') */
    'set-map-type',
    /**
     * Fired whenever the mini-map div mounts (el: HTMLElement) or
     * unmounts (el: null).  The parent uses this to manage the map instance
     * lifecycle via setMarkerMapRef() + initMarkerMiniMap().
     */
    'map-ref',
]);

// ─── Local state ───────────────────────────────────────────────────────────────

/** Search query is purely local UI state — cleared when the row collapses */
const localSearchQuery = ref('');
let _searchDebounceTimer = null;

watch(localSearchQuery, (q) => {
    clearTimeout(_searchDebounceTimer);
    if (!q.trim()) return;
    _searchDebounceTimer = setTimeout(onSearch, 500);
});

// ─── Handlers ──────────────────────────────────────────────────────────────────

/**
 * Called by Vue's :ref callback when the mini-map div mounts or unmounts.
 * Forwards the element (or null) to the parent so it can control the map instance.
 */
function onMapDivRef(el) {
    emit('map-ref', el);
}

function onSearch() {
    clearTimeout(_searchDebounceTimer);
    emit('search', localSearchQuery.value);
}
</script>
