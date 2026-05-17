# Prompts Claude Code Séquentiels
## IKOMA GEOFACT — Stack PHP / Laravel / MySQL / cPanel

---

> **Règle d'utilisation** : chaque prompt est autonome et séquentiel.
> Ne pas passer au prompt suivant avant validation complète du précédent.
> Claude Code ne doit jamais anticiper une couche supérieure.

---

## Récapitulatif des 12 Prompts

| Prompt | Couche | Dépend de |
|--------|--------|-----------|
| P-00 | Instruction d'amorçage | Rien — à envoyer en premier |
| P-01 | Initialisation Laravel | P-00 |
| P-02 | Migrations MySQL | P-01 |
| P-03 | Modèles Eloquent | P-02 |
| P-04 | Authentification JWT et Sécurité | P-03 |
| P-05 | Raw Store et Connector Layer | P-04 |
| P-06 | GEOFACT Core | P-05 |
| P-07 | Rules Engine | P-06 |
| P-08 | KPI Engine RT et DF | P-07 |
| P-09 | Insight Engine | P-08 |
| P-10 | Delivery Engine | P-09 |
| P-11 | Controllers API et Routes | P-10 |
| P-12 | Tests et Validation finale | P-11 |

---

## PROMPT 00 — Instruction d'amorçage

```
Tu vas implémenter IKOMA GEOFACT — système d'intelligence flotte
multi-tenant — en PHP / Laravel 11 / MySQL / cPanel.

Tu disposes de quatre documents de référence :
1. docs/01_architecture_conceptuelle_v3.md — 13 contrats C-01 à C-13
2. docs/02_dictionnaire_canonique_v1.md    — 16 tables DC-01 à DC-16 MySQL
3. docs/03_arborescence_technique.md       — structure Laravel
4. docs/04_prompts_claude_code.md          — ce fichier

RÈGLES ABSOLUES pour toute cette session :
- Tu suis les prompts dans l'ordre séquentiel strict
- Tu ne passes jamais au prompt suivant sans ma validation explicite
- Tu ne crées jamais de fichier hors de l'arborescence définie
- Tu ne bypasses jamais un contrat même si tu penses connaître
  une solution plus simple
- Tout identifiant est UUID v4 — jamais auto-increment
- raw_store, audit_logs, telemetry_events, kpi_records :
  INSERT uniquement — aucun UPDATE jamais
- Le Core ne connaît jamais les fournisseurs GPS
- Le Connector ne connaît jamais le Rules Engine
- Le tenant filter est injecté par TenantInjectorMiddleware
  jamais par les Controllers
- Toute erreur est loggée — aucune erreur silencieuse

Confirme que tu as bien compris ces règles avant de recevoir le Prompt 01.
```

---

## PROMPT 01 — Initialisation Laravel

```
Tu vas initialiser le projet Laravel pour IKOMA GEOFACT.

CONTEXTE :
- Stack : PHP 8.2+ / Laravel 11 / MySQL 8.0
- Projet : système d'intelligence flotte multi-tenant
- Toute décision technique doit respecter les contrats GEOFACT

INSTRUCTIONS :
1. Crée un projet Laravel 11 fresh nommé "geofact" :
   composer create-project laravel/laravel geofact

2. Configure le fichier .env avec :
   - DB_CONNECTION=mysql
   - DB_CHARSET=utf8mb4
   - DB_COLLATION=utf8mb4_unicode_ci
   - APP_TIMEZONE=Africa/Abidjan
   - QUEUE_CONNECTION=database

3. Installe ces dépendances uniquement :
   - tymon/jwt-auth (authentification JWT)
   - ramsey/uuid (génération UUID v4)
   - guzzlehttp/guzzle (appels HTTP Connector)

4. Configure config/geofact.php avec ces valeurs :
   - tolerance_window_hours : 4
   - raw_store_retention_days : 365
   - audit_log_retention_days : 730
   - max_transfer_delay_days : 90
   - device_disconnect_threshold_hours : 24

5. Crée la structure de dossiers exacte sous app/ :
   Core/, Connector/, Rules/, Kpi/, Insight/, Delivery/,
   RawStore/, Auth/, Events/, Listeners/, Jobs/, Exceptions/

6. Dans chaque dossier, crée un fichier .gitkeep

RÈGLES ABSOLUES :
- Jamais d'auto-increment comme identifiant — UUID v4 partout
- Ne crée aucun modèle ni migration à cette étape
- Confirme la structure créée avec un tree complet
```

