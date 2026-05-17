# Architecture Conceptuelle GEOFACT v3
## 13 Contrats Fondamentaux

---

## Récapitulatif des 13 contrats

| Code | Objet | Version |
|------|-------|---------|
| C-01 | Isolation du Core & Gestion des Anomalies | v2 |
| C-02 | Taxonomie des Champs Critiques CCS / CCM | v2 |
| C-03 | Gouvernance de la Configuration CCM | v2 |
| C-04 | Architecture du Moteur de Règles RS / RMC | v2 |
| C-05 | Architecture du KPI Engine RT / DF | v2 |
| C-06 | Architecture de l'Insight Engine | v2 |
| C-07 | Architecture du Delivery Engine | v2 |
| C-08 | Modèle de Tenant & Hiérarchie Opérationnelle | v2 |
| C-09 | Taxonomie des Événements Canoniques | v3 |
| C-10 | Contrat du Connector | v3 |
| C-11 | Cycle de Vie des Objets | v3 |
| C-12 | Modèle de Sécurité et d'Authentification | v3 |
| C-13 | Politique de Versioning et d'Évolution des Contrats | v3 |

---

## C-01 — Isolation du Core & Gestion des Anomalies

### C-01.1 — Principe d'isolation absolue
Le Core GEOFACT ne connaît aucun protocole de transport.
Il ne reçoit qu'un seul type d'objet en entrée : un **Canonical Event** — produit exclusivement par le Connector Layer.
Tout ce qui précède le Canonical Event est **hors Core par définition**.

### C-01.2 — Niveaux de traitement d'un événement entrant

| Situation | Action Connector | Action Core |
|-----------|-----------------|-------------|
| Données complètes et valides | Produit un Canonical Event | Traitement normal |
| Champ non-critique manquant | Canonical Event + flag INCOMPLETE | Traitement avec réserve |
| Champ critique manquant | Stockage Raw Store + flag REJECTED | Non transmis au Core |
| Données corrompues | Stockage Raw Store + flag CORRUPTED | Non transmis au Core |

> **Règle absolue : aucune donnée n'est jamais ignorée silencieusement.**

### C-01.3 — Le Raw Store
Toute donnée reçue — valide ou non — est d'abord écrite dans le Raw Store avant tout traitement.
Le Raw Store est **immuable et append-only**.
Il permet :
- **Audit** : traçabilité complète de ce qui a été reçu
- **Replay** : retraitement d'événements rejetés après correction du connecteur

### C-01.4 — Champs critiques vs non-critiques
La distinction critique / non-critique **n'appartient pas au connecteur**.
Elle est définie dans le **Dictionnaire Canonique GEOFACT** — source de vérité unique.

---

## C-02 — Taxonomie des Champs Critiques CCS / CCM

### C-02.1 — Deux niveaux de criticité
- **Niveau 1 — Champs Critiques Système (CCS)** : définis par GEOFACT Core, fixes, non désactivables
- **Niveau 2 — Champs Critiques Métier (CCM)** : définis par le client dans sa configuration de flotte

### C-02.2 — Champs Critiques Système (CCS)

| Champ | Type | Rôle |
|-------|------|------|
| timestamp | ISO 8601 | Horodatage universel de l'événement |
| device_id | string unique | Identifiant de la source physique |
| event_type | enum canonique | Type d'événement (taxonomie GEOFACT) |

> Absence d'un seul CCS → événement rejeté du Core, stocké en Raw Store.

### C-02.3 — Champs Critiques Métier (CCM)

| Secteur | Champ CCM | Type |
|---------|-----------|------|
| Transport frigorifique | temperature_celsius | float |
| Industrie minière | fuel_level_pct | float |
| Transport de fonds | door_status | enum |

### C-02.4 — Responsabilité de la définition

| Qui | Définit quoi | Peut modifier |
|-----|-------------|---------------|
| GEOFACT | CCS | Jamais côté client |
| Client | CCM | Dans sa configuration uniquement |
| Connecteur | Rien | Applique, ne décide pas |

### C-02.5 — Principe de non-contamination
La configuration CCM d'un client n'affecte jamais le comportement du Core pour les autres clients.

---

## C-03 — Gouvernance de la Configuration CCM

