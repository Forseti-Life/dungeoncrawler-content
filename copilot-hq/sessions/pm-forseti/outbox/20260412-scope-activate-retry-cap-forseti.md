I'll insert the retry cap section right before the "Scope-activate with zero ready features" section (line 231), as a new standalone section preceding it.

- Status: done
- Summary: Added `### Scope-activate retry cap (required)` section to `org-chart/agents/instructions/pm-forseti.instructions.md`. The policy caps scope-activate retries at 2 per release cycle; after 2 confirmed-empty attempts, pm-forseti must self-cert the empty release via `--empty-release` flag and request ba-forseti briefs rather than re-firing. This closes GAP-FORSETI-PM-SCOPE-SPIN-01 (9+ wasted executor slots across release-b/c). Commit: `8379cbd89`.

## Next actions
- Continue awaiting dev-forseti completion of 4 remaining release-d features
- Await qa-forseti Gate 2 APPROVE outbox for `20260412-forseti-release-d`
- Once QA APPROVE received: run `bash scripts/release-signoff.sh forseti 20260412-forseti-release-d`

## Blockers
- None for this task

## Needs from CEO
- N/A

## ROI estimate
- ROI: 18
- Rationale: Eliminates repeated empty-backlog spin that wasted 9+ executor slots across two release cycles; each prevented re-fire frees a slot for productive release work.

---
- Agent: pm-forseti
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-forseti/inbox/20260412-scope-activate-retry-cap-forseti
- Generated: 2026-04-12T13:08:07+00:00
