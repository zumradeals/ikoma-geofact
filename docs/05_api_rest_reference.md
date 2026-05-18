# IKOMA GEOFACT — Référence API REST v1

**Base URL** : `https://base.ikomagroup.net/api/v1`  
**Format** : JSON (`Content-Type: application/json`)  
**Auth** : JWT Bearer (sauf `/auth/login`)

---

## Conventions de réponse

Toutes les réponses suivent la structure :

```json
{
  "success": true,
  "data":    { ... },
  "message": "Description"
}
```

En cas d'erreur :
```json
{
  "success": false,
  "message": "Description de l'erreur",
  "errors":  { "champ": "message" }
}
```

Codes HTTP utilisés :

| Code | Signification |
|------|---------------|
| 200  | OK |
| 201  | Créé |
| 401  | Token absent, expiré ou invalidé |
| 403  | Accès refusé (mauvais tenant) |
| 404  | Ressource introuvable |
| 422  | Validation échouée |
| 500  | Erreur serveur |

---

## 1. Authentification

### POST `/auth/login`
Authentifie un utilisateur humain. Pas de JWT requis.

**Corps :**
```json
{
  "email":    "admin@votresociete.com",
  "password": "votre_mot_de_passe"
}
```

**Réponse 200 :**
```json
{
  "success": true,
  "data": {
    "token":      "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
    "token_type": "Bearer",
    "expires_in": 3600,
    "user": {
      "id":              "uuid-v4",
      "email":           "admin@votresociete.com",
      "role":            "org_admin",
      "organization_id": "uuid-v4"
    }
  },
  "message": "Authentification réussie."
}
```

**Exemple cURL :**
```bash
curl -X POST https://base.ikomagroup.net/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@votresociete.com","password":"motdepasse"}'
```

---

### POST `/auth/refresh`
Renouvelle le token JWT avant expiration.  
**Header requis :** `Authorization: Bearer {token}`

**Réponse 200 :**
```json
{
  "success": true,
  "data": {
    "token":      "nouveau_token...",
    "token_type": "Bearer",
    "expires_in": 3600
  }
}
```

---

### POST `/auth/logout`
Révoque le token courant.  
**Header requis :** `Authorization: Bearer {token}`

**Réponse 200 :**
```json
{ "success": true, "message": "Déconnexion réussie.", "data": [] }
```

---

## 2. Header d'authentification (toutes les routes suivantes)

```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
Content-Type: application/json
```

> **Sécurité tenant** : `organization_id` est extrait du JWT — il ne peut
> jamais être passé dans le body ou les query params. Toute tentative est ignorée.

---

## 3. Véhicules

### GET `/vehicles`
Liste tous les véhicules de l'organisation.

```bash
curl -H "Authorization: Bearer {token}" \
  https://base.ikomagroup.net/api/v1/vehicles
```

### GET `/vehicles/{id}`
Détail d'un véhicule.

### POST `/vehicles`
Créer un véhicule.

```json
{
  "name":       "Camion-01",
  "license_plate": "AB-1234-CI",
  "brand":      "Mercedes",
  "model":      "Actros",
  "year":       2022,
  "fleet_id":   "uuid-de-la-flotte",
  "status":     "active"
}
```

### PUT `/vehicles/{id}`
Modifier un véhicule (name, brand, model, year).

### PATCH `/vehicles/{id}/status`
Changer le statut d'un véhicule.

```json
{ "status": "maintenance" }
```

Valeurs : `active` | `maintenance` | `inactive` | `retired`

### POST `/vehicles/{id}/transfer`
Transférer un véhicule vers une autre flotte.

```json
{
  "target_fleet_id": "uuid-flotte-destination",
  "reason":          "Réorganisation régionale",
  "effective_date":  "2026-06-01"
}
```

### GET `/vehicles/{id}/active-trip`
Récupère le trajet actif ou en pause d'un véhicule.

---

## 4. Flottes

### GET `/fleets` | POST `/fleets` | GET `/fleets/{id}` | PUT `/fleets/{id}` | DELETE `/fleets/{id}`

**Corps POST :**
```json
{
  "name":        "Flotte Nord",
  "description": "Véhicules zone nord",
  "status":      "active"
}
```

