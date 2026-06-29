# Outbox: Swashbuckler Class (APG) Implementation

- Status: done
- Summary: Swashbuckler class was already implemented in `CLASSES['swashbuckler']` (L2111) with full Panache mechanics (binary state, speed bonus scaling L1–L19, circumstance bonus, no-attack-after-finisher rule), all 5 Styles (Battledancer/Braggart/Fencer/Gymnast/Wit) with panache gain triggers, Precise Strike (flat precision for normal Strikes, dice for Finishers, scaling L1–L17), Confident Finisher with failure effect, Opportune Riposte (L3), and Exemplary Finisher (L9) with style-specific bonus effects. `CLASS_ADVANCEMENT['swashbuckler']` (L7356) had complete L1–L19 coverage. However, it contained a **duplicate `11 =>` PHP array key** (lines 7395 and 7401), which silently discards the first key — causing Perception Master and Weapon Mastery to be lost at L11 (only Vivacious Speed +20 was active). Fixed by merging both L11 entries into one. PHP lint clean. `dc_requirements` has 37 swashbuckler rows (> 0). Site returns 200. feature.md updated to done.

## Commits
- `2a8d950ea` (forseti.life) — `fix(dungeoncrawler): merge duplicate L11 key in swashbuckler CLASS_ADVANCEMENT`

## Bug fixed
- Duplicate PHP array key `11 =>` in `CLASS_ADVANCEMENT['swashbuckler']` silently discarded Perception Master and Weapon Mastery auto_features at L11.
- Resolution: merged all three L11 features (Perception Master, Weapon Mastery, Vivacious Speed +20) into a single `11 => ['auto_features' => [...]]` entry.

## Verification
- `php -l CharacterManager.php` — No syntax errors ✓
- `CLASSES['swashbuckler']`: Panache, 5 styles, Precise Strike scaling, Confident Finisher, Opportune Riposte, Exemplary Finisher ✓
- `CLASS_ADVANCEMENT['swashbuckler']`: L1–L19 single-key entries (duplicate fixed) ✓
- `dc_requirements` swashbuckler rows: 37 (> 0) ✓
- Site: `https://dungeoncrawler.forseti.life` → HTTP 200 ✓
- Feature `dc-apg-class-swashbuckler` marked Status: done ✓

## Rollback
- `git revert 2a8d950ea` restores duplicate L11 key (re-introduces the bug; not recommended)
- Preferred rollback: revert only the merge by re-splitting into two `11 =>` entries (though this restores the silent data loss)

## Next actions
- Awaiting next inbox item dispatch

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 20
- Rationale: Completes Swashbuckler for release-g and fixes a silent data loss bug where two L11 proficiency upgrades were never applied at level-up. Unblocks QA gate for this class.
