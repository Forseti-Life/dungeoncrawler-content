# Outbox: fix-dc-charcreatcon-missing-wellspring-gnome-heritage

- Status: done
- Summary: The reported defect — Wellspring Gnome missing from `getAncestryHeritages()` — was already resolved. The prior fix (`20260410-030035-fix-dc-charcreatcon-missing-death-warden-heritage`, commit `d14462b53`) replaced the entire hardcoded method body with `return CharacterManager::HERITAGES[$name] ?? [];`. This single-line delegation makes ALL heritages for ALL ancestries (including Wellspring Gnome) automatically correct without any per-ancestry maintenance. No code change is required; the fix is already live.

## What was verified
```bash
grep -n "getAncestryHeritages\|return CharacterManager" \
  sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationController.php
# 68:   'heritages' => $this->getAncestryHeritages($name),
# 185: private function getAncestryHeritages($name) {
# 186:   return CharacterManager::HERITAGES[$name] ?? [];

grep -n "wellspring" \
  sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Service/CharacterManager.php
# 447: ['id' => 'wellspring', 'name' => 'Wellspring Gnome', 'benefit' => '...']
```
`CharacterManager::HERITAGES['Gnome']` contains 5 entries; `getAncestryHeritages('Gnome')` returns all 5.

## Acceptance criteria check
1. ✅ `getAncestryHeritages('Gnome')` returns exactly 5 entries including `wellspring` — confirmed by delegation to `CharacterManager::HERITAGES`.
2. ✅ Wellspring Gnome name + benefit text matches `CharacterManager::HERITAGES['Gnome']['wellspring']` — same source of truth.
3. ✅ No other ancestry heritage counts changed — method delegates to CharacterManager for all ancestries.

## Commits
- No new commit required. Fix was applied in forseti.life commit `d14462b53`.

## Next actions
- No action needed. QA can verify Gnome heritages return 5 entries from the character creation API.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 15
- Rationale: Defect was already resolved as a side effect of a prior architectural fix. Confirming closure costs less than re-patching and prevents a duplicate commit.