### PATCH `/fleets/{id}/status`
```json
{ "status": "inactive" }
```

---

## 5. Conducteurs

### GET `/drivers` | POST `/drivers` | GET `/drivers/{id}` | PUT `/drivers/{id}`

**Corps POST :**
```json
{
  "first_name":      "Konan",
  "last_name":       "Yao",
  "license_number":  "CI-2021-456789",
  "license_expiry":  "2028-03-15",
  "phone":           "+2250712345678",
  "status":          "active"
}
```

---

## 6. Trajets

### GET `/trips`
Liste les trajets (filtrables par `?vehicle_id=` ou `?driver_id=`).

### GET `/trips/{id}`
Détail d'un trajet avec positions GPS si disponibles.

### POST `/trips`
Démarrer un trajet manuellement (normalement déclenché par le connecteur).

```json
{
  "vehicle_id": "uuid-vehicule",
  "driver_id":  "uuid-conducteur",
  "started_at": "2026-05-18T08:00:00Z"
}
```

---

## 7. Alertes

### GET `/alerts`
Liste les alertes. Filtres disponibles :
- `?severity=CRITICAL`
- `?status=open`
- `?vehicle_id=uuid`

### GET `/alerts/{id}`

### PATCH `/alerts/{id}/acknowledge`
Accuser réception d'une alerte.

```json
{ "note": "Pris en charge par le superviseur Dupont" }
```

### PATCH `/alerts/{id}/resolve`
Clôturer une alerte.

```json
{
  "resolution_note": "Vitesse excessive — conducteur averti",
  "resolved_by":     "uuid-utilisateur"
}
```

---

## 8. Géozones

### GET `/geozones` | POST `/geozones` | GET `/geozones/{id}` | PUT `/geozones/{id}` | DELETE `/geozones/{id}`

**Corps POST :**
```json
{
  "name":        "Zone Entrepôt Abidjan",
  "type":        "circle",
  "geometry": {
    "center": { "lat": 5.3484, "lng": -4.0165 },
    "radius_m": 500
  },
  "alert_on_entry": true,
  "alert_on_exit":  false,
  "status":         "active"
}
```

Types de géométrie : `circle` | `polygon` | `rectangle`

---

## 9. KPIs

### GET `/kpis/{scopeType}/{scopeId}`
Dernier KPI calculé pour un scope.

- `scopeType` : `vehicle` | `driver` | `fleet` | `organization`
- `scopeId` : UUID de la ressource

```bash
curl -H "Authorization: Bearer {token}" \
  https://base.ikomagroup.net/api/v1/kpis/driver/uuid-conducteur
```

### GET `/kpis/{scopeType}/{scopeId}/history`
Historique des KPIs. Paramètre optionnel : `?from=2026-01-01&to=2026-05-18`

---

## 10. Insights IA

### GET `/insights/{scopeType}/{scopeId}`
Dernier insight généré.

### POST `/insights/{scopeType}/{scopeId}/generate`
Déclenche la génération d'un insight via l'Insight Engine (Anthropic Claude).

```bash
curl -X POST -H "Authorization: Bearer {token}" \
  https://base.ikomagroup.net/api/v1/insights/vehicle/uuid-vehicule/generate
```

**Réponse 200 :**
```json
{
  "success": true,
  "data": {
    "id":         "uuid",
    "scope_type": "vehicle",
    "scope_id":   "uuid-vehicule",
    "content":    "Ce véhicule présente une consommation anormale...",
    "generated_at": "2026-05-18T14:30:00Z"
  }
}
```

---

## 11. Organisations

### GET `/organizations` | GET `/organizations/{id}`

> Note : la création d'organisation se fait via le panel SuperAdmin `/admin`.
> L'API retourne uniquement l'organisation du tenant courant (scopé JWT).

---

## 12. Webhook Connecteur GPS/IoT

Route spéciale pour les boîtiers GPS et IoT. Authentification par **token connecteur** (pas de JWT humain).

### POST `/webhook/connector/ingest`

**Authentication :** Header `X-Connector-Token: {token_en_clair}`  
(Le token est généré via `/admin` → Connecteurs → Rotation token)

