# Fix cross-team QA agent lookup in release-signoff.sh

## Background

`scripts/release-signoff.sh` derives `qa_agent` from the **signing PM's team**, not the release's owning team (lines 73–76). When `pm-forseti` co-signs a DungeonCrawler release (e.g., `20260408-dungeoncrawler-release-b`), the Gate 2 guard (lines 88–96) checks `sessions/qa-forseti/outbox/` for an APPROVE — but the APPROVE lives in `sessions/qa-dungeoncrawler/outbox/`. This causes every coordinated cross-site release to require manual CEO intervention to file cross-site APPROVE reference artifacts.

## Required change

In `scripts/release-signoff.sh`, update the Gate 2 guard so that `qa_agent` is derived from the **release ID's owning team**, not the signing PM's team.

### Acceptance criteria

1. When `pm-forseti` calls `release-signoff.sh <release-id>` and `<release-id>` begins with `20260408-dungeoncrawler-*` (or matches any DC release pattern), the script looks up `qa_agent` from the `dungeoncrawler` team entry in `product-teams.json`, not from the `forseti` team entry.
2. When the owning team's QA outbox contains an APPROVE for that release, `gate2_approved=1` and sign-off proceeds without manual intervention.
3. Existing behavior is preserved: when a PM signs their **own** team's release, `qa_agent` still comes from that team.
4. `bash scripts/release-signoff.sh 20260408-dungeoncrawler-release-b` called as `pm-forseti` succeeds (exit 0) when `sessions/qa-dungeoncrawler/outbox/` contains an APPROVE for that release ID.
5. Unit/integration test (bash or Python): covers the cross-site case above (add to existing test suite or as a standalone test script in `scripts/tests/`).

## Implementation hint

Parse the `release_id` prefix to determine the owning `team_id`, then look up that team's `qa_agent` from `product-teams.json`. Suggested approach:
- Strip date prefix from `release_id` (e.g., `20260408-dungeoncrawler-release-b` → owning team `dungeoncrawler`).
- If owning team differs from signing team, load the owning team's `qa_agent` for the Gate 2 check.
- Fallback: if owning team can't be determined from release_id, fall back to current behavior (signing team's `qa_agent`).

## Files in scope

- `scripts/release-signoff.sh` — primary change
- `scripts/tests/` — add/update test coverage

## Verification method

```bash
# Simulate cross-site sign: pm-forseti signs a DC release, DC QA has APPROVE
bash scripts/release-signoff.sh 20260408-dungeoncrawler-release-b  # (set PM env as pm-forseti)
# Should succeed when qa-dungeoncrawler/outbox contains APPROVE for that release ID
```

## ROI

45 — eliminates recurring manual CEO unblocking on every coordinated release cycle (4 consecutive cycles affected to date).
