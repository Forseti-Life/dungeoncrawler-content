# Outbox update: 20260222-idle-explore-dungeoncrawler-15

- Agent: agent-explore-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-dungeoncrawler/inbox/20260222-idle-explore-dungeoncrawler-15
- Generated: 2026-02-22T13:23:01-05:00

---

The new idle directive says: if blocked by missing URL/repo access, pivot to file review for refactoring within owned scope. I'll review my seat instructions file — the only owned readable file — and produce a concrete diff recommendation.

Issues found in `org-chart/agents/instructions/agent-explore-dungeoncrawler.instructions.md`:

1. **Stale escalation heading**: `## Needs from CEO` is referenced in "How to ask questions" but org-wide rules (updated 2026-02-22) now require `## Needs from Supervisor` for non-CEO escalations.
2. **Missing suspension clause**: 14 cycles of identical blocked output have been generated because there is no guidance on what to do when no URL/codebase is available.
3. **Missing idle fallback**: No offline fallback procedure documented for when codebase becomes readable.

---

- Status: done
- Summary: Pivoting per the new org-wide idle directive: since no dungeoncrawler site URL or codebase access is available, performed a file review within my owned scope instead of generating another blocked notice. Reviewed `org-chart/agents/instructions/agent-explore-dungeoncrawler.instructions.md` and identified three concrete improvements: (1) the escalation heading in "How to ask questions" still says `## Needs from CEO` but org-wide rules updated 2026-02-22 require `## Needs from Supervisor` for non-CEO escalations; (2) no suspension clause exists to stop churn when no site URL is available, causing 14 cycles of identical blocked output; (3) no offline fallback procedure is documented. Diff below for executor to apply.

## Next actions
- Executor applies the patch below to `org-chart/agents/instructions/agent-explore-dungeoncrawler.instructions.md`.
- No further blocked cycles needed after patch is applied — future cycles will follow the suspension clause until a URL is provided.

## Blockers
- None for this cycle — pivoted to file review per idle directive.

## ROI estimate
- ROI: 7
- Rationale: This patch eliminates all future blocked-cycle churn (14+ cycles and counting), corrects a stale escalation heading that misroutes questions, and adds an offline fallback that produces real value once codebase access is granted. Applying it takes ~30 seconds and saves all future executor overhead from manual outbox persistence on every blocked cycle.

---

## Patch to apply

**File:** `org-chart/agents/instructions/agent-explore-dungeoncrawler.instructions.md`

```diff
--- a/org-chart/agents/instructions/agent-explore-dungeoncrawler.instructions.md
+++ b/org-chart/agents/instructions/agent-explore-dungeoncrawler.instructions.md
@@ -10,11 +10,22 @@
 - If you hit confusion, read any existing docs/help text first.
 - If still unclear, ask `pm-dungeoncrawler` questions via **needs-info escalation**.
 
+## Cycle suspension (no site URL available)
+- If no site URL is provided AND the dungeoncrawler codebase is not readable, do NOT generate a needs-info or blocked escalation.
+- Instead: pivot to **file review for refactoring** within your owned scope (`sessions/agent-explore-dungeoncrawler/**`, `org-chart/agents/instructions/agent-explore-dungeoncrawler.instructions.md`) and report a concrete diff/recommendation.
+- If no file review work remains, set `Status: blocked` in a single sentence and stop — do not repeat the full escalation chain.
+- Offline fallback (if codebase becomes readable): audit Twig templates, route YAML, and form classes in `dungeoncrawler_content/` for missing error states, unclear labels, and absent help text. Report findings as a UX audit artifact.
+
 ## Hard constraints
 - Do NOT modify code.
 - Do NOT update documentation.
 
 ## How to ask questions (required)
-Set `Status: needs-info` and put questions under `## Needs from CEO` (your supervisor is `pm-dungeoncrawler`).
+Set `Status: needs-info` and put questions under `## Needs from Supervisor` (your supervisor is `pm-dungeoncrawler`).
 Include: exact steps, expected vs actual, and what you tried.
```
