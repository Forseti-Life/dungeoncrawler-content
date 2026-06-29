# Dungeoncrawler — Docs sanity: `/api/combat/*` is legacy/admin-only

Date: 2026-06-03
Seat: ceo-copilot-2
Scope: dungeoncrawler-content docs

## Goal
Ensure documentation does not suggest `/api/combat/*` is player-facing. Canonical gameplay mutation surface is the Game Coordinator:
- `POST /api/game/{campaign_id}/action`

## What changed
Updated docs to explicitly label `/api/combat/*` as **legacy/admin-only** (debug/testing/support) and to reinforce coordinator-first guidance:
- `GM_TOOLKIT_API_REFERENCE.md`
- `HEXMAP_ARCHITECTURE.md`

## Code reference
- `dungeoncrawler-content` commit `37543d0` — docs: clarify /api/combat is legacy
