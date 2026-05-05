import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
    postCp,
    googleGeocode,
    googleReverseGeocode,
    geocodeAddress,
    reverseGeocodeAddress,
} from '../../resources/js/composables/useGeocoder.js';

// ─── postCp ───────────────────────────────────────────────────────────────────

describe('postCp — CSRF guard', () => {
    beforeEach(() => {
        // Remove any meta tag from previous tests
        document.head.querySelectorAll('meta[name="csrf-token"]').forEach((el) => el.remove());
    });

    it('throws when <meta name="csrf-token"> is absent', async () => {
        await expect(postCp('google-maps/geocode', { address: 'London' })).rejects.toThrow(
            'CSRF token not found'
        );
    });

    it('sends request with CSRF header when meta tag is present', async () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'test-token-abc';
        document.head.appendChild(meta);

        const mockFetch = vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ success: true }),
        });
        vi.stubGlobal('fetch', mockFetch);

        await postCp('google-maps/geocode', { address: 'London' });

        expect(mockFetch).toHaveBeenCalledWith(
            '/cp/google-maps/geocode',
            expect.objectContaining({
                method: 'POST',
                headers: expect.objectContaining({ 'X-CSRF-TOKEN': 'test-token-abc' }),
            })
        );

        vi.unstubAllGlobals();
    });

    it('throws on non-ok HTTP response', async () => {
        const meta = document.createElement('meta');
        meta.name = 'csrf-token';
        meta.content = 'tok';
        document.head.appendChild(meta);

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: false,
            status: 422,
            text: async () => 'Validation failed',
        }));

        await expect(postCp('google-maps/geocode', {})).rejects.toThrow('HTTP 422');

        vi.unstubAllGlobals();
    });

    it('uses $axios when provided, skipping CSRF meta lookup', async () => {
        const mockAxios = {
            post: vi.fn().mockResolvedValue({ data: { success: true } }),
        };

        // No meta tag in DOM — but $axios path should not need it
        const result = await postCp('google-maps/geocode', { address: 'Paris' }, mockAxios);

        expect(mockAxios.post).toHaveBeenCalledWith('/cp/google-maps/geocode', { address: 'Paris' });
        expect(result).toEqual({ success: true });
    });
});

// ─── googleGeocode ─────────────────────────────────────────────────────────────

describe('googleGeocode', () => {
    afterEach(() => {
        delete window.google;
    });

    it('rejects when window.google is not loaded', async () => {
        await expect(googleGeocode('London')).rejects.toThrow('Google Maps is not loaded');
    });

    it('resolves with lat/lng on OK status', async () => {
        window.google = {
            maps: {
                Geocoder: class {
                    geocode(_params, cb) {
                        cb(
                            [{ geometry: { location: { lat: () => 51.5, lng: () => -0.1 } }, formatted_address: 'London, UK' }],
                            'OK'
                        );
                    }
                },
            },
        };

        const result = await googleGeocode('London');
        expect(result).toEqual({ lat: 51.5, lng: -0.1, address: 'London, UK' });
    });

    it('resolves with null on ZERO_RESULTS', async () => {
        window.google = {
            maps: {
                Geocoder: class {
                    geocode(_params, cb) { cb([], 'ZERO_RESULTS'); }
                },
            },
        };

        const result = await googleGeocode('zzznowhere');
        expect(result).toBeNull();
    });

    it('rejects on other error statuses', async () => {
        window.google = {
            maps: {
                Geocoder: class {
                    geocode(_params, cb) { cb([], 'REQUEST_DENIED'); }
                },
            },
        };

        await expect(googleGeocode('any')).rejects.toThrow('REQUEST_DENIED');
    });
});

// ─── googleReverseGeocode ──────────────────────────────────────────────────────

describe('googleReverseGeocode', () => {
    afterEach(() => { delete window.google; });

    it('resolves null when google not loaded', async () => {
        const result = await googleReverseGeocode(51.5, -0.1);
        expect(result).toBeNull();
    });

    it('resolves with formatted address on OK', async () => {
        window.google = {
            maps: {
                Geocoder: class {
                    geocode(_params, cb) {
                        cb([{ formatted_address: 'London, UK' }], 'OK');
                    }
                },
            },
        };

        expect(await googleReverseGeocode(51.5, -0.1)).toBe('London, UK');
    });

    it('resolves null on non-OK status', async () => {
        window.google = {
            maps: {
                Geocoder: class {
                    geocode(_params, cb) { cb([], 'ZERO_RESULTS'); }
                },
            },
        };

        expect(await googleReverseGeocode(0, 0)).toBeNull();
    });
});

// ─── geocodeAddress (provider routing) ────────────────────────────────────────

describe('geocodeAddress', () => {
    beforeEach(() => {
        document.head.querySelectorAll('meta[name="csrf-token"]').forEach((el) => el.remove());
    });

    it('routes osm provider through postCp', async () => {
        const $axios = {
            post: vi.fn().mockResolvedValue({ data: { success: true, result: { lat: 1, lng: 2, address: 'x' } } }),
        };

        const result = await geocodeAddress('Berlin', 'osm', $axios);

        expect($axios.post).toHaveBeenCalledWith(
            '/cp/google-maps/geocode',
            { address: 'Berlin', provider: 'osm' }
        );
        expect(result).toEqual({ lat: 1, lng: 2, address: 'x' });
    });

    it('returns null when osm returns success=false', async () => {
        const $axios = {
            post: vi.fn().mockResolvedValue({ data: { success: false } }),
        };

        expect(await geocodeAddress('nowhere', 'osm', $axios)).toBeNull();
    });
});

// ─── reverseGeocodeAddress ─────────────────────────────────────────────────────

describe('reverseGeocodeAddress', () => {
    it('routes osm provider through postCp', async () => {
        const $axios = {
            post: vi.fn().mockResolvedValue({
                data: { success: true, result: { address: 'Berlin, Germany' } },
            }),
        };

        const result = await reverseGeocodeAddress(52.5, 13.4, 'osm', $axios);

        expect($axios.post).toHaveBeenCalledWith(
            '/cp/google-maps/reverse-geocode',
            { lat: 52.5, lng: 13.4, provider: 'osm' }
        );
        expect(result).toBe('Berlin, Germany');
    });

    it('returns null when osm reverse geocode fails', async () => {
        const $axios = {
            post: vi.fn().mockResolvedValue({ data: { success: false } }),
        };

        expect(await reverseGeocodeAddress(0, 0, 'osm', $axios)).toBeNull();
    });

    it('swallows errors and returns null for osm', async () => {
        const $axios = {
            post: vi.fn().mockRejectedValue(new Error('network error')),
        };

        expect(await reverseGeocodeAddress(0, 0, 'osm', $axios)).toBeNull();
    });
});
