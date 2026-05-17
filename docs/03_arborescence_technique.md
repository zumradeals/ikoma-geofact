# Arborescence Technique GEOFACT
## Stack : PHP / Laravel 11 / MySQL / cPanel

---

## Structure racine

```
geofact/
│
├── app/
│   ├── Core/                    ← GEOFACT Core — couche canonique pure
│   ├── Connector/               ← Connector Layer — isolation totale
│   ├── Rules/                   ← Rules Engine — évaluation déterministe
│   ├── Kpi/                     ← KPI Engine — RT et DF séparés
│   ├── Insight/                 ← Insight Engine — IA contextuelle
│   ├── Delivery/                ← Delivery Engine — distribution
│   ├── RawStore/                ← Raw Store — append-only absolu
│   ├── Auth/                    ← Authentification JWT
│   ├── Models/                  ← Modèles Eloquent (1 par table DC)
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Events/
│   ├── Listeners/
│   ├── Jobs/
│   └── Exceptions/
│
├── database/
│   ├── migrations/              ← 16 migrations dans l'ordre des FK
│   └── seeders/
│
├── config/
│   ├── geofact.php              ← Configuration centrale
│   ├── connectors.php           ← Providers supportés
│   └── delivery.php             ← Canaux WhatsApp/Email
│
├── routes/
│   ├── api.php                  ← Routes API V1 versionnées
│   ├── webhook.php              ← Routes Connector push
│   └── console.php              ← Commandes Scheduler DF
│
├── tests/
│   ├── Unit/
│   └── Feature/
│
└── docs/                        ← Référentiels contractuels
```

---

## app/Core/ — Couche canonique pure

```
app/Core/
├── Canonical/
│   ├── CanonicalEvent.php           ← Objet immuable — seul objet accepté par le Core
│   ├── CanonicalEventFactory.php    ← Produit les CanonicalEvents
│   └── CompletenessEvaluator.php    ← Évalue CCS + CCM — décide COMPLETE/INCOMPLETE/REJECTED
│
├── Contracts/                        ← Interfaces contractuelles — jamais bypassed
│   ├── ConnectorInterface.php
│   ├── RulesEngineInterface.php
│   ├── KpiEngineInterface.php
│   ├── InsightEngineInterface.php
│   └── DeliveryEngineInterface.php
│
├── Deduplication/
│   └── CanonicalDeduplicator.php    ← Déduplication canonique finale (C-01)
│
└── TenantResolver.php               ← Injecte organization_id sur chaque requête (C-12)
```

> **Règle absolue** : `app/Core/` n'a AUCUN import depuis Connector/, Rules/, Kpi/, Delivery/, Insight/. Seules les interfaces de Contracts/ sont connues du reste du système.

---

## app/Connector/ — Connector Layer

```
app/Connector/
├── Contracts/
│   └── ConnectorDriverInterface.php ← Interface que tout driver doit implémenter
│
├── Drivers/                          ← Un driver par fournisseur GPS
│   ├── WialonDriver.php
│   ├── TraccarDriver.php
│   ├── TeltonikaDriver.php
│   └── CustomApiDriver.php
│
├── Normalizer/
│   ├── FieldNormalizer.php           ← Traduit speed→speed_kmh, lat→latitude...
│   └── NormalizationMap.php          ← Dictionnaire de mapping par provider_id
│
├── Deduplication/
│   └── TransportDeduplicator.php     ← Déduplication transport avant Raw Store (C-10)
│
├── Auth/
│   └── ConnectorAuthenticator.php   ← Valide token + token_version du Connector
│
└── ConnectorPipeline.php            ← Orchestre : Auth → Dedup → Normalize → Produce
```

**Pipeline ConnectorPipeline — 10 étapes dans l'ordre strict :**
1. `ConnectorAuthenticator::authenticate()` → échec : 401, stop total, rien écrit
2. `RawStoreWriter::write(payload, flag='OK')` → retourne rawRef
3. `TransportDeduplicator::isDuplicate()` → si true : flag DUPLICATE_TRANSPORT, return null
4. `FieldNormalizer::normalize()`
5. `CompletenessEvaluator::evaluate()` → si REJECTED : flag REJECTED, return null
6. `CanonicalEventFactory::create()`
7. `CanonicalDeduplicator::isDuplicate()` → si true : return null
8. `TenantResolver::resolve()` si vehicle_id absent
9. INSERT TelemetryEvent via Eloquent — INSERT uniquement
10. `event(new CanonicalEventReceived($event))`

