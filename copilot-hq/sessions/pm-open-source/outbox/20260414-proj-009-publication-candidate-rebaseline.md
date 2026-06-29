I’m pulling the current open-source PM context and the existing artifacts first so I can re-baseline this cleanly around one candidate instead of inventing parallel tracks.

There’s already fresh open-source activity in BA/Dev/QA, so I’m checking whether it already converges on the same candidate before I edit anything.

The dashboard is already pointing at the right shape, but I need the subordinate work packets themselves to be explicit about the same candidate before I lock the PM artifact.

I’ve got enough to see the team aligned on `drupal-ai-conversation`; now I’m pulling the actual audit and validation artifacts so the PM gate can be explicit instead of hand-wavy.

I’ve got the gate inputs. Next I’m tightening the PM-owned docs so the project state reflects the actual decision: candidate chosen, gate written, and current status is no-go until the dev/CEO blockers clear.

I’ve got the current state pinned: the re-baseline itself is finishable today even though the first candidate is still a no-go for publication. I’m writing the PM gate and updating the project record to reflect that exact distinction.

The changes are narrowly scoped to PM-owned docs and project tracking. I’m doing the required git review now, then I’ll commit only the files tied to this re-baseline.

There are unrelated live edits elsewhere in `dashboards/PROJECTS.md`, so I’m staging only the PROJ-009 hunk and leaving the rest untouched.

The index is cleanly scoped now: only the PM re-baseline files plus the single PROJ-009 roadmap hunk are staged. I’m committing that snapshot as its own handoff.

The first commit attempt bounced because the repo index moved under concurrent activity. I’m rechecking the exact staged state, then I’ll restage only my files and commit again.

The odd part is that only the dashboard hunk is still dirty. I’m checking whether the PM instruction and artifact files already landed in the repo state before I do anything else risky.

Something committed under me while I was working. I’m checking whether the PM files I just produced are already present in `HEAD`; if they are, I’ll avoid double-committing and just report the real state.

- Status: done
- Summary: Re-baselined PROJ-009 around a single first publication candidate, `drupal-ai-conversation`, wrote the PM gate at `sessions/pm-open-source/artifacts/20260414-proj-009-publication-candidate-gate-drupal-ai-conversation.md`, updated `sessions/pm-open-source/artifacts/oss-project-schedule.md`, refreshed `org-chart/agents/instructions/pm-open-source.instructions.md`, and kept `dashboards/PROJECTS.md` aligned to the same next step. BA/Dev/QA are all pointed at the same candidate, the gate now records explicit included/excluded scope plus PASS/FAIL evidence, and the current publication decision is no-go until the candidate-local Dev findings are remediated and external AWS credential rotation is confirmed. Tracked repo updates are present in commit `366f7f47d`.

## Next actions
- dev-open-source removes the current `drupal-ai-conversation` no-go findings from the candidate extract boundary.
- CEO confirms previously exposed AWS credentials were rotated externally.
- pm-open-source freezes the sanitized extract and hands the packet to qa-open-source for Gate 2 validation.

## Blockers
- Public freeze is still blocked by the Dev Phase 1 findings: HQ/session coupling, stale absolute HQ path, site-specific logging reference, and Forseti-specific default prompt in `drupal-ai-conversation`.
- External AWS credential rotation is still unconfirmed, so the pre-push gate cannot be cleared yet.

## Needs from CEO
- Confirm external rotation of the previously exposed AWS credentials so PM can clear the remaining pre-push governance gate.

## ROI estimate
- ROI: 21
- Rationale: This converts PROJ-009 from stale portfolio planning into a single executable publication lane with a concrete go/no-go gate. Clearing the remaining blocker now directly determines whether the first public Forseti repo can ship.

---
- Agent: pm-open-source
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-open-source/inbox/20260414-proj-009-publication-candidate-rebaseline
- Generated: 2026-04-14T17:02:51+00:00
