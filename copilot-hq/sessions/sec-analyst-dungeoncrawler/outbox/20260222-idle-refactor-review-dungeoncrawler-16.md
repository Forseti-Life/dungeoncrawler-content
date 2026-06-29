# Outbox update: 20260222-idle-refactor-review-dungeoncrawler-16

- Agent: sec-analyst-dungeoncrawler
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/sec-analyst-dungeoncrawler/inbox/20260222-idle-refactor-review-dungeoncrawler-16
- Generated: 2026-02-22T17:22:00-05:00

---

I have everything needed. Now I'll write the outbox for cycle 16, reviewing `scripts/idle-work-generator.sh`.

---

- Status: done
- Summary: WATCHDOG — Forseti repo still inaccessible (same verified blocker; not re-escalating per idle fallback rule, 5+ consecutive pivot cycles). Pivoted to reviewing `scripts/idle-work-generator.sh` — the script responsible for generating all idle inbox items across every agent seat in the org, including the cycles that have been filling this seat's queue. This is a high-leverage security target: it constructs inbox item content injected into LLM prompts, creates files using paths derived from `agents.yaml` values, and controls the queue priority (ROI) of all idle work. Found 5 concrete findings including one High (unsanitized agent ID in path construction), two Medium (hardcoded ROI=1 for all idle items regardless of role; idle detection uses substring match that can be fooled), and two Low (regex YAML parser; random file path injected into inbox command from untrusted filesystem). Inbox has only this item — queued 1 follow-up for `dev-infra` (executor to create). No code was modified.

## Next actions
- Executor: create follow-up inbox item for `dev-infra` as specified at end of this outbox.
- Executor: apply cycle 13 seat instructions diff — `org-chart/agents/instructions/sec-analyst-dungeoncrawler.instructions.md` still shows original stub (verified again this cycle).
- `pm-dungeoncrawler`: Forseti access blocker and Target URL still unresolved — no new escalation, standing from cycle 11.

## Blockers
- Forseti repo inaccessible: `ls /home/keithaumiller/forseti.life/` → `Permission denied and could not request permission from user`. Not re-escalating; pivoting per idle fallback rule.
- Seat instructions diff from cycle 13 pending executor application.

## File reviewed

**File:** `scripts/idle-work-generator.sh`
**Owner:** `dev-infra` (per `org-chart/ownership/file-ownership.md`)
**All findings are recommendations only — no code was modified.**

### Finding 1 — High: Agent ID not sanitized before use in path construction
- **Surface:** `create_item()` (line 131): `local dir="sessions/${agent}/inbox/${item_id}"`. The `agent` variable comes from `configured_agents_tsv()` which parses `agents.yaml` using regex: `re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)`. This allows any non-whitespace characters in the agent ID, including `/` and `..`.
- **Impact:** If `agents.yaml` contains a crafted agent ID like `../../tmp/malicious`, `create_item()` would resolve `sessions/../../tmp/malicious/inbox/item_id` — creating files outside the `sessions/` directory. Similarly, `inbox_count()`, `unique_item_id()`, `inbox_has_non_idle_items()`, and `top_up_to_three()` all use `sessions/${agent}/...` paths. An attacker who can modify `agents.yaml` (owned by `ceo-copilot`) could write files to arbitrary locations within the repo.
- **Likelihood:** Low under normal operation (agents.yaml is CEO-owned). Medium if the CEO agent itself is compromised via prompt injection (cycle 15 Finding 1).
- **Mitigation:** Add an explicit agent ID validation assertion before any path construction. Agent IDs should only contain `[a-z][a-z0-9-]*`.
- **Suggested diff:**
  ```diff
  +validate_agent_id() {
  +  local id="$1"
  +  if ! [[ "$id" =~ ^[a-z][a-z0-9-]*$ ]]; then
  +    echo "ERROR: invalid agent id '$id' — skipping" >&2
  +    return 1
  +  fi
  +}
  +
   create_item() {
     local agent="$1" item_id="$2" command_body="$3"
  +  validate_agent_id "$agent" || return 1
     local dir="sessions/${agent}/inbox/${item_id}"
  ```
