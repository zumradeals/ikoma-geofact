# Dictionnaire Canonique GEOFACT v1
## Stack : PHP / Laravel / MySQL

---

## Conventions globales
- **UUID v4** partout — jamais `AUTO_INCREMENT` comme identifiant métier
- **ENGINE=InnoDB**, CHARSET=utf8mb4, COLLATE=utf8mb4_unicode_ci sur toutes les tables
- **ON DELETE RESTRICT** sur toutes les FK métier
- Tables immuables (raw_store, audit_logs, telemetry_events, kpi_records) : pas de colonne `updated_at`

---

## Récapitulatif des 16 tables

| Code | Table | Immuable | Notes |
|------|-------|----------|-------|
| DC-01 | organizations | Non | Tenant racine |
| DC-02 | fleets | Non | Subdivision opérationnelle |
| DC-03 | vehicles | Non | Unité terrain centrale |
| DC-04 | drivers | Non | Conducteur scoré |
| DC-05 | devices | Non | Tracker GPS physique |
| DC-06 | connectors | Non | Ingestion certifiée |
| DC-07 | trips | partiel | Trip completed immuable |
| DC-08 | geozones | Non | Zone géographique versionnée |
| DC-09 | alerts | partiel | Suppression physique interdite |
| DC-10 | telemetry_events | **OUI** | INSERT only absolu |
| DC-11 | raw_store | **OUI** | INSERT only absolu |
| DC-12 | kpi_records | **OUI** | INSERT only absolu |
| DC-13 | insights | Non | Versionné |
| DC-14 | users | Non | Acteur authentifié |
| DC-15 | audit_logs | **OUI** | INSERT only absolu |
| DC-16 | vehicle_transfers | partiel | historical_data_policy immuable après validation |

---

## DC-01 — organizations

