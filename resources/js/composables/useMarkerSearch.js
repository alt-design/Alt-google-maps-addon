import { postCp, googleGeocode, googleReverseGeocode } from './useGeocoder.js';

/**
 * useMarkerSearch — provider-agnostic geocoding wrappers with friendly errors.
 *
 * Pure network layer — does not own UI state (loading flags, error messages
 * live in the calling composable). Returns:
 *   - geocode(query):       resolves to {lat, lng, address}, or throws
 *                           an Error whose .userMessage is safe to show
 *   - reverseGeocode(lat, lng): resolves to string | null (errors swallowed)
 */
const PERMISSION_MESSAGE =
    'You do not have permission to use the address search. Ask an administrator to grant it, or enter the coordinates by hand.';

function isForbidden(err) {
    return err?.response?.status === 403 || /HTTP 403/.test(err?.message || '');
}

export function useMarkerSearch({ _axios, mapProvider }) {
    async function postGeocode(query) {
        try {
            return await postCp('google-maps/geocode', { address: query, provider: 'osm' }, _axios);
        } catch (err) {
            if (isForbidden(err)) {
                err.userMessage = PERMISSION_MESSAGE;
            }
            throw err;
        }
    }

    async function geocode(query) {
        const q = (query || '').trim();
        if (!q) {
            const err = new Error('Empty query.');
            err.userMessage = 'Empty query.';
            throw err;
        }

        if (mapProvider.value === 'osm') {
            const data = await postGeocode(q);
            if (!data.success) {
                const err = new Error(data.message || 'Location not found.');
                err.userMessage = data.message || 'Location not found.';
                throw err;
            }
            return data.result;
        }

        const result = await googleGeocode(q);
        if (!result) {
            const err = new Error('No results found.');
            err.userMessage = 'No results found.';
            throw err;
        }
        return result;
    }

    async function reverseGeocode(lat, lng) {
        try {
            if (mapProvider.value === 'osm') {
                const data = await postCp(
                    'google-maps/reverse-geocode',
                    { lat, lng, provider: 'osm' },
                    _axios
                );
                return data?.success ? data.result.address : null;
            }
            return await googleReverseGeocode(lat, lng);
        } catch {
            return null;
        }
    }

    return { geocode, reverseGeocode };
}
