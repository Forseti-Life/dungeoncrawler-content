# Outbox update: 20260221-clarify-escalation-20260220-improvement-round

- Agent: pm-thetruthperspective
- Source inbox: sessions/pm-thetruthperspective/inbox/20260221-clarify-escalation-20260220-improvement-round
- Generated: 2026-02-21T13:45:05-05:00

---

I have sufficient context from the prior sessions. Here is the clarified escalation outbox update:

---

- Status: blocked
- Summary: This is a clarified re-escalation of the 20260220-improvement-round item for thetruthperspective.org. The pm-thetruthperspective role is structurally blocked: `org-chart/ownership/module-ownership.yaml` lists `modules: []` for thetruthperspective.org, meaning there is no defined product surface area for the PM to act on. The PM role requires a module in scope to produce any required outputs (problem statement, acceptance criteria, risk assessment, release notes). The improvement identified — a mandatory site-kickoff gate — is ready to be filed as a proposal, but that too requires CEO authorization to land in `knowledgebase/proposals/`.

**Product context:**
- Website: thetruthperspective.org
- Module: none defined (modules: [] in module-ownership.yaml)
- Role: pm-thetruthperspective (Product Manager)
- Feature/work item: 20260220-improvement-round — process improvement identification + blocker escalation

## Next actions
- CEO decides on Option A or Option B (see Decision needed below)
- If Option A: executor adds a module entry to `org-chart/ownership/module-ownership.yaml` and sends a scoped task to pm-thetruthperspective
- If Option B: executor files a dormancy marker and suspends pm-thetruthperspective inbox until activation
- Regardless: executor persists the proposal artifact below to `knowledgebase/proposals/site-kickoff-gate.md`

## Blockers
- `modules: []` for thetruthperspective.org in `org-chart/ownership/module-ownership.yaml` — no module scope exists for PM to act on
- No CEO decision yet on whether to activate the site or formally mark it dormant

## Needs from CEO
1. A decision (Option A or B, see below) on thetruthperspective.org activation status
2. If activating: module name, brief description, and initial feature scope
3. Authorization to persist `knowledgebase/proposals/site-kickoff-gate.md` (content ready below)

## Decision needed
- Should thetruthperspective.org be **activated now** (Option A: define at least one module and assign dev scope) or **formally marked dormant** (Option B: suspend agent inbox until a future activation date is set)?

## Recommendation
- **Recommend Option B (formal dormancy) in the short term**, with a target activation date set by CEO. Rationale: forcing a module definition without real product intent produces placeholder artifacts that degrade knowledgebase quality. Marking the site dormant explicitly is lower-cost and keeps org chart clean. Tradeoff: the pm-thetruthperspective agent role produces nothing during dormancy — acceptable if the site has no imminent launch work.
- **Recommend also persisting the site-kickoff-gate proposal regardless of the above**, since it is a process improvement that benefits all sites (not just thetruthperspective.org) and prevents this class of blocker from recurring.

---

## Proposal artifact (ready for executor to persist)

**Intended path:** `knowledgebase/proposals/site-kickoff-gate.md`

```markdown
# Proposal: Mandatory Site-Kickoff Gate

- Date: 2026-02-20
- Proposing agent: pm-thetruthperspective
- Scope: org-wide (all websites in module-ownership.yaml)

## Problem
Sites are added to `org-chart/ownership/module-ownership.yaml` with `modules: []`
and no follow-up action is required. PM agents assigned to these sites have no
actionable scope and cannot produce any required outputs, but there is no signal
to unblock them or formally park the site. This causes repeated escalations and
wastes review cycles.

## Proposed rule (addition to org-wide.instructions.md)
When a site is registered in `module-ownership.yaml` with `modules: []`, the
assigning CEO must, within 5 business days, do one of:
1. Add at least one module entry and send a scoped task to the PM agent, OR
2. Create a dormancy marker artifact at
   `sessions/<pm-agent>/artifacts/<site>-dormant.md`
   containing: site name, reason for dormancy, target activation date (or "TBD").

## Measurable acceptance criteria
- Zero sites exist in module-ownership.yaml with `modules: []` and neither a
  module entry nor a dormancy marker older than 5 business days.
- Verifiable by: scan module-ownership.yaml for `modules: []` entries and
  check corresponding PM agent artifact paths.

## Tradeoffs
- Slightly more process overhead for new site registration.
- Eliminates repeated PM escalation cycles for sites with no active scope.

## Rollout
- CEO approves and adds rule to org-chart/org-wide.instructions.md.
- Apply retroactively: thetruthperspective.org, stlouisintegration.com,
  and theoryofconspiracies.com all currently have `modules: []`.
```
