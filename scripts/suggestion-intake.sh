#!/usr/bin/env bash
# suggestion-intake.sh — Pull new community_suggestion nodes from Drupal into feature_request_intake
#
# Usage:
#   ./scripts/suggestion-intake.sh [site]      # site defaults to "forseti"
#   FORCE_RESEED_NIDS=7 ./scripts/suggestion-intake.sh forseti
#
# What it does:
#   1. Queries Drupal for community_suggestion nodes with status = "new"
#   2. Marks each queried suggestion as "under_review" in Drupal
#   3. Seeds one `feature_request_intake` entrypoint inbox item per suggestion
#   4. Lets the new flow own review, product-team matching, BA/PM routing, and delivery launch
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-forseti}"
FORCE_RESEED_NIDS="${FORCE_RESEED_NIDS:-}"
LOCK_FILE="/var/tmp/suggestion-intake-${SITE}.lock"

mkdir -p "$(dirname "$LOCK_FILE")"
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "[suggestion-intake] Another run is already in progress for site: $SITE"
  exit 0
fi

resolve_drupal_root() {
  local site="$1"
  local configured_roots
  configured_roots="$(python3 - "$site" <<'PY'
import json
import pathlib
import sys

site = (sys.argv[1] or '').strip().lower()
p = pathlib.Path('org-chart/products/product-teams.json')
if not p.exists():
    raise SystemExit(0)

data = json.loads(p.read_text(encoding='utf-8'))
teams = data.get('teams', data) if isinstance(data, dict) else data

def aliases_for(team):
    vals = set()
    tid = str(team.get('id') or '').strip().lower()
    tsite = str(team.get('site') or '').strip().lower()
    if tid:
        vals.add(tid)
    if tsite:
        vals.add(tsite)
        vals.add(tsite.replace('.life', ''))
    for a in (team.get('aliases') or []):
        a = str(a).strip().lower()
        if a:
            vals.add(a)
    return vals

for t in teams:
    if site not in aliases_for(t):
        continue

    roots = []
    drupal_root = str(t.get('drupal_root') or '').strip()
    if drupal_root:
        roots.append(drupal_root)

    site_audit = t.get('site_audit') or {}
    drupal_web_root = str(site_audit.get('drupal_web_root') or '').strip()
    if drupal_web_root:
        if drupal_web_root.endswith('/web'):
            roots.append(drupal_web_root[:-4])
        else:
            roots.append(drupal_web_root)

    for r in roots:
        print(r)
    break
PY
)"

  local -a candidates=()
  if [ -n "$configured_roots" ]; then
    while IFS= read -r line; do
      [ -n "$line" ] && candidates+=("$line")
    done <<< "$configured_roots"
  fi

  case "$site" in
    forseti)
      candidates+=(
        "/var/www/html/forseti"
      )
      ;;
    dungeoncrawler)
      candidates+=(
        "/var/www/html/dungeoncrawler"
      )
      ;;
    *)
      ;;
  esac

  local root
  for root in "${candidates[@]}"; do
    [ -n "$root" ] || continue
    if [ -x "$root/vendor/bin/drush" ]; then
      printf '%s\n' "$root"
      return 0
    fi
  done

  return 1
}

if ! DRUPAL_ROOT="$(resolve_drupal_root "$SITE")"; then
  echo "ERROR: could not resolve Drupal root for site '$SITE' (drush not found)." >&2
  exit 1
fi

# ── drupal_web_root validation (GAP-DC-RB-IR-02) ─────────────────────────────
# Read drupal_web_root from product-teams.json and verify it is reachable before
# doing any work. A stale/wrong path silently breaks all subsequent drush calls.
DRUPAL_WEB_ROOT="$(python3 - "$SITE" <<'PY'
import json, sys, pathlib
site = (sys.argv[1] or '').strip().lower()
p = pathlib.Path('org-chart/products/product-teams.json')
if not p.exists():
    sys.exit(0)
data = json.loads(p.read_text(encoding='utf-8'))
for t in (data.get('teams') or []):
    aliases = {str(t.get('id','')).lower()}
    aliases.update(str(a).lower() for a in (t.get('aliases') or []))
    if site not in aliases:
        continue
    wroot = str((t.get('site_audit') or {}).get('drupal_web_root') or '').strip()
    print(wroot)
    break
PY
)"

