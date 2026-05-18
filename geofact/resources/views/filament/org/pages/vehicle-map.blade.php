<x-filament-panels::page>
    {{-- Polling Livewire 30s : rafraîchit les données sans recharger la carte --}}
    <div wire:poll.30000ms="$refresh" style="display:none"></div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    {{-- Stats summary --}}
    <div class="grid grid-cols-2 gap-4 mb-4 sm:grid-cols-4">
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 border-l-4" style="border-color:#1e3a5f">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Véhicules actifs</p>
            <p class="text-2xl font-bold" style="color:#1e3a5f">{{ $vehicles->count() }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 border-l-4" style="border-color:#f97316">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Localisés</p>
            <p class="text-2xl font-bold" style="color:#f97316">{{ $withPos }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 border-l-4 border-gray-300">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Sans position</p>
            <p class="text-2xl font-bold text-gray-600 dark:text-gray-300">{{ $withoutPos }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 border-l-4 border-green-400">
            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Couverture</p>
            <p class="text-2xl font-bold text-green-600">
                {{ $vehicles->count() > 0 ? round($withPos / $vehicles->count() * 100) : 0 }}%
            </p>
        </div>
    </div>

    {{-- Map container --}}
    <div class="rounded-xl overflow-hidden shadow-lg" style="height:600px; border:2px solid #1e3a5f">
        <div id="vehicle-map" style="height:100%;width:100%;"></div>
    </div>

    {{-- Vehicle list without position --}}
    @if($withoutPos > 0)
    <div class="mt-4 rounded-xl bg-white dark:bg-gray-800 shadow p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">
            Véhicules sans position GPS
        </h3>
        <div class="flex flex-wrap gap-2">
            @foreach($vehicles->where('has_pos', false) as $v)
            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1 text-xs font-medium text-gray-600 dark:text-gray-300">
                {{ $v['plate'] }} — {{ $v['name'] }}
            </span>
            @endforeach
        </div>
    </div>
    @endif

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
    <script>
    (function () {
        var vehicles = {!! $vehiclesJson !!};
        var geoZones = {!! $geoZonesJson !!};

        var defaultLat = 5.3599517;
        var defaultLng = -4.0082563;
        var defaultZoom = 12;

        if (vehicles.length > 0) {
            defaultLat = vehicles[0].lat;
            defaultLng = vehicles[0].lng;
        }

        // Initialise la carte une seule fois — le polling Livewire met à jour les compteurs
        // sans détruire la carte Leaflet déjà rendue
        if (window._ikomaMap) {
            // Mise à jour des marqueurs uniquement
            window._ikomaMarkers.forEach(function (m) { window._ikomaMap.removeLayer(m); });
            window._ikomaMarkers = [];
        } else {
            window._ikomaMap = L.map('vehicle-map').setView([defaultLat, defaultLng], defaultZoom);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(window._ikomaMap);
            window._ikomaMarkers = [];

            // Overlay GeoZones (rendu une seule fois)
            geoZones.forEach(function (z) {
                if (!z.geometry) return;
                var g = z.geometry;
                var opts = { color: '#1e3a5f', fillColor: '#1e3a5f', fillOpacity: 0.1, weight: 1.5 };
                var popup = '<strong>' + z.name + '</strong><br><small>' + z.type + '</small>';
                var layer = null;

                if (g.type === 'circle' && g.center && g.radius) {
                    layer = L.circle(g.center, Object.assign({}, opts, { radius: g.radius }));
                } else if (g.type === 'polygon' && g.coordinates) {
                    layer = L.polygon(g.coordinates, opts);
                } else if (g.type === 'rectangle' && g.bounds) {
                    layer = L.rectangle(g.bounds, opts);
                }
                if (layer) layer.addTo(window._ikomaMap).bindPopup(popup);
            });
        }

        var map = window._ikomaMap;

        function makeIcon(heading) {
            var rotation = heading || 0;
            var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32">'
                + '<g transform="rotate(' + rotation + ' 16 16)">'
                + '<polygon points="16,2 26,28 16,22 6,28" fill="#f97316" stroke="#1e3a5f" stroke-width="2"/>'
                + '</g></svg>';
            return L.divIcon({ html: svg, iconSize: [32, 32], iconAnchor: [16, 16], popupAnchor: [0, -16], className: '' });
        }

        var bounds = [];

        vehicles.forEach(function (v) {
            if (!v.lat || !v.lng) return;
            var latlng = [v.lat, v.lng];
            bounds.push(latlng);
            var ts    = v.ts ? new Date(v.ts).toLocaleString('fr-FR') : '—';
            var speed = v.speed !== null ? v.speed.toFixed(1) + ' km/h' : '—';
            var popup = '<div style="min-width:160px;font-family:sans-serif">'
                + '<div style="font-weight:700;font-size:14px;color:#1e3a5f;border-bottom:2px solid #f97316;padding-bottom:4px;margin-bottom:6px">' + v.plate + '</div>'
                + '<table style="font-size:12px;width:100%;border-collapse:collapse">'
                + '<tr><td style="color:#666;padding:1px 4px 1px 0">Nom</td><td style="font-weight:600">' + (v.name || '—') + '</td></tr>'
                + '<tr><td style="color:#666;padding:1px 4px 1px 0">Flotte</td><td>' + (v.fleet || '—') + '</td></tr>'
                + '<tr><td style="color:#666;padding:1px 4px 1px 0">Vitesse</td><td>' + speed + '</td></tr>'
                + '<tr><td style="color:#666;padding:1px 4px 1px 0">Dernière MAJ</td><td>' + ts + '</td></tr>'
                + '</table></div>';
            var marker = L.marker(latlng, { icon: makeIcon(v.heading) }).addTo(map).bindPopup(popup);
            window._ikomaMarkers.push(marker);
        });

        if (bounds.length > 1 && !window._ikomaFitted) {
            map.fitBounds(bounds, { padding: [30, 30] });
            window._ikomaFitted = true;
        } else if (bounds.length === 1 && !window._ikomaFitted) {
            map.setView(bounds[0], 14);
            window._ikomaFitted = true;
        }
    })();
    </script>
</x-filament-panels::page>
