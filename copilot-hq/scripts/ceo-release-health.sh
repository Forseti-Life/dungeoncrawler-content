#!/usr/bin/env bash
# ceo-release-health.sh — CEO release cycle diagnostic
#
# Runs an end-to-end health check of the release cycle for all active coordinated teams.
# Covers every issue that has historically caused blocked releases:
#   • Stale release_id / next_release_id runtime files
#   • Feature coverage and status mismatches
#   • Gate 2 APPROVE presence
#   • PM signoffs (own + cross-team)
#   • Coordinated push readiness
#   • GitHub Actions deploy.yml enabled + last run
#   • Orphaned features — in_progress on stale/closed releases with no dev work
#   • Backlog health — ready features awaiting grooming into upcoming releases
#
# Usage:
#   bash scripts/ceo-release-health.sh
#   bash scripts/ceo-release-health.sh --fix     # auto-fix stale next_release_id AND reset orphaned features (CEO authority)
#
# Output uses ✅ PASS / ❌ FAIL / ⚠️  WARN prefixes.
# Exit 0 = all checks pass. Exit 1 = at least one FAIL.
#
set -euo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

FIX_MODE=0
RELEASE_START_GRACE_SECONDS="${RELEASE_START_GRACE_SECONDS:-14400}"
for arg in "$@"; do
  [ "$arg" = "--fix" ] && FIX_MODE=1
done

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"
ACTIVE_DIR="tmp/release-cycle-active"
PASS="✅ PASS"
FAIL="❌ FAIL"
WARN="⚠️  WARN"
INFO="   ℹ️ "

FAILURES=0

fail() { echo "$FAIL $*"; FAILURES=$((FAILURES + 1)); }
pass() { echo "$PASS $*"; }
warn() { echo "$WARN $*"; }
info() { echo "$INFO $*"; }

hr() { echo "────────────────────────────────────────────────────────"; }

find_gate2_evidence() {
  local qa_outbox="$1"
  local release_id="$2"
  [ -d "$qa_outbox" ] || return 0
  find "$qa_outbox" -maxdepth 1 \( -name "*gate2-approve*" -o -name "*empty-release-self-cert*" \) -type f 2>/dev/null \
    | xargs grep -l "$release_id" 2>/dev/null | head -1 || true
}

echo
echo "═══════════════════════════════════════════════════════"
echo "  CEO Release Cycle Health Check"
echo "  $(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "═══════════════════════════════════════════════════════"

# ── Load coordinated teams ───────────────────────────────────────────────────
if [ ! -f "$PRODUCT_TEAMS_JSON" ]; then
  fail "product-teams.json missing: $PRODUCT_TEAMS_JSON"
  exit 1
fi

COORDINATED_TEAMS="$(python3 - "$PRODUCT_TEAMS_JSON" <<'PY'
import json, sys
data = json.load(open(sys.argv[1], encoding='utf-8'))
for t in data.get('teams', []):
    if t.get('active') and t.get('coordinated_release_default'):
        print('\t'.join([
            t.get('id',''),
            t.get('pm_agent', 'pm-' + t.get('id','')),
            t.get('qa_agent', 'qa-' + t.get('id','')),
        ]))
PY
)"

if [ -z "$COORDINATED_TEAMS" ]; then
  fail "No active coordinated-release teams found in product-teams.json"
  exit 1
fi

# ── GitHub Actions deploy.yml check ─────────────────────────────────────────
echo
hr
echo "  GitHub Actions: deploy.yml"
hr

REPO="keithaumiller/forseti.life"
GH_BIN="$(command -v gh 2>/dev/null || true)"
DEPLOY_CHECKED=0