---

## app/Rules/ — Rules Engine

```
app/Rules/
├── Contracts/
│   └── RuleInterface.php            ← Interface que toute règle doit implémenter
│
├── SystemRules/                      ← RS officielles — une classe par règle
│   ├── RS01_OverspeedRule.php
│   ├── RS02_SuspiciousStopRule.php
│   ├── RS03_HarshBrakingRule.php
│   ├── RS04_GeozoneEntryRule.php
│   └── RS05_MaintenanceThresholdRule.php
│
├── ClientRules/
│   └── RmcRuleLoader.php            ← Charge les RMC du tenant depuis la DB
│
└── Engine/
    ├── SystemRulesEvaluator.php
    ├── ClientRulesEvaluator.php
    └── RulesEngine.php              ← Orchestre RS puis RMC dans l'ordre strict
```

**Ordre d'évaluation RulesEngine :**
1. `SystemRulesEvaluator::evaluate()` — toutes les RS applicables, pas de court-circuit
2. `ClientRulesEvaluator::evaluate()` — RMC du tenant
3. Merge — si conflit RS vs RMC : RS prime, conflit loggué
4. INSERT chaque Alert via Eloquent
5. `event(new AlertTriggered($alert))` pour chaque Alert

---

## app/Kpi/ — KPI Engine

```
app/Kpi/
├── RealTime/
│   ├── Calculators/
│   │   ├── CurrentSpeedCalculator.php
│   │   ├── VehicleStatusCalculator.php
│   │   ├── OverspeedCountCalculator.php
│   │   ├── ActiveTripDurationCalculator.php
│   │   └── CurrentZoneCalculator.php
│   ├── RtKpiEngine.php              ← Calculé à chaque événement canonique
│   └── RtKpiPublisher.php
│
├── Deferred/
│   ├── Calculators/
│   │   ├── DriverScoreCalculator.php
│   │   ├── FleetUtilizationCalculator.php
│   │   ├── WeeklyPerformanceCalculator.php
│   │   ├── BehavioralAnalysisCalculator.php
│   │   └── MonthlyReportCalculator.php
│   ├── DfKpiEngine.php              ← Déclenché par le Scheduler
│   └── DfKpiVersioner.php           ← Gère le versioning des KPIs (C-05)
│
└── Aggregator/
    └── ScopeAggregator.php          ← Vehicle→Fleet→Organization (C-08)
```

**Scheduler DF (routes/console.php) :**
```php
$schedule->job(new ComputeDeferredKpis('driver_score'))->dailyAt('01:00');
$schedule->job(new ComputeDeferredKpis('fleet_utilization'))->dailyAt('01:30');
$schedule->job(new ComputeDeferredKpis('weekly_performance'))->weeklyOn(1, '02:00');
$schedule->job(new ComputeDeferredKpis('monthly_report'))->monthlyOn(1, '03:00');
```

---

## app/Insight/ — Insight Engine

```
app/Insight/
├── InsightEngine.php                ← Orchestre la génération
├── PacketBuilder.php                ← Prépare le paquet structuré pour l'IA (C-06)
├── InsightValidator.php             ← Valide scope_type + scope_id avant transmission
├── InsightVersioner.php             ← Gère le versioning des insights
└── Fallback/
    └── InsightFallbackHandler.php   ← Livre l'objet sans insight si IA indisponible
```

---

## app/Delivery/ — Delivery Engine

```
app/Delivery/
├── DeliveryEngine.php               ← Évalue SP puis CDP dans l'ordre strict
├── Policies/
│   ├── SystemPoliciesEvaluator.php  ← SP non désactivables
│   └── ClientPoliciesEvaluator.php  ← CDP configurées par le client
├── Channels/                         ← Un handler par canal
│   ├── WhatsAppChannel.php
│   ├── EmailChannel.php
│   ├── ApiChannel.php
│   └── ExportChannel.php
└── DeliveryLogger.php               ← Loggue chaque livraison — succès et échec
```

---

## app/RawStore/ — Raw Store

