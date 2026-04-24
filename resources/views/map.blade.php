<div
    id="{{ $id }}"
    style="width: {{ $width }}; height: {{ $height }}px; border-radius: 8px; overflow: hidden;"
></div>

@if ($provider === 'osm')
{{-- ── OpenStreetMap / Leaflet ──────────────────────────────────────────────── --}}
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
/>
<script>
(function () {
    var mapId = {{ Js::from($id) }};
    var lat   = {{ $lat }};
    var lng   = {{ $lng }};
    var zoom  = {{ $zoom }};

    function initLeaflet() {
        if (typeof L === 'undefined') return;
        var el = document.getElementById(mapId);
        if (!el || el._leaflet_id) return;

        var map = L.map(el, {
            center: [lat, lng],
            zoom: zoom,
            scrollWheelZoom: false,
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 21,
        }).addTo(map);

        L.marker([lat, lng]).addTo(map);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            loadLeaflet(initLeaflet);
        });
    } else {
        loadLeaflet(initLeaflet);
    }

    function loadLeaflet(cb) {
        if (typeof L !== 'undefined') { cb(); return; }
        if (window._leafletLoading) { window._leafletLoading.then(cb); return; }
        window._leafletLoading = new Promise(function (resolve) {
            var s = document.createElement('script');
            s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            s.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WPaA=';
            s.crossOrigin = '';
            s.onload = resolve;
            document.head.appendChild(s);
        });
        window._leafletLoading.then(cb);
    }
})();
</script>

@else
{{-- ── Google Maps ──────────────────────────────────────────────────────────── --}}
<script>
(function () {
    var mapId   = {{ Js::from($id) }};
    var lat     = {{ $lat }};
    var lng     = {{ $lng }};
    var zoom    = {{ $zoom }};
    var mapType = {{ Js::from($map_type) }};

    function initGoogle() {
        var el = document.getElementById(mapId);
        if (!el || !window.google) return;

        var pos = { lat: lat, lng: lng };
        var map = new google.maps.Map(el, {
            center: pos,
            zoom: zoom,
            mapTypeId: mapType,
            scrollwheel: false,
        });
        new google.maps.Marker({ position: pos, map: map });
    }

    if (window.google && window.google.maps) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initGoogle);
        } else {
            initGoogle();
        }
        return;
    }

    if (window._gmapsLoadingFrontend) {
        window._gmapsLoadingFrontend.then(initGoogle);
        return;
    }

    window._gmapsLoadingFrontend = new Promise(function (resolve) {
        var s = document.createElement('script');
        s.src = 'https://maps.googleapis.com/maps/api/js?key={{ urlencode($key) }}';
        s.async = true;
        s.onload = resolve;
        document.head.appendChild(s);
    });
    window._gmapsLoadingFrontend.then(initGoogle);
})();
</script>
@endif
