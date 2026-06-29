# Outbox update: 20260222-idle-refactor-review-infrastructure-20

- Agent: qa-infra
- Source inbox: /home/keithaumiller/copilot-sessions-hq/sessions/qa-infra/inbox/20260222-idle-refactor-review-infrastructure-20
- Generated: 2026-02-22T16:40:39-05:00

---

I have sufficient findings from `consume-forseti-replies.sh`. Dev-infra is still at cap (3 items). Here is the outbox for cycle 20:

---

- Status: done
- Summary: Reviewed `scripts/consume-forseti-replies.sh` — the script that pulls Drupal UI replies and converts them into HQ inbox items. Found 6 concrete bugs: (1) critical Python IndentationError: after the `for r in data:` loop body uses 4-space indent, the lines `intended = to_agent` and everything following drop to 2-space indent — Python raises `IndentationError` on any non-empty reply list, silently swallowed because the bash command substitution `result="$(python3 ...)"` captures no output and the empty-ids guard exits 0, meaning all Drupal replies are permanently silently lost; (2) created inbox items never include `roi.txt`, violating the org-wide inbox ROI ordering policy and causing those items to be sorted last or ignored by `agent-exec-next.sh`'s ROI sort; (3) `resolved_items` is iterated in bash as a bare word `for item in $resolved_items` — if any `in_reply_to` value contains spaces or glob chars the loop splits or expands incorrectly; (4) Drush PHP eval interpolates the space-separated `$ids` string directly into a PHP string literal (`"'"$ids"'"`) — while currently safe because IDs are integers, any future non-integer ID value would enable PHP injection; (5) `FORSITI_SITE_DIR` has a typo (`FORSITI` vs `FORSETI`) in the variable name — harmless today but a latent readability/grep hazard; (6) `time.strftime('%Y%m%d')` inside Python uses the Python process clock, not bash's date, and could produce a different date than other scripts if run at midnight. Dev-infra is at capacity (3 items); follow-up recommendation staged for ceo-copilot escalation of the IndentationError since it is a silent data-loss bug.

## File reviewed
`scripts/consume-forseti-replies.sh`

## Findings

### 1. Python IndentationError — silent loss of ALL Drupal replies (CRITICAL)
Inside the `python3 - "$json" <<'PY'` heredoc, the `for r in data:` body is indented at 4 spaces:
```python
for r in data:
    rid = int(r.get("id"))
    ...
    if not to_agent or not msg:
        continue
```
But immediately after the `continue`, the next substantive lines drop to **2-space indent**:
```python
  intended = to_agent
  if configured and to_agent not in configured:
    to_agent = "ceo-copilot"

    slug = re.sub(...)
    item = f"..."
    ...
    ids.append(str(rid))
```
Python `IndentationError`: 2-space indent is less than the 4-space for-loop body but greater than the 0-space for-statement — Python treats this as an unexpected unindent and raises `IndentationError` before any code executes.

The crash is **silently swallowed**: `result="$(python3 ...)"` captures empty output on crash; `ids=""` → `[ -z "$ids" ]` → `exit 0`. No error is logged. Every unconsumed Drupal reply is skipped permanently (the DB `consumed` field is never set to 1, so the same rows will be re-fetched indefinitely but never processed).

**Minimal diff direction:** Fix indentation — the `intended = to_agent` line and everything through `ids.append(str(rid))` should be at 4-space indent (inside the for loop). The `if configured and to_agent not in configured:` block's body should be at 8-space indent:
```python
    intended = to_agent
    if configured and to_agent not in configured:
        to_agent = "ceo-copilot"

    slug = re.sub(r'[^A-Za-z0-9._-]+', '-', in_reply_to)[:50] or f"compose-{rid}"
    item = f"{time.strftime('%Y%m%d')}-reply-keith-{slug}-{rid}"
    inbox_dir = root / "sessions" / to_agent / "inbox" / item
    inbox_dir.mkdir(parents=True, exist_ok=True)
    ...
    ids.append(str(rid))
    mapping[str(rid)] = item
    if in_reply_to:
        resolved.append(in_reply_to)
```

Also add a crash guard so Python errors surface:
```bash
result="$(python3 - "$json" <<'PY' ... PY)" || { echo "Python error in reply processor" >&2; exit 1; }
```

### 2. Inbox items created without `roi.txt` (line ~60)
```python
inbox_dir.mkdir(parents=True, exist_ok=True)
(inbox_dir / "command.md").write_text(...)
```
No `roi.txt` is written. The org-wide policy (2026-02-22) requires every inbox item folder to include `roi.txt`. `agent-exec-next.sh` ROI-sorts candidates at line 176 — items without `roi.txt` are treated as ROI=0 and deprioritized or dropped.

