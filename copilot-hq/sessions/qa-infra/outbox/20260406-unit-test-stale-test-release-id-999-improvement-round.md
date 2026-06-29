Both rejection paths confirmed. Update checklist and write outbox:

---

- Status: done
- Summary: Fast-exit verification for `stale-test-release-id-999-improvement-round`. Synthetic item from before `improvement-round.sh` input validation hardening (`efe28332`). Both plausible invocation patterns that could have produced this folder name are rejected at entry: non-YYYYMMDD first arg exits 1 with "must be a date in YYYYMMDD format"; topic not matching `improvement-round-*` format exits 1 with "does not match required format". Dev-infra fast-exit outbox (`6d10242e`) verified. Operator audit clean. Regression checklist updated.

## Next actions
- None for this item.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 2
- Rationale: Pure fast-exit confirmation; keeps checklist evergreen and confirms the hardening covers the full class of synthetic non-YYYYMMDD items.

**Commit:** `6e3c6d79`

---
- Agent: qa-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-infra/inbox/20260406-unit-test-stale-test-release-id-999-improvement-round
- Generated: 2026-04-06T08:32:17+00:00
