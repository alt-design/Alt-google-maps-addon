/**
 * useMapLoader — lazy-loading utilities for Google Maps API and Leaflet.
 *
 * Module-level caches ensure the script / library is only loaded once per page
 * no matter how many fieldtype instances are mounted.
 */

let googleMapsLoadPromise = null;
let _leafletLib = null;

/**
 * Lazily load the Google Maps JavaScript API.
 * Calling this multiple times is safe — subsequent calls return the same promise.
 *
 * @param {string} apiKey
 * @returns {Promise<void>}
 */
export function loadGoogleMapsScript(apiKey) {
    if (window.google?.maps?.Map) return Promise.resolve();
    if (googleMapsLoadPromise) return googleMapsLoadPromise;

    googleMapsLoadPromise = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(apiKey)}`;
        script.async = true;

        const timeout = setTimeout(() => {
            googleMapsLoadPromise = null;
            reject(new Error('Google Maps API load timed out after 15s'));
        }, 15000);

        script.addEventListener('load', () => {
            clearTimeout(timeout);
            if (!window.google?.maps?.Map) {
                googleMapsLoadPromise = null;
                reject(new Error('Google Maps script loaded but window.google.maps is unavailable'));
                return;
            }
            resolve();
        });
        script.addEventListener('error', () => {
            clearTimeout(timeout);
            googleMapsLoadPromise = null;
            reject(new Error('Failed to load Google Maps API'));
        });
        document.head.appendChild(script);
    });

    return googleMapsLoadPromise;
}

/**
 * Lazily import Leaflet and cache the result.
 * Subsequent calls return the already-loaded library synchronously via the promise.
 *
 * Note: Leaflet default-icon setup (import.meta.url paths) must be done by the
 * caller after this resolves, because import.meta.url is resolved per-file at
 * build time and must stay in the component file.
 *
 * @returns {Promise<import('leaflet').default>}
 */
export async function loadLeaflet() {
    if (_leafletLib) return _leafletLib;
    _leafletLib = (await import('leaflet')).default;
    return _leafletLib;
}

/**
 * Synchronously return the already-loaded Leaflet library (or null if not yet loaded).
 * Use this in non-async functions that run after loadLeaflet() has already resolved.
 *
 * @returns {import('leaflet').default | null}
 */
export function getLeaflet() {
    return _leafletLib;
}
