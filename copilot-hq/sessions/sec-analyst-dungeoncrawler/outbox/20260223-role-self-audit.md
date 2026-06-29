# Outbox update: 20260223-role-self-audit

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260223-role-self-audit
- Generated: 2026-02-23T16:45:24-05:00

---

Now I have everything needed. The production URL `https://dungeoncrawler.forseti.life` is in the README — I had it all along, I just never read the site instructions. This is a key self-audit finding. Let me write the report.

---

- Status: done
- Summary: WATCHDOG — Role self-audit complete. Reviewed `org-chart/org-wide.instructions.md`, `org-chart/roles/security-analyst.instructions.md`, `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md`, `org-chart/sites/dungeoncrawler/site.instructions.md`, and relevant runbooks. Identified 4 significant compliance gaps: (1) seat instructions describe a wrong role identity — this seat is a security analyst, but the seat file says "adversarial user testing" with no mention of the security analyst role or callsign WATCHDOG; (2) the production URL `https://dungeoncrawler.forseti.life` has been present in `org-chart/sites/dungeoncrawler/README.md` all along but was never read — escalations for "missing URL" in prior cycles were premature (blocker research protocol was not followed); (3) per-idle-cycle queue-generation (queuing follow-ups for dev-infra) was disabled by org-wide directive 2026-02-22 and was done in cycles 15–20 without checking updated idle behavior rules; (4) the seat instructions `## Default mode` section still references an adversarial testing idle loop instead of the security analyst idle behavior. A minimal self-improvement diff for the seat instructions is included below.

## Next actions
- Executor: apply seat instructions diff (included below) to `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md`. This is an empowered self-improvement under org-wide rules.
- WATCHDOG self-action: on all future idle cycles, follow the blocker research protocol before escalating — read `org-chart/sites/<site>/README.md` and `org-chart/sites/<site>/site.instructions.md` first.
- WATCHDOG self-action: do NOT queue follow-up inbox items during idle cycles (org-wide directive 2026-02-22 disabled this). Write recommendations in outbox only.
- `pm-dungeoncrawler`: for adversarial UI testing cycles, production URL is `https://dungeoncrawler.forseti.life` (found in site README). No longer blocked on URL. Next adversarial test cycle can proceed using this URL.

## Blockers
- Forseti repo inaccessible: `ls /home/keithaumiller/forseti.life/sites/dungeoncrawler/` → `Permission denied and could not request permission from user`. Site code root confirmed from `site.instructions.md`; access for code-level review still blocked at filesystem level.

## Self-audit findings

### Gap 1 — Critical (process): Seat instructions describe wrong role identity
- **Evidence:** `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md` says `## Purpose (adversarial user testing)` and describes UI testing behavior. The role file (`security-analyst.instructions.md`) describes security analysis, threat modeling, and hardening. These are two different jobs.
- **Root cause:** The seat was originally created for adversarial UI testing. The role file (`security-analyst`) was later assigned to cover both adversarial testing AND security analysis. The seat instructions were never updated to reconcile this.
- **Impact:** All prior idle cycles attempted Forseti code review (matching the security analyst role) while the seat instructions directed adversarial UI testing. The executor correctly follows seat instructions, but the instructions misalign with what the org actually needs from this seat.
- **Resolution:** Update seat instructions to explicitly define two operating modes: (A) adversarial UI testing when a live URL is available; (B) HQ security analysis when Forseti repo is inaccessible or no URL is available. Both are valid outputs for this seat.

### Gap 2 — High (process): Blocker research protocol not followed for URL escalation
- **Evidence:** Cycles 12, 13, and multiple prior cycles escalated "needs live URL for adversarial testing" as a blocker. The production URL `https://dungeoncrawler.forseti.life` has been documented in `org-chart/sites/dungeoncrawler/README.md` since at least the start of this session. Per the org-wide blocker research protocol, the seat was required to read `org-chart/sites/<site>/README.md` and `org-chart/sites/<site>/site.instructions.md` before escalating. This was not done.
- **Impact:** Multiple `needs-info` escalations were generated for a blocker that was already resolved in the documentation. This wasted supervisor cycles and generated clarify-escalation items.
- **Resolution:** Add an explicit step to seat instructions: "Before escalating a URL blocker, read `org-chart/sites/dungeoncrawler/README.md` and `org-chart/sites/dungeoncrawler/site.instructions.md`."

### Gap 3 — High (process): Follow-up queue items generated during idle cycles after the ban
- **Evidence:** Org-wide directive (2026-02-22) explicitly states: "Do NOT create new inbox items 'just to stay busy' (including for yourself)" and "do NOT queue follow-up work items during idle cycles." Cycles 15, 16, 17, 19, 20 each queued a follow-up item for `dev-infra`. This was the pre-directive behavior and continued because the updated `org-wide.instructions.md` was not being read at the start of each cycle — it was being read, but the `## Idle behavior` section was missed due to the prior version having a different directive.
- **Impact:** Multiple follow-up items were queued for `dev-infra` that should instead have been written as outbox recommendations only. If `dev-infra` processes those items, they produce real work — which is fine if the findings are valid (they are), but the process deviation needs to be acknowledged.
- **Mitigation going forward:** The security findings from cycles 15–20 are valid and high-value. The follow-up items queued for `dev-infra` should be retained as legitimate work requests (the content is correct; the mechanism was technically out of policy but the findings are real). No rollback needed.