if [ -n "$GH_BIN" ] && [ -f /home/ubuntu/github.token ]; then
  TOKEN="$(cat /home/ubuntu/github.token)"
  WF_STATE="$(GH_TOKEN="$TOKEN" "$GH_BIN" api "repos/${REPO}/actions/workflows/deploy.yml" --jq '.state' 2>/dev/null || echo 'unknown')"
  if [ "$WF_STATE" = "active" ]; then
    pass "deploy.yml is enabled (state=active)"
  elif [ "$WF_STATE" = "disabled_manually" ] || [ "$WF_STATE" = "disabled_fork" ]; then
    fail "deploy.yml is DISABLED (state=$WF_STATE) — production will not update on push"
    if [ "$FIX_MODE" = "1" ]; then
      info "FIX: re-enabling deploy.yml..."
      GH_TOKEN="$TOKEN" "$GH_BIN" workflow enable deploy.yml --repo "$REPO" 2>/dev/null && info "deploy.yml re-enabled" || warn "could not re-enable deploy.yml"
    else
      info "Run with --fix to re-enable, or: GH_TOKEN=\$(cat /home/ubuntu/github.token) gh workflow enable deploy.yml --repo $REPO"
    fi
  else
    warn "deploy.yml state=$WF_STATE (could not determine)"
  fi

  # Last run
  LAST_RUN="$(GH_TOKEN="$TOKEN" "$GH_BIN" run list --workflow=deploy.yml --repo "$REPO" --limit 1 --json conclusion,createdAt,displayTitle -q '.[0]' 2>/dev/null || echo '')"
  if [ -n "$LAST_RUN" ]; then
    LAST_DATE="$(echo "$LAST_RUN" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('createdAt','?'))")"
    LAST_STATUS="$(echo "$LAST_RUN" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('conclusion','in_progress'))")"
    LAST_TITLE="$(echo "$LAST_RUN" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('displayTitle','?')[:60])")"
    AGE_SECS="$(python3 -c "from datetime import datetime,timezone; a='$LAST_DATE'; dt=datetime.fromisoformat(a.replace('Z','+00:00')); print(int((datetime.now(timezone.utc)-dt).total_seconds()))" 2>/dev/null || echo '?')"
    if [ "$AGE_SECS" != "?" ] && [ "$AGE_SECS" -gt 86400 ]; then
      fail "Last deploy run was $((AGE_SECS/3600))h ago ($LAST_DATE) — status=$LAST_STATUS"
      info "Title: $LAST_TITLE"
    elif [ "$LAST_STATUS" = "failure" ]; then
      fail "Last deploy run FAILED ($LAST_DATE) — investigate GitHub Actions"
    else
      pass "Last deploy: $LAST_DATE status=$LAST_STATUS"
    fi
  fi
  DEPLOY_CHECKED=1
fi

if [ "$DEPLOY_CHECKED" = "0" ]; then
  warn "gh CLI or /home/ubuntu/github.token not available — skipping deploy.yml check"
fi

# ── Per-team release cycle checks ───────────────────────────────────────────
ALL_RELEASE_IDS=""

while IFS=$'\t' read -r TEAM PM_AGENT QA_AGENT; do
  [ -n "$TEAM" ] || continue

  echo
  hr
  echo "  Team: $TEAM  |  pm=$PM_AGENT  qa=$QA_AGENT"
  hr

  # 1. release_id
  RELEASE_ID_FILE="$ACTIVE_DIR/${TEAM}.release_id"
  NEXT_ID_FILE="$ACTIVE_DIR/${TEAM}.next_release_id"

  if [ ! -f "$RELEASE_ID_FILE" ]; then
    fail "[$TEAM] $RELEASE_ID_FILE not found — release cycle not started"
    continue
  fi

  RELEASE_ID="$(cat "$RELEASE_ID_FILE" | tr -d '[:space:]')"
  NEXT_RELEASE_ID="$(cat "$NEXT_ID_FILE" 2>/dev/null | tr -d '[:space:]' || echo '')"
  STARTED_AT_FILE="$ACTIVE_DIR/${TEAM}.started_at"
  RELEASE_AGE_SECS=""
  if [ -f "$STARTED_AT_FILE" ]; then
    STARTED_AT="$(cat "$STARTED_AT_FILE" | tr -d '[:space:]')"
    RELEASE_AGE_SECS="$(python3 - "$STARTED_AT" <<'PY'
