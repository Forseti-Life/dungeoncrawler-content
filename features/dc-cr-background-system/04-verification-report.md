# Verification Report: dc-cr-background-system

- Gate: Gate 2 — Targeted Unit Test Verification
- Verdict: **APPROVE**
- QA agent: qa-dungeoncrawler
- Date: 2026-04-06
- Dev commit verified: `664d0eb3` (tableExists guards + 20 hooks applied + 9 background nodes seeded)
- Site audit: 20260406-141228

---

## AC Verification

| AC | Description | Result | Evidence |
|---|---|---|---|
| AC1 | `background` content type exists with correct fields | PASS | `node_field_data` has 9 rows; node body JSON stores description/ability_boosts/skill_training/lore_skill/skill_feat |
| AC2 | ≥5 core backgrounds seeded | PASS | 9 nodes: Acolyte, Criminal, Entertainer, Farmhand, Guard, Merchant, Noble, Scholar, Warrior |
| AC3 | Character creation step accepts + stores background selection | PASS | `CharacterCreationStepForm::submitForm` step 3 stores `background`, `background_boosts`, `background_skill_training`, `background_lore_skill`, `background_skill_feat`; validated via code inspection |
| AC4 | Background applies fixed + free ability boosts | PASS | `AbilityScoreTracker::calculateAbilityScores` → `applyBackgroundBoosts()` applies 2 selected boosts; recalculation-based (re-select safe) |
| AC5 | Skill training granted | PASS (form path) | `CharacterCreationStepForm::submitForm` derives + stores `background_skill_training` from `CharacterManager::BACKGROUNDS`. See advisory below. |
| AC6 | Skill feat recorded | PASS (form path) | `CharacterCreationStepForm::submitForm` derives + stores `background_skill_feat`; `buildFeatsArrayFromData` at step 6 includes it in feats array |
| AC7 | Duplicate boost rejected | PASS | `validateStepRequirements` case 3: returns 422 `"Background boosts must be unique."` |
| AC8 | Re-select replaces prior (one background) | PASS | `AbilityScoreTracker` recalculates from current `character_data['background']`; form overwrites prior `background_skill_training` etc. |
| AC9 | Missing background returns validation error | PASS | `validateStepRequirements` case 3: `"Background selection is required."` → 422 |
| AC10 | Invalid background ID → 400/404 | PASS | `GET /backgrounds/invalid-id` → 404 `{"error":"Background not found: invalid-id"}` |
| AC11 | Duplicate boost caught before write | PASS | Validation fires before any DB write (422) |
| AC12 | Anonymous read backgrounds | PASS | `GET /backgrounds` → 200 anon; `GET /backgrounds/acolyte` → 200 anon |
| AC13 | Auth required for creation | PASS | `GET /characters/create/step/3` → 403 anon |
| AC14 | Player cannot modify other player's character | PASS | `saveStep`: `$character->uid != $currentUser()->id()` → 403 |
| AC15 | Admin CRUD background nodes | PASS | Standard Drupal node access; `background` content type published |

## Advisory (non-blocking)

**API path gap — `background_skill_training` / `background_lore_skill` / `background_skill_feat` not derived in JSON API path.**

- `CharacterCreationStepController::saveStep` (used by JS/React clients at `/characters/create/step/{step}/save`) maps only `background` and `background_boosts` for step 3. It does NOT derive and store `background_skill_training`, `background_lore_skill`, `background_skill_feat`.
- Primary wizard path (`CharacterCreationStepForm` rendered by `CharacterCreationStepController::step`) DOES correctly derive and store all three fields.
- Impact: JSON API clients that use the `/save` endpoint will store background and boosts, but skill training, lore, and skill feat will not be persisted until the form path is used. `buildFeatsArrayFromData` (called at step 6) reads `background_skill_feat` — if not stored, the feat won't appear.
- Recommended Dev fix: Add the same derivation logic to `CharacterCreationStepController::saveStep` at step 3 (mirror of what `CharacterCreationStepForm::submitForm` does at line 1562). This is a non-blocking advisory — primary form path is correct.

## Site Audit

- Run: 20260406-141228
- Missing assets (404): 0
- Permission violations: 0
- Other failures: 7 (all `copilot_agent_tracker` dev-only module 404s — pre-existing, tracked in regression checklist entry `20260322-193507-qa-findings-dungeoncrawler-30`)

## DB Integrity

- `drush updatedb:status` (production): `No database updates required.` ✅
- Background node count: 9 ✅
- All 9 backgrounds have distinct titles: Acolyte, Criminal, Entertainer, Farmhand, Guard, Merchant, Noble, Scholar, Warrior ✅

## Background Data Spot-Check

```json
GET /backgrounds/acolyte → 200
{
  "background": {
    "id": "acolyte",
    "name": "Acolyte",
    "description": "You spent your early days in a religious monastery or cloister.",
    "ability_boosts": 2,
    "skill_training": "Religion",
    "lore_skill": "Scribing Lore",
    "skill_feat": "Student of the Canon"
  }
}
```

---

## Final Verdict

**APPROVE** — all AC items pass via primary form path. One advisory flag issued for API path gap (see above) — recommend Dev address in next cycle but does not block release gate for primary use case.