if [ -n "$DRUPAL_WEB_ROOT" ]; then
  if [ ! -d "$DRUPAL_WEB_ROOT" ]; then
    echo "ERROR: drupal_web_root not reachable: $DRUPAL_WEB_ROOT" >&2
    echo "  Site: $SITE — update site_audit.drupal_web_root in org-chart/products/product-teams.json" >&2
    mkdir -p "$ROOT_DIR/tmp/config-validation-failures"
    printf 'site: %s\ndrupal_web_root: %s\nerror: path does not exist\ntimestamp: %s\n' \
      "$SITE" "$DRUPAL_WEB_ROOT" "$(date -Iseconds)" \
      > "$ROOT_DIR/tmp/config-validation-failures/$(date +%Y%m%d-%H%M%S)-${SITE}.txt"
    exit 1
  fi
fi

DRUSH="$DRUPAL_ROOT/vendor/bin/drush"
FLOW_OWNER="ceo-copilot-2"
INBOX_DIR="sessions/${FLOW_OWNER}/inbox"
DATE_TAG="$(date +%Y%m%d-%H%M%S)"

if [ ! -f "$DRUSH" ]; then
  echo "ERROR: drush not found at $DRUSH" >&2
  exit 1
fi

echo "[suggestion-intake] Querying new suggestions for site: $SITE"
echo "[suggestion-intake] Drupal root: $DRUPAL_ROOT"

# Query new suggestions via drush php-eval
QUERY_ERR_FILE="$(mktemp)"
set +e
SUGGESTIONS_JSON="$(cd "$DRUPAL_ROOT" && vendor/bin/drush php:eval '
try {
  $query = \Drupal::entityQuery("node")
    ->condition("type", "community_suggestion")
    ->condition("field_suggestion_status", "new")
    ->accessCheck(FALSE)
    ->sort("created", "ASC")
    ->execute();
  $nodes = \Drupal\node\Entity\Node::loadMultiple($query);
  $results = [];
  foreach ($nodes as $node) {
    $results[] = [
      "nid"          => $node->id(),
      "title"        => $node->getTitle(),
      "created"      => date("Y-m-d H:i", $node->getCreatedTime()),
      "uid"          => $node->getOwnerId(),
      "summary"      => $node->get("field_suggestion_summary")->value ?? "",
      "original_msg" => $node->get("field_original_message")->value ?? "",
      "category"     => $node->get("field_suggestion_category")->value ?? "other",
      "conv_nid"     => $node->get("field_conversation_reference")->target_id ?? null,
    ];
  }
  echo json_encode($results);
} catch (\Exception $e) {
  // If the community_suggestion content type or field is not implemented, return empty list
  echo "[]";
}
' 2>"$QUERY_ERR_FILE")"
QUERY_RC=$?
set -e

if [ "$QUERY_RC" -ne 0 ]; then
  echo "ERROR: suggestion-intake query failed for site '$SITE'." >&2
  sed -n '1,40p' "$QUERY_ERR_FILE" >&2 || true
  rm -f "$QUERY_ERR_FILE"
  exit 1
fi

# Empty output is not an error - it means no suggestions or no content type
if [ -z "${SUGGESTIONS_JSON//[$' \t\r\n\[\]']/}" ]; then
  # Try to parse it as JSON to make sure it's valid
  COUNT="$(printf '%s' "$SUGGESTIONS_JSON" | python3 -c "import json, sys; print(len(json.loads(sys.stdin.read())))" 2>/dev/null || echo '0')"
  if [ "$COUNT" = "0" ]; then
    echo "[suggestion-intake] No new suggestions found for site: $SITE"
    rm -f "$QUERY_ERR_FILE"
    exit 0
  fi
fi

COUNT="$(python3 - "$SUGGESTIONS_JSON" <<'PY'
import json
import sys

raw = sys.argv[1]
try:
    data = json.loads(raw)
except json.JSONDecodeError as exc:
    print(f"ERROR: invalid suggestion JSON: {exc}", file=sys.stderr)
    raise SystemExit(1)

if not isinstance(data, list):
    print("ERROR: suggestion query did not return a JSON list", file=sys.stderr)
    raise SystemExit(1)

print(len(data))
PY
)"
rm -f "$QUERY_ERR_FILE"

if [ "$COUNT" -eq 0 ]; then
  echo "[suggestion-intake] No new suggestions found. Nothing to do."
  exit 0
fi

echo "[suggestion-intake] Found $COUNT new suggestion(s). Seeding feature_request_intake items..."

SEED_RESULT_JSON="$(python3 - "$SUGGESTIONS_JSON" "$INBOX_DIR" "$SITE" "$DATE_TAG" "$FLOW_OWNER" "$FORCE_RESEED_NIDS" <<'PY'
import json
import pathlib
import re
import sys
import textwrap