```
app/RawStore/
├── RawStoreWriter.php               ← INSERT uniquement — jamais UPDATE
└── RawStoreReplayer.php             ← Rejoue les événements rejetés
```

---

## app/Auth/ — Authentification JWT

```
app/Auth/
├── JwtService.php                   ← Génère et valide les JWT GEOFACT
├── TokenVersionGuard.php            ← Vérifie token_version — révocation par version
├── ToleranceWindowHandler.php       ← Politique tolérance réseau 4h (C-12.3)
├── ScopeGuard.php                   ← Vérifie organization_id + fleet_ids sur chaque requête
└── RolePermissionMatrix.php         ← Matrice des droits C-12.5
```

---

## app/Models/ — Modèles Eloquent

```
app/Models/
├── Organization.php
├── Fleet.php
├── Vehicle.php
├── Driver.php
├── Device.php
├── Connector.php
├── Trip.php
├── GeoZone.php
├── Alert.php
├── TelemetryEvent.php   ← boot() : bloquer UPDATE et DELETE
├── RawStore.php         ← boot() : bloquer UPDATE et DELETE
├── KpiRecord.php        ← boot() : bloquer UPDATE et DELETE
├── Insight.php
├── User.php
├── AuditLog.php         ← boot() : bloquer UPDATE et DELETE
└── VehicleTransfer.php
```

**Configuration obligatoire sur tous les modèles :**
```php
protected $primaryKey = 'id';
public $incrementing  = false;
protected $keyType    = 'string';
```

---

## app/Http/ — Controllers et Middleware

```
app/Http/
├── Controllers/
│   └── Api/V1/
│       ├── AuthController.php
│       ├── OrganizationController.php
│       ├── FleetController.php
│       ├── VehicleController.php
│       ├── DriverController.php
│       ├── TripController.php
│       ├── AlertController.php
│       ├── GeoZoneController.php
│       ├── KpiController.php
│       ├── InsightController.php
│       ├── ReportController.php
│       └── ConnectorController.php   ← Webhook push/pull
│
├── Middleware/
│   ├── JwtAuthMiddleware.php         ← Valide JWT sur chaque requête
│   ├── TenantInjectorMiddleware.php  ← Injecte organization_id — obligatoire sur toutes les routes API
│   ├── ScopeEnforcerMiddleware.php   ← Bloque accès hors scope
│   ├── ConnectorAuthMiddleware.php   ← Auth spécifique Connector
│   └── AuditMiddleware.php           ← Loggue toute action authentifiée
│
└── Requests/
    ├── StoreVehicleRequest.php
    ├── StoreTripRequest.php
    ├── ConnectorIngestRequest.php
    └── VehicleTransferRequest.php
```

---

## app/Events/ et app/Listeners/

```
app/Events/
├── CanonicalEventReceived.php
├── AlertTriggered.php
├── TripCompleted.php
├── KpiComputed.php
└── InsightGenerated.php

app/Listeners/
├── ProcessCanonicalEvent.php     → déclenche Rules Engine
├── TriggerDeliveryOnAlert.php    → déclenche Delivery Engine
├── ComputeRtKpisOnEvent.php      → déclenche KPI RT
└── GenerateInsightOnKpi.php      → déclenche Insight Engine
```

---

## app/Jobs/ — Jobs async et Scheduler DF

```
app/Jobs/
├── ProcessConnectorIngestion.php
├── ComputeDeferredKpis.php          ← Déclenché par Scheduler
├── GenerateInsight.php
├── DeliverAlert.php
├── ReplayRawStoreEvents.php
└── EscalateUnacknowledgedAlerts.php
```

---

## app/Exceptions/

```
app/Exceptions/
├── CanonicalValidationException.php
├── ConnectorAuthException.php
├── TenantViolationException.php
└── ContractViolationException.php
```

---

## database/migrations/ — Ordre strict

```
database/migrations/
├── 2026_01_01_000001_create_organizations_table.php
├── 2026_01_01_000002_create_fleets_table.php
├── 2026_01_01_000003_create_vehicles_table.php
├── 2026_01_01_000004_create_drivers_table.php
├── 2026_01_01_000005_create_devices_table.php
├── 2026_01_01_000006_create_connectors_table.php
├── 2026_01_01_000007_create_trips_table.php
├── 2026_01_01_000008_create_geozones_table.php
├── 2026_01_01_000009_create_alerts_table.php
├── 2026_01_01_000010_create_telemetry_events_table.php
├── 2026_01_01_000011_create_raw_store_table.php
├── 2026_01_01_000012_create_kpi_records_table.php
├── 2026_01_01_000013_create_insights_table.php
├── 2026_01_01_000014_create_users_table.php
├── 2026_01_01_000015_create_audit_logs_table.php
└── 2026_01_01_000016_create_vehicle_transfers_table.php
```

