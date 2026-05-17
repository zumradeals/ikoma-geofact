# IKOMA GEOFACT

Système d'intelligence flotte multi-tenant pour PME africaines.

**Stack : PHP / Laravel 11 / MySQL / cPanel**

---

## Documents de référence

| Fichier | Contenu |
|---------|---------|
| `CLAUDE.md` | Instructions pour Claude Code |
| `docs/01_architecture_conceptuelle_v3.md` | 13 contrats C-01 à C-13 |
| `docs/02_dictionnaire_canonique_v1.md` | 16 tables MySQL DC-01 à DC-16 |
| `docs/03_arborescence_technique.md` | Structure Laravel + cPanel |
| `docs/04_prompts_claude_code.md` | 12 prompts séquentiels Claude Code |

---

## Démarrage avec Claude Code

```bash
# Dans le dossier du projet
claude
```

Claude Code lira automatiquement `CLAUDE.md` au démarrage.
Envoyer ensuite le Prompt P-00 (amorçage), puis P-01 à P-12 dans l'ordre.

---

## Règles absolues

- UUID v4 partout — jamais auto-increment
- Tables immuables (INSERT only) : `raw_store`, `audit_logs`, `telemetry_events`, `kpi_records`
- Isolation des couches : `Core/` → `Connector/` → `Rules/` → `Kpi/` → `Delivery/`
- Isolation tenant : `TenantInjectorMiddleware` sur toutes les routes API
- Un contrat violé = un test qui échoue → corriger le code, jamais le test

---

## Projet IKOMA · GAMAD Technologie
