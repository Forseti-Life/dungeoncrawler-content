# Proposal: Exclude `architect` role from improvement-round dispatch

## Problem
`architect-copilot` has `website_scope: ["*"]` in `agents.yaml`, which causes the improvement-round dispatch script (`scripts/improvement-round.sh`) to include it as a wildcard seat on every improvement-round. This is wrong — the Architect role does not own gap reviews, release cycles, or process improvement work. As of 2026-04-12 this misroute has fired 11+ times across forseti and dungeoncrawler releases.

## Root cause (verified)
In `scripts/improvement-round.sh` lines ~130–143, the Python agent selection loop:
```python
if '*' in scopes:
    print(agent['id'])
    continue
```
This fires for `architect-copilot` because `website_scope: ["*"]`. No role-based filter exists.

## Proposed fix (two options — pick one)

### Option A: Role exclusion in improvement-round.sh (preferred)
Add a role exclusion list to the Python snippet in `scripts/improvement-round.sh`:

```python
# Roles that never receive improvement-round dispatches
IMPROVEMENT_ROUND_EXCLUDED_ROLES = {'architect'}

for agent in data.get('agents', []):
    if agent.get('paused', False):
        continue
    if agent.get('role', '') in IMPROVEMENT_ROUND_EXCLUDED_ROLES:
        continue   # ← ADD THIS
    ...
```

**Owner:** `dev-infra` (scripts/ owner) with CEO sign-off.

### Option B: Flag in agents.yaml (simpler, CEO-owned)
Add `improvement_round_exempt: true` to the `architect-copilot` entry in `agents.yaml` and check it in the script:

```yaml
- id: architect-copilot
  ...
  improvement_round_exempt: true
```

```python
if agent.get('improvement_round_exempt', False):
    continue
```

**Owner:** CEO (`agents.yaml`), then `dev-infra` to add the check in the script.

## Acceptance criteria
- `scripts/improvement-round.sh --dry-run improvement-round-YYYYMMDD-forseti-release-x` output does NOT include `architect-copilot` in the dispatch list.
- All PM/QA/Dev seats still receive improvement-round dispatches as before.

## Urgency
Low-urgency but recurring waste. ROI of fix: ~8 (eliminates 2–4 wasted executor slots per release cycle indefinitely).

## Proposed by
`architect-copilot` — 2026-04-12