---

## PROMPT 02 — Migrations MySQL

```
Tu vas créer les 16 migrations MySQL pour IKOMA GEOFACT.

CONTEXTE :
- Les migrations doivent respecter exactement le Dictionnaire Canonique
  GEOFACT (DC-01 à DC-16) défini dans docs/02_dictionnaire_canonique_v1.md
- L'ordre des migrations respecte les contraintes de clés étrangères
- ENGINE=InnoDB, CHARSET=utf8mb4, COLLATE=utf8mb4_unicode_ci sur toutes les tables

ORDRE OBLIGATOIRE des migrations :
1. organizations
2. fleets (FK → organizations)
3. vehicles (FK → fleets, organizations)
4. drivers (FK → organizations)
5. devices (FK → vehicles, organizations)
6. connectors (FK → organizations)
7. trips (FK → vehicles, drivers, organizations, fleets)
8. geozones (FK → organizations)
9. alerts (FK → organizations, vehicles, trips)
10. telemetry_events (FK → devices, organizations)
11. raw_store (sans FK)
12. kpi_records (sans FK — scope_id est polymorphique)
13. insights (sans FK — scope_id est polymorphique)
14. users (FK → organizations)
15. audit_logs (sans FK — actor_id peut être system)
16. vehicle_transfers (FK → vehicles)

RÈGLES ABSOLUES pour chaque migration :
- id : CHAR(36) NOT NULL — jamais bigIncrements
- Tous les ENUM doivent lister exactement les valeurs du Dictionnaire Canonique
- Les index définis dans le DC doivent tous être présents
- ON DELETE RESTRICT sur toutes les FK métier
- ON DELETE SET NULL uniquement si explicitement indiqué dans le DC

TABLES IMMUABLES — pas de colonne updated_at :
- raw_store
- audit_logs
- telemetry_events
- kpi_records

Exécute php artisan migrate après création.
Confirme avec la liste des tables créées.
```

---

## PROMPT 03 — Modèles Eloquent

```
Tu vas créer les 16 modèles Eloquent pour IKOMA GEOFACT.

CONTEXTE :
- Un modèle par table DC
- Les modèles appliquent les règles de validation métier du Dictionnaire Canonique
- Aucune logique métier dans les modèles — uniquement définitions de structure

INSTRUCTIONS pour chaque modèle :
1. Configuration UUID obligatoire sur tous :
   protected $primaryKey = 'id';
   public $incrementing  = false;
   protected $keyType    = 'string';

2. $fillable doit lister exactement les champs du DC correspondant
   — jamais $guarded = []

3. Relations Eloquent obligatoires :
   - Organization : hasMany(Fleet), hasMany(Vehicle), hasMany(User), hasMany(Connector)
   - Fleet : belongsTo(Organization), hasMany(Vehicle)
   - Vehicle : belongsTo(Fleet), belongsTo(Organization), hasOne(Device), hasMany(Trip), hasMany(Alert)
   - Driver : belongsTo(Organization), hasMany(Trip)
   - Device : belongsTo(Vehicle)
   - Trip : belongsTo(Vehicle), belongsTo(Driver), hasMany(Alert), hasMany(TelemetryEvent)
   - Alert : belongsTo(Vehicle), belongsTo(Trip)
   - TelemetryEvent : belongsTo(Device), belongsTo(Trip)

4. Boot method sur les modèles immuables
   (RawStore, AuditLog, TelemetryEvent, KpiRecord) :
   - Bloquer tout UPDATE via updating() event
   - Bloquer tout DELETE via deleting() event
   - Lancer une ContractViolationException si tentative

5. Sur le modèle Vehicle :
   - Scope local activeTrip() qui retourne le Trip actif ou en pause
   - Mutator sur organization_id : calculé depuis fleet_id automatiquement

6. Sur le modèle Alert :
   - Bloquer la suppression physique dans le boot()
   - Valider que resolution_note est présent si status passe à 'resolved'

7. Sur le modèle GeoZone :
   - Boot method : si geometry est modifié sur une zone active,
     incrémenter version automatiquement

RÈGLE ABSOLUE :
- Jamais de logique métier dans les modèles
- Jamais d'appel à un autre service depuis un modèle
```

