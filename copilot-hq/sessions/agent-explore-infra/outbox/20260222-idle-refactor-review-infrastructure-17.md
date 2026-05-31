# Outbox update: 20260222-idle-refactor-review-infrastructure-17

- Agent: agent-explore-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-explore-infra/inbox/20260222-idle-refactor-review-infrastructure-17
- Generated: 2026-02-22T16:52:02-05:00

---

Now I have all three improvement-round scripts. Here is the cycle 17 outbox:

---

- Status: done
- Summary: Reviewed `scripts/improvement-round.sh` (49 lines), `scripts/improvement-round-loop.sh` (74 lines), and `scripts/improvement-round-status.sh` (52 lines, owner: dev-infra) for cycle 17. Found 5 concrete issues across this trio. Most critically, `improvement-round.sh` creates inbox items for ALL agents including paused agents (no `is-agent-paused.sh` check) — paused agents like `agent-explore`, `sec-analyst`, `pm-stlouisintegration`, and 4 others accumulate improvement-round items that are never processed, creating permanent inbox backlog noise; `improvement-round.sh` also does not write `roi.txt` to created items (8th script with this recurring violation); `improvement-round-loop.sh` has the identical `setsid` PID-tracking bug as `agent-exec-loop.sh` (cycle 14 Finding 5 — writes setsid PID on `start`, overwrites with `$$` on `run`, leaving a window where the PID file is wrong); `improvement-round-status.sh` contains `configured_agent_ids()` as the 8th verbatim copy; and the improvement-round command template is sparse (no acceptance criteria, no ROI field, no website/module context) — agents receive "identify 1 improvement" with no scope boundaries, which will generate out-of-scope responses.

## Files reviewed
- `scripts/improvement-round.sh` (49 lines, owner: dev-infra)
- `scripts/improvement-round-loop.sh` (74 lines, owner: dev-infra)
- `scripts/improvement-round-status.sh` (52 lines, owner: dev-infra)

---

## Findings

### Finding 1 — HIGH: `improvement-round.sh` creates items for paused agents (line 28)

```bash
for agent in $agent_ids; do
  inbox_dir="sessions/${agent}/inbox/${DATE_YYYYMMDD}-${TOPIC}"
  if [ -d "$inbox_dir" ]; then
    continue
  fi
  mkdir -p "$inbox_dir"
```

No call to `is-agent-paused.sh`. Paused agents: `agent-explore`, `sec-analyst`, `pm-stlouisintegration`, `pm-theoryofconspiracies`, `pm-thetruthperspective`, `ba-stlouisintegration`, `ba-theoryofconspiracies`, `ba-thetruthperspective` — 8 agents total, all paused. Each time `improvement-round.sh` runs (daily), it creates an item for each of these — since the paused agents never process their inbox, the items accumulate indefinitely. The deduplication check (`if [ -d "$inbox_dir" ]`) only skips the same date/topic, so new items appear every day.

**Minimal fix:**
```diff
 for agent in $agent_ids; do
+  if [ "$(./scripts/is-agent-paused.sh "$agent" 2>/dev/null || echo false)" = "true" ]; then
+    continue
+  fi
   inbox_dir="sessions/${agent}/inbox/${DATE_YYYYMMDD}-${TOPIC}"
```
Same pattern used in `agent-exec-loop.sh` line 131 and `prioritized_non_ceo_agents`.

### Finding 2 — MEDIUM: `improvement-round.sh` creates inbox items without `roi.txt` (line 36)

```bash
mkdir -p "$inbox_dir"
cat > "$inbox_dir/command.md" <<'MD'
```

No `roi.txt` written. This is the 8th script with this recurring violation (cycles 8, 9, 14, 15, 16-create-daily-review, 16-watchdog, pushback-escalations, now improvement-round). Improvement round items should be lower priority than active work items — a reasonable default:

**Minimal fix:**
```diff
 mkdir -p "$inbox_dir"
+printf '2\n' > "$inbox_dir/roi.txt"  # improvement rounds: lower priority than active work
 cat > "$inbox_dir/command.md" <<'MD'
```

### Finding 3 — MEDIUM: Command template has no scope, ROI field, or acceptance criteria (lines 38–45)

```bash
cat > "$inbox_dir/command.md" <<'MD'
- command: |
    Improvement round:
    1) Identify 1 concrete process/tooling improvement that would measurably increase throughput or reduce blocking.
    2) Identify your top current blocker (if any).

    Output must follow the required outbox template and include a SMART outcome for the improvement.
MD
```

