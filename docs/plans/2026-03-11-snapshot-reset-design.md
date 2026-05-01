# WPfaker Test Environment — Snapshot/Reset System

## Goal

Reproducible WordPress test environment with fast reset capability. All 6 test plugins installed, all schemas imported, golden DB snapshot for instant reset.

## Architecture

Blueprint directory = source of truth. Docker directory = runtime copy (gitignored). Makefile orchestrates everything.

## Workflow

```
make up         → Docker starten, WordPress installieren
make provision  → Plugins + Schemas + Snapshot erstellen
make reset      → DB aus golden.sql zurücksetzen (~3s)
make destroy    → Alles löschen (docker compose down -v)
make snapshot   → Neuen Golden Snapshot erstellen
make status     → Container + Plugin Status
```

## Provisioning Steps

1. Alle 6 Testplugins aktivieren
2. Schema-Import pro Plugin (Movies + Recipes)
3. Alle Plugins deaktivieren außer ACF Pro
4. mu-plugins kopieren
5. DB exportieren → snapshots/golden.sql.gz

## Files

- `Makefile` — Orchestrierung
- `Blueprint/provision.sh` — Plugin-Setup + Schema-Import
- `snapshots/golden.sql.gz` — DB-Snapshot (committed)

## Default State

- ACF Pro aktiv, alle anderen installiert aber deaktiviert
- Movies + Recipes Schemas in allen 5 Plugins importiert
- WPfaker NICHT installiert