---

## PROMPT 04 — Authentification JWT et Sécurité

```
Tu vas implémenter la couche d'authentification GEOFACT.

CONTEXTE :
- Modèle : JWT uniquement (C-12)
- 6 rôles officiels fermés
- Isolation tenant par organization_id via Middleware
- Politique de tolérance réseau : 4h par défaut (contexte Afrique de l'Ouest)

INSTRUCTIONS :
1. Crée app/Auth/JwtService.php :
   - generateToken(User $user) : génère JWT avec payload :
     sub, actor_type, organization_id, fleet_ids, role, scope_type,
     issued_at, expires_at, token_version
   - validateToken(string $token) : valide signature + expiration + token_version
   - refreshToken(string $token) : renouvelle si dans tolerance_window (config)

2. Crée app/Auth/TokenVersionGuard.php :
   - Vérifie token_version du JWT == token_version en base
   - Si inférieur : rejette avec 401 + log audit

3. Crée app/Auth/ToleranceWindowHandler.php :
   - Si token expiré mais dans la fenêtre de 4h : renouvelle silencieusement
   - Si token révoqué : rejet immédiat sans tolérance
   - Log chaque renouvellement silencieux

4. Crée app/Auth/ScopeGuard.php :
   - checkOrganizationScope(User $user, string $organizationId)
   - checkFleetScope(User $user, string $fleetId)
   - Lève TenantViolationException si hors scope
   - INSERT audit_logs chaque violation avec result='forbidden'

5. Crée app/Auth/RolePermissionMatrix.php :
   Matrice exacte de C-12.5 :
   - canPerform(string $role, string $action) : bool
   - Actions : create_organization, create_fleet, register_connector,
     configure_ccm, adjust_thresholds, create_rmc, initiate_transfer,
     validate_transfer, physical_delete

6. Crée les 5 Middleware dans app/Http/Middleware/ :
   - JwtAuthMiddleware : valide JWT sur chaque requête
   - TenantInjectorMiddleware : injecte organization_id dans le contexte
     de la requête — obligatoire sur toutes les routes API
   - ScopeEnforcerMiddleware : vérifie scope après injection
   - ConnectorAuthMiddleware : valide token Connector (token_hash + token_version)
   - AuditMiddleware : loggue toute action en fin de requête

RÈGLE ABSOLUE :
- Le filtre tenant est injecté par TenantInjectorMiddleware
  jamais par les Controllers
- Aucune route API sans JwtAuthMiddleware
- Aucune route API sans TenantInjectorMiddleware
```

---

## PROMPT 05 — Raw Store et Connector Layer

```
Tu vas implémenter le Raw Store et le Connector Layer.

CONTEXTE :
- Le Raw Store est immuable et append-only (C-01)
- Le Connector Layer est la seule couche autorisée à communiquer avec les fournisseurs GPS
- Le Core ne connaît jamais les formats propriétaires

INSTRUCTIONS :
1. Crée app/RawStore/RawStoreWriter.php :
   - write(string $connectorId, string $payload, string $format, string $flag) : string
   - INSERT uniquement — lève ContractViolationException si UPDATE tenté
   - Retourne l'id du raw_store créé (raw_ref)

2. Crée app/RawStore/RawStoreReplayer.php :
   - replay(string $rawStoreId) : retraite un événement rejeté
   - Génère connector.replay.started au début
   - Génère connector.replay.completed ou connector.replay.failed à la fin

3. Crée app/Connector/Auth/ConnectorAuthenticator.php :
   - authenticate(Request $request) : Connector
   - Vérifie : connector_id, token valide (bcrypt), token_version, status=active
   - Si invalide : rien n'est écrit, retourne 401 immédiatement

4. Crée app/Connector/Deduplication/TransportDeduplicator.php :
   - isDuplicate(string $connectorId, string $payload,
     string $deviceId, string $timestamp) : bool
   - Hash MD5 du payload+deviceId+timestamp — fenêtre 5 minutes (cache Laravel)
   - Si doublon : loggue duplicate_transport_event, retourne true

5. Crée app/Connector/Normalizer/FieldNormalizer.php :
   - normalize(array $rawPayload, string $providerId) : array
   - Applique NormalizationMap selon providerId

6. Crée app/Connector/Normalizer/NormalizationMap.php :
   Mapping par providerId :
   wialon  : ['speed'→'speed_kmh', 'lat'→'latitude', 'lon'→'longitude', 'ts'→'timestamp']
   traccar : ['speed'→'speed_kmh', 'deviceTime'→'timestamp']
   teltonika: ['spd'→'speed_kmh', 'lat'→'latitude', 'lng'→'longitude', 'imei'→'device_id']

7. Crée app/Connector/ConnectorPipeline.php :
   Pipeline strict dans cet ordre UNIQUEMENT :
   Étape 1 : ConnectorAuthenticator::authenticate()
   Étape 2 : RawStoreWriter::write(payload, flag='OK')
   Étape 3 : TransportDeduplicator::isDuplicate()
              → si true : mettre à jour flag Raw Store → DUPLICATE_TRANSPORT, stop
   Étape 4 : FieldNormalizer::normalize()
   Étape 5 : CompletenessEvaluator::evaluate()
              → si REJECTED : mettre à jour flag Raw Store → REJECTED, stop
   Étape 6 : CanonicalEventFactory::create()
   Étape 7 : CanonicalDeduplicator::isDuplicate() → si true : stop
   Étape 8 : TenantResolver::resolve() si vehicle_id absent
   Étape 9 : INSERT TelemetryEvent — INSERT uniquement
   Étape 10: event(new CanonicalEventReceived($event))

RÈGLE ABSOLUE :
- ConnectorPipeline ne connaît pas le Rules Engine
- Il dispatch un Event Laravel — jamais d'appel direct
- Toute erreur est catchée et loggée — jamais silencieuse
```

