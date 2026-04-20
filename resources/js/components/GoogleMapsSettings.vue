<template>
    <div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">Google Maps – Default Location</h1>
        </div>

        <div class="card p-6">
            <p class="text-sm text-gray mb-4">
                Set the default center location that appears when a map field has no saved value yet.
            </p>

            <div class="gmf-search mb-4">
                <div class="flex gap-2">
                    <input
                        v-model="searchQuery"
                        type="text"
                        class="input-text flex-1"
                        placeholder="Search for an address..."
                        @keydown.enter.prevent="handleSearch"
                    />
                    <button
                        class="btn-primary"
                        :disabled="!searchQuery || isSearching"
                        @click="handleSearch"
                    >
                        {{ isSearching ? 'Searching...' : 'Search' }}
                    </button>
                </div>
                <p v-if="searchError" class="text-red text-sm mt-1">{{ searchError }}</p>
            </div>

            <div
                ref="mapContainer"
                class="mb-4"
                style="height: 400px; border-radius: 6px; overflow: hidden; border: 1px solid var(--color-border-input);"
            >
                <div v-if="!apiKey" class="gmf-placeholder" style="height: 100%; display: flex; align-items: center; justify-content: center;">
                    <p>Set <code>GOOGLE_MAPS_API_KEY</code> in your .env file</p>
                </div>
            </div>

            <div class="flex gap-4 mb-6">
                <div class="flex-1">
                    <label class="publish-field-label">Latitude</label>
                    <input
                        type="number"
                        step="any"
                        class="input-text w-full"
                        v-model.number="lat"
                        @change="syncMapCenter"
                    />
                </div>
                <div class="flex-1">
                    <label class="publish-field-label">Longitude</label>
                    <input
                        type="number"
                        step="any"
                        class="input-text w-full"
                        v-model.number="lng"
                        @change="syncMapCenter"
                    />
                </div>
                <div style="width: 130px">
                    <label class="publish-field-label">Zoom</label>
                    <input
                        type="number"
                        min="1"
                        max="21"
                        class="input-text w-full"
                        v-model.number="zoom"
                        @change="syncMapZoom"
                    />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button class="btn-primary" :disabled="isSaving" @click="save">
                    {{ isSaving ? 'Saving...' : 'Save' }}
                </button>
                <span v-if="saveSuccess" style="color: var(--color-green);" class="text-sm">Settings saved!</span>
                <span v-if="saveError" style="color: var(--color-red);" class="text-sm">{{ saveError }}</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
    initialDefaults: { type: Object, default: () => ({ lat: 51.5074, lng: -0.1278, zoom: 13 }) },
    saveUrl: { type: String, required: true },
});

const mapContainer = ref(null);
const map = ref(null);
const marker = ref(null);

const lat = ref(Number(props.initialDefaults.lat));
const lng = ref(Number(props.initialDefaults.lng));
const zoom = ref(Number(props.initialDefaults.zoom) || 13);

const searchQuery = ref('');
const searchError = ref('');
const isSearching = ref(false);

const isSaving = ref(false);
const saveSuccess = ref(false);
const saveError = ref('');

const globalConfig = Statamic.$config.get('googleMaps') || {};
const apiKey = globalConfig.api_key || '';

onMounted(() => {
    if (apiKey) loadGoogleMaps();
});

onBeforeUnmount(() => {
    if (map.value) google.maps.event.clearInstanceListeners(map.value);
    if (marker.value) google.maps.event.clearInstanceListeners(marker.value);
});

function loadGoogleMaps() {
    if (window.google && window.google.maps) { initMap(); return; }
    if (window._gmapsLoading) { window._gmapsLoading.then(() => initMap()); return; }
    window._gmapsLoading = new Promise((resolve) => {
        const s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key=' + apiKey + '&libraries=places';
        s.async = true;
        s.onload = resolve;
        document.head.appendChild(s);
    });
    window._gmapsLoading.then(() => initMap());
}

function initMap() {
    map.value = new google.maps.Map(mapContainer.value, {
        center: { lat: lat.value, lng: lng.value },
        zoom: zoom.value,
        mapTypeId: globalConfig.map_type || 'roadmap',
        streetViewControl: false,
    });

    marker.value = new google.maps.Marker({
        map: map.value,
        draggable: true,
        position: { lat: lat.value, lng: lng.value },
        animation: google.maps.Animation.DROP,
    });

    map.value.addListener('zoom_changed', () => {
        zoom.value = map.value.getZoom();
    });

    marker.value.addListener('dragend', () => {
        const pos = marker.value.getPosition();
        lat.value = parseFloat(pos.lat().toFixed(7));
        lng.value = parseFloat(pos.lng().toFixed(7));
    });

    map.value.addListener('click', (e) => {
        const pos = { lat: e.latLng.lat(), lng: e.latLng.lng() };
        marker.value.setPosition(pos);
        map.value.panTo(pos);
        lat.value = parseFloat(pos.lat.toFixed(7));
        lng.value = parseFloat(pos.lng.toFixed(7));
    });
}

function syncMapCenter() {
    if (!map.value) return;
    const pos = { lat: lat.value, lng: lng.value };
    map.value.setCenter(pos);
    marker.value.setPosition(pos);
}

function syncMapZoom() {
    if (!map.value) return;
    map.value.setZoom(zoom.value);
}

async function handleSearch() {
    if (!searchQuery.value) return;
    isSearching.value = true;
    searchError.value = '';

    try {
        const res = await fetch('/cp/google-maps/geocode', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': Statamic.$config.get('csrfToken'),
            },
            body: JSON.stringify({ address: searchQuery.value }),
        });
        const data = await res.json();

        if (data.success) {
            lat.value = parseFloat(data.result.lat.toFixed(7));
            lng.value = parseFloat(data.result.lng.toFixed(7));

            if (map.value) {
                const pos = { lat: lat.value, lng: lng.value };
                marker.value.setPosition(pos);
                map.value.panTo(pos);
                map.value.setZoom(14);
                zoom.value = 14;
            }

            searchQuery.value = '';
        } else {
            searchError.value = 'No results found.';
        }
    } catch (e) {
        searchError.value = 'Search failed. Check your API key.';
    } finally {
        isSearching.value = false;
    }
}

async function save() {
    isSaving.value = true;
    saveSuccess.value = false;
    saveError.value = '';

    try {
        const res = await fetch(props.saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': Statamic.$config.get('csrfToken'),
            },
            body: JSON.stringify({ lat: lat.value, lng: lng.value, zoom: zoom.value }),
        });
        const data = await res.json();

        if (data.success) {
            saveSuccess.value = true;
            setTimeout(() => { saveSuccess.value = false; }, 3000);
        } else {
            saveError.value = 'Failed to save settings.';
        }
    } catch (e) {
        saveError.value = 'Failed to save settings.';
    } finally {
        isSaving.value = false;
    }
}
</script>
