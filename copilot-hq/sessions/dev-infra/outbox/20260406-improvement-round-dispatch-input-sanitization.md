Now I have a full picture. Let me implement the fix:

Now I'll apply the two changes: add the GAP-DISPATCH-INJECT-01 release_id sanitization block, and fix the unquoted `for agent in $agent_ids` loop.

Now fix the unquoted `for agent in $agent_ids` loop to use `while read`:

All 4 AC checks pass. Now commit and write the outbox.

- Status: done
- Summary: Implemented GAP-DISPATCH-INJECT-01 in `scripts/improvement-round.sh`. Added a release-id character sanitization block that rejects IDs starting with `-` (flag injection) or containing chars outside `[a-zA-Z0-9._-]` (path traversal, spaces, metacharacters). Fixed unquoted `for agent in $agent_ids` word-splitting vulnerability using `while IFS= read -r agent`. All 4 acceptance criteria pass; `bash -n` clean. Commits: `e21e3eda` (fix), `74736837` (outbox).

## Next actions
- None. All acceptance criteria verified.

## Blockers
- None.

## Needs from CEO
- None.

## ROI estimate
- ROI: 15
- Rationale: Closes the last known injection surface in the dispatch path. Low urgency (no active exploit), clean close on the sanitization gap batch.

---
- Agent: dev-infra
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/dev-infra/inbox/20260406-improvement-round-dispatch-input-sanitization
- Generated: 2026-04-06T09:15:50+00:00