**Corps :**
```json
{
  "event_type": "telemetry.position.updated",
  "device_id":  "DEVICE-ABC123",
  "timestamp":  "2026-05-18T14:22:00Z",
  "latitude":   5.3484,
  "longitude":  -4.0165,
  "speed_kmh":  67.5,
  "heading":    245,
  "altitude_m": 12,
  "accuracy_m": 4,
  "vehicle_id": "uuid-vehicule",
  "trip_id":    "uuid-trajet"
}
```

**Réponse 200 :**
```json
{
  "success": true,
  "data":    { "reached_core": true },
  "message": "Event processed"
}
```

### Types d'événements acceptés

| Catégorie | Événements |
|-----------|-----------|
| **Télémétrie** | `telemetry.position.updated`, `telemetry.speed.updated`, `telemetry.fuel.updated`, `telemetry.temperature.updated`, `telemetry.battery.updated`, `telemetry.ignition.on`, `telemetry.ignition.off` |
| **Trajets** | `trip.started`, `trip.ended`, `trip.paused`, `trip.resumed`, `trip.cancelled` |
| **Véhicule** | `vehicle.moving`, `vehicle.stopped`, `vehicle.idle`, `vehicle.assigned`, `vehicle.unassigned` |
| **Conducteur** | `driver.trip.started`, `driver.trip.ended`, `driver.score.computed`, `driver.behavior.flagged` |
| **Géozones** | `geozone.entered`, `geozone.exited`, `geozone.violated`, `geozone.overdue` |
| **Alertes** | `alert.overspeed.detected`, `alert.harsh.braking`, `alert.harsh.acceleration`, `alert.stop.unauthorized`, `alert.night.activity` |
| **Maintenance** | `maintenance.threshold.reached`, `maintenance.overdue`, `maintenance.scheduled`, `maintenance.completed` |
| **Appareil** | `device.connected`, `device.disconnected`, `device.error`, `device.tampered`, `device.low.battery` |
| **Sécurité** | `security.device.tampered`, `security.fuel.suspected_theft`, `security.unauthorized_activity`, `security.route.deviation` |

### GET `/webhook/connector/pull/{raw_id}`
Rejoue un événement depuis le raw_store (utile pour debug ou rattrapage).

---

## 13. Rôles et permissions

| Rôle | Accès API |
|------|-----------|
| `geofact_admin` | Toutes les organisations (SuperAdmin) |
| `org_admin` | Toute l'organisation |
| `fleet_admin` | Flottes assignées uniquement (fleet_ids dans JWT) |
| `supervisor` | Lecture + acknowledge/resolve alertes |
| `driver` | Ses propres trajets et KPIs |
| `integrator` | Endpoints webhook uniquement |

---

## 14. Intégration App Mobile — Flux typique

```
1. POST /auth/login          → obtenir le JWT
2. GET  /vehicles            → liste de la flotte
3. GET  /vehicles/{id}/active-trip → trajet en cours
4. GET  /alerts?status=open  → alertes actives
5. PATCH /alerts/{id}/acknowledge  → accuser réception
6. POST /auth/refresh        → avant expiration (expires_in)
7. POST /auth/logout         → à la déconnexion
```

---

## 15. Intégration Boîtier GPS — Flux typique

```
1. Obtenir le token connecteur dans /admin → Connecteurs GPS
2. POST /webhook/connector/ingest  (toutes les N secondes)
   → Header : X-Connector-Token: {token}
   → Body   : event_type + device_id + timestamp + coords
3. En cas d'échec réseau : stocker localement + rejouer via /pull
```

**Fréquence recommandée :** toutes les 30 secondes en mouvement, 5 minutes à l'arrêt.

---

## 16. Codes d'erreur spécifiques

| Message | Cause | Solution |
|---------|-------|----------|
| `Identifiants invalides` | Email/mot de passe incorrect | Vérifier les credentials |
| `Token expiré` | JWT expiré | Appeler `/auth/refresh` |
| `token_version mismatch` | Token révoqué (rotation) | Reconnecter l'utilisateur |
| `organization_id mismatch` | Accès inter-tenant refusé | Vérifier le JWT |
| `event_type hors taxonomie` | Type inconnu dans l'ingest | Voir la liste §12 |
| `Connector not found` | Token connecteur invalide | Vérifier dans /admin |
