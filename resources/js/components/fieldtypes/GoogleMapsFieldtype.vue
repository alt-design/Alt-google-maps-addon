<template>
    <div class="flex flex-col gap-3 p-3 bg-gray-100 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-xl">

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
        </template>

        <!-- Footer: clear -->
        <div v-if="hasLocation && !props.readOnly" class="flex justify-end">
            <button type="button" class="gmf-clear-btn" @click.prevent="clearLocation">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18M6 6l12 12"/>
                </svg>
                Clear location
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick, getCurrentInstance } from 'vue';

// Module-level cache so multiple fieldtype instances share one load promise
let googleMapsLoadPromise = null;
let leafletLib = null;

// Capture instance synchronously during setup — getCurrentInstance() returns null in async calls
const _instance = getCurrentInstance();

const props = defineProps({
    value: { default: null },
    config: { type: Object, default: () => ({}) },
    handle: { type: String, default: '' },
    meta: { type: Object, default: () => ({}) },
    readOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:value']);

function update(value) {
    internalValue.value = { ...value }; // update local state immediately — don't wait for props to round-trip
    emit('update:value', value);
}

const globalConfig = computed(() => Statamic.$config.get('googleMaps') || {});
const mapProvider = computed(() =>
    props.config.map_provider || globalConfig.value.map_provider || 'google'
);
const mapHeight = computed(() => globalConfig.value.map_height || 420);

const defaultZoom = computed(() =>
    parseInt(props.config.default_zoom) || globalConfig.value.defaults?.zoom || 13
);
const defaultLat = computed(() =>
    parseFloat(props.config.default_lat) || globalConfig.value.defaults?.lat || null
);
const defaultLng = computed(() =>
    parseFloat(props.config.default_lng) || globalConfig.value.defaults?.lng || null
);

// Ref (not computed) so inputs update immediately without waiting for Statamic's props cycle
const internalValue = ref(
    props.value && typeof props.value === 'object' && props.value.lat != null
        ? { ...props.value }
        : {
            lat: defaultLat.value,
            lng: defaultLng.value,
            address: '',
            zoom: defaultZoom.value,
            infobox_title: '',
            infobox_content: '',
        }
);

const hasLocation = computed(() =>
    internalValue.value.lat != null && internalValue.value.lng != null
);

// ─── Search state ──────────────────────────────────────────────────────────────

const searchQuery = ref('');
const isSearching = ref(false);
const searchError = ref('');

// ─── Map state ────────────────────────────────────────────────────────────────

const mapEl = ref(null);
let mapInstance = null;
let markerInstance = null;
const mapReady = ref(false);

// ─── Map initialisation ───────────────────────────────────────────────────────

async function initMap() {
    if (!mapEl.value) return;
    try {
        if (mapProvider.value === 'osm') {
            await initLeaflet();
        } else {
            await initGoogleMaps();
        }
        mapReady.value = true;
    } catch (err) {
        console.error('[GoogleMapsFieldtype] Map init failed:', err);
    }
}

async function initLeaflet() {
    if (!leafletLib) {
        leafletLib = (await import('leaflet')).default;
    }
    const L = leafletLib;

    // Fix default icon paths broken by Vite bundling
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
        const { lat: clat, lng: clng } = e.latlng;
        placeOrMoveLeafletMarker(clat, clng);
        onMapPick(clat, clng);
    });
}