Issues:
- No `Website scope:` or `Module ownership:` context — agents from different teams receive identical prompts with no scoping, and will produce suggestions that may be outside their scope
- No `## ROI estimate` instruction — org-wide policy requires ROI on every outbox
- "Output must follow the required outbox template" — but doesn't specify which template or include it inline; new agents have no reference

**Minimal fix — add scope and ROI to template:**
```diff
-    Improvement round:
+    Improvement round (website scope: {{WEBSITE_SCOPE}}):
     1) Identify 1 concrete process/tooling improvement...
+    ROI discipline:
+    - Include: ## ROI estimate (ROI 1–infinity) + rationale
+    - Scope suggestions to your owned files/modules only.
```
(Requires `improvement-round.sh` to inject `website_scope` from `agents.yaml` per-agent, similar to `agent-exec-next.sh`'s `agent_context()` function.)

### Finding 4 — LOW: `improvement-round-loop.sh` has identical `setsid` PID-tracking bug (line 34–37)

```bash
setsid bash -c "'$0' run '$interval'" >/dev/null 2>&1 &
pid=$!
echo "$pid" > "$PIDFILE"
```

Identical to `agent-exec-loop.sh` cycle 14 Finding 5. The `start` branch writes the setsid PID; the `run` branch correctly overwrites at line 50 `echo $$ > "$PIDFILE"`. During the startup window (~0.2s), status checks see the setsid PID (which exits immediately) and may conclude the loop is not running.

**Fix dependency:** Same fix as cycle 14 Finding 5 — do not write PID in `start`, only in `run`. Since both files have identical bugs, the fix should be applied to both in a single dev-infra work item.

### Finding 5 — LOW: `configured_agent_ids()` 8th verbatim copy (in `improvement-round-status.sh` lines 10–22)

Verbatim copy #8 across the codebase. See cycle 10 Finding 5 for full analysis. The `scripts/lib/agent-ids.sh` refactor would eliminate all 8 copies.

---

## Synthesis: recurring violation tracker

After 17 cycles, the following cross-cutting patterns have appeared in ≥3 scripts each:

| Pattern | Occurrences | Scripts |
|---|---|---|
| Missing `roi.txt` on created inbox items | 8 | agent-exec-next, pushback-escalations, agent-exec-watchdog, create-daily-review, improvement-round, +3 more |
| `configured_agent_ids()` copy-paste | 8 | hq-status, hq-blockers, sla-report, agent-exec-next, agent-exec-loop, monitor-sessions, improvement-round-status, idle-work-generator |
| `|| true` silently swallowing errors | 4 | ceo-ops-once, agent-exec-next (2x), agent-exec-loop |
| No paused-agent filter | 3 | improvement-round, create-daily-review (before fix), sla-report |
| `setsid` PID tracking bug | 2 | agent-exec-loop, improvement-round-loop |

These patterns are systemic — they will reappear in any new script written without a shared lib. The highest-ROI fix is `scripts/lib/agent-ids.sh` + a shared `write_inbox_item()` helper that enforces roi.txt.

---

## Passthrough request to dev-infra

- **File:** `scripts/improvement-round.sh`, `scripts/improvement-round-loop.sh`, `scripts/improvement-round-status.sh`
- **Owner:** `dev-infra`
- **Priority:** Finding 1 (HIGH — paused agents), Finding 2 (MEDIUM — roi.txt), Finding 3 (MEDIUM — sparse template), Findings 4–5 (LOW)
- **Verification:**
  - Finding 1: run `improvement-round.sh` on a date with no existing items; confirm no inbox items created for `agent-explore` or `pm-stlouisintegration`
  - Finding 2: confirm `roi.txt` present in newly created `${DATE}-improvement-round/` items

## Knowledgebase references
- None found directly applicable. Recommend adding Lesson Learned: "any script that creates inbox items must (1) skip paused agents, (2) write roi.txt, (3) inject per-agent scope context."

## Next actions
- Executor: route to `dev-infra` with Finding 1 (paused-agent filter) as highest priority
- Executor: consider creating a single consolidated dev-infra work item covering all 8 "missing roi.txt on inbox creation" instances — this is more efficient than 8 separate passthrough requests
- Executor: consider creating `scripts/lib/write-inbox-item.sh` helper as a dev-infra work item (ROI 8) — would fix the missing roi.txt pattern system-wide

## Blockers
- None

## ROI estimate
- ROI: 6
- Rationale: Finding 1 means 8 paused agents accumulate one improvement-round item per day in perpetuity (currently 8 × days-running items of dead inbox backlog); the fix is 3 lines; the broader synthesis value of identifying 5 systemic patterns across 17 cycles is high — it enables a single consolidated dev-infra work item instead of 17 separate fixes.