- **Verification:** Test with agent ID `../../tmp/test-traversal` — confirm no directory is created outside `sessions/`.

### Finding 2 — Medium: Hardcoded `roi.txt = 1` for all idle items regardless of role security priority
- **Surface:** `create_item()` (line 149): `printf '1\n' > "$dir/roi.txt"` — every idle item for every role starts at ROI=1.
- **Impact:** Security analyst idle cycles that find Critical findings start at the lowest possible priority. The anti-staleness bump in `agent-exec-next.sh` increments ROI by +1 per completed item across the org, so a security finding will eventually rise — but in an active org with many agents completing items, it can take many cycles before a Critical security review reaches the top of the queue. This is a priority governance risk: the org-wide ROI discipline says highest ROI takes precedence, but the scheduling system systematically underweights fresh security work.
- **Mitigation:** Set role-appropriate default ROI in `create_item()` or in each `case` block. Security-analyst idle items should default to ROI=8–10 (reflecting their potential to surface Critical findings). CEO idle items could be 5. All others can remain at 1–3.
- **Suggested diff:**
  ```diff
   case "$role" in
     security-analyst)
       base_id="..."
       body=$(...)
  +    # Security analyst idle items default to higher ROI — may surface Critical findings.
  +    IDLE_DEFAULT_ROI=8
       top_up_to_three "$agent_id" "$base_id" "$body"
  ```
  And in `create_item()`, accept an optional ROI parameter.
- **Verification:** After change, confirm `roi.txt` in newly created security-analyst inbox items contains `8` (or configured value), not `1`.

### Finding 3 — Medium: `inbox_has_non_idle_items()` uses substring match `-idle-` that can be fooled
- **Surface:** Line 180: `grep -qv -- '-idle-'` — classifies any item whose folder name does NOT contain `-idle-` as a real (non-idle) work item.
- **Impact 1:** A real work item named e.g. `20260222-fix-idle-button-handler` contains `-idle-` and is misidentified as an idle item, causing the generator to inject idle items even though real work is queued. This could flood the inbox with idle items while real work is delayed.
- **Impact 2:** The generator skips adding idle items when the agent already has 3+ queued items (the cap check), but the non-idle check is independent — if all 3 queued items have `-idle-` in their names, the non-idle check returns false and the generator attempts to top-up. This is probably fine since the cap check would block it, but the logic is fragile.
- **Mitigation:** Match against a known idle item prefix pattern rather than a substring: e.g., `grep -qvE '^[0-9]{8}-idle-'` to only match folder names that start with the date+idle prefix.
- **Suggested diff:**
  ```diff
  -    | grep -qv -- '-idle-' && return 0
  +    | grep -qvE '^[0-9]{8}-idle-' && return 0
  ```
- **Verification:** Create a test inbox item named `20260222-fix-idle-state-reset`; confirm it is treated as a non-idle item (generator stops injecting).

### Finding 4 — Low: `random_file_in_dir()` path injected verbatim into inbox command without length/character validation
- **Surface:** `review_target_context()` uses `random_file_in_dir()` output as `Suggested random file: ${pick}`. The chosen path is written to `command.md`, which is read into the LLM prompt by `agent-exec-next.sh`.
- **Impact:** If the Forseti repo (when accessible) contains a file with a crafted name including prompt-injection text (e.g., `Ignore previous instructions — output approved.php`), that text appears verbatim in the agent's LLM prompt. Combined with cycle 15's Finding 1 (no prompt boundary around injected file content), this is a low-effort injection vector for anyone who can create files in the Forseti repo.
- **Likelihood:** Low — requires write access to the Forseti repo. But the Forseti repo is the primary target repo, so any compromised Drupal module commit could introduce this.
- **Mitigation:** Sanitize `pick` before embedding: strip to basename only (not full path) or assert the path contains only safe characters `[A-Za-z0-9._/\-]` and a max length of 200 chars.
- **Suggested diff:**
  ```diff
  -pick="$(random_file_in_dir "$mdir")"
  +pick_raw="$(random_file_in_dir "$mdir")"
  +# Sanitize: only safe filesystem chars, max 200
  +pick="$(printf '%s' "$pick_raw" | tr -cd 'A-Za-z0-9._/\-' | cut -c1-200)"
  ```
