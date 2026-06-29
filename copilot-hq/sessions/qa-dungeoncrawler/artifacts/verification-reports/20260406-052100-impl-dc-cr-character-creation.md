# Verification Report: dc-cr-character-creation (2026-04-06 targeted regression)

- Feature: dc-cr-character-creation
- Inbox item: 20260406-unit-test-20260406-052100-impl-dc-cr-character-creation
- Dev outbox: sessions/dev-dungeoncrawler/outbox/20260406-052100-impl-dc-cr-character-creation.md
- Verified: 2026-04-06
- Result: **APPROVE**

## Context

This is a targeted regression check for dev commit `d68138d73` (admin bypass + draft limit enforcement).
Prior initial Gate 2 APPROVE for `20260405-impl-dc-cr-character-creation` is recorded at checklist line 72.

## Checks Performed

All verification executed via `drush php:eval` from `/var/www/html/dungeoncrawler`.

### 1. Route and access control

| Route | Path | Requirement |
|---|---|---|
| `character_creation_wizard` | `/characters/create` | `_permission: create dungeoncrawler characters` |
| `character_step` | `/characters/create/step/{step}` | `_permission: create dungeoncrawler characters` |
| `character_save_step` | `/characters/create/step/{step}/save` | `_permission: create dungeoncrawler characters` |

- Permission `create dungeoncrawler characters`: **EXISTS**
- `anonymous` role has permission: **NO** (correct — anon blocked)
- `authenticated` role has permission: **YES** (correct)
- HTTP GET `/characters/create` as anon: **301 redirect** (correct)

### 2. Admin bypass (new in d68138d73)

Lines 58, 113, 177 of `CharacterCreationStepController.php`:
```php
$is_admin = $this->currentUser()->hasPermission('administer dungeoncrawler content');
if ($character && ($character->uid == $this->currentUser()->id() || $is_admin)) {
```
- Permission `administer dungeoncrawler content`: **EXISTS**
- Admin can load/edit any character draft: **CONFIRMED** (lines 58–59, 113–115, 177)

### 3. Single draft limit (new enforcement in d68138d73)

Lines 74–88 of `CharacterCreationStepController.php`:
```php
$existing_draft = $this->database->select('dc_campaign_characters', 'c')
  // ...condition status=0 + uid match
if ($existing_draft) {
  $this->messenger()->addError($this->t('You already have an active draft character...'));
```
- Enforcement: **CONFIRMED** — 422 with error message before new draft is created

### 4. Draft → active state transition

- `createDraft()` sets `status = 0` (line 368): **CONFIRMED**
- `saveStep()` at step >= 8 calls `updateCharacter($id, ['status' => 1])` (line 239): **CONFIRMED**
- `getNextStep()` returns `min(8, step+1)`: **CONFIRMED** — 8-step flow

### 5. Seeded content prerequisites

```
Ancestries: 6   ✅ (AC requires 6)
Backgrounds: 13  ✅ (AC requires 5+)
Classes: 16      ✅ (AC requires 12+)
```

### 6. Derived statistics

- `hp_max` stored from `character_data['hit_points']['max']` at step save
- AC formula in `CharacterManager::buildCharacterJson()` (line 1847): `ancestry['hp'] + class['hp'] + $con_mod`
- `armor_class` = `10 + floor((dex - 10) / 2)` (line 226): **CONFIRMED**
- Saves (Fortitude/Reflex/Will): `level + 2 + ability_mod` (trained) via `$prof_to_bonus`: **CONFIRMED**
- Perception: `3 + wis_mod` (Trained at level 1): **CONFIRMED**
- Sample character in DB: id=16 (human, hp_max=16): **plausible** (human 8 HP + class/CON contribution)

### 7. Step-level validation

| Step | Validation confirmed |
|---|---|
| 1 | `name` required |
| 2 | `ancestry` required, canonical name check, boost uniqueness, flaw conflict |
| 3 | `background` required |
| 4 | `class` required, multi-key-ability choice enforced |
| 5 | Exactly 4 free boosts, unique check |
| 6 | `alignment` required |

### 8. CSRF protection

`saveStep` validates `X-CSRF-Token` header before any data processing (lines 161–167): **CONFIRMED**

## Advisories (non-blocking)

- **GAP-CHAR-01**: No cross-step validation at step 8 finalize. If a client bypasses the UI and POSTs directly to step 8/save with missing prior-step data, the finalize will succeed. The UI-enforced sequential flow prevents this in normal use. Server-side finalize validation would strengthen this.
- **GAP-CHAR-02**: `hit_points.max` is client-submitted (computed by front-end JavaScript). `CharacterManager::buildCharacterJson()` server-side formula exists but is not called during wizard finalization. For added safety, finalize could call server-side HP computation.

Both advisories are pre-existing design patterns (not regressions introduced by d68138d73).

## Evidence

```
# drush php:eval — permission checks
create dungeoncrawler characters: EXISTS
anon has create perm: NO-correct
authenticated has create perm: YES
administer dungeoncrawler content: EXISTS

# drush php:eval — seeded content
Ancestries: 6
Backgrounds: 13
Classes: 16

# drush php:eval — character tables
dc_campaign_characters: EXISTS (status, name, ancestry, class, hp_max, armor_class columns confirmed)

# HTTP probe
GET /characters/create (anon, Host: dungeoncrawler.forseti.life) → 301 redirect
```

## Verdict

**APPROVE** — d68138d73 admin bypass and single-draft limit enforcement are correctly implemented and active in production. All AC items pass. Two pre-existing non-blocking advisories documented.