async function initGoogleMaps() {
    if (!globalConfig.value.api_key) {
        mapEl.value.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#6b7280;font-size:0.875rem;text-align:center;padding:1rem;">No Google Maps API key configured.<br>Add <code>GOOGLE_MAPS_API_KEY</code> to your <code>.env</code>,<br>or switch to OpenStreetMap in Tools → Google Maps.</div>';
        return;
    }
    await loadGoogleMapsScript();

    const lat  = internalValue.value.lat  ?? (defaultLat.value ?? 0);
    const lng  = internalValue.value.lng  ?? (defaultLng.value ?? 0);
    const zoom = hasLocation.value ? (internalValue.value.zoom || defaultZoom.value) : 2;

    mapInstance = new window.google.maps.Map(mapEl.value, {
        center: { lat, lng },
        zoom,
        mapTypeId: globalConfig.value.map_type || 'roadmap',
        gestureHandling: props.readOnly ? 'none' : 'auto',
    });

    if (hasLocation.value) {
        markerInstance = new window.google.maps.Marker({
            position: { lat, lng },
            map: mapInstance,
            draggable: !props.readOnly,
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
        const clat = e.latLng.lat();
        const clng = e.latLng.lng();
        placeOrMoveGoogleMarker(clat, clng);
        onMapPick(clat, clng);
    });
}

function loadGoogleMapsScript() {
    if (window.google?.maps) return Promise.resolve();
    if (!googleMapsLoadPromise) {
        googleMapsLoadPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${globalConfig.value.api_key}`;
            script.async = true;
            script.addEventListener('load', resolve);
            script.addEventListener('error', () => {
                googleMapsLoadPromise = null; // allow retry on next mount
                reject(new Error('Failed to load Google Maps API'));
            });
            document.head.appendChild(script);
        });
    }
    return googleMapsLoadPromise;
}

// ─── Marker helpers ───────────────────────────────────────────────────────────

function placeOrMoveLeafletMarker(lat, lng) {
    if (!markerInstance) {
        markerInstance = leafletLib.marker([lat, lng], { draggable: true }).addTo(mapInstance);
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
            position: { lat, lng },
            map: mapInstance,
            draggable: true,
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

// ─── Actions ─────────────────────────────────────────────────────────────────

// Prevents the lat/lng watcher from redundantly moving the marker when the
// update already originated from a map event (drag, click).
let _fromMapInteraction = false;

async function onMapPick(lat, lng) {
    _fromMapInteraction = true;
    update({ ...internalValue.value, lat, lng });
    await nextTick();
    _fromMapInteraction = false;

    try {
        let address = null;
        if (mapProvider.value === 'osm') {
            const data = await postCp('google-maps/reverse-geocode', { lat, lng, provider: 'osm' });
            if (data.success) address = data.result.address;
        } else {
            // Use the JS API Geocoder — works with browser-restricted API keys
            address = await googleReverseGeocode(lat, lng);
        }
        if (address) {
            update({ ...internalValue.value, lat, lng, address });
        }
    } catch { /* reverse geocode failure is non-fatal */ }
}

async function search() {
    const q = searchQuery.value.trim();
    if (!q || isSearching.value) return;

    isSearching.value = true;
    searchError.value = '';

    try {
        let lat, lng, address;

        if (mapProvider.value === 'osm') {
            const data = await postCp('google-maps/geocode', { address: q, provider: 'osm' });
            if (!data.success) {
                searchError.value = data.message || 'Location not found.';
                return;
            }
            ({ lat, lng, address } = data.result);
        } else {
            // Use the JS API Geocoder — works with browser-restricted API keys
            const result = await googleGeocode(q);
            if (!result) {
                searchError.value = 'No results found.';
                return;
            }
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

// ─── Client-side Google geocoding (uses Maps JS API — respects browser-restricted keys) ──

function googleGeocode(address) {
    return new Promise((resolve, reject) => {
        if (!window.google?.maps?.Geocoder) {
            reject(new Error('Google Maps is not loaded.'));
            return;
        }
        const geocoder = new window.google.maps.Geocoder();
        geocoder.geocode({ address }, (results, status) => {
            if (status === 'OK' && results?.length) {
                resolve({
                    lat:     results[0].geometry.location.lat(),
                    lng:     results[0].geometry.location.lng(),
                    address: results[0].formatted_address,
                });
            } else if (status === 'ZERO_RESULTS') {
                resolve(null);
            } else {
                reject(new Error(`Geocoding failed (${status}).`));
            }
        });
    });
}

function googleReverseGeocode(lat, lng) {
    return new Promise((resolve) => {
        if (!window.google?.maps?.Geocoder) { resolve(null); return; }
        const geocoder = new window.google.maps.Geocoder();
        geocoder.geocode({ location: { lat, lng } }, (results, status) => {
            resolve(status === 'OK' && results?.length ? results[0].formatted_address : null);
        });
    });
}

async function postCp(path, body) {
    // In Vue 2.7, getCurrentInstance().proxy holds the component's `this` — which has $axios.
    // (Vue 3's appContext.config.globalProperties path doesn't exist in Vue 2.7)
    const $axios = _instance?.proxy?.$axios;
    if ($axios) {
        const resp = await $axios.post(`/cp/${path}`, body);
        return resp.data;
    }
    // Fetch fallback — used if $axios is unavailable for any reason
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const resp = await fetch(`/cp/${path}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });
    if (!resp.ok) {
        const text = await resp.text();
        throw new Error(`HTTP ${resp.status}: ${text.slice(0, 200)}`);
    }
    return resp.json();
}

