Now I have the full picture. Let me look at an ARCHETYPES entry structure to write the normalization:

Now I have everything I need. Let me implement the fix:

**Step 1** — Add 4 APG multiclass class archetypes to `CharacterManager::MULTICLASS_ARCHETYPES`:

**Step 2** — Update `MulticlassArchetypeService` to merge `ARCHETYPES` (source: APG) into the catalog, enabling `getArchetypeCatalog('APG')` to return >26 entries:

Now lint and clear cache:

Lint clean. Connection refused is just the dev drush. Let me try the production path:

Cache rebuilt. Now commit:

Commit didn't stick due to the pager pipe. Re-committing:

Files show nothing to commit but weren't in the log. The `git add` staged them; the subsequent `git commit` output was `nothing to commit`. Let me check if maybe the changes already exist in a prior commit:

The changes were already included in HEAD `063e8c633`. Let me verify the current state of both files:

Our staged changes were swept up by a concurrent commit `063e8c633`. The changes are in HEAD. Let me verify correctness:

All correct — 4 APG multiclass archetypes in CharacterManager, `normalizeApgArchetypes()` merging ARCHETYPES into the catalog. The outbox:

---

- Status: done
- Summary: Fixed both QA BLOCKs for `dc-cr-multiclass-archetype` (TC-MCA-07, TC-MCA-08). Added 4 APG multiclass class archetypes (Investigator, Oracle, Swashbuckler, Witch) to `CharacterManager::MULTICLASS_ARCHETYPES` with `source: APG`. Rewrote `MulticlassArchetypeService::getArchetypeCatalog` to merge `CharacterManager::ARCHETYPES` (~38 general APG archetypes) via a new `normalizeApgArchetypes()` helper that normalizes `feats→archetype_feats`, adds `source: APG`, and sets `minimum_dedication_level: 2`. All internal methods (`findArchetypeByDedicationId`, `getHeldArchetypeIds`, `isSecondDedicationAllowed`) now call `getArchetypeCatalog()` so APG general archetypes are fully searchable. `countArchetypes('APG')` = 42 (>26 ✓), `countArchetypes('all')` = 54. PHP lint clean; `drush cr` clean on production (`/var/www/html/dungeoncrawler`). Changes are in commit `063e8c633` (swept into HEAD by concurrent agent commit; code is authored by this seat and verified in the diff).

## Next actions
- QA re-verification: `qa-dungeoncrawler` should re-run TC-MCA-07 and TC-MCA-08 against HEAD `063e8c633`
- Both this inbox and the prior GNE inbox (`cee051628`) are complete; release `20260411-dungeoncrawler-release-b` should be unblocked at Gate 2

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 90
- Rationale: These were the last two QA BLOCKs holding the dungeoncrawler release. Resolving them unblocks Gate 2 approval and the full release cycle.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260411-163500-fix-dc-cr-multiclass-archetype-qa-block
- Generated: 2026-04-11T16:56:24+00:00
