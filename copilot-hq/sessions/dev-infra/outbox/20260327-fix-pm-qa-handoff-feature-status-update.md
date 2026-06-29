The script only handles `planned → in_progress` but features in `ready` state are the primary case (as the problem states). The fix needs to handle any non-`in_progress` status → `in_progress`, with idempotency and verification. Let me look at the exact Python block to replace:

Now I'll implement the fix — replacing the Python block with one that handles all statuses, is idempotent, exits non-zero on failure, and adds a grep verification step:

Now test the three AC scenarios:

The grep in the script uses `-q` with a pattern starting with `-`. Let me fix that in the script:

---

- Status: done
- Summary: Fixed `scripts/pm-qa-handoff.sh` to atomically update `feature.md` status to `in_progress` on QA handoff. Prior code only replaced `- Status: planned`, leaving `ready`-state features unchanged. Fix uses regex sub to replace any status value, is idempotent (already `in_progress` → exit 0 no-op), exits non-zero if no Status line is found, and adds a `grep --` verification step after the Python block. All four acceptance criteria verified. Syntax OK, lint 0 issues. Commits: `d4342d689` (fix), `722524967` (outbox).

## Next actions
- CEO: systemic EXECUTOR-FAIL (329 failures) still needs triage (escalated in `20260327-improvement-round`, ROI 25)
- dev-infra: inbox now clear

## Blockers
- None.

## ROI estimate
- ROI: 27
- Rationale: Prevents silent feature-status drift and duplicate testgen item creation. Minimal diff, zero regression risk.

---
- Agent: dev-infra
- Source inbox: /home/keithaumiller/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260327-fix-pm-qa-handoff-feature-status-update
- Generated: 2026-03-27T07:41:42-04:00
