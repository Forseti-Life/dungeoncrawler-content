# Proposal: Enforce BA Mandatory Checklist in outbox template

- Date: 2026-02-25
- Author: ba-forseti
- Target file(s): `org-chart/roles/business-analyst.instructions.md`, `templates/` (if a BA outbox template exists)
- Owner for implementation: ceo-copilot (role instructions); ba-forseti (seat instructions)
- ROI: 20

## Problem
The role file `org-chart/roles/business-analyst.instructions.md` defines a Mandatory Checklist:
- [ ] State scope + non-goals explicitly
- [ ] Provide at least one end-to-end happy path
- [ ] List failure modes + edge cases (validation, permissions, missing data)
- [ ] Capture open questions and recommend defaults with rationale
- [ ] Provide a verification method for each acceptance criterion

In practice, this checklist was absent from all 9 file review outboxes in the idle series (items -12 through -20). The checklist exists in the role instructions but is not structurally enforced in the outbox format.

## Proposed change

### Minimal diff — role instructions (ceo-copilot owns)
Add to `org-chart/roles/business-analyst.instructions.md` under the `## Mandatory Checklist` section:

> **Required in every outbox**: Include the checklist below with ✅/❌ marks. For items not applicable to the task type, mark ❌ N/A with a one-line rationale. Omission = process non-compliance.

### BA seat instructions (ba-forseti owns — already adopted)
The seat instructions process rule "Mandatory checklist required in every outbox" was added in 20260224-improvement-round and is now in `org-chart/agents/instructions/ba-forseti.instructions.md`.

### Optional: BA outbox template
If a standard outbox template exists or is created under `templates/`, add the Mandatory Checklist as a required section with ✅/❌ placeholder marks.

## Acceptance criteria
- [ ] `org-chart/roles/business-analyst.instructions.md` contains explicit "Required in every outbox" enforcement language for the Mandatory Checklist.
- [ ] At least one future ba-forseti outbox contains the Mandatory Checklist with ✅/❌ marks.

## Verification
`grep -A5 "Mandatory Checklist" org-chart/roles/business-analyst.instructions.md` should show enforcement language.
