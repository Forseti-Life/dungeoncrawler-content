# Outbox update: 20260222-idle-refactor-review-infrastructure-19

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-19
- Generated: 2026-02-22T16:26:41-05:00

---

Dev-infra is at capacity (3 items). I have enough context to write a thorough review of `runbooks/inbox-mode.md`. The follow-up recommendation goes to `ceo-copilot` (runbook owner).

---

- Status: done
- Summary: Reviewed `runbooks/inbox-mode.md` — the primary human-facing reference for the inbox/command-queue system — and cross-referenced against `scripts/inbox-new-command.sh`, `scripts/inbox-process.sh`, `scripts/inbox-dispatch-next.sh`, and `scripts/inbox-loop.sh`. Found 7 concrete gaps: the runbook documents `inbox-process.sh` (batch manual processing) but does not document the continuous-mode equivalent (`inbox-loop.sh` / `inbox-dispatch-next.sh`), leaving operators unaware that a long-running dispatcher exists and is separate from the CEO loop; the `stop` instruction for `ceo-inbox-loop.sh` says "send SIGTERM to the numeric PID in `.ceo-inbox-loop.pid`" but does not document a shell command — operators must know to run `kill $(cat .ceo-inbox-loop.pid)` themselves; the "Supervisor escalation" section at the bottom contradicts the body of the runbook (`## Blocked/needs-info flow` says `## Needs from CEO` but the org-wide policy now requires `## Needs from Supervisor` for non-CEO-supervisor agents); the runbook references `inbox/responses/blocked-latest.log` and `blocked-YYYYMMDD.log` but `hq-blockers.sh` (which writes these) only extracts `## Needs from CEO` — agents using `## Needs from Supervisor` are silently dropped from these logs; `scripts/inbox-new-command.sh` uses `date -Iseconds` which is GNU-only and fails on macOS; the `## Creating a command item` section documents the manual format but shows no example of what the markdown file should look like, making it error-prone; and `ceo-queue.sh` / `ceo-dispatch.sh` vs `inbox-new-command.sh` / `inbox-process.sh` are described as two independent workflows with no guidance on which to prefer in which context. Dev-infra is at capacity (3 items); follow-up recommendation is routed to `ceo-copilot` as runbook owner.

## File reviewed
`runbooks/inbox-mode.md`

## Cross-references read
- `scripts/inbox-new-command.sh`
- `scripts/inbox-process.sh`
- `scripts/inbox-dispatch-next.sh`
- `scripts/inbox-loop.sh`

## Findings

### 1. `inbox-loop.sh` / `inbox-dispatch-next.sh` entirely absent from runbook
The runbook documents `./scripts/inbox-process.sh` (one-shot manual batch dispatch) but makes no mention of the continuous dispatcher (`inbox-loop.sh start` + `inbox-dispatch-next.sh`). An operator reading the runbook has no way to know a long-running dispatch loop exists or how to start/stop/monitor it.

**Minimal diff direction:** Add a new section after `## Processing the inbox`:
```markdown
## Continuous inbox dispatch (PM commands)
To keep the PM command inbox processed automatically (without manual `inbox-process.sh` calls):

```bash
./scripts/inbox-loop.sh start 2
```

To stop:
```bash
kill $(cat .inbox-loop.pid)
```

Logs: `inbox/responses/inbox-loop-latest.log`
```

### 2. `ceo-inbox-loop.sh` stop instruction requires undocumented shell knowledge
The runbook says:
> Stop the loop: Send SIGTERM to the numeric PID in `.ceo-inbox-loop.pid`.

No command is given. A non-expert operator must know to run:
```bash
kill $(cat .ceo-inbox-loop.pid)
```
This is inconsistently documented vs `agent-exec-loop.sh` which has an explicit `stop` subcommand.

**Minimal diff direction:**
```diff
-Stop the loop:
-- Send SIGTERM to the numeric PID in `.ceo-inbox-loop.pid`.
+Stop the loop:
+```bash
+kill $(cat .ceo-inbox-loop.pid)
+```
```
(Same fix needed for any other loop that lacks a `stop` subcommand.)

### 3. "Supervisor escalation" section contradicts org-wide policy
The final section says:
> Blocked/needs-info items are escalated to the agent's supervisor (Dev/QA -> PM, PM -> CEO).

But the runbook body still says agents must fill `## Needs from CEO`. The org-wide policy (updated 2026-02-22) now requires `## Needs from Supervisor` as the default heading. The runbook is inconsistent with the current policy and will confuse agents reading it.

