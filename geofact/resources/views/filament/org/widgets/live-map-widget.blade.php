<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Carte en temps réel</x-slot>
        <x-slot name="description">
            <span class="{{ $withPos > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-500' }} font-semibold">
                {{ $withPos }}
            </span>
            / {{ $total }} véhicule(s) localisé(s) — actualisation automatique toutes les 30s
        </x-slot>

        <style>
            .ikoma-dmap-tooltip{background:rgba(30,58,95,.92);border:none;border-radius:4px;color:#fff;font-size:11px;font-weight:700;padding:3px 7px;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.35);}
            .ikoma-dmap-tooltip::before{display:none;}
            .ikoma-dmap-pulse{animation:ikoma-pulse 1.5s ease-in-out infinite;}
            @keyframes ikoma-pulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.3);opacity:.8}}
        </style>

        <script>
        var _ikomaVehicles = {!! $vehiclesJson !!};

        function ikomaLiveMap() {
            return {
                vehicles: _ikomaVehicles,
                map: null,
                init: function () {
                    var self = this;
                    this.$nextTick(function () { self.initMap(); });
                },
                initMap: function () {
                    var self = this;
                    if (typeof L === 'undefined') {
                        setTimeout(function () { self.initMap(); }, 300);
                        return;
                    }
                    var el = document.getElementById('ikoma-live-map');
                    if (!el) return;
                    if (this.map) { this.map.remove(); this.map = null; }

                    var withPos = this.vehicles.filter(function (v) { return v.has_pos; });
                    var center = [5.345317, -4.024429], zoom = 8;
                    if (withPos.length === 1) {
                        center = [withPos[0].lat, withPos[0].lng];
                        zoom = 13;
                    } else if (withPos.length > 1) {
                        var lats = withPos.map(function (v) { return v.lat; });
                        var lngs = withPos.map(function (v) { return v.lng; });
                        center = [
                            (Math.min.apply(null, lats) + Math.max.apply(null, lats)) / 2,
                            (Math.min.apply(null, lngs) + Math.max.apply(null, lngs)) / 2
                        ];
                        zoom = 9;
                    }

                    this.map = L.map(el, { zoomControl: true, preferCanvas: true }).setView(center, zoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© <a href="https://openstreetmap.org">OSM</a>',
                        maxZoom: 19
                    }).addTo(this.map);

                    withPos.forEach(function (v) {
                        var moving = v.speed > 2;
                        var color  = moving ? '#16a34a' : '#1e3a5f';
                        var size   = moving ? 14 : 10;
                        var pulse  = moving ? 'ikoma-dmap-pulse' : '';
                        var html   = '<div class="' + pulse + '" style="width:' + size + 'px;height:' + size + 'px;background:' + color + ';border:2.5px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>';
                        var icon   = L.divIcon({
                            html: html,
                            className: '',
                            iconSize: [size, size],
                            iconAnchor: [size / 2, size / 2]
                        });
                        var ts = v.ts
                            ? new Date(v.ts).toLocaleString('fr-FR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })
                            : '—';
                        var tooltip = '<strong>' + v.plate + '</strong><br>' + (v.speed || 0) + ' km/h · ' + ts;
                        L.marker([v.lat, v.lng], { icon: icon })
                            .addTo(self.map)
                            .bindTooltip(tooltip, { className: 'ikoma-dmap-tooltip', direction: 'top', offset: [0, -8] });
                    });

                    setTimeout(function () { self.map && self.map.invalidateSize(); }, 400);
                }
            };
        }
        </script>

        <div x-data="ikomaLiveMap()" style="height:380px;position:relative;">
            <div id="ikoma-live-map" style="height:100%;width:100%;border-radius:8px;"></div>
        </div>

    </x-filament::section>
</x-filament-widgets::widget>
