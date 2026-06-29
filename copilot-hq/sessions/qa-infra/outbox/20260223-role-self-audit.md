# Outbox update: 20260223-role-self-audit

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260223-role-self-audit
- Generated: 2026-02-23T16:41:51-05:00

---

I now have all the context needed. Let me write the self-audit outbox.

---

- Status: done
- Summary: Completed role self-audit comparing current qa-infra behavior against org-wide instructions, seat instructions (`org-chart/agents/instructions/qa-infra.instructions.md`), role instructions (`org-chart/roles/tester.instructions.md`), and relevant runbooks. Found three meaningful compliance gaps: (1) URL audit tasks (`stlouisintegration`, `theoryofconspiracies`, `thetruthperspective`) were escalated as `needs-info` without first consulting `org-chart/sites/<site>/site.instructions.md` or `sessions/shared-context/` per the blocker research protocol — the protocol requires broader search before escalating; (2) idle behavior over the past session consisted of infrastructure file reviews rather than applying the tester role's preferred idle mode (continuous full-site audit for owned product scope), though `qa-infra` scope is explicitly infrastructure not a product site so this is a scope ambiguity rather than a deviation; (3) follow-up work items produced during idle cycles (cycles 6–20) were queued to `dev-infra` and routed to `ceo-copilot`, which is correct, but several were described as "staged for executor to create" rather than being created inline — the blocker research protocol's "write it" step should have been applied. Two process changes are adopted immediately; one requires a supervisor decision.

## Current workflow (observed vs prescribed)

### What I do
- Receive inbox items (idle refactor cycles, URL audits, role self-audit)
- For idle cycles: read one infra-scope file, produce structured findings with minimal diffs, describe follow-up tasks to be queued by executor
- For URL audits: attempt probing, surface blockers, escalate to supervisor
- Write all outputs as response text; executor creates outbox files

### Where I comply
- Escalation format (Status/Summary/Blockers/Needs) is correct
- Supervisor escalations go to `pm-infra`, not CEO directly
- Outbox content includes ROI estimates on all items
- No files are modified outside owned scope (`sessions/qa-infra/**`)
- Idle items do not create new inbox items for self (compliant with 2026-02-22 directive)

### Where I deviate (gaps)

#### Gap 1: Blocker research protocol not fully applied on URL audits
The org-wide blocker research protocol (added to `org-wide.instructions.md`) requires: read docs in expected places → broaden search → write missing docs → then escalate. For the three URL audits, I escalated immediately after noting no BASE_URL was in the command, without checking:
- `org-chart/sites/<site>/site.instructions.md` (may contain BASE_URL)
- `sessions/shared-context/` (may have copied env docs)
- `sessions/pm-<site>/artifacts/` (may have env notes from PM work)

**Process change 1 (adopted now):** Before any `needs-info` escalation on a missing-input blocker, explicitly document which of those three locations I checked and what I found. If site instructions exist with a BASE_URL, use it without escalating.

#### Gap 2: Site instructions files not checked — may have BASE_URL for URL audit sites
I checked `sessions/pm-<site>/outbox/` product documentation for environment URLs but did not check `org-chart/sites/<site>/site.instructions.md` (per the new instruction stack layer). These files may now exist and contain the BASE_URL needed for all three pending URL audits.

The site instructions exist and confirm all three BASE_URLs are `TBD` — my escalations were correct in content but I should have cited these files explicitly rather than relying solely on PM outbox checks. This confirms Gap 1 was a process gap in documentation, not a wrong answer.

---

Continuing the self-audit outbox:

#### Gap 3: Idle cycle process — infrastructure scope vs full-site audit
The role instructions now specify that QA idle behavior should be a **continuous full-site audit** for the agent's product site. `qa-infra` scope is infrastructure (HQ scripts/runbooks), not a product site — there is no `BASE_URL` or site to crawl. The idle behavior I have been doing (infrastructure file reviews) is the correct fallback for this scope but the seat instructions do not explicitly state this. This is an instructions gap, not a behavioral gap.

**Process change 2 (adopted now — self-improvement):** Update `org-chart/agents/instructions/qa-infra.instructions.md` to explicitly state that the continuous full-site audit idle behavior does not apply (infrastructure scope, no product site), and that idle behavior is infrastructure file review within `scripts/**` and `runbooks/**` scope (read-only, findings → outbox).

**Process change 3 (requires CEO action):** The three URL audit items (`stlouisintegration`, `theoryofconspiracies`, `thetruthperspective`) were assigned to `qa-infra` but all three sites have `BASE_URL: TBD` in their site instructions and outbound HTTP is unavailable in this environment. These audits cannot progress until either (a) BASE_URLs are filled in and a network-capable runner is provided, or (b) the audits are re-assigned to a seat with network access (e.g., a future `qa-stlouisintegration` seat). Decision needed from CEO.

## Process changes adopted now

### Change 1: Blocker research protocol compliance
Before any `needs-info` escalation on missing inputs, I will explicitly document:
- `org-chart/sites/<site>/site.instructions.md` — checked? (result)
- `sessions/shared-context/` — checked? (result)
- `sessions/pm-<site>/artifacts/` — checked? (result)

### Change 2: Seat instructions update (self-improvement — owned file)
Adding the following to `org-chart/agents/instructions/qa-infra.instructions.md`:

```diff
+## Idle behavior (qa-infra specific)
+- `qa-infra` scope is infrastructure (HQ scripts/runbooks), not a product site.
+- The "continuous full-site audit" idle behavior in tester.instructions.md does NOT apply.
+- Idle behavior: perform a short read-only verification/review pass on infra files
+  (scripts/**, runbooks/**), write findings and minimal diff recommendations to outbox,
+  and escalate to pm-infra if action is needed. Do NOT create new inbox items for self.
```

Executor: please apply this diff to `org-chart/agents/instructions/qa-infra.instructions.md`.

## Decision needed (requires CEO/supervisor action)

The three URL audit inbox items cannot be executed:
- All three sites have `BASE_URL: TBD` in `org-chart/sites/<site>/site.instructions.md`
- Outbound HTTP unavailable in this execution environment

**Decision options:**
1. Fill in `BASE_URL` for each site in `org-chart/sites/<site>/site.instructions.md` + provide network access → qa-infra can execute
2. Close these three audit items as premature; re-open when sites are activated
3. Re-assign to a human-assisted runner (CEO runs crawl, pastes results back)

## Recommendation
Close all three as premature (option 2). The sites are not yet activated (`TBD` BASE_URLs confirm this). URL audits against unactivated sites have near-zero ROI. Re-queue them automatically when `BASE_URL` is filled in as part of the site activation checklist.

## Next actions
- Executor: apply seat instructions diff (Change 2) to `org-chart/agents/instructions/qa-infra.instructions.md`
- CEO/pm-infra: decide disposition of the three pending URL audit items (close as premature vs provide BASE_URLs)
- qa-infra: no other blockers; future idle cycles follow updated seat instructions

## Blockers
- None for this self-audit item

## Needs from Supervisor
- Decision on disposition of three pending URL audit items (stlouisintegration, theoryofconspiracies, thetruthperspective) — close as premature or provide BASE_URLs + execution method

## Decision needed
- Are the three URL audit sites active/live? If not, close audits as premature and add BASE_URL population to the site activation checklist.

## ROI estimate
- ROI: 6
- Rationale: Self-audit uncovered a concrete instructions gap (idle behavior not documented for infrastructure scope) that affects every future idle cycle, and identified that three audit items are permanently blocked by missing site activation data. Fixing both prevents repeated `needs-info` escalations for the same root cause. The seat instructions update is a one-minute edit with lasting value.
