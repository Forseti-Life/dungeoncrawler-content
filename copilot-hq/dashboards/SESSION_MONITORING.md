# Session Monitoring Dashboard

## Daily
- Run: `./scripts/monitor-sessions.sh`

## Key metric: auto-remediation rate
Track how often the CEO health monitor self-heals stalled execution.

- Event source: `inbox/responses/ceo-health-YYYYMMDD.log`
- Event marker: `AUTO-REMEDIATE:`
- Goal: non-zero when queues are stuck, near-zero during steady state.

### Quick checks
- Total today:
  - `grep -c 'AUTO-REMEDIATE' inbox/responses/ceo-health-$(date +%Y%m%d).log`
- Per-hour breakdown today:
  - `grep 'AUTO-REMEDIATE' inbox/responses/ceo-health-$(date +%Y%m%d).log | sed -E 's/^\[([0-9-]+T[0-9]{2}).*/\1/' | sort | uniq -c`

### Interpretation
- Spikes mean monitor-driven recovery is actively compensating for stalled execution.
- Persistent spikes across many hours indicate a deeper routing/executor issue and should trigger a CEO-level follow-up item.

## Key metric: handoff-gap recoveries
Track when release monitor detects Dev-complete/open-issues handoff gaps.

- Event source: `inbox/responses/ceo-health-YYYYMMDD.log`
- Event marker: `AUTO-HANDOFF:`

### Quick checks
- Total today:
  - `grep -c 'AUTO-HANDOFF' inbox/responses/ceo-health-$(date +%Y%m%d).log`
- Per-hour breakdown today:
  - `grep 'AUTO-HANDOFF' inbox/responses/ceo-health-$(date +%Y%m%d).log | sed -E 's/^\[([0-9-]+T[0-9]{2}).*/\1/' | sort | uniq -c`

## Key metric: Stage 3 velocity (issues resolved per 15 minutes)
Track how quickly implementation-stage defects are being burned down from QA findings.

- Source of truth: `sessions/<qa-seat>/artifacts/auto-site-audit/*/findings-summary.json`
- Defect total per run: `missing_assets_404s + permission_violations + failures`
- Resolved count: positive drop between consecutive QA runs

### Quick checks
- Forseti (15-minute window):
  - `python3 scripts/stage3-velocity.py --team forseti --window-minutes 15`
- All site-audit teams (JSON):
  - `python3 scripts/stage3-velocity.py --team all --window-minutes 15 --json`

### Interpretation
- `resolved per 15m > 0`: defects are being closed in current execution window.
- `introduced > resolved`: net regression; prioritize blocker triage.
- `handoff signal=strong`: Dev updates indicate QA handoff and QA completion markers are both present in the window.

## Where to look
- Daily reviews: `knowledgebase/reviews/daily/YYYYMMDD/`
- Per-agent inbox/outbox: `sessions/<agent-id>/`
- Scoreboards: `knowledgebase/scoreboards/`
