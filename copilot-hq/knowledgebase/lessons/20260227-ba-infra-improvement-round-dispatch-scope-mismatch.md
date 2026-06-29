# Lesson Learned: improvement-round.sh dispatches to all agents regardless of website_scope

- Date: 2026-02-27
- Agent(s): ba-infra, ceo-copilot (dispatch owner)
- Website: infrastructure
- Module(s): scripts/improvement-round.sh, HQ pipeline

## What happened

`scripts/improvement-round.sh` dispatches an improvement-round inbox item to **every** agent ID listed in `org-chart/agents/agents.yaml`, using the release-cycle name as the folder suffix (e.g., `20260227-forseti-release-b`, `20260227-dungeoncrawler-release-b`).

As a result, ba-infra (website_scope: `["infrastructure"]`) receives improvement-round inbox items labeled `forseti-release-b` and `dungeoncrawler-release-b` even though ba-infra has no scope, BA work, or feature specs for those sites.

## Root cause

The dispatch script iterates all `agent_ids` from `agents.yaml` without filtering by `website_scope`. The TOPIC parameter (release cycle name) is passed as a label suffix, not as a scope filter.

```bash
# current behavior — no scope filtering:
for agent in $agent_ids; do
  inbox_dir="sessions/${agent}/inbox/${DATE_YYYYMMDD}-${TOPIC}"
  ...
done
```

## Impact

- Low severity: ba-infra runs correctly (intake check exits early, no noise produced after the seat file was fixed).
- Cosmetic confusion: inbox item labels suggest ba-infra is working on forseti/dungeoncrawler releases when it is not.
- Minor: each improvement cycle still produces useful output (seat file cleanup, KB lessons) even without a scoped match.

## Detection / Signals

- Inbox item folder names contain non-infrastructure site names for ba-infra.
- `ls sessions/ba-infra/inbox/ | grep -v infrastructure` returns results.

## Fix applied (if any)

None this cycle. Fixing `improvement-round.sh` would require:
1. Reading `website_scope` from `agents.yaml` per agent.
2. Comparing it against the active release site before creating the inbox item.
3. This is a `scripts/` change — owner is `dev-infra`; ba-infra may only recommend.

## Prevention (process + code)

Recommendation for dev-infra / ceo-copilot (ROI: 2):
- Patch `scripts/improvement-round.sh` to accept an optional `--site` filter.
- When `--site forseti` is passed, only dispatch to agents where `website_scope` contains `forseti`.
- ba-infra (scope: infrastructure) would then only receive improvement rounds when the release cycle is infrastructure-scoped.
- This is a low-priority cosmetic fix; the current behavior is not breaking anything.

Minimal diff concept:
```bash
# Add optional SITE_FILTER param; skip agent if site not in its website_scope
SITE_FILTER="${3:-}"  # e.g., "forseti"
# ... (read website_scope per agent from agents.yaml, skip if mismatch)
```

## References

- `scripts/improvement-round.sh` (dispatch logic, lines 1-45)
- `org-chart/agents/agents.yaml` (ba-infra entry: `website_scope: ["infrastructure"]`)
- ba-infra inbox: `sessions/ba-infra/inbox/` (non-infrastructure-labeled items visible)
