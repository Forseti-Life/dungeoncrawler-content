#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
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

ts="$(date -Iseconds)"
dir="sessions/${pm_agent}/artifacts/release-signoffs"
mkdir -p "$dir" 2>/dev/null || true

slug="$(printf '%s' "$release_id" | tr -cs 'A-Za-z0-9._-' '-' | sed 's/^-//;s/-$//' | cut -c1-80)"
out_file="${dir}/${slug}.md"

# Gate 2 guard: require QA APPROVE evidence before writing PM signoff artifact.
# For cross-team co-sign scenarios: the release_id may belong to a different team
# (e.g., dungeoncrawler PM signing a forseti release). In that case the APPROVE evidence
# lives in the OWNING team's qa outbox, not the signing team's. We check both.
gate2_approved=0

# Determine owning team's QA agent by matching release_id against all team IDs/aliases.
owning_qa_agent="$(python3 - "$PRODUCT_TEAMS_JSON" "$release_id" <<'PY'
import json
import sys

cfg_path = sys.argv[1]
release_id_arg = sys.argv[2].lower()

with open(cfg_path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

best_team = None
best_len = 0
for team in (data.get('teams') or []):
    if not team.get('active', False):
        continue
    team_id = str(team.get('id') or '').lower()
    aliases = [str(a).lower() for a in (team.get('aliases') or [])]
    candidates = [team_id] + aliases
    for cand in candidates:
        if cand and cand in release_id_arg and len(cand) > best_len:
            best_len = len(cand)
            best_team = team

if best_team:
    print(str(best_team.get('qa_agent') or '').strip())
else:
    print('')
PY
)"

qa_outbox="sessions/${qa_agent}/outbox"
_check_gate2_in() {
  local outbox_dir="$1"
  [ -d "$outbox_dir" ] || return 1
  grep -rl "$release_id" "$outbox_dir/" 2>/dev/null \
    | xargs grep -l "APPROVE" 2>/dev/null \
    | grep -q .
}

if _check_gate2_in "$qa_outbox"; then
  gate2_approved=1
elif [ -n "$owning_qa_agent" ] && [ "$owning_qa_agent" != "$qa_agent" ]; then
  owning_qa_outbox="sessions/${owning_qa_agent}/outbox"
  if _check_gate2_in "$owning_qa_outbox"; then
    gate2_approved=1
    echo "INFO: Gate 2 APPROVE found in owning team QA outbox (${owning_qa_agent}) for cross-team co-sign"
  fi
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
    if [ -n "$owning_qa_agent" ] && [ "$owning_qa_agent" != "$qa_agent" ]; then
      echo "  Also searched: sessions/${owning_qa_agent}/outbox/ (owning team QA for cross-team co-sign)" >&2
    fi
    echo "  If this release shipped zero features, re-run with --empty-release to self-certify." >&2
    echo "BLOCKED: PM signoff requires Gate 2 QA APPROVE before it can be issued." >&2
    exit 1
  fi
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

If this is part of a coordinated release, the release operator must wait for all required PM signoffs configured in org-chart/products/product-teams.json before the official push.
MD

echo "SIGNED_OFF: ${pm_agent} ${release_id} -> ${out_file}"

# After recording signoff, check if ALL coordinated PMs have now signed.
# If yes, queue a push-ready inbox item for the release operator (pm-forseti).
python3 - "$PRODUCT_TEAMS_JSON" "$release_id" "$slug" "$ROOT_DIR" <<'PY'
import json
import sys
from pathlib import Path

cfg_path, release_id, slug, root = sys.argv[1], sys.argv[2], sys.argv[3], sys.argv[4]
root = Path(root)

