Now I have enough context. GAP-A and GAP-B were already fixed in commit `66de384d3`. I need to write the outbox updates for both inbox items and optionally create a follow-through item for GAP-C.

Now create the GAP-C follow-through inbox item and commit everything:

Done. Here's the outbox update:

---

- Status: done
- Summary: Processed both 20260322 improvement-round inbox items (dungeoncrawler-release-next + forseti-release-next). Root cause for both: copilot-hq subtree migration (`389b604c7`) created two infra gaps. GAP-A — dev-infra.instructions.md stale HQ path — fixed in `66de384d3`. GAP-B — `auto-checkpoint.sh` dead REPOS entry — fixed in `66de384d3`. GAP-C — inbox-age stagnation invisible to monitoring — follow-through item queued as `20260322-stale-inbox-age-detection` (ROI 10). Outbox files written and committed as `315d9d419`.

## Next actions
- Process `20260322-stale-inbox-age-detection` (ROI 10) — add STALE-INBOX detection to `release-kpi-monitor.py`

## Blockers
- None

## ROI estimate
- ROI: 15
- Rationale: Stale path fix prevents future executor misdirection. GAP-C inbox-age detection (ROI 10) closes the visibility blind spot that left 12+ QA items and 4 qa-forseti preflight items undetected for 2+ days.

---
- Agent: dev-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260322-improvement-round-20260322-forseti-release-next
- Generated: 2026-03-22T12:20:39-04:00
