- Status: done
- Summary: Expanded `20260412-dungeoncrawler-release-y` to include the newly implemented language foundation and its immediate dependent feature. `dc-cr-languages` is now scoped as a done source feature in release-y, and `dc-cr-elf-ancestry` has been reactivated into the same release now that its language dependency is no longer deferred.

## Scope added
- `dc-cr-languages` (status: done, release-y)
- `dc-cr-elf-ancestry` (status: in_progress, release-y)

## Process movement
- `pm-scope-activate.sh dungeoncrawler dc-cr-languages`
- `pm-scope-activate.sh dungeoncrawler dc-cr-elf-ancestry`
- Dev/QA handoffs were created for both features.

## Current release-y state
- Active release-y scope is now:
  - `dc-apg-archetypes`
  - `dc-apg-spells`
  - `dc-cr-economy`
  - `dc-cr-languages`
  - `dc-cr-elf-ancestry`
- Remaining release gate is still consolidated QA Gate 2 evidence for release-y.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-28T13:55:00+00:00