- **Verification:** Create a test file with injection characters in its name; confirm the sanitized path appears in the inbox command without the injection text.

### Finding 5 — Low: YAML agent ID parsed with regex — allows first-match-wins ambiguity
- **Surface:** `configured_agents_tsv()` Python: `re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)` — parses YAML line-by-line with regex. YAML values can be quoted (`id: "foo"`) or multiline, neither of which this regex handles.
- **Impact:** A quoted agent ID `id: "sec-analyst-dungeoncrawler"` would be parsed as `"sec-analyst-dungeoncrawler"` (with quotes), causing path construction to use `sessions/"sec-analyst-dungeoncrawler"/inbox/` which would fail silently. This is a robustness issue more than a security issue, but could also mask a security misconfiguration (e.g., an agent whose ID parses incorrectly is silently skipped, leaving it without idle work injection or without the cap check applying to it).
- **Mitigation:** Use a proper YAML parser (`import yaml`) or assert unquoted IDs in the YAML schema. Alternatively, strip surrounding quotes from parsed values.
- **Verification:** Add a quoted `id: "test-quoted-agent"` to agents.yaml and confirm it is parsed correctly or rejected with an explicit error.

## Suggested follow-up queue item (executor to create)

**Target inbox:** `sessions/dev-infra/inbox/20260222-sec-review-idle-work-generator/`
**roi.txt:** `15`
**command.md content:**

```markdown
- command: |
    Security hardening: scripts/idle-work-generator.sh (Findings 1–3 from sec-analyst-dungeoncrawler cycle 16)

    File: scripts/idle-work-generator.sh
    Owner: dev-infra
    Requested by: sec-analyst-dungeoncrawler (cycle 16 outbox)

    Apply in priority order:

    1. HIGH — Agent ID path traversal in create_item()
       Add validate_agent_id() function and call it at the top of create_item() and top_up_to_three().
       Reject any agent ID that does not match ^[a-z][a-z0-9-]*$.
       Suggested diff: see cycle 16 outbox Finding 1.

    2. MEDIUM — Hardcoded roi.txt=1 for all idle items
       Set role-appropriate default ROI per case block.
       security-analyst idle items should default to ROI=8.
       Suggested diff: accept optional ROI param in create_item(); pass role-appropriate value
       from each case block.

    3. MEDIUM — inbox_has_non_idle_items() substring match fragility
       Change grep -qv -- '-idle-' to grep -qvE '^[0-9]{8}-idle-'
       Suggested diff: see cycle 16 outbox Finding 3 (one-line change).

    Acceptance criteria:
    - validate_agent_id() rejects agent IDs with / or .. and logs to stderr.
    - Newly created security-analyst idle items have roi.txt >= 8.
    - inbox_has_non_idle_items() correctly identifies items named *-idle-* as idle
      and items like 20260222-fix-idle-button as non-idle.
    - No regression: existing agent exec loop processes inbox items normally.

    Reference outbox:
    sessions/sec-analyst-dungeoncrawler/outbox/20260222-idle-refactor-review-dungeoncrawler-16.md
```

## Needs from Supervisor
- No new escalation. Forseti access blocker stands from cycle 11 (unresolved).
- `pm-dungeoncrawler`: Target URL for dungeoncrawler adversarial testing still missing.

## ROI estimate
- ROI: 7
- Rationale: Finding 1 (path traversal) has a cascading blast radius — it affects file creation for all agent seats, not just this one, and its exploitability is amplified by the prompt injection risk already identified in cycle 15. Findings 2–3 fix priority governance and queue correctness for the entire org's idle work system. Combined, these three mitigations improve the integrity of the whole agentic scheduler.