---

## PROMPT 06 — GEOFACT Core

```
Tu vas implémenter le GEOFACT Core — couche canonique pure.

CONTEXTE :
- Le Core ne reçoit que des CanonicalEvents (C-01)
- Il ne connaît aucun protocole de transport
- Il est le seul arbitre de la complétude des événements

INSTRUCTIONS :
1. Crée app/Core/Canonical/CanonicalEvent.php :
   Classe readonly avec propriétés :
   - eventId (UUID v4)
   - eventType (validé contre taxonomie C-09)
   - connectorId, organizationId, deviceId, vehicleId (nullable)
   - timestamp (Carbon — précision milliseconde)
   - receivedAt (Carbon)
   - payload (array)
   - missingFields (array)
   - completeness (COMPLETE|INCOMPLETE|REJECTED)
   - rawRef (id Raw Store)
   Aucun setter — utiliser Object::freeze équivalent PHP

2. Crée app/Core/Canonical/CanonicalEventFactory.php :
   - create(array $normalizedPayload, Connector $connector, string $rawRef) : CanonicalEvent
   - Génère eventId UUID v4
   - Valide eventType contre la liste fermée C-09
   - Lève CanonicalValidationException si eventType invalide

3. Crée app/Core/Canonical/CompletenessEvaluator.php :
   - evaluate(array $payload, string $organizationId) : string
   - Vérifie les 3 CCS : timestamp, device_id, event_type → REJECTED si manquant
   - Charge les CCM du client depuis sa configuration → INCOMPLETE si CCM manquant
   - Sinon → COMPLETE

4. Crée app/Core/Deduplication/CanonicalDeduplicator.php :
   - isDuplicate(string $eventId) : bool
   - Vérifie en DB si event_id existe déjà dans telemetry_events
   - Si doublon : loggue DUPLICATE_CANONICAL, retourne true
   - Ne lève jamais d'exception — retourne boolean

5. Crée app/Core/TenantResolver.php :
   - resolve(string $deviceId) : array
   - Retourne [organization_id, fleet_id, vehicle_id] depuis le device_id

6. Crée app/Core/Contracts/ — les 5 interfaces :
   ConnectorInterface : produceCanonicalEvent(array $raw) : CanonicalEvent
   RulesEngineInterface : evaluate(CanonicalEvent $event) : array
   KpiEngineInterface : compute(CanonicalEvent $event) : void
   InsightEngineInterface : generate(string $scopeType, string $scopeId) : ?Insight
   DeliveryEngineInterface : deliver(Alert $alert) : void

RÈGLE ABSOLUE :
- app/Core/ n'a AUCUN use/import depuis Connector/, Rules/, Kpi/, Delivery/ ou Insight/
- Seules les Interfaces de Core/Contracts/ sont connues du reste du système
```

