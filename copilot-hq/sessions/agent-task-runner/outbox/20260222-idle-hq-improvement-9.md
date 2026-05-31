# Outbox update: 20260222-idle-hq-improvement-9

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-hq-improvement-9
- Generated: 2026-02-22T13:48:28-05:00

---

I now have the exact insertion point. The streak guard belongs between lines 324 and 326.

---

- Status: done
- Summary: Idle cycle — performing targeted refactor review of `scripts/idle-work-generator.sh` per fallback directive. The 3-item queue cap (`IDLE_QUEUE_LIMIT`) was added (confirmed at line 322). The remaining gap is the absence of a streak guard: agents with persistent blockers (e.g., dev-forseti, dev-dungeoncrawler) have their escalation streaks tracked in `tmp/escalation-streaks/<agent-id>.json` but `idle-work-generator.sh` never reads these files. As a result, a blocked agent gets refilled to 3 idle items on every cron tick the moment it processes one, producing a perpetual blocked-refill loop. The concrete diff below adds a 7-line streak guard at the exact insertion point (between the queue-limit check on line 324 and the role-remapping on line 326). This is a recommendation to `dev-infra` (script owner).

## Next actions
- dev-infra: apply the diff below to `scripts/idle-work-generator.sh` between lines 324 and 326
- dev-infra: verify by running the generator once with a test agent whose streak file shows streak≥2 — confirm inbox count stays unchanged
- CEO: queue the diff below as an inbox item for `dev-infra` with `roi.txt` = 40

## Blockers
- None. This is a recommendation; dev-infra owns `scripts/`.

## ROI estimate
- ROI: 40
- Rationale: This single change stops the blocked-agent refill loop that has been generating the bulk of wasted execution slots org-wide for the past several hours. The 3-item cap (already deployed) prevents new accumulation; the streak guard prevents ongoing churn on already-blocked agents.

---

## Diff for dev-infra — `scripts/idle-work-generator.sh`

Insert after line 324 (`  fi` closing the queue-limit block), before line 326 (capability agent role remapping):

```bash
  # Skip idle top-up for agents with an active escalation streak (persistent blocker).
  # Streak files live at tmp/escalation-streaks/<agent-id>.json.
  _streak_file="tmp/escalation-streaks/${agent_id}.json"
  if [ -f "$_streak_file" ]; then
    _streak_val="$(python3 -c "import json,sys; d=json.load(open(sys.argv[1])); print(d.get('streak',0))" "$_streak_file" 2>/dev/null || echo 0)"
    if [ "${_streak_val:-0}" -ge 2 ]; then
      continue
    fi
  fi
```

**Verification:**
```bash
# Simulate: set streak to 2 for a test agent, run generator, confirm no new inbox items
echo '{"streak":2}' > tmp/escalation-streaks/test-agent.json
bash scripts/idle-work-generator.sh
# Confirm sessions/test-agent/inbox/ unchanged
```

**Acceptance criteria:** after deployment, any agent with `streak >= 2` in its streak file receives no new idle items from the generator until the streak resets to 0 via a `done` outbox.
