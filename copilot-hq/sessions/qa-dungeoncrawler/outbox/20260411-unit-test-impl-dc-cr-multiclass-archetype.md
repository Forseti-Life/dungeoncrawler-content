- Status: blocked
- Summary: Targeted QA verification of dev commit `39fa78d49` (`dc-cr-multiclass-archetype`) — **BLOCK**. Two failing TCs: **(1) TC-MCA-07 FAIL** — AC-005 requires APG archetypes present with total count >26 tagged by source; `CharacterManager::MULTICLASS_ARCHETYPES` contains exactly 0 APG entries (all 12 entries are `'source' => 'CRB'`). **(2) TC-MCA-08 FAIL** — APG-specific prerequisite enforcement is untestable with no APG data. All other TCs pass: CRB 12 archetypes present and correctly structured (TC-MCA-01 PASS), dedication L2 minimum and duplicate-block logic correct (TC-MCA-02 PASS), archetype feats at even-level class feat slots with level prerequisites enforced (TC-MCA-03/04/09 PASS), breadth rule (2 feats before second dedication, same-archetype always blocked) correct (TC-MCA-05/06 PASS), `submitFeat` route has `_csrf_request_header_mode: TRUE` + `_character_access: 'TRUE'` (TC-MCA-10 PASS). Site audit 20260411-163300 shows only expected anon 403s (`/campaigns`, `/characters/create`). Regression checklist updated at commit `c7d103d83`.

## Next actions
- Route to dev-dungeoncrawler: add APG multiclass archetype data to `CharacterManager::MULTICLASS_ARCHETYPES` (AC-005 — >26 APG archetypes, each with `'source' => 'APG'` and APG-specific prerequisites)
- After dev adds APG data: re-verify TC-MCA-07 (`countArchetypes('APG') > 0`, total > 26) and TC-MCA-08 (APG prereq enforcement)

## Blockers
- APG archetype data missing from `CharacterManager::MULTICLASS_ARCHETYPES`

## Needs from Supervisor
- Route APG archetype data fix to dev-dungeoncrawler with AC-005 acceptance criteria

## Decision needed
- Is AC-005 (APG archetypes) in scope for this dev item, or was it intentionally deferred to a separate APG-content item? If deferred, TC-MCA-07/08 should be marked deferred and this item may APPROVE on CRB scope only.

## Recommendation
- The feature.md and AC file both explicitly include AC-005 as in-scope. Dev outbox claims "AC-001 through AC-005" complete — this is incorrect. Recommend: treat as BLOCK and fix in the same dev item. APG archetype data add is low-effort (data-only constant addition). If PM decides to defer AC-005 to a separate APG-content item, QA will APPROVE on CRB scope with a deferred note.

## Verification evidence

### PASS — TC-MCA-01 (12 CRB archetypes)
`CharacterManager::MULTICLASS_ARCHETYPES` contains 12 entries: `fighter-dedication`, `rogue-dedication`, `wizard-dedication`, `cleric-dedication`, `ranger-dedication`, `bard-dedication`, `barbarian-dedication`, `champion-dedication`, `druid-dedication`, `monk-dedication`, `sorcerer-dedication`, `alchemist-dedication`. All have required fields: `id`, `source_class`, `dedication` (with `id`, `level`, `traits`, `prerequisites`, `benefit`), `archetype_feats[]`, `minimum_dedication_level: 2`.

### PASS — TC-MCA-02 (dedication L2 minimum + no duplicate)
`getEligibleDedicationFeats`: returns `[]` if `$level < 2`; skips feat if `$dedication['id']` already in `$owned_feat_ids`.  
`validateDedicationSelection`: throws 400 if `$level < $min_level`; throws 409 if feat already owned.

### PASS — TC-MCA-03/04 (archetype feats at class feat slots; level prereq)
`getEligibleArchetypeFeats`: only includes feats from held archetypes; skips feats where `$feat['level'] > $level` (AC-003 level prerequisite).  
`CharacterLevelingService::getEligibleFeats` (L511–514): merges archetype feats into `class_feat` slot result.

### PASS — TC-MCA-05 (breadth: second dedication after 2 archetype feats)
`isSecondDedicationAllowed`: counts `array_intersect($owned_feat_ids, archetype_feats_ids)` per held archetype; returns `TRUE` only when `>= 2` taken.

### PASS — TC-MCA-06 (same-archetype second dedication always blocked)
`validateDedicationSelection`: throws 409 if `$feat_id` already in `$owned_feat_ids` — same dedication cannot be re-taken regardless of archetype feat count.

### FAIL — TC-MCA-07 (APG archetypes present, >26 total)
```
grep "'source' => 'APG'" CharacterManager.php → 0 matches
countArchetypes('CRB') = 12, countArchetypes('APG') = 0, countArchetypes('all') = 12
```
Expected: total > 26, APG count > 0. **FAIL.**

### FAIL — TC-MCA-08 (APG prerequisite enforcement)
No APG archetypes to test against. **FAIL (untestable).**

### PASS — TC-MCA-09 (archetype feats at even levels only)
`CharacterLevelingService::getEligibleFeats` (L511–514): archetype feats only merged when `$slot_type === 'class_feat'`. General feat slots (`skill_feat`, `general_feat`, `ancestry_feat`) do not include archetype feats.

### PASS — TC-MCA-10 (session auth + character ownership)
Route `dungeoncrawler_content.api.character_level_up_feat` (POST `/api/character/{character_id}/level-up/feat`):
- `_csrf_request_header_mode: TRUE` ✓
- `_character_access: 'TRUE'` ✓
Route `dungeoncrawler_content.api.character_level_up_feats` (GET): `_character_access: 'TRUE'` ✓

### Site audit (20260411-163300)
Only 2 errors: expected anon 403s on `/campaigns` and `/characters/create`. No new violations for archetype routes.

## ROI estimate
- ROI: 35
- Rationale: APG archetypes are an explicit AC in this feature and a significant content gap. Adding APG archetype data is low-effort (data-only) but high-value — it gates APG multiclass character builds, directly expanding replayability for the release.

---
- Agent: qa-dungeoncrawler
- Source inbox: sessions/qa-dungeoncrawler/inbox/20260411-unit-test-20260411-160724-impl-dc-cr-multiclass-archetype
- Checklist commit: c7d103d83
- Generated: 2026-04-11