from datetime import datetime, timezone
import sys

value = sys.argv[1].strip()
if not value:
    print("")
    raise SystemExit(0)
try:
    dt = datetime.fromisoformat(value.replace("Z", "+00:00"))
except Exception:
    print("")
    raise SystemExit(0)
print(int((datetime.now(timezone.utc) - dt).total_seconds()))
PY
)"
  fi
  IN_RELEASE_GRACE=0
  if [ -n "$RELEASE_AGE_SECS" ] && [ "$RELEASE_AGE_SECS" -lt "$RELEASE_START_GRACE_SECONDS" ]; then
    IN_RELEASE_GRACE=1
  fi

  echo "  Release ID:  $RELEASE_ID"
  echo "  Next ID:     ${NEXT_RELEASE_ID:-<not set>}"

  # 2. Stale next_release_id check (next should not equal or precede current)
  if [ -n "$NEXT_RELEASE_ID" ]; then
    if [ "$NEXT_RELEASE_ID" = "$RELEASE_ID" ]; then
      fail "[$TEAM] next_release_id == release_id ($RELEASE_ID) — definitely stale"
    else
      # Lexicographic compare: next should be "greater" (later release label)
      LATER="$(printf '%s\n%s\n' "$RELEASE_ID" "$NEXT_RELEASE_ID" | sort | tail -1)"
      if [ "$LATER" != "$NEXT_RELEASE_ID" ]; then
        fail "[$TEAM] next_release_id ($NEXT_RELEASE_ID) sorts before release_id ($RELEASE_ID) — stale"
        if [ "$FIX_MODE" = "1" ]; then
          # Compute next: find the label suffix (release-X), increment past current
          NEW_NEXT="$(python3 - "$RELEASE_ID" "$NEXT_RELEASE_ID" <<'PY'
import sys, re
cur, stale = sys.argv[1], sys.argv[2]
# Extract prefix (date+team part) and suffix (release-X)
m = re.match(r'^(.*-release-)([a-z]+)$', cur)
if not m:
    print('')
    sys.exit(0)