---

## PROMPT 07 — Rules Engine

```
Tu vas implémenter le Rules Engine de GEOFACT.

CONTEXTE :
- RS évaluées avant RMC — toujours (C-04)
- RS non désactivables par aucun client
- En cas de conflit RS vs RMC : RS prime toujours
- Le Rules Engine dispatch des Events — jamais d'appel direct à Delivery

INSTRUCTIONS :
1. Crée app/Rules/Contracts/RuleInterface.php :
   - evaluate(CanonicalEvent $event) : ?Alert
   - applies(CanonicalEvent $event) : bool
   - getRuleId() : string
   - getRuleType() : string (RS|RMC)

2. Crée les 5 règles système dans app/Rules/SystemRules/ :
   RS01_OverspeedRule.php :
   - applies() : payload contient speed_kmh
   - evaluate() : si speed_kmh > seuil configuré
     → Alert event_type='alert.overspeed.detected'
     → sévérité : <20%→MEDIUM, <40%→HIGH, ≥40%→CRITICAL

   RS02_SuspiciousStopRule.php :
   - applies() : vehicle.stopped depuis > seuil (défaut 4h)
   - evaluate() : si zone != authorized → Alert 'alert.stop.suspicious'

   RS03_HarshBrakingRule.php :
   - applies() : payload.harsh_braking == true
   - evaluate() → Alert 'alert.harsh.braking', severity HIGH

   RS04_GeozoneEntryRule.php :
   - applies() : latitude + longitude présents
   - evaluate() : vérifie position dans geozones actives
     → 'geozone.violated' si zone restricted, sinon 'geozone.entered'

   RS05_MaintenanceThresholdRule.php :
   - applies() : vehicle avec kilométrage tracké
   - evaluate() : si distance_km >= seuil → Alert 'maintenance.threshold.reached'

3. Crée app/Rules/Engine/SystemRulesEvaluator.php :
   - evaluate(CanonicalEvent $event) : array d'Alerts
   - Évalue RS01→RS05 dans l'ordre — toutes les RS applicables,
     pas de court-circuit entre RS

4. Crée app/Rules/ClientRules/RmcRuleLoader.php :
   - load(string $organizationId) : array de règles RMC
   - Charge depuis la DB les RMC actives du client

5. Crée app/Rules/Engine/RulesEngine.php :
   Ordre strict :
   Étape 1 : SystemRulesEvaluator::evaluate()
   Étape 2 : ClientRulesEvaluator::evaluate()
   Étape 3 : Merge — si conflit RS vs RMC : RS prime, conflit loggué
   Étape 4 : INSERT chaque Alert en DB
   Étape 5 : event(new AlertTriggered($alert)) pour chaque Alert
   
   Déclenché par : @OnEvent CanonicalEventReceived

RÈGLE ABSOLUE :
- RulesEngine ne connaît pas Delivery Engine
- RulesEngine ne modifie jamais un CanonicalEvent
- Toute Alert produite est persistée avant le dispatch
- system.rule.evaluation.failed si exception non gérée
```

---

## PROMPT 08 — KPI Engine RT et DF

