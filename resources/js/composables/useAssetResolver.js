import { postCp } from './useGeocoder.js';

/**
 * useAssetResolver — resolve asset ids to URLs via the CP assets fieldtype.
 *
 * The Statamic AssetsFieldtype stores asset ids; to display the icon URL
 * (and pass it to the frontend in the field value) we need to resolve the
 * id → metadata via /cp/assets-fieldtype.
 *
 * Pure network helper. Returns null on error or when the resolve fails.
 */
export function useAssetResolver({ _axios }) {
    /**
     * Resolve a single asset id to its public URL.
     *
     * @param {string} id
     * @returns {Promise<string | null>}
     */
    async function resolveUrl(id) {
        if (!id) return null;
        try {
            const assets = await postCp('assets-fieldtype', { assets: [id] }, _axios);
            return assets?.[0]?.url ?? null;
        } catch (e) {
            console.error('[useAssetResolver] Asset resolve failed:', e);
            return null;
        }
    }

    return { resolveUrl };
}
