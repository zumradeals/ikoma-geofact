<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
  .header { background: #1e3a5f; color: #fff; padding: 24px 32px; }
  .header h1 { font-size: 20px; font-weight: 700; letter-spacing: 0.5px; }
  .header .subtitle { font-size: 12px; color: #93c5fd; margin-top: 4px; }
  .header .period { font-size: 11px; color: #bfdbfe; margin-top: 2px; }
  .accent-bar { height: 4px; background: #f97316; }
  .body { padding: 28px 32px; }
  .section { margin-bottom: 24px; }
  .section-title {
    font-size: 12px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: #1e3a5f;
    border-bottom: 2px solid #f97316; padding-bottom: 4px; margin-bottom: 12px;
  }
  .kpi-grid { display: table; width: 100%; border-collapse: separate; border-spacing: 8px; }
  .kpi-row  { display: table-row; }
  .kpi-cell {
    display: table-cell; width: 25%;
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 12px; text-align: center;
  }
  .kpi-value { font-size: 22px; font-weight: 700; color: #1e3a5f; }
  .kpi-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
  .score-circle {
    display: inline-block; width: 80px; height: 80px; border-radius: 50%;
    text-align: center; line-height: 80px; font-size: 24px; font-weight: 700; color: #fff;
    margin: 0 auto;
  }
  .score-good    { background: #22c55e; }
  .score-medium  { background: #f97316; }
  .score-bad     { background: #dc2626; }
  .driver-info {
    background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;
    padding: 14px 18px; margin-bottom: 20px; display: table; width: 100%;
  }
  .info-row { display: table-row; }
  .info-cell { display: table-cell; width: 33%; padding: 4px 8px; }
  .info-label { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
  .info-value { font-size: 13px; font-weight: 700; color: #1e3a5f; margin-top: 2px; }
  .ai-box {
    background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%);
    border: 1px solid #bfdbfe; border-left: 4px solid #1e3a5f;
    border-radius: 6px; padding: 16px; margin-bottom: 16px;
  }
  .ai-box p { line-height: 1.6; color: #1e293b; }
  .list-item { padding: 7px 10px; margin-bottom: 5px; border-radius: 4px; font-size: 10.5px; line-height: 1.5; }
  .list-anomaly { background: #fff7ed; border-left: 3px solid #f97316; }
  .list-reco    { background: #f0fdf4; border-left: 3px solid #22c55e; }
  table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
  th { background: #1e3a5f; color: #fff; padding: 7px 10px; text-align: left; font-weight: 600; }
  td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; }
  tr:last-child td { border-bottom: none; }
  tr:nth-child(even) td { background: #f8fafc; }
  .badge { display: inline-block; padding: 2px 7px; border-radius: 9px; font-size: 9px; font-weight: 700; }
  .badge-critical { background: #fee2e2; color: #dc2626; }
  .badge-high     { background: #ffedd5; color: #ea580c; }
  .badge-medium   { background: #fef9c3; color: #ca8a04; }
  .badge-low      { background: #dcfce7; color: #16a34a; }
  .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
</style>
</head>
<body>

<div class="header">
  <h1>IKOMA — Rapport Conducteur</h1>
  <div class="subtitle">{{ $data['driver_name'] }}</div>
  <div class="period">Permis : {{ $data['license_number'] }} &nbsp;|&nbsp; Période : {{ $data['period_from'] }} → {{ $data['period_to'] }}</div>
</div>
<div class="accent-bar"></div>

<div class="body">

  {{-- Identité conducteur --}}
  <div class="section">
    <div class="section-title">Identité du conducteur</div>
    <div class="driver-info">
      <div class="info-row">
        <div class="info-cell">
          <div class="info-label">Nom complet</div>
          <div class="info-value">{{ $data['driver_name'] }}</div>
        </div>
        <div class="info-cell">
          <div class="info-label">Téléphone</div>
          <div class="info-value">{{ $data['driver_phone'] }}</div>
        </div>
        <div class="info-cell">
          <div class="info-label">Expiration permis</div>
          <div class="info-value">{{ $data['license_expiry'] }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Score de sécurité --}}
  <div class="section">
    <div class="section-title">Score de sécurité</div>
    <div style="text-align:center; padding: 16px 0;">
      @php
        $scoreClass = $data['safety_score'] >= 80 ? 'score-good'
                    : ($data['safety_score'] >= 50 ? 'score-medium' : 'score-bad');
      @endphp
      <div class="score-circle {{ $scoreClass }}">{{ $data['safety_score'] }}</div>
      <div style="margin-top:8px; font-size:11px; color:#64748b;">
        @if($data['safety_score'] >= 80) Conducteur fiable
        @elseif($data['safety_score'] >= 50) Vigilance requise
        @else Risque élevé — action immédiate
        @endif
      </div>
    </div>
  </div>

  {{-- KPIs --}}
  <div class="section">
    <div class="section-title">Indicateurs de la période</div>
    <div class="kpi-grid">
      <div class="kpi-row">
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['total_trips'] }}</div>
          <div class="kpi-label">Trajets</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['total_km'] }}</div>
          <div class="kpi-label">Kilomètres</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['total_hours'] }}</div>
          <div class="kpi-label">Heures de conduite</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['active_days'] }}</div>
          <div class="kpi-label">Jours actifs</div>
        </div>
      </div>
      <div class="kpi-row" style="margin-top:8px;">
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['avg_speed_kmh'] ?? '—' }}</div>
          <div class="kpi-label">Vitesse moy. (km/h)</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['max_speed_kmh'] ?? '—' }}</div>
          <div class="kpi-label">Vitesse max (km/h)</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['anomalous_trips'] }}</div>
          <div class="kpi-label">Trajets anomaleux</div>
        </div>
        <div class="kpi-cell">
          <div class="kpi-value">{{ $data['vehicles_count'] }}</div>
          <div class="kpi-label">Véhicules utilisés</div>
        </div>
      </div>
    </div>
  </div>

  {{-- Analyse IA --}}
  <div class="section">
    <div class="section-title">Analyse IA</div>
    <div class="ai-box">
      <p>{{ $ai['summary'] }}</p>
    </div>
    @if(!empty($ai['anomalies']))
      @foreach($ai['anomalies'] as $a)
        <div class="list-item list-anomaly">⚠ {{ $a }}</div>
      @endforeach
    @endif
  </div>

  @if(!empty($ai['recommendations']))
  <div class="section">
    <div class="section-title">Recommandations</div>
    @foreach($ai['recommendations'] as $r)
      <div class="list-item list-reco">✓ {{ $r }}</div>
    @endforeach
  </div>
  @endif

  {{-- Véhicules utilisés --}}
  @if(!empty($data['vehicles_used']))
  <div class="section">
    <div class="section-title">Véhicules utilisés</div>
    <table>
      <thead>
        <tr><th>Immatriculation</th><th>Trajets</th><th>Distance (km)</th></tr>
      </thead>
      <tbody>
        @foreach($data['vehicles_used'] as $v)
          <tr>
            <td><strong>{{ $v['plate'] }}</strong></td>
            <td>{{ $v['trips'] }}</td>
            <td>{{ $v['distance'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

  {{-- Alertes --}}
  @if($data['alerts_total'] > 0)
  <div class="section">
    <div class="section-title">Alertes déclenchées ({{ $data['alerts_total'] }})</div>
    <table>
      <thead>
        <tr>
          <th>Sévérité</th>
          <th>Nombre</th>
          <th>Statut</th>
        </tr>
      </thead>
      <tbody>
        @foreach(['CRITICAL'=>'Critique','HIGH'=>'Haute','MEDIUM'=>'Moyenne','LOW'=>'Basse'] as $sev => $label)
          @if(($data['alerts_' . strtolower($sev)] ?? 0) > 0)
          <tr>
            <td><span class="badge badge-{{ strtolower($sev) }}">{{ $label }}</span></td>
            <td>{{ $data['alerts_' . strtolower($sev)] }}</td>
            <td>{{ $data['alerts_unresolved'] > 0 ? $data['alerts_unresolved'] . ' non résolues' : 'Toutes résolues' }}</td>
          </tr>
          @endif
        @endforeach
      </tbody>
    </table>
  </div>
  @endif

</div>

<div class="footer">
  Rapport généré par IKOMA Geofact &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