### C-03.2 — Phase 1 : Onboarding
**Acteurs autorisés** : Administrateur GEOFACT ou Intégrateur Certifié uniquement.
- Définition des CCM de la flotte
- Association CCM ↔ types d'événements concernés
- Définition des seuils initiaux
- Validation de cohérence avec le moteur d'analyse

> Aucun client n'accède au système avant validation complète de sa configuration par un acteur habilité.

### C-03.3 — Phase 2 : Modifications autonomes

| Type de paramètre | Modifiable par le client | Exemple |
|-------------------|------------------------|---------|
| Seuils de valeurs | Oui, dans une plage définie | Limite vitesse : 60 à 120 km/h |
| Ajout d'un nouveau CCM | Non | Nécessite un intégrateur certifié |
| Suppression d'un CCM | Non | Nécessite un intégrateur certifié |
| Modification du type de données | Non | Jamais en autonomie |

### C-03.4 — Principe de plage autorisée
```yaml
parametre: speed_limit_kmh
valeur_min: 40
valeur_max: 130
valeur_defaut: 90
modifiable_par_client: true
```
Toute tentative de valeur hors plage est rejetée par le système, **jamais silencieusement acceptée**.

### C-03.5 — Traçabilité des modifications
| Champ | Contenu |
|-------|---------|
| config_event_id | Identifiant unique |
| actor_id | Qui a modifié |
| actor_role | Son rôle au moment de l'action |
| parameter | Ce qui a été modifié |
| old_value | Valeur précédente |
| new_value | Valeur appliquée |
| timestamp | Horodatage ISO 8601 |

---

## C-04 — Architecture du Moteur de Règles RS / RMC

### C-04.2 — Règles Système (RS)
Définies par GEOFACT. Identiques pour tous les clients, **non désactivables**.

| Règle | Déclencheur | Événement produit |
|-------|------------|-------------------|
| RS-01 | speed_kmh > speed_limit | overspeed.detected |
| RS-02 | stop_duration > 4h AND zone != authorized | suspicious_stop |
| RS-03 | harsh_braking == true | harsh.braking |
| RS-04 | geozone.entered | geozone.entered |
| RS-05 | maintenance_km >= threshold | maintenance.threshold_reached |

### C-04.3 — Règles Métier Client (RMC)
**Mode A — Paramétrage** : le client ajuste les variables d'une règle système dans sa plage autorisée.
**Mode B — Composition** : le client combine des événements canoniques existants.

> **Option C (création libre) refusée en v1.**
> Une RMC ne peut référencer que des événements canoniques existants dans la taxonomie GEOFACT.

### C-04.5 — Gouvernance des RMC

| Action | Acteur autorisé |
|--------|----------------|
| Créer une RMC | Admin GEOFACT ou Intégrateur Certifié |
| Paramétrer (Mode A) | Client dans sa plage déléguée |
| Composer une RMC (Mode B) | Intégrateur Certifié uniquement |
| Désactiver une RS | Personne |

### C-04.6 — Évaluation des règles
Ordre strict :
1. Évaluation des RS (toujours en premier)
2. Évaluation des RMC du client concerné
3. Production des événements résultants
4. Transmission à l'Analytics Engine

**En cas de conflit RS vs RMC — la RS prime toujours.**

---

## C-05 — Architecture du KPI Engine RT / DF

### C-05.1 — Deux modes de calcul strictement séparés
- **Mode RT** — Real-Time KPIs : calculés à chaque événement canonique entrant
- **Mode DF** — Deferred KPIs : calcul différé par scheduler

Ces deux modes n'utilisent pas le même pipeline. **Ils ne se bloquent jamais mutuellement.**

### C-05.2 — KPIs Real-Time (Mode RT)

| KPI | Déclencheur | Unité |
|-----|------------|-------|
| speed_current | Tout événement de télémétrie | km/h |
| vehicle_status | trip.started / vehicle.stopped | enum |
| overspeed_count | overspeed.detected | entier cumulatif |
| active_trip_duration | trip.started → now | minutes |
| current_zone | geozone.entered / exited | zone_id |

### C-05.3 — KPIs Différés (Mode DF)

| KPI | Périodicité | Fenêtre |
|-----|------------|---------|
| driver_score | Journalier | J-1 00:00 → 23:59 |
| fleet_utilization_rate | Journalier / Hebdo | Période glissante |
| weekly_performance | Hebdomadaire | Lun → Dim |
| behavioral_analysis | Journalier | J-1 |
| monthly_report_kpis | Mensuel | M-1 |

