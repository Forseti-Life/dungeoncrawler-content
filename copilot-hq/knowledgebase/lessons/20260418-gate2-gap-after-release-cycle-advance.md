# Lesson: Gate 2 APPROVE gap after release cycle advance

- Date: 2026-04-18
- Team: dungeoncrawler
- Release: 20260412-dungeoncrawler-release-n
- Blocker class removed: `Gate 2 APPROVE gap (up to 2h) after post-coordinated-push cycle advance`

## What happened

The clean-audit Gate 2 backstop in `ceo-ops-once.sh` filed the Gate 2 APPROVE for release-n approximately 2 hours after the release cycle was seeded. The normal path (`site-audit-run.sh` → `gate2-clean-audit-backstop.py`) only fires after an audit runs. Because the latest audit pre-dated release-n's cycle start, there was no audit run to trigger the normal path until the ceo-ops-once backstop fired.

Timeline:
- `20260417-160402` — last audit ran (during release-m)
- `2026-04-18T12:00:56` — release-n cycle started (post-coordinated-push.sh advanced)
- `20260418-140044` — Gate 2 APPROVE filed by backstop (~2h gap)

## Root cause

`post-coordinated-push.sh` advanced the release cycle but did not call `gate2-clean-audit-backstop.py`. The existing clean audit in `latest/` was not re-evaluated against the new release_id until the next site audit run or the 2-hour ceo-ops-once backstop cycle.

## Fix applied

Added a `gate2-clean-audit-backstop.py` call at the end of `post-coordinated-push.sh` (before `ceo-release-boundary-health.sh`). Now when the release cycle advances, any pre-existing clean audit immediately satisfies Gate 2 for the new release — eliminating the gap.

File changed: `scripts/post-coordinated-push.sh`

## Prevention going forward

- The `ceo-ops-once.sh` backstop remains as a safety net (correct behavior).
- `post-coordinated-push.sh` now closes the gap proactively.
- No instruction changes required — the fix is in automation.
