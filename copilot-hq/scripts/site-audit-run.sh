#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# NOTE: main() sets runtime variables (ts, SITE_FILTER) and enforces org kill-switch.
ts=""
SITE_FILTER="all"

# This server IS production. Default targets are the live production URLs.
# QA automation against production is gated by ALLOW_PROD_QA=1 (see assert_not_prod_base_url below).
# Override FORSETI_BASE_URL / DUNGEONCRAWLER_BASE_URL if needed for a staging test.
FORSETI_BASE_URL="${FORSETI_BASE_URL:-https://forseti.life}"
DUNGEONCRAWLER_BASE_URL="${DUNGEONCRAWLER_BASE_URL:-https://dungeoncrawler.forseti.life}"
ALLOW_PROD_QA="${ALLOW_PROD_QA:-0}"
PRODUCT_TEAMS_JSON="org-chart/products/product-teams.json"

assert_not_prod_base_url() {
  local label="$1"
  local base_url="$2"

  case "$base_url" in
    https://forseti.life|https://dungeoncrawler.forseti.life)
      if [ "$ALLOW_PROD_QA" != "1" ]; then
        echo "ERROR: refusing to run QA audit against production for ${label}: ${base_url}" >&2
        echo "Set ALLOW_PROD_QA=1 to explicitly authorize running against production." >&2
        echo "Example: ALLOW_PROD_QA=1 bash scripts/site-audit-run.sh" >&2
        exit 2
      fi
      ;;
  esac
}

