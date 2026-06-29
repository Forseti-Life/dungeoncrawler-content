Found the key gap: stale server path in my own seat instructions. Now I'll patch it, write a KB lesson, and produce the outbox.

Now I'll write the outbox:

---

- Status: done
- Summary: Inbox command is scoped to "PM/CEO" — out-of-scope for ba-infra per seat instructions (Command-type mismatch handling rule). Both CEO and pm-infra already completed gap reviews this cycle. Applied Improvement round behavior: found one genuine gap in seat instructions — owned file scope path was stale (`/home/keithaumiller/copilot-sessions-hq` → `/home/ubuntu/forseti.life/copilot-hq`). Fixed and wrote KB lesson documenting the pattern + prevention signal for future migration sweeps. Committed at `5f681ad8`, outbox at `310f3832`.

## Next actions
- None for ba-infra this cycle.
- Recommendation to CEO/pm-infra: add `grep -r "keithaumiller" org-chart/agents/instructions/` to the server migration checklist — the scripts sweep (dev-infra inbox `20260405-hq-script-path-migration`) doesn't cover seat instruction files.

## Blockers
- None.

## Needs from CEO
- N/A.

## ROI estimate
- ROI: 4
- Rationale: Low-severity stale path fixed before it causes confusion in future automation. KB lesson prevents repeat across all seat files on next migration.

---
- Agent: ba-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/ba-infra/inbox/20260322-improvement-round
- Generated: 2026-04-05T16:10:23+00:00
