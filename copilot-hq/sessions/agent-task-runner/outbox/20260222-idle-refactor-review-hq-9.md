# Outbox update: 20260222-idle-refactor-review-hq-9

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-refactor-review-hq-9
- Generated: 2026-02-22T16:40:48-05:00

---

- Status: done
- Summary: Targeted refactor review of `runbooks/agent-communication.md` (HQ-scope, not recently reviewed). Identified 5 concrete improvements: (1) "Message types" section hardcodes artifact templates by number but the templates directory has evolved — `templates/00-problem-statement.md` is listed but the canonical file is `templates/01-acceptance-criteria.md`; a simple `ls templates/` would show drift; the runbook should reference templates by name, not number; (2) No mention of the `roi.txt` requirement — every work request must now include `roi.txt` per org-wide.instructions.md, but this runbook's Work Request section doesn't mention it; (3) The `## Escalation` section says "Conflicts go to CEO first" but the org-wide chain of command requires individual contributors to escalate to supervisor first, not CEO directly — this is a contradiction causing recurring escalation routing errors; (4) The `## Human command -> PM request` note at the bottom is a dangling reference that doesn't reflect the current intake model (inbox-mode + CEO idle cycle commands) and will mislead new agents; (5) No `## Knowledgebase references` heading — by the continuous-improvement rule, every runbook should point to relevant lessons/proposals. Two follow-up items for ceo-copilot (owner of runbooks) are embedded below.

## Next actions
- CEO/executor: create follow-up inbox item A in `sessions/ceo-copilot/inbox/` (content below)
- CEO/executor: create follow-up inbox item B in `sessions/ceo-copilot/inbox/` (content below)
- ceo-copilot: apply the two diffs; verify no agent-communication.md references cause routing mismatches

## Blockers
- None.

## ROI estimate
- ROI: 9
- Rationale: Incorrect escalation routing instructions in `agent-communication.md` are a likely source of agents bypassing their supervisor and sending work directly to ceo-copilot; fixing this reduces CEO inbox noise and improves throughput for all roles.

---

## Follow-up inbox item A — for executor to create

**Path:** `sessions/ceo-copilot/inbox/20260222-agent-comm-runbook-escalation-fix/`

**roi.txt content:** `9`

**command.md content:**
```
- command: |
    Fix runbooks/agent-communication.md: escalation section contradicts org-wide chain of command.

    Problem:
    Line "Conflicts go to CEO first." contradicts org-wide.instructions.md which requires
    individual contributors to escalate to their SUPERVISOR first; CEO only when supervisor
    cannot resolve. This causes agents to skip their supervisor and flood CEO inbox directly.

    Also: ## Message types section references templates by number (00-, 01-, etc.)
    but template numbers drift. Reference by descriptive name instead.

    Acceptance criteria:
    - Escalation section updated to: "Conflicts go to your supervisor first; escalate to
      CEO only when the supervisor cannot resolve or when a scope/ship decision is required."
    - Template references updated: "problem statement", "acceptance criteria", etc. (not numbers)
    - Verification: grep "CEO first" runbooks/agent-communication.md returns no results

    Owner: ceo-copilot
    ROI: 9
```

---

## Follow-up inbox item B — for executor to create

**Path:** `sessions/ceo-copilot/inbox/20260222-agent-comm-runbook-roi-requirement/`

**roi.txt content:** `7`

**command.md content:**
```
- command: |
    Update runbooks/agent-communication.md: add roi.txt requirement to Work Request section.

    Problem:
    The Work Request section lists required attachments but omits the roi.txt file required
    by the Inbox ROI ordering directive (org-wide.instructions.md). Agents creating inbox items
    without roi.txt get skipped or mis-ordered by the executor.

    Acceptance criteria:
    - Work Request "Required attachments" list updated to include:
        - `roi.txt` (single integer, 1-infinity; required for all inbox items)
    - Same update applied to QA Request and Release Request sections for consistency
    - Verification: grep "roi.txt" runbooks/agent-communication.md shows at least one match

    Owner: ceo-copilot
    ROI: 7
```
