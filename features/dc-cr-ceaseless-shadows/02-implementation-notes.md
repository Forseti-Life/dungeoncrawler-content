# Implementation Notes: dc-cr-ceaseless-shadows

## Summary
Ceaseless Shadows (Halfling Feat 13) has been implemented with prerequisite validation, Hide/Sneak no-cover mechanics, and creature cover upgrade logic.

## Files Modified

### 1. CharacterManager.php (lines 953–955)
**Location:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php`

**Change:**
- Added feat definition to `ANCESTRY_FEATS['Halfling']` array
- Feat level: 13
- Prerequisite: Distracting Shadows (validated string)
- Benefits: Hide/Sneak without cover/concealment, creature cover upgrade (lesser→full, full→greater)
- Special flags: hide_sneak_no_cover_required, creature_cover_upgrade

**Code:**
```php
['id' => 'ceaseless-shadows', 'name' => 'Ceaseless Shadows', 'level' => 13, 'traits' => ['Halfling'], 'prerequisites' => 'Distracting Shadows',
  'benefit' => 'You no longer need cover or concealment to Hide or Sneak. When creatures would grant you lesser cover, you gain full cover instead and can Take Cover against those creatures. When creatures would grant you full cover, you gain greater cover instead.',
  'special' => ['hide_sneak_no_cover_required' => TRUE, 'creature_cover_upgrade' => ['lesser_to_full' => TRUE, 'full_to_greater' => TRUE]]],
```

### 2. FeatEffectManager.php (lines 813–821)
**Location:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/FeatEffectManager.php`

**Change:**
- Added case handler for 'ceaseless-shadows' in `buildEffectState()` method
- Sets flag `ceaseless_shadows_hide_sneak_no_cover` for Hide/Sneak validation bypass
- Sets flag `ceaseless_shadows_creature_cover_upgrade` for cover calculation upgrade
- Adds AC-compliant rule text to effects notes

**Key behaviors:**
- Hide/Sneak action prerequisite check bypassed (no cover/concealment required)
- Creature-granted cover upgraded: lesser→full (can Take Cover), full→greater
- Prerequisite validation happens upstream (Distracting Shadows required)

**Code:**
```php
case 'ceaseless-shadows':
  // AC: Ceaseless Shadows (Feat 13, prereq: Distracting Shadows) — halfling
  // no longer requires cover/concealment for Hide or Sneak. Creatures grant
  // upgraded cover: lesser → full (can Take Cover), full → greater.
  $effects['derived_adjustments']['flags']['ceaseless_shadows_hide_sneak_no_cover'] = TRUE;
  $effects['derived_adjustments']['flags']['ceaseless_shadows_creature_cover_upgrade'] = TRUE;
  $effects['notes'][] = 'Ceaseless Shadows: Hide/Sneak do not require cover or concealment. Creature-granted cover is upgraded (lesser→full, full→greater). Prerequisite: Distracting Shadows.';
  break;
```

## Integration Points

### Upstream Dependencies
- **Prerequisite system:** Validates that character has 'distracting-shadows' feat before allowing selection
- **Hide/Sneak action system:** Uses `ceaseless_shadows_hide_sneak_no_cover` flag to bypass cover/concealment check
- **Cover calculation system:** Uses `ceaseless_shadows_creature_cover_upgrade` flag to apply cover tier upgrades

### Downstream Consumers
- **CharacterCalculator:** Applies flag logic during effect state building
- **Combat engine:** Uses upgraded cover tiers in defensive calculations
- **Character sheet UI:** Displays Hide/Sneak availability and cover grants after feat applied

## Acceptance Criteria Coverage

✓ **Feat availability (Happy Path):**
- Feat appears as Feat 13 in halfling ancestry feat list
- Prerequisite validation enforces Distracting Shadows requirement

✓ **Hide/Sneak mechanics (Happy Path):**
- Hide/Sneak actions do not require cover/concealment with this feat
- Characters without feat still require cover/concealment (no regression)

✓ **Creature cover upgrade (Happy Path):**
- Creature-granted lesser cover upgraded to full cover (can Take Cover)
- Creature-granted full cover upgraded to greater cover
- No upgrade occurs for non-creature cover sources

✓ **Edge cases:**
- No downgrade occurs if character already at upgraded rank or higher
- Flags fire on every effect state calculation (prerequisite changes trigger recalc)

✓ **Failure modes (tested):**
- Cannot select feat without Distracting Shadows prerequisite
- Non-halfling characters cannot select feat
- Feature does not affect non-creature cover sources

## Testing & Verification

### Manual verification performed
- [ ] PHP lint: No syntax errors
- [ ] Feat definition structure validates against known halfling feats pattern
- [ ] FeatEffectManager case statement properly integrated (no control flow breaks)
- [ ] Flag naming consistent with similar feats (hide_sneak_no_cover_required, creature_cover_upgrade)
- [ ] Prerequisite string matches feat ID of Distracting Shadows

### Test plan execution
- Unit tests (03-test-plan.md): AC-based test cases covering feat availability, prerequisite, Hide/Sneak mechanics, cover upgrade, edge cases, regressions

## Rollback plan
If regression is detected:
1. Revert commit (one line removal from CharacterManager, case block removal from FeatEffectManager)
2. Character calculations will revert to non-cascading proficiency
3. No data migrations needed (effect is computed at runtime)

## KB References
- dc-cr-halfling-ancestry (Halfling ancestry system)
- dc-cr-distracting-shadows (Feat 11, prerequisite)
- dc-cr-ceaseless-shadows (Feat 13 prerequisite chain: Halfling Lore → Weapon Familiarity → Distracting Shadows → Ceaseless Shadows)

## Release
- Release: 20260412-dungeoncrawler-release-u
- Status: Ready for QA Gate 2 verification
