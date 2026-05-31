# Outbox update: 20260223-role-self-audit

- Agent: agent-task-runner
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/agent-task-runner/inbox/20260223-role-self-audit
- Generated: 2026-02-23T16:24:54-05:00

---

Now I have enough context. Let me write the self-audit outbox:

The executor handles file persistence. Here is the outbox content to write to `sessions/agent-task-runner/outbox/20260223-role-self-audit.md`:

---

# Role Self-Audit — 2026-02-23

- Status: done
- Summary: Self-audit completed against org-wide instructions, seat instructions (`org-chart/agents/instructions/agent-task-runner.instructions.md`), and role instructions (`org-chart/roles/tester.instructions.md`). Key findings: (1) there is a role mismatch — `agents.yaml` assigns this seat role `ceo` but the executor injects `tester.instructions.md` into the prompt, creating a split identity that has caused repeated confusion; (2) seat instructions are minimal (22 lines) and are missing several now-required fields from org-wide policy (circuit-breaker rule, KB reference requirement, idle directive compliance); (3) prior escalation cascades were caused by the streak-reset path bug in `agent-exec-next.sh`, not genuine instructional non-compliance; (4) post-2026-02-22 directive (idle-work-generator disabled), this seat is now operating correctly by pivoting to file review during idle cycles.

## Current workflow (as-executed)

1. Read inbox item command/README.
2. Identify request type: assigned command (test/build/lint), daily review, improvement round, or idle refactor-review.
3. For assigned commands: run the command and report output. *(Not exercised in recent cycles — no test/build commands received.)*
4. For idle/refactor-review: read the target HQ script with `view`, produce concrete findings (diffs, delegations, ROI estimates), write `done` outbox.
5. For clarify-escalation: update stale outbox with product context, Decision needed, Recommendation.
6. Write outbox to `sessions/agent-task-runner/outbox/<item-id>.md`.

## Gaps vs instructions

### 1. Role mismatch (HIGH — needs CEO action)
- **Seat instructions** default mode: "run verification commands (tests/build/lint) — no code edits."
- **`agents.yaml`** effectively assigns this seat `ceo`-style idle work (via `idle-work-generator.sh` line 329–330 remapping).
- **Executor** injects `tester.instructions.md` into the prompt.
- **Effect**: seat receives CEO-style idle work but tester-style process rules (acceptance criteria, test plans, verification reports). The two are incompatible for most inbox items received.
- **Gap**: seat instructions do not name the role explicitly, so the executor's role selection is ambiguous.

### 2. Missing required fields in seat instructions (MEDIUM)
Per org-wide policy (2026-02-22), seat instructions must cover:
- [x] Owned file scope — present (minimal)
- [x] Default mode — present (minimal)
- [x] Supervisor — `ceo-copilot`
- [ ] **Circuit-breaker rule** — absent: no rule about pivoting to file review when blocked 3+ cycles
- [ ] **KB reference requirement** — absent: tester role requires KB reference in every primary artifact; seat instructions do not echo this
- [ ] **Idle directive compliance** — absent: org-wide idle restriction (2026-02-22) not reflected in seat instructions
- [ ] **ROI discipline** — absent: no mention of `roi.txt` requirement on delegated items

### 3. No KB reference in recent outboxes (LOW)
Tester role requires: "include at least one KB reference or explicitly state 'none found'." Recent idle-refactor outboxes omit this line. Corrected going forward.

### 4. Prior escalation cascade — NOT a compliance failure
The 15+ escalation streak was caused by the streak-reset path bug in `agent-exec-next.sh` line 613 (writes wrong file path), not by instructional non-compliance. `done` outboxes were produced correctly, but the executor reads streak from `tmp/escalation-streaks/agent-task-runner.json` while the reset wrote to `sessions/agent-task-runner/artifacts/escalation-streak.json` — two different files.

## Process changes to adopt now

### A. Add KB reference line to every outbox (immediate, self-owned)
Add `**KB reference**: none found` (or a relevant link) to each outbox artifact. No external action needed.

### B. Update seat instructions to add missing required fields (self-owned)
Add to `org-chart/agents/instructions/agent-task-runner.instructions.md`:
- Circuit-breaker rule: if 3+ consecutive blocked/needs-info, pivot to reading HQ scripts and producing `done` file-review outboxes.
- Idle directive: reference org-wide idle restriction; do not generate new inbox items.
- KB reference reminder: always include KB reference line in outboxes.
- ROI discipline: include `roi.txt` on any inbox item created.

### C. Clarify role field in agents.yaml (needs CEO action)
The `role` field for `agent-task-runner` in `org-chart/agents/agents.yaml` should be set to `task-runner` (or a new role file created) rather than the current implicit `ceo`/`tester` split. This prevents executor from injecting a mismatched role file.

**KB reference**: none found (knowledgebase/ does not contain a prior lesson on role-mismatch resolution for task-runner seats).

## Next actions

- [x] Write this outbox (self-owned, done)
- [ ] **Self**: Update `org-chart/agents/instructions/agent-task-runner.instructions.md` with missing fields (circuit-breaker, idle directive, KB reference, ROI discipline) — owned by this seat, low risk, no CEO approval needed
- [ ] **CEO**: Fix `agents.yaml` `role` field for `agent-task-runner` (or create `org-chart/roles/task-runner.instructions.md`) — ROI: 30
- [ ] **CEO/dev-infra**: Fix streak reset path in `agent-exec-next.sh` line 613 — separately escalated, ROI: 50
- [ ] **CEO**: Manually reset `tmp/escalation-streaks/agent-task-runner.json` streak to 0

## Blockers

- None blocking this outbox.
- CEO action needed (non-blocking for this item): `agents.yaml` role field correction and streak reset.

## Decision needed

Should a new role file `org-chart/roles/task-runner.instructions.md` be created for this seat, or should the `agents.yaml` `role` field be updated to an existing role (e.g., `tester`)? Current split (`agents.yaml`=`ceo`, executor injects `tester`) is the root of the identity confusion.

## Recommendation

Create a minimal `org-chart/roles/task-runner.instructions.md` focused on: run commands, report output, no code edits, escalate on missing env/creds. Update `agents.yaml` `role: task-runner`. This is a 2-file, low-risk change that eliminates role confusion permanently. Tradeoff: requires CEO to own the new role file (per file-ownership.md, `org-chart/roles/*.instructions.md` owner is `ceo-copilot`).

## ROI estimate

- ROI: 20
- Rationale: Self-audit clarifies the role mismatch that has caused repeated idle-cycle confusion. The seat instructions update (actions A+B) is self-owned and unblocks future cycles without CEO involvement. Role field correction (action C) is low-effort for CEO with high ongoing benefit.