prefix, label = m.group(1), m.group(2)
# cycle: a b c d e f ...
alpha = 'abcdefghijklmnopqrstuvwxyz'
idx = alpha.index(label) if label in alpha else -1
new_label = alpha[(idx + 1) % len(alpha)] if idx >= 0 else 'd'
print(prefix + new_label)
PY
)"
          if [ -n "$NEW_NEXT" ]; then
            echo "$NEW_NEXT" > "$NEXT_ID_FILE"
            info "FIX: next_release_id set to $NEW_NEXT"
          fi
        else
          info "Run with --fix to auto-correct, or: echo '<correct-id>' > $NEXT_ID_FILE"
        fi
      else
        pass "[$TEAM] next_release_id ($NEXT_RELEASE_ID) is ahead of current — OK"
      fi
    fi
  else
    warn "[$TEAM] next_release_id not set"
  fi

  ALL_RELEASE_IDS="$ALL_RELEASE_IDS $RELEASE_ID"

  # 3. Features in current release
  echo
  echo "  Features in $RELEASE_ID:"
  FEATURES_IN_RELEASE=()
  FEATURES_NOT_DONE=()
  FEATURES_WAITING_FOR_IMPL=0
  while IFS= read -r FEAT_DIR; do
    [ -d "$FEAT_DIR" ] || continue
    FM="$FEAT_DIR/feature.md"
    [ -f "$FM" ] || continue
    TEXT="$(cat "$FM")"
    # Match release_id in feature.md
    if echo "$TEXT" | grep -qE "^-\s+Release:\s*${RELEASE_ID}\s*$"; then
      FEAT_NAME="$(basename "$FEAT_DIR")"
      STATUS="$(echo "$TEXT" | grep -E '^-\s+Status:' | head -1 | sed 's/^-\s*Status:\s*//')"
      FEATURES_IN_RELEASE+=("$FEAT_NAME")
      if [ "$STATUS" = "done" ]; then
        pass "  feature: $FEAT_NAME (status=$STATUS)"
      elif [ "$STATUS" = "in_progress" ]; then
        # Check for dev outbox (implementation)
        DEV_AGENT="dev-$TEAM"
        HAS_IMPL="$(ls "sessions/${DEV_AGENT}/outbox/" 2>/dev/null | grep "$FEAT_NAME" | head -1 || true)"
        if [ -n "$HAS_IMPL" ]; then
          pass "  feature: $FEAT_NAME (status=$STATUS, dev outbox: $HAS_IMPL)"
        else
          if [ "$IN_RELEASE_GRACE" = "1" ]; then
            warn "  feature: $FEAT_NAME (status=$STATUS, no dev outbox yet — release still within startup grace)"
          else
            fail "  feature: $FEAT_NAME (status=$STATUS, NO dev outbox found — implementation missing)"
          fi
          FEATURES_WAITING_FOR_IMPL=$((FEATURES_WAITING_FOR_IMPL + 1))
          FEATURES_NOT_DONE+=("$FEAT_NAME")
        fi
      else
        warn "  feature: $FEAT_NAME (status=${STATUS:-unknown})"
        FEATURES_NOT_DONE+=("$FEAT_NAME")
      fi
    fi
  done < <(find features -maxdepth 1 -mindepth 1 -type d 2>/dev/null)

  FEAT_COUNT="${#FEATURES_IN_RELEASE[@]}"
  if [ "$FEAT_COUNT" -eq 0 ]; then
    warn "[$TEAM] No features scoped to $RELEASE_ID"
  else
    info "[$TEAM] $FEAT_COUNT feature(s) in scope"
  fi

  # 4. Gate 2 APPROVE
  echo
  QA_OUTBOX="sessions/${QA_AGENT}/outbox"
  GATE2_FILE="$(find_gate2_evidence "$QA_OUTBOX" "$RELEASE_ID")"

  if [ -n "$GATE2_FILE" ]; then
    pass "[$TEAM] Gate 2 evidence: $(basename "$GATE2_FILE")"
  else
    if [ "$FEAT_COUNT" -eq 0 ]; then
      warn "[$TEAM] Gate 2 APPROVE not found (empty release — may need --empty-release flag)"
    elif [ "$FEATURES_WAITING_FOR_IMPL" -gt 0 ]; then
      warn "[$TEAM] Gate 2 APPROVE pending implementation completion (${FEATURES_WAITING_FOR_IMPL} feature(s) still missing dev outbox)"
    else
      fail "[$TEAM] Gate 2 APPROVE not found in $QA_OUTBOX for $RELEASE_ID"
      info "Expected: $QA_OUTBOX/<timestamp>-gate2-approve-<slug>.md containing '$RELEASE_ID' and 'APPROVE'"
    fi
  fi

  # 5. PM signoff
  PM_SIGNOFF="sessions/${PM_AGENT}/artifacts/release-signoffs/${RELEASE_ID}.md"
  if [ -f "$PM_SIGNOFF" ]; then
    pass "[$TEAM] PM signoff ($PM_AGENT): found"
  elif [ "$FEAT_COUNT" -eq 0 ]; then
    warn "[$TEAM] PM signoff pending scope activation for $RELEASE_ID"
  elif [ "$FEATURES_WAITING_FOR_IMPL" -gt 0 ]; then
    warn "[$TEAM] PM signoff pending implementation and QA completion for $RELEASE_ID"
  elif [ -z "$GATE2_FILE" ]; then
    warn "[$TEAM] PM signoff pending Gate 2 APPROVE for $RELEASE_ID"
  else
    fail "[$TEAM] PM signoff missing: $PM_SIGNOFF"
    info "Run: bash scripts/release-signoff.sh $TEAM $RELEASE_ID"
  fi

  # 6. Orphaned features — in_progress on a stale/closed release (Python for speed)
  echo
  ORPHAN_RESULTS="$(python3 - features "$TEAM" "$RELEASE_ID" <<'PY'