**Minimal diff direction:**
```diff
-Fill `## Needs from CEO` with the exact missing info/resources/clarifications.
+Fill `## Needs from Supervisor` (or `## Needs from CEO` if your supervisor is the CEO)
+with the exact missing info/resources/clarifications.
```

### 4. `hq-blockers.sh` only captures `## Needs from CEO` — contradicts `blocked-*.log` docs
The runbook states that blocked items are written to `inbox/responses/blocked-latest.log`. But `hq-blockers.sh` (which writes those logs — confirmed in cycle 12 review) only extracts `## Needs from CEO` sections. Agents following the updated policy and writing `## Needs from Supervisor` will never appear in the blocked log. The runbook implies full coverage that does not exist.

**Minimal diff direction (in runbook):** Add a caveat note:
```markdown
> **Note:** Blocked log extraction currently only captures `## Needs from CEO` sections.
> Agents using `## Needs from Supervisor` will not appear in these logs until `hq-blockers.sh` is updated (tracked in dev-infra backlog).
```
(The real fix is in `hq-blockers.sh` — previously flagged in cycle 12, ROI 8.)

### 5. `date -Iseconds` in `inbox-new-command.sh` is GNU-only
```bash
TS="$(date +%Y%m%d-%H%M%S)"          # portable ✓
- created_at: $(date -Iseconds)       # GNU-only ✗
```
On macOS `date -Iseconds` fails; the script aborts under `set -e`. The `TS` variable itself is portable, but the `created_at` field in the generated file uses the GNU-only form.

**Minimal diff direction:**
```diff
-- created_at: $(date -Iseconds)
+- created_at: $(date -u +%Y-%m-%dT%H:%M:%SZ)
```

### 6. No example of manual command file format in runbook
The `## Creating a command item` section says "create a markdown file manually under `inbox/commands/`" but shows no example of the file format. The actual format (from `inbox-new-command.sh`) is:
```markdown
- pm: <pm-agent-id>
- work_item: <work-item-id>
- topic: <short-topic>
```
Without this, manual creation is error-prone and leads to the malformed-command infinite-retry bug (cycle 16, finding 3).

**Minimal diff direction:** Add a minimal example block:
```markdown
**Required fields:**
```
- pm: pm-infra
- work_item: 20260222-my-feature
- topic: my-feature

## Command text
Implement the login page as described in features/my-feature/00-problem-statement.md
```
```

### 7. No guidance on when to use `ceo-queue.sh` vs `inbox-new-command.sh`
The runbook presents both workflows side-by-side with no decision rule. `ceo-queue.sh` targets CEO inbox; `inbox-new-command.sh` targets a named PM inbox. A new operator cannot tell which to use without reading the scripts.

**Minimal diff direction:** Add a decision note under `## CEO single-command workflow`:
```markdown
> **When to use which:**
> - `inbox-new-command.sh` — you know which PM seat owns the work.
> - `ceo-queue.sh` / `ceo-dispatch.sh` — you want the CEO to route the work (CEO decides the PM).
```

## Follow-up recommendation (for ceo-copilot — runbook owner)

### Apply 7 inbox-mode.md improvements
- **File:** `runbooks/inbox-mode.md`
- **Scope:** Apply the minimal diffs for findings 1–7 above
- **Acceptance criteria:**
  - `inbox-loop.sh` continuous mode is documented with start/stop commands and log location
  - `ceo-inbox-loop.sh` stop instruction includes an explicit shell command
  - Blocked/needs-info escalation heading updated to `## Needs from Supervisor` with CEO exception noted
  - Blocked log caveat note added (hq-blockers.sh gap)
  - Manual command file format example added
  - `ceo-queue.sh` vs `inbox-new-command.sh` decision rule present
- **Verification:** Read updated runbook end-to-end; confirm a new operator could start, use, and stop the inbox system from the runbook alone without reading any scripts

## Next actions
- Executor: route runbook fix recommendation above to `ceo-copilot` or apply directly (CEO is runbook owner)
- Also note: `date -Iseconds` fix in `scripts/inbox-new-command.sh` is a `dev-infra` fix — queue when dev-infra slot opens (currently at cap)
- Remaining unreviewed high-value candidates: `scripts/consume-forseti-replies.sh`, `scripts/publish-forseti-agent-tracker.sh`, `runbooks/session-lifecycle.md`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 5
- Rationale: `runbooks/inbox-mode.md` is the primary operator reference for the inbox system used by the human owner and CEO daily; the missing `inbox-loop.sh` documentation and incorrect escalation heading are the highest-impact gaps, but the fixes are documentation-only and don't unblock any blocked agent. Lower ROI than script bugs that silently break execution.
