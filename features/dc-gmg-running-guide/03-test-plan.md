# Test Plan: dc-gmg-running-guide

## Coverage summary
- AC items: ~35 (session zero, GM dashboard, secret checks, ruling records, adventure/campaign design, encounter design tools, running encounter UI)
- Test cases: 12 (TC-RUN-01–12)
- Suites: playwright (downtime, encounter, character creation)
- Security: AC exemption granted (no new routes)

---

## TC-RUN-01 — Session zero workflow: party links, character integration notes
- Description: Pre-play phase supports records for party relationships and character integration notes for adventure hooks
- Suite: playwright/character-creation
- Expected: session-zero phase accessible before first encounter; party_links and adventure_hook fields saved per character
- AC: SessionZero-1

## TC-RUN-02 — GM dashboard: key PC modifier cache (Perception, Will, Recall Knowledge)
- Description: GM sees quick-reference cache of PC modifiers (Perception, Will, common RK skills); refreshes on level-up or stat change
- Suite: playwright/encounter
- Expected: GM dashboard panel shows PC modifier list; values update automatically after level-up or stat edit
- AC: GMDashboard-1

## TC-RUN-03 — Secret check mode: GM-only roll outcome view
- Description: Secret-check mode hides roll outcomes from players; GM-only view
- Suite: playwright/encounter
- Expected: secret mode toggle hides roll results in player view; GM sees all results; player sees only "roll was made"
- AC: GMDashboard-2

## TC-RUN-04 — Ruling records: precedent linkage and provisional flags
- Description: Rulings can reference prior analogous decisions; accumulated precedents tracked; GM can mark ruling provisional with deferred review flag
- Suite: playwright/downtime
- Expected: ruling record UI allows linking to prior ruling; provisional flag + deferred-review flag available; review workflow before next session
- AC: Rulings-1–3

## TC-RUN-05 — Creative-action resolution templates
- Description: GM has templates for: minor bonus, minor penalty, minor damage-plus-rider, object-triggered save; one-time exception flag prevents disruptive precedent
- Suite: playwright/encounter
- Expected: resolution template picker available; one-time-exception flag marks ruling as non-precedent
- AC: Rulings-4–5

## TC-RUN-06 — Adventure authoring: motivation hooks, scene diversity tracking
- Description: Per-player motivation hooks and engagement targets; scene-type diversity tracking (combat/social/problem-solving/stealth) per session and arc
- Suite: playwright/downtime
- Expected: adventure record has per-player hook fields; scene-type log per session; arc-level diversity summary
- AC: AdventureDesign-1–2

## TC-RUN-07 — Encounter set builder: repetition warning; encounter metadata
- Description: Encounter builder warns on repetitive composition across consecutive sessions; metadata captures narrative purpose, adversary rationale, location hooks
- Suite: playwright/encounter
- Expected: consecutive same-composition sessions → warning; encounter metadata fields present (narrative purpose, adversary rationale)
- AC: EncounterDesign-1–2

## TC-RUN-08 — Threat scheduling: trivial/low/moderate/severe; extreme as set-piece gate
- Description: Encounter builder mixes threat levels; extreme encounters gated as designated set-pieces
- Suite: playwright/encounter
- Expected: threat-level distribution visible in campaign planner; extreme encounters require explicit set-piece flag
- AC: EncounterDesign-3

## TC-RUN-09 — Turn-order UI: current + next actor shown
- Description: Encounter turn-order panel shows current actor highlighted and next actor in queue
- Suite: playwright/encounter
- Expected: current turn actor visually distinct; next-up actor shown in initiative order
- AC: RunningEncounters-1

## TC-RUN-10 — Turn manager: same-turn rewinds permitted; cross-turn blocked by default
- Description: Same-turn rewinds allowed; cross-turn rewinds blocked by default (configurable setting)
- Suite: playwright/encounter
- Expected: undo within current turn succeeds; undo crossing a turn boundary blocked unless config enabled; lightweight corrections (omitted static damage) always allowed
- AC: RunningEncounters-3–4

## TC-RUN-11 — Stealth initiative: compares Stealth vs. each observer's Perception DC
- Description: When "Avoiding Notice" initiative used: compare Stealth result against each enemy's Perception DC; determines undetected state per observer
- Suite: playwright/encounter
- Expected: stealth initiative roll compared against each observer individually; result: undetected to some observers, detected to others (per result)
- AC: RunningEncounters-5–6

## TC-RUN-12 — Campaign planner: scope templates and player preference intake
- Description: Campaign scope templates (one-shot, brief, extended, epic) with level ceilings and session cadence; templates promotable to longer arcs; player-preference intake collected at campaign creation
- Suite: playwright/downtime
- Expected: 4 scope templates available; promotion preserves data; player goal coverage tracked per session
- AC: CampaignDesign-1–3