```
Tu vas implémenter le KPI Engine — pipelines RT et DF séparés.

CONTEXTE :
- Pipeline RT déclenché par CanonicalEventReceived
- Pipeline DF déclenché par le Scheduler Laravel
- Jamais dans le même pipeline (C-05)
- kpi_records : INSERT uniquement — jamais UPDATE

INSTRUCTIONS :
1. Crée les 5 calculateurs RT dans app/Kpi/RealTime/Calculators/ :
   CurrentSpeedCalculator.php :
   - compute(CanonicalEvent $event) : ?array
   - Si speed_kmh dans payload → KpiRecord mode='RT' scope_type='vehicle'

   VehicleStatusCalculator.php :
   - compute() : statut déduit depuis event_type
     trip.started→'moving', vehicle.stopped→'stopped', vehicle.idle→'idle'

   OverspeedCountCalculator.php :
   - compute() : si event_type='alert.overspeed.detected'
     → incrémente compteur journalier du vehicle

   ActiveTripDurationCalculator.php :
   - compute() : durée = now - Trip.started_at si trip actif

   CurrentZoneCalculator.php :
   - compute() : zone courante depuis dernière position

2. Crée app/Kpi/RealTime/RtKpiEngine.php :
   - computeAll(CanonicalEvent $event) : void
   - Appelle chaque calculateur RT
   - Pour chaque résultat : INSERT kpi_records mode='RT'
   - Ne bloque jamais — try/catch global par calculateur

3. Crée les 5 calculateurs DF dans app/Kpi/Deferred/Calculators/ :
   DriverScoreCalculator.php :
   - compute(string $driverId, Carbon $from, Carbon $to) : array
   - Formule : score = 100 - (overspeed_count*3) - (harsh_braking*2)
                    - (night_activity*1) - (suspicious_stop*4)
   - MIN(score, 0)

   FleetUtilizationCalculator.php :
   - compute(string $fleetId, Carbon $from, Carbon $to) : array
   - (heures_actives / heures_periode) * 100

   WeeklyPerformanceCalculator.php
   BehavioralAnalysisCalculator.php
   MonthlyReportCalculator.php

4. Crée app/Kpi/Deferred/DfKpiVersioner.php :
   - Si KpiRecord existant pour même scope + kpi_type + période : version + 1
   - INSERT toujours — jamais UPDATE

5. Crée app/Kpi/Aggregator/ScopeAggregator.php :
   - aggregateToFleet(string $fleetId, string $kpiType, Carbon $period)
   - aggregateToOrganization(string $orgId, string $kpiType, Carbon $period)
   - Agrège Vehicle → Fleet → Organization dans cet ordre

6. Configure le Scheduler dans routes/console.php :
   - driver_score : quotidien à 01h00
   - fleet_utilization : quotidien à 01h30
   - weekly_performance : chaque lundi à 02h00
   - monthly_report : 1er du mois à 03h00

RÈGLE ABSOLUE :
- RtKpiEngine n'utilise que l'événement courant (pas de requête DB complexe)
- DfKpiEngine lit uniquement telemetry_events archivés (jamais données live)
- Jamais d'UPDATE sur kpi_records — INSERT toujours
```

---

## PROMPT 09 — Insight Engine

```
Tu vas implémenter l'Insight Engine de GEOFACT.

CONTEXTE :
- L'IA interprète — elle ne détecte pas (C-06)
- Le Core ne dépend jamais de la disponibilité de l'IA
- Tout insight sans scope valide est rejeté
- Les insights sont versionnés et immuables

INSTRUCTIONS :
1. Crée app/Insight/PacketBuilder.php :
   - build(string $scopeType, string $scopeId, string $organizationId,
     Carbon $from, Carbon $to) : array
   - Charge les KPIs depuis kpi_records
   - Charge les règles déclenchées depuis alerts
   - Retourne paquet structuré — jamais de données brutes à l'IA

2. Crée app/Insight/InsightValidator.php :
   - validate(array $insightData) : bool
   - scope_type dans la liste C-06, scope_id non vide, insight_text non vide
   - Lève CanonicalValidationException si invalide

3. Crée app/Insight/InsightEngine.php :
   - generate(...) : ?Insight
   Étape 1 : PacketBuilder::build()
   Étape 2 : Appel Anthropic API (claude-sonnet-4-20250514)
     System prompt : "Tu es l'Insight Engine de GEOFACT.
     Tu reçois des données structurées sur un {scope_type}.
     Tu ne détectes pas — tu interprètes.
     Réponds UNIQUEMENT en JSON :
     insight_text (string), confidence_level (high|medium|low),
     insight_type (anomaly|trend|performance|alert|summary).
     Pas de markdown. JSON pur."
   Étape 3 : Parse JSON réponse
   Étape 4 : InsightValidator::validate()
   Étape 5 : InsightVersioner::save()
   Étape 6 : Retourne Insight ou null si IA indisponible

4. Crée app/Insight/InsightVersioner.php :
   - Si insight existant pour même scope + période : version + 1
   - INSERT toujours — jamais UPDATE

5. Crée app/Insight/Fallback/InsightFallbackHandler.php :
   - handle(...) : null
   - Loggue system.insight.generation.failed
   - Retourne null — jamais d'exception vers le caller

RÈGLE ABSOLUE :
- InsightEngine retourne null si IA indisponible — jamais d'exception vers le Core
- PacketBuilder ne passe jamais raw_store à l'IA
- Un Insight sans scope_type valide n'est jamais persisté
```

---

