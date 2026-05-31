#!/usr/bin/env bash
# ceo-system-health.sh — Systemic health checks beyond the release pipeline.
# Covers: error logs, executor failures, orchestrator health, feature velocity,
# scoreboard freshness, tailoring queue, KB lesson rate, dead-letter inboxes.
#
# Exit 0 = all checks pass
# Exit 1 = one or more FAIL or actionable WARN found
#
# Usage:
#   bash scripts/ceo-system-health.sh              # report only
#   bash scripts/ceo-system-health.sh --dispatch   # report + create agent inbox items for each finding
#   bash scripts/ceo-system-health.sh --json       # report + JSON summary line
set -euo pipefail
ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
cd "$ROOT_DIR"

# shellcheck source=scripts/lib/merge-health.sh
source "./scripts/lib/merge-health.sh"

JSON_MODE=0
DISPATCH_MODE=0
for arg in "$@"; do
  [[ "$arg" == "--json" ]]     && JSON_MODE=1
  [[ "$arg" == "--dispatch" ]] && DISPATCH_MODE=1
done

FAIL_COUNT=0
WARN_COUNT=0
RESULTS=()
# Dispatch queue: "agent|slug|severity|title|body" entries collected during checks
DISPATCH_ITEMS=()

now_ts=$(date +%s)
now_iso=$(date -u +"%Y-%m-%dT%H:%M:%SZ")
date_prefix=$(date -u +"%Y%m%d")
APACHE_LOG_DIR="${APACHE_LOG_DIR:-/var/log/apache2}"
APACHE_FATAL_QUIET_MINUTES="${APACHE_FATAL_QUIET_MINUTES:-30}"

SEP="────────────────────────────────────────────────────────"

pass()  { echo "✅ PASS $*"; RESULTS+=("PASS|$*"); }
fail()  { echo "❌ FAIL $*"; RESULTS+=("FAIL|$*"); FAIL_COUNT=$(( FAIL_COUNT + 1 )); }
warn()  { echo "⚠️  WARN $*"; RESULTS+=("WARN|$*"); WARN_COUNT=$(( WARN_COUNT + 1 )); }
info()  { echo "   ℹ️  $*"; }

apache_recent_matching_lines() {
  local log_path="$1"
  local window_seconds="$2"
  local include_pattern="$3"
  local exclude_pattern="${4:-}"
  python3 - "$log_path" "$window_seconds" "$include_pattern" "$exclude_pattern" <<'PY'
from datetime import datetime
import pathlib
import re
import sys

path = pathlib.Path(sys.argv[1])
window_seconds = int(sys.argv[2])
include_pattern = re.compile(sys.argv[3])
exclude_pattern = re.compile(sys.argv[4]) if sys.argv[4] else None
now = datetime.now()

def parse_timestamp(line: str):
    if not line.startswith("["):
        return None
    try:
        raw = line.split("]", 1)[0][1:]
    except IndexError:
        return None
    for fmt in ("%a %b %d %H:%M:%S.%f %Y", "%a %b %d %H:%M:%S %Y"):
        try:
            return datetime.strptime(raw, fmt)
        except ValueError:
            continue
    return None

try:
    lines = path.read_text(encoding="utf-8", errors="ignore").splitlines()
except FileNotFoundError:
    raise SystemExit(0)

matches = []
for line in lines:
    if exclude_pattern and exclude_pattern.search(line):
        continue
    if not include_pattern.search(line):
        continue
    ts = parse_timestamp(line)
    if ts is None:
        continue
    age_seconds = (now - ts).total_seconds()
    if 0 <= age_seconds <= window_seconds:
        matches.append(line)

if matches:
    print("\n".join(matches))
PY
}

apache_last_match_age_seconds() {
  local log_path="$1"
  local include_pattern="$2"
  local exclude_pattern="${3:-}"
  python3 - "$log_path" "$include_pattern" "$exclude_pattern" <<'PY'
from datetime import datetime
import pathlib
import re
import sys

path = pathlib.Path(sys.argv[1])
include_pattern = re.compile(sys.argv[2])
exclude_pattern = re.compile(sys.argv[3]) if sys.argv[3] else None
now = datetime.now()

def parse_timestamp(line: str):
    if not line.startswith("["):
        return None
    try:
        raw = line.split("]", 1)[0][1:]
    except IndexError:
        return None
    for fmt in ("%a %b %d %H:%M:%S.%f %Y", "%a %b %d %H:%M:%S %Y"):
        try:
            return datetime.strptime(raw, fmt)
        except ValueError:
            continue
    return None

try:
    lines = path.read_text(encoding="utf-8", errors="ignore").splitlines()
except FileNotFoundError:
    print("")
    raise SystemExit(0)

last_age_seconds = None
for line in lines:
    if exclude_pattern and exclude_pattern.search(line):
        continue
    if not include_pattern.search(line):
        continue
    ts = parse_timestamp(line)
    if ts is None:
        continue
    age_seconds = int((now - ts).total_seconds())
    if age_seconds < 0:
        continue
    last_age_seconds = age_seconds

print("" if last_age_seconds is None else last_age_seconds)
PY
}

runner_pids() {
  local pattern="$1"
  ps -eo pid=,ppid=,args= 2>/dev/null | awk -v pattern="$pattern" '$0 ~ pattern {print $1 "\t" $2}'
}

