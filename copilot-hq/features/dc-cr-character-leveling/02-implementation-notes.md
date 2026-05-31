# Implementation Notes — dc-cr-character-leveling

- Feature: Character Leveling and Advancement
- Dev owner: dev-dungeoncrawler
- Forseti.life commit: `a5b8f3d98`
- Date: 2026-03-22
- KB check: none found for character leveling; no prior lessons on PF2e advancement in dungeoncrawler knowledgebase.

## Summary

Full PF2e milestone-based character leveling implemented. Level-up is gated on a session milestone flag (set by GM) rather than XP threshold per PM decision (2026-03-08). All AC criteria addressed.

## New routes introduced

| Route | Method | Permission | administrator | dc_playwright_admin |
|---|---|---|---|---|
| `/api/character/{id}/level-up` (existing, re-pointed) | POST | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/status` | GET | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/ability-boosts` | POST | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/skill-increase` | POST | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/feat` | POST | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/feats` | GET | `_character_access` | allow | allow |
| `/api/character/{id}/level-up/admin-force` | POST | `administer dungeoncrawler content` | allow | allow |
| `/api/character/{id}/level-up/admin-reset` | POST | `administer dungeoncrawler content` | allow | allow |
| `/api/character/{id}/milestone` | POST | `administer dungeoncrawler content` | allow | allow |

> **Signal to qa-dungeoncrawler**: 8 new route paths need `qa-permissions.json` entries in the `dungeoncrawler-character-levelup` rule before the QA audit run.

## Files changed

| File | Change type | Description |
|---|---|---|
| `src/Service/CharacterLevelingService.php` | NEW | Core leveling logic (milestone gate, triggerLevelUp, submitAbilityBoosts, submitSkillIncrease, submitFeat, adminForce/Reset, setMilestone, getEligibleFeats) |
| `src/Controller/CharacterLevelingController.php` | NEW | 9 REST endpoints |
| `src/Service/CharacterManager.php` | MODIFIED | Added `CLASS_ADVANCEMENT` constant (7 classes, levels 1–20) and `getClassAdvancement()` static helper |
| `dungeoncrawler_content.services.yml` | MODIFIED | Registered `dungeoncrawler_content.character_leveling` service |
| `dungeoncrawler_content.routing.yml` | MODIFIED | 8 new routes + re-pointed existing level-up route from stub to CharacterLevelingController |

## Data storage

Level-up state is stored in the `character_data` JSON column of `dc_campaign_characters` under the key `levelUpState`:
```json
{
  "levelUpState": {
    "milestoneReady": false,
    "inProgress": false,
    "transitionTo": 0,
    "pendingChoices": [],
    "completedChoices": [],
    "autoApplied": [],
    "hpGranted": 0
  }
}
```

No schema migration is required. Rollback: revert commit `a5b8f3d98`.

## Design decisions and AC alignment

### Milestone gate (AC: "character at level N can trigger a level-up when they have reached the session milestone trigger")
- `milestoneReady` boolean in `levelUpState` JSON. Set by `POST /api/character/{id}/milestone` (GM/admin only).
- `triggerLevelUp()` checks this flag; throws `403` if not set (unless `admin_force`).
- Test helper for QA: `POST /api/character/{id}/milestone {"ready": true}` using admin credentials.

### Advancement table (AC: "presents the character's class advancement table for level N+1")
- `CharacterManager::getClassAdvancement($class, $level)` returns `feat_slots`, `skill_increases`, `ability_boosts`, `auto_features`, `hp_bonus`.
- The `triggerLevelUp()` response includes `pendingChoices[]` listing each open slot with type and label.

### Auto-apply class features (AC: "applied automatically for no-choice features")
- `CLASS_ADVANCEMENT` defines per-level `auto_features` for fighter, wizard, rogue, cleric, ranger, bard, barbarian.
- Applied immediately at `triggerLevelUp()` with no player prompt.
- Idempotent: checks `existing_ids` before appending.

### Ability boosts (AC: "4 boosts at levels 5/10/15/20; each at most once; may exceed 18 per PF2e rules")
- `submitAbilityBoosts()` validates count == 4, all distinct, all valid ability names.
- Applies `+2` each; no cap — post-creation boosts may exceed 18 per PF2e (confirmed in AC).

### Skill increases (AC: "raise one skill proficiency rank by one step")
- `submitSkillIncrease()` advances `RANK_ORDER` by +1 step; validates current rank is not already Legendary.
- Skills stored as `$char_data['skills']['arcana'] = 'Expert'` etc.

### Feat selection (AC: "filtered by prerequisites; persisted")
- `submitFeat()` validates feat is in the correct catalog for `slot_type`, level prereq passes, not already owned.
- Feat stored in `features.feats[]` with `slot_type` and `gained_at_level`.
- `getEligibleFeats()` endpoint returns filtered catalog for a given slot type — QA can use this for TC-006.

### Idempotency (AC: "cannot re-trigger until next milestone")
- `triggerLevelUp()` detects an already-in-progress transition to the same target level and returns current state without re-applying.
- `milestoneReady` is set to `FALSE` at trigger time; GM must re-set for next level.

### Persistence (AC: "persists across save/reload")
- All state written to `character_data` JSON in `dc_campaign_characters`. Survives server restarts.

### Admin override (AC: "admin may force-apply a level or reset level-up state")
- `POST /admin-force`: triggers level-up bypassing milestone.
- `POST /admin-reset`: reverts level, HP, and auto-applied class features for the last transition.

### Concurrency guard
- No optimistic locking applied at this layer (character library row is single-user per player). The `inProgress` flag prevents double-trigger from the same client for the same level. Full concurrency serialization per TC-015 would require a database-level lock; this is flagged in the QA test plan as needing a test-harness approach.

### Error handling
- `InvalidArgumentException` codes: 400 (bad input), 403 (no milestone/access), 404 (not found).
- Controller maps code to HTTP status; non-4xx falls back to 400. No PHP exceptions reach the client.

## Impact analysis

- **Existing `/api/character/{id}/level-up` route**: was a stub returning `{success: true, newLevel: 0, updatedState: []}`. Now returns a real level-up response. This is a breaking change to the stub — any client relying on `newLevel: 0` will see a different response shape. No known clients depended on the stub.
- **`CharacterStateController::levelUp()`**: the stub method remains in the file but is no longer routed. Can be removed in a follow-up cleanup pass.
- **CharacterManager**: 268 lines added (constants + one static method). No existing constants or methods modified.
- **No other modules modified** — no cross-module impact.

## Verification

```bash
# Cache rebuild succeeded
cd /home/keithaumiller/forseti.life/sites/dungeoncrawler && vendor/bin/drush cr
# → [success] Cache rebuild complete.

