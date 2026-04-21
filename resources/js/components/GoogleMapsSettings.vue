<template>
    <div>
        <div class="flex items-center justify-between" style="margin-top: 32px; margin-bottom: 2rem;">
            <h1 class="text-[25px] leading-[1.25] st-text-legibility font-medium antialiased flex items-center gap-2.5 md:flex-1">Google Maps – Default Location</h1>
        </div>

        <div class="card p-6">
            <p class="text-sm text-gray mb-4">
                Set the default coordinates that appear when a <code>google_maps</code> field has no saved value yet. Leave blank to show an empty field.
            </p>

            <div class="flex gap-4 mb-6">
                <div class="flex-1">
                    <label class="publish-field-label">Latitude</label>
                    <input
                        type="number"
                        step="any"
                        class="input-text w-full"
                        v-model.number="lat"
                        placeholder="e.g. 51.5074"
                    />
                </div>
                <div class="flex-1">
                    <label class="publish-field-label">Longitude</label>
                    <input
                        type="number"
                        step="any"
                        class="input-text w-full"
                        v-model.number="lng"
                        placeholder="e.g. -0.1278"
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
                        placeholder="13"
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
import { ref } from 'vue';

const props = defineProps({
    initialDefaults: { type: Object, default: () => ({ lat: null, lng: null, zoom: 13 }) },
    saveUrl: { type: String, required: true },
});

const lat = ref(props.initialDefaults.lat !== null ? Number(props.initialDefaults.lat) : null);
const lng = ref(props.initialDefaults.lng !== null ? Number(props.initialDefaults.lng) : null);
const zoom = ref(Number(props.initialDefaults.zoom) || 13);

const isSaving = ref(false);
const saveSuccess = ref(false);
const saveError = ref('');

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