---

## database/seeders/

```
database/seeders/
├── GeofactAdminSeeder.php      ← Crée le compte geofact_admin initial
├── SystemRulesSeeder.php       ← Insère les RS officielles
└── EventTaxonomySeeder.php     ← Insère la taxonomie C-09
```

---

## config/geofact.php

```php
return [
    'tolerance_window_hours'       => 4,
    'raw_store_retention_days'     => 365,
    'audit_log_retention_days'     => 730,
    'max_transfer_delay_days'      => 90,
    'device_disconnect_threshold_hours' => 24,
];
```

---

## Routes API

```php
// routes/api.php
Route::prefix('api/v1')->middleware(['jwt.auth', 'tenant.inject'])->group(function () {
    Route::post('/auth/login',   [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    Route::apiResource('organizations', OrganizationController::class);
    Route::apiResource('fleets',        FleetController::class);
    Route::apiResource('vehicles',      VehicleController::class);
    Route::post('vehicles/{id}/transfer', [VehicleController::class, 'transfer']);
    Route::get('vehicles/{id}/active-trip', [TripController::class, 'activeTrip']);
    Route::apiResource('drivers',    DriverController::class);
    Route::apiResource('trips',      TripController::class);
    Route::apiResource('alerts',     AlertController::class);
    Route::patch('alerts/{id}/acknowledge', [AlertController::class, 'acknowledge']);
    Route::patch('alerts/{id}/resolve',     [AlertController::class, 'resolve']);
    Route::apiResource('geozones',   GeoZoneController::class);
    Route::get('kpis/{scopeType}/{scopeId}',         [KpiController::class, 'show']);
    Route::get('kpis/{scopeType}/{scopeId}/history', [KpiController::class, 'history']);
    Route::get('insights/{scopeType}/{scopeId}',     [InsightController::class, 'show']);
    Route::post('insights/{scopeType}/{scopeId}/generate', [InsightController::class, 'generate']);
});

// routes/webhook.php
Route::middleware(['connector.auth'])->group(function () {
    Route::post('/connector/ingest',       [ConnectorController::class, 'ingest']);
    Route::get('/connector/pull/{id}',     [ConnectorController::class, 'pull']);
});
```

---

## Règles d'arborescence — non négociables

| Règle | Description |
|-------|-------------|
| `Core/` ne dépend de rien | Aucun import depuis Connector/, Rules/, Delivery/ |
| `Connector/` ne connaît pas `Rules/` | Il produit — il n'évalue pas |
| `Rules/` ne connaît pas `Delivery/` | Il dispatch des Events — il ne livre pas |
| `Kpi/RealTime/` et `Kpi/Deferred/` | Jamais dans le même pipeline |
| `RawStore/`, `AuditLog`, `TelemetryEvent`, `KpiRecord` | INSERT uniquement — aucun UPDATE dans tout le projet |
| UUID v4 partout | Jamais d'auto-increment comme identifiant métier |
| Toute action passe par un Middleware | Jamais d'accès direct DB sans TenantInjectorMiddleware |

---

## Déploiement cPanel

```
public_html/         ← Pointer le Document Root ici
├── index.php        ← Copier depuis /public/index.php
└── .htaccess        ← Copier depuis /public/.htaccess

storage/             ← Hors public_html
bootstrap/cache/     ← Hors public_html
```

**.htaccess Laravel :**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

**Variables d'environnement (.env) :**
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://votre-domaine.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=geofact
DB_USERNAME=geofact_user
DB_PASSWORD=XXXXX
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

JWT_SECRET=XXXXX
JWT_TTL=60
GEOFACT_TOLERANCE_WINDOW_HOURS=4

ANTHROPIC_API_KEY=XXXXX
WHATSAPP_API_URL=XXXXX
WHATSAPP_TOKEN=XXXXX

QUEUE_CONNECTION=database
```