with open(cfg_path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

teams = [t for t in (data.get('teams') or []) if t.get('active') and t.get('coordinated_release_default')]
if len(teams) < 2:
    sys.exit(0)

all_signed = all(
    (root / 'sessions' / t['pm_agent'] / 'artifacts' / 'release-signoffs' / f"{slug}.md").exists()
    for t in teams
)
if not all_signed:
    unsigned = [t['pm_agent'] for t in teams
                if not (root / 'sessions' / t['pm_agent'] / 'artifacts' / 'release-signoffs' / f"{slug}.md").exists()]
    print(f"INFO: coordinated push not yet ready — unsigned: {', '.join(unsigned)}")
    sys.exit(0)

# All signed — queue push-ready item for pm-forseti (release operator per DECISION_OWNERSHIP_MATRIX)
import datetime
ts = datetime.datetime.now().strftime('%Y%m%d-%H%M%S')
item_id = f"{ts}-push-ready-{slug[:40]}"
inbox_dir = root / 'sessions' / 'pm-forseti' / 'inbox' / item_id
outbox_file = root / 'sessions' / 'pm-forseti' / 'outbox' / f"{item_id}.md"

if inbox_dir.exists() or outbox_file.exists():
    print(f"INFO: push-ready item already exists for pm-forseti ({item_id})")
    sys.exit(0)

# Check if any push-ready item for this release already exists
inbox_root = root / 'sessions' / 'pm-forseti' / 'inbox'
needle = f"-push-ready-{slug[:30]}"
for p in (inbox_root.iterdir() if inbox_root.exists() else []):
    if p.is_dir() and needle in p.name:
        print(f"INFO: push-ready item already queued for pm-forseti: {p.name}")
        sys.exit(0)

inbox_dir.mkdir(parents=True, exist_ok=True)
(inbox_dir / 'roi.txt').write_text('200\n', encoding='utf-8')
signers = ', '.join(f"{t['pm_agent']} ({t['site']})" for t in teams)
cmd = f"""# Push ready: {release_id}

All required PM signoffs recorded for coordinated release `{release_id}`.

## Signed off by
{signers}

## Required action
As release operator, proceed with the official push:
1. Verify: `bash scripts/release-signoff-status.sh {release_id}`
2. Push per `runbooks/shipping-gates.md` Gate 4.
3. **Advance team release cycles**: `bash scripts/post-coordinated-push.sh`
   (Files each coordinated team's own release signoff so their cycle can advance.)
4. Complete post-push steps (config import, smoke test, SLA report update).
"""
(inbox_dir / 'command.md').write_text(cmd, encoding='utf-8')
print(f"INFO: ALL PMs signed — queued push-ready item for pm-forseti: {item_id}")
PY

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
    <h1 style=\"color:#fff;margin:0;font-size:18px;\">🚀 Coordinated Release Ready for Operator Push</h1>
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
        <td style=\"padding:6px 0;\"><span style=\"background:#1a7f37;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;font-weight:600;\">ALL PMs SIGNED OFF</span></td>
      </tr>
      <tr>
        <td style=\"padding:6px 0;color:#57606a;font-size:13px;\">Site</td>
        <td style=\"padding:6px 0;color:#1f2328;\">${site}</td>
      </tr>
    </table>

    ${_features_html}

    <div style=\"margin-top:24px;padding:16px;background:#f6f8fa;border-radius:6px;border-left:3px solid #1a7f37;\">
      <p style=\"margin:0 0 8px;font-weight:600;color:#1f2328;\">Board note</p>
      <p style=\"margin:0;color:#57606a;font-size:13px;\">This notification is informational unless you are personally acting as the release operator (<code>pm-forseti</code>) for this push.</p>
    </div>

    <div style=\"margin-top:16px;padding:16px;background:#f6f8fa;border-radius:6px;border-left:3px solid #0969da;\">
      <p style=\"margin:0 0 8px;font-weight:600;color:#1f2328;\">Operator action (<code>pm-forseti</code>)</p>
      <p style=\"margin:0;color:#57606a;font-size:13px;\">Verify, push, and advance the release cycle:</p>
      <ol style=\"margin:8px 0 0;padding-left:20px;color:#57606a;font-size:13px;\">
        <li>Check status: <code>bash scripts/release-signoff-status.sh ${release_id}</code></li>
        <li>Push per <strong>runbooks/shipping-gates.md</strong> Gate 4</li>
        <li>Advance cycles: <code>bash scripts/post-coordinated-push.sh</code></li>
        <li>Run post-push steps (config import, smoke test, SLA report)</li>
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

printf "To: %s\nFrom: %s\nSubject: [%s] FYI: coordinated release ready for operator push: %s\nContent-Type: text/html; charset=UTF-8\nMIME-Version: 1.0\n\n%s\n" \
  "$BOARD_EMAIL" "$HQ_FROM_EMAIL" "$HQ_SITE_NAME" "$release_id" "$_email_html" \
  | /usr/sbin/sendmail -t \
  && echo "INFO: Board notification sent to ${BOARD_EMAIL}" \
  || echo "WARN: Board notification email failed (sendmail returned non-zero)"