### C-05.4 — Séparation des pipelines
```
Événement canonique entrant
│
├──→ RT Pipeline → KPI RT mis à jour immédiatement
│
└──→ Event Store → DF Pipeline déclenché par scheduler
```

### C-05.6 — Versioning des KPIs calculés
Un KPI DF calculé est **immuable** une fois produit. Si les données sources sont corrigées (replay), un nouveau calcul produit une nouvelle **version** du KPI — il ne remplace pas l'ancien.

---

## C-06 — Architecture de l'Insight Engine

### C-06.1 — Principe fondamental
> **L'Insight Engine ne détecte pas. Elle interprète.**
La détection appartient au moteur de règles (C-04).

### C-06.2 — Les six niveaux de portée
- `event` → un événement isolé
- `trip` → un trajet complet
- `vehicle` → comportement d'un véhicule sur une période
- `driver` → profil de conduite d'un conducteur
- `fleet` → état global d'une flotte
- `organization` → tendance opérationnelle multi-flotte

### C-06.3 — Contrat de sortie d'un Insight
```json
{
  "insight_id": "string unique",
  "scope_type": "event|trip|vehicle|driver|fleet|organization",
  "scope_id": "identifiant de l'objet concerné",
  "client_id": "tenant",
  "language": "fr|en|...",
  "generated_at": "ISO 8601",
  "insight_text": "texte interprétif en langage naturel",
  "confidence_level": "high|medium|low",
  "source_kpis": ["kpi_id_1", "kpi_id_2"],
  "source_events": ["event_id_1", "event_id_2"],
  "insight_type": "anomaly|trend|performance|alert|summary",
  "version": 1
}
```

> **Règle absolue** : un insight sans scope_type et scope_id valides est rejeté avant transmission.

### C-06.5 — Politique de fallback

| Situation | Comportement |
|-----------|-------------|
| IA disponible | Insight produit et attaché à l'objet |
| IA indisponible | Objet livré sans insight — jamais bloqué |
| Insight rejeté (contrat invalide) | Loggué, pas transmis — retry possible |
| confidence_level == low | Insight transmis avec flag visible côté client |

> **Le Core ne dépend jamais de la disponibilité de l'IA.**

---

## C-07 — Architecture du Delivery Engine

### C-07.1 — Deux niveaux de politique
- **Niveau 1 — System Policies (SP)** : contrôlées par GEOFACT
- **Niveau 2 — Client Distribution Policies (CDP)** : configurées par le client

Les SP s'appliquent **avant** les CDP. Elles ne peuvent pas être écrasées.

### C-07.2 — System Policies (SP)

| Code | Déclencheur | Canal | Destinataire |
|------|------------|-------|-------------|
| SP-01 | Alerte sécurité critique | Email + WhatsApp | Admin GEOFACT + Contact sécurité client |
| SP-02 | Erreur système Core | Email | Admin GEOFACT |
| SP-03 | Incident connecteur | Email | Admin GEOFACT + Intégrateur Certifié |
| SP-04 | Échec calcul KPI DF | Email | Admin GEOFACT |

### C-07.6 — Canaux supportés

| Canal | Type | Usage typique |
|-------|------|---------------|
| whatsapp | Push temps réel | Alertes opérationnelles |
| email | Push programmé | Rapports, escalades |
| api | Pull / Push | Intégrations tierces |
| dashboard | Consultation | Supervision en temps réel |
| export | Téléchargement | PDF, CSV, Excel |

### C-07.7 — Modèle de destinataire
Un destinataire est un objet **Contact** rattaché au tenant — jamais une adresse email brute.

---

## C-08 — Modèle de Tenant & Hiérarchie Opérationnelle

### C-08.1 — Hiérarchie canonique
```
Organization
├── Fleet A
│   ├── Vehicle 1
│   ├── Vehicle 2
│   └── Vehicle 3
├── Fleet B
│   ├── Vehicle 4
│   └── Vehicle 5
└── Fleet C
    └── Vehicle 6
```

> **Règle absolue** : un Vehicle appartient toujours à une seule Fleet. Une Fleet appartient toujours à une seule Organization.

### C-08.3 — Portée d'application des règles et KPIs

| Portée | Exemple d'application |
|--------|----------------------|
| organization | Taux d'utilisation global du groupe |
| fleet | Score conducteur de la flotte transport |
| vehicle | Alertes overspeed du SHACMAN-115 |