**Minimal diff direction:**
```python
(inbox_dir / "roi.txt").write_text("5\n", encoding="utf-8")  # replies from CEO/Keith are high priority
```

### 3. `for item in $resolved_items` — word-splitting on in_reply_to values (line ~85)
```bash
for item in $resolved_items; do
```
`$resolved_items` is an unquoted space-separated string built from `in_reply_to` values. If any work-item ID contains a space or glob character, the loop splits the token into multiple directory names, attempting to move nonexistent paths.

**Minimal diff direction:** Use an array or `read -a`:
```bash
IFS=' ' read -ra resolved_arr <<< "$resolved_items"
for item in "${resolved_arr[@]}"; do
```
Or accumulate into a newline-separated value and use `while IFS= read -r item`.

### 4. PHP injection via bash interpolation of `$ids` (line ~70)
```bash
'preg_split("/\s+/", trim("'"$ids"'"))'
```
`$ids` is interpolated directly into a PHP string literal. Currently safe (IDs are integers from `int(r.get("id"))`), but if ID parsing ever changes or a malformed row returns a non-integer, this is a PHP injection point inside a `drush php:eval`.

**Minimal diff direction:** Pass IDs via environment variable:
```bash
(cd "$FORSITI_SITE_DIR" && IDS_ENV="$ids" MAP_JSON="$map_json" "$DRUSH_BIN" -q php:eval '
  $ids = preg_split("/\s+/", trim(getenv("IDS_ENV") ?: ""));
  ...
')
```

### 5. `FORSITI_SITE_DIR` variable name typo (line 10)
```bash
FORSITI_SITE_DIR="/home/keithaumiller/forseti.life/sites/forseti"
```
Typo: `FORSITI` should be `FORSETI`. Harmless to runtime but confusing to `grep`, code review, and any script that tries to reference this variable by the correct spelling.

**Minimal diff direction:** Rename to `FORSETI_SITE_DIR` (also rename in `publish-forseti-agent-tracker.sh` which has the same variable).

### 6. `time.strftime('%Y%m%d')` uses Python clock, not bash date
The item ID prefix is generated inside Python using `time.strftime('%Y%m%d')`. If the script is invoked just before midnight and Python runs just after midnight, the item ID date will differ from the bash script's date context. Cosmetic, but inconsistent with the convention of using a single `DATE_YYYYMMDD` captured in bash.

**Minimal diff direction:** Pass the date as an argument:
```bash
python3 - "$json" "$(date +%Y%m%d)" <<'PY'
date_str = sys.argv[2]
# use date_str instead of time.strftime('%Y%m%d')
```

## Follow-up task (staged — dev-infra at cap)

### Task for dev-infra when slot opens: Fix consume-forseti-replies.sh critical bugs
- **File:** `scripts/consume-forseti-replies.sh`
- **Scope:**
  1. Fix Python IndentationError (finding 1) — add crash surfacing guard
  2. Write `roi.txt` for each created inbox item (finding 2)
  3. Fix word-splitting in `for item in $resolved_items` (finding 3)
  4. Pass `$ids` via env var to PHP eval (finding 4)
- **Acceptance criteria:**
  - Script processes a test reply row in the DB and creates an inbox item with `roi.txt`
  - Python errors are printed to stderr and exit non-zero rather than silently producing empty output
  - `resolved_items` with a space in the item name does not cause a spurious `mv` attempt
- **Verification:** Insert a test row with `consumed=0` into `copilot_agent_tracker_replies`; run the script; confirm inbox item + roi.txt created and `consumed=1` in DB
- **ROI:** 9 — this script is the only path for Drupal UI replies to reach HQ; the IndentationError means every reply from the Drupal interface has been silently discarded since the indentation bug was introduced

## Next actions
- Executor: when dev-infra slot opens, queue the Task above (`sessions/dev-infra/inbox/20260222-fix-consume-forseti-replies/` with `roi.txt` = 9)
- Remaining unreviewed candidates: `scripts/publish-forseti-agent-tracker.sh`, `runbooks/session-lifecycle.md`, `scripts/dispatch-pm-request.sh`

## Blockers
- None

## Needs from CEO
- None

## ROI estimate
- ROI: 9
- Rationale: The Python IndentationError in `consume-forseti-replies.sh` is a silent data-loss bug — every reply entered via the Drupal UI is permanently silently dropped, making the Drupal→HQ reply path completely non-functional. This is the highest-ROI finding of any cycle to date because it silently breaks a user-facing workflow with no error surfaced anywhere in the system.
