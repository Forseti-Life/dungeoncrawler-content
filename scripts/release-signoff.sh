#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="${HQ_ROOT_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
SCRIPT_LIB_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/lib" && pwd)"
cd "$ROOT_DIR"

PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"

site="${1:-}"
release_id="${2:-}"
empty_release=0

# Parse optional flags (may appear after positional args)
for arg in "$@"; do
  case "$arg" in
    --empty-release) empty_release=1 ;;
  esac
done

if [ -z "$site" ] || [ -z "$release_id" ]; then
  echo "Usage: $0 <site-or-team-alias> <release-id> [--empty-release]" >&2
  echo "Examples:" >&2
  echo "  $0 forseti.life 20260223-coordinated-release" >&2
  echo "  $0 dungeoncrawler 20260223-coordinated-release" >&2
  echo "  $0 dungeoncrawler 20260223-coordinated-release --empty-release" >&2
  echo "  --empty-release: self-certify Gate 2 when no features were shipped (PM authority)" >&2
  exit 2
fi

# Guard: reject non-conforming release IDs (e.g., phantom IDs derived from QA outbox filenames).
# Valid format: YYYYMMDD-<team>-release-<letter[s]>  e.g. 20260408-forseti-release-j
if ! echo "$release_id" | grep -qE '^[0-9]{8}-[a-zA-Z][a-zA-Z0-9-]+-release-[a-z][a-z0-9]*$'; then
  echo "ERROR: release_id '${release_id}' does not match required format YYYYMMDD-<team>-release-<letter>." >&2
  echo "This is likely a phantom dispatch from a QA unit-test or feature-verify outbox." >&2
  echo "Archive the inbox item and discard — do NOT run this with a non-release ID." >&2
  exit 2
fi

if ! lookup_result="$(python3 - "$PRODUCT_TEAMS_JSON" "$site" <<'PY'
import json
import sys

cfg_path = sys.argv[1]
query = (sys.argv[2] or '').strip().lower()

with open(cfg_path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

teams = data.get('teams') or []
for team in teams:
    aliases = [str(a).strip().lower() for a in (team.get('aliases') or []) if str(a).strip()]
    team_id = str(team.get('id') or '').strip().lower()
    team_site = str(team.get('site') or '').strip().lower()
    if query not in aliases and query != team_id and query != team_site:
        continue

    if not team.get('active', False):
        print(f"ERROR: team is not active for query '{query}'", file=sys.stderr)
        raise SystemExit(3)

    pm_agent = str(team.get('pm_agent') or '').strip()
    normalized_site = str(team.get('site') or '').strip()
    team_id_out = str(team.get('id') or '').strip()
    if not pm_agent or not normalized_site:
        print(f"ERROR: team '{team_id_out}' missing pm_agent/site in registry", file=sys.stderr)
        raise SystemExit(4)

    qa_agent = str(team.get('qa_agent') or '').strip()
    print(f"{team_id_out}\t{normalized_site}\t{pm_agent}\t{qa_agent}")
    raise SystemExit(0)

print(f"ERROR: unknown site/team alias: {query}", file=sys.stderr)
print("Update org-chart/products/product-teams.json to onboard this team.", file=sys.stderr)
raise SystemExit(2)
PY
  2>&1)"; then
  echo "$lookup_result" >&2
  exit 2
fi

IFS=$'\t' read -r team_id site pm_agent qa_agent <<<"$lookup_result"

# Fallback: derive qa_agent from team_id if not configured.
if [ -z "$qa_agent" ]; then
  qa_agent="qa-${team_id}"
fi

# Independent release ownership guard: the signing site/team must own the release ID.
if ! owner_lookup="$(python3 - "$PRODUCT_TEAMS_JSON" "$release_id" <<'PY'
import json
import sys

cfg_path = sys.argv[1]
release_id_arg = (sys.argv[2] or '').strip().lower()

