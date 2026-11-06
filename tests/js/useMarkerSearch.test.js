import { describe, it, expect } from 'vitest';
import { ref } from 'vue';
import { useMarkerSearch } from '../../resources/js/composables/useMarkerSearch.js';

function searchWith(axiosPost) {
    return useMarkerSearch({ _axios: { post: axiosPost }, mapProvider: ref('osm') });
}

describe('useMarkerSearch geocode', () => {
    it('returns the result on success', async () => {
        const { geocode } = searchWith(async () => ({
            data: { success: true, result: { lat: 51.5, lng: -0.1, address: 'London' } },
        }));

        await expect(geocode('London')).resolves.toEqual({ lat: 51.5, lng: -0.1, address: 'London' });
    });

    it('explains a missing permission when the server answers 403', async () => {
        const forbidden = Object.assign(new Error('Request failed with status code 403'), {
            response: { status: 403 },
        });
        const { geocode } = searchWith(async () => {
            throw forbidden;
        });

        await expect(geocode('London')).rejects.toMatchObject({
            userMessage: expect.stringContaining('do not have permission'),
        });
    });

    it('passes the server message through when the search finds nothing', async () => {
        const { geocode } = searchWith(async () => ({ data: { success: false, message: 'No results found.' } }));

        await expect(geocode('Nowhere')).rejects.toMatchObject({ userMessage: 'No results found.' });
    });
});
