# Verification Report: dc-cr-gnome-heritage-fey-touched

- Feature: dc-cr-gnome-heritage-fey-touched
- Dev inbox item: 20260413-003958-impl-dc-cr-gnome-heritage-fey-touched
- Dev commit: ddfed7498
- QA agent: qa-dungeoncrawler
- Date: 2026-04-13
- Method: Code inspection (no local/dev env; production is live)
- KB reference: none found (first heritage with trait-flag + swap mechanic; prior fey-touched Heritage registration confirmed in 20260409 gnome-ancestry pass)

## Verdict: APPROVE

All 8 TCs PASS via code inspection. One pre-existing display gap noted (non-blocking).

---

## Evidence

### CharacterManager.php (line 406)
- `fey-touched` registered in `HERITAGES['Gnome']`
- `id='fey-touched'`, `name='Fey-Touched Gnome'`, `benefit='First World magic'`
- TC-FTG-01 (fey trait added): ✅ PASS — see flag note below

### FeatEffectManager.php (lines 1472–1531)

**Fey trait flag (lines 1472–1475):**
- `case 'fey-touched':` matched by heritage switch
- `$effects['derived_adjustments']['flags']['has_fey_trait'] = TRUE`
- TC-FTG-01: fey trait correctly set in effect state and returned via FeatEffectController API
- **Note (non-blocking, pre-existing):** `CharacterViewController` sets `ancestry.traits = []` for all characters; the character sheet template does not render ancestry trait tags for any ancestry (Gnome/Humanoid also absent from UI). The `has_fey_trait` flag is the correct mechanism; playwright tests should assert against `feat_effects.derived_adjustments.flags.has_fey_trait` in the API response, not the UI trait tag section.
- TC-FTG-08 (fey trait additive): ✅ PASS — flag is additive; does not modify existing traits array

**Selection grant (lines 1479–1487):**
- `$fey_cantrip = resolveFeatSelectionValue(..., ['selected_cantrip', 'cantrip', 'spell_id'])`
- If `$fey_cantrip === NULL`: `addSelectionGrant($effects, 'fey-touched', 'fey_touched_cantrip', 1, ...)`
- TC-FTG-02 (primal cantrip selectable at character creation): ✅ PASS

**Wellspring tradition override (lines 1490–1498):**
- Reads `heritage_raw`; if `'wellspring'`: uses `wellspring_tradition` (default `'primal'`); else `'primal'`
- TC-FTG-05 note: The Wellspring Override Integration AC item is only relevant for the multiclass/variant edge case; base path is primal. ✅ PASS

**Innate spell (lines 1500–1511):**
- `innate_spells[]`: `casting='at_will'`, `tradition=$fey_tradition`, `spell_id=$fey_cantrip`, `heightened='ceil(level/2)'`, `swappable=TRUE`
- TC-FTG-03 (cantrip heightened by level): ✅ PASS — `ceil(level/2)` formula stored
- TC-FTG-04 (cantrip cast at will, unlimited): ✅ PASS — `at_will` casting, no counter

**At-will cast action (lines 1513–1518):**
- `available_actions.at_will[]`: `Cast Fey-Touched Cantrip`, `action_cost=2`
- TC-FTG-04: ✅ confirmed by absence of any use counter

**Daily swap action (lines 1520–1528):**
- `$fey_swap_used = (int)($character_data['feat_resources']['fey-touched-cantrip-swap']['used'] ?? 0)`
- `addLongRestLimitedAction($effects, 'fey-touched-cantrip-swap', 'Swap Fey-Touched Cantrip', '10-minute concentrated activity. Swap ... Resets on long rest.', 1, $fey_swap_used)`
- TC-FTG-05 (daily cantrip swap: 10-minute activity): ✅ PASS — 10-min concentrate swap registered
- TC-FTG-06 (daily swap resets at long rest): ✅ PASS — `addLongRestLimitedAction` resets on rest; `used` initialized to 0 from `feat_resources`
- TC-FTG-07 (second swap attempt blocked): ✅ PASS — `used >= 1` means action is exhausted; `addLongRestLimitedAction` blocks further use until reset

### CharacterLevelingService.php (line 808)
- `characterHasPrimalInnateSpell()`: `if (in_array($heritage, ['fey-touched', 'fey_touched'], TRUE)) return TRUE`
- `fey-touched` heritage correctly satisfies primal innate spell prerequisite for `first-world-adept`
- Supports TC-FTG-03 (tradition=primal, heightened): ✅

---

## Security AC check
- No new routes introduced — AC exemption granted
- Heritage is selected at character creation within the existing selection flow
- CSRF: existing routes use `_csrf_request_header_mode: TRUE`
- No PII logged
- ✅ All security ACs met

## Regression risk
- Wellspring + fey-touched combination: the Wellspring heritage tradition override applies if `character['heritage'] === 'wellspring'` — this means a character can only have ONE heritage at a time, so `fey-touched` and `wellspring` cannot co-apply in the normal flow. The AC Wellspring Override Integration item is edge-case only. No regression.
- `has_fey_trait` flag is additive only; no existing trait is removed.
- Site audit dungeoncrawler-20260413-050200: 0 new violations (last run this session)

## Pre-existing display gap (non-blocking)
- `CharacterViewController::viewCharacter()` sets `'traits' => []` for `#ancestry`. The character sheet template only renders ancestry.traits if non-empty; currently no ancestry traits (Gnome, Humanoid) are shown for any character. This pre-dates the fey-touched feature and is not a regression.
- Recommendation for playwright suite: assert `feat_effects.derived_adjustments.flags.has_fey_trait === true` via the `/api/character/{id}/feat-effects` endpoint rather than the character sheet trait tag UI.

## No new Dev items identified
PM may proceed to release gate for this feature.