with open(cfg_path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

best_team = None
best_len = 0
for team in (data.get('teams') or []):
    if not team.get('active', False):
        continue
    team_id = str(team.get('id') or '').strip().lower()
    aliases = [str(a).strip().lower() for a in (team.get('aliases') or []) if str(a).strip()]
    candidates = [team_id] + aliases
    for cand in candidates:
        if cand and cand in release_id_arg and len(cand) > best_len:
            best_len = len(cand)
            best_team = team

if best_team:
    print(
        f"{str(best_team.get('id') or '').strip()}\t"
        f"{str(best_team.get('pm_agent') or '').strip()}\t"
        f"{str(best_team.get('qa_agent') or '').strip()}"
    )
PY
  2>&1)"; then
  echo "$owner_lookup" >&2
  exit 2
fi

IFS=$'\t' read -r owning_team_id owning_pm_agent owning_qa_agent <<<"$owner_lookup"
if [ -n "$owning_team_id" ] && [ "$owning_team_id" != "$team_id" ]; then
  echo "ERROR: release '${release_id}' belongs to team '${owning_team_id}', not '${team_id}'." >&2
  echo "Cross-team PM co-signs are no longer allowed; each product team ships independently." >&2
  exit 2
fi

if [ -n "$owning_qa_agent" ]; then
  qa_agent="$owning_qa_agent"
fi

ts="$(date -Iseconds)"
dir="sessions/${pm_agent}/artifacts/release-signoffs"
mkdir -p "$dir" 2>/dev/null || true

slug="$(printf '%s' "$release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-80)"
out_file="${dir}/${slug}.md"

# Gate 2 guard: require QA APPROVE evidence before writing PM signoff artifact.
gate2_approved=0

qa_outbox="sessions/${qa_agent}/outbox"
_check_gate2_in() {
  local outbox_dir="$1"
  [ -d "$outbox_dir" ] || return 1
  while IFS= read -r f; do
    grep -q "$release_id" "$f" 2>/dev/null || continue
    grep -q "APPROVE" "$f" 2>/dev/null || continue
    if [[ "$(basename "$f")" == *"empty-release-self-cert"* ]]; then
      return 0
    fi
    if grep -qi "APPROVE filed automatically" "$f" 2>/dev/null; then
      continue
    fi
    return 0
  done < <(find "$outbox_dir" -maxdepth 1 \( -name "*gate2-approve*" -o -name "*gate2-aggregate-approve*" -o -name "*empty-release-self-cert*" \) -type f 2>/dev/null)
  return 1
}

if _check_gate2_in "$qa_outbox"; then
  gate2_approved=1
fi

if [ "$gate2_approved" -ne 1 ]; then
  # Empty-release self-cert bypass (PM authority): when no features were shipped in this
  # release, QA cannot produce APPROVE evidence. PM may self-certify by passing --empty-release.
  # This writes the Gate 2 self-cert to qa outbox on PM's behalf and proceeds with signoff.
  if [ "$empty_release" -eq 1 ]; then
    mkdir -p "$qa_outbox"
    self_cert_file="${qa_outbox}/$(date +%Y%m%d-%H%M%S)-empty-release-self-cert-${slug}.md"
    cat >"$self_cert_file" <<CERT
# Gate 2 Self-Certification — Empty Release

${release_id} — APPROVE — empty release self-certified by PM

## Self-certification basis
- Release closed with zero features shipped (all deferred to next cycle).
- No code changes to verify; Gate 2 QA evidence is not applicable.
- PM is authorized to self-certify empty releases without QA APPROVE or CEO waiver.
- Certified by: ${pm_agent}
- Certified at: ${ts}
CERT
    echo "INFO: empty-release self-cert written to ${self_cert_file}"
    gate2_approved=1
  else
    echo "ERROR: Gate 2 APPROVE evidence not found for release '${release_id}'" >&2
    echo "  Searched: ${qa_outbox}/ for files containing both '${release_id}' and 'APPROVE'" >&2
    echo "  If this release shipped zero features, re-run with --empty-release to self-certify." >&2
    echo "BLOCKED: PM signoff requires Gate 2 QA APPROVE before it can be issued." >&2
    exit 1
  fi
fi

# Gate 1b guard: require completed code review evidence or an explicit same-release
# risk acceptance before PM signoff can be recorded.
if [ "$empty_release" -eq 1 ]; then
  echo "INFO: Gate 1b self-certified for empty release '${release_id}'"
else
  gate1b_tmp="$(mktemp)"
  set +e
  python3 - "$ROOT_DIR" "$SCRIPT_LIB_DIR" "$release_id" "$pm_agent" <<'PY' >"$gate1b_tmp" 2>&1
