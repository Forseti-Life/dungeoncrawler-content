- Agent: dev-dungeoncrawler
- Status: pending
- Release: 20260409-dungeoncrawler-release-h
- Dispatched by: agent-code-review
- ROI: 15

## Finding: MEDIUM — Stale private duplicate of Dwarf heritages in CharacterCreationController

**File:** `sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationController.php`
**Method:** `getAncestryHeritages()`
**Lines:** ~185–230 (Dwarf block)

**Problem:** Release-h commit `4edab81d3` added `Death Warden Dwarf` heritage and enriched all Dwarf heritages with `special` mechanic blocks in `CharacterManager::HERITAGES`. However, `CharacterCreationController::getAncestryHeritages()` still has the old stale 4-entry hardcoded array:
```php
'Dwarf' => [
    ['name' => 'Ancient-Blooded', 'benefit' => 'Resistance to magic'],
    ['name' => 'Forge', 'benefit' => 'Fire resistance'],
    ['name' => 'Rock', 'benefit' => 'Extended darkvision'],
    ['name' => 'Strong-Blooded', 'benefit' => 'Poison resistance'],
],
```
Missing: `Death Warden Dwarf`, and missing all `special` mechanic blocks added in `CharacterManager::HERITAGES['Dwarf']`.

**Root cause pattern:** Same stale-private-duplicate issue as `getAncestryTraits()` vs `CharacterManager::ANCESTRIES` (prior KB lesson on this pattern applies).

**Required fix:** Replace the private hardcoded `getAncestryHeritages()` lookup for Dwarf with a delegation to `CharacterManager::HERITAGES`. Preferred pattern:
```php
// In getAncestryHeritages():
$heritages = CharacterManager::HERITAGES[$name] ?? [];
return $heritages;
```
Or minimally, update the Dwarf array to include Death Warden and all `special` blocks matching `CharacterManager::HERITAGES['Dwarf']`.

## Acceptance criteria
- [ ] `getAncestryHeritages('Dwarf')` returns 5 heritages (including Death Warden)
- [ ] All 5 heritages include the `special` mechanic blocks from CharacterManager::HERITAGES
- [ ] Ideally `getAncestryHeritages()` delegates to CharacterManager::HERITAGES to prevent future drift
- [ ] No other ancestry's heritage data is disrupted

## Verification method
```bash
cd sites/dungeoncrawler && grep -A 30 "getAncestryHeritages" web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationController.php | grep -c "death-warden\|Death Warden"
# Expected: 1 (at minimum)
```

## Source
Code review: release `20260409-dungeoncrawler-release-h`, commit `4edab81d3`