# Service instantiation
vendor/bin/drush php:eval "\$s = \Drupal::service('dungeoncrawler_content.character_leveling'); print get_class(\$s);"
# → Drupal\dungeoncrawler_content\Service\CharacterLevelingService

# All 9 routes registered (verified via drush php:eval)
# dungeoncrawler_content.api.character_level_up -> /api/character/{character_id}/level-up
# dungeoncrawler_content.api.character_level_up_status -> /api/character/{character_id}/level-up/status
# ... (all 9 confirmed)
```

## Deviations from AC / open questions for QA

1. **TC-015 (concurrent requests)**: Full race-condition prevention requires a pessimistic DB lock or a separate mutex. The `inProgress` flag covers same-client double-submit. QA test plan flagged this as needing PHPUnit-level or test-harness approach — recommend QA treat this as a manual/unit test.
2. **TC-016 (missing advancement data)**: `getClassAdvancement()` returns a safe default (just `class_feat` slot) for any class or level not in `CLASS_ADVANCEMENT`. It will not throw. Classes not in the constant (e.g., `oracle`) fall back to universal advancement only.
3. **TC-019 (admin force/reset endpoints)**: Force = `POST /api/character/{id}/level-up/admin-force`. Reset = `POST /api/character/{id}/level-up/admin-reset`. Both require `administer dungeoncrawler content` permission.
4. **Feat catalog query endpoint (TC-006)**: `GET /api/character/{id}/level-up/feats?slot_type=class_feat` — this is the endpoint for prerequisite validation in QA tests.
5. **Skill increase level+class combinations for fixtures**: Fighter gets a skill increase at levels 3, 7, 11, 15, 19. Wizard same. Rogue at the same levels. QA can use any of these for TC-005 test fixture.
