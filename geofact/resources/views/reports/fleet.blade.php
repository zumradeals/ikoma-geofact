<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }

  /* En-tête */
  .header { background: #1e3a5f; color: #fff; padding: 24px 32px; }
  .header h1 { font-size: 20px; font-weight: 700; letter-spacing: 0.5px; }
  .header .subtitle { font-size: 12px; color: #93c5fd; margin-top: 4px; }
  .header .period { font-size: 11px; color: #bfdbfe; margin-top: 2px; }

  /* Bande orange */
  .accent-bar { height: 4px; background: #f97316; }

  /* Corps */
  .body { padding: 28px 32px; }

  /* Sections */
  .section { margin-bottom: 24px; }
  .section-title {
    font-size: 12px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: #1e3a5f;
    border-bottom: 2px solid #f97316; padding-bottom: 4px; margin-bottom: 12px;
  }

  /* Grille KPIs */
  .kpi-grid { display: table; width: 100%; border-collapse: separate; border-spacing: 8px; }
  .kpi-row  { display: table-row; }
  .kpi-cell {
    display: table-cell; width: 25%;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 12px; text-align: center;
  }
  .kpi-value { font-size: 22px; font-weight: 700; color: #1e3a5f; }
  .kpi-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }

  /* IA */
  .ai-box {
    background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
    border: 1px solid #bfdbfe; border-left: 4px solid #1e3a5f;
    border-radius: 6px; padding: 16px; margin-bottom: 16px;
  }
  .ai-box p { line-height: 1.6; color: #1e293b; }

  /* Listes */
  .list-item {
    padding: 7px 10px; margin-bottom: 5px;
    border-radius: 4px; font-size: 10.5px; line-height: 1.5;
  }
  .list-anomaly { background: #fff7ed; border-left: 3px solid #f97316; }
  .list-reco    { background: #f0fdf4; border-left: 3px solid #22c55e; }

  /* Table alertes */
  table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
  th { background: #1e3a5f; color: #fff; padding: 7px 10px; text-align: left; font-weight: 600; }
  td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; }
  tr:last-child td { border-bottom: none; }
  tr:nth-child(even) td { background: #f8fafc; }

  .badge {
    display: inline-block; padding: 2px 8px; border-radius: 10px;
    font-size: 9px; font-weight: 700; text-transform: uppercase;
  }
  .badge-critical { background: #fee2e2; color: #dc2626; }
  .badge-high     { background: #ffedd5; color: #ea580c; }
  .badge-medium   { background: #fef9c3; color: #ca8a04; }
  .badge-low      { background: #f0fdf4; color: #16a34a; }

  /* Pied de page */
  .footer {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: #f8fafc; border-top: 1px solid #e2e8f0;
    padding: 8px 32px; font-size: 9px; color: #94a3b8;
    display: flex; justify-content: space-between;
  }
</style>
</head>
<body>

<div class="header">
  <h1>IKOMA GEOFACT — Rapport Flotte</h1>
  <div class="subtitle">Intelligence Flotte · Rapport généré automatiquement</div>
  <div class="period">Période : {{ $data['period_from'] }} → {{ $data['period_to'] }}</div>
</div>
<div class="accent-bar"></div>

<div class="body">

  {{-- KPIs principaux --}}
  <div class="section">
    <div class="section-title">Indicateurs clés</div>
    <div class="kpi-grid">
      <div class="kpi-row">
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['total_vehicles'] }}</div>
          <div class="kpi-label">Véhicules actifs</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value" style="color:#f97316">{{ $data['coverage_pct'] }}%</div>
          <div class="kpi-label">Couverture GPS</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['total_trips'] }}</div>
          <div class="kpi-label">Trajets</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ number_format($data['total_km'], 0, ',', ' ') }} km</div>
          <div class="kpi-label">Distance totale</div>
        </div>
      </div>
    </div>
    <br>
    <div class="kpi-grid">
      <div class="kpi-row">
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['total_hours'] }} h</div>
          <div class="kpi-label">Heures conduite</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['avg_speed_kmh'] ?? '—' }} km/h</div>
          <div class="kpi-label">Vitesse moyenne</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value" style="{{ $data['alerts_critical'] > 0 ? 'color:#dc2626' : '' }}">
            {{ $data['alerts_total'] }}
          </div>
          <div class="kpi-label">Alertes totales</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value" style="{{ $data['anomalous_trips'] > 0 ? 'color:#ea580c' : '' }}">
            {{ $data['anomalous_trips'] }}
          </div>
          <div class="kpi-label">Trajets anomaleux</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Résumé IA --}}
  @if($ai['summary'])
  <div class="section">
    <div class="section-title">Analyse IA — Résumé exécutif</div>
    <div class="ai-box">
      <p>{{ $ai['summary'] }}</p>
    </div>
  </div>
  @endif

  {{-- Anomalies IA --}}
  @if(!empty($ai['anomalies']))
  <div class="section">
    <div class="section-title">Points d'attention détectés</div>
    @foreach($ai['anomalies'] as $item)
    <div class="list-item list-anomaly">⚠ {{ $item }}</div>
    @endforeach
  </div>
  @endif

  {{-- Recommandations IA --}}
  @if(!empty($ai['recommendations']))
  <div class="section">
    <div class="section-title">Recommandations</div>
    @foreach($ai['recommendations'] as $item)
    <div class="list-item list-reco">✓ {{ $item }}</div>
    @endforeach
  </div>
  @endif

  {{-- Répartition par flotte --}}
  @if(!empty($data['by_fleet']))
  <div class="section">
    <div class="section-title">Répartition par flotte</div>
    <table>
      <thead>
        <tr>
          <th>Flotte</th>
          <th>Véhicules</th>
          <th>Localisés GPS</th>
          <th>Couverture</th>
        </tr>
      </thead>
      <tbody>
        @foreach($data['by_fleet'] as $name => $fleet)
        <tr>
          <td>{{ $name }}</td>
          <td>{{ $fleet['count'] }}</td>
          <td>{{ $fleet['localized'] }}</td>
          <td>{{ $fleet['count'] > 0 ? round($fleet['localized'] / $fleet['count'] * 100) : 0 }}%</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

  {{-- Détail alertes --}}
  <div class="section">
    <div class="section-title">Détail des alertes ({{ $data['alerts_total'] }} total — {{ $data['alerts_unresolved'] }} non résolues)</div>
    <table>
      <thead>
        <tr>
          <th>Sévérité</th>
          <th>Nombre</th>
          <th>% du total</th>
        </tr>
      </thead>
      <tbody>
        @foreach([
          'CRITICAL' => ['label'=>'Critique','class'=>'badge-critical','count'=>$data['alerts_critical']],
          'HIGH'     => ['label'=>'Haute',   'class'=>'badge-high',    'count'=>$data['alerts_high']],
          'MEDIUM'   => ['label'=>'Moyenne', 'class'=>'badge-medium',  'count'=>$data['alerts_medium']],
          'LOW'      => ['label'=>'Basse',   'class'=>'badge-low',     'count'=>$data['alerts_low']],
        ] as $s)
        <tr>
          <td><span class="badge {{ $s['class'] }}">{{ $s['label'] }}</span></td>
          <td>{{ $s['count'] }}</td>
          <td>{{ $data['alerts_total'] > 0 ? round($s['count'] / $data['alerts_total'] * 100) : 0 }}%</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  {{-- Véhicule le plus actif --}}
  @if($data['top_vehicle'] !== '—')
  <div class="section">
    <div class="section-title">Véhicule le plus actif</div>
    <div class="ai-box" style="border-left-color:#f97316">
      <p><strong>{{ $data['top_vehicle'] }}</strong> — {{ number_format($data['top_vehicle_km'], 1, ',', ' ') }} km parcourus sur la période.</p>
    </div>
  </div>
  @endif

</div>

<div class="footer">
  <span>IKOMA GEOFACT — Rapport confidentiel</span>
  <span>Généré le {{ now()->format('d/m/Y à H:i') }}</span>
</div>

</body>
</html>
