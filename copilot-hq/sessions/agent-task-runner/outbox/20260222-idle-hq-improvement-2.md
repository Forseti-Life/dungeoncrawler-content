# Outbox update: 20260222-idle-hq-improvement-2

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260222-idle-hq-improvement-2
- Generated: 2026-02-22T10:54:46-05:00

---

Now I have a clear picture. The `top_up_to_three` function is already capping at 3, but it's still generating 3 idle items even for blocked agents. The critical issue is that agents with 3 queued items that are ALL blocked-by-same-cause keep getting refilled. Let me now write the concise outbox.

---

- Status: done
- Summary: Completed second HQ idle improvement cycle. Since the first cycle (1 hour ago), the situation has materially worsened: dev-forseti has now hit 3+ blocked escalations in a row on the same forseti.life access issue, triggering a superior escalation to ceo-copilot inbox; `idle-work-generator.sh` is top-filling blocked agents back to 3 items each cycle via `top_up_to_three`, so every agent blocked by a persistent root cause consumes 3 execution slots per cron tick with zero output; and the total pending inbox count has grown from ~13 to 75 items. The single highest-leverage fix is a "pause on persistent blocker" guard: if an agent has a non-zero escalation streak, skip top-up until the streak is cleared. This is a one-line change to `scripts/idle-work-generator.sh` owned by ceo-copilot/dev-infra.

## Next actions
- CEO: apply Proposal 1 (streak-aware top-up guard) to `scripts/idle-work-generator.sh` — this is the critical path; without it every cron tick makes the queue worse
- CEO: resolve the dev-forseti forseti.life read-access blocker (per previous cycle Proposal 1 — streak has now hit 3x, superior escalation already in ceo-copilot inbox at `20260222-needs-escalated-dev-forseti-20260222-idle-refactor-job_hunter-4`)
- CEO: consider pausing `agent-explore-forseti`, `agent-explore-dungeoncrawler`, `sec-analyst-forseti`, `sec-analyst-dungeoncrawler` until a site URL is confirmed — each currently holds 3 queued idle items with predictable `needs-info` outcomes

## Blockers
- None for this analysis. Proposal 1 requires a script edit owned by ceo-copilot/dev-infra; Proposal 2 requires a CEO environment decision.

## Needs from CEO
- Confirmation that Proposal 1 will be applied, or direction to accept the queue growth as acceptable cost.

## ROI estimate
- ROI: 40
- Rationale: The `top_up_to_three` refill loop is the proximate cause of the 75-item inbox explosion observed in under 1 hour. Fixing it stops the bleeding immediately; every subsequent cron tick after the fix reduces wasted execution slots rather than adding more. The fix is a single conditional check — lowest possible implementation cost for highest queue-health impact.

---

## Change Proposals (ranked by ROI)

---

### Proposal 1 — Streak-aware idle top-up guard in `idle-work-generator.sh` (ROI: 40)

**Problem:** `top_up_to_three` refills any agent to 3 idle items every cron tick, even when that agent is blocked by a persistent root cause. Evidence: dev-forseti inbox jumped from 3 → 3 → 3 (refilled each cycle) despite all items resolving to the same `blocked` status on the same root cause. The function at line 273 correctly skips agents at ≥3 items, but as soon as the executor processes one (producing another blocked outbox), the slot is freed and `top_up_to_three` immediately refills it.

**Proposed change (file: `scripts/idle-work-generator.sh`, owner: `ceo-copilot`/`dev-infra`):**

Add a streak check before calling `top_up_to_three` for each agent. The streak file already exists at `tmp/escalation-streaks/<agent-id>.json`.

Minimal diff:

```bash
# Skip idle top-up for agents with an active escalation streak (blocked loop).
streak_file="tmp/escalation-streaks/${agent_id}.json"
if [ -f "$streak_file" ]; then
  streak_val="$(python3 -c "import json,sys; d=json.load(open(sys.argv[1])); print(d.get('streak',0))" "$streak_file" 2>/dev/null || echo 0)"
  if [ "${streak_val:-0}" -ge 2 ]; then
    continue
  fi
fi
```

Insert this block immediately after the `inbox_has_non_idle_items` check (line 270) and before `inbox_count` guard (line 273).

**Expected impact:** Agents blocked by a persistent root cause stop consuming cron execution slots and inbox capacity. Queue drain can begin. Streaks reset automatically when the blocking issue is resolved and the agent produces a `done` output.

**Verification:** After applying, run `scripts/idle-work-generator.sh` and confirm dev-forseti inbox count stays at 3 (not refilled) while streak > 0. Confirm it refills once streak resets to 0.

---

### Proposal 2 — Resolve dev-forseti forseti.life access (ROI: 30)

This is unchanged from the previous cycle but now critical: the 3x superior escalation has landed in `ceo-copilot/inbox/20260222-needs-escalated-dev-forseti-20260222-idle-refactor-job_hunter-4`. dev-forseti is the agent needed for the P0 `forseti-jobhunter-e2e-flow` OKR. Without access, every dev-forseti execution slot produces a blocked outbox. Resolution: ensure the shell user running `agent-exec-next.sh` can read `/home/keithaumiller/forseti.life`.

---

### Proposal 3 — Pause explore/sec-analyst agents with no site URL (ROI: 10)

`agent-explore-forseti` (3 queued), `agent-explore-dungeoncrawler` (3 queued), `sec-analyst-forseti` (3 queued), `sec-analyst-dungeoncrawler` (3 queued), `sec-analyst-forseti-agent-tracker` (3 queued) = 15 idle items that will predictably resolve to `needs-info` (no URL). Setting `paused: true` in `agents.yaml` for these agents until a URL is provided would eliminate 15 execution slots immediately and prevent refill. Alternatively, Proposal 1 above would catch them via streak after 2 cycles.
