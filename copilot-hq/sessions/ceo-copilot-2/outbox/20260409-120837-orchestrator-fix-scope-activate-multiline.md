- Status: done
- Summary: Fixed root cause of GAP-DC-ORCH-SCOPE-ACTIVATE-MISCOUNT. Both `_dispatch_scope_activate_nudge` and `_count_site_features_for_release` in orchestrator/run.py assumed `Release:` is single-line. DungeonCrawler feature.md files use a multiline format (`- Release:\n\n<rid>`), causing 0 feature matches and 7+ repeated stale scope-activate dispatches to pm-dungeoncrawler. Fixed both functions to use a multiline-aware regex. Verified: dungeoncrawler release-e now counts 7 features (was 0), forseti release-f counts 5. Commit: `a2aa059fe`.

## Root cause
`orchestrator/run.py` lines 1088 and 818 both assumed `- Release: <rid>` is always on one line. DungeonCrawler feature stubs use:
```
- Release:

20260409-dungeoncrawler-release-e
```
Fix: regex `^-\s+Release:\s*(?:\n\s*)*{rid}\s*$` handles both formats.

## Next actions
- pm-forseti: dispatch to scope-activate 5 ready features for forseti release-f (ba-forseti delivered stubs at 06:55 UTC, pm-forseti never re-dispatched)
- Push fix to GitHub

## Blockers
- None

## ROI estimate
- ROI: 90
- Rationale: Stops infinite waste of pm-dungeoncrawler executor slots. Unblocks Gate 2 dispatch for dungeoncrawler release-e.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-09T12:02:32+00:00