suggestions = json.loads(sys.argv[1])
inbox_root = pathlib.Path(sys.argv[2])
site = sys.argv[3]
date_tag = sys.argv[4]
flow_owner = sys.argv[5]
force_reseed_raw = sys.argv[6]
force_reseed_nids = {value.strip() for value in force_reseed_raw.split(",") if value.strip()}

category_labels = {
    "safety_feature": "Safety Feature",
    "partnership": "Partnership Opportunity",
    "community_initiative": "Community Initiative",
    "technical_improvement": "Technical Improvement",
    "content_update": "Content Update",
    "general_feedback": "General Feedback",
    "other": "Other",
}

def slugify(value: str) -> str:
    slug = re.sub(r"[^A-Za-z0-9._-]+", "-", value).strip("-").lower()
    return slug or "item"

def load_cross_site_keywords(current_site_id: str) -> dict[str, list[str]]:
    product_teams_path = pathlib.Path.cwd() / "org-chart" / "products" / "product-teams.json"
    if not product_teams_path.exists():
        return {}
    data = json.loads(product_teams_path.read_text(encoding="utf-8"))
    teams = data.get("teams", []) if isinstance(data, dict) else data

    current_domain = None
    for team in teams:
        if str(team.get("id") or "").strip().lower() == current_site_id.lower():
            current_domain = str(team.get("site") or "").strip().lower()
            break

    cross_site: dict[str, list[str]] = {}
    for team in teams:
        tid = str(team.get("id") or "").strip().lower()
        tsite = str(team.get("site") or "").strip().lower()
        if not tid or tid == current_site_id.lower():
            continue
        if current_domain and tsite == current_domain:
            continue
        keywords = {tid}
        if tsite:
            keywords.add(tsite)
            keywords.add(tsite.replace(".life", ""))
        for alias in (team.get("aliases") or []):
            alias = str(alias).strip().lower()
            if alias and len(alias) >= 4:
                keywords.add(alias)
        cross_site[tid] = sorted(keywords, key=len, reverse=True)
    return cross_site

def detect_cross_site_mentions(text: str, cross_site_map: dict[str, list[str]]) -> list[tuple[str, str]]:
    text_lower = text.lower()
    found: list[tuple[str, str]] = []
    for team_id, keywords in cross_site_map.items():
        for keyword in keywords:
            if re.search(r"(?<![a-z0-9])" + re.escape(keyword) + r"(?![a-z0-9])", text_lower):
                found.append((team_id, keyword))
                break
    return found

def item_exists(marker: str) -> bool:
    seat_root = pathlib.Path.cwd() / "sessions" / flow_owner
    for bucket in ("inbox", "outbox", "artifacts"):
        root = seat_root / bucket
        if not root.exists():
            continue
        for path in root.iterdir():
            if marker in path.name:
                return True
    return False

def next_rerun_suffix(site_id: str, nid: str) -> int:
    pattern = re.compile(rf"\bSuggestion NID:\s*{re.escape(nid)}\b")
    run_id_pattern = re.compile(rf"- Flow run id:\s*suggestion-{re.escape(site_id)}-nid-{re.escape(nid)}(?:-r([0-9]+))?\b")
    seat_root = pathlib.Path.cwd() / "sessions" / flow_owner
    max_suffix = 1
    for bucket in ("inbox", "outbox", "artifacts"):
        root = seat_root / bucket
        if not root.exists():
            continue
        for path in root.rglob("command.md"):
            try:
                text = path.read_text(encoding="utf-8")
            except OSError:
                continue
            if not pattern.search(text):
                continue
            match = run_id_pattern.search(text)
            if not match:
                continue
            suffix = int(match.group(1) or "1")
            max_suffix = max(max_suffix, suffix)
    return max_suffix + 1

cross_site_keywords = load_cross_site_keywords(site)
created = 0
skipped = 0
forced = 0

