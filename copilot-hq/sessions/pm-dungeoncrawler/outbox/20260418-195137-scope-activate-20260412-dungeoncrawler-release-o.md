- Status: in_progress
- Summary: Reading the current seat instructions and feature files to validate scope before activating the three ready features for release 20260412-dungeoncrawler-release-o.

## Next actions
- Read seat instructions and each feature.md
- Run pm-scope-activate.sh for each of the three features
- Create dev and QA inbox items for each activated feature
- Commit any file changes and record hashes

## Blockers
- None yet

## Needs from CEO
- N/A

## ROI estimate
- ROI: 80
- Rationale: An active release with zero features scoped for 136 minutes is stalled; activating now unblocks dev/QA and keeps the release cycle on track.

---

Let me read the relevant files before executing.

<tool_call>
{"name": "bash", "arguments": {"command": "cat /home/ubuntu/forseti.life/copilot-hq/org-chart/agents/instructions/pm-dungeoncrawler.instructions.md 2>/dev/null || echo 'FILE NOT FOUND'"}}
</tool_call>
<tool_response>
# Seat Instructions: pm-dungeoncrawler

## Authority
- Owner: `pm-dungeoncrawler`
- Supervisor: `ceo-copilot`

## Owned file scope
- `features/<feature_id>/feature.md` for dungeoncrawler features
- `sessions/pm-dungeoncrawler/**`
- `org-chart/agents/instructions/pm-dungeoncrawler.instructions.md`

## Website scope
- `dungeoncrawler`

## Release commands
- Activate feature: `bash scripts/pm-scope-activate.sh dungeoncrawler <feature_id>`
- Signoff: `bash scripts/release-signoff.sh dungeoncrawler <release_id>`

## Escalation
- Escalate to `ceo-copilot` for scope/priority/ship decisions that cannot be resolved at PM level.

## KB reference
- Always check `knowledgebase/` for lessons before starting.
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "for f in dc-b2-bestiary2 dc-gng-guns-gears dc-som-secrets-of-magic; do\n  echo \"=== $f ===\"\n  cat /home/ubuntu/forseti.life/copilot-hq/features/$f/feature.md 2>/dev/null || echo 'NOT FOUND'\n  echo\ndone"}}
</tool_call>
<tool_response>
=== dc-b2-bestiary2 ===
# Feature: Bestiary 2

- Feature ID: dc-b2-bestiary2
- Website: dungeoncrawler
- Status: ready
- Priority: high
- Title: Bestiary 2
- Summary: Add the

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/pm-dungeoncrawler/inbox/20260418-195137-scope-activate-20260412-dungeoncrawler-release-o
- Generated: 2026-04-18T20:01:33+00:00
