{{--
    Éditeur Leaflet.Draw pour la géométrie des GeoZones.
    ViewField::make('geometry_map_editor') — synchronise avec data.geometry via $wire.set().
--}}
@php $existingJson = $livewire->data['geometry'] ?? ''; @endphp

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" crossorigin="" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-draw@1.0.4/dist/leaflet.draw.css" crossorigin="" />
<style>
    #gz-map-editor { height: 420px; border-radius: 8px; overflow: hidden; z-index: 0; }
    .gz-json-preview {
        margin-top: 8px;
        padding: 8px 12px;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 11px;
        font-family: monospace;
        color: #475569;
        word-break: break-all;
        max-height: 60px;
        overflow: hidden;
        white-space: nowrap;
        text-overflow: ellipsis;
    }
    .dark .gz-json-preview { background: #1e293b; border-color: #334155; color: #94a3b8; }
    .gz-hint {
        font-size: 12px;
        color: #64748b;
        margin-bottom: 8px;
    }
</style>

<div class="gz-hint">
    Dessinez une zone sur la carte. Utilisez les outils en haut à gauche pour tracer un cercle, un polygone ou un rectangle.
    La géométrie sera enregistrée automatiquement.
</div>

<div id="gz-map-editor"></div>

<div class="gz-json-preview" id="gz-json-preview">
    {{ $existingJson ?: 'Aucune géométrie — dessinez une zone sur la carte' }}
</div>

<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js" crossorigin=""></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet-draw@1.0.4/dist/leaflet.draw.js" crossorigin=""></script>
<script>
(function () {
    const EXISTING_JSON = @json($existingJson);
    let map = null;
    let drawnItems = null;

    function initGzMap() {
        const container = document.getElementById('gz-map-editor');
        if (!container) return;
        if (window._gzMapInstance) { window._gzMapInstance.remove(); window._gzMapInstance = null; }

        // Default center: Abidjan
        let center = [5.345317, -4.024429];
        let zoom   = 8;

        map = L.map('gz-map-editor').setView(center, zoom);
        window._gzMapInstance = map;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap', maxZoom: 19
        }).addTo(map);

        drawnItems = new L.FeatureGroup();
        map.addLayer(drawnItems);

        const drawControl = new L.Control.Draw({
            draw: {
                polyline:  false,
                marker:    false,
                circlemarker: false,
                circle:    { shapeOptions: { color: '#f97316' } },
                polygon:   { shapeOptions: { color: '#1e3a5f' }, allowIntersection: false },
                rectangle: { shapeOptions: { color: '#16a34a' } },
            },
            edit: { featureGroup: drawnItems, remove: true }
        });
        map.addControl(drawControl);

        // Load existing geometry
        if (EXISTING_JSON) {
            loadExistingGeometry(EXISTING_JSON);
        }

        // On draw created
        map.on('draw:created', function (e) {
            drawnItems.clearLayers();
            drawnItems.addLayer(e.layer);
            persistGeometry(e.layerType, e.layer);
        });

        // On draw edited
        map.on('draw:edited', function (e) {
            e.layers.eachLayer(function (layer) {
                const type = layer instanceof L.Circle ? 'circle'
                           : (layer instanceof L.Rectangle ? 'rectangle' : 'polygon');
                persistGeometry(type, layer);
            });
        });

        // On draw deleted
        map.on('draw:deleted', function () {
            updateField('');
        });
    }

    function loadExistingGeometry(jsonStr) {
        if (!jsonStr) return;
        let geo;
        try { geo = JSON.parse(jsonStr); } catch (e) { return; }

        const type = (geo.type || '').toLowerCase();

        if (type === 'circle' && geo.center && geo.radius) {
            const lat = geo.center[0], lng = geo.center[1];
            const circle = L.circle([lat, lng], {
                radius: geo.radius,
                color: '#f97316'
            });
            drawnItems.addLayer(circle);
            map.setView([lat, lng], 13);

        } else if ((type === 'polygon' || type === 'rectangle') && geo.coordinates) {
            const coords = geo.coordinates[0];
            const latlngs = coords.map(c => [c[1], c[0]]); // [lng,lat] → [lat,lng]
            const poly = L.polygon(latlngs, { color: type === 'rectangle' ? '#16a34a' : '#1e3a5f' });
            drawnItems.addLayer(poly);
            map.fitBounds(poly.getBounds());
        }
    }

    function persistGeometry(layerType, layer) {
        let geometry = null;

        if (layerType === 'circle') {
            const c = layer.getLatLng();
            geometry = {
                type: 'circle',
                center: [parseFloat(c.lat.toFixed(7)), parseFloat(c.lng.toFixed(7))],
                radius: Math.round(layer.getRadius())
            };
        } else if (layerType === 'rectangle') {
            const lls = layer.getLatLngs()[0];
            geometry = {
                type: 'rectangle',
                coordinates: [lls.map(ll => [parseFloat(ll.lng.toFixed(7)), parseFloat(ll.lat.toFixed(7))])]
            };
            // Close ring
            geometry.coordinates[0].push(geometry.coordinates[0][0]);
        } else {
            const lls = layer.getLatLngs()[0];
            geometry = {
                type: 'polygon',
                coordinates: [lls.map(ll => [parseFloat(ll.lng.toFixed(7)), parseFloat(ll.lat.toFixed(7))])]
            };
            geometry.coordinates[0].push(geometry.coordinates[0][0]);
        }

        updateField(JSON.stringify(geometry));
    }

    function updateField(json) {
        const preview = document.getElementById('gz-json-preview');
        if (preview) preview.textContent = json || 'Zone supprimée — redessinée une zone';

        // Update Livewire form state
        if (window.Livewire && json !== undefined) {
            const component = window.Livewire.find(
                document.getElementById('gz-map-editor')
                    ?.closest('[wire\\:id]')
                    ?.getAttribute('wire:id')
            );
            if (component) {
                component.set('data.geometry', json);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', initGzMap);
    document.addEventListener('livewire:navigated', initGzMap);
    document.addEventListener('livewire:update', function () { setTimeout(initGzMap, 100); });
})();
</script>
