I'll work through this systematically. Let me start by reading the current state of the release-x backlog features and relevant context.

## Step 1: Read existing feature files

```bash
cat /home/ubuntu/forseti.life/features/dc-feature-015/feature.md 2>/dev/null || echo "NOT FOUND"
```

Let me run a comprehensive investigation first.

<tool_call>
{"name": "bash", "arguments": {"command": "ls /home/ubuntu/forseti.life/features/ | grep dc-feature"}}
</tool_call>
<tool_response>
dc-feature-001
dc-feature-002
dc-feature-003
dc-feature-004
dc-feature-005
dc-feature-006
dc-feature-007
dc-feature-008
dc-feature-009
dc-feature-010
dc-feature-011
dc-feature-012
dc-feature-013
dc-feature-014
dc-feature-015
dc-feature-016
dc-feature-017
dc-feature-018
dc-feature-019
dc-feature-020
</tool_response>

<tool_call>
{"name": "bash", "arguments": {"command": "for f in dc-feature-015 dc-feature-016 dc-feature-017 dc-feature-018 dc-feature-019 dc-feature-020; do echo \"=== $f ===\"; cat /home/ubuntu/forseti.life/features/$f/feature.md 2>/dev/null || echo 'NOT FOUND'; echo; done"}}
</tool_call>
<tool_response>
=== dc-feature-015 ===
# dc-feature-015: Criminal NPC Voice — Forseti System Prompt Update

- Feature ID: dc-feature-015
- Website: dungeoncrawler
- Release: 20260412-dungeoncrawler-release-x
- Status: planned
- Priority: high
- ROI: 95
- Owner: pm-dungeoncrawler
- Created: 2026-04-12

## Summary
The Forseti GM chatbot for the dungeoncrawler product needs its system prompt updated to reflect the current game context: **Criminal**, a gritty urban-fantasy campaign setting. The current system prompt is generic; it should be updated to reflect Criminal's tone, setting-specific world details, and NPC roster context so that the GM gives meaningful, context-aware narration and rulings.

## Acceptance criteria
- [ ] The Forseti system prompt for dungeoncrawler is updated to reference the Criminal campaign setting
- [ ] Tone guidance reflects Criminal's gritty urban-fantasy atmosphere
- [ ] World details (city, factions, key locations) are included at the appropriate level of specificity
- [ ] NPC roster context is incorporated (key NPCs relevant to current campaign arc)
- [ ] The updated prompt is tested against at least 3 representative player queries and produces accurate, in-setting responses
- [ ] No

---
- Agent: pm-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/sessions/pm-dungeoncrawler/inbox/20260427-groom-20260412-dungeoncrawler-release-x-followup
- Generated: 2026-04-27T02:40:11+00:00
