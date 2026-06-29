# dc-gmg-running-guide — Implementation Notes

## Commit
`470173efd`

## Files Created
| File | Purpose |
|---|---|
| `src/Service/GmReferenceService.php` | Searchable GMG ch01 reference content store with 30 seed entries |
| `src/Service/GmRunningGuideService.php` | GM operational services: session zero, dashboard, rulings, safety, story points, rarity, encounter metadata, campaign design |
| `src/Controller/GmRunningGuideController.php` | 30 REST endpoints |

## Files Modified
| File | Change |
|---|---|
| `dungeoncrawler_content.routing.yml` | +30 routes under `/api/gm/*` and `/api/campaign/{id}/gm/*` |
| `dungeoncrawler_content.services.yml` | `gm_reference` + `gm_running_guide` service registrations |
| `dungeoncrawler_content.install` | Hook 10046: 11 new DB tables + seed call |

## DB Tables Created (hook 10046)
- `dc_gm_reference` — GMG reference content (searchable, structured_data_json)
- `dc_session_zero` — session zero preferences per campaign
- `dc_gm_dashboard` — PC modifier cache (refresh-on-levelup)
- `dc_secret_check_log` — GM override reveals for secret Calculator rolls
- `dc_ruling_record` — provisional/precedent/exception ruling log
- `dc_safety_config` — lethality_level, tpk_handling, X-Card, lines/veils
- `dc_story_points` — story point pool per campaign/session
- `dc_rarity_allowlist` — allowed/denied content rarity entries
- `dc_encounter_meta` — narrative metadata: purpose, adversary_rationale, setup_profile, dynamic_twists
- `dc_scene_design` — scene type tracking + over-concentration warnings
- `dc_campaign_design` — scope template, level ceiling, downtime depth, collaboration mode

## Reference Seed Catalog (30 entries)
Covers all 13 GMG ch01 sections: `general_advice`, `adjudicating_rules`, `adventure_design`, `campaign_structure`, `encounter_design`, `running_encounters`, `running_exploration`, `running_downtime`, `rarity`, `narrative_collaboration`, `resolving_problems`, `special_circumstances`, `drawing_maps`.

Structured data tables included:
- XP budget thresholds (Trivial/Low/Moderate/Severe/Extreme) for encounter_design entries
- Campaign scope templates (one-shot/short/medium/long) for campaign_structure entries
- Encounter setup profiles (ambush/stand-off/strategic-terrain/social-skill-challenge/dungeon-crawl) for running_encounters entries
- Collaboration modes (open/mystery/sandbox/structured) for narrative_collaboration entries
- Downtime depth templates (light/standard/deep) for running_downtime entries

## API Design Notes

### Secret Check Reveal
`POST /api/gm/secret-check/reveal` — GM explicitly reveals a Calculator secret check result to specific players. Log is append-only; `check_ref` links to Calculator roll output.

### Story Points
- Valid spend actions: `minor_twist`, `scene_fact`, `npc_attitude_shift` (cannot auto-resolve whole scenes per GMG AC)
- Pool resets each session via `POST /api/campaign/{id}/gm/story-points/reset`
- 400 returned if spend attempted with 0 available

### Rarity Evaluation
Evaluation order: denylist → allowlist → semantics (only `common` freely available; `uncommon`/`rare`/`unique` require allowlist).

### Safety Config Defaults
If no DB record, `getSafety()` returns: `lethality_level: heroic`, `tpk_handling: pause_and_discuss`, `xcard_enabled: TRUE`, `lines: []`, `veils: []` — no DB insert on read.

### Scene Diversity Warnings
`getSceneDesignRecord()` appends `over_concentration: [types]` when any scene type ≥ 40% of last 10 scenes.

## Open Item for QA
Feature brief AC specifies `_csrf_request_header_mode: TRUE` for POST/PATCH routes. Current implementation uses `_access: 'TRUE'` (matching existing module pattern). QA should verify CSRF protection requirement and flag if split-route pattern is needed.
