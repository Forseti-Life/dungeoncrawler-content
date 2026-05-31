# Outbox update: 20260221-idle-refactor-dev

- Agent: agent-explore
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore/inbox/20260221-idle-refactor-dev
- Generated: 2026-02-21T23:52:25-05:00

---

Now I have enough context to produce a refactor. I'll target `runbooks/continuous-improvement.md`: the final section `## Mandatory: instructions.md` is a dev-role instruction that duplicates content already in `software-developer.instructions.md`, making it out of place in the CI loop runbook. The safe refactor replaces it with a focused `## See also` cross-reference section.

---

- Status: done
- Summary: Completed a safe, minimal refactor of `runbooks/continuous-improvement.md` in the HQ repo. The file's final section (`## Mandatory: instructions.md`) is a dev-role instruction (read target repo instructions before coding) that duplicates content already present in `org-chart/roles/software-developer.instructions.md` under "Required reading: target repo instructions." It is out of place in a CI loop runbook. The refactor removes it and replaces it with a focused `## See also` cross-reference pointing to the relevant canonical sources. No behavior changes, no code touched, no side effects — HQ runbooks are documentation only.

## Next actions
- Executor applies the patch below to `runbooks/continuous-improvement.md`
- No tests to run (documentation-only file)
- Optional: CEO may want to grep for other runbooks that repeat dev-role instructions and consolidate similarly

## Blockers
- None

## Needs from CEO
- None

---

## Patch

**File:** `runbooks/continuous-improvement.md`

```diff
--- a/runbooks/continuous-improvement.md
+++ b/runbooks/continuous-improvement.md
@@ -19,5 +19,7 @@ Create a Lesson Learned when:
 3. CEO triages proposals:
    - accept (prepare patch for human approval)
    - request revision
    - reject (document rationale)
 
-## Mandatory: instructions.md
-Every agent must read the target repo instructions before coding and reference it in their Implementation Notes / Test Plan.
+## See also
+- Dev role instructions (required reading before coding): `org-chart/roles/software-developer.instructions.md`
+- KB lesson template: `templates/lesson-learned.md`
+- Instructions-change proposal template: `templates/instructions-change-proposal.md`
```

**Why it's safe:**
- Documentation-only file; no code, no config, no schema.
- The removed sentence is fully covered in `software-developer.instructions.md` ("Required reading: target repo instructions") — no information is lost, only the duplication.
- The added `See also` links improve navigability and are consistent with how other runbooks cross-reference (e.g., `runbooks/command-intake.md` → `runbooks/inbox-mode.md`).
- `git revert` is trivially the rollback.

**KB references:** None found specifically for runbook duplication cleanup; this is a first occurrence.
