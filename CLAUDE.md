# IKOMA GEOFACT — Instructions pour Claude Code

## Contexte du projet
Système d'intelligence flotte multi-tenant pour PME africaines.
**Stack : PHP / Laravel / MySQL / cPanel**

## Documents de référence obligatoires
Avant toute action, lire dans l'ordre :
1. `docs/01_architecture_conceptuelle_v3.md` — 13 contrats C-01 à C-13
2. `docs/02_dictionnaire_canonique_v1.md` — 16 tables MySQL DC-01 à DC-16
3. `docs/03_arborescence_technique.md` — structure Laravel
4. `docs/04_prompts_claude_code.md` — 12 prompts séquentiels

## Stack technique
- **Backend** : PHP 8.2+ / Laravel 11
- **Base de données** : MySQL 8.0 / MariaDB 10.6+
- **ORM** : Eloquent (Laravel natif)
- **Auth** : JWT via tymon/jwt-auth
- **Hébergement** : cPanel (shared hosting ou VPS)
- **UUID** : ramsey/uuid

## Règles absolues (jamais violées)

### Identifiants
- UUID v4 partout — jamais auto_increment comme identifiant métier
- Généré côté PHP via Str::uuid() ou ramsey/uuid
- Type MySQL : CHAR(36) NOT NULL

### Tables immuables (INSERT uniquement — aucun UPDATE jamais)
- raw_store
- audit_logs
- telemetry_events
- kpi_records

### Isolation des couches
- `app/Core/` n'importe rien depuis Connector/, Rules/, Kpi/, Delivery/
- `app/Connector/` ne connaît pas Rules/ — il dispatch des Events Laravel
- `app/Rules/` ne connaît pas Delivery/ — il dispatch des Events Laravel
- `app/Kpi/RealTime/` et `app/Kpi/Deferred/` sont deux pipelines indépendants

### Sécurité et tenant
- Isolation tenant garantie par les Middleware Laravel
- Le scope organization_id vient toujours du JWT validé — jamais du body de la requête
- Toute action authentifiée génère un INSERT dans audit_logs via AuditMiddleware
- Aucune route API sans JwtAuthMiddleware + TenantInjectorMiddleware

### Modèles Eloquent
- `$primaryKey = 'id'` avec `$incrementing = false` et `$keyType = 'string'`
- `$fillable` liste exacte des champs — jamais `$guarded = []`
- Modèles immuables (RawStore, AuditLog, TelemetryEvent, KpiRecord) :
  bloquer UPDATE et DELETE dans le boot() via ContractViolationException

### Migrations Laravel
- ENGINE=InnoDB, CHARSET=utf8mb4, COLLATE=utf8mb4_unicode_ci sur toutes les tables
- ON DELETE RESTRICT sur toutes les FK métier
- Les tables immuables n'ont pas de colonne updated_at

### Séquençage des prompts
- Suivre les prompts dans l'ordre strict (P-01 à P-12)
- Ne jamais passer au prompt suivant sans validation explicite
- Ne jamais créer de fichier hors de l'arborescence définie

### Erreurs
- Toute erreur est loggée — aucune erreur silencieuse
- Les Exceptions ne révèlent jamais les détails d'infrastructure en production

## Ordre de priorité des décisions
1. Les contrats (C-01 à C-13) — non négociables
2. Le Dictionnaire Canonique (DC-01 à DC-16) — source de vérité du schéma
3. L'arborescence technique — structure des fichiers
4. Les prompts séquentiels — ordre d'implémentation

## Commandes utiles
```bash
php artisan migrate
php artisan migrate:status
php artisan test --coverage
php artisan queue:work
php artisan schedule:run
```