for suggestion in suggestions:
    nid = str(suggestion.get("nid") or "").strip()
    title = str(suggestion.get("title") or "").strip()
    summary = str(suggestion.get("summary") or "").strip()
    original = str(suggestion.get("original_msg") or "").strip()
    category = category_labels.get(str(suggestion.get("category") or "other"), str(suggestion.get("category") or "other"))
    conv_nid = suggestion.get("conv_nid")
    created_at = str(suggestion.get("created") or "").strip()

    marker = f"feature-request-intake-{site}-nid-{nid}"
    force_reseed = nid in force_reseed_nids
    item_name = f"{date_tag}-flow-{marker}-{slugify(title)[:40]}".rstrip("-")
    item_dir = inbox_root / item_name
    if not force_reseed and (item_exists(marker) or item_dir.exists()):
        skipped += 1
        continue
    if force_reseed:
        forced += 1

    combined_text = " ".join([title, summary, original])
    cross_site_mentions = detect_cross_site_mentions(combined_text, cross_site_keywords)
    cross_site_section = ""
    if cross_site_mentions:
        mentions = "\n".join(f"- `{keyword}` appears to reference product team `{team_id}`" for team_id, keyword in cross_site_mentions)
        cross_site_section = (
            "\n## Cross-site warning\n\n"
            "This suggestion mentions another site or product alias. Do not assume the source site owns it without review.\n\n"
            f"{mentions}\n"
        )

    run_id = f"suggestion-{site}-nid-{nid}"
    reseed_note = ""
    if force_reseed:
        rerun_suffix = next_rerun_suffix(site, nid)
        run_id = f"{run_id}-r{rerun_suffix}"
        reseed_note = (
            "\n## Reseed note\n\n"
            f"- This item was force-reseeded from Drupal `new` state after an earlier intake run for suggestion NID {nid} produced stale or incorrect flow context.\n"
            f"- Use this run (`{run_id}`) as the authoritative retry. Do not inherit scope or assumptions from prior `suggestion-{site}-nid-{nid}` runs without re-reading the original user message.\n"
        )

    conv_line = f"- Source conversation node: {conv_nid}" if conv_nid is not None else "- Source conversation node: n/a"
    item_dir.mkdir(parents=True, exist_ok=True)
    command = (
        f"- Flow id: feature_request_intake\n"
        f"- Flow run id: {run_id}\n"
        f"- Flow node: Receive Feature Request\n"
        f"- Flow owner seat: {flow_owner}\n"
        f"\n"
        f"# Incoming feature request from community suggestion\n\n"
        f"- Source system: Drupal community_suggestion\n"
        f"- Source site: {site}\n"
        f"- Suggestion NID: {nid}\n"
        f"{conv_line}\n"
        f"- Suggestion category: {category}\n"
        f"- Created at: {created_at or 'unknown'}\n"
        f"- Suggested product team: {site}\n"
        f"\n"
        f"## Request summary\n\n"
        f"{textwrap.fill(summary or title or '(no summary provided)', 100)}\n"
        f"\n"
        f"## Suggestion title\n\n"
        f"{title or '(untitled suggestion)'}\n"
        f"\n"
        f"## Original user message\n\n"
        f"{textwrap.fill(original or '(not captured)', 100)}\n"
        f"\n"
        f"## Intake notes\n\n"
        f"- This request was automatically seeded by `scripts/suggestion-intake.sh`.\n"
        f"- Legacy PM-only suggestion triage has been retired; use the `feature_request_intake` flow to review, clarify, defer, reject, or approve this request.\n"
        f"- If approved, the intake flow should decide whether to materialize or update a backlog feature artifact before launching delivery.\n"
        f"- Drupal node edit URL: /node/{nid}/edit\n"
        f"{reseed_note}"
        f"{cross_site_section}"
    )
    (item_dir / "command.md").write_text(command, encoding="utf-8")
    (item_dir / "roi.txt").write_text("30\n", encoding="utf-8")
    created += 1

print(json.dumps({"created": created, "skipped": skipped, "forced": forced, "total": len(suggestions)}))
PY
)"

# Mark suggestions as under_review in Drupal
echo "[suggestion-intake] Marking suggestions as under_review in Drupal..."
NIDS_JSON="$(echo "$SUGGESTIONS_JSON" | python3 -c "import json,sys; print(json.dumps([s['nid'] for s in json.loads(sys.stdin.read())]))")"
cd "$DRUPAL_ROOT" && vendor/bin/drush php:eval "
\$nids = json_decode('${NIDS_JSON}', true);
foreach (\$nids as \$nid) {
  \$node = \Drupal\node\Entity\Node::load(\$nid);
  if (\$node) {
    \$node->set('field_suggestion_status', 'under_review');
    \$node->save();
  }
}
echo count(\$nids) . ' nodes updated to under_review';
" 2>/dev/null && echo "" || echo "[suggestion-intake] WARN: could not update Drupal status (offline?)"

cd "$ROOT_DIR"

CREATED_COUNT="$(printf '%s' "$SEED_RESULT_JSON" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('created', 0))")"
SKIPPED_COUNT="$(printf '%s' "$SEED_RESULT_JSON" | python3 -c "import json,sys; print(json.loads(sys.stdin.read()).get('skipped', 0))")"

echo "[suggestion-intake] Done."
echo "[suggestion-intake] Created $CREATED_COUNT feature_request_intake item(s); skipped $SKIPPED_COUNT duplicate/already-seeded suggestion(s)."
echo "[suggestion-intake] Flow owner inbox: sessions/${FLOW_OWNER}/inbox/"