```sql
CREATE TABLE organizations (
  id           CHAR(36)     NOT NULL,
  name         VARCHAR(150) NOT NULL,
  country_code CHAR(2)      NOT NULL DEFAULT 'CI',
  timezone     VARCHAR(60)  NOT NULL DEFAULT 'Africa/Abidjan',
  status       ENUM('active','suspended','deleted') NOT NULL DEFAULT 'active',
  created_by   CHAR(36)     NOT NULL,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at   DATETIME     NULL,
  PRIMARY KEY (id),
  INDEX idx_status (status),
  INDEX idx_country (country_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| id | UUID v4 généré côté PHP — jamais auto-increment |
| name | Non vide, min 2 caractères, max 150 |
| country_code | Liste ISO 3166-1 — CI par défaut |
| timezone | Liste IANA officielle |
| status | Transition `deleted` irréversible — soft delete uniquement |

---

## DC-02 — fleets

```sql
CREATE TABLE fleets (
  id              CHAR(36)    NOT NULL,
  organization_id CHAR(36)    NOT NULL,
  name            VARCHAR(150) NOT NULL,
  fleet_type      ENUM('transport','mining','maintenance','executive','regional','custom')
                              NOT NULL DEFAULT 'transport',
  status          ENUM('active','suspended','archived','deleted') NOT NULL DEFAULT 'active',
  created_by      CHAR(36)    NOT NULL,
  created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fleet_name (organization_id, name),
  CONSTRAINT fk_fleet_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_organization (organization_id),
  INDEX idx_status (status),
  INDEX idx_type (fleet_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| organization_id | Doit référencer une Organization active |
| name | Unique par organization_id — min 2 chars |
| status | `archived` → `deleted` autorisé. `deleted` → toute autre : interdit |

---

## DC-03 — vehicles

```sql
CREATE TABLE vehicles (
  id              CHAR(36)    NOT NULL,
  fleet_id        CHAR(36)    NOT NULL,
  organization_id CHAR(36)    NOT NULL,  -- déduit de fleet_id — jamais saisi manuellement
  name            VARCHAR(100) NOT NULL,
  plate           VARCHAR(20)  NOT NULL,
  brand           VARCHAR(80)  NULL,
  model           VARCHAR(80)  NULL,
  year            SMALLINT     NULL,
  status          ENUM('pending','active','suspended','transferred','archived','deleted')
                              NOT NULL DEFAULT 'pending',
  created_by      CHAR(36)    NOT NULL,
  created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  archived_at     DATETIME    NULL,
  deleted_at      DATETIME    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_plate_org (plate, organization_id),
  CONSTRAINT fk_vehicle_fleet
    FOREIGN KEY (fleet_id) REFERENCES fleets(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_vehicle_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_fleet (fleet_id),
  INDEX idx_organization (organization_id),
  INDEX idx_status (status),
  INDEX idx_plate (plate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| organization_id | Calculé automatiquement depuis fleet_id — jamais saisi |
| plate | Unique par organization_id |
| year | Entre 1990 et année courante + 1 |
| archived_at | Délai minimum 90 jours avant autorisation de deleted |
| deleted_at | id définitivement réservé après suppression — jamais réutilisé |

---

## DC-04 — drivers

```sql
CREATE TABLE drivers (
  id              CHAR(36)    NOT NULL,
  organization_id CHAR(36)    NOT NULL,
  fleet_id        CHAR(36)    NULL,  -- optionnel — conducteur peut être multi-fleet
  first_name      VARCHAR(80)  NOT NULL,
  last_name       VARCHAR(80)  NOT NULL,
  phone           VARCHAR(20)  NULL,  -- format E.164
  license_number  VARCHAR(50)  NULL,
  license_expiry  DATE         NULL,
  status          ENUM('pending','active','suspended','archived','deleted')
                              NOT NULL DEFAULT 'pending',
  created_by      CHAR(36)    NOT NULL,
  created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME    NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_driver_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_organization (organization_id),
  INDEX idx_fleet (fleet_id),
  INDEX idx_status (status),
  INDEX idx_license_expiry (license_expiry)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| phone | Format E.164 obligatoire si renseigné — ex: +2250700000000 |
| license_expiry | Alerte automatique si < 30 jours → driver.behavior.flagged |
| status | `suspended` bloque toute affectation à un Trip |
| Contrainte | Un seul Driver active par Vehicle à un instant T |

---

## DC-05 — devices

```sql
CREATE TABLE devices (
  id               CHAR(36)    NOT NULL,
  vehicle_id       CHAR(36)    NULL,  -- NULL si non encore affecté
  organization_id  CHAR(36)    NOT NULL,
  imei             VARCHAR(20)  NOT NULL,
  provider_id      VARCHAR(60)  NOT NULL,  -- wialon|traccar|teltonika|custom
  serial_number    VARCHAR(80)  NULL,
  firmware_version VARCHAR(40)  NULL,
  status           ENUM('pending','active','disconnected','error','tampered','decommissioned','deleted')
                               NOT NULL DEFAULT 'pending',
  last_seen_at     DATETIME    NULL,
  created_by       CHAR(36)    NOT NULL,
  created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_imei (imei),
  CONSTRAINT fk_device_vehicle
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_device_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_vehicle (vehicle_id),
  INDEX idx_organization (organization_id),
  INDEX idx_status (status),
  INDEX idx_last_seen (last_seen_at),
  INDEX idx_imei (imei)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| imei | 15 chiffres — validation Luhn obligatoire |
| vehicle_id | Un Device ne peut être affecté qu'à un seul Vehicle actif |
| status = tampered | Transition vers active bloquée sans validation Admin |
| last_seen_at | Si > 24h sans update → événement device.disconnected automatique |

---

## DC-06 — connectors

```sql
CREATE TABLE connectors (
  id               CHAR(36)    NOT NULL,
  organization_id  CHAR(36)    NOT NULL,
  provider_id      VARCHAR(60)  NOT NULL,
  connector_type   ENUM('CENTRAL','EDGE') NOT NULL DEFAULT 'CENTRAL',
  token_hash       VARCHAR(255) NOT NULL,  -- hash bcrypt du JWT
  token_version    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  certified_by     CHAR(36)    NOT NULL,  -- geofact_admin user_id
  certified_at     DATETIME    NOT NULL,
  status           ENUM('pending','active','suspended','revoked') NOT NULL DEFAULT 'pending',
  contract_versions JSON        NOT NULL,  -- {"C-09":"v1.0","C-10":"v1.0"}
  last_sync_at     DATETIME    NULL,
  created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_connector_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_organization (organization_id),
  INDEX idx_status (status),
  INDEX idx_provider (provider_id),
  INDEX idx_last_sync (last_sync_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| token_hash | Jamais stocké en clair — bcrypt uniquement |
| token_version | Incrément à chaque rotation — toute version inférieure rejetée |
| status = revoked | Irréversible — nouveau Connector obligatoire |
| contract_versions | Doit déclarer au minimum C-09 et C-10 |

---

## DC-07 — trips

```sql
CREATE TABLE trips (
  id               CHAR(36)     NOT NULL,
  vehicle_id       CHAR(36)     NOT NULL,
  driver_id        CHAR(36)     NULL,
  organization_id  CHAR(36)     NOT NULL,
  fleet_id         CHAR(36)     NOT NULL,
  status           ENUM('pending','active','paused','completed','cancelled','anomalous')
                                NOT NULL DEFAULT 'pending',
  started_at       DATETIME     NULL,
  ended_at         DATETIME     NULL,
  duration_minutes SMALLINT UNSIGNED NULL,  -- calculé à la complétion
  distance_km      DECIMAL(10,3) NULL,       -- calculé à la complétion
  start_latitude   DECIMAL(10,7) NULL,
  start_longitude  DECIMAL(10,7) NULL,
  end_latitude     DECIMAL(10,7) NULL,
  end_longitude    DECIMAL(10,7) NULL,
  anomaly_note     TEXT         NULL,  -- requis si status=anomalous
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_trip_vehicle
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_trip_driver
    FOREIGN KEY (driver_id) REFERENCES drivers(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_trip_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_trip_fleet
    FOREIGN KEY (fleet_id) REFERENCES fleets(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_vehicle (vehicle_id),
  INDEX idx_driver (driver_id),
  INDEX idx_organization (organization_id),
  INDEX idx_fleet (fleet_id),
  INDEX idx_status (status),
  INDEX idx_started_at (started_at),
  INDEX idx_ended_at (ended_at),
  INDEX idx_vehicle_status (vehicle_id, status)  -- requête fréquente : trip actif d'un véhicule
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| ended_at | Doit être > started_at — rejet sinon |
| status = completed | Déclenche calcul duration_minutes et distance_km |
| status = completed | Immuable — aucune modification après complétion |
| status = anomalous | anomaly_note obligatoire — jamais supprimé automatiquement |
| status = cancelled | Exclu des KPIs analytiques DF |
| Contrainte | Un seul Trip active ou paused par Vehicle |

---

## DC-08 — geozones

```sql
CREATE TABLE geozones (
  id               CHAR(36)    NOT NULL,
  organization_id  CHAR(36)    NOT NULL,
  fleet_id         CHAR(36)    NULL,  -- NULL = applicable à toute l'org
  name             VARCHAR(100) NOT NULL,
  zone_type        ENUM('authorized','restricted','depot','customer','alert','custom')
                               NOT NULL DEFAULT 'custom',
  geometry         JSON        NOT NULL,  -- GeoJSON Polygon ou Circle
  max_stay_minutes SMALLINT UNSIGNED NULL,
  active_from      TIME        NULL,
  active_to        TIME        NULL,
  version          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status           ENUM('draft','active','suspended','archived','deleted')
                               NOT NULL DEFAULT 'draft',
  created_by       CHAR(36)    NOT NULL,
  created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_geozone_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_organization (organization_id),
  INDEX idx_fleet (fleet_id),
  INDEX idx_status (status),
  INDEX idx_type (zone_type),
  INDEX idx_version (id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| geometry | GeoJSON valide — Polygon ou Circle uniquement |
| version | Toute modification de geometry sur zone active incrémente version — ancienne version archivée |
| active_from / active_to | Les deux doivent être renseignés ensemble ou aucun |

---

## DC-09 — alerts

```sql
CREATE TABLE alerts (
  id               CHAR(36)    NOT NULL,
  organization_id  CHAR(36)    NOT NULL,
  fleet_id         CHAR(36)    NOT NULL,
  vehicle_id       CHAR(36)    NOT NULL,
  driver_id        CHAR(36)    NULL,
  trip_id          CHAR(36)    NULL,
  rule_id          VARCHAR(30)  NOT NULL,  -- RS-01, RMC-CLIENT_X-01...
  rule_type        ENUM('RS','RMC') NOT NULL,
  event_type       VARCHAR(80)  NOT NULL,  -- taxonomie C-09
  severity         ENUM('LOW','MEDIUM','HIGH','CRITICAL') NOT NULL DEFAULT 'MEDIUM',
  status           ENUM('triggered','delivered','acknowledged','escalated','resolved','expired')
                               NOT NULL DEFAULT 'triggered',
  triggered_at     DATETIME    NOT NULL,
  acknowledged_at  DATETIME    NULL,
  resolved_at      DATETIME    NULL,
  resolution_note  TEXT        NULL,  -- obligatoire si resolved
  escalated_at     DATETIME    NULL,
  payload          JSON        NOT NULL,  -- contexte de l'alerte
  created_at       DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_alert_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_alert_vehicle
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_alert_trip
    FOREIGN KEY (trip_id) REFERENCES trips(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  INDEX idx_organization (organization_id),
  INDEX idx_vehicle (vehicle_id),
  INDEX idx_status (status),
  INDEX idx_severity (severity),
  INDEX idx_triggered_at (triggered_at),
  INDEX idx_rule (rule_id),
  INDEX idx_event_type (event_type),
  INDEX idx_vehicle_status (vehicle_id, status, triggered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| resolution_note | Obligatoire si status = resolved — rejet si vide |
| Suppression physique | Interdite — archivage uniquement (bloquer dans boot() Eloquent) |
| status | Jamais rétrogradé — transitions uniquement vers l'avant |

---

## DC-10 — telemetry_events

```sql
CREATE TABLE telemetry_events (
  id                 CHAR(36)      NOT NULL,
  connector_id       CHAR(36)      NOT NULL,
  device_id          CHAR(36)      NOT NULL,
  vehicle_id         CHAR(36)      NULL,
  organization_id    CHAR(36)      NOT NULL,
  trip_id            CHAR(36)      NULL,
  event_type         VARCHAR(80)   NOT NULL,  -- taxonomie C-09
  ts                 DATETIME(3)   NOT NULL,  -- précision milliseconde
  received_at        DATETIME(3)   NOT NULL,
  latitude           DECIMAL(10,7) NULL,
  longitude          DECIMAL(10,7) NULL,
  speed_kmh          DECIMAL(6,2)  NULL,
  heading            SMALLINT      NULL,  -- 0-359 degrés
  altitude_m         DECIMAL(8,2)  NULL,
  fuel_level_pct     DECIMAL(5,2)  NULL,
  temperature_celsius DECIMAL(5,2) NULL,
  ignition           TINYINT(1)    NULL,
  payload            JSON          NOT NULL,
  completeness       ENUM('COMPLETE','INCOMPLETE','REJECTED') NOT NULL DEFAULT 'COMPLETE',
  missing_fields     JSON          NULL,
  raw_ref            CHAR(36)      NOT NULL,  -- référence Raw Store
  PRIMARY KEY (id),
  CONSTRAINT fk_telemetry_device
    FOREIGN KEY (device_id) REFERENCES devices(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_telemetry_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_vehicle (vehicle_id),
  INDEX idx_device (device_id),
  INDEX idx_organization (organization_id),
  INDEX idx_trip (trip_id),
  INDEX idx_ts (ts),
  INDEX idx_event_type (event_type),
  INDEX idx_completeness (completeness),
  INDEX idx_vehicle_ts (vehicle_id, ts)  -- requête KPI RT critique
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| ts | Précision milliseconde — rejet si absent (CCS) |
| latitude / longitude | Entre -90/90 et -180/180 respectivement |
| speed_kmh | Entre 0 et 300 — valeur hors plage → flag anomalie |
| heading | Entre 0 et 359 |
| fuel_level_pct | Entre 0.00 et 100.00 |
| Immuabilité | Aucun UPDATE autorisé — INSERT uniquement (bloquer dans boot() Eloquent) |

---

## DC-11 — raw_store

```sql
CREATE TABLE raw_store (
  id             CHAR(36)   NOT NULL,
  connector_id   CHAR(36)   NOT NULL,
  organization_id CHAR(36)  NOT NULL,
  received_at    DATETIME(3) NOT NULL,
  payload_raw    LONGTEXT   NOT NULL,  -- données brutes exactes reçues
  payload_format ENUM('json','xml','csv','binary','unknown') NOT NULL DEFAULT 'json',
  flag           ENUM('OK','INCOMPLETE','REJECTED','CORRUPTED','DUPLICATE_TRANSPORT')
                            NOT NULL DEFAULT 'OK',
  canonical_ref  CHAR(36)   NULL,  -- référence TelemetryEvent si produit
  processed_at   DATETIME(3) NULL,
  PRIMARY KEY (id),
  INDEX idx_connector (connector_id),
  INDEX idx_organization (organization_id),
  INDEX idx_received_at (received_at),
  INDEX idx_flag (flag),
  INDEX idx_canonical_ref (canonical_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| Immuabilité | Aucun UPDATE ni DELETE autorisé — INSERT uniquement |
| payload_raw | Stocké tel quel — jamais modifié ni normalisé |
| flag = REJECTED | canonical_ref toujours NULL |
| Rétention | Minimum 12 mois — configurable par Admin GEOFACT |

---

## DC-12 — kpi_records

```sql
CREATE TABLE kpi_records (
  id               CHAR(36)       NOT NULL,
  organization_id  CHAR(36)       NOT NULL,
  scope_type       ENUM('organization','fleet','vehicle','driver') NOT NULL,
  scope_id         CHAR(36)       NOT NULL,
  kpi_type         VARCHAR(80)    NOT NULL,  -- driver_score, fleet_utilization_rate...
  mode             ENUM('RT','DF') NOT NULL,
  period_from      DATETIME       NULL,  -- NULL pour RT
  period_to        DATETIME       NULL,
  value            DECIMAL(15,4)  NOT NULL,
  unit             VARCHAR(20)    NULL,  -- km, %, score, minutes...
  version          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  computed_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  scheduler_run_id CHAR(36)       NULL,  -- référence run DF
  PRIMARY KEY (id),
  INDEX idx_organization (organization_id),
  INDEX idx_scope (scope_type, scope_id),
  INDEX idx_kpi_type (kpi_type),
  INDEX idx_mode (mode),
  INDEX idx_period (period_from, period_to),
  INDEX idx_computed_at (computed_at),
  INDEX idx_scope_kpi_period (scope_id, kpi_type, period_from)  -- requête dashboard critique
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## DC-13 — insights

```sql
CREATE TABLE insights (
  id               CHAR(36)    NOT NULL,
  organization_id  CHAR(36)    NOT NULL,
  scope_type       ENUM('event','trip','vehicle','driver','fleet','organization') NOT NULL,
  scope_id         CHAR(36)    NOT NULL,
  insight_type     ENUM('anomaly','trend','performance','alert','summary') NOT NULL,
  language         CHAR(2)     NOT NULL DEFAULT 'fr',
  insight_text     TEXT        NOT NULL,
  confidence_level ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
  source_kpis      JSON        NULL,  -- [kpi_record_id, ...]
  source_events    JSON        NULL,  -- [telemetry_event_id, ...]
  version          TINYINT UNSIGNED NOT NULL DEFAULT 1,
  generated_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_organization (organization_id),
  INDEX idx_scope (scope_type, scope_id),
  INDEX idx_type (insight_type),
  INDEX idx_generated_at (generated_at),
  INDEX idx_confidence (confidence_level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## DC-14 — users

```sql
CREATE TABLE users (
  id              CHAR(36)    NOT NULL,
  organization_id CHAR(36)    NOT NULL,
  first_name      VARCHAR(80)  NOT NULL,
  last_name       VARCHAR(80)  NOT NULL,
  email           VARCHAR(150) NOT NULL,
  password_hash   VARCHAR(255) NOT NULL,  -- bcrypt
  role            ENUM('geofact_admin','org_admin','integrator','fleet_admin','supervisor','driver')
                              NOT NULL,
  fleet_ids       JSON        NULL,  -- fleets accessibles si fleet_admin/supervisor
  token_version   TINYINT UNSIGNED NOT NULL DEFAULT 1,
  status          ENUM('pending','active','suspended','deleted') NOT NULL DEFAULT 'pending',
  last_login_at   DATETIME    NULL,
  created_by      CHAR(36)    NOT NULL,
  created_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  deleted_at      DATETIME    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email (email),
  CONSTRAINT fk_user_organization
    FOREIGN KEY (organization_id) REFERENCES organizations(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_organization (organization_id),
  INDEX idx_role (role),
  INDEX idx_status (status),
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| password_hash | Jamais en clair — bcrypt coût 12 minimum |
| email | Unique global — validation RFC 5322 |
| role = geofact_admin | Un seul par système — protégé par seed |
| token_version | Incrément à chaque changement de mot de passe ou révocation |
| fleet_ids | Obligatoire si role = fleet_admin ou supervisor |

---

## DC-15 — audit_logs

```sql
CREATE TABLE audit_logs (
  id            CHAR(36)     NOT NULL,
  actor_id      CHAR(36)     NOT NULL,
  actor_role    VARCHAR(30)  NOT NULL,
  organization_id CHAR(36)   NULL,
  action        VARCHAR(120) NOT NULL,
  resource_type VARCHAR(60)  NOT NULL,
  resource_id   CHAR(36)     NULL,
  result        ENUM('success','rejected','forbidden') NOT NULL,
  ip_address    VARCHAR(45)  NULL,  -- IPv4 ou IPv6
  user_agent    VARCHAR(255) NULL,
  payload       JSON         NULL,  -- contexte additionnel
  created_at    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  INDEX idx_actor (actor_id),
  INDEX idx_organization (organization_id),
  INDEX idx_resource (resource_type, resource_id),
  INDEX idx_result (result),
  INDEX idx_created_at (created_at),
  INDEX idx_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Règles de validation métier :**

| Champ | Règle |
|-------|-------|
| Immuabilité | Aucun UPDATE ni DELETE autorisé — INSERT uniquement |
| Rétention actions de configuration | 2 ans minimum |
| Rétention suppressions physiques | 5 ans minimum |
| Rétention accès non autorisés | 2 ans minimum |
| Rétention standard | 1 an minimum |

---

## DC-16 — vehicle_transfers

```sql
CREATE TABLE vehicle_transfers (
  id                      CHAR(36)   NOT NULL,
  vehicle_id              CHAR(36)   NOT NULL,
  transfer_type           ENUM('inter_fleet','inter_organization') NOT NULL,
  source_organization_id  CHAR(36)   NOT NULL,
  source_fleet_id         CHAR(36)   NOT NULL,
  target_organization_id  CHAR(36)   NOT NULL,
  target_fleet_id         CHAR(36)   NOT NULL,
  historical_data_policy  ENUM('stays_source','follows_vehicle') NOT NULL,
  effective_date          DATETIME   NOT NULL,
  status                  ENUM('pending','validated','completed','rejected')
                                     NOT NULL DEFAULT 'pending',
  initiated_by            CHAR(36)   NOT NULL,
  initiated_at            DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  validated_by            CHAR(36)   NULL,
  validated_at            DATETIME   NULL,
  notes                   TEXT       NULL,
  PRIMARY KEY (id),
  CONSTRAINT fk_transfer_vehicle
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  INDEX idx_vehicle (vehicle_id),
  INDEX idx_status (status),
  INDEX idx_source_org (source_organization_id),
  INDEX idx_target_org (target_organization_id),
  INDEX idx_effective_date (effective_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> **Règle absolue** : `historical_data_policy` ne peut jamais être modifié après `status = validated`.

---

## Ordre de migration Laravel
```
001_create_organizations_table
002_create_fleets_table
003_create_vehicles_table
004_create_drivers_table
005_create_devices_table
006_create_connectors_table
007_create_trips_table
008_create_geozones_table
009_create_alerts_table
010_create_telemetry_events_table
011_create_raw_store_table
012_create_kpi_records_table
013_create_insights_table
014_create_users_table
015_create_audit_logs_table
016_create_vehicle_transfers_table
```