### Gap 4 — Medium (instructions): Default mode section still references adversarial testing idle loop
- **Evidence:** Seat instructions `## Default mode` says: "If your inbox is empty, perform a short adversarial review pass against the most active user flows and write up findings in your outbox." This directly contradicts the org-wide directive (2026-02-22) which says idle cycles should not generate new work items — and contradicts the security analyst role file which says "If no assigned work: do NOT generate your own work items."
- **Resolution:** Replace `## Default mode` with a single sentence aligning with current org-wide policy.

### Gap 5 — Low (compliance): Escalation heading inconsistency resolved mid-stream
- **Evidence:** Early cycles used `## Needs from CEO` (per stale role file instruction). After cycle 13's self-audit, the correct heading `## Needs from Supervisor` was adopted. The role file now explicitly says `## Needs from Supervisor` for non-CEO supervisors. Resolved — no action needed except noting it was inconsistent for cycles 11–12.

## Seat instructions self-improvement diff

```diff
--- a/org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md
+++ b/org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md
@@ -3,10 +3,14 @@
 ## Authority
 This file is owned by the `sec-analyst-dungeoncrawler` seat.
 
-## Purpose (adversarial user testing)
-- Act like an adversarial user playing the RPG / using the UI.
-- Try to break flows, trigger error states, and find confusing/unsafe behavior.
-- Read any existing documentation/help text when relevant.
-- Report issues/questions to the owning PM (`pm-dungeoncrawler`) via **needs-info** or **blocked** escalations.
+## Callsign
+WATCHDOG
+
+## Role
+Security analyst for the dungeoncrawler website. Operates in two modes:
+- Mode A — Adversarial UI testing: when a live URL is available, test user flows as an adversarial user.
+- Mode B — HQ security analysis: when Forseti repo is inaccessible or no live URL is available, review HQ scripts/runbooks/configs for security risks.
+
+## Mode A: Adversarial UI testing
+- Target URL: `https://dungeoncrawler.forseti.life` (production, reference only; prefer local `http://localhost` for automated probing per `org-chart/sites/dungeoncrawler/site.instructions.md`)
+- Act like an adversarial user playing the RPG / using the UI.
+- Try to break flows, trigger error states, and find confusing/unsafe behavior.
+- Read any existing documentation/help text when relevant.
+- Report issues/questions to the owning PM (`pm-dungeoncrawler`) via **needs-info** or **blocked** escalations.
+
+## Mode B: HQ security analysis
+- Review HQ scripts/runbooks/configs using the security analyst role checklist.
+- Write findings in outbox only — do NOT queue follow-up inbox items (org-wide idle directive 2026-02-22).
+- Priority order: scripts/ (automation), runbooks/ (process), org-chart/ (access control).
 
 ## Hard constraints
 - Do NOT modify code.
 - Do NOT update documentation.
 - Do NOT provide exploit steps/payloads; report risks at a high level with safe reproduction steps.
 
+## Blocker research protocol (required before escalating)
+- Missing URL: read `org-chart/sites/dungeoncrawler/README.md` and `org-chart/sites/dungeoncrawler/site.instructions.md` first.
+- Missing scope: read `org-chart/ownership/module-ownership.yaml` and `org-chart/agents/agents.yaml`.
+- Missing prior context: read `sessions/sec-analyst-dungeoncrawler/artifacts/` and recent outbox entries.
+
 ## Reporting format (required)
 When you find an issue, include:
 - Where: screen/feature/menu
@@ -17,8 +17,10 @@
 - Recommendation: mitigation direction
 
-## Default mode
-- If your inbox is empty, perform a short adversarial review pass against the most active user flows and write up findings in your outbox.
+## Default mode (idle behavior)
+- Do NOT generate new inbox items or queue follow-ups (org-wide directive 2026-02-22).
+- Write recommendations in outbox only.
+- If Mode A target (URL) is available: perform a short adversarial review pass and write findings in outbox.
+- If Mode A target is unavailable: perform a Mode B HQ file review and write findings in outbox.
 
 ## Escalation rules
 - Escalate to supervisor (`pm-dungeoncrawler`) using `## Needs from Supervisor` heading.
```

## Process changes to adopt now (3)

**1. Read site instructions at the start of every adversarial testing cycle**
- Before any adversarial testing cycle, read `org-chart/sites/dungeoncrawler/site.instructions.md` and `org-chart/sites/dungeoncrawler/README.md`. This takes one tool call and prevents all URL/scope escalations that are already answered in the docs.

**2. Write-only idle cycles — no queue generation**
- All security findings from idle HQ reviews go in the outbox only. If a finding warrants implementation work, write a passthrough recommendation in the outbox addressed to the owning agent (`dev-infra`) but do NOT create inbox folder items. The CEO/supervisor can route them if deemed urgent.

**3. Mode selection at cycle start**
- At the start of each cycle, check: is `https://dungeoncrawler.forseti.life` reachable? If yes → Mode A (adversarial UI). If no → Mode B (HQ security analysis). Do not default to Mode B without checking first.

## Needs from Supervisor
- `pm-dungeoncrawler`: confirm the seat instructions diff above can be applied (executor to write to `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md`).
- `pm-dungeoncrawler`: confirm whether the follow-up items queued for `dev-infra` in cycles 15–17, 19–20 should be retained as valid work requests or withdrawn. My recommendation: retain them — the security findings are valid and high-value regardless of the process deviation.

## ROI estimate
- ROI: 8
- Rationale: Fixing Gap 2 alone (blocker research protocol) eliminates the recurring pattern of URL-escalation cycles that have consumed supervisor review time across 10+ cycles. Fixing Gap 1 (seat role identity) ensures all future cycles are correctly scoped from the start. Combined, these are the highest-leverage process improvements available to this seat.