from pathlib import Path
import sys

root = Path(sys.argv[1])
fallback_lib = Path(sys.argv[2])
release_id = sys.argv[3]
pm_agent = sys.argv[4]

for lib_dir in (root / "scripts" / "lib", fallback_lib):
    if lib_dir.exists() and str(lib_dir) not in sys.path:
        sys.path.insert(0, str(lib_dir))

from gate1b_artifacts import latest_gate1b_artifact, latest_gate1b_risk_acceptance  # type: ignore

gate1b_outbox = root / "sessions" / "agent-code-review" / "outbox"
risk_dir = root / "sessions" / pm_agent / "artifacts" / "risk-acceptances"

artifact = latest_gate1b_artifact(gate1b_outbox, release_id)
risk = latest_gate1b_risk_acceptance(risk_dir, release_id)

if risk is not None:
    print(f"INFO: Gate 1b cleared by risk acceptance: {risk}")
    if artifact is not None:
        print(f"INFO: Latest Gate 1b artifact: {artifact.path} ({artifact.verdict})")
    raise SystemExit(0)

if artifact is None:
    print(f"ERROR: Gate 1b evidence not found for release '{release_id}'", file=sys.stderr)
    print(
        f"  Searched: {gate1b_outbox}/ for release-scoped code-review/manual-cr artifacts and "
        f"{risk_dir}/ for same-release Gate 1b risk acceptance",
        file=sys.stderr,
    )
    raise SystemExit(1)

if artifact.verdict != "APPROVE":
    print(
        f"ERROR: Gate 1b latest artifact is not clear for release '{release_id}' "
        f"({artifact.verdict}: {artifact.path})",
        file=sys.stderr,
    )
    print("  Resolve the review findings or add an explicit same-release Gate 1b risk acceptance.", file=sys.stderr)
    raise SystemExit(1)

print(f"INFO: Gate 1b cleared by {artifact.path} ({artifact.verdict})")
PY
  gate1b_status=$?
  set -e
  gate1b_check="$(cat "$gate1b_tmp")"
  rm -f "$gate1b_tmp"
  if [ "$gate1b_status" -ne 0 ]; then
    echo "$gate1b_check" >&2
    echo "BLOCKED: PM signoff requires completed Gate 1b code review evidence or explicit same-release risk acceptance." >&2
    exit 1
  fi
  if [ -n "$gate1b_check" ]; then
    echo "$gate1b_check"
  fi
fi

# Release metadata guard: if a release candidate change list exists for this release,
# every listed feature must have an exact Release: <release_id> match in feature.md.
metadata_tmp="$(mktemp)"
set +e
python3 - "$ROOT_DIR" "$release_id" <<'PY' >"$metadata_tmp" 2>&1
from pathlib import Path
import re
import sys

root = Path(sys.argv[1])
release_id = sys.argv[2].strip()
change_lists = sorted(root.glob(f"sessions/*/artifacts/release-candidates/{release_id}/01-change-list.md"))
if not change_lists:
    raise SystemExit(0)

feature_heading = re.compile(r'^###\s+([A-Za-z0-9._-]+)\s*$')
release_line = re.compile(r'^-\s+Release:\s*(.*?)\s*$', re.MULTILINE | re.IGNORECASE)
errors = []
seen = set()

for change_list in change_lists:
    for raw_line in change_list.read_text(encoding='utf-8', errors='ignore').splitlines():
        match = feature_heading.match(raw_line.strip())
        if not match:
            continue
        feature_id = match.group(1).strip()
        if feature_id in seen:
            continue
        seen.add(feature_id)
        feature_md = root / 'features' / feature_id / 'feature.md'
        if not feature_md.is_file():
            errors.append(f"{feature_id}: feature brief missing (listed in {change_list.relative_to(root)})")
            continue
        text = feature_md.read_text(encoding='utf-8', errors='ignore')
        release_match = release_line.search(text)
        feature_release = release_match.group(1).strip() if release_match else ''
        if feature_release != release_id:
            shown = feature_release if feature_release else '(blank)'
            errors.append(f"{feature_id}: Release field is '{shown}', expected '{release_id}'")

if errors:
    for err in errors:
        print(f"ERROR: release metadata mismatch — {err}")
    raise SystemExit(1)
