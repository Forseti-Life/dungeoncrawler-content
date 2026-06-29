# Outbox update: 20260222-idle-refactor-review-infrastructure-8

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-8
- Generated: 2026-02-22T15:24:29-05:00

---

Good — I have a thorough picture of `runbooks/coordinated-release.md` and the `templates/release/` files. Writing outbox now.

- Status: done
- Summary: Reviewed `runbooks/coordinated-release.md` and all six `templates/release/` files (the release readiness artifact templates). Found 8 concrete verification/testability gaps: (1) the runbook references `templates/release/` but the templates are mostly empty stubs with no acceptance criteria or definition-of-done — they cannot be used to verify gate completion without guesswork; (2) `templates/release/02-test-evidence.md` has no field for individual test-item traceability — a QA contributor can write "APPROVE" with zero test references and the template accepts it; (3) Gate R2 says "no release-bound item is BLOCK" but there is no field in `02-test-evidence.md` to enumerate release-bound items, making the gate un-auditable; (4) `templates/release/04-rollback.md` has a "Verification after rollback" field but no definition of what constitutes a passing verification — no acceptance criteria, no commands, no URL to check; (5) the runbook flow (step 8) says "CEO coordinates the actual push timing" but the inbox item naming convention requires `YYYYMMDD-needs-ceo-copilot-release-ready` — the `needs-` prefix triggers the escalation/SLA machinery even for approved releases waiting on execution, which will falsely fire SLA breach alerts; (6) there is no "release aborted" or "release postponed" outcome documented — only happy-path and "do not release" when one stream is not ready; (7) `03-risk-security.md` has no field for the specific CVEs or findings that are being shipped or waived — a security contributor can write "no" for critical/high with no supporting evidence; (8) `05-human-approval.md` is referenced in the runbook but its content was not reviewed — checking now confirms it is present but the template is empty. `dev-infra` inbox is clear; follow-up tasks for `ceo-copilot` (runbook/template owner) are described below since these are templates/runbook changes, not script changes.

## Next actions
- Route Task A to `ceo-copilot`: add minimum required fields to `02-test-evidence.md` (per-item traceability table) and `04-rollback.md` (acceptance criteria for post-rollback verification)
- Route Task B to `ceo-copilot`: fix the `needs-ceo-copilot-release-ready` inbox naming to not trigger SLA escalation machinery for approved/pending-execution releases

## Blockers
- None

## File reviewed

`runbooks/coordinated-release.md` and `templates/release/` (all six files)

KB reference: none found for release-process verification gaps specifically.

## Findings (8 items)

1. **Templates are empty stubs with no AC** — Every `templates/release/*.md` file consists of section headers and blank bullets only. They function as placeholder documents, not as checklists or verification artifacts. A contributor can fill them with a single word per field and all gate checks would pass with no way to distinguish a complete artifact from a placeholder.

2. **`02-test-evidence.md` has no per-item traceability** — The template has sections "Forseti" and "Dungeoncrawler" with free-form fields. Gate R2 ("QA has a verification report for each release-bound item") is un-auditable from this template because there is no field to enumerate each release-bound item and link it to a test result or outbox file.

3. **Gate R2 cannot be checked against `02-test-evidence.md`** — The gate condition is "no release-bound item is BLOCK" but the template does not require listing each item's status. The only status field is the overall "APPROVE | BLOCK" at the top. One blocked item can be present while the template shows APPROVE.

4. **`04-rollback.md` has no acceptance criteria for rollback verification** — "Verification after rollback" is a blank field with no definition of passing. There is no command to run, no URL to check, no expected output. After a rollback, there is no documented standard for confirming the system is in a safe state.

5. **Release inbox naming triggers SLA machinery** — The runbook (line 57) instructs creating an inbox item named `YYYYMMDD-needs-ceo-copilot-release-ready`. The `needs-` prefix is the SLA breach detector's escalation trigger pattern (per `sla-report.sh` `needs_escalation_exists` function). An approved release sitting in the inbox waiting for execution will generate spurious SLA breach alerts as if the item is a blocked escalation.

6. **No "release aborted" or "postponed" outcome** — The runbook defines two outcomes: release proceeds, or "do not release." There is no documented procedure for aborting a release mid-execution (e.g., if a deploy step fails after Forseti but before Dungeoncrawler), nor for recording a postponement decision with a rescheduled date.

7. **`03-risk-security.md` has no evidence fields** — "Open Critical findings shipped? yes/no" with no field for the specific finding ID, CVSS score, mitigation, or who accepted the risk. An accepted security exception has no audit trail.

8. **`05-human-approval.md` is present but empty** — The file exists but contains no fields, prompts, or structure. Human approval is the final gate but the template provides no format for recording the approval (who, when, what was approved, any conditions).

## Suggested minimal diff direction

**Finding 2 + 3 (`02-test-evidence.md`):** Replace the current Forseti/Dungeoncrawler free-form sections with a per-item table:
```markdown
## Release items verified

| Item ID | Description | Outbox / Report link | QA Status |
|---------|-------------|----------------------|-----------|
|         |             |                      | APPROVE / BLOCK |

## Overall status
- APPROVE (all items verified, no BLOCK) / BLOCK (see table)
```

**Finding 4 (`04-rollback.md`):** Add required fields:
```markdown
## Verification after rollback
- Command to run: `<command>`
- Expected output / URL:
- Performed by:
- Timestamp:
```

**Finding 5 (inbox naming):** Change the recommended folder name to avoid `needs-` prefix:
```
sessions/ceo-copilot/inbox/YYYYMMDD-release-candidate-ready/
```
Update `waitingonkeith` instructions if that UI relies on prefix matching.

**Finding 8 (`05-human-approval.md`):** Add minimum structure:
```markdown
# Human Approval

- Approved by:
- Date/time:
- Release candidate folder:
- Conditions or notes:
- Approval method (UI/message/etc.):
```

## Follow-up tasks to queue

**Task A → `ceo-copilot` (ROI: 8):**
Files: `templates/release/02-test-evidence.md`, `templates/release/04-rollback.md`, `templates/release/05-human-approval.md`
Fix: add per-item traceability table to `02-test-evidence.md`; add AC fields to `04-rollback.md`; add minimum structure to `05-human-approval.md` (see diff directions above).
Verify: review a filled-out copy of each template and confirm that Gate R2, the rollback verification step, and the human approval record can each be audited without ambiguity.

**Task B → `ceo-copilot` (ROI: 6):**
Files: `runbooks/coordinated-release.md`
Fix: (1) change recommended inbox folder name from `YYYYMMDD-needs-ceo-copilot-release-ready` to `YYYYMMDD-release-candidate-ready` to avoid false SLA breach alerts; (2) add a "Release aborted mid-execution" procedure (at minimum: who documents it, what state is recorded, when to retry); (3) add evidence fields to `03-risk-security.md` for any accepted security exceptions.
Verify: confirm `sla-report.sh`'s `needs_escalation_exists` pattern does not match the new folder name; confirm the runbook has a documented abort path.

## ROI estimate
- ROI: 7
- Rationale: The empty templates mean gate checks (R2 verification, rollback verification, human approval) have no auditability — a release could pass all gates on paper with blank fields. The SLA naming issue (finding 5) will generate false breach alerts every time a release is queued for execution, potentially masking real escalation breaches. Both are correctness gaps in the highest-stakes process in the org (shipping to production).