import pathlib, sys, re
feat_root = pathlib.Path(sys.argv[1])
team, current_release = sys.argv[2], sys.argv[3]
for feat_dir in sorted(feat_root.iterdir()):
    fm = feat_dir / "feature.md"
    if not fm.exists(): continue
    text = fm.read_text()
    status = next((re.sub(r"^- Status:\s*", "", l).strip()
                   for l in text.splitlines() if re.match(r"^- Status:", l)), "")
    if status != "in_progress": continue
    release = next((re.sub(r"^- Release:\s*", "", l).strip()
                    for l in text.splitlines() if re.match(r"^- Release:", l)), "")
    if not release or release in ("none", "(set by PM at activation)"): continue
    if release == current_release: continue
    if team not in release: continue   # only flag features belonging to this team
    print(f"{feat_dir.name}\t{release}")
PY
)"

  ORPHAN_COUNT=0
  if [ -n "$ORPHAN_RESULTS" ]; then
    while IFS=$'\t' read -r FEAT_NAME F_RELEASE; do
      [ -n "$FEAT_NAME" ] || continue
      DEV_AGENT="dev-$TEAM"
      HAS_IMPL="$(ls "sessions/${DEV_AGENT}/outbox/" 2>/dev/null | grep "$FEAT_NAME" | head -1 || true)"
      if [ -n "$HAS_IMPL" ]; then
        warn "[$TEAM] ORPHAN: $FEAT_NAME (in_progress on OLD $F_RELEASE — dev outbox exists, reconcile status instead of deleting)"
      else
        fail "[$TEAM] ORPHAN: $FEAT_NAME (in_progress on CLOSED $F_RELEASE — no dev work done)"
        info "  Fix: reset to ready + clear release; do not delete the feature record. Run with --fix to auto-reset."
        if [ "$FIX_MODE" = "1" ]; then
          FM="features/$FEAT_NAME/feature.md"
          sed -i 's/^- Status: in_progress/- Status: ready/' "$FM"
          sed -i "s|^- Release: ${F_RELEASE}|- Release: |" "$FM"
          info "  FIX: reset $FEAT_NAME → ready, release cleared (feature preserved)"
        fi
      fi
      ORPHAN_COUNT=$((ORPHAN_COUNT + 1))
    done <<<"$ORPHAN_RESULTS"
  fi

  if [ "$ORPHAN_COUNT" -eq 0 ]; then
    pass "[$TEAM] No orphaned in_progress features on stale/closed releases"
  fi

done <<<"$COORDINATED_TEAMS"

# ── Cross-team signoffs ───────────────────────────────────────────────────────
echo
hr
echo "  Cross-team signoff matrix"
hr

# For each coordinated team, each OTHER team's PM must have signed off on it
TEAMS_LIST=()
RELEASE_MAP=()
while IFS=$'\t' read -r TEAM PM_AGENT QA_AGENT; do
  [ -n "$TEAM" ] || continue
  TEAMS_LIST+=("$TEAM")
  RID="$(cat "$ACTIVE_DIR/${TEAM}.release_id" 2>/dev/null | tr -d '[:space:]' || echo '')"
  RELEASE_MAP+=("$TEAM:$PM_AGENT:$QA_AGENT:$RID")
done <<<"$COORDINATED_TEAMS"

