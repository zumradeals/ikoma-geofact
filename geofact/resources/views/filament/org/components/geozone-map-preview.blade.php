@php
    $geometry = $this->getRecord()?->geometry ?? null;
    $geoJson  = $geometry ? (is_array($geometry) ? json_encode($geometry) : $geometry) : 'null';
@endphp

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

<div id="geozone-preview-map" style="height:350px;width:100%;border-radius:8px;border:2px solid #1e3a5f"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
<script>
(function () {
    var geometry = {!! $geoJson !!};
    if (!geometry) return;

    var map = L.map('geozone-preview-map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    var layer = null;

    if (geometry.type === 'circle' && geometry.center && geometry.radius) {
        var center = geometry.center; // [lat, lng]
        layer = L.circle(center, {
            radius: geometry.radius,
            color: '#1e3a5f',
            fillColor: '#f97316',
            fillOpacity: 0.25,
            weight: 2,
        }).addTo(map);
        map.setView(center, 14);

    } else if (geometry.type === 'polygon' && geometry.coordinates) {
        layer = L.polygon(geometry.coordinates, {
            color: '#1e3a5f',
            fillColor: '#f97316',
            fillOpacity: 0.25,
            weight: 2,
        }).addTo(map);
        map.fitBounds(layer.getBounds(), { padding: [20, 20] });

    } else if (geometry.type === 'rectangle' && geometry.bounds) {
        // bounds: [[lat1,lng1],[lat2,lng2]]
        layer = L.rectangle(geometry.bounds, {
            color: '#1e3a5f',
            fillColor: '#f97316',
            fillOpacity: 0.25,
            weight: 2,
        }).addTo(map);
        map.fitBounds(layer.getBounds(), { padding: [20, 20] });
    } else {
        // Fallback : centre sur Abidjan
        map.setView([5.3599517, -4.0082563], 12);
    }
})();
</script>