## PROMPT 10 — Delivery Engine

```
Tu vas implémenter le Delivery Engine de GEOFACT.

CONTEXTE :
- SP évaluées avant CDP — toujours (C-07)
- Les SP ne peuvent pas être désactivées
- Aucune livraison n'est silencieuse
- Les destinataires WhatsApp sont passifs — pas d'auth

INSTRUCTIONS :
1. Crée app/Delivery/Policies/SystemPoliciesEvaluator.php :
   - evaluate(Alert $alert) : array de DeliveryTask
   - SP officielles non désactivables :
     SP-01 : severity=CRITICAL → Email+WhatsApp → Admin+Contact sécurité
     SP-02 : system.* events → Email → Admin GEOFACT
     SP-03 : connector.* events → Email → Admin+Intégrateur
     SP-04 : system.kpi.computation.failed → Email → Admin

2. Crée app/Delivery/Policies/ClientPoliciesEvaluator.php :
   - evaluate(Alert $alert, string $organizationId) : array de DeliveryTask
   - Charge les CDP actives depuis DB
   - Filtre par event_type, severity, horaires

3. Crée les canaux dans app/Delivery/Channels/ :
   WhatsAppChannel.php :
   - send(Contact $contact, Alert $alert) : bool
   - API WhatsApp Business — timeout 10 secondes
   - Retourne bool — jamais d'exception

   EmailChannel.php :
   - send(Contact $contact, Alert $alert) : bool
   - Mailer Laravel — template selon severity
   - Retourne bool

   ApiChannel.php :
   - send(string $webhookUrl, Alert $alert) : bool
   - POST JSON vers webhook client — timeout 15 secondes

4. Crée app/Delivery/DeliveryEngine.php :
   Ordre strict :
   Étape 1 : SystemPoliciesEvaluator::evaluate()
   Étape 2 : ClientPoliciesEvaluator::evaluate()
   Étape 3 : Merge des DeliveryTasks
   Étape 4 : Pour chaque DeliveryTask :
     → Sélectionne le Channel approprié
     → Tente la livraison
     → DeliveryLogger::log(résultat)
     → Si échec : retry x3 avec backoff exponentiel

5. Crée app/Jobs/EscalateUnacknowledgedAlerts.php :
   - Vérifie toutes les Alerts delivered depuis > délai CDP
   - Change status → escalated
   - Crée nouveau DeliveryTask vers superviseur

RÈGLE ABSOLUE :
- SP évaluées en premier — toujours, jamais contournées
- Aucun channel ne lève d'exception vers DeliveryEngine
- Tout échec est loggué — jamais silencieux
- system.delivery.failed si échec total après retry
```

---

## PROMPT 11 — Controllers API et Routes

```
Tu vas implémenter les Controllers API et les routes de GEOFACT.

CONTEXTE :
- API versionnée V1
- Tous les endpoints protégés par JWT + TenantInjector
- Aucune logique métier dans les Controllers
- Réponse standard : {success, data, message, errors}

INSTRUCTIONS :
1. Dans routes/api.php, structure les routes :
   Groupe /api/v1 avec middleware [jwt.auth, tenant.inject] :
   
   POST   /auth/login
   POST   /auth/refresh
   GET    /organizations
   POST   /organizations
   GET    /organizations/{id}
   GET    /fleets
   POST   /fleets
   PATCH  /fleets/{id}/status
   GET    /vehicles
   POST   /vehicles
   GET    /vehicles/{id}
   PATCH  /vehicles/{id}/status
   POST   /vehicles/{id}/transfer
   GET    /vehicles/{id}/active-trip
   GET    /drivers
   POST   /drivers
   GET    /drivers/{id}
   GET    /trips
   GET    /trips/{id}
   GET    /alerts
   GET    /alerts/{id}
   PATCH  /alerts/{id}/acknowledge
   PATCH  /alerts/{id}/resolve
   GET    /geozones
   POST   /geozones
   GET    /kpis/{scopeType}/{scopeId}
   GET    /kpis/{scopeType}/{scopeId}/history
   GET    /insights/{scopeType}/{scopeId}
   POST   /insights/{scopeType}/{scopeId}/generate

   Groupe /api/webhook avec middleware [connector.auth] :
   POST   /connector/ingest         ← push fournisseur
   GET    /connector/pull/{id}      ← pull scheduler

2. Dans chaque Controller :
   - Injecte le scope depuis le Middleware (request()->tenant)
   - Valide avec la Request correspondante
   - Délègue à un Service — jamais de logique directe
   - Retourne JsonResponse avec structure standard

3. Crée AlertController.php :
   - acknowledge() : vérifie scope, change status → acknowledged, log audit
   - resolve() : vérifie resolution_note non vide (obligatoire), change status → resolved

4. Crée les FormRequests :
   - StoreVehicleRequest : valide plate, fleet_id, name
   - StoreTripRequest : valide vehicle_id, driver_id
   - ConnectorIngestRequest : valide event_type contre taxonomie C-09
   - VehicleTransferRequest : valide historical_data_policy, effective_date

RÈGLE ABSOLUE :
- Aucun Controller ne touche directement la DB
- Aucun Controller ne connaît le Rules Engine ni le Delivery Engine
- Le scope organizationId vient toujours de request()->tenant
  jamais du body ou des params
- Les réponses d'erreur ne révèlent jamais les détails d'infrastructure
```

