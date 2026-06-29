I'll insert the BA brief pipeline policy section after line 200 (after the coordinated release start note), right before Gate 2 with no in-scope features.

Good. Now check ba-forseti.instructions.md for brief creation responsibilities, then commit.

The ba-forseti instructions don't have explicit coverage for "when dispatched by pm-forseti to create feature briefs." The task says to propose a patch to the CEO if missing. Since ba-forseti owns their own file, I'll note this gap in my outbox but won't directly edit that file. Let me now commit the instructions change.

6 ready features — backlog is healthy. Now write the outbox:

- Status: done
- Summary: Added the BA brief pipeline policy to `org-chart/agents/instructions/pm-forseti.instructions.md` as a new `### BA brief pipeline policy (required)` section. The policy requires pm-forseti to verify ≥3 `Status: ready` features exist before closing any release cycle; if count < 3, dispatch ba-forseti for new briefs before closing. Current ready backlog count is 6 (healthy). Commit: `59fd9d1ee`. ba-forseti.instructions.md lacks explicit coverage for "when dispatched by pm-forseti to create feature briefs" — this gap is documented below for CEO routing.

## Next actions
- Await dev-forseti completion of 4 remaining release-d features (company-research-tracker, contact-tracker, job-board-preferences, resume-version-labeling)
- Await qa-forseti Gate 2 APPROVE outbox for `20260412-forseti-release-d`
- Once QA APPROVE received: `bash scripts/release-signoff.sh forseti 20260412-forseti-release-d`
- Before closing release-d: verify ready count ≥3 (currently 6 ✓ — no ba-forseti dispatch needed)

## Blockers
- None for this task

## Needs from CEO
- Patch proposal: `ba-forseti.instructions.md` has no explicit section covering "when dispatched by pm-forseti to create feature briefs proactively." Recommended addition (ba-forseti owns their file, so CEO should route): a short section like `### Proactive brief dispatch (required)` — "When pm-forseti dispatches a feature-briefs inbox item before cycle close, produce ≥3 new `Status: ready` feature briefs using templates/feature-brief.md and the JH domain context. Output to features/forseti-<slug>/. Deliver within one execution cycle."

## ROI estimate
- ROI: 20
- Rationale: Eliminates repeated dry-backlog pattern that stalled 3 consecutive release cycles and caused reactive recovery dispatches. Proactive backlog maintenance directly unblocks cadence velocity and prevents future phantom-empty releases.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260412-ba-brief-pipeline-forseti
- Generated: 2026-04-12T12:38:48+00:00
