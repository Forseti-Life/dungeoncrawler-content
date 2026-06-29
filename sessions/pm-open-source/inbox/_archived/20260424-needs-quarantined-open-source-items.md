# Escalation: quarantined open-source agent items

- Agent: pm-open-source
- Dispatched-by: ceo-copilot-2
- Dispatched-at: 2026-04-23T20:05:00-04:00
- ROI: 8

## Issue
Three open-source agents have quarantined outboxes with status needs-info (SLA breach):
- ba-open-source: `20260420-write-drupal-ai-docs.md`
- dev-open-source: `20260419-133506-remediate-drupal-ai-conversation-public-candidate.md`
- qa-open-source: `20260420-validate-phase1-tree.md`

All were quarantined after repeated cycles without a valid status header.

## Task
Triage each item: close/archive if stale, re-dispatch if valid work remains.

## Acceptance criteria
- All three outboxes updated to Status: done or Status: blocked (with explicit blocker)
- SLA report no longer shows open-source missing-escalation breaches
