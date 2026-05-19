<x-filament-panels::page>
    {{-- Polling Livewire 30s — met à jour les stats et les données sans toucher la carte --}}
    <div wire:poll.30000ms="$refresh" style="display:none"></div>

    {{-- Source de données mise à jour par Livewire — lue par le JS pour rafraîchir les marqueurs --}}
    <div id="ikoma-vehicles-data"
         data-vehicles="{!! htmlspecialchars($vehiclesJson, ENT_QUOTES) !!}"
         data-geozones="{!! htmlspecialchars($geoZonesJson, ENT_QUOTES) !!}"
         style="display:none"></div>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.min.css" crossorigin="" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.min.css" crossorigin="" />
    <style>
        .ikoma-tooltip {
            background: rgba(30,58,95,0.9);
            border: none;
            border-radius: 4px;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 6px;
            white-space: nowrap;
            box-shadow: 0 1px 4px rgba(0,0,0,.3);
        }
        .ikoma-tooltip::before { display: none; }
        .marker-cluster-small  { background-color: rgba(249,115,22,.4); }
        .marker-cluster-medium { background-color: rgba(249,115,22,.6); }
        .marker-cluster-large  { background-color: rgba(249,115,22,.8); }
        .marker-cluster-small  div { background-color: rgba(249,115,22,.8); }
        .marker-cluster-medium div { background-color: rgba(249,115,22,.9); }
        .marker-cluster-large  div { background-color: rgba(249,115,22,1);  }
        .marker-cluster div { color: #fff; font-weight: 700; }
    </style>

    {{-- Stats (mis à jour par Livewire) --}}
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

    {{-- Légende --}}
    <div class="flex gap-4 mb-3 text-xs font-medium text-gray-600 dark:text-gray-400">
        <span class="flex items-center gap-1">
            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#22c55e"></span> En mouvement
        </span>
        <span class="flex items-center gap-1">
            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#ef4444"></span> À l'arrêt
        </span>
        <span class="flex items-center gap-1">
            <span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:#9ca3af"></span> Hors ligne (&gt;1h)
        </span>
    </div>

    {{-- Carte — wire:ignore empêche Livewire de toucher ce div lors du poll --}}
    <div wire:ignore class="rounded-xl overflow-hidden shadow-lg" style="height:600px; border:2px solid #1e3a5f">
        <div id="vehicle-map" style="height:100%;width:100%;"></div>
    </div>

    {{-- Véhicules sans position --}}
    @if($withoutPos > 0)
    <div class="mt-4 rounded-xl bg-white dark:bg-gray-800 shadow p-4">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Véhicules sans position GPS</h3>
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
    <script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.min.js" crossorigin=""></script>
    <script>
    function vehicleColor(v, now) {
        if (!v.ts) return '#9ca3af';
        var age = now - new Date(v.ts).getTime();
        if (age > 3600000) return '#9ca3af';
        if (v.speed && v.speed > 0) return '#22c55e';
        return '#ef4444';
    }

    function makeIcon(heading, color) {
        var rotation = heading || 0;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 36 36">'
            + '<g transform="rotate(' + rotation + ' 18 18)">'
            + '<polygon points="18,3 29,31 18,24 7,31" fill="' + color + '" stroke="#fff" stroke-width="2"/>'
            + '</g></svg>';
        return L.divIcon({ html: svg, iconSize: [36, 36], iconAnchor: [18, 18], popupAnchor: [0, -20], className: '' });
    }

    function ikomaUpdateMarkers() {
        if (!window._ikomaMap || !window._ikomaCluster) return;
        var el = document.getElementById('ikoma-vehicles-data');
        if (!el) return;

        var vehicles = JSON.parse(el.dataset.vehicles || '[]');
        var now = Date.now();

        window._ikomaCluster.clearLayers();

        vehicles.forEach(function (v) {
            if (!v.lat || !v.lng) return;
            var color  = vehicleColor(v, now);
            var ts     = v.ts ? new Date(v.ts).toLocaleString('fr-FR') : '—';
            var speed  = v.speed !== null ? v.speed.toFixed(1) + ' km/h' : '—';
            var stateLabel = color === '#22c55e' ? 'En mouvement' : (color === '#ef4444' ? 'À l\'arrêt' : 'Hors ligne');
            var popup = '<div style="min-width:170px;font-family:sans-serif">'
                + '<div style="font-weight:700;font-size:14px;color:#1e3a5f;border-bottom:3px solid ' + color + ';padding-bottom:4px;margin-bottom:6px">'
                + (v.plate || '—') + ' <span style="font-size:10px;font-weight:400;color:' + color + '">' + stateLabel + '</span></div>'
                + '<table style="font-size:12px;width:100%;border-collapse:collapse">'
                + '<tr><td style="color:#666;padding:2px 6px 2px 0">Nom</td><td style="font-weight:600">' + (v.name || '—') + '</td></tr>'
                + '<tr><td style="color:#666;padding:2px 6px 2px 0">Flotte</td><td>' + (v.fleet || '—') + '</td></tr>'
                + '<tr><td style="color:#666;padding:2px 6px 2px 0">Vitesse</td><td>' + speed + '</td></tr>'
                + '<tr><td style="color:#666;padding:2px 6px 2px 0">Dernière MAJ</td><td>' + ts + '</td></tr>'
                + '</table></div>';
            var marker = L.marker([v.lat, v.lng], { icon: makeIcon(v.heading, color) })
                .bindPopup(popup)
                .bindTooltip(v.plate || v.name, { permanent: true, direction: 'top', offset: [0, -20], className: 'ikoma-tooltip' });
            window._ikomaCluster.addLayer(marker);
        });
    }

    function ikomaInitMap() {
        if (typeof L === 'undefined' || typeof L.markerClusterGroup === 'undefined') {
            setTimeout(ikomaInitMap, 200);
            return;
        }
        var el = document.getElementById('vehicle-map');
        if (!el) return;

        var dataEl   = document.getElementById('ikoma-vehicles-data');
        var vehicles = dataEl ? JSON.parse(dataEl.dataset.vehicles || '[]') : [];
        var geoZones = dataEl ? JSON.parse(dataEl.dataset.geozones || '[]') : [];
        var now = Date.now();

        // Eviter double init
        if (window._ikomaMap) return;

        var defaultLat = 5.3599517, defaultLng = -4.0082563, defaultZoom = 7;
        var first = vehicles.find(function(v){ return v.lat && v.lng; });
        if (first) { defaultLat = first.lat; defaultLng = first.lng; defaultZoom = 8; }

        var map = L.map('vehicle-map', { zoomControl: true }).setView([defaultLat, defaultLng], defaultZoom);
        window._ikomaMap = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);

        // Géozones
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

        window._ikomaCluster = L.markerClusterGroup({ maxClusterRadius: 50, disableClusteringAtZoom: 15 });
        map.addLayer(window._ikomaCluster);

        ikomaUpdateMarkers();

        var bounds = [];
        vehicles.forEach(function(v){ if (v.lat && v.lng) bounds.push([v.lat, v.lng]); });
        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [50, 50] });
        } else if (bounds.length === 1) {
            map.setView(bounds[0], 14);
        }

        setTimeout(function () { map.invalidateSize(); }, 300);
    }

    // Init initiale
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ikomaInitMap);
    } else {
        ikomaInitMap();
    }

    // Ré-init si navigation Livewire (wire:navigate)
    document.addEventListener('livewire:navigated', function () {
        window._ikomaMap = null;
        window._ikomaCluster = null;
        ikomaInitMap();
    });

    // Après chaque poll Livewire : mettre à jour les marqueurs sans toucher la carte
    document.addEventListener('livewire:updated', ikomaUpdateMarkers);
    </script>
</x-filament-panels::page>
