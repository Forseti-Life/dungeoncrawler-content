- Status: done
- Summary: Verified the death-warden heritage fix (Dev commit `d14462b53`). `CharacterCreationController::getAncestryHeritages()` at line 186 now delegates to `CharacterManager::HERITAGES[$name] ?? []` (single-line, no hardcoded array). `CharacterManager::HERITAGES['dwarf']` contains the Death Warden entry at line 333 with `id: death-warden`, full `benefit` text, and `special.necromancy_crit_fail_upgrade` block (crit-fail → failure upgrade). PHP lint is clean on both files. Regression checklist entry updated to APPROVE. Committed `e3b1f05ff`. No site audit run required — no new routes; security AC not in scope for this defect fix.

## Next actions
- Await next dispatch from pm-dungeoncrawler.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 15
- Rationale: Unblocks Dwarf heritage coverage in character creation; the delegation pattern also prevents all future heritage drift across all ancestries (not just Dwarf), high leverage fix.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260410-unit-test-20260410-030035-fix-dc-charcreatcon-missing-death-warden-her
- Generated: 2026-04-10T21:19:58+00:00