### C-08.4 — Isolation des données entre organizations

| Règle | Description |
|-------|-------------|
| Isolation absolue | Aucune organization ne voit les données d'une autre |
| Isolation par fleet | Une fleet ne voit pas les données des autres fleets sans permission explicite |
| Requête cross-fleet | Autorisée uniquement au niveau organization par un acteur habilité |

### C-08.5 — Modèle de droits par niveau

| Rôle | Organization | Fleet | Vehicle |
|------|-------------|-------|---------|
| geofact_admin | Lecture totale | Lecture totale | Lecture totale |
| org_admin | Gestion totale | Gestion totale | Gestion totale |
| fleet_admin | Lecture org | Gestion sa fleet | Gestion ses véhicules |
| supervisor | Lecture org | Lecture ses fleets | Lecture ses véhicules |
| driver | — | — | Ses données uniquement |

---

## C-09 — Taxonomie des Événements Canoniques

### C-09.1 — Convention de nommage absolue
Deux formats autorisés, **aucun autre** :
- `domaine.action`
- `domaine.sous-domaine.action`

| Règle | Description |
|-------|-------------|
| Minuscules uniquement | `trip.started` — jamais `Trip.Started` |
| Séparateur point uniquement | Jamais underscore, tiret, slash |
| Action au passé | `started`, `detected`, `updated` — jamais `start`, `detect` |
| Maximum 3 segments | `domaine.sous-domaine.action` est le plafond |
| Noms métier uniquement | Jamais `gps`, `tracking`, `map`, `location` |

### C-09.2 — Les 11 domaines officiels v1

| Domaine | Nature | Rôle |
|---------|--------|------|
| telemetry | Observation brute | Données normalisées issues du Connector |
| trip | Cycle métier | Cycle complet d'un trajet |
| vehicle | État physique | État opérationnel d'un véhicule |
| driver | Activité humaine | Activité et affectation d'un conducteur |
| geozone | Événement géographique | Interactions avec les zones définies |
| alert | Événement qualifié | Résultat d'une règle déclenchée |
| maintenance | Cycle technique | Seuils et obligations de maintenance |
| device | État tracker | Santé du matériel GPS physique |
| connector | Santé ingestion | État des connecteurs de données |
| security | Risque terrain | Fraude, sabotage, activité suspecte |
| system | Infrastructure | Erreurs et états internes GEOFACT |

> **Domaine réservé v2** : `compliance` — conformité transport et temps de conduite.

### C-09.4 — Taxonomie complète des événements officiels v1

**telemetry**
- `telemetry.position.updated`
- `telemetry.speed.updated`
- `telemetry.fuel.updated`
- `telemetry.temperature.updated`
- `telemetry.battery.updated`
- `telemetry.ignition.on`
- `telemetry.ignition.off`

**trip**
- `trip.started`
- `trip.ended`
- `trip.paused`
- `trip.resumed`
- `trip.cancelled`

**vehicle**
- `vehicle.moving`
- `vehicle.stopped`
- `vehicle.idle`
- `vehicle.assigned`
- `vehicle.unassigned`

**driver**
- `driver.trip.started`
- `driver.trip.ended`
- `driver.score.computed`
- `driver.behavior.flagged`

**geozone**
- `geozone.entered`
- `geozone.exited`
- `geozone.violated`
- `geozone.overdue`

**alert**
- `alert.overspeed.detected`
- `alert.harsh.braking`
- `alert.harsh.acceleration`
- `alert.stop.suspicious`
- `alert.stop.unauthorized`
- `alert.night.activity`
- `alert.acknowledged`
- `alert.resolved`

**maintenance**
- `maintenance.threshold.reached`
- `maintenance.overdue`
- `maintenance.scheduled`
- `maintenance.completed`

**device**
- `device.connected`
- `device.disconnected`
- `device.error`
- `device.tampered`
- `device.low.battery`

**connector**
- `connector.connected`
- `connector.disconnected`
- `connector.sync.failed`
- `connector.sync.recovered`
- `connector.replay.started`
- `connector.replay.completed`
- `connector.replay.failed`

**security**
- `security.device.tampered`
- `security.fuel.suspected_theft`
- `security.door.unauthorized_open`
- `security.battery.sabotage`
- `security.unauthorized_activity`
- `security.route.deviation`

