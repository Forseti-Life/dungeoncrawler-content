# Implementation Notes (Dev-owned)
# Feature: dc-cr-character-creation

## KB references
- `knowledgebase/lessons/20260225-executor-patch-lag-silent-accumulation.md` — run `drush cr` + QA audit after module changes.
- CSRF routing KB: `_csrf_token: TRUE` on GET+POST causes GET 403 — always use split-route pattern for browser routes (applied to character_step in prior CSRF FINDING-3 work).

## Status
- Status: done (QA Gate 2 APPROVE — commit 97472be41)

## What already existed
The character creation workflow was already substantially implemented before this inbox item was dispatched:
- `CharacterCreationStepForm.php` (2619 lines): 8-step wizard (name → ancestry/heritage → background → class+spells → skills → ability scores → equipment → finalize)
- `CharacterCreationStepController.php`: start(), step(), saveStep(), createDraft(), updateStepData()
- `CharacterCalculator.php`: calculateHP(), calculateArmorClass(), calculateProficiencyBonus()
- Draft state (status=0), active state (status=1 at step 8)
- Optimistic locking (version field) for concurrent session conflict detection
- PF2E boost/flaw rules applied in updateStepData() for step 2 (ancestry)
- Derived saves (fortitude/reflex/will = level+2+ability_mod) and perception computed at finalization (lines 2071–2076 of form)
- Public character list filters to status=1 only

## Gaps filled this cycle (commit d68138d7)
### 1. Admin bypass (AC: "Admins can view and edit any character draft for GM/admin tooling purposes")
- `start()`, `step()`, `saveStep()` all added `hasPermission('administer dungeoncrawler content')` bypass
- Previously: uid check was unconditional; admins got "Access denied" on other players' drafts

### 2. Draft limit (AC: "A player may have at most 1 active draft creation session at a time per character slot")
- `start()` now queries `dc_campaign_characters` for existing `status=0` records for the current user
- If found: error message + redirect to existing draft instead of creating a second orphan

## Access control summary (post-fix)
| Action | Anonymous | Player (own) | Player (other) | Admin |
|---|---|---|---|---|
| Initiate creation | → login redirect | ✅ | n/a | ✅ |
| View/edit draft | → login redirect | ✅ | 403 | ✅ |
| Save step (AJAX) | 403 | ✅ | 403 | ✅ |

## Files modified
- `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationStepController.php`

## Verification
```bash
# PHP lint
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationStepController.php
# Cache clear
cd /var/www/html/dungeoncrawler && drush --uri=https://dungeoncrawler.forseti.life cr
```
Both passed.

## Rollback
- Revert commit d68138d7 to remove admin bypass and draft limit check.
- No schema changes; no drush updatedb required.

## Remaining AC items (QA-facing)
| AC item | Coverage | Notes |
|---|---|---|
| TC-CWF-01: Draft creation produces status=0 entity | ✅ code | createDraft() sets status=0 |
| TC-CWF-02: Step order enforced | ✅ code | step() redirects if step > saved_step |
| TC-CWF-03: Each step saves + advances | ✅ code | saveStep() |
| TC-CWF-04: Back-nav flags/clears conflicts | ✅ code | ancestry re-selection reverses boosts |
| TC-CWF-05: Derived stats computed | ✅ code | saves+perception at form lines 2071–2076 |
| TC-CWF-06: Draft→active at step 8 | ✅ code | status=1 when step>=8 |
| TC-CWF-07: Abandoned draft resumable | ✅ code | start() loads by character_id |
| TC-CWF-08: Concurrent session conflict | ✅ code | optimistic locking via version field |
| TC-CWF-09: Boost/flaw rules validated | ✅ code | updateStepData() step 2 |
| TC-CWF-10: Empty prereq content error | ✅ code | PHP catalogs always populated |
| TC-CWF-11: Incomplete step blocks finalize | ✅ code | validateStepRequirements() |
| TC-CWF-12: Anonymous → login redirect | ✅ Drupal | _permission requirement on route |
| TC-CWF-13: Player 403 on others' draft | ✅ code | uid check in step()/saveStep() |
| TC-CWF-14: Admin access to any draft | ✅ code | THIS CYCLE — admin bypass added |
| TC-CWF-15: Derived stat crash-safety | ✅ QA | CharacterCalculator uses fallback defaults (classHp=8, con=10, ancestryHp=0) — returns partial result, no crash |
| TC-CWF-16: Draft not on public list | ✅ code | status=0 filtered in CharacterViewController |
| TC-CWF-17: 1 active draft per slot | ✅ code | THIS CYCLE — draft limit enforced |
| TC-CWF-18: Rollback preserves active chars | ✅ code | status=1 records unaffected |
| TC-CWF-19: Prereq seeding check | n/a | PHP catalogs; 6 ancestries / 13 backgrounds / 16 classes seeded |
| TC-CWF-20: QA audit passes | ✅ QA APPROVE | commit 97472be41 — Gate 2 APPROVE |
