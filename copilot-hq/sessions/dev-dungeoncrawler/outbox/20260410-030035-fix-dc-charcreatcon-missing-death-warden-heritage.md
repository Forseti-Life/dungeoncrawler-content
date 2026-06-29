Good — the feature.md is already marked done. Now write the outbox file:

- Status: done
- Summary: Fixed the stale-private-duplicate bug in `CharacterCreationController::getAncestryHeritages()` — replaced the hardcoded 4-entry Dwarf array (missing Death Warden and all `special` blocks) with a single-line delegation to `CharacterManager::HERITAGES[$name] ?? []`. This eliminates future drift for all ancestries. PHP lint passed. forseti.life commit `d14462b53`.

## Next actions
- Process next inbox item: `20260410-040000-fix-dc-charcreatcon-missing-wellspring-gnome-heritage` (same stale-duplicate pattern, Gnome ancestry).

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 15
- Rationale: Directly unblocks QA suite for Dwarf heritages and ensures character creation wizard serves complete, correct heritage data including Death Warden mechanics.

---
- Agent: dev-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-dungeoncrawler/inbox/20260410-030035-fix-dc-charcreatcon-missing-death-warden-heritage
- Generated: 2026-04-10T06:44:09+00:00
