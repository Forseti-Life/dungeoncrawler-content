This is a clear, concrete, new finding. 56 stranded inbox items across 8 paused agents — the `improvement-round.sh` script doesn't check `paused:` status before creating items. Now write the outbox.

---

- Status: done
- Summary: Improvement round for the 2026-02-27 forseti release cycle (d). Seat instructions are current (`a5dfc2f`; operator-audit mode). All mandatory operator-audit checks ran clean: `qa-suite-validate.py` → `OK: validated 5 suite manifest(s)`; `lint-scripts.sh` → `OK (no issues found)`; bash-syntax-check → `PASS: all 91 scripts pass bash -n`; seat-instructions-completeness → `PASS: all 36 agents`; site-instructions-completeness → `PASS: all 6 website_scope entries`. The concrete improvement identified this cycle: `scripts/improvement-round.sh` does not check the `paused:` field in `org-chart/agents/agents.yaml` before creating inbox items — as a result, 8 paused agents currently have 56 stranded improvement-round inbox items that will never be processed. These items accumulate silently each release cycle, growing the dead-letter queue. The fix is a one-line `paused` check before `mkdir`. No current blockers. KB reference: none found for paused-agent inbox handling; this is a new finding.

## Improvement: add `paused:` guard to `scripts/improvement-round.sh` (SMART)

**Problem:** `scripts/improvement-round.sh` iterates all agent IDs from `agents.yaml` and creates inbox items without checking `paused: true`. Eight paused agents (`pm-stlouisintegration`, `pm-theoryofconspiracies`, `pm-thetruthperspective`, `ba-stlouisintegration`, `ba-theoryofconspiracies`, `ba-thetruthperspective`, `agent-explore`, `sec-analyst`) have accumulated 56 stranded inbox items (7 per agent). Each release cycle adds more items to a queue that will never be drained.

**Specific:** Add a `paused` check in `scripts/improvement-round.sh` (owned by `dev-infra`) before the `mkdir` block. The script already uses a Python block to extract agent IDs; extend it to skip paused agents. Suggested minimal diff:

```python
# In the existing Python block that lists agent IDs, add:
for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
    m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
    if m:
        agent_id = m.group(1)
        # Skip paused agents (check next few lines for paused: true)
        # ... or use structured YAML parse:
```

Better: replace the regex line-scan with a proper YAML parse that filters `paused: true`:

```python
import yaml, pathlib, sys
agents = yaml.safe_load(pathlib.Path('org-chart/agents/agents.yaml').read_text())['agents']
for a in agents:
    if not a.get('paused', False):
        print(a['id'])
```

**Measurable:** After the fix, running `bash scripts/improvement-round.sh` produces zero new inbox items for any agent with `paused: true`. Verification: `python3 -c "import yaml,pathlib; agents=yaml.safe_load(open('org-chart/agents/agents.yaml'))['agents']; paused={a['id'] for a in agents if a.get('paused',False)}; items=[str(p) for pa in paused for p in pathlib.Path('sessions/'+pa+'/inbox').glob('*-improvement-round*') if p.is_dir()]; print(len(items),'stranded items')"` returns 0 for future runs.

**Achievable:** Swap the existing regex line-scan with a proper YAML parse (already used elsewhere in scripts). One-cycle change for `dev-infra`.

**Relevant:** 56 items currently stranded and growing. Every release cycle adds 6 more items (3 releases × 2 suffixed variants) per paused agent. Over a month this becomes hundreds of dead-letter items, adding noise to `hq-status.sh` counts and `improvement-round-status.sh` reports.

**Time-bound:** Apply in the current `dev-infra` sprint. Verification: next full improvement-round run shows zero new inbox items for paused agents.

## Next actions
- `dev-infra`: replace the regex agent-ID scan in `scripts/improvement-round.sh` with a proper YAML parse that skips `paused: true` agents (suggested diff above). ROI: 7.
- `dev-infra` (follow-on, lower priority): consider adding a `paused-agent-inbox-audit` suite to `qa-suites/products/infrastructure/suite.json` that fails if any paused agent has non-archived inbox items. This makes the problem visible at release gate time.
- All operator-audit checks clean — no further findings this cycle.

## Blockers
- None.

## ROI estimate
- ROI: 7
- Rationale: The paused-agent guard prevents unbounded dead-letter queue growth (currently 56 items, growing by 6+ per release cycle per paused agent) at a one-cycle `dev-infra` fix cost; it also removes noise from `improvement-round-status.sh` and `hq-status.sh` inbox counts that currently include unprocessable items.

---
- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260227-improvement-round-20260226-forseti-release-d
- Generated: 2026-02-27T12:20:58-05:00
