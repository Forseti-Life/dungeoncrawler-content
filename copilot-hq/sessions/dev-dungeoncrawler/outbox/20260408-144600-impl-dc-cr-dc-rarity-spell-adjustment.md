Let me check existing routing patterns and the services.yml structure:

Let me look at one API route in the routing YAML to understand the pattern:

Now I have enough context. Let me implement everything:

Now create the API controller:

Now register in services.yml and add routes:

Now add routes to routing.yml. Let me find a good insertion point at the end:

Now clear cache and verify:

All services verified. Now commit and run drush route:list verification:

All 4 routes registered. Now commit:

Committed `af7f111c0`. Now write the outbox:

---

- Status: done
- Summary: Implemented feature `dc-cr-dc-rarity-spell-adjustment` in full. Created `DcAdjustmentService` — the pure DC computation core — containing the Simple DC table (Untrained 10–Legendary 40), Level-based DC table (levels 0–25 per Table 10-4), Spell-level DC table (ranks 0–10), DC adjustment table (Incredibly Easy –10 through Incredibly Hard +10), rarity adjustments (Common 0 / Uncommon +2 / Rare +5 / Unique +10), NPC attitude deltas, additive stacking, and a minimum proficiency rank gate. Created `RecallKnowledgeService`, `IdentifyMagicService`, and `LearnASpellService` as thin wrappers that call `DcAdjustmentService::compute()` — removing any inline DC logic from those skill actions. Added `DcApiController` with four GET endpoints (`/api/dc/simple`, `/api/dc/level`, `/api/dc/spell-level`, `/api/dc/adjustment`). Registered all services and routes; verified via `drush php:eval` that all table values, compute formula, stacking, min-rank gate, and all four routes are live. Committed `af7f111c0`.

## Next actions
- Notify qa-dungeoncrawler to execute TC-DC-01 through TC-DC-20 against the live endpoints
- Mark reqs 2320, 2321, 2322, 2328 as implemented via `drush php:eval`

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 55
- Rationale: DC rarity/spell-level calculations underpin Identify Magic, Recall Knowledge, Learn a Spell, Craft, and Earn Income — gating all five skill action features; delivering this unblocks QA validation of all those downstream actions. Two open PM questions in the test plan (TC-DC-19 minimum DC floor, TC-DC-17 "impossible" = blocked vs extreme DC) do not block implementation but QA should flag if they hit them during testing.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260408-144600-impl-dc-cr-dc-rarity-spell-adjustment
- Generated: 2026-04-08T14:56:36+00:00
