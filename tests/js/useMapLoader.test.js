import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

// Each test calls vi.resetModules() so the module-level singletons
// (googleMapsLoadPromise, _leafletLib) start fresh.

// ─── loadGoogleMapsScript ──────────────────────────────────────────────────────

describe('loadGoogleMapsScript', () => {
    let appendChildSpy;

    beforeEach(() => {
        vi.resetModules();
        delete window.google;
        appendChildSpy = vi.spyOn(document.head, 'appendChild').mockImplementation(() => {});
    });

    afterEach(() => {
        vi.restoreAllMocks();
        delete window.google;
    });

    it('resolves immediately when window.google.maps.Map already exists', async () => {
        window.google = { maps: { Map: class {} } };
        const { loadGoogleMapsScript } = await import('../../resources/js/composables/useMapLoader.js');

        await expect(loadGoogleMapsScript('key123')).resolves.toBeUndefined();
        expect(appendChildSpy).not.toHaveBeenCalled();
    });

    it('appends a script tag with encoded API key', async () => {
        const { loadGoogleMapsScript } = await import('../../resources/js/composables/useMapLoader.js');

        appendChildSpy.mockImplementation((script) => {
            window.google = { maps: { Map: class {} } };
            script.dispatchEvent(new Event('load'));
        });

        await loadGoogleMapsScript('my api key');

        const script = appendChildSpy.mock.calls[0][0];
        expect(script.tagName).toBe('SCRIPT');
        expect(script.src).toContain('my%20api%20key');
    });

    it('rejects when the script fires an error event', async () => {
        const { loadGoogleMapsScript } = await import('../../resources/js/composables/useMapLoader.js');

        appendChildSpy.mockImplementation((script) => {
            script.dispatchEvent(new Event('error'));
        });

        await expect(loadGoogleMapsScript('bad-key')).rejects.toThrow('Failed to load Google Maps API');
    });

    it('rejects when script loads but window.google.maps is unavailable', async () => {
        const { loadGoogleMapsScript } = await import('../../resources/js/composables/useMapLoader.js');

        appendChildSpy.mockImplementation((script) => {
            // google intentionally not set — simulates 200 OK but broken payload
            script.dispatchEvent(new Event('load'));
        });

        await expect(loadGoogleMapsScript('key')).rejects.toThrow('window.google.maps is unavailable');
    });

    it('rejects with timeout error after 15 s (fake timers)', async () => {
        vi.useFakeTimers();
        const { loadGoogleMapsScript } = await import('../../resources/js/composables/useMapLoader.js');

        // script never fires load or error
        appendChildSpy.mockImplementation(() => {});

        const promise = loadGoogleMapsScript('slow-key');
        vi.advanceTimersByTime(16000);

        await expect(promise).rejects.toThrow('timed out after 15s');
        vi.useRealTimers();
    });

    it('returns the same promise for concurrent calls (no double-load)', async () => {
        const { loadGoogleMapsScript } = await import('../../resources/js/composables/useMapLoader.js');
        let resolveScript;

        appendChildSpy.mockImplementation((script) => {
            resolveScript = () => {
                window.google = { maps: { Map: class {} } };
                script.dispatchEvent(new Event('load'));
            };
        });

        const p1 = loadGoogleMapsScript('key');
        const p2 = loadGoogleMapsScript('key');

        expect(p1).toBe(p2);

        resolveScript();
        await Promise.all([p1, p2]);

        expect(appendChildSpy).toHaveBeenCalledTimes(1);
    });
});

// ─── loadLeaflet / getLeaflet ──────────────────────────────────────────────────

describe('loadLeaflet', () => {
    beforeEach(() => {
        vi.resetModules();
    });

    it('returns the leaflet default export', async () => {
        const { loadLeaflet, getLeaflet } = await import('../../resources/js/composables/useMapLoader.js');

        const L = await loadLeaflet();

        expect(L).toBeDefined();
        expect(typeof L.map).toBe('function');

        // Subsequent sync call returns same instance
        expect(getLeaflet()).toBe(L);
    });

    it('getLeaflet returns null before loadLeaflet is called', async () => {
        const { getLeaflet } = await import('../../resources/js/composables/useMapLoader.js');
        expect(getLeaflet()).toBeNull();
    });

    it('returns the same cached instance on repeated calls', async () => {
        const { loadLeaflet } = await import('../../resources/js/composables/useMapLoader.js');
        const first = await loadLeaflet();
        const second = await loadLeaflet();
        expect(first).toBe(second);
    });
});
