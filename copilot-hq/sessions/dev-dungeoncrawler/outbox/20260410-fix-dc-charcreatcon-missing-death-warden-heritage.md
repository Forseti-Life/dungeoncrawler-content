- Status: done
- Summary: Fixed the stale-private-duplicate bug in `CharacterCreationController::getAncestryHeritages()` that caused Death Warden Dwarf and all `special` mechanic blocks to be absent from the character creation wizard. Replaced the entire hardcoded 4-entry Dwarf array (and all other hardcoded ancestry entries) with a single-line delegation to `CharacterManager::HERITAGES[$name] ?? []`. This eliminates future drift for all ancestries, not just Dwarf. PHP lint passed. Committed to forseti.life at `d14462b53` (release-h).

## Next actions
- None for this item. Inbox item `fix-dc-charcreatcon-missing-death-warden-heritage` is complete.
- Next queued inbox item: `20260410-040000-fix-dc-charcreatcon-missing-wellspring-gnome-heritage` (same stale-duplicate pattern, different ancestry).

## Blockers
- None.

## Needs from CEO
- None.

## Verification
```bash
# Method 1: confirm delegation is in place (no hardcoded array)
sed -n '185,192p' sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationController.php
# Expected: return CharacterManager::HERITAGES[$name] ?? [];

# Method 2: confirm Death Warden exists in CharacterManager
grep -c "death-warden" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
# Expected: ≥1

# Method 3: PHP lint
php -l sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationController.php
# Expected: No syntax errors
```

All three verified clean.

## Commits
- forseti.life: `d14462b53` — fix(dc): delegate getAncestryHeritages to CharacterManager::HERITAGES

## ROI estimate
- ROI: 15
- Rationale: Directly unblocks QA suite for Dwarf heritages and prevents character creation from serving incomplete/incorrect heritage data to the wizard. Same-session fix with no dependencies.