**system**
- `system.kpi.computation.failed`
- `system.rule.evaluation.failed`
- `system.insight.generation.failed`
- `system.delivery.failed`
- `system.rawstore.write.failed`
- `system.contract.retired`

### C-09.5 — Modèle semi-fermé : règles d'extension

**Ce qui est fermé :**
- Liste des 11 domaines — fermée, aucun ajout sans révision contractuelle
- Événements officiels — fermés, non modifiables
- Convention de nommage — fermée, aucune exception
- Domaines `system` et `connector` — réservés GEOFACT, aucune extension client

**Ce qui est extensible** (via enregistrement contrôlé par Intégrateur Certifié) :
```json
{
  "extension_id": "string unique",
  "domain": "security",
  "event_type": "security.cargo.seal_broken",
  "description": "Rupture du scellé de cargaison détectée",
  "client_id": "CLIENT_X",
  "registered_by": "INTEGRATOR_ID",
  "registered_at": "ISO 8601",
  "status": "active"
}
```

---

## C-10 — Contrat du Connector

### C-10.2 — Deux types de Connectors

| Type | Déployé par | Supervisé par | Cas d'usage |
|------|------------|--------------|-------------|
| CENTRAL | GEOFACT | GEOFACT | Standard SaaS — cas majoritaire |
| EDGE | Client | Client (sous contrat) | Réseau fermé, contrainte terrain |

### C-10.4 — Responsabilités du Connector (ni plus, ni moins)

| Responsabilité | Description | Frontière |
|---------------|-------------|-----------|
| Réception | Recevoir les données brutes du fournisseur | Avant Raw Store |
| Déduplication transport | Détecter et rejeter les doublons de transport | Avant Raw Store |
| Normalisation | Traduire les champs fournisseur vers le modèle canonique | Après Raw Store |
| Production | Émettre un Canonical Event valide vers le Core | Après normalisation |

### C-10.5 — Déduplication : responsabilités partagées

| Niveau | Responsable | Détecte | Action |
|--------|------------|---------|--------|
| Transport | Connector | Même payload brut reçu deux fois | Rejet silencieux avant Raw Store |
| Canonique | Core | Même event_id canonique déjà traité | Flag DUPLICATE_CANONICAL — loggué |

### C-10.7 — Contrat de sortie du Connector
```json
{
  "event_id": "uuid v4 — généré par le Connector",
  "event_type": "domaine.action (taxonomie C-09)",
  "connector_id": "identifiant du Connector source",
  "provider_id": "identifiant du fournisseur GPS",
  "organization_id": "tenant",
  "device_id": "identifiant canonique du tracker",
  "vehicle_id": "identifiant canonique du véhicule — si résolu",
  "timestamp": "ISO 8601 — horodatage de l'événement source",
  "received_at": "ISO 8601 — horodatage de réception GEOFACT",
  "payload": { "speed_kmh": 87.5, "latitude": 5.3484, "longitude": -4.0167 },
  "missing_fields": ["temperature_celsius"],
  "completeness": "COMPLETE|INCOMPLETE|REJECTED",
  "raw_ref": "référence vers l'entrée Raw Store correspondante"
}
```

### C-10.9 — Cycle de vie d'un Connector

| Statut | Description | Transition autorisée vers |
|--------|-------------|--------------------------|
| pending | Enregistré, en attente de certification | active |
| active | Opérationnel et certifié | suspended, revoked |
| suspended | Temporairement désactivé | active, revoked |
| revoked | Révoqué définitivement | Aucune — terminal |

> Un Connector revoked ne peut jamais être réactivé. Un nouveau Connector doit être créé et certifié.

---

## C-11 — Cycle de Vie des Objets

### C-11.1 — Principes généraux

| Règle | Description |
|-------|-------------|
| Immuabilité de l'historique | Un objet archivé ou transféré conserve toutes ses données historiques |
| Traçabilité des transitions | Tout changement d'état génère un événement immuable horodaté |
| Suppression physique encadrée | Autorisée uniquement sur demande explicite, par Admin GEOFACT |

### C-11.2 — Objet Vehicle
```
pending → active → suspended → active
                └→ transferred (opération contractuelle — cf. C-11.8)
                └→ archived
                └→ deleted
```