dispatch_findings() {
  local label="$1"
  local base_url="$2"
  local out_dir="$3"
  local dev_agent_id="$4"
  local pm_agent_id="$5"
  local run_ts="$6"
  local role_cfg_path="${7:-}"
  local release_cycle_active="${8:-0}"
  local release_id="${9:-}"
  local team_id="${10:-}"

  python3 - "$label" "$base_url" "$out_dir" "$dev_agent_id" "$pm_agent_id" "$run_ts" "$role_cfg_path" "$release_cycle_active" "$release_id" "$team_id" <<'PY'
import json
import re
import sys
from pathlib import Path
from urllib.parse import urlparse

label = sys.argv[1]
base_url = sys.argv[2]
out_dir = Path(sys.argv[3])
dev_agent_id = (sys.argv[4] or '').strip()
pm_agent_id = (sys.argv[5] or '').strip()
run_ts = sys.argv[6]
role_cfg_path = (sys.argv[7] or '').strip()
release_cycle_active = (sys.argv[8] or '0').strip() == '1'
release_id = (sys.argv[9] or '').strip()
team_id = (sys.argv[10] or '').strip()

def _is_prod_url(u: str) -> bool:
  try:
    p = urlparse(u)
  except Exception:
    return False
  host = (p.hostname or '').lower()
  return host in ('forseti.life', 'dungeoncrawler.forseti.life')

def _path_for_url(url: str) -> str:
  try:
    p = urlparse(url)
    return p.path or '/'
  except Exception:
    return ''

def _classify(url: str, status: int, *, source: str) -> str:
  path = _path_for_url(url)
  if status >= 500:
    return 'dev'
  if status == 404:
    return 'dev'
  if status in (401, 403):
    # ACL ambiguities usually need PM confirmation.
    # Exception: theme/static assets should not be access-controlled.
    if '/themes/' in path or path.startswith('/sites/default/files/'):
      return 'dev'
    if path.startswith('/admin'):
      return 'ignore'
    return 'pm'
  if status == 405:
    # Often expected for POST-only endpoints; avoid spamming.
    return 'ignore'
  if status >= 400:
    return 'dev'
  return 'ignore'

pm_questions: list[dict] = []
missing_assets_404s: list[dict] = []
failures: list[dict] = []

deny_anon_rules: list[tuple[str, re.Pattern[str]]] = []
if role_cfg_path:
  p = Path(role_cfg_path)
  if p.exists():
    try:
      cfg = json.loads(p.read_text(encoding='utf-8', errors='ignore'))
    except Exception:
      cfg = {}
    for rule in (cfg.get('rules') or []):
      rid = str((rule or {}).get('id') or '').strip() or 'rule'
      rx = str((rule or {}).get('path_regex') or '').strip()
      if not rx:
        continue
      expect = (rule or {}).get('expect') or {}
      anon = str(expect.get('anon') or '').strip().lower()
      if anon != 'deny':
        continue
      try:
        cre = re.compile(rx)
      except re.error:
        continue
      deny_anon_rules.append((rid, cre))

def _suppressed_acl_question(path: str, status: int) -> bool:
  # If anon is expected to be denied for this path, do not escalate as a PM decision.
  # The deny expectation is the documented PM decision.
  if status not in (401, 403):
    return False
  if not path:
    return False
  for _, cre in deny_anon_rules:
    try:
      if cre.search(path):
        return True
    except Exception:
      continue
  return False

perm_violations: list[dict] = []

perm_path = out_dir / 'permissions-validation.json'
if perm_path.exists():
  try:
    perm = json.loads(perm_path.read_text(encoding='utf-8', errors='ignore'))
  except Exception:
    perm = {}
  perm_violations = list(perm.get('violations') or [])

config_drift_warnings: list[dict] = []
drift_path = out_dir / 'config-drift-warnings.json'
if drift_path.exists():
  try:
    config_drift_warnings = json.loads(drift_path.read_text(encoding='utf-8', errors='ignore')) or []
    if not isinstance(config_drift_warnings, list):
      config_drift_warnings = []
  except Exception:
    config_drift_warnings = []

crawl_path = out_dir / f"{label}-crawl.json"
if crawl_path.exists():
  try:
    crawl = json.loads(crawl_path.read_text(encoding='utf-8', errors='ignore'))
  except Exception:
    crawl = {}
  for r in (crawl.get('results') or []):
    url = str(r.get('url') or '')
    status = int(r.get('status') or 0)
    if status < 400:
      continue
    target = _classify(url, status, source='crawl')
    if target == 'ignore':
      continue

    path = _path_for_url(url)
    if status == 404 and ('/themes/' in path or path.startswith('/sites/default/files/')):
      missing_assets_404s.append({
        "source": "crawl",
        "status": status,
        "url": url,
        "path": path,
        "linked_from_url": str(r.get('source_page_url') or ''),
        "link_href": str(r.get('source_link_href') or ''),
        "link_text": str(r.get('source_link_text') or ''),
        "link_title": str(r.get('source_link_title') or ''),
      })
      continue

    evidence_md = out_dir / f"{label}-crawl.md"
    if target == 'pm':
      pm_questions.append({
        "source": "crawl",
        "status": status,
        "url": url,
        "path": path,
        "linked_from_url": str(r.get('source_page_url') or ''),
        "link_href": str(r.get('source_link_href') or ''),
        "link_text": str(r.get('source_link_text') or ''),
        "link_title": str(r.get('source_link_title') or ''),
        "evidence_md": evidence_md.as_posix(),
        "evidence_json": crawl_path.as_posix(),
      })
      continue

    failures.append({
      "source": "crawl",
      "status": status,
      "url": url,
      "path": path,
      "linked_from_url": str(r.get('source_page_url') or ''),
      "link_href": str(r.get('source_link_href') or ''),
      "link_text": str(r.get('source_link_text') or ''),
      "link_title": str(r.get('source_link_title') or ''),
      "evidence_md": evidence_md.as_posix(),
      "evidence_json": crawl_path.as_posix(),
    })

routes_path = out_dir / f"{label}-custom-routes.json"
if routes_path.exists():
  try:
    routes = json.loads(routes_path.read_text(encoding='utf-8', errors='ignore'))
  except Exception:
    routes = {}
  for c in (routes.get('checks') or []):
    url = str(c.get('url') or '')
    status = int(c.get('status') or 0)
    if status < 400:
      continue

    path_tpl = str(c.get('path_template') or '')
    # 404 on a parameterized route probe just means the placeholder entity
    # doesn't exist in the test DB — not a code or permission failure.
    if status == 404 and '{' in path_tpl:
      continue

    target = _classify(url, status, source='routes')
    if target == 'ignore':
      continue

    route_name = str(c.get('route_name') or '')
    module = str(c.get('module') or '')
    note = str(c.get('note') or '')

    evidence_md = out_dir / 'route-audit-summary.md'
    path = _path_for_url(url)
    if target == 'pm':
      pm_questions.append({
        "source": "route",
        "status": status,
        "url": url,
        "path": path,
        "route_name": route_name,
        "path_template": path_tpl,
        "module": module,
        "note": note,
        "evidence_md": evidence_md.as_posix(),
        "evidence_json": routes_path.as_posix(),
      })
      continue

    failures.append({
      "source": "route",
      "status": status,
      "url": url,
      "path": path,
      "route_name": route_name,
      "path_template": path_tpl,
      "module": module,
      "note": note,
      "evidence_md": evidence_md.as_posix(),
      "evidence_json": routes_path.as_posix(),
    })
pm_questions_filtered: list[dict] = []
suppressed = 0
for r in pm_questions:
  path = str(r.get('path') or '')
  status = int(r.get('status') or 0)
  if _suppressed_acl_question(path, status):
    suppressed += 1
    continue
  pm_questions_filtered.append(r)

summary = {
  "label": label,
  "base_url": base_url,
  "run_ts": run_ts,
  "is_prod": _is_prod_url(base_url),
  "counts": {
    "missing_assets_404s": len(missing_assets_404s),
    "pm_acl_questions": len(pm_questions_filtered),
    "pm_acl_questions_suppressed": suppressed,
    "permission_violations": len(perm_violations),
    "failures": len(failures),
  },
  "missing_assets_404s": missing_assets_404s[:300],
  "pm_acl_questions": pm_questions_filtered[:300],
  "permission_violations": perm_violations[:300],
  "failures": failures[:300],
  "config_drift_warnings": config_drift_warnings[:100],
  "evidence": {
    "crawl_json": crawl_path.as_posix() if crawl_path.exists() else "",
    "crawl_md": (out_dir / f"{label}-crawl.md").as_posix(),
    "routes_json": routes_path.as_posix() if routes_path.exists() else "",
    "routes_md": (out_dir / 'route-audit-summary.md').as_posix(),
    "permissions_json": perm_path.as_posix() if perm_path.exists() else "",
    "permissions_md": (out_dir / 'permissions-validation.md').as_posix(),
  },
}

open_issue_total = (
  int(summary["counts"]["missing_assets_404s"])
  + int(summary["counts"]["permission_violations"])
  + int(summary["counts"]["failures"])
)

def _slug(text: str) -> str:
  s = re.sub(r'[^A-Za-z0-9._-]+', '-', (text or '').strip())
  s = s.strip('-')
  return s[:40] or 'site'

def _has_pending_findings_item(agent_id: str, label_slug: str) -> bool:
  inbox_root = Path('sessions') / agent_id / 'inbox'
  if not inbox_root.exists() or not inbox_root.is_dir():
    return False
  needle = f"-qa-findings-{label_slug}-"
  for p in inbox_root.iterdir():
    if p.is_dir() and needle in p.name:
      return True
  return False

def _queue_dev_findings_item() -> None:
  if not release_cycle_active:
    return
  if not dev_agent_id:
    return
  if open_issue_total <= 0:
    return

  label_slug = _slug(label)
  if _has_pending_findings_item(dev_agent_id, label_slug):
    return

  item_id = f"{run_ts}-qa-findings-{label_slug}-{open_issue_total}"
  inbox_dir = Path('sessions') / dev_agent_id / 'inbox' / item_id
  outbox_file = Path('sessions') / dev_agent_id / 'outbox' / f"{item_id}.md"
  if inbox_dir.exists() or outbox_file.exists():
    return

  inbox_dir.mkdir(parents=True, exist_ok=True)
  roi = max(4, min(50, open_issue_total * 2))
  (inbox_dir / 'roi.txt').write_text(f"{roi}\n", encoding='utf-8')

  # --- Feature ID detection ---
  # Load qa-permissions.json and suite.json to find feature_ids for failing paths.
  # If a failing test/route has a feature_id, Dev must IMPLEMENT the feature
  # (read features/<feature_id>/ living doc), not just fix a regression.
  feature_id_map: dict[str, str] = {}  # path_regex_or_suite_id -> feature_id

  if role_cfg_path:
    try:
      cfg = json.loads(Path(role_cfg_path).read_text(encoding='utf-8', errors='ignore'))
      for rule in (cfg.get('rules') or []):
        fid = str(rule.get('feature_id') or '').strip()
        if fid:
          path_regex = str(rule.get('path_regex') or '').strip()
          rule_id = str(rule.get('id') or '').strip()
          if path_regex:
            feature_id_map[f"rule:{path_regex}"] = fid
          if rule_id:
            feature_id_map[f"id:{rule_id}"] = fid
    except Exception:
      pass

  # Also check suite.json for feature-tagged suites
  suite_feature_ids: list[str] = []
  try:
    from org.chart.products import product_teams_json  # noqa
  except Exception:
    pass
  suite_manifest_paths = list(Path('qa-suites/products').glob('*/suite.json'))
  for sp in suite_manifest_paths:
    try:
      sm = json.loads(sp.read_text(encoding='utf-8', errors='ignore'))
      for suite in (sm.get('suites') or []):
        fid = str(suite.get('feature_id') or '').strip()
        if fid and fid not in suite_feature_ids:
          suite_feature_ids.append(fid)
    except Exception:
      pass

  # Cross-reference failures against feature_id_map
  new_feature_items: dict[str, list[str]] = {}  # feature_id -> [failing paths]
  for f_item in (summary.get('permission_violations') or []) + (summary.get('failures') or []):
    path = str(f_item.get('path') or f_item.get('url') or '').strip()
    # Try to match against known feature rule regexes
    for key, fid in feature_id_map.items():
      if key.startswith('rule:'):
        pattern = key[5:]
        try:
          if re.search(pattern, path):
            new_feature_items.setdefault(fid, [])
            if path not in new_feature_items[fid]:
              new_feature_items[fid].append(path)
        except re.error:
          pass

  new_feature_section = ''
  if new_feature_items:
    lines = [
      '\n    ## NEW FEATURE IMPLEMENTATIONS REQUIRED',
      '    The following failures are on tests that have NEVER PASSED.',
      '    These are NOT regressions. The feature has not been built yet.',
      '    For each feature_id below, your task is to IMPLEMENT the feature',
      '    per the living requirements document at features/<feature_id>/.',
      '    '
    ]
    for fid, paths in new_feature_items.items():
      lines.append(f'    ### Feature: {fid}')
      lines.append(f'    Living doc: features/{fid}/')
      lines.append(f'    - feature.md          → PM brief + goals')
      lines.append(f'    - 01-acceptance-criteria.md → what to build (PM-owned)')
      lines.append(f'    - 03-test-plan.md     → what QA will verify (QA-owned)')
      lines.append(f'    - 02-implementation-notes.md → YOUR artifact (create/update this)')
      lines.append(f'    Failing paths ({len(paths)}):')
      for p in paths[:10]:
        lines.append(f'      - {p}')
      lines.append('    ')
      lines.append('    BEFORE implementing: read 01-acceptance-criteria.md fully.')
      lines.append('    BEFORE implementing: assess impact on existing flows (see Dev instructions).')
      lines.append('    AFTER implementing: notify QA with specific paths fixed for targeted retest.')
      lines.append('    ')
    new_feature_section = '\n'.join(lines)

  regression_section = ''
  regression_total = open_issue_total - sum(len(v) for v in new_feature_items.values())
  if regression_total > 0:
    regression_section = f"""
    ## REGRESSION FIXES REQUIRED
    The following failures are on tests that previously passed (or have no feature_id).
    Treat these as regressions — identify what changed and restore correct behavior.

    Open issue buckets:
    - Missing assets (404): {summary['counts']['missing_assets_404s']}
    - Permission violations: {summary['counts']['permission_violations']}
    - Other failures (4xx/5xx): {summary['counts']['failures']}
    - Total: {regression_total} (approx — subtract new-feature paths above)

    Required actions:
    1) Fix highest-impact failures first.
    2) For each fix, notify QA immediately with explicit handoff marker.
    3) Keep notes concise in outbox and include touched files/routes.
"""

  cmd = f"""# QA Findings — Action Required

- Product team: {team_id or '(unknown)'}
- Release id: {release_id or '(unspecified)'}
- Site label: {label}
- Base URL: {base_url}
- QA run: {run_ts}
- Findings summary: { (out_dir / 'findings-summary.md').as_posix() }
- Findings JSON: { (out_dir / 'findings-summary.json').as_posix() }
- Total open issues: {open_issue_total}
{new_feature_section}{regression_section}
Deliverable:
- Outbox update with Status + Summary and QA handoff notes.
- For new features: create/update features/<feature_id>/02-implementation-notes.md.
"""
  (inbox_dir / 'command.md').write_text(cmd, encoding='utf-8')

_queue_dev_findings_item()

def _queue_pm_gate2_ready_item() -> None:
  """Notify PM when violations drop to 0 during an active release cycle."""
  if not release_cycle_active:
    return
  if not pm_agent_id:
    return
  # Guard: skip if release_id is stale (cycle has advanced since audit started).
  # Re-read the active marker at dispatch time; if the file is gone or holds a
  # different release_id, the cycle has already advanced and we must not dispatch.
  if team_id and release_id:
    active_rid_file = Path('tmp') / 'release-cycle-active' / f"{team_id}.release_id"
    if not active_rid_file.exists():
      print(f"INFO: skip gate2-ready queue — release {release_id} no longer active (marker removed)")
      return
    current_active_rid = active_rid_file.read_text(encoding='utf-8').strip()
    if current_active_rid != release_id:
      print(f"INFO: skip gate2-ready queue — release {release_id} stale (active: {current_active_rid})")
      return
  if open_issue_total > 0:
    return

  # Guard: skip if any in-progress feature for this site/release lacks a dev outbox.
  # Fires when late-activated features have not been implemented yet, preventing a
  # premature gate2-ready signal from reaching the PM.
  if release_id and dev_agent_id and team_id:
    features_dir = Path('features')
    dev_outbox_dir = Path('sessions') / dev_agent_id / 'outbox'
    dev_outbox_names: set = set()
    if dev_outbox_dir.exists():
      dev_outbox_names = {f.name for f in dev_outbox_dir.iterdir() if f.is_file()}
    if features_dir.exists():
      for feature_md in features_dir.glob('*/feature.md'):
        feature_id = feature_md.parent.name
        try:
          text = feature_md.read_text(encoding='utf-8')
        except OSError:
          continue
        status_m  = re.search(r'^-\s+Status:\s*(\S+)',  text, re.MULTILINE)
        release_m = re.search(r'^-\s+Release:\s*(\S+)', text, re.MULTILINE)
        website_m = re.search(r'^-\s+Website:\s*(\S+)', text, re.MULTILINE)
        if not (status_m and release_m and website_m):
          continue
        if status_m.group(1).strip() != 'in_progress':
          continue
        if release_m.group(1).strip() != release_id:
          continue
        # Match Website field against both label (e.g. "forseti.life") and
        # team_id (e.g. "forseti") to handle naming differences across sites.
        if website_m.group(1).strip() not in (label, team_id):
          continue
        # Feature is in scope — require at least one dev outbox file naming it.
        if not any(feature_id in name for name in dev_outbox_names):
          print(f"Gate2-ready suppressed: feature {feature_id} has no dev outbox yet")
          return

  label_slug = _slug(label)
  item_id = f"{run_ts}-gate2-ready-{label_slug}"
  inbox_dir = Path('sessions') / pm_agent_id / 'inbox' / item_id
  outbox_file = Path('sessions') / pm_agent_id / 'outbox' / f"{item_id}.md"
  # Only queue once per run_ts; skip if a gate2-ready item for this run already exists.
  if inbox_dir.exists() or outbox_file.exists():
    return
  # Skip if a more recent gate2-ready item already exists (avoid duplicate churn).
  inbox_root = Path('sessions') / pm_agent_id / 'inbox'
  needle = f"-gate2-ready-{label_slug}"
  for p in (inbox_root.iterdir() if inbox_root.exists() else []):
    if p.is_dir() and needle in p.name and p.name != item_id:
      return
  # Skip if PM already recorded a release signoff for this release-id (idempotency).
  if release_id:
    release_slug = re.sub(r'[^A-Za-z0-9._-]+', '-', release_id.strip()).strip('-')[:80]
    signoff_path = Path('sessions') / pm_agent_id / 'artifacts' / 'release-signoffs' / f"{release_slug}.md"
    if signoff_path.exists():
      print(f"INFO: skip gate2-ready queue — signoff already recorded: {signoff_path}")
      return

  inbox_dir.mkdir(parents=True, exist_ok=True)
  (inbox_dir / 'roi.txt').write_text("150\n", encoding='utf-8')
  cmd = f"""# Gate 2 Ready — {label}

- Site: {label}
- Release id: {release_id or '(unspecified)'}
- QA run: {run_ts}
- Base URL: {base_url}
- Findings summary: {(out_dir / 'findings-summary.md').as_posix()}

## Signal
All automated permission checks passed (0 violations, 0 missing assets, 0 other failures).
The site is ready for Gate 2 — Verification.

## Required actions
1) Review the QA evidence linked above.
2) If satisfied: run `bash scripts/release-signoff.sh {team_id or label_slug} {release_id or '(release-id)'}` to record your signoff.
3) Coordinate with the release operator ({pm_agent_id}) to confirm both required PM signoffs before the official push.
"""
  (inbox_dir / 'command.md').write_text(cmd, encoding='utf-8')
  print(f"INFO: queued Gate 2 ready notification for {pm_agent_id}: {item_id}")

_queue_pm_gate2_ready_item()

(out_dir / 'findings-summary.json').write_text(
  json.dumps(summary, indent=2, sort_keys=True) + "\n",
  encoding='utf-8',
)

lines = []
lines.append(f"# QA audit findings summary — {label}")
lines.append("")
lines.append(f"- Base URL: {base_url}")
lines.append(f"- Run: {run_ts}")
lines.append(f"- Environment: {'PRODUCTION' if summary['is_prod'] else 'local/dev'}")
lines.append("")
lines.append("## PASS/FAIL signal")
lines.append("- This audit is FAIL if any of the below are non-zero (unless explicitly accepted for this release):")
lines.append(f"  - Missing assets (404): {summary['counts']['missing_assets_404s']}")
lines.append(f"  - Permission expectation violations: {summary['counts']['permission_violations']}")
lines.append(f"  - Other failures (4xx/5xx): {summary['counts']['failures']}")
lines.append("")
lines.append("## PM decisions needed (ACL intent)")
lines.append(f"- Pending: {summary['counts']['pm_acl_questions']}")
lines.append(f"- Suppressed (already decided as anon=deny): {summary['counts']['pm_acl_questions_suppressed']}")
lines.append("")
lines.append("## Evidence")
for k, v in summary['evidence'].items():
  if v:
    lines.append(f"- {k}: {v}")
lines.append("")
lines.append("## Config drift")
if config_drift_warnings:
  lines.append(f"Config drift detected: {len(config_drift_warnings)} user.role.* items not in sync")
  for w in config_drift_warnings:
    lines.append(f"- {w.get('config_item', '?')} is {w.get('status', '?')}")
else:
  lines.append("- No config drift detected (user.role.* configs are Identical).")
lines.append("")
lines.append("## Notes")
if not release_cycle_active:
  lines.append("- Release cycle inactive; no Dev findings item queued.")
elif dev_agent_id and open_issue_total > 0:
  lines.append("- Script auto-queued a Dev findings item (review QA results and fix failed tests) when no pending findings item existed.")
else:
  lines.append("- No Dev findings item queued (no open issues or no mapped Dev seat).")
lines.append("- Dev consumes this evidence and fixes failing behavior; QA updates suites if the test script is flawed.")
lines.append("- PM is pulled in only for scope/intent decisions (e.g., ACL publicness) and for release coordination/final push.")

(out_dir / 'findings-summary.md').write_text("\n".join(lines) + "\n", encoding='utf-8')

print("WROTE: findings-summary.md")
print("WROTE: findings-summary.json")
PY

  if [ -n "${team_id:-}" ]; then
    python3 scripts/gate2-clean-audit-backstop.py --team "${team_id}" --source "site-audit-run.sh" || true
  fi
}

