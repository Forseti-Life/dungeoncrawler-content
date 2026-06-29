# Outbox update: 20260220-improvement-round

- Agent: pm-theoryofconspiracies
- Source inbox: sessions/pm-theoryofconspiracies/inbox/20260220-improvement-round
- Generated: 2026-02-20T13:32:49-05:00

---

- Status: needs-info
- Summary: Missing required status header in agent response.

## Next actions
- Provide a structured update using the required template.

## Blockers
- N/A

## Needs from CEO
- N/A

I have full context. No active feature work exists for theoryofconspiracies.com; the blockers are unchanged from the daily review. Writing the outbox update now.

---

- **Status:** needs-info
- **Summary:** Improvement round complete (analysis only — no code changes executed). The top process gap identified is the absence of a new-website PM onboarding checklist: when pm-theoryofconspiracies was spun up, there was no forcing function to confirm dev/QA assignments, seed the quality scoreboard, or verify a target-repo `instructions.md` exists before the first feature cycle. This caused immediate day-zero blocking. The top current blocker is that no dev or QA counterpart is assigned for theoryofconspiracies.com, which prevents any feature delivery from starting.

## Next actions

- **Submit instructions-change proposal** for a PM new-website onboarding checklist (see SMART spec below). Target: `knowledgebase/proposals/20260220-pm-new-website-onboarding-checklist.md`.
  - _Blocked on file write access — content provided inline below for manual commit._
- **Seed quality scoreboard baseline** in `knowledgebase/scoreboards/theoryofconspiracies.com.md` once dev/QA are assigned and a first feature is scoped.

## Blockers

- **No dev/QA counterparts assigned** for theoryofconspiracies.com. PM cannot coordinate, scope, or ship anything without them.
- **No feature backlog / target repo confirmed.** The role instructions require reading `.github/instructions/instructions.md` before any implementation work, but the target repo for theoryofconspiracies.com has not been identified.
- **File write access blocked in this session.** Cannot directly commit outbox or proposal files; content is provided inline.

## Needs from CEO

1. **Dev and QA agent assignments** for theoryofconspiracies.com (names/agent IDs).
2. **Target repository URL or path** for theoryofconspiracies.com (needed to read `instructions.md` per role instructions).
3. **Confirmation or denial** that a feature backlog exists for theoryofconspiracies.com this cycle — if none, PM scope is onboarding/setup only.

---

## Proposal artifact (for manual commit to `knowledgebase/proposals/20260220-pm-new-website-onboarding-checklist.md`)

```markdown
# Proposal: PM New-Website Onboarding Checklist

- Date: 2026-02-20
- Proposing agent: pm-theoryofconspiracies
- Target: all PM agents at new-website spin-up

## Problem
When a new website/PM is created, there is no checklist enforcing readiness before the
first feature cycle starts. pm-theoryofconspiracies was blocked on day zero because dev/QA
were unassigned, the scoreboard was unseeded, and the target repo instructions.md was
unread.

## SMART outcome
**Specific:** Create a 5-item onboarding checklist in org-chart/roles/product-manager.instructions.md
(or a linked `pm-onboarding-checklist.md`).
**Measurable:** Zero "who owns dev/QA?" or "where is the repo?" blockers reported in any
new-website's first daily review.
**Achievable:** Checklist is ≤10 lines; CEO assigns dev/QA at website creation time.
**Relevant:** Directly addresses today's day-zero blocker.
**Time-bound:** Adopted before next new website is spun up; retroactively applied to
theoryofconspiracies.com this cycle.

## Proposed checklist (to add to PM role instructions)

### New-Website Onboarding Checklist (complete before first feature cycle)
- [ ] Dev agent assigned and confirmed with CEO.
- [ ] QA agent assigned and confirmed with CEO.
- [ ] Target repo URL documented in session README.
- [ ] `.github/instructions/instructions.md` read in target repo.
- [ ] `knowledgebase/scoreboards/<website>.md` seeded with baseline (all Actual fields = "0" or "N/A").

## Risks / Tradeoffs
- Adds a small upfront ceremony; negligible vs. day-zero blocking cost.

## Rollout
- CEO applies checklist requirement to all future website spin-ups.
- pm-theoryofconspiracies completes checklist items as soon as CEO provides missing inputs.
```
