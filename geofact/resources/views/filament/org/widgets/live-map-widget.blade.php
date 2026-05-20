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

        <div
            x-data="{
                vehicles: {!! $vehiclesJson !!},
                map: null,
                init() {
                    this.$nextTick(() => this.initMap());
                },
                initMap() {
                    if (typeof L === 'undefined') {
                        setTimeout(() => this.initMap(), 300);
                        return;
                    }
                    const el = document.getElementById('ikoma-live-map');
                    if (!el) return;
                    if (this.map) { this.map.remove(); this.map = null; }

                    const withPos = this.vehicles.filter(v => v.has_pos);
                    let center = [5.345317, -4.024429], zoom = 8;
                    if (withPos.length === 1) { center = [withPos[0].lat, withPos[0].lng]; zoom = 13; }
                    else if (withPos.length > 1) {
                        const lats = withPos.map(v => v.lat), lngs = withPos.map(v => v.lng);
                        center = [(Math.min(...lats)+Math.max(...lats))/2, (Math.min(...lngs)+Math.max(...lngs))/2];
                        zoom = 9;
                    }

                    this.map = L.map(el, {zoomControl:true, preferCanvas:true}).setView(center, zoom);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution:'© <a href=\"https://openstreetmap.org\">OSM</a>', maxZoom:19
                    }).addTo(this.map);

                    withPos.forEach(v => {
                        const moving = v.speed > 2;
                        const color  = moving ? '#16a34a' : '#1e3a5f';
                        const size   = moving ? 14 : 10;
                        const icon   = L.divIcon({
                            html: `<div class='${moving ? 'ikoma-dmap-pulse' : ''}' style='width:${size}px;height:${size}px;background:${color};border:2.5px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4);'></div>`,
                            className:'', iconSize:[size,size], iconAnchor:[size/2,size/2]
                        });
                        const ts = v.ts ? new Date(v.ts).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : '—';
                        L.marker([v.lat,v.lng],{icon}).addTo(this.map)
                            .bindTooltip(`<strong>${v.plate}</strong><br>${v.speed ?? 0} km/h · ${ts}`,
                                {className:'ikoma-dmap-tooltip',direction:'top',offset:[0,-8]});
                    });

                    setTimeout(() => this.map && this.map.invalidateSize(), 400);
                }
            }"
            style="height:380px;position:relative;"
        >
            <div id="ikoma-live-map" style="height:100%;width:100%;border-radius:8px;"></div>
        </div>

    </x-filament::section>
</x-filament-widgets::widget>