PY
metadata_status=$?
set -e
metadata_check="$(cat "$metadata_tmp")"
rm -f "$metadata_tmp"
if [ "$metadata_status" -ne 0 ]; then
  echo "$metadata_check" >&2
  echo "BLOCKED: PM signoff requires release-bound features to carry exact Release: ${release_id} metadata." >&2
  exit 1
fi

if [ -n "$metadata_check" ]; then
  echo "$metadata_check"
fi

# Stale orchestrator artifact check: if an existing signoff was written by the orchestrator
# (not a real PM), do not treat it as valid — fall through and overwrite after guard passes.
is_stale_orchestrator=0
if [ -f "$out_file" ] && grep -q "Signed by: orchestrator" "$out_file" 2>/dev/null; then
  is_stale_orchestrator=1
fi

if [ -f "$out_file" ] && [ "$is_stale_orchestrator" -eq 0 ]; then
  echo "OK: already signed off: ${pm_agent} ${slug} (${out_file})"
  exit 0
fi

cat >"$out_file" <<MD
# PM signoff

- Release id: ${release_id}
- Site: ${site}
- PM seat: ${pm_agent}
- Signed off at: ${ts}

## Signoff statement
I confirm the PM-level gates for this site are satisfied for this release id:

- Scope is defined; risks are documented.
- Dev provided commit hash(es) + rollback steps.
- QA provided verification evidence and APPROVE (or explicit documented risk acceptance).

This team release ships independently; no cross-team PM co-sign or shared release operator is required.
MD

echo "SIGNED_OFF: ${pm_agent} ${release_id} -> ${out_file}"
echo "INFO: independent team push now depends only on ${pm_agent} signoff and ${qa_agent} Gate 2 evidence."

# ── Board email notification ──────────────────────────────────────────────────
# Load board.conf if present (provides BOARD_EMAIL, HQ_FROM_EMAIL, HQ_SITE_NAME)
BOARD_CONF="${ROOT_DIR}/org-chart/board.conf"
if [ -f "$BOARD_CONF" ]; then
  # shellcheck source=../org-chart/board.conf
  source "$BOARD_CONF"
fi
BOARD_EMAIL="${BOARD_EMAIL:-keith.aumiller@stlouisintegration.com}"
HQ_FROM_EMAIL="${HQ_FROM_EMAIL:-hq-noreply@forseti.life}"
HQ_SITE_NAME="${HQ_SITE_NAME:-forseti.life HQ}"

# ── Build HTML features list from PM release-notes artifact ──────────────────
_features_html=""
_features_section=""

# Search all PM release-notes artifacts for this release_id
for _rn_file in "${ROOT_DIR}"/sessions/pm-*/artifacts/release-notes/"${release_id}".md \
                "${ROOT_DIR}"/sessions/pm-*/artifacts/releases/"${release_id}"/01-change-list.md; do
  if [ -f "$_rn_file" ]; then
    # Extract the "Features in scope" / "Features shipped" section
    _features_section="$(awk '/^##[[:space:]]+Features/{found=1; next} found && /^##/{exit} found{print}' "$_rn_file" | head -40)"
    break
  fi
done

if [ -n "$_features_section" ]; then
  _features_html="<h3 style=\"color:#1f2328;margin:16px 0 8px;\">Features in this release</h3><ul style=\"margin:0;padding-left:20px;color:#1f2328;\">"
  while IFS= read -r _line; do
    # Match: "1. \`feature-id\` — Description" or "- **feature** (ROI N) — Desc"
    if echo "$_line" | grep -qE '^[[:space:]]*([0-9]+\.|[-*])[[:space:]]+'; then
      _clean="$(echo "$_line" | sed 's/^[[:space:]]*[0-9]*\.[[:space:]]*//' | sed 's/^[[:space:]]*[-*][[:space:]]*//' | sed 's/`//g' | sed 's/\*\*//g')"
      if [ -n "$_clean" ]; then
        _features_html="${_features_html}<li style=\"margin:3px 0;\">${_clean}</li>"
      fi
    fi
  done <<< "$_features_section"
  _features_html="${_features_html}</ul>"
fi

# ── Build HTML email ──────────────────────────────────────────────────────────
_releases_url="https://forseti.life/admin/reports/copilot-agent-tracker/releases"

