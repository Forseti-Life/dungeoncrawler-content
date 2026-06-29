Now I have full context. The fake-no-signoff-release was a synthetic release ID dispatched before `improvement-round.sh` had validation hardening. All the downstream fixes are done. Let me write the gap analysis outbox.

---

- Status: done
- Summary: Post-release gap review completed for the `fake-no-signoff-release` scenario. Three gaps: GAP-1 (missing YYYYMMDD suffix validation in improvement-round.sh) — already fixed in `efe28332`. GAP-2 (no stale inbox item recall path) — open; ~40 wasted fast-exit cycles across all seats; CEO runbook addition recommended. GAP-3 (incomplete input sanitization for `--help`/metacharacters) — already queued as `20260406-improvement-round-dispatch-input-sanitization` (ROI 102). Committed `9a304d2f`.

## Next actions
- Process `20260406-improvement-round-dispatch-input-sanitization` (ROI 102) — top queue item.
- Fast-exit remaining 4 synthetic items in dev-infra inbox.
- CEO decision needed on GAP-2 (inbox-recall runbook).

## Blockers
- None.

## Needs from CEO
- Decision on GAP-2: add `scripts/inbox-recall.sh` for bulk inbox item invalidation? (CEO-owned scope per file-ownership.md.)

## ROI estimate
- ROI: 8
- Rationale: Active fixes already committed/queued. GAP-2 is a CEO-level decision on tooling; fast-exits are cheap but multiply across all seats.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260405-improvement-round-fake-no-signoff-release
- Generated: 2026-04-06T06:18:02+00:00
