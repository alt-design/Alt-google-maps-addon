<template>
    <div class="gmf-index">
        <template v-if="hasValue">
            <svg class="gmf-index-icon" xmlns="http://www.w3.org/2000/svg"
                width="14" height="14" viewBox="0 0 24 24" fill="none"
                stroke="currentColor" stroke-width="2">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" />
                <circle cx="12" cy="10" r="3" />
            </svg>
            <span :title="fullText">{{ displayText }}</span>
        </template>
        <span v-else style="color: #9CA3AF;">-</span>
    </div>
</template>

<script>
import { IndexFieldtype } from '@statamic/cms';

export default {
    mixins: [IndexFieldtype],
    computed: {
        hasValue() { return this.value && this.value.lat != null; },
        displayText() {
            if (!this.hasValue) return '';
            if (this.value.address) {
                const a = this.value.address;
                return a.length > 45 ? a.substring(0, 45) + '...' : a;
            }
            return this.value.lat.toFixed(4) + ', ' + this.value.lng.toFixed(4);
        },
        fullText() {
            if (!this.hasValue) return '';
            return this.value.address || this.value.lat + ', ' + this.value.lng;
        },
    },
};
</script>

<style scoped>
.gmf-index { display: flex; align-items: center; gap: 6px; }
.gmf-index-icon { flex-shrink: 0; color: #6B7280; }
</style>