for SIGNING_ENTRY in "${RELEASE_MAP[@]:-}"; do
  [ -n "$SIGNING_ENTRY" ] || continue
  SIGNING_TEAM="${SIGNING_ENTRY%%:*}"
  REST="${SIGNING_ENTRY#*:}"
  SIGNING_PM="${REST%%:*}"
  REST="${REST#*:}"
  SIGNING_QA="${REST%%:*}"
  SIGNING_RID="${REST##*:}"
  [ -n "$SIGNING_RID" ] || continue

  for TARGET_ENTRY in "${RELEASE_MAP[@]:-}"; do
    [ -n "$TARGET_ENTRY" ] || continue
    TARGET_TEAM="${TARGET_ENTRY%%:*}"
    TARGET_REST="${TARGET_ENTRY#*:}"
    TARGET_PM="${TARGET_REST%%:*}"
    TARGET_REST="${TARGET_REST#*:}"
    TARGET_QA="${TARGET_REST%%:*}"
    TARGET_RID="${TARGET_REST##*:}"
    [ "$SIGNING_TEAM" = "$TARGET_TEAM" ] && continue  # skip self
    [ -n "$TARGET_RID" ] || continue

    CROSS_FILE="sessions/${SIGNING_PM}/artifacts/release-signoffs/${TARGET_RID}.md"
    TARGET_OWNER_SIGNOFF="sessions/${TARGET_PM}/artifacts/release-signoffs/${TARGET_RID}.md"
    TARGET_GATE2="$(find_gate2_evidence "sessions/${TARGET_QA}/outbox" "$TARGET_RID")"
    if [ ! -f "$TARGET_OWNER_SIGNOFF" ]; then
      warn "$SIGNING_PM co-sign for $TARGET_RID not yet applicable (owner PM signoff missing)"
    elif [ -z "$TARGET_GATE2" ]; then
      warn "$SIGNING_PM co-sign for $TARGET_RID not yet applicable (Gate 2 evidence missing)"
    elif [ -f "$CROSS_FILE" ]; then
      pass "$SIGNING_PM co-signed $TARGET_RID"
    else
      fail "$SIGNING_PM has NOT co-signed $TARGET_RID"
      info "Run: bash scripts/release-signoff.sh $TARGET_TEAM $TARGET_RID  (as $SIGNING_PM)"
    fi
  done
done

# ── Push readiness ────────────────────────────────────────────────────────────
echo
hr
echo "  Coordinated push readiness"
hr

SORTED_IDS="$(echo "$ALL_RELEASE_IDS" | tr ' ' '\n' | sort | grep -v '^$' | tr '\n' '_' | sed 's/_$//')"
PUSH_MARKER_KEY="$(echo "$ALL_RELEASE_IDS" | tr ' ' '\n' | sort | grep -v '^$' | paste -sd '__')"
PUSH_MARKER="tmp/release-cycle-active/${PUSH_MARKER_KEY}.pushed"

if [ -f "$PUSH_MARKER" ]; then
  warn "Push marker exists: $PUSH_MARKER_KEY.pushed — coordinated push was already dispatched"
  info "If deploy didn't run, check deploy.yml above. Delete marker to re-trigger: rm \"$PUSH_MARKER\""
else
  # Check if all signoffs are present
  ALL_SIGNED=1
  for ENTRY in "${RELEASE_MAP[@]:-}"; do
    [ -n "$ENTRY" ] || continue
    TEAM="${ENTRY%%:*}"
    REST="${ENTRY#*:}"
    PM="${REST%%:*}"
    RID="${REST##*:}"
    [ -n "$RID" ] || { ALL_SIGNED=0; continue; }
    [ -f "sessions/${PM}/artifacts/release-signoffs/${RID}.md" ] || { ALL_SIGNED=0; break; }
  done
  if [ "$ALL_SIGNED" = "1" ] && [ "$FAILURES" -eq 0 ]; then
    pass "All signoffs present — coordinated push will fire on next orchestrator tick"
  elif [ "$ALL_SIGNED" = "1" ]; then
    warn "All PM signoffs present but there are FAIL items above — resolve before push"
  else
    info "Push not yet ready — waiting on signoffs listed above"
  fi
fi

# ── Feature backlog health ────────────────────────────────────────────────────
echo
hr
echo "  Feature Backlog Health"
hr

while IFS=$'\t' read -r TEAM PM_AGENT QA_AGENT; do
  [ -n "$TEAM" ] || continue

  COUNTS="$(python3 - features "$PM_AGENT" <<'PY'
