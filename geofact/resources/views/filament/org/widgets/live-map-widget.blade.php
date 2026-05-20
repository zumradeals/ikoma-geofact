<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Carte en temps réel</x-slot>
        <x-slot name="description">
            <span class="{{ $withPos > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-500' }} font-semibold">
                {{ $withPos }}
            </span>
            / {{ $total }} véhicule(s) localisé(s) — actualisation automatique toutes les 30s
        </x-slot>

        {{-- Données JSON injectées côté serveur --}}
        <div id="ikoma-dash-map-data"
             data-vehicles="{!! htmlspecialchars($vehiclesJson, ENT_QUOTES) !!}"
             style="display:none"></div>

        <style>
            .ikoma-dmap-tooltip { background:rgba(30,58,95,.92);border:none;border-radius:4px;color:#fff;font-size:11px;font-weight:700;padding:3px 7px;white-space:nowrap;box-shadow:0 1px 4px rgba(0,0,0,.35); }
            .ikoma-dmap-tooltip::before { display:none; }
            .ikoma-dmap-marker-moving { animation:ikoma-pulse 1.5s ease-in-out infinite; }
            @keyframes ikoma-pulse { 0%,100%{transform:scale(1);opacity:1} 50%{transform:scale(1.3);opacity:.8} }
        </style>

        <div style="height:380px;border-radius:8px;position:relative;">
            <div id="ikoma-dash-map" style="height:100%;width:100%;position:absolute;top:0;left:0;border-radius:8px;"></div>
        </div>

        <script>
            (function () {
                function doInit() {
                    var container = document.getElementById('ikoma-dash-map');
                    var dataEl    = document.getElementById('ikoma-dash-map-data');
                    if (!container || !dataEl || typeof L === 'undefined') return;

                    // Détruire l'instance précédente si elle existe
                    if (window._ikomaDashMap) {
                        window._ikomaDashMap.remove();
                        window._ikomaDashMap = null;
                    }

                    var vehicles = JSON.parse(dataEl.dataset.vehicles || '[]');
                    var withPos  = vehicles.filter(function(v){ return v.has_pos; });

                    var center = [5.345317, -4.024429], zoom = 8;
                    if (withPos.length === 1) {
                        center = [withPos[0].lat, withPos[0].lng]; zoom = 13;
                    } else if (withPos.length > 1) {
                        var lats = withPos.map(function(v){ return v.lat; });
                        var lngs = withPos.map(function(v){ return v.lng; });
                        center = [(Math.min.apply(null,lats)+Math.max.apply(null,lats))/2,
                                  (Math.min.apply(null,lngs)+Math.max.apply(null,lngs))/2];
                        zoom = 9;
                    }

                    var map = L.map('ikoma-dash-map', { zoomControl:true, preferCanvas:true }).setView(center, zoom);
                    window._ikomaDashMap = map;

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© <a href="https://openstreetmap.org">OpenStreetMap</a>', maxZoom: 19
                    }).addTo(map);

                    withPos.forEach(function(v) {
                        var moving = v.speed > 2;
                        var color  = moving ? '#16a34a' : '#1e3a5f';
                        var size   = moving ? 14 : 10;
                        var icon   = L.divIcon({
                            html: '<div class="'+(moving?'ikoma-dmap-marker-moving':'')+'" style="width:'+size+'px;height:'+size+'px;background:'+color+';border:2.5px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.4);"></div>',
                            className:'', iconSize:[size,size], iconAnchor:[size/2,size/2]
                        });
                        var tsLabel = v.ts ? new Date(v.ts).toLocaleString('fr-FR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}) : '—';
                        L.marker([v.lat,v.lng],{icon:icon}).addTo(map)
                            .bindTooltip('<strong>'+v.plate+'</strong><br>'+v.speed+' km/h · '+tsLabel,
                                {className:'ikoma-dmap-tooltip',direction:'top',offset:[0,-8]});
                    });

                    setTimeout(function(){ map.invalidateSize(); }, 400);
                }

                // Attendre que Leaflet soit disponible (chargé dans le <head> du panel)
                function tryInit(attempts) {
                    if (typeof L !== 'undefined') { doInit(); }
                    else if (attempts > 0) { setTimeout(function(){ tryInit(attempts-1); }, 200); }
                }

                // Lancer au prochain tick (après que Livewire a injecté le HTML dans le DOM)
                setTimeout(function(){ tryInit(10); }, 0);

                // Ré-initialiser après navigation Livewire
                document.addEventListener('livewire:navigated', function(){ setTimeout(function(){ tryInit(10); }, 0); });
            })();
        </script>
    </x-filament::section>
</x-filament-widgets::widget>