# queue_dispatch <agent> <slug> <roi> <severity> <title> <body>
queue_dispatch() {
  local agent="$1" slug="$2" roi="$3" severity="$4" title="$5" body="$6"
  DISPATCH_ITEMS+=("${agent}|${slug}|${roi}|${severity}|${title}|${body}")
}

# create_inbox_item — writes one inbox folder if it doesn't already exist
create_inbox_item() {
  local agent="$1" slug="$2" roi="$3" title="$4" body="$5"
  local dir="sessions/${agent}/inbox/${date_prefix}-syshealth-${slug}"
  if [ -d "$dir" ]; then
    echo "   ℹ️  [dispatch] Already exists, skipping: $dir"
    return
  fi
  mkdir -p "$dir"
  printf '%s\n' "$roi" > "$dir/roi.txt"
  # Use printf %b to interpret \n escape sequences in body
  body_rendered=$(printf '%b' "$body")
  cat > "$dir/README.md" <<ITEMEOF
# ${title}

- Agent: ${agent}
- Dispatched-by: ceo-copilot-2 (ceo-system-health.sh)
- Dispatched-at: ${now_iso}
- Source: system health check

## Issue

${body_rendered}

## Acceptance criteria
- Issue resolved and verified with command output or log evidence
- Outbox entry filed with Status: done and verification steps

## Verification
- Re-run: \`bash scripts/ceo-system-health.sh\` — relevant check should show ✅ PASS
ITEMEOF
  echo "   📥 [dispatch] Created: $dir"
}

echo "═══════════════════════════════════════════════════════"
echo "  CEO System Health Check"
echo "  $now_iso"
echo "═══════════════════════════════════════════════════════"

# ─── 1. EXECUTOR FAILURES ───────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Executor Failures  (tmp/executor-failures/)"
echo "$SEP"

failure_dir="tmp/executor-failures"
if [ -d "$failure_dir" ]; then
  total_failures=$(ls "$failure_dir" | wc -l)
  recent_failures=$(find "$failure_dir" -newer <(date -d '24 hours ago' +%Y%m%d%H%M%S 2>/dev/null || date -v-24H +%Y%m%d%H%M%S 2>/dev/null || echo "19700101000000") -type f 2>/dev/null | wc -l || \
    find "$failure_dir" -mmin -1440 -type f 2>/dev/null | wc -l)

  if [ "$recent_failures" -gt 10 ]; then
    fail "Executor failures (last 24h): $recent_failures  (total: $total_failures)"
    info "Recent: $(ls -t "$failure_dir" | head -3 | tr '\n' ' ')"
    info "Investigate: head tmp/executor-failures/\$(ls -t tmp/executor-failures/ | head -1)"
    queue_dispatch "dev-infra" "executor-failures-spike" "8" "FAIL" \
      "Executor failure spike: $recent_failures failures in 24h" \
      "The executor failure directory has $recent_failures new failures in the last 24 hours (total: $total_failures).\n\nRecent items:\n\`\`\`\n$(ls -t "$failure_dir" | head -5 | tr '\n' '\n')\`\`\`\n\nInvestigate root cause. Check agent command errors, tool timeouts, and permission issues. Prune resolved items."
  elif [ "$recent_failures" -gt 0 ]; then
    warn "Executor failures (last 24h): $recent_failures  (total: $total_failures)"
    info "Recent: $(ls -t "$failure_dir" | head -3 | tr '\n' ' ')"
    queue_dispatch "dev-infra" "executor-failures-backlog" "5" "WARN" \
      "Executor failure backlog: $recent_failures new in 24h (total: $total_failures)" \
      "The executor failure directory has $recent_failures new entries in the last 24 hours and $total_failures total.\n\nReview recent failures and prune resolved items:\n\`\`\`\nbash: ls -t tmp/executor-failures/ | head -5\n\`\`\`"
  elif [ "$total_failures" -gt 100 ]; then
    warn "Executor failure backlog: $total_failures items — consider pruning after triage"
    info "Run: ls -t tmp/executor-failures/ | head -5"
    queue_dispatch "dev-infra" "executor-failures-prune" "3" "WARN" \
      "Executor failure backlog needs pruning: $total_failures items" \
      "The executor failure directory has $total_failures accumulated items. Review and prune resolved/stale entries to keep signal clear."
  else
    pass "Executor failures (last 24h): $recent_failures  (total: $total_failures)"
  fi
else
  pass "Executor failures: directory not present"
fi

# ─── 2. ORCHESTRATOR HEALTH ─────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Orchestrator Health"
echo "$SEP"

pid_file=".orchestrator-loop.pid"
health_file="tmp/orchestrator-health-last-autoexec"

if [ -f "$pid_file" ]; then
  orc_pid=$(cat "$pid_file" 2>/dev/null || true)
  if [[ "$orc_pid" =~ ^[0-9]+$ ]] && ps -p "$orc_pid" >/dev/null 2>&1; then
    pass "Orchestrator: running (pid $orc_pid)"
  else
    fail "Orchestrator: pid file exists but process $orc_pid is not running"
    info "Restart: bash scripts/orchestrator-loop.sh start"
    queue_dispatch "dev-infra" "orchestrator-down" "9" "FAIL" \
      "Orchestrator process is down" \
      "The orchestrator pid file exists but process $orc_pid is not running.\n\nRestart:\n\`\`\`bash\nbash scripts/orchestrator-loop.sh start\n\`\`\`\nThen verify with: bash scripts/orchestrator-loop.sh status"
  fi
else
  warn "Orchestrator: no pid file found — may not be running"
  info "Start: bash scripts/orchestrator-loop.sh start"
  queue_dispatch "dev-infra" "orchestrator-no-pid" "7" "WARN" \
    "Orchestrator has no pid file — may not be running" \
    "No orchestrator pid file found at .orchestrator-loop.pid.\n\nVerify if it's running and restart if needed:\n\`\`\`bash\nbash scripts/orchestrator-loop.sh start\n\`\`\`"
fi

if [ -f "$health_file" ]; then
  last_ts=$(cat "$health_file" 2>/dev/null | tr -d '[:space:]' || echo "0")
  if [[ "$last_ts" =~ ^[0-9]+$ ]]; then
    age_h=$(( (now_ts - last_ts) / 3600 ))
    if [ "$age_h" -gt 2 ]; then
      warn "Orchestrator last autoexec: ${age_h}h ago (expected < 2h)"
    else
      pass "Orchestrator last autoexec: ${age_h}h ago"
    fi
  else
    warn "Orchestrator health file unreadable: $health_file"
  fi
else
  warn "Orchestrator: no last-autoexec health file"
fi

# ─── 2A. AUTOMATION DUPLICATION / COPILOT PRESSURE ──────────────────────────
echo ""
echo "$SEP"
echo "  Automation Duplication / Copilot Pressure"
echo "$SEP"

legacy_agent_exec_lines="$(runner_pids '[[:space:]]([.]/)?scripts/agent-exec-loop[.]sh run($|[[:space:]])')"
legacy_agent_exec_count=$(printf '%s\n' "$legacy_agent_exec_lines" | sed '/^$/d' | wc -l | tr -d ' ')
if [ "${legacy_agent_exec_count:-0}" -gt 0 ]; then
  legacy_agent_exec_pids="$(printf '%s\n' "$legacy_agent_exec_lines" | awk '{print $1}' | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/^ //; s/ $//')"
  fail "Legacy agent-exec-loop is running: pid(s) ${legacy_agent_exec_pids}"
  info "Expected state: agent-exec-loop stopped; orchestrator is the only scheduler"
  info "Stop legacy runner: bash scripts/disable-agent-exec-loop.sh"
  queue_dispatch "dev-infra" "legacy-agent-exec-running" "10" "FAIL" \
    "Legacy agent-exec-loop is still running" \
    "The deprecated agent-exec-loop is still running alongside orchestrator-driven automation.\n\nObserved pid(s): ${legacy_agent_exec_pids}\n\nThis can double agent traffic and amplify Copilot rate-limit issues.\n\nRemediate:\n\`\`\`bash\nbash scripts/disable-agent-exec-loop.sh\nbash scripts/hq-automation.sh status\n\`\`\`\nThen re-run:\n\`\`\`bash\nbash scripts/ceo-system-health.sh\n\`\`\`"
else
  pass "Legacy agent-exec-loop: not running"
fi

orchestrator_lines="$(runner_pids '[[:space:]]([.]/)?scripts/orchestrator-loop[.]sh run($|[[:space:]])')"
orchestrator_root_count=$(printf '%s\n' "$orchestrator_lines" | awk '$2 == 1 {count += 1} END {print count + 0}')
orchestrator_visible_count=$(printf '%s\n' "$orchestrator_lines" | sed '/^$/d' | wc -l | tr -d ' ')
if [ "${orchestrator_root_count:-0}" -gt 1 ]; then
  orchestrator_root_pids="$(printf '%s\n' "$orchestrator_lines" | awk '$2 == 1 {print $1}' | tr '\n' ' ' | sed -E 's/[[:space:]]+/ /g; s/^ //; s/ $//')"
  fail "Duplicate orchestrator roots detected: pid(s) ${orchestrator_root_pids}"
  info "Expected one top-level orchestrator-loop process (the launcher may also show one child shell)"
  info "Clean restart: bash scripts/orchestrator-loop.sh stop && bash scripts/orchestrator-loop.sh start 60"
  queue_dispatch "dev-infra" "duplicate-orchestrator-roots" "10" "FAIL" \
    "Duplicate top-level orchestrator loops detected" \
    "More than one top-level orchestrator-loop process is running.\n\nObserved top-level pid(s): ${orchestrator_root_pids}\n\nThis can duplicate scheduler ticks and compound agent execution / Copilot rate-limit pressure.\n\nRemediate with a clean restart:\n\`\`\`bash\nbash scripts/orchestrator-loop.sh stop\nbash scripts/orchestrator-loop.sh start 60\nbash scripts/orchestrator-loop.sh status\n\`\`\`"
elif [ "${orchestrator_visible_count:-0}" -gt 2 ]; then
  warn "Extra orchestrator-loop processes visible: ${orchestrator_visible_count} process(es)"
  info "Visible pid/ppid pairs:"
  while IFS= read -r line; do
    [ -n "$line" ] || continue
    info "$line"
  done <<<"$orchestrator_lines"
else
  pass "Orchestrator loop visibility: ${orchestrator_visible_count:-0} process(es) (expected launcher + child)"
fi

copilot_rate_limit_hits=0
for log in \
  "inbox/responses/agent-exec-latest.log" \
  "inbox/responses/agent-exec-$(date -u +%Y%m%d).log" \
  "inbox/responses/orchestrator-latest.log" \
  "inbox/responses/orchestrator-$(date -u +%Y%m%d).log"
do
  [ -f "$log" ] || continue
  hits="$(tail -n 200 "$log" 2>/dev/null | grep -cE "hit a rate limit|Too Many Requests|Please try again in [0-9]+ (seconds?|minutes?)" || true)"
  hits="${hits:-0}"
  copilot_rate_limit_hits=$(( copilot_rate_limit_hits + hits ))
done

copilot_rate_limit_failures=0
if [ -d "$failure_dir" ]; then
  copilot_rate_limit_failures="$({ find "$failure_dir" -mmin -1440 -type f -exec \
      grep -liE "Copilot rate limit|hit a rate limit|Too Many Requests|Please try again in [0-9]+ (seconds?|minutes?)" {} + 2>/dev/null \
      || true; } \
    | wc -l | tr -d ' ')"
fi
copilot_rate_limit_failures="${copilot_rate_limit_failures:-0}"

if [ "$copilot_rate_limit_hits" -gt 0 ] || [ "$copilot_rate_limit_failures" -gt 0 ]; then
  warn "Recent Copilot rate-limit signatures: logs=${copilot_rate_limit_hits} failure-records=${copilot_rate_limit_failures}"
  info "Check runner duplication, COPILOT_API_MIN_DELAY_SECONDS, and recent executor failures"
  queue_dispatch "dev-infra" "copilot-rate-limit-pressure" "8" "WARN" \
    "Recent Copilot rate-limit pressure detected" \
    "Recent automation logs/failure records contain Copilot rate-limit signatures.\n\nCounts:\n- log hits: ${copilot_rate_limit_hits}\n- failure records (24h): ${copilot_rate_limit_failures}\n\nQuick checks:\n\`\`\`bash\nbash scripts/hq-automation.sh status\nbash scripts/orchestrator-loop.sh status\nls -t tmp/executor-failures | head -5\n\`\`\`\nConfirm only the orchestrator is running, then verify backoff/cooldown behavior in \`scripts/agent-exec-next.sh\`."
else
  pass "Recent Copilot rate-limit signatures: none detected"
fi

# ─── 3. MERGE HEALTH ───────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Merge Health"
echo "$SEP"

merge_health_scan "."
if [ "$MERGE_HEALTH_IN_GIT_REPO" -eq 0 ]; then
  warn "Merge health: not a git repository"
elif [ "$MERGE_HEALTH_HAS_ISSUES" -eq 1 ]; then
  merge_details=""
  while IFS= read -r detail; do
    [ -n "$detail" ] || continue
    if [ -n "$merge_details" ]; then
      merge_details+="\n"
    fi
    merge_details+="$detail"
  done < <(merge_health_issue_lines 20)
  fail "Merge health: $MERGE_HEALTH_SUMMARY"
  while IFS= read -r detail; do
    [ -n "$detail" ] || continue
    info "$detail"
  done < <(merge_health_issue_lines 20)
  info "Inspect: git status --short --branch"
  if [ "$MERGE_HEALTH_MERGE_HEAD" -eq 1 ]; then
    info "Abort if safe: git merge --abort"
  fi
  if [ "$MERGE_HEALTH_REBASE_IN_PROGRESS" -eq 1 ]; then
    info "Abort/continue rebase as appropriate: git rebase --abort | git rebase --continue"
  fi
  if [ "$MERGE_HEALTH_CHERRY_PICK_HEAD" -eq 1 ]; then
    info "Abort/continue cherry-pick as appropriate: git cherry-pick --abort | git cherry-pick --continue"
  fi
  if [ "$MERGE_HEALTH_REVERT_HEAD" -eq 1 ]; then
    info "Abort/continue revert as appropriate: git revert --abort | git revert --continue"
  fi
  if [ "$MERGE_HEALTH_UNMERGED_COUNT" -gt 0 ]; then
    info "After resolving conflicts: git add <resolved-files> && git commit"
  fi
  if [ "$MERGE_HEALTH_TRACKED_CHANGE_COUNT" -gt 0 ]; then
    info "Checkpoint or stash local tracked changes before the next merge/pull"
  fi
  queue_dispatch "dev-infra" "merge-health-remediation" "10" "FAIL" \
    "HQ repo has merge/integration blockers" \
    "The HQ repo has merge/integration blockers.\n\nSummary: ${MERGE_HEALTH_SUMMARY}\n\nDetails:\n\`\`\`\n${merge_details}\n\`\`\`\n\nInspect:\n\`\`\`bash\ngit status --short --branch\n\`\`\`\nIf a merge is in progress and should be abandoned:\n\`\`\`bash\ngit merge --abort\n\`\`\`\nIf a rebase/cherry-pick/revert is in progress, finish or abort it. If local tracked changes are pending, checkpoint/stash/clean them before the next merge or pull."
else
  pass "Merge health: no active merge conflicts, unfinished integration state, or dirty tracked changes"
fi

# ─── 4. APACHE ERROR LOG ANALYSIS ──────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Apache Error Logs (real errors, last 24h)"
echo "$SEP"

for site in forseti dungeoncrawler; do
  log="${APACHE_LOG_DIR}/${site}_error.log"
  if [ ! -f "$log" ]; then
    info "[$site] No error log at $log"
    continue
  fi

  php_pattern="PHP Fatal|PHP Parse error|PHP Exception|Uncaught PHP Exception"
  php_fatal_lines="$(apache_recent_matching_lines "$log" 86400 "$php_pattern")"
  php_fatal=$(printf '%s\n' "$php_fatal_lines" | sed '/^$/d' | wc -l | tr -d ' ')
  php_fatal=${php_fatal:-0}
  quiet_window_seconds=$(( APACHE_FATAL_QUIET_MINUTES * 60 ))
  php_fatal_active_lines="$(apache_recent_matching_lines "$log" "$quiet_window_seconds" "$php_pattern")"
  php_fatal_active=$(printf '%s\n' "$php_fatal_active_lines" | sed '/^$/d' | wc -l | tr -d ' ')
  php_fatal_active=${php_fatal_active:-0}
  php_last_age_seconds="$(apache_last_match_age_seconds "$log" "$php_pattern")"
  php_last_age_minutes=""
  if [[ "$php_last_age_seconds" =~ ^[0-9]+$ ]]; then
    php_last_age_minutes=$(( php_last_age_seconds / 60 ))
  fi
  real_error_lines="$(apache_recent_matching_lines "$log" 86400 "\\[error\\]" "AH01630")"
  real_errors=$(printf '%s\n' "$real_error_lines" | sed '/^$/d' | wc -l | tr -d ' ')
  real_errors=${real_errors:-0}

  if [ "${php_fatal}" -gt 0 ]; then
    if [ "${php_fatal_active}" -gt 0 ]; then
      fail "[$site] PHP Fatal/Parse/Exception errors: $php_fatal in last 24h ($php_fatal_active in last ${APACHE_FATAL_QUIET_MINUTES}m)"
      info "$(printf '%s\n' "$php_fatal_lines" | tail -2)"
      dev_agent="dev-${site}"
      [[ "$site" == "forseti" ]] && dev_agent="dev-forseti"
      queue_dispatch "$dev_agent" "php-fatal-${site}" "9" "FAIL" \
        "PHP Fatal errors in Apache log: $site ($php_fatal_active active, $php_fatal in 24h)" \
        "PHP fatal/parse/exception errors found in ${log}.\n\nActive window: last ${APACHE_FATAL_QUIET_MINUTES} minutes.\nRecent lines:\n\`\`\`\n$(printf '%s\n' "$php_fatal_lines" | tail -3)\n\`\`\`\n\nInvestigate and fix. Verify site returns HTTP 200 after fix."
    else
      warn "[$site] PHP Fatal/Parse/Exception errors: $php_fatal in last 24h, but none in last ${APACHE_FATAL_QUIET_MINUTES}m"
      if [ -n "$php_last_age_minutes" ]; then
        info "Last fatal/exception was ${php_last_age_minutes}m ago"
      fi
      info "$(printf '%s\n' "$php_fatal_lines" | tail -2)"
    fi
  elif [ "${real_errors}" -gt 50 ]; then
    warn "[$site] Non-scan Apache errors: $real_errors (last 24h)"
    info "$(printf '%s\n' "$real_error_lines" | tail -2)"
    queue_dispatch "dev-infra" "apache-errors-${site}" "6" "WARN" \
      "High Apache error rate: $site ($real_errors non-scan errors)" \
      "Apache error log /var/log/apache2/${site}_error.log has $real_errors non-security-scan errors.\n\nInvestigate and resolve."
  else
    pass "[$site] No PHP fatals; non-scan errors: $real_errors"
  fi

  # Flag repeated security probe IPs (same IP probing .env/.git > 20 times)
  top_probe=$(grep "AH01630" "$log" 2>/dev/null | grep -oE 'client [0-9.]+' | awk '{print $2}' | sort | uniq -c | sort -rn | head -1 || true)
  if [ -n "$top_probe" ]; then
    probe_count=$(echo "$top_probe" | awk '{print $1}')
    probe_ip=$(echo "$top_probe" | awk '{print $2}')
    if [ "${probe_count:-0}" -gt 20 ] 2>/dev/null; then
      warn "[$site] High-volume security probe: $probe_ip ($probe_count hits) — consider rate-limiting or fail2ban"
      queue_dispatch "dev-infra" "security-probe-${site}" "5" "WARN" \
        "High-volume security probe on $site: $probe_ip ($probe_count hits)" \
        "IP $probe_ip has probed $site for .env/.git files $probe_count times.\n\nConsider adding to fail2ban or rate-limiting in Apache config."
    fi
  fi
done

# ─── 5. DRUPAL WATCHDOG ─────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Drupal Watchdog (forseti.life)"
echo "$SEP"

drupal_root="/var/www/html/forseti"
if [ ! -f "$drupal_root/vendor/bin/drush" ]; then
  drupal_root="/home/ubuntu/forseti.life/sites/forseti"
fi
if [ -f "$drupal_root/vendor/bin/drush" ]; then
  watchdog_out=$(cd "$drupal_root" && vendor/bin/drush watchdog:show --severity=3 --count=5 --format=string 2>/dev/null || echo "DRUSH_UNAVAILABLE")
  if echo "$watchdog_out" | grep -q "DRUSH_UNAVAILABLE\|Error\|Cannot"; then
    warn "Drupal watchdog: drush unavailable or errored"
  elif [ -z "$(echo "$watchdog_out" | tr -d '[:space:]')" ]; then
    pass "Drupal watchdog: no recent errors"
  else
    error_count=$(echo "$watchdog_out" | grep -c "^" || echo 0)
    if [ "$error_count" -gt 0 ]; then
      fail "Drupal watchdog: $error_count recent error(s)"
      echo "$watchdog_out" | head -5 | sed 's/^/   /'
      info "Full log: cd $drupal_root && vendor/bin/drush watchdog:show --severity=error"
      queue_dispatch "dev-forseti" "drupal-watchdog-errors" "8" "FAIL" \
        "Drupal watchdog has $error_count recent error(s)" \
        "Drush watchdog:show reports $error_count errors.\n\nCheck:\n\`\`\`bash\ncd $drupal_root && vendor/bin/drush watchdog:show --severity=error\n\`\`\`\n\nInvestigate and resolve each error. Verify clean watchdog after fix."
    fi
  fi
else
  info "Drupal watchdog: drush not found at $drupal_root/vendor/bin/drush"
fi

# ─── 6. SCOREBOARD FRESHNESS ────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Scoreboard Freshness  (target: updated within 7 days)"
echo "$SEP"

scoreboard_dir="knowledgebase/scoreboards"
stale_boards=0
if [ -d "$scoreboard_dir" ]; then
  for board in "$scoreboard_dir"/*.md; do
    [ -f "$board" ] || continue
    board_name=$(basename "$board" .md)
    mtime=$(stat -c %Y "$board" 2>/dev/null || stat -f %m "$board" 2>/dev/null || echo 0)
    age_days=$(( (now_ts - mtime) / 86400 ))
    if [ "$age_days" -gt 7 ]; then
      warn "Scoreboard stale: $board_name (${age_days}d old)"
      stale_boards=$(( stale_boards + 1 ))
      # Map board name to PM agent
      pm_agent="pm-forseti"
      [[ "$board_name" == *"dungeoncrawler"* ]] && pm_agent="pm-dungeoncrawler"
      queue_dispatch "$pm_agent" "scoreboard-stale-${board_name}" "3" "WARN" \
        "Scoreboard stale: $board_name (${age_days}d old)" \
        "The weekly scoreboard at knowledgebase/scoreboards/${board_name}.md has not been updated in ${age_days} days (target: ≤7 days).\n\nUpdate with current KPI data: post-merge regressions, reopen rate, time-to-verify, escaped defects, audit freshness."
    else
      pass "Scoreboard fresh: $board_name (${age_days}d old)"
    fi
  done
  [ "$stale_boards" -eq 0 ] && info "All scoreboards within 7-day freshness target"
else
  warn "Scoreboard directory missing: $scoreboard_dir"
fi

# ─── 7. FEATURE VELOCITY ────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Feature Velocity  (shipped features per recent release)"
echo "$SEP"

for site in forseti dungeoncrawler; do
  feature_dir="features/$site"
  [ -d "$feature_dir" ] || continue

  total=$(grep -rl "Status: shipped" "$feature_dir"/*/feature.md 2>/dev/null | wc -l || echo 0)
  in_progress=$(grep -rl "Status: in_progress" "$feature_dir"/*/feature.md 2>/dev/null | wc -l || echo 0)
  ready=$(grep -rl "Status: ready" "$feature_dir"/*/feature.md 2>/dev/null | wc -l || echo 0)
  info "[$site] shipped=$total  in_progress=$in_progress  ready(backlog)=$ready"

  # Stale in_progress: mtime > 48h
  stale_ip=0
  while IFS= read -r ffile; do
    fmtime=$(stat -c %Y "$ffile" 2>/dev/null || stat -f %m "$ffile" 2>/dev/null || echo 0)
    age_h=$(( (now_ts - fmtime) / 3600 ))
    if [ "$age_h" -gt 48 ]; then
      warn "[$site] Stale in_progress feature (${age_h}h): $(dirname "$ffile" | xargs basename)"
      stale_ip=$(( stale_ip + 1 ))
      feature_id=$(dirname "$ffile" | xargs basename)
      dev_agent="dev-${site}"
      queue_dispatch "$dev_agent" "stale-feature-${feature_id}" "6" "WARN" \
        "Stale in_progress feature: $feature_id (${age_h}h without update)" \
        "Feature $feature_id has been in_progress for ${age_h}h without a file update.\n\nEither complete implementation and update status to 'done', or re-scope back to 'ready' if blocked. File outbox entry with current status."
    fi
  done < <(grep -rl "Status: in_progress" "$feature_dir"/*/feature.md 2>/dev/null || true)

  if [ "$stale_ip" -eq 0 ] && [ "$in_progress" -gt 0 ]; then
    pass "[$site] All $in_progress in_progress feature(s) recently active"
  elif [ "$in_progress" -eq 0 ]; then
    pass "[$site] No in_progress features (release between cycles or idle)"
  fi
done

# ─── 8. KB LESSON RATE ──────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  KB Lesson Rate  (lessons filed in last 7 days)"
echo "$SEP"

lesson_dir="knowledgebase/lessons"
if [ -d "$lesson_dir" ]; then
  recent_lessons=$(find "$lesson_dir" -name "*.md" -mtime -7 2>/dev/null | wc -l)
  total_lessons=$(find "$lesson_dir" -name "*.md" 2>/dev/null | wc -l)
  if [ "$recent_lessons" -eq 0 ]; then
    warn "No KB lessons filed in last 7 days (total: $total_lessons) — confirm friction is being captured"
    info "If recurring blockers occurred this week, file a lesson: knowledgebase/lessons/YYYYMMDD-<slug>.md"
  else
    pass "KB lessons filed in last 7 days: $recent_lessons (total: $total_lessons)"
  fi
else
  warn "KB lessons directory missing: $lesson_dir"
fi

# ─── 9. TAILORING QUEUE ─────────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Drupal Queue Health  (tailoring queue)"
echo "$SEP"

queue_log="/var/log/drupal/tailoring_queue.log"
if [ -f "$queue_log" ]; then
  last_entry=$(tail -1 "$queue_log" 2>/dev/null || true)
  last_mtime=$(stat -c %Y "$queue_log" 2>/dev/null || echo 0)
  age_h=$(( (now_ts - last_mtime) / 3600 ))
  # Check for error patterns in the last 500 lines only (avoids false positives from rotated/historical log data)
  error_count="$(python3 - "$queue_log" <<'PY'
import pathlib
import re
import sys

path = pathlib.Path(sys.argv[1])
try:
    lines = path.read_text(encoding="utf-8", errors="ignore").splitlines()[-500:]
except FileNotFoundError:
    print(0)
    raise SystemExit(0)

pattern = re.compile(r"error|exception|failed", re.IGNORECASE)
print(sum(1 for line in lines if pattern.search(line)))
PY
)"

  if [ "$error_count" -gt 0 ]; then
    fail "Tailoring queue log has $error_count error/exception lines (last 500 lines)"
    info "$(tail -500 "$queue_log" | grep -i "error\|exception\|failed" | tail -2)"
    queue_dispatch "dev-forseti" "tailoring-queue-errors" "8" "FAIL" \
      "Tailoring queue has $error_count error/exception lines in log (recent)" \
      "The Drupal tailoring queue log ($queue_log) contains $error_count recent error/exception/failed lines.\n\nRecent errors:\n\`\`\`\n$(tail -500 "$queue_log" | grep -i "error\|exception\|failed" | tail -5)\n\`\`\`\n\nInvestigate the AI resume service integration. Check JSON parsing, API connectivity, and cache state. Fix and verify the queue processes without errors."
  elif [ "$age_h" -gt 2 ]; then
    warn "Tailoring queue log last updated ${age_h}h ago — queue cron may be stopped"
    info "Check: crontab -l | grep tailoring"
    queue_dispatch "dev-infra" "tailoring-queue-cron-stopped" "7" "WARN" \
      "Tailoring queue cron appears stopped (log ${age_h}h stale)" \
      "The tailoring queue log has not been updated in ${age_h}h. The queue cron may be stopped.\n\nCheck and restart:\n\`\`\`bash\ncrontab -l | grep tailoring\n\`\`\`"
  else
    pass "Tailoring queue: processing normally (log updated ${age_h}h ago)"
    info "Last entry: $last_entry"
  fi
else
  info "Tailoring queue log not found: $queue_log"
fi

# ─── 10. QA AUDIT FRESHNESS ─────────────────────────────────────────────────
echo ""
echo "$SEP"
echo "  QA Audit Freshness  (auto-site-audit/latest)"
echo "$SEP"

for site_qa in qa-forseti qa-dungeoncrawler; do
  latest_link="sessions/${site_qa}/artifacts/auto-site-audit/latest"
  if [ -L "$latest_link" ] || [ -d "$latest_link" ]; then
    # Get mtime of the symlink target
    audit_mtime=$(stat -c %Y "$latest_link" 2>/dev/null || stat -f %m "$latest_link" 2>/dev/null || echo 0)
    age_h=$(( (now_ts - audit_mtime) / 3600 ))
    findings=""
    if [ -f "${latest_link}/findings-summary.md" ]; then
      findings=$(grep -c "^[|*-]" "${latest_link}/findings-summary.md" 2>/dev/null || echo "?")
    fi
    if [ "$age_h" -gt 24 ]; then
      warn "[$site_qa] Audit stale: ${age_h}h old (target ≤24h)"
      info "Rerun: bash scripts/site-audit-run.sh ${site_qa#qa-}"
      queue_dispatch "$site_qa" "audit-stale-${site_qa}" "6" "WARN" \
        "QA audit stale for ${site_qa}: ${age_h}h old" \
        "The auto-site-audit latest output is ${age_h}h old (target ≤24h).\n\nRerun:\n\`\`\`bash\nbash scripts/site-audit-run.sh ${site_qa#qa-}\n\`\`\`\nVerify findings-summary.md is updated."
    else
      pass "[$site_qa] Audit fresh: ${age_h}h old (findings lines: ${findings:-?})"
    fi
  else
    warn "[$site_qa] No auto-site-audit/latest found — audit may never have run"
    info "Run: bash scripts/site-audit-run.sh ${site_qa#qa-}"
    queue_dispatch "$site_qa" "audit-never-run-${site_qa}" "7" "WARN" \
      "No QA audit found for ${site_qa} — audit may never have run" \
      "No auto-site-audit/latest directory found for $site_qa.\n\nRun the initial audit:\n\`\`\`bash\nbash scripts/site-audit-run.sh ${site_qa#qa-}\n\`\`\`"
  fi
done

# ─── 11. DEAD-LETTER INBOX DETECTION ────────────────────────────────────────
echo ""
echo "$SEP"
echo "  Dead-Letter Inbox Items  (non-archived items > 48h old)"
echo "$SEP"

dead_letter_count=0
# Walk all agent inboxes
while IFS= read -r inbox_item; do
  [[ "$(basename "$inbox_item")" == _archived* ]] && continue
  [[ -d "$inbox_item/_archived" ]] && continue

  # Skip items already marked done in command.md (Option A stamp).
  if grep -qiE '^\- Status:\s*done' "$inbox_item/command.md" 2>/dev/null; then
    continue
  fi

  # Skip items that have a matching done outbox entry (outbox correlation).
  item_name=$(basename "$inbox_item")
  agent=$(echo "$inbox_item" | sed 's|sessions/||;s|/inbox.*||')
  outbox_dir="sessions/${agent}/outbox"
  if ls "${outbox_dir}/${item_name}"*.md 2>/dev/null | xargs grep -liE '^\- Status:\s*done' 2>/dev/null | grep -q .; then
    continue
  fi

  item_mtime=$(stat -c %Y "$inbox_item" 2>/dev/null || echo 0)
  age_h=$(( (now_ts - item_mtime) / 3600 ))
  if [ "$age_h" -gt 48 ]; then
    warn "Dead letter: $agent → $item_name (${age_h}h old)"
    dead_letter_count=$(( dead_letter_count + 1 ))
    queue_dispatch "ceo-copilot-2" "dead-letter-${agent}-${item_name}" "5" "WARN" \
      "Dead-letter inbox item: $agent → $item_name (${age_h}h)" \
      "Inbox item ${item_name} in sessions/${agent}/inbox/ has been sitting for ${age_h}h without resolution.\n\nCEO action required: investigate, resolve or archive.\n- If resolvable: create outbox item with Status: done\n- If stale/superseded: move to _archived subfolder"
    [ "$dead_letter_count" -ge 5 ] && { info "(truncated — more dead letters exist)"; break; }
  fi
done < <(find sessions/*/inbox -mindepth 1 -maxdepth 1 -not -name "_archived" 2>/dev/null | sort)

if [ "$dead_letter_count" -eq 0 ]; then
  pass "No dead-letter inbox items found (all items < 48h or archived)"
fi

# ─── DISPATCH ───────────────────────────────────────────────────────────────
if [ "$DISPATCH_MODE" -eq 1 ] && [ "${#DISPATCH_ITEMS[@]}" -gt 0 ]; then
  echo ""
  echo "$SEP"
  echo "  Dispatching inbox items for findings..."
  echo "$SEP"
  dispatched=0
  for entry in "${DISPATCH_ITEMS[@]}"; do
    IFS='|' read -r d_agent d_slug d_roi d_sev d_title d_body <<< "$entry"
    # Check if agent session inbox exists
    inbox_dir="sessions/${d_agent}/inbox"
    if [ ! -d "$inbox_dir" ]; then
      echo "   ⚠️  No inbox for $d_agent — skipping: $d_slug"
      continue
    fi
    create_inbox_item "$d_agent" "$d_slug" "$d_roi" "$d_title" "$d_body"
    dispatched=$(( dispatched + 1 ))
  done
  echo "   ℹ️  Dispatched $dispatched item(s)"
elif [ "$DISPATCH_MODE" -eq 1 ]; then
  echo ""
  echo "$SEP"
  echo "  No findings to dispatch — all checks clean"
  echo "$SEP"
fi

# ─── SUMMARY ────────────────────────────────────────────────────────────────
echo ""
echo "$SEP"
if [ "$FAIL_COUNT" -eq 0 ] && [ "$WARN_COUNT" -eq 0 ]; then
  echo "✅  All system health checks PASS"
elif [ "$FAIL_COUNT" -eq 0 ]; then
  echo "⚠️   $WARN_COUNT warning(s) — review items above"
else
  echo "❌  $FAIL_COUNT check(s) FAILED, $WARN_COUNT warning(s) — see items above"
fi
echo "$SEP"

if [ "$JSON_MODE" -eq 1 ]; then
  echo ""
  echo '{"fail":'$FAIL_COUNT',"warn":'$WARN_COUNT'}'
fi

[ "$FAIL_COUNT" -eq 0 ]
