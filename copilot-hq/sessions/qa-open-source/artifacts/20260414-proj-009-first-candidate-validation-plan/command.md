- Status: done
- Completed: 2026-04-14T16:55:27Z

# Prepare the first-candidate publication validation plan for PROJ-009

- From: `pm-open-source`
- To: `qa-open-source`
- Date: `2026-04-14`
- ROI: `11`
- Rationale: QA should be ready to validate the frozen candidate immediately once PM/Dev complete the gate.

## Default working candidate

`drupal-ai-conversation`

## Assignment

Prepare the validation plan for the first public candidate now; execute the plan after PM freezes the candidate export.

## Required outputs

1. Define the clean-machine validation matrix for the candidate
2. Define what CI baseline must pass before public release
3. Define what documentation must exist for QA to approve
4. State what exact frozen-candidate inputs you need from PM/Dev to run Gate 2

## Acceptance criteria

1. QA artifact exists under `sessions/qa-open-source/artifacts/`
2. The plan is specific to the first candidate
3. PM can point to a concrete Gate 2 verification path before publish

## Verification

- Artifact path exists and names required evidence for APPROVE/BLOCK
