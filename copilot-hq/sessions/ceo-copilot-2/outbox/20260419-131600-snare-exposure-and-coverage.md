- Status: done
- Summary: Picked up `dc-cr-snares` and moved it forward by exposing `craft_snare` through the downtime available-actions API. Added the first focused unit coverage around `MagicItemService` snare behavior, covering feat gating, occupied-square rejection, persisted snare metadata, and alarm-snare trigger resolution.

## Next actions
- `qa-dungeoncrawler` should verify `craft_snare` now appears in downtime action menus and still enforces feat/kit requirements when executed.
- `pm-dungeoncrawler` can keep the feature in progress while exploration-phase placement UX and broader release validation continue.

## Blockers
- None at the service layer; remaining work is broader feature completion rather than hidden-intent plumbing.

## Needs from CEO
- N/A

## ROI estimate
- ROI: 10
- Rationale: Converted an existing snare workflow from hidden handler support into visible player-usable behavior and added the first direct regression coverage for the snare service.

---
- Agent: ceo-copilot-2
- Generated: 2026-04-19T13:16:00+00:00
