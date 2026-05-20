<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Carte en temps réel</x-slot>
        <x-slot name="description">
            <span class="{{ $withPos > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-500' }} font-semibold">
                {{ $withPos }}
            </span>
            / {{ $total }} véhicule(s) localisé(s) — actualisation automatique toutes les 30s
        </x-slot>

        <div id="ikoma-dash-map-data"
             data-vehicles="{!! htmlspecialchars($vehiclesJson, ENT_QUOTES) !!}"
             style="display:none"></div>

        <link rel="stylesheet" href="/vendor/leaflet/leaflet.min.css" />

        <div style="height:380px;border-radius:8px;position:relative;">
            <div id="ikoma-dash-map" style="height:100%;width:100%;position:absolute;top:0;left:0;border-radius:8px;"></div>
        </div>

        <style>
            .ikoma-dmap-tooltip {
                background: rgba(30,58,95,0.92);
                border: none;
                border-radius: 4px;
                color: #fff;
                font-size: 11px;
                font-weight: 700;
                padding: 3px 7px;
                white-space: nowrap;
                box-shadow: 0 1px 4px rgba(0,0,0,.35);
            }
            .ikoma-dmap-tooltip::before { display: none; }
            .ikoma-dmap-marker-moving { animation: ikoma-pulse 1.5s ease-in-out infinite; }
            @keyframes ikoma-pulse {
                0%,100% { transform: scale(1); opacity:1; }
                50%      { transform: scale(1.3); opacity:.8; }
            }
        </style>

        <script src="/vendor/leaflet/leaflet.min.js"></script>
        <script>
            (function () {
                function initMap() {
                    if (typeof L === 'undefined') { setTimeout(initMap, 200); return; }
                    const container = document.getElementById('ikoma-dash-map');
                    const dataEl    = document.getElementById('ikoma-dash-map-data');
                    if (!container || !dataEl) return;

                    const vehicles = JSON.parse(dataEl.dataset.vehicles || '[]');
                    const withPos  = vehicles.filter(v => v.has_pos);

                    if (window._ikomaDashMapInstance) {
                        window._ikomaDashMapInstance.remove();
                        window._ikomaDashMapInstance = null;
                    }

                    // Default center: Abidjan, Ivory Coast
                    let center = [5.345317, -4.024429];
                    let zoom   = 8;

                    if (withPos.length === 1) {
                        center = [withPos[0].lat, withPos[0].lng];
                        zoom = 13;
                    } else if (withPos.length > 1) {
                        const lats = withPos.map(v => v.lat);
                        const lngs = withPos.map(v => v.lng);
                        center = [
                            (Math.min(...lats) + Math.max(...lats)) / 2,
                            (Math.min(...lngs) + Math.max(...lngs)) / 2
                        ];
                        zoom = 9;
                    }

                    const map = L.map('ikoma-dash-map', { zoomControl: true, preferCanvas: true }).setView(center, zoom);
                    window._ikomaDashMapInstance = map;
                    setTimeout(function () { map.invalidateSize(); }, 300);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>',
                        maxZoom: 19,
                    }).addTo(map);

                    vehicles.forEach(function (v) {
                        if (!v.has_pos) return;

                        const moving = v.speed > 2;
                        const color  = moving ? '#16a34a' : '#1e3a5f';
                        const size   = moving ? 14 : 10;

                        const icon = L.divIcon({
                            html: `<div class="${moving ? 'ikoma-dmap-marker-moving' : ''}"
                                        style="width:${size}px;height:${size}px;background:${color};border:2.5px solid #fff;
                                               border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>`,
                            className: '',
                            iconSize:  [size, size],
                            iconAnchor: [size / 2, size / 2],
                        });

                        const tsLabel = v.ts
                            ? new Date(v.ts).toLocaleString('fr-FR', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' })
                            : '—';

                        L.marker([v.lat, v.lng], { icon }).addTo(map)
                            .bindTooltip(
                                `<strong>${v.plate}</strong><br>${v.name}<br>${v.speed ?? 0} km/h · ${tsLabel}`,
                                { className: 'ikoma-dmap-tooltip', direction: 'top', offset: [0, -8] }
                            );
                    });
                }

                // Lancer immédiatement (DOMContentLoaded peut être déjà passé en SPA Livewire)
                setTimeout(initMap, 150);
                document.addEventListener('livewire:navigated', function () { setTimeout(initMap, 150); });
                document.addEventListener('livewire:updated',   function () { setTimeout(initMap, 150); });
            })();
        </script>
    </x-filament::section>
</x-filament-widgets::widget>
