<x-filament-panels::page>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    {{-- Trip info header --}}
    <div class="grid grid-cols-2 gap-4 mb-4 sm:grid-cols-4">
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 border-l-4" style="border-color:#1e3a5f">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Véhicule</p>
            <p class="text-lg font-bold" style="color:#1e3a5f">{{ $trip->vehicle?->plate ?? '—' }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 border-l-4" style="border-color:#f97316">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Conducteur</p>
            <p class="text-lg font-bold" style="color:#f97316">{{ $trip->driver?->first_name ?? '—' }}</p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 border-l-4 border-green-400">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Départ</p>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ $trip->started_at?->format('d/m/Y H:i') ?? '—' }}
            </p>
        </div>
        <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 border-l-4 border-gray-300">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Arrivée</p>
            <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ $trip->ended_at?->format('d/m/Y H:i') ?? 'En cours' }}
            </p>
        </div>
    </div>

    {{-- Replay controls --}}
    <div class="rounded-xl bg-white dark:bg-gray-800 shadow px-4 py-3 mb-4 flex items-center gap-4 flex-wrap">
        <span class="text-sm text-gray-500">{{ $totalPoints }} points GPS</span>
        <button id="btn-play" onclick="replayPlay()"
            class="px-4 py-1.5 rounded-lg text-sm font-semibold text-white"
            style="background:#1e3a5f">▶ Rejouer</button>
        <button id="btn-pause" onclick="replayPause()" disabled
            class="px-4 py-1.5 rounded-lg text-sm font-semibold text-white bg-gray-400">⏸ Pause</button>
        <button onclick="replayReset()"
            class="px-4 py-1.5 rounded-lg text-sm font-semibold text-white"
            style="background:#f97316">↺ Début</button>
        <div class="flex items-center gap-2 ml-auto">
            <label class="text-xs text-gray-500">Vitesse</label>
            <select id="replay-speed" class="rounded text-xs border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-2 py-1">
                <option value="500">×1</option>
                <option value="200" selected>×2</option>
                <option value="100">×4</option>
                <option value="50">×8</option>
            </select>
        </div>
        <div class="text-xs text-gray-500 ml-2">
            Point <span id="replay-counter">0</span> / {{ $totalPoints }}
        </div>
    </div>

    {{-- Map --}}
    <div class="rounded-xl overflow-hidden shadow-lg" style="height:550px; border:2px solid #1e3a5f">
        <div id="replay-map" style="height:100%;width:100%;"></div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV/XN/WLs=" crossorigin=""></script>
    <script>
    var POINTS = {!! $pointsJson !!};

    var map = L.map('replay-map');
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    // Polyline complète du trajet (grisée)
    var fullCoords = POINTS.map(function (p) { return [p.lat, p.lng]; });
    if (fullCoords.length > 0) {
        L.polyline(fullCoords, { color: '#9ca3af', weight: 2, opacity: 0.5 }).addTo(map);
        map.fitBounds(fullCoords, { padding: [30, 30] });
    }

    // Polyline de replay (orange)
    var replayLine = L.polyline([], { color: '#f97316', weight: 3 }).addTo(map);

    // Marqueur véhicule
    function makeIcon(heading) {
        var r = heading || 0;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 32 32">'
            + '<g transform="rotate(' + r + ' 16 16)">'
            + '<polygon points="16,2 26,28 16,22 6,28" fill="#f97316" stroke="#1e3a5f" stroke-width="2"/>'
            + '</g></svg>';
        return L.divIcon({ html: svg, iconSize: [28, 28], iconAnchor: [14, 14], className: '' });
    }

    var marker = null;
    if (POINTS.length > 0) {
        marker = L.marker([POINTS[0].lat, POINTS[0].lng], { icon: makeIcon(0) }).addTo(map);
    }

    // Marqueurs début/fin
    if (POINTS.length > 0) {
        L.circleMarker([POINTS[0].lat, POINTS[0].lng], { radius: 7, color: '#16a34a', fillColor: '#16a34a', fillOpacity: 1 })
            .addTo(map).bindPopup('Départ');
        L.circleMarker([POINTS[POINTS.length - 1].lat, POINTS[POINTS.length - 1].lng],
            { radius: 7, color: '#dc2626', fillColor: '#dc2626', fillOpacity: 1 })
            .addTo(map).bindPopup('Arrivée');
    }

    var replayIdx = 0;
    var replayTimer = null;

    function replayStep() {
        if (replayIdx >= POINTS.length) {
            replayPause();
            return;
        }
        var p = POINTS[replayIdx];
        var latlng = [p.lat, p.lng];
        replayLine.addLatLng(latlng);
        if (marker) {
            marker.setLatLng(latlng);
        }
        document.getElementById('replay-counter').textContent = replayIdx + 1;
        replayIdx++;
    }

    window.replayPlay = function () {
        if (replayTimer) return;
        var speed = parseInt(document.getElementById('replay-speed').value);
        document.getElementById('btn-play').disabled = true;
        document.getElementById('btn-pause').disabled = false;
        replayTimer = setInterval(replayStep, speed);
    };

    window.replayPause = function () {
        clearInterval(replayTimer);
        replayTimer = null;
        document.getElementById('btn-play').disabled = false;
        document.getElementById('btn-pause').disabled = true;
    };

    window.replayReset = function () {
        replayPause();
        replayIdx = 0;
        replayLine.setLatLngs([]);
        if (marker && POINTS.length > 0) marker.setLatLng([POINTS[0].lat, POINTS[0].lng]);
        document.getElementById('replay-counter').textContent = '0';
    };
    </script>
</x-filament-panels::page>