run_site() {
  local label="$1"
  local base_url="$2"
  local drupal_web_root="$3"
  local qa_art_dir="$4"
  local route_regex="$5"
  local role_cfg_path="${6:-}"
  local dev_agent_id="${7:-}"
  local pm_agent_id="${8:-}"
  local team_id="${9:-}"

  mkdir -p "$qa_art_dir"
  local out_dir="$qa_art_dir/${ts}"
  mkdir -p "$out_dir"

  local tmp_dir="tmp/site-audit/${label}-${ts}"
  mkdir -p "$tmp_dir"

  # --- MODULE-STATE PREFLIGHT (Drupal local sites only) ---
  # Prevents false-positive 404 storms caused by disabled custom modules.
  # Runs only for local URLs where drush is accessible; skips silently otherwise.
  local _preflight_local=false
  case "$base_url" in http://localhost*|http://127.*|http://0.0.0.0*) _preflight_local=true ;; esac

  if [ "$_preflight_local" = "true" ] && [ -n "$drupal_web_root" ] && [ -d "$drupal_web_root" ]; then
    local _site_root
    _site_root="$(dirname "$drupal_web_root")"
    local _drush_bin="$_site_root/vendor/bin/drush"
    if [ -x "$_drush_bin" ]; then
      local _disabled_modules
      _disabled_modules=$(cd "$_site_root" && "$_drush_bin" pm:list --type=module --status=disabled 2>/dev/null \
        | grep -Ei "^(AI|Dungeon Crawler)" | awk -F'(' '{print $2}' | tr -d ')' | xargs)
      if [ -n "$_disabled_modules" ]; then
        echo "PREFLIGHT_FAIL: ${label}: custom modules disabled: ${_disabled_modules} — skipping audit to prevent false-positive 404 storm" >&2
        return 1
      fi
    fi
  fi
  # --- END MODULE-STATE PREFLIGHT ---

  timeout 180s python3 scripts/site-full-audit.py \
    --base-url "$base_url" \
    --max-pages 400 \
    --max-depth 4 \
    --timeout-sec 10 \
    --max-seconds 150 \
    --out-prefix "$tmp_dir/${label}-crawl"

  timeout 180s python3 scripts/drupal-custom-routes-audit.py \
    --drupal-web-root "$drupal_web_root" \
    --base-url "$base_url" \
    --timeout-sec 10 \
    --max-seconds 150 \
    --path-regex "$route_regex" \
    --max-routes 800 \
    --out "$tmp_dir/${label}-custom-routes.json"

  cp -f "$tmp_dir/${label}-crawl."* "$out_dir/" 2>/dev/null || true
  cp -f "$tmp_dir/${label}-custom-routes.json" "$out_dir/" 2>/dev/null || true

  # Optional: role-based audits + permission expectation validation.
  if [ -n "$role_cfg_path" ] && [ -f "$role_cfg_path" ]; then

    # Auto-acquire QA session cookies if drupal_root is set in the config.
    # drupal-qa-sessions.py creates ephemeral qa_tester_<role> users via drush,
    # gets one-time login URLs, follows them to capture session cookies, then
    # writes an env file we source here to populate SITE_COOKIE_ROLE vars.
    #
    # OTL-based acquisition works for local URLs and for production URLs when
    # drush is collocated with the live Drupal instance (same DB + codebase).
    # Only non-collocated production audits require pre-set COOKIE env vars
    # (for example via drupal-qa-sessions.py --credentials-file).
    local drupal_root_from_cfg
    drupal_root_from_cfg=$(python3 -c "
import json, sys
cfg = json.loads(open(sys.argv[1], encoding='utf-8').read())
print(cfg.get('drupal_root') or '')
" "$role_cfg_path" 2>/dev/null || true)

    local is_local_url=false
    case "$base_url" in
      http://localhost*|http://127.*|http://0.0.0.0*)
        is_local_url=true ;;
    esac

    # Collocated drush: the machine running the audit IS the Drupal backend.
    # When vendor/bin/drush is present in drupal_root, drush can generate OTL
    # tokens valid for any base_url (local or production HTTPS).
    local is_drush_collocated=false
    if [ -n "$drupal_root_from_cfg" ] && [ -x "$drupal_root_from_cfg/vendor/bin/drush" ]; then
      is_drush_collocated=true
    fi

    if [ -n "$drupal_root_from_cfg" ] && [ -d "$drupal_root_from_cfg" ] && \
        { [ "$is_local_url" = "true" ] || [ "$is_drush_collocated" = "true" ]; }; then
      local qa_sessions_env="$tmp_dir/${label}-qa-sessions.env"
      local otl_mode_label="local site"
      [ "$is_local_url" = "false" ] && otl_mode_label="collocated drush (server=production)"
      echo "INFO: ${label}: acquiring QA session cookies via drush OTL (${otl_mode_label})..." >&2
      if python3 scripts/drupal-qa-sessions.py \
          --drupal-root "$drupal_root_from_cfg" \
          --config "$role_cfg_path" \
          --base-url "$base_url" \
          --out "$qa_sessions_env" 2>&1; then
        # shellcheck disable=SC1090
        source "$qa_sessions_env"
        echo "INFO: ${label}: QA sessions sourced ($(grep -c '^export' "$qa_sessions_env" || echo 0) roles)" >&2
      else
        echo "WARN: ${label}: QA session acquisition partially failed; roles without cookies will be skipped" >&2
        # shellcheck disable=SC1090
        [ -f "$qa_sessions_env" ] && source "$qa_sessions_env" || true
      fi
    elif [ -n "$drupal_root_from_cfg" ] && [ "$is_local_url" = "false" ] && [ "$is_drush_collocated" = "false" ]; then
      echo "INFO: ${label}: production URL — using pre-set COOKIE env vars for per-role audit (set via drupal-qa-sessions.py --credentials-file for automation)" >&2
    fi

    while IFS=$'\t' read -r role_id cookie_env; do
      [ -n "$role_id" ] || continue
      if [ "$role_id" = "anon" ]; then
        continue
      fi
      cookie_env="${cookie_env:-}"
      if [ -z "$cookie_env" ]; then
        continue
      fi
      cookie_val="${!cookie_env:-}"
      if [ -z "$cookie_val" ]; then
        echo "WARN: ${label}: skipping role '${role_id}' (env var ${cookie_env} not set)" >&2
        continue
      fi

      # Session validity probe: follow /user redirect — valid sessions land on /user/<uid>,
      # invalid (expired/failed) sessions land back on /user/login or /user/register.
      local _probe_final_url
      _probe_final_url=$(curl -s -o /dev/null -L -w '%{url_effective}' \
        --cookie "${cookie_val}" \
        --max-time 10 \
        "${base_url}/user" 2>/dev/null || echo "")
      if echo "$_probe_final_url" | grep -qE '/user/login|/user/register'; then
        echo "WARN: ${label}: session invalid for role '${role_id}' (probe redirected to ${_probe_final_url}); skipping crawl to avoid false violations" >&2
        continue
      fi

      local role_tmp_dir="$tmp_dir/roles/$role_id"
      local role_out_dir="$out_dir/roles/$role_id"
      mkdir -p "$role_tmp_dir" "$role_out_dir"

      if ! timeout 180s python3 scripts/site-full-audit.py \
        --base-url "$base_url" \
        --header "Cookie: ${cookie_val}" \
        --max-pages 250 \
        --max-depth 4 \
        --timeout-sec 10 \
        --max-seconds 120 \
        --out-prefix "$role_tmp_dir/${label}-crawl"; then
        echo "WARN: ${label}: role '${role_id}' crawl failed/timeout" >&2
      fi

      if ! timeout 180s python3 scripts/drupal-custom-routes-audit.py \
        --drupal-web-root "$drupal_web_root" \
        --base-url "$base_url" \
        --header "Cookie: ${cookie_val}" \
        --timeout-sec 10 \
        --max-seconds 120 \
        --path-regex "$route_regex" \
        --max-routes 800 \
        --out "$role_tmp_dir/${label}-custom-routes.json"; then
        echo "WARN: ${label}: role '${role_id}' route audit failed/timeout" >&2
      fi

      cp -f "$role_tmp_dir/${label}-crawl."* "$role_out_dir/" 2>/dev/null || true
      cp -f "$role_tmp_dir/${label}-custom-routes.json" "$role_out_dir/" 2>/dev/null || true
    done < <(
      python3 - "$role_cfg_path" <<'PY'
import json
import sys

path = sys.argv[1]
cfg = json.loads(open(path, 'r', encoding='utf-8').read())
for r in (cfg.get('roles') or []):
  rid = str(r.get('id') or '').strip()
  env = str(r.get('cookie_env') or '').strip()
  if not rid:
    continue
  print(f"{rid}\t{env}")
PY
    )

    # Union validation: take all discovered URLs (crawl + custom routes, across
    # roles) and probe that complete set as each role. This ensures newly found
    # URLs are automatically included in the permission validation set.
    all_paths_file="$tmp_dir/${label}-all-paths.txt"
    python3 - "$out_dir" "$label" "$all_paths_file" <<'PY'
import json
import re
import sys
from pathlib import Path
from urllib.parse import urlparse

out_dir = Path(sys.argv[1])
label = sys.argv[2]
out_path = Path(sys.argv[3])

def path_for_url(u: str) -> str:
  try:
    p = urlparse(u)
    return p.path or '/'
  except Exception:
    return ''

def read_urls_from(path: Path) -> list[str]:
  if not path.exists():
    return []
  try:
    data = json.loads(path.read_text(encoding='utf-8', errors='ignore'))
  except Exception:
    return []
  urls = []
  if isinstance(data, dict):
    if 'results' in data and isinstance(data.get('results'), list):
      for r in data.get('results') or []:
        if isinstance(r, dict):
          urls.append(str(r.get('url') or ''))
    if 'checks' in data and isinstance(data.get('checks'), list):
      for c in data.get('checks') or []:
        if isinstance(c, dict):
          urls.append(str(c.get('url') or ''))
  return [u for u in urls if u]

paths: set[str] = set()

for p in [
  out_dir / f"{label}-crawl.json",
  out_dir / f"{label}-custom-routes.json",
]:
  for u in read_urls_from(p):
    pa = path_for_url(u)
    if pa:
      paths.add(pa)

roles_dir = out_dir / 'roles'
if roles_dir.exists() and roles_dir.is_dir():
  for role_dir in roles_dir.iterdir():
    if not role_dir.is_dir():
      continue
    for p in [
      role_dir / f"{label}-crawl.json",
      role_dir / f"{label}-custom-routes.json",
    ]:
      for u in read_urls_from(p):
        pa = path_for_url(u)
        if pa:
          paths.add(pa)

out_path.parent.mkdir(parents=True, exist_ok=True)
out_path.write_text("\n".join(sorted(paths)) + "\n", encoding='utf-8')
print(f"union_paths={len(paths)}")
PY

    # Tune limits for production post-release audits (explicitly enabled) to
    # reduce load. Localhost/dev can be more exhaustive.
    validate_max_urls=600
    validate_max_seconds=120
    case "$base_url" in
      https://forseti.life|https://dungeoncrawler.forseti.life)
        validate_max_urls=250
        validate_max_seconds=60
        ;;
    esac

    # Anonymous validation.
    timeout 180s python3 scripts/site-validate-urls.py \
      --base-url "$base_url" \
      --in "$all_paths_file" \
      --timeout-sec 10 \
      --max-seconds "$validate_max_seconds" \
      --max-urls "$validate_max_urls" \
      --out "$tmp_dir/${label}-validate.json"
    cp -f "$tmp_dir/${label}-validate.json" "$out_dir/" 2>/dev/null || true

    # Per-role validation using configured cookies.
    while IFS=$'\t' read -r role_id cookie_env; do
      [ -n "$role_id" ] || continue
      if [ "$role_id" = "anon" ]; then
        continue
      fi
      cookie_env="${cookie_env:-}"
      [ -n "$cookie_env" ] || continue
      cookie_val="${!cookie_env:-}"
      if [ -z "$cookie_val" ]; then
        echo "WARN: ${label}: skipping validate role '${role_id}' (env var ${cookie_env} not set)" >&2
        continue
      fi

      # Session validity probe: same guard as crawl loop.
      local _probe_final_url
      _probe_final_url=$(curl -s -o /dev/null -L -w '%{url_effective}' \
        --cookie "${cookie_val}" \
        --max-time 10 \
        "${base_url}/user" 2>/dev/null || echo "")
      if echo "$_probe_final_url" | grep -qE '/user/login|/user/register'; then
        echo "WARN: ${label}: session invalid for role '${role_id}' (probe redirected to ${_probe_final_url}); skipping validate to avoid false violations" >&2
        continue
      fi

      role_tmp_dir="$tmp_dir/roles/$role_id"
      role_out_dir="$out_dir/roles/$role_id"
      mkdir -p "$role_tmp_dir" "$role_out_dir"

      timeout 180s python3 scripts/site-validate-urls.py \
        --base-url "$base_url" \
        --in "$all_paths_file" \
        --header "Cookie: ${cookie_val}" \
        --timeout-sec 10 \
        --max-seconds "$validate_max_seconds" \
        --max-urls "$validate_max_urls" \
        --out "$role_tmp_dir/${label}-validate.json"
      cp -f "$role_tmp_dir/${label}-validate.json" "$role_out_dir/" 2>/dev/null || true
    done < <(
      python3 - "$role_cfg_path" <<'PY'
import json
import sys

path = sys.argv[1]
cfg = json.loads(open(path, 'r', encoding='utf-8').read())
for r in (cfg.get('roles') or []):
  rid = str(r.get('id') or '').strip()
  env = str(r.get('cookie_env') or '').strip()
  if not rid:
    continue
  print(f"{rid}\t{env}")
PY
    )

    # Config drift pre-flight check: detect user.role.* items not yet imported into DB.
    # Runs only on local URLs where drush is available (same guard as cookie acquisition).
    # WARNING-only: does not change PASS/FAIL signal or exit code.
    if [ -n "$drupal_root_from_cfg" ] && [ "$is_local_url" = "true" ]; then
      local drush_bin="${drupal_root_from_cfg}/vendor/bin/drush"
      if [ -x "$drush_bin" ]; then
        local drift_out
        drift_out=$(
          "$drush_bin" --root="$drupal_root_from_cfg" config:status 2>/dev/null \
            | grep "user\.role\." | grep -v "Identical" || true
        )
        local drift_json="[]"
        if [ -n "$drift_out" ]; then
          while IFS= read -r drift_line; do
            [ -n "$drift_line" ] || continue
            # Extract config item name (first word-group) and status (rest).
            local drift_item drift_status
            drift_item=$(echo "$drift_line" | awk '{print $1}')
            drift_status=$(echo "$drift_line" | awk '{$1=""; print $0}' | sed 's/^ *//')
            echo "CONFIG DRIFT WARNING: ${drift_item} is ${drift_status}"
          done <<< "$drift_out"
          # Build JSON array of drift items.
          drift_json=$(
            echo "$drift_out" | python3 - <<'DRIFTPY'
import sys, json
items = []
for ln in sys.stdin:
    ln = ln.rstrip()
    if not ln:
        continue
    parts = ln.split(None, 1)
    if len(parts) == 2:
        items.append({"config_item": parts[0], "status": parts[1].strip()})
    else:
        items.append({"config_item": parts[0], "status": ""})
print(json.dumps(items))
DRIFTPY
          )
        fi
        printf '%s\n' "$drift_json" > "$out_dir/config-drift-warnings.json"
      fi
    fi

    python3 scripts/role-permissions-validate.py \
      --config "$role_cfg_path" \
      --base-url "$base_url" \
      --label "$label" \
      --out-dir "$out_dir"
  fi

  BASE_URL="$base_url" ROUTES_PATH="$out_dir/${label}-custom-routes.json" OUT_PATH="$out_dir/route-audit-summary.md" \
    python3 - <<'PY'
import json
import os
import pathlib

base_url = os.environ.get('BASE_URL', '').strip()
routes_path = pathlib.Path(os.environ.get('ROUTES_PATH', '').strip())
out_path = pathlib.Path(os.environ.get('OUT_PATH', '').strip())

data = json.loads(routes_path.read_text(encoding='utf-8')) if routes_path.exists() else {}
checks = data.get('checks', []) or []

def is_admin(p: str) -> bool:
    return p.startswith('/admin')
def is_api(p: str) -> bool:
    return p.startswith('/api/') or '/api/' in p

admin200 = []
api_err = []
other_err = []
for c in checks:
    path_tpl = c.get('path_template','')
    status = int(c.get('status') or 0)
    module = c.get('module','')
    route = c.get('route_name','')
    url = c.get('url','')
    if is_admin(path_tpl) and status == 200:
        admin200.append((status, module, route, path_tpl, url))
    if is_api(path_tpl) and status >= 400:
        api_err.append((status, module, route, path_tpl, url))
    if (not is_admin(path_tpl)) and (not is_api(path_tpl)) and status >= 400:
        other_err.append((status, module, route, path_tpl, url))

lines = []
lines.append('# Route audit summary')
lines.append('')
lines.append(f'- Base URL: {base_url}')
lines.append(f'- Routes checked: {len(checks)}')
lines.append('')

def section(title, rows, limit=40):
    lines.append(f'## {title}')
    if not rows:
        lines.append('- None')
        lines.append('')
        return
    lines.append('| Status | Module | Route | Path | URL |')
    lines.append('|---:|---|---|---|---|')
    for status, module, route, path_tpl, url in rows[:limit]:
        lines.append(f'| {status} | {module} | {route} | `{path_tpl}` | {url} |')
    if len(rows) > limit:
        lines.append('')
        lines.append(f'(Truncated: {len(rows)} rows)')
    lines.append('')

section('Admin routes returning 200 (potential ACL bug)', sorted(admin200))
section('API routes with errors (>=400)', sorted(api_err))
section('Other non-admin route errors (>=400)', sorted(other_err))

out_path.write_text('\n'.join(lines), encoding='utf-8')
PY

  # Update a stable "latest" pointer for dashboards/quick access.
  # Use a relative symlink to avoid malformed/broken targets.
  (
    cd "$qa_art_dir"
    ln -sfn "${ts}" latest
  )

  local release_cycle_active=0
  local release_id=""
  if [ -n "$team_id" ]; then
    local marker="tmp/release-cycle-active/${team_id}.release_id"
    if [ -f "$marker" ]; then
      release_id="$(tr -d '\r\n' < "$marker" 2>/dev/null || true)"
      if [ -n "$release_id" ]; then
        release_cycle_active=1
      fi
    fi
  fi

  dispatch_findings "$label" "$base_url" "$out_dir" "$dev_agent_id" "$pm_agent_id" "$ts" "$role_cfg_path" "$release_cycle_active" "$release_id" "$team_id"
}

main() {
  # Org kill-switch: if org automation is disabled, do not run audits.
  if [ "$(./scripts/is-org-enabled.sh 2>/dev/null || echo false)" != "true" ]; then
    echo "[site-audit-run] org disabled (org-control.json enabled=false), skipping." >&2
    exit 0
  fi

  ts="$(date +%Y%m%d-%H%M%S)"
  SITE_FILTER="${1:-all}"

  mkdir -p tmp/site-audit 2>/dev/null || true

  if [ ! -f "$PRODUCT_TEAMS_JSON" ]; then
    echo "ERROR: missing product team registry: $PRODUCT_TEAMS_JSON" >&2
    exit 2
  fi

  if ! selected_teams="$({
    python3 - "$PRODUCT_TEAMS_JSON" "$SITE_FILTER" <<'PY'
import json
import sys

cfg_path = sys.argv[1]
query = (sys.argv[2] or 'all').strip().lower()

with open(cfg_path, 'r', encoding='utf-8') as fh:
    data = json.load(fh)

teams = data.get('teams') or []
matched = []
for team in teams:
    if not team.get('active', False):
        continue
    site_audit = team.get('site_audit') or {}
    if not site_audit.get('enabled', False):
        continue

    team_id = str(team.get('id') or '').strip()
    site = str(team.get('site') or '').strip()
    aliases = [str(a).strip().lower() for a in (team.get('aliases') or []) if str(a).strip()]
    audit_filter = str(site_audit.get('filter') or '').strip()
    candidates = set(aliases + [team_id.lower(), site.lower(), audit_filter.lower()])

    if query != 'all' and query not in candidates:
        continue

    matched.append((
        team_id,
        audit_filter,
        str(site_audit.get('base_url_env') or '').strip(),
        str(site_audit.get('local_base_url') or '').strip(),
        str(site_audit.get('production_url') or '').strip(),
        str(site_audit.get('drupal_web_root') or '').strip(),
        str(site_audit.get('qa_artifacts_dir') or '').strip(),
        str(site_audit.get('route_regex') or '').strip(),
        str(site_audit.get('qa_permissions_path') or '').strip(),
        str(team.get('dev_agent') or '').strip(),
        str(team.get('pm_agent') or '').strip(),
    ))

if query != 'all' and not matched:
    print(f"ERROR: unknown site/team filter '{query}'", file=sys.stderr)
    print("Update org-chart/products/product-teams.json to onboard this team and enable site_audit.", file=sys.stderr)
    raise SystemExit(2)

for row in matched:
    print("\t".join(row))
PY
  } 2>&1)"; then
    echo "$selected_teams" >&2
    exit 2
  fi

  if [ -z "$selected_teams" ]; then
    echo "ERROR: no active site-audit teams found in $PRODUCT_TEAMS_JSON" >&2
    exit 2
  fi

  while IFS=$'\t' read -r team_id audit_filter base_url_env local_base_url production_url drupal_web_root qa_artifacts_dir route_regex qa_permissions_path dev_agent_id pm_agent_id; do
    [ -n "$team_id" ] || continue
    [ -n "$audit_filter" ] || audit_filter="$team_id"

    # Priority:
    #   ALLOW_PROD_QA=1: production_url (product registry) > base_url_env env override > local_base_url
    #   normal (no ALLOW_PROD_QA):  local_base_url (product registry) — both point to production on this server
    #   (env var not used in normal mode to prevent stale daemon-cached values overriding local_base_url)
    if [ "$ALLOW_PROD_QA" = "1" ]; then
      if [ -n "$production_url" ]; then
        base_url="$production_url"
      elif [ -n "$base_url_env" ]; then
        env_value="${!base_url_env-}"
        base_url="${env_value:-$local_base_url}"
      else
        base_url="$local_base_url"
      fi
    elif [ -n "$local_base_url" ]; then
      base_url="$local_base_url"
    else
      base_url="https://forseti.life"
    fi

    assert_not_prod_base_url "$audit_filter" "$base_url"
    if run_site \
      "$audit_filter" \
      "$base_url" \
      "$drupal_web_root" \
      "$qa_artifacts_dir" \
      "$route_regex" \
      "$qa_permissions_path" \
      "$dev_agent_id" \
      "$pm_agent_id" \
      "$team_id"; then
      echo "OK: $audit_filter audit completed at $ts"
    else
      echo "ERROR: $audit_filter audit failed at $ts (exit $?); continuing to next site" >&2
    fi
  done <<<"$selected_teams"

  echo "OK: site audits completed at $ts"
}

if [ "${BASH_SOURCE[0]}" = "$0" ]; then
  main "$@"
fi
