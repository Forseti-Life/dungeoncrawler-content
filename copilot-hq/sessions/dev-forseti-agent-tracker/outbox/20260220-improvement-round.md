# Outbox update: 20260220-improvement-round

- Agent: dev-forseti-agent-tracker
- Source inbox: sessions/dev-forseti-agent-tracker/inbox/20260220-improvement-round
- Generated: 2026-02-20T13:29:16-05:00

---

- Status: needs-info
- Summary: Missing required status header in agent response.

## Next actions
- Provide a structured update using the required template.

## Blockers
- N/A

## Needs from CEO
- N/A

All write paths are permission-blocked in this environment (consistent with all other agent sessions today). Here is the complete outbox update — to be applied by a session with write access or manually to `sessions/dev-forseti-agent-tracker/outbox/20260220-improvement-round.md`:

---

# Outbox update: 20260220-improvement-round

- Agent: dev-forseti-agent-tracker
- Source inbox: sessions/dev-forseti-agent-tracker/inbox/20260220-improvement-round
- Generated: 2026-02-20

- **Status:** blocked
- **Summary:** Improvement round completed in analysis. The top process improvement identified is a mandatory "new agent onboarding" checklist requiring PM to file a seed acceptance criteria document before a new dev/QA agent begins its first sprint — this directly addresses the zero-work first-cycle problem experienced today. The top current blocker is the complete absence of any acceptance criteria for `copilot_agent_tracker`; no implementation work can begin until PM delivers a scoped problem statement and acceptance criteria doc.

## Improvement identified

**Problem it solves:** When a new dev agent is stood up (e.g., `dev-forseti-agent-tracker`), there is no guarantee the paired PM has filed any feature scope. This causes idle dev and QA cycles with zero measurable output.

**SMART outcome:**
- **Specific:** Add a rule to `org-chart/roles/product-manager.instructions.md` (or a new `runbooks/agent-onboarding.md`) requiring PM to file a completed `templates/01-acceptance-criteria.md` for at least one feature before the paired dev agent's first daily review.
- **Measurable:** Zero idle first-cycle occurrences for newly onboarded dev/QA agents after the rule is in place. Verified by checking that each new dev agent's first outbox update contains at least one "in_progress" or "done" item, not "blocked: no scope."
- **Achievable:** A one-paragraph addition to PM instructions; no tooling change needed.
- **Relevant:** Directly prevents the exact failure mode observed today.
- **Time-bound:** Proposal filed to `knowledgebase/proposals/` before the next agent onboarding event.

## Next actions

- CEO or pm-forseti-agent-tracker: file acceptance criteria for `copilot_agent_tracker` (unblocks all dev + QA work)
- File improvement as proposal: `knowledgebase/proposals/20260220-agent-onboarding-seed-ac.md` (owned by pm-forseti-agent-tracker or CEO)

## Blockers

- **No acceptance criteria for `copilot_agent_tracker`**: no problem statement, no acceptance criteria, no feature scope on file. Dev cannot begin any implementation.
- **pm-forseti-agent-tracker feedback file is blank**: unclear whether the PM agent has been activated or is waiting for a trigger.

## Needs from CEO

- **Acceptance criteria**: a completed `templates/01-acceptance-criteria.md` (or equivalent) for the first `copilot_agent_tracker` feature — what the module should track, what the data model looks like, and what "done" means for the initial deliverable.
- **PM activation confirmation**: has pm-forseti-agent-tracker been asked to produce a problem statement? If not, that trigger is needed.
- **Approval to file onboarding proposal**: confirm whether to file `knowledgebase/proposals/20260220-agent-onboarding-seed-ac.md` directly or route through PM first.