_email_html="<!DOCTYPE html>
<html>
<head><meta charset=\"UTF-8\"></head>
<body style=\"font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;background:#f6f8fa;margin:0;padding:24px;\">
<div style=\"max-width:600px;margin:0 auto;background:#fff;border:1px solid #d0d7de;border-radius:6px;overflow:hidden;\">

  <!-- Header -->
  <div style=\"background:#1a7f37;padding:20px 24px;\">
    <h1 style=\"color:#fff;margin:0;font-size:18px;\">🚀 Team Release Signed Off</h1>
    <p style=\"color:#d1fae5;margin:4px 0 0;font-size:13px;\">${HQ_SITE_NAME}</p>
  </div>

  <!-- Body -->
  <div style=\"padding:24px;\">
    <table style=\"width:100%;border-collapse:collapse;margin-bottom:20px;\">
      <tr>
        <td style=\"padding:6px 0;color:#57606a;font-size:13px;width:140px;\">Release ID</td>
        <td style=\"padding:6px 0;font-weight:600;font-size:14px;color:#1f2328;font-family:monospace;\">${release_id}</td>
      </tr>
      <tr>
        <td style=\"padding:6px 0;color:#57606a;font-size:13px;\">Status</td>
        <td style=\"padding:6px 0;\"><span style=\"background:#1a7f37;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;\">PM SIGNED OFF</span></td>
      </tr>
      <tr>
        <td style=\"padding:6px 0;color:#57606a;font-size:13px;\">Site</td>
        <td style=\"padding:6px 0;color:#1f2328;\">${site}</td>
      </tr>
    </table>

    ${_features_html}

    <div style=\"margin-top:24px;padding:16px;background:#f6f8fa;border-radius:6px;border-left:3px solid #1a7f37;\">
      <p style=\"margin:0 0 8px;font-weight:600;color:#1f2328;\">Board note</p>
      <p style=\"margin:0;color:#57606a;font-size:13px;\">This release now moves independently through the owning PM lane; no cross-team operator handoff is required.</p>
    </div>

    <div style=\"margin-top:16px;padding:16px;background:#f6f8fa;border-radius:6px;border-left:3px solid #0969da;\">
      <p style=\"margin:0 0 8px;font-weight:600;color:#1f2328;\">Owning PM action (<code>${pm_agent}</code>)</p>
      <p style=\"margin:0;color:#57606a;font-size:13px;\">The orchestrator may trigger the deploy on the next tick; after deployment, advance only this team's release cycle:</p>
      <ol style=\"margin:8px 0 0;padding-left:20px;color:#57606a;font-size:13px;\">
        <li>Check status: <code>bash scripts/release-signoff-status.sh ${release_id}</code></li>
        <li>Watch for deploy trigger / completion in GitHub Actions</li>
        <li>Advance only this team's cycle: <code>bash scripts/post-coordinated-push.sh ${team_id} ${release_id}</code></li>
        <li>Run post-push steps (config import, smoke test, SLA report) for <code>${site}</code></li>
      </ol>
    </div>

    <p style=\"margin:20px 0 0;text-align:center;\">
      <a href=\"${_releases_url}\" style=\"display:inline-block;padding:8px 20px;background:#0969da;color:#fff;border-radius:6px;text-decoration:none;font-size:14px;font-weight:600;\">View all releases →</a>
    </p>
  </div>

  <div style=\"padding:12px 24px;background:#f6f8fa;border-top:1px solid #d0d7de;font-size:11px;color:#57606a;text-align:center;\">
    Sent automatically by release-signoff.sh · ${HQ_SITE_NAME}
  </div>
</div>
</body></html>"

printf "To: %s\nFrom: %s\nSubject: [%s] FYI: team release signed off: %s\nContent-Type: text/html; charset=UTF-8\nMIME-Version: 1.0\n\n%s\n" \
  "$BOARD_EMAIL" "$HQ_FROM_EMAIL" "$HQ_SITE_NAME" "$release_id" "$_email_html" \
  | /usr/sbin/sendmail -t \
  && echo "INFO: Board notification sent to ${BOARD_EMAIL}" \
  || echo "WARN: Board notification email failed (sendmail returned non-zero)"
