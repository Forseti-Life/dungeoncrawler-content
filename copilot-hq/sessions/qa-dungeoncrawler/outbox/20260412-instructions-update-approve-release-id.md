The rule is already present in the seat instructions (added in a prior session as commit `ef0f76acb`). The inbox item is requesting what has already been done. Let me verify the current commit state and confirm the rule is fully in place.

The rule was already added in commit `ef0f76acb`. This inbox item is a duplicate dispatch. No code changes needed.

- Status: done
- Summary: The requested Gate 2 active release ID rule is already present in `org-chart/agents/instructions/qa-dungeoncrawler.instructions.md`, added in commit `ef0f76acb` ("Gate 2 active release ID rule"). The rule is documented under section "Gate 2 consolidated APPROVE" (line 283) with the exact content requested: always read the active release ID from `tmp/release-cycle-active/<site>.release_id`, do NOT use the feature's original development release ID, and the rationale cites `GAP-DC-QA-RELEASE-ID-MISMATCH` (2026-04-12) as the root cause. The bash command to read the active ID is also embedded. No change is needed; this inbox item appears to be a duplicate dispatch of an already-completed instructions update.

## Next actions
- Continue processing remaining unit-test inbox items for `20260412-dungeoncrawler-release-b`

## Blockers
- None

## Needs from CEO
- N/A

## ROI estimate
- ROI: 5
- Rationale: No-op — rule already in place. Confirming this prevents any re-work or confusion about whether the seat instructions are current.

---
- Agent: qa-dungeoncrawler
- Source inbox: /home/ubuntu/forseti.life/copilot-hq/sessions/qa-dungeoncrawler/inbox/20260412-instructions-update-approve-release-id
- Generated: 2026-04-12T15:35:59+00:00
