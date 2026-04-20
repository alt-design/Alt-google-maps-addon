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
            <span v-else class="text-sm italic text-gray-400 dark:text-gray-500">
                No location set
            </span>
        </div>

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
import { computed } from 'vue';

const props = defineProps({
    value: { default: null },
    config: { type: Object, default: () => ({}) },
    handle: { type: String, default: '' },
    meta: { type: Object, default: () => ({}) },
    readOnly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:value']);

function update(value) {
    emit('update:value', value);
}

const globalConfig = computed(() => Statamic.$config.get('googleMaps') || {});

const defaultZoom = computed(() =>
    parseInt(props.config.default_zoom) || globalConfig.value.defaults?.zoom || 13
);

const internalValue = computed(() =>
    props.value && typeof props.value === 'object'
        ? props.value
        : { lat: null, lng: null, address: '', zoom: defaultZoom.value }
);

const hasLocation = computed(() =>
    internalValue.value.lat != null && internalValue.value.lng != null
);

function updateCoord(field, rawValue) {
    const num = field === 'zoom' ? parseInt(rawValue) : parseFloat(rawValue);
    if (isNaN(num)) return;
    update({ ...internalValue.value, [field]: num });
}

function clearLocation() {
    update({ lat: null, lng: null, address: '', zoom: defaultZoom.value });
}
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
</style>