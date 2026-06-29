Now I have the full picture. The active release has 5 stale in_progress features tagged for old `release-b`. 4 of them are valid for release-next (on the activation list); `dc-cr-conditions` is not. Let me fix the tags, fill the artifacts, and report.

Now fix the Release field tags and reset dc-cr-conditions:

---

- Status: done
- Summary: Diagnosed release-next KPI stagnation: `20260406-dungeoncrawler-release-next` was activated at 04:47Z with 10 claimed in_progress features, but all 5 remaining (after previous cleanup) were mis-tagged `Release: 20260406-dungeoncrawler-release-b` — artifacts of `pm-scope-activate.sh` running when release-b was the active release. Root fix: re-tagged 4 valid features (background-system, character-class, heritage-system, skill-system) to `Release: 20260406-dungeoncrawler-release-next`; reset `dc-cr-conditions` to `Status: ready` (not on the release-next activation list). KPI stagnation artifacts written to `sessions/pm-dungeoncrawler/artifacts/20260406-release-kpi-stagnation/`. Committed as `f0d240c1`.

## Next actions
- dev-dungeoncrawler: confirm active implementation on 4 re-tagged features (background-system, character-class, heritage-system, skill-system) for release-next; no re-implementation needed, only metadata was wrong
- qa-dungeoncrawler: confirm suite-activate inbox items for these 4 features are current; re-issue if tied to old release-b scope
- Auto-close monitor: release-next has 4 properly-tagged in_progress features; auto-close fires at ≥10 Gate 2 APPROVEs or ~2026-04-07T04:47Z (18h away)
- Root cause: `pm-scope-activate.sh` must be run AFTER the active release ID is set; add this constraint to seat instructions

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 40
- Rationale: Without this fix, release-next would close with 0 properly-scoped features (scope cap counter was blind to the 4 valid in_progress), causing a false empty-release event; correcting it unlocks the full dev-QA pipeline for this cycle.

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260406-release-kpi-stagnation
- Generated: 2026-04-06T11:01:28+00:00