---

## PROMPT 12 — Tests et Validation finale

```
Tu vas écrire les tests critiques de GEOFACT et valider l'ensemble du système.

CONTEXTE :
- Les tests valident les contrats — pas l'implémentation
- Chaque test porte le numéro du contrat qu'il valide
- Un test qui passe = un contrat respecté

INSTRUCTIONS :
1. Tests unitaires dans tests/Unit/ :

   Core/CanonicalEventTest.php (valide C-01) :
   - test_canonical_event_is_immutable()
   - test_rejects_unknown_event_type()
   - test_requires_three_ccs_fields()

   Core/CompletenessEvaluatorTest.php (valide C-02) :
   - test_returns_rejected_if_timestamp_missing()
   - test_returns_rejected_if_device_id_missing()
   - test_returns_incomplete_if_ccm_missing()
   - test_returns_complete_if_all_fields_present()

   Rules/RS01_OverspeedRuleTest.php (valide C-04) :
   - test_triggers_alert_when_speed_exceeds_limit()
   - test_no_alert_below_limit()
   - test_severity_scales_with_overspeed_percentage()
   - test_system_rule_cannot_be_disabled()

   Kpi/DriverScoreCalculatorTest.php (valide C-05) :
   - test_score_starts_at_100()
   - test_overspeed_reduces_score()
   - test_new_version_created_on_recalculation()

2. Tests Feature dans tests/Feature/ :

   ConnectorIngestionTest.php (valide C-10) :
   - test_rejects_connector_without_valid_token()
   - test_writes_to_raw_store_before_processing()
   - test_duplicate_transport_never_reaches_raw_store()
   - test_rejected_event_stays_in_raw_store()
   - test_complete_event_reaches_core()

   TripLifecycleTest.php (valide C-11) :
   - test_only_one_active_trip_per_vehicle()
   - test_completed_trip_is_immutable()
   - test_anomalous_trip_requires_note()
   - test_cancelled_trip_excluded_from_kpis()

   MultiTenantIsolationTest.php (valide C-08, C-12) :
   - test_org_a_cannot_read_org_b_vehicles()
   - test_fleet_admin_cannot_access_other_fleets()
   - test_tenant_filter_injected_automatically()
   - test_forbidden_access_logged_as_security_event()

   AlertEscalationTest.php (valide C-07) :
   - test_system_policy_cannot_be_disabled()
   - test_critical_alert_triggers_sp01()
   - test_unacknowledged_alert_escalates()
   - test_alert_physical_deletion_is_forbidden()
   - test_resolved_alert_requires_resolution_note()

3. Lance les tests :
   php artisan test --coverage

   Objectif minimum : 80% de couverture sur
   app/Core/, app/Rules/, app/Connector/

4. Génère le rapport de conformité contractuelle :
   Pour chaque contrat C-01 à C-13 :
   - Composant(s) qui l'implémentent
   - Test(s) qui le valident
   - Statut : VALIDÉ / EN ATTENTE / MANQUANT

RÈGLE ABSOLUE :
- Aucun test ne mocke le tenant filter
- Aucun test ne bypasse l'authentification
- Les tests d'isolation multi-tenant sont obligatoires avant tout déploiement
- Un test qui échoue = un contrat violé
  → corriger le code, jamais le test
```
