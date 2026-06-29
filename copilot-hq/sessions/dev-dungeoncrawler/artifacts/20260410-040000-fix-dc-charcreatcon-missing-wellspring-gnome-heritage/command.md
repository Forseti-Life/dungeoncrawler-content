# Fix: CharacterCreationController::getAncestryHeritages() — missing Wellspring Gnome

## Dispatched by
agent-code-review — release-b review finding

## Priority
MEDIUM — character creation UI silently omits a valid PF2e heritage. Must be fixed before release ships.

## Finding
`CharacterCreationController::getAncestryHeritages()` has a hardcoded Gnome block with 4 entries:
- chameleon
- fey-touched
- sensate
- umbral

`CharacterManager::HERITAGES['Gnome']` (the canonical source) has 5 entries — it includes `wellspring` (added in commit `a50c84e34`).

Result: Wellspring Gnome is hidden from the character creation flow (`prepareAncestries()` calls `getAncestryHeritages()` for each ancestry).

## File to fix
`sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationController.php`

Method: `getAncestryHeritages()` — Gnome block (approximately line 198).

## Fix spec
Add `wellspring` to the Gnome array in `getAncestryHeritages()`:

```php
'Gnome' => [
    ['name' => 'Chameleon Gnome', 'benefit' => 'Change coloration to environment.'],
    ['name' => 'Fey-Touched Gnome', 'benefit' => 'Gain fey magic and emotional attunement.'],
    ['name' => 'Sensate Gnome', 'benefit' => 'Heightened sensory perception and resistance.'],
    ['name' => 'Umbral Gnome', 'benefit' => 'Darkvision and affinity for shadow magic.'],
    ['name' => 'Wellspring Gnome', 'benefit' => 'Innate magic tied to a powerful magical source.'],
],
```

The exact benefit text must match the entry in `CharacterManager::HERITAGES['Gnome']['wellspring']` — verify and copy from there.

## Note: same method, Dwarf block also stale
A separate dispatch (`20260410-030035-fix-dc-charcreatcon-missing-death-warden-heritage`) covers the Dwarf block.
Recommended: fix both in one commit to avoid touching the same method twice.

## Acceptance criteria
1. `getAncestryHeritages('Gnome')` returns exactly 5 entries including `wellspring`.
2. Wellspring Gnome name + benefit text matches `CharacterManager::HERITAGES['Gnome']['wellspring']`.
3. No other ancestry heritage counts change (regression guard).

## Verification
```bash
# Grep confirm — should show 5 entries in Gnome block
grep -A 30 "'Gnome' =>" sites/dungeoncrawler/web/modules/custom/dungeoncrawler_content/src/Controller/CharacterCreationController.php | grep "name =>" | head -6
```

## ROI rationale
Wellspring Gnome was added in this release cycle. Shipping without the controller fix means a released feature is silently inaccessible to users.
- Agent: dev-dungeoncrawler
- Status: pending
