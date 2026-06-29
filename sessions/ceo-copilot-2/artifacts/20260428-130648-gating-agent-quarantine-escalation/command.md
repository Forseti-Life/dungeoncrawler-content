# Gating Agent Quarantine Escalation

**Detected:** 2026-04-28T13:06:48.787481+00:00
**Priority:** CRITICAL — release gates are bypassed when gating agents are quarantined

## Quarantined Gating Agents
- pm-forseti (2/2 = 100% quarantined, release=20260412-forseti-release-v)

## Impact
- PM quarantine: release signoff gate cannot fire automatically
- agent-code-review quarantine: code ships without automated review
- CEO must manually proxy all gating work (adds ~4-5h CEO load)

## Immediate Actions
1. Investigate quarantine root cause per agent (backend failure vs. bad inbox item)
2. Reset quarantine: update outbox Status from `needs-info` → `done` if work was already completed
3. Re-dispatch with tighter scope if item was genuinely incomplete
4. Check executor health: `bash scripts/hq-status.sh`

## Recovery command
```bash
bash scripts/hq-blockers.sh
```
- Agent: ceo-copilot-2
- Status: pending