// ─── Coordinate inputs ────────────────────────────────────────────────────────

function updateCoord(field, rawValue) {
    const num = field === 'zoom' ? parseInt(rawValue) : parseFloat(rawValue);
    if (isNaN(num)) return;
    update({ ...internalValue.value, [field]: num });
}

function clearLocation() {
    update({ lat: null, lng: null, address: '', zoom: defaultZoom.value, infobox_title: '', infobox_content: '' });
    searchQuery.value = '';
    searchError.value = '';
}

// ─── Watchers ─────────────────────────────────────────────────────────────────

watch(
    () => [internalValue.value.lat, internalValue.value.lng],
    ([lat, lng]) => {
        if (!mapReady.value || lat == null || lng == null) return;
        if (_fromMapInteraction) return; // marker is already at this position
        moveMarker(lat, lng);
    }
);

watch(
    () => internalValue.value.zoom,
    zoom => {
        if (!mapReady.value || !zoom) return;
        setMapZoom(zoom);
    }
);

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(() => initMap());

onBeforeUnmount(() => {
    if (mapProvider.value === 'osm' && mapInstance) {
        mapInstance.remove();
    }
    mapInstance = null;
    markerInstance = null;
});
</script>

<style scoped>
.gmf-pin-badge {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 9999px;
    background: white;
    border: 1px solid rgb(209 213 219);
    box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05);
    color: rgb(75 85 99);
}

:is(.dark .gmf-pin-badge) {
    background: rgb(31 41 55);
    border-color: rgb(75 85 99);
    color: rgb(209 213 219);
}

.gmf-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.gmf-input {
    width: 100%;
    padding: 0.375rem 0.5rem;
    font-size: 0.875rem;
    color: rgb(17 24 39);
    background: white;
    border: 1px solid rgb(209 213 219);
    border-radius: 0.375rem;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.gmf-input:focus {
    border-color: rgb(139 92 246);
    box-shadow: 0 0 0 3px rgb(139 92 246 / 0.15);
}

.gmf-input:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

:is(.dark .gmf-input) {
    color: rgb(243 244 246);
    background: rgb(17 24 39);
    border-color: rgb(75 85 99);
}

:is(.dark .gmf-input):focus {
    border-color: rgb(139 92 246);
}

.gmf-map {
    width: 100%;
}

.gmf-search-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
    font-weight: 500;
    color: white;
    background: rgb(139 92 246);
    border: none;
    border-radius: 0.375rem;
    cursor: pointer;
    white-space: nowrap;
    transition: background 0.15s;
    flex-shrink: 0;
}

.gmf-search-btn:hover:not(:disabled) {
    background: rgb(124 58 237);
}

.gmf-search-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.gmf-clear-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    font-size: 0.75rem;
    color: rgb(156 163 175);
    background: transparent;
    border: none;
    cursor: pointer;
    padding: 0.25rem 0.375rem;
    border-radius: 0.375rem;
    transition: color 0.15s, background 0.15s;
}

.gmf-clear-btn:hover {
    color: rgb(239 68 68);
    background: rgb(254 226 226);
}

:is(.dark .gmf-clear-btn):hover {
    color: rgb(252 165 165);
    background: rgb(127 29 29 / 0.3);
}

@keyframes gmf-spin {
    to { transform: rotate(360deg); }
}

.gmf-spin {
    animation: gmf-spin 0.8s linear infinite;
}
</style>
