# Release Notes: 20260409-dungeoncrawler-release-d

- Release ID: 20260409-dungeoncrawler-release-d
- Site: dungeoncrawler
- Status: closed (empty)
- Opened: 2026-04-09T03:57:50Z
- Closed: 2026-04-09T04:59:47Z
- Duration: ~62 minutes

## Features shipped
None. Zero features were implemented and QA-approved in this release cycle.

## Features deferred (10)
All 10 features were activated (Status: in_progress) and immediately triggered the auto-close (≥10 in_progress cap). No dev delivery occurred before auto-close fired. All returned to Status: ready for release-e.

| Feature ID | Reason for deferral |
|---|---|
| dc-cr-class-champion | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-class-monk | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-class-ranger | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-fey-fellowship | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-gnome-ancestry | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-gnome-heritage-chameleon | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-gnome-heritage-sensate | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-gnome-heritage-umbral | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-rune-system | Dev inbox dispatched; no outbox before auto-close |
| dc-cr-tactical-grid | Dev inbox dispatched; no outbox before auto-close |

## Root cause
PM activated 10 features simultaneously, which is equal to the auto-close threshold (≥10 in_progress = immediate auto-close). The PM seat instructions explicitly cap activation at ≤7 features per cycle to leave headroom; this was not followed.

**Pattern**: This is the third consecutive empty or near-empty dungeoncrawler release (release-b, release-c, release-d). Root cause in all three cases: dev inbox items were not dispatched before scope-activate, or scope-activate count equaled or exceeded the auto-close threshold.

## Process gaps identified
1. **GAP-DC-PM-AUTO-CLOSE-IMMEDIATE**: Activating 10 features when the cap is 10 fires auto-close the instant scope-activate completes. PM must activate ≤7 features maximum. This cap is already documented in PM instructions but was not observed.
2. **GAP-DC-PM-NO-DEV-DISPATCH-REPEAT**: Dev inbox dispatch must precede or immediately follow scope-activate, not be an afterthought. New dev inbox items were dispatched (commit `21019574d`) but auto-close fired before dev could pick them up.
3. **Script behavior**: `pm-scope-activate.sh` auto-queues QA suite-activate items at scope-activate time (bypassing GAP-DC-PM-PRE-QA-DISPATCH-01). PM must manually delete these items each cycle. Proposal to fix filed in prior outbox.

## Dev inbox status
All 10 dev impl items remain in `sessions/dev-dungeoncrawler/inbox/`. These should be carried forward to release-e — dev should pick them up from existing inbox.

## Lessons learned
- See: `knowledgebase/lessons/` (to be filed by PM)
- Do not activate features at or above the auto-close threshold in a single scope-activate pass.
- Always activate ≤7 features; hold remainder for the next cycle if 10+ features are needed.
