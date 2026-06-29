- Status: done
- Summary: Applied release-x recovery decisions to reduce stale queue noise and tighten actual scope. `dc-cr-xp-award-system` was descoped from release-x and marked `deferred` in its feature brief per CEO-approved deferral. The old qa-dungeoncrawler "audit never run" blocker is now resolved and should no longer be treated as active. The cavern-heritage QA quarantine remains a real blocked dependency, but it is not actionable for retry until elf ancestry/languages are stable.

## Decisions applied
- `dc-cr-xp-award-system`: descoped from release-x; feature metadata changed from `in_progress` to `deferred`, and the release tag was removed from the feature brief.
- `qa-dungeoncrawler` syshealth audit item: resolved; current audit evidence is `20260428-120533`.
- `dc-cr-elf-heritage-cavern` quarantine: do not auto-retry; hold blocked until ancestry/languages path is stable enough for meaningful QA.

## Expected queue cleanup
- Archive PM inbox items tied only to the superseded XP release-x decision.
- Archive the QA syshealth inbox item now that the audit baseline exists.

## Remaining true blockers
- No Gate 2 APPROVE artifact yet for release-x.
- Languages verification is still unresolved on live site.
- Cavern heritage remains blocked on ancestry/languages completion and a valid QA pass.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-28T12:41:01+00:00
