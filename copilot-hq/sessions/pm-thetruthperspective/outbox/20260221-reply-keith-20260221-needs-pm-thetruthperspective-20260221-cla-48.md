# Outbox update: 20260221-reply-keith-20260221-needs-pm-thetruthperspective-20260221-cla-48

- Agent: pm-thetruthperspective
- Source inbox: sessions/pm-thetruthperspective/inbox/20260221-reply-keith-20260221-needs-pm-thetruthperspective-20260221-cla-48
- Generated: 2026-02-21T14:52:04-05:00

---

- Status: done
- Summary: CEO approved the improvement-round recommendation. This closes the 20260220-improvement-round escalation. The approved recommendation was: (1) formally designate thetruthperspective.org as dormant with a target activation date, suspending the PM/Dev/QA loop until a module is defined, and (2) file the site-kickoff-gate proposal to prevent this class of blocker org-wide. Both artifacts are produced below for executor persistence.

## Next actions
- Executor persists dormancy marker artifact to `sessions/pm-thetruthperspective/artifacts/thetruthperspective.org-dormant.md` (content below)
- Executor persists proposal to `knowledgebase/proposals/site-kickoff-gate.md` (content below)
- Executor updates `org-chart/ownership/module-ownership.yaml` to add `status: dormant` and `activation_target: 2026-03-21` for thetruthperspective.org (diff below)
- CEO closes all open product-documentation escalation chain items (v2–v5 + all clarify variants) as superseded by dormancy decision
- pm-thetruthperspective inbox suspended until CEO sends an activation task for thetruthperspective.org

## Blockers
- None. CEO decision received. All artifacts ready for persistence.

## Needs from CEO
- None.

---

## Artifact 1: Dormancy marker

**Intended path:** `sessions/pm-thetruthperspective/artifacts/thetruthperspective.org-dormant.md`

```markdown
# Dormancy Marker — thetruthperspective.org

- Date: 2026-02-21
- Agent: pm-thetruthperspective
- Approved by: CEO (Keith), drupal_reply_id=48

## Status
dormant

## Reason
No modules defined in org-chart/ownership/module-ownership.yaml at time of registration.
No site purpose, user persona, or product scope provided.
PM/Dev/QA loop cannot be exercised without at least one module in scope.

## Target activation date
2026-03-21 (or earlier if CEO provides module scope sooner)

## To activate
CEO must:
1. Add at least one module entry to org-chart/ownership/module-ownership.yaml for thetruthperspective.org
2. Send a scoped task to pm-thetruthperspective with site purpose, first module name, and primary persona
3. Remove this dormancy marker or update status to "active"
```

---

## Artifact 2: Site-kickoff-gate proposal

**Intended path:** `knowledgebase/proposals/site-kickoff-gate.md`

```markdown
# Proposal: Mandatory Site-Kickoff Gate

- Date: 2026-02-21
- Proposing agent: pm-thetruthperspective
- Approved by: CEO (Keith), drupal_reply_id=48
- Scope: org-wide (all websites in module-ownership.yaml)

## Problem
Sites are added to org-chart/ownership/module-ownership.yaml with modules: []
and no follow-up action is required. PM agents assigned to these sites have no
actionable scope and cannot produce any required outputs, but there is no signal
to unblock them or formally park the site. This caused 5+ escalation iterations
and multiple clarification loops for thetruthperspective.org before a dormancy
decision was made.

## Proposed rule (addition to org-wide.instructions.md)
When a site is registered in module-ownership.yaml with modules: [], the
assigning CEO must, within 5 business days, do one of:
1. Add at least one module entry and send a scoped task to the PM agent, OR
2. Create a dormancy marker artifact at
   sessions/<pm-agent>/artifacts/<site>-dormant.md
   containing: site name, reason for dormancy, target activation date.

## Measurable acceptance criteria
- Zero sites exist in module-ownership.yaml with modules: [] and neither a
  module entry nor a dormancy marker older than 5 business days.
- Verifiable by: scan module-ownership.yaml for modules: [] entries and
  check corresponding PM agent artifact paths.

## Retroactive application
The following sites currently have modules: [] and need dormancy markers or
module definitions applied:
- thetruthperspective.org (dormant marker being filed with this proposal)
- stlouisintegration.com
- theoryofconspiracies.com

## Tradeoffs
- Slightly more process overhead for new site registration.
- Eliminates repeated PM escalation cycles for sites with no active scope.

## Rollout
- CEO approves and adds rule to org-chart/org-wide.instructions.md.
- Apply retroactively to all three sites listed above.
```

---

## Diff 3: module-ownership.yaml update

```diff
   thetruthperspective.org:
     pm_owners:
       - role: product-manager
         name: "PM (thetruthperspective.org)"
-    modules: []
+    modules: []
+    status: dormant
+    activation_target: "2026-03-21"
```
