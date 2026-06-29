# Dungeoncrawler — Canonicalize spell slot and focus point resource authority

Date: 2026-06-06
Owner seat: ceo-copilot-2
Priority: Medium

## Context
Current resource ownership is still split for encounter casting:
- Encounter cast flow (`EncounterPhaseHandler::processCastSpell`) mutates spell/focus state on combat participant snapshot state.
- Canonical campaign character sheets are the required source of truth.

This creates risk of drift between encounter runtime state and canonical character state.

## Requested review scope
1. Audit every spell/focus spend path (PC, NPC, item-assisted cast flows).
2. Move authoritative spell slot and focus point mutation to canonical campaign character-sheet objects.
3. Keep encounter/runtime stores as projections only (no independent authority).
4. Add contract tests proving no divergence after cast actions and turn transitions.

## Acceptance criteria
- Canonical character sheet is authoritative for spell slots and focus points.
- Encounter and projected combat payloads reflect canonical values after every cast.
- No code path spends spell/focus only in participant snapshot state.
- Regression tests pass for cast, sync, and turn-advance flows.

## Progress update — 2026-06-12
- Completed canonical-authority hardening for encounter and exploration cast spend paths.
- Removed encounter participant-only spend fallback for both focus and slot-consuming spells.
- Exploration cast/refocus now require canonical character identity and mutate canonical state before projection sync.
- Added/updated unit coverage for canonical spend sync and canonical-required failure paths.
- Implementation shipped in `dungeoncrawler-content` commit `f87c1e9` on `main`.
