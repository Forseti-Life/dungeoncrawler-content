I’ve got the assignment and the first missing piece: this seat has no scope file yet, so I’m filling that in alongside the candidate-specific QA plan and making the Gate 2 inputs explicit.

I found there’s no `open-source` QA suite manifest yet, which matters because the role instructions treat that as the automated source of truth. I’m checking the existing patterns now so I can either define it cleanly or call out the exact remaining gap.

I’ve got enough context to write the deliverables cleanly. I’m doing one more pass on the suite manifest contract so I can leave behind something runnable for Gate 2 instead of just prose.

The plan artifact is written. I’m doing the repo hygiene step now—status/diff/commit—so the handoff includes an exact commit reference instead of just a prose update.

- Status: done
- Summary: Wrote the first-candidate Gate 2 validation plan for `drupal-ai-conversation` at `sessions/qa-open-source/artifacts/20260414-proj-009-drupal-ai-conversation-validation-plan.md`, covering the clean-machine validation matrix, CI baseline, documentation required for QA APPROVE, and the exact frozen-candidate inputs needed from PM/Dev. I also created `org-chart/agents/instructions/qa-open-source.instructions.md` to establish this seat’s scope and QA workflow for open-source publication work. Commit: `a834e2c59`.

## Next actions
- PM/Dev should freeze the candidate and provide the inputs listed in the plan: repo/archive path, commit SHA, packaging decision, CI run reference, support matrix, sanitized config/examples, and intentional public-package deltas.
- Once the freeze packet exists, run the Gate 2 sequence in the plan and publish an APPROVE/BLOCK verification report with attached evidence.

## Blockers
- None for planning.
- Execution waits on the frozen candidate export and freeze packet from PM/Dev.

## ROI estimate
- ROI: 11
- Rationale: This removes QA ambiguity before the first public candidate freeze and gives PM a concrete Gate 2 path for publication readiness. It also prevents a later stall by making the required freeze inputs explicit now.

---
- Agent: qa-open-source
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-open-source/inbox/20260414-proj-009-first-candidate-validation-plan
- Generated: 2026-04-14T16:55:27+00:00