import pathlib, sys, re
feat_root = pathlib.Path(sys.argv[1])
pm_agent = sys.argv[2]
ready, unassigned = 0, 0
for feat_dir in sorted(feat_root.iterdir()):
    fm = feat_dir / "feature.md"
    if not fm.exists():
        continue
    text = fm.read_text()
    # Only count features owned by this team's PM
    if not any(pm_agent in l for l in text.splitlines() if re.match(r"^- PM owner:", l)):
        continue
    status_val = next(
        (re.sub(r"^- Status:\s*", "", l).strip()
         for l in text.splitlines() if re.match(r"^- Status:", l)), "")
    if status_val != "ready":
        continue
    ready += 1
    release_val = next(
        (re.sub(r"^- Release:\s*", "", l).strip()
         for l in text.splitlines() if re.match(r"^- Release:", l)), "")
    if not release_val or release_val in ("none", "(set by PM at activation)"):
        unassigned += 1
print(f"{ready}\t{unassigned}")
PY
)"

  READY_COUNT="$(echo "$COUNTS" | cut -f1)"
  UNASSIGNED_COUNT="$(echo "$COUNTS" | cut -f2)"

  if [ "${READY_COUNT:-0}" -ge 30 ]; then
    warn "[$TEAM] ${READY_COUNT} ready features in backlog (${UNASSIGNED_COUNT} unassigned to any release) — grooming needed"
    info "Dispatch grooming task to $PM_AGENT: scope next batch into upcoming release"
  elif [ "${READY_COUNT:-0}" -gt 0 ]; then
    pass "[$TEAM] ${READY_COUNT} ready feature(s) in backlog (${UNASSIGNED_COUNT} unassigned) — healthy"
  else
    pass "[$TEAM] Backlog empty — no unshipped ready features"
  fi
done <<<"$COORDINATED_TEAMS"

# ── Summary ───────────────────────────────────────────────────────────────────
echo
hr
if [ "$FAILURES" -eq 0 ]; then
  echo "✅  All checks PASSED — release cycle is healthy"
else
  echo "❌  $FAILURES check(s) FAILED — see items above"
fi
hr
echo

exit "$( [ "$FAILURES" -eq 0 ] && echo 0 || echo 1 )"


# ── MULTIREPOSITORY VALIDATION (Added 2026-04-21) ──────────────────────────────
validate_multirepository_access() {
  echo
  hr
  echo "  Multi-Repository Access (Forseti-Life Organization)"
  hr

  local token_file="/home/ubuntu/github.token"
  [ -f "$token_file" ] || { warn "GitHub token file not found: $token_file"; return; }

  local token
  token=$(cat "$token_file")
  [ -n "$token" ] || { warn "GitHub token is empty"; return; }

  # Test personal account access
  if GH_TOKEN="$token" gh api /user >/dev/null 2>&1; then
    pass "Personal GitHub account access OK"
  else
    fail "Cannot access personal GitHub account (check token scopes)"
  fi

  # Test organization access
  if GH_TOKEN="$token" gh api orgs/Forseti-Life >/dev/null 2>&1; then
    pass "Forseti-Life organization access OK"
  else
    fail "Cannot access Forseti-Life organization (check token org:read scope)"
  fi

  # List public repos in organization
  local repo_count
  repo_count=$(GH_TOKEN="$token" gh repo list Forseti-Life --limit 100 --json nameWithOwner -q '.[].nameWithOwner' 2>/dev/null | wc -l)
  if [ "$repo_count" -gt 0 ]; then
    pass "Found $repo_count public repositories in Forseti-Life organization"
    GH_TOKEN="$token" gh repo list Forseti-Life --limit 100 --json nameWithOwner -q '.[].nameWithOwner' | sed 's/^/     → /'
  else
    warn "Could not list repositories in Forseti-Life organization"
  fi

  # Test git remotes (cloned but not functional without proper credentials)
  echo
  info "Git remotes status:"
  git remote -v | sed 's/^/  /'
}

# Call multirepository validation if token exists
if [ -f /home/ubuntu/github.token ]; then
  validate_multirepository_access
fi
