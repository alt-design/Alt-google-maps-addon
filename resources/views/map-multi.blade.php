<div
    id="{{ $id }}"
    style="width: {{ $width }}; height: {{ $height }}px; border-radius: 8px; overflow: hidden;"
></div>

@if ($provider === 'osm')
{{-- ── OpenStreetMap / Leaflet + MarkerCluster ─────────────────────────────── --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@if ($clustering)
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" integrity="sha256-YU3qCpj/P06tdPBJGPax0bm6Q1wltfwjsho5TR4+TYc=" crossorigin="" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" integrity="sha256-YSWCMtmNZNwqex4CEw1nQhvFub2lmU7vcCKP+XVwwXA=" crossorigin="" />
@endif
<script>
(function () {
    var mapId      = {{ Js::from($id) }};
    var markers    = {!! $markers_json !!};
    var zoom       = {{ $zoom ?? 'null' }};
    var defaultZoom = {{ $default_zoom }};
    var clustering = {{ $clustering ? 'true' : 'false' }};

    function initMap() {
        var el = document.getElementById(mapId);
        if (!el || el._leaflet_id) return;

        var map = L.map(el, { scrollWheelZoom: false });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 21,
        }).addTo(map);

        var layer = clustering && window.L && L.markerClusterGroup
            ? L.markerClusterGroup()
            : null;

        var leafletMarkers = [];
        markers.forEach(function (m) {
            var icon = undefined;
            if (m.iconJson) {
                try {
                    var ic = typeof m.iconJson === 'string' ? JSON.parse(m.iconJson) : m.iconJson;
                    if (ic && ic.url) {
                        icon = L.icon({
                            iconUrl: ic.url,
                            iconSize: ic.width && ic.height ? [ic.width, ic.height] : [40, 40],
                            iconAnchor: ic.width && ic.height ? [ic.width / 2, ic.height] : [20, 40],
                        });
                    }
                } catch (e) {}
            }

            var mk = L.marker([m.lat, m.lng], icon ? { icon: icon } : {});

            if (m.infobox_title || m.infobox_content) {
                var content = m.infobox_title
                    ? '<strong>' + m.infobox_title + '</strong>' + (m.infobox_content ? '<br>' + m.infobox_content : '')
                    : m.infobox_content;
                mk.bindPopup(content);
            }

            if (layer) {
                layer.addLayer(mk);
            } else {
                mk.addTo(map);
            }
            leafletMarkers.push(mk);
        });

        if (layer) map.addLayer(layer);

        if (markers.length === 1) {
            map.setView([markers[0].lat, markers[0].lng], zoom !== null ? zoom : defaultZoom);
        } else if (leafletMarkers.length > 0) {
            var group = L.featureGroup(leafletMarkers);
            map.fitBounds(group.getBounds(), { padding: [40, 40] });
            if (zoom !== null) map.setZoom(zoom);
        }
    }

    function showLoadError() {
        var el = document.getElementById(mapId);
        if (el) { el.textContent = "This map could not be loaded. Check your connection and reload the page."; el.style.cssText += 'display:flex;align-items:center;justify-content:center;text-align:center;padding:1rem;background:#f3f4f6;color:#4b5563;'; }
    }

    function loadLeafletAndCluster(cb) {
        function loadLeaflet(next) {
            if (typeof L !== 'undefined') { next(); return; }
            if (window._leafletLoading) { window._leafletLoading.then(next); return; }
            window._leafletLoading = new Promise(function (resolve) {
                var s = document.createElement('script');
                s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                s.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
                s.crossOrigin = '';
                s.onload = resolve;
                s.onerror = function () { window._leafletLoading = null; showLoadError(); };
                document.head.appendChild(s);
            });
            window._leafletLoading.then(next);
        }

        function loadCluster(next) {
            if (!clustering || (typeof L !== 'undefined' && L.markerClusterGroup)) { next(); return; }
            if (window._leafletClusterLoading) { window._leafletClusterLoading.then(next); return; }
            window._leafletClusterLoading = new Promise(function (resolve) {
                var s = document.createElement('script');
                s.src = 'https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js';
                s.integrity = 'sha256-Hk4dIpcqOSb0hZjgyvFOP+cEmDXUKKNE/tT542ZbNQg=';
                s.crossOrigin = '';
                s.onload = resolve;
                s.onerror = function () { window._leafletClusterLoading = null; showLoadError(); };
                document.head.appendChild(s);
            });
            window._leafletClusterLoading.then(next);
        }

        loadLeaflet(function () { loadCluster(cb); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { loadLeafletAndCluster(initMap); });
    } else {
        loadLeafletAndCluster(initMap);
    }
})();
</script>

@else
{{-- ── Google Maps + MarkerClusterer ───────────────────────────────────────── --}}
<script>
(function () {
    var mapId       = {{ Js::from($id) }};
    var markers     = {!! $markers_json !!};
    var zoom        = {{ $zoom ?? 'null' }};
    var defaultZoom = {{ $default_zoom }};

    function showLoadError() {
        var el = document.getElementById(mapId);
        if (el) { el.textContent = "This map could not be loaded. Check your connection and reload the page."; el.style.cssText += 'display:flex;align-items:center;justify-content:center;text-align:center;padding:1rem;background:#f3f4f6;color:#4b5563;'; }
    }
    var clustering  = {{ $clustering ? 'true' : 'false' }};
    var mapType     = {{ Js::from($map_type) }};

    function initMap() {
        var el = document.getElementById(mapId);
        if (!el) return;

        var map = new google.maps.Map(el, {
            zoom: defaultZoom,
            center: { lat: markers[0].lat, lng: markers[0].lng },
            mapTypeId: mapType,
            scrollwheel: false,
        });

        var bounds = new google.maps.LatLngBounds();
        var gmMarkers = markers.map(function (m) {
            var opts = { position: { lat: m.lat, lng: m.lng } };

            if (m.iconJson) {
                try {
                    var ic = typeof m.iconJson === 'string' ? JSON.parse(m.iconJson) : m.iconJson;
                    if (ic && ic.url) {
                        opts.icon = {
                            url: ic.url,
                            scaledSize: ic.width && ic.height
                                ? new google.maps.Size(ic.width, ic.height)
                                : new google.maps.Size(40, 40),
                        };
                    }
                } catch (e) {}
            }

            // When clustering, don't attach to map yet — clusterer does it
            if (!clustering) opts.map = map;

            var mk = new google.maps.Marker(opts);
            bounds.extend({ lat: m.lat, lng: m.lng });

            if (m.infobox_title || m.infobox_content) {
                var content = '<div style="max-width:240px">'
                    + (m.infobox_title ? '<strong>' + m.infobox_title + '</strong>' : '')
                    + (m.infobox_content ? '<p style="margin:4px 0 0">' + m.infobox_content + '</p>' : '')
                    + '</div>';
                var infoWindow = new google.maps.InfoWindow({ content: content });
                mk.addListener('click', function () { infoWindow.open(map, mk); });
            }

            return mk;
        });

        if (clustering) {
            var clusterColor = {{ Js::from($cluster_color ?: '#4F46E5') }};
            new window.markerClusterer.MarkerClusterer({
                map: map,
                markers: gmMarkers,
                renderer: {
                    render: function (cluster) {
                        var count = cluster.count;
                        var size  = count < 10 ? 56 : count < 100 ? 64 : 72;
                        var half  = size / 2;
                        var svg = '<svg fill="none" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '">'
                            + '<circle cx="' + half + '" cy="' + half + '" r="' + half + '" fill="' + clusterColor + '" fill-opacity="0.15"/>'
                            + '<circle cx="' + half + '" cy="' + half + '" r="' + (half * 0.78) + '" fill="' + clusterColor + '" fill-opacity="0.35"/>'
                            + '<circle cx="' + half + '" cy="' + half + '" r="' + (half * 0.56) + '" fill="' + clusterColor + '"/>'
                            + '<text x="50%" y="50%" text-anchor="middle" dominant-baseline="central" font-family="roboto,arial,sans-serif" font-size="' + Math.round(half * 0.52) + '" font-weight="500" fill="#fff">' + count + '</text>'
                            + '</svg>';
                        return new google.maps.Marker({
                            position: cluster.position,
                            icon: {
                                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                                scaledSize: new google.maps.Size(size, size),
                                anchor: new google.maps.Point(half, half),
                            },
                            zIndex: 1000 + count,
                        });
                    },
                },
            });
        }

        if (markers.length === 1) {
            map.setCenter({ lat: markers[0].lat, lng: markers[0].lng });
            map.setZoom(zoom !== null ? zoom : defaultZoom);
        } else {
            map.fitBounds(bounds);
            if (zoom !== null) {
                google.maps.event.addListenerOnce(map, 'bounds_changed', function () {
                    map.setZoom(zoom);
                });
            }
        }
    }

    // Load a script once, caching the promise globally under `cacheKey`
    function loadScript(src, cacheKey, alreadyLoaded, integrity) {
        if (alreadyLoaded()) return Promise.resolve();
        if (window[cacheKey]) return window[cacheKey];
        window[cacheKey] = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            if (integrity) { s.integrity = integrity; s.crossOrigin = ''; }
            s.onload = resolve;
            s.onerror = function () { window[cacheKey] = null; reject(new Error('Failed: ' + src)); };
            document.head.appendChild(s);
        });
        return window[cacheKey];
    }

    function run() {
        loadScript(
            'https://maps.googleapis.com/maps/api/js?key={{ urlencode($key) }}',
            '_gmapsLoading',
            function () { return !!(window.google && window.google.maps); }
        ).then(function () {
            if (!clustering) { initMap(); return; }
            return loadScript(
                'https://unpkg.com/@googlemaps/markerclusterer@2.6.2/dist/index.umd.js',
                '_mcLoading',
                function () { return !!(window.markerClusterer && window.markerClusterer.MarkerClusterer); },
                'sha256-6yIVCkgKFWog5aEoz/dMM4H7y9Jvg8OWFDhocO81Pd0='
            ).then(initMap);
        }).catch(function (e) { console.error('[google_map:multi]', e); showLoadError(); });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run);
    } else {
        run();
    }
})();
</script>
@endif
