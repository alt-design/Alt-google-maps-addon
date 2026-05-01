/**
 * useGeocoder — geocoding helpers and CP HTTP utility.
 *
 * All functions are stateless and can be imported by any component or composable.
 */

// ─── CP HTTP helper ────────────────────────────────────────────────────────────

/**
 * POST to a Statamic CP endpoint.
 *
 * Prefers the Vue-injected $axios instance (avoids a manual CSRF fetch) but
 * falls back to the native fetch API so the function works outside of Vue
 * component context too.
 *
 * @param {string}      path   - CP path after /cp/, e.g. 'google-maps/geocode'
 * @param {object}      body   - JSON-serialisable request body
 * @param {object|null} $axios - Optional axios instance (from getCurrentInstance().proxy.$axios)
 * @returns {Promise<any>}
 */
export async function postCp(path, body, $axios = null) {
    if ($axios) {
        const resp = await $axios.post(`/cp/${path}`, body);
        return resp.data;
    }

    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!csrf) {
        throw new Error('CSRF token not found in DOM (<meta name="csrf-token">). Cannot make CP request.');
    }
    const resp = await fetch(`/cp/${path}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
    });

    if (!resp.ok) {
        const text = await resp.text();
        throw new Error(`HTTP ${resp.status}: ${text.slice(0, 200)}`);
    }

    return resp.json();
}

// ─── Google Maps geocoding ─────────────────────────────────────────────────────

/**
 * Forward-geocode an address string using the Google Maps Geocoder API.
 *
 * @param {string} address
 * @returns {Promise<{lat: number, lng: number, address: string} | null>}
 */
export function googleGeocode(address) {
    return new Promise((resolve, reject) => {
        if (!window.google?.maps?.Geocoder) {
            reject(new Error('Google Maps is not loaded.'));
            return;
        }
        const geocoder = new window.google.maps.Geocoder();
        geocoder.geocode({ address }, (results, status) => {
            if (status === 'OK' && results?.length) {
                resolve({
                    lat: results[0].geometry.location.lat(),
                    lng: results[0].geometry.location.lng(),
                    address: results[0].formatted_address,
                });
            } else if (status === 'ZERO_RESULTS') {
                resolve(null);
            } else {
                reject(new Error(`Geocoding failed (${status}).`));
            }
        });
    });
}

/**
 * Reverse-geocode a lat/lng pair to a formatted address using Google Maps.
 *
 * @param {number} lat
 * @param {number} lng
 * @returns {Promise<string | null>}
 */
export function googleReverseGeocode(lat, lng) {
    return new Promise((resolve) => {
        if (!window.google?.maps?.Geocoder) {
            resolve(null);
            return;
        }
        const geocoder = new window.google.maps.Geocoder();
        geocoder.geocode({ location: { lat, lng } }, (results, status) => {
            resolve(status === 'OK' && results?.length ? results[0].formatted_address : null);
        });
    });
}

// ─── Provider-agnostic wrappers ────────────────────────────────────────────────

/**
 * Forward-geocode using either OpenStreetMap (via CP proxy) or Google Maps.
 *
 * @param {string}           address
 * @param {'osm'|'google'}   provider
 * @param {object|null}      $axios
 * @returns {Promise<{lat: number, lng: number, address: string} | null>}
 */
export async function geocodeAddress(address, provider, $axios = null) {
    if (provider === 'osm') {
        const data = await postCp('google-maps/geocode', { address, provider: 'osm' }, $axios);
        if (!data.success) return null;
        return data.result;
    }
    return googleGeocode(address);
}

/**
 * Reverse-geocode using either OpenStreetMap (via CP proxy) or Google Maps.
 *
 * @param {number}           lat
 * @param {number}           lng
 * @param {'osm'|'google'}   provider
 * @param {object|null}      $axios
 * @returns {Promise<string | null>}
 */
export async function reverseGeocodeAddress(lat, lng, provider, $axios = null) {
    if (provider === 'osm') {
        try {
            const data = await postCp('google-maps/reverse-geocode', { lat, lng, provider: 'osm' }, $axios);
            if (data.success) return data.result.address;
            return null;
        } catch {
            return null;
        }
    }
    return googleReverseGeocode(lat, lng);
}
