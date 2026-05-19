<x-filament-panels::page>
    {{-- Polling Livewire 30s : rafraîchit les données sans recharger la carte --}}
    <div wire:poll.30000ms="$refresh" style="display:none"></div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" crossorigin="" />

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

    <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js" crossorigin=""></script>
    <script>
    function ikomaInitMap() {
        if (typeof L === 'undefined') {
            setTimeout(ikomaInitMap, 200);
            return;
        }
        var el = document.getElementById('vehicle-map');
        if (!el) return;

        var vehicles = {!! $vehiclesJson !!};
        var geoZones = {!! $geoZonesJson !!};

        var defaultLat = 5.3599517;
        var defaultLng = -4.0082563;
        var defaultZoom = 7;

        if (vehicles.length > 0 && vehicles[0].lat) {
            defaultLat = vehicles[0].lat;
            defaultLng = vehicles[0].lng;
            defaultZoom = 14;
        }

        if (window._ikomaMap) {
            try { window._ikomaMap.remove(); } catch(e) {}
            window._ikomaMap = null;
            window._ikomaFitted = false;
        }

        var map = L.map('vehicle-map', { zoomControl: true }).setView([defaultLat, defaultLng], defaultZoom);
        window._ikomaMap = map;
        window._ikomaMarkers = [];

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

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
            if (layer) layer.addTo(map).bindPopup(popup);
        });

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
                + '<div style="font-weight:700;font-size:14px;color:#1e3a5f;border-bottom:2px solid #f97316;padding-bottom:4px;margin-bottom:6px">' + (v.plate || '—') + '</div>'
                + '<table style="font-size:12px;width:100%;border-collapse:collapse">'
                + '<tr><td style="color:#666;padding:1px 4px 1px 0">Nom</td><td style="font-weight:600">' + (v.name || '—') + '</td></tr>'
                + '<tr><td style="color:#666;padding:1px 4px 1px 0">Flotte</td><td>' + (v.fleet || '—') + '</td></tr>'
                + '<tr><td style="color:#666;padding:1px 4px 1px 0">Vitesse</td><td>' + speed + '</td></tr>'
                + '<tr><td style="color:#666;padding:1px 4px 1px 0">Dernière MAJ</td><td>' + ts + '</td></tr>'
                + '</table></div>';
            var marker = L.marker(latlng, { icon: makeIcon(v.heading) }).addTo(map).bindPopup(popup);
            window._ikomaMarkers.push(marker);
        });

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [40, 40] });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        }

        setTimeout(function () { map.invalidateSize(); }, 300);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ikomaInitMap);
    } else {
        ikomaInitMap();
    }

    document.addEventListener('livewire:navigated', ikomaInitMap);
    </script>
</x-filament-panels::page>