### C-11.3 — Objet Trip
```
pending → active → paused → active
               └→ completed  (immuable)
               └→ cancelled  (exclu des KPIs DF)
               └→ anomalous  (anomaly_note obligatoire)
```

### C-11.4 — Objet Driver
```
pending → active → suspended → active
               └→ archived
               └→ deleted
```

### C-11.5 — Objet Device
```
pending → active → disconnected → active
               └→ error → active
               └→ tampered → (intervention admin requise)
               └→ decommissioned
               └→ deleted
```

### C-11.6 — Objet Alert
```
triggered → delivered → acknowledged → resolved
                      └→ escalated → acknowledged → resolved
                      └→ expired
```
> **Suppression physique interdite — archivage uniquement.**
> `resolution_note` obligatoire si status = resolved.

### C-11.8 — Opération contractuelle de transfert de Vehicle
Un transfert est une **opération contractuelle à part entière** — jamais un simple changement de `fleet_id`.

| historical_data_policy | Signification |
|------------------------|---------------|
| stays_source | L'historique reste visible à l'organisation source |
| follows_vehicle | L'historique complet suit le véhicule vers la cible |

> Cette politique est définie au moment du transfert et **ne peut plus être modifiée** après validation.

---

## C-12 — Modèle de Sécurité et d'Authentification

### C-12.2 — Modèle JWT uniquement
Structure du JWT GEOFACT :
```json
{
  "sub": "user_id ou connector_id",
  "actor_type": "human|connector|integrator",
  "organization_id": "tenant de rattachement",
  "fleet_ids": ["fleet_id_1", "fleet_id_2"],
  "role": "geofact_admin|org_admin|fleet_admin|supervisor|driver",
  "scope_type": "organization|fleet",
  "issued_at": "ISO 8601",
  "expires_at": "ISO 8601",
  "token_version": 1
}
```

### C-12.3 — Politique de tolérance réseau (Afrique de l'Ouest)

| Situation | Comportement |
|-----------|-------------|
| Token expiré < tolerance_window (défaut 4h) | Session maintenue — token renouvelé silencieusement |
| Token expiré > tolerance_window | Ré-authentification obligatoire |
| Token révoqué | Rejet immédiat — tolérance non applicable |

### C-12.4 — Les 6 rôles officiels GEOFACT (liste fermée en v1)

| Rôle | Code | Portée |
|------|------|--------|
| Administrateur GEOFACT | geofact_admin | Système entier — tous tenants |
| Administrateur Organisation | org_admin | Son organisation entière |
| Intégrateur Certifié | integrator | Organisations qu'il gère |
| Administrateur Fleet | fleet_admin | Ses fleets uniquement |
| Superviseur | supervisor | Ses fleets en lecture |
| Conducteur | driver | Ses propres données uniquement |

### C-12.7 — Destinataires passifs WhatsApp
Les opérateurs terrain recevant des alertes WhatsApp sont des **destinataires passifs**.
- Aucune authentification GEOFACT
- Aucun droit système
- Accusé de réception WhatsApp → met à jour Alert vers `acknowledged` sans session authentifiée

---

## C-13 — Politique de Versioning et d'Évolution des Contrats

### C-13.2 — Nomenclature
```
vMAJEUR.MINEUR

v1.0 → version initiale
v1.1 → évolution non cassante (ajout champ optionnel, clarification)
v2.0 → évolution cassante avec période de transition
```

### C-13.3 — Qui peut initier une révision

| Acteur | Peut initier | Périmètre |
|--------|-------------|-----------|
| GEOFACT | Tout contrat | Sans restriction |
| Intégrateur Certifié | C-09, C-10 | Contrats techniques qu'il implémente |
| Client | Demande uniquement | GEOFACT arbitre |

### C-13.4 — Processus formel (5 étapes)
1. **PROPOSITION** — RFC (Request For Contract Change)
2. **ÉVALUATION D'IMPACT** — Classification mineur/majeur, composants affectés
3. **VALIDATION** — Signature Admin GEOFACT obligatoire
4. **PUBLICATION** — Nouvelle version dans le Registre, ancienne maintenue
5. **CLÔTURE** — Vérification migration complète → DEPRECATED → RETIRED

### C-13.6 — Périodes de transition
- **Changement mineur** : prise d'effet immédiate
- **Changement majeur** : période de coexistence **minimum 90 jours** (non raccourcissable — contrainte terrain Afrique de l'Ouest)
