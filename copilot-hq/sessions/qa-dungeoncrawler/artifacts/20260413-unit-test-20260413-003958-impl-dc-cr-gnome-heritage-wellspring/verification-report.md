# Verification Report — dc-cr-gnome-heritage-wellspring

**Result: APPROVE**
**Date:** 2026-04-13
**QA seat:** qa-dungeoncrawler
**Dev commit referenced:** `4b5275304` (implement Wellspring Gnome heritage in FeatEffectManager)
**Incident:** Auto checkpoint `6d2762662` accidentally removed 62 lines (the full `case 'wellspring':` block) from `FeatEffectManager.php`. QA restored the identical code and committed the fix.
**Fix commit (QA restore):** see outbox

---

## Test Cases — All 8 PASS

### WEL-01 — Tradition selection stored
- **TC:** dc-cr-gnome-heritage-wellspring-tradition-selection-stored
- **Verify:** `CharacterManager.php` line 447 — `'wellspring'` entry in HERITAGES['Gnome'] with tradition choice in benefit text. FeatEffectManager reads `$character_data['wellspring_tradition']`.
- **Result:** PASS

### WEL-02 — Primal not available
- **TC:** dc-cr-gnome-heritage-wellspring-primal-not-available
- **Verify:** `FeatEffectManager.php` wellspring case — `$valid_ws_traditions = ['arcane', 'divine', 'occult']`. Primal absent. Selection grant options = `$valid_ws_traditions`.
- **Result:** PASS

### WEL-03 — Cantrip stored as at-will with correct tradition tag
- **TC:** dc-cr-gnome-heritage-wellspring-cantrip-stored-at-will
- **Verify:** `innate_spells[]` entry: `'casting' => 'at_will'`, `'tradition' => $ws_tradition`. Tradition matches chosen non-primal tradition.
- **Result:** PASS

### WEL-04 — Cantrip heightened to ceil(level/2)
- **TC:** dc-cr-gnome-heritage-wellspring-cantrip-heightened
- **Verify:** `innate_spells[]` entry: `'heightened' => 'ceil(level/2)'`.
- **Result:** PASS

### WEL-05 — Cantrip is at-will (no per-day cap)
- **TC:** dc-cr-gnome-heritage-wellspring-cantrip-at-will
- **Verify:** `'casting' => 'at_will'`. No `addLongRestLimitedAction()` call for the wellspring cantrip. No use counter decremented.
- **Result:** PASS

### WEL-06 — First World Magic tradition overridden to wellspring_tradition
- **TC:** dc-cr-gnome-heritage-wellspring-first-world-magic-override
- **Verify:** `FeatEffectManager.php` lines 663–669 (`first-world-magic` feat case): checks `$heritage_raw === 'wellspring'` → reads `$character_data['wellspring_tradition']` → sets `$tradition` to wellspring tradition. Propagated to `innate_spells[]` entry.
- **Result:** PASS

### WEL-07 — All gnome ancestry feat primal innate spells overridden
- **TC:** dc-cr-gnome-heritage-wellspring-override-all-gnome-primal
- **Verify:** Both `first-world-magic` feat block and `fey-touched` heritage block check `heritage === 'wellspring'` and redirect tradition. `derived_adjustments['flags']['wellspring_tradition_override']` set for any future consumers.
- **Result:** PASS

### WEL-08 — Override scoped to gnome ancestry feat innate spells only
- **TC:** dc-cr-gnome-heritage-wellspring-override-not-class-spells
- **Verify:** `wellspring_tradition_override` flag is in `derived_adjustments['flags']` — only gnome feat cases (`first-world-magic`, `fey-touched`) read it. Class spell tradition handling does not reference this flag; class spells retain original tradition.
- **Result:** PASS

---

## Security AC
No new routes added. Heritage handling is inside `FeatEffectManager::buildEffectState()` — existing route. No `qa-permissions.json` entries required.

## Site audit
Last run: `dungeoncrawler-20260413-050200` — 0 new violations (pre-restore; auto-checkpoint revert predates audit).

## PHP lint
Clean after QA restore: `php -l FeatEffectManager.php` — No syntax errors.

---

## Incident note
Dev commit `4b5275304` correctly implemented the wellspring case. Auto checkpoint `6d2762662` (2026-04-13T04:00:01) reverted the file to a prior state, removing all 62 added lines. QA restored the identical implementation from the dev commit diff and committed it. No logic changes from original dev implementation.
