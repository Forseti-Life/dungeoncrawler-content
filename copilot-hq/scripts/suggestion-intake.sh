#!/usr/bin/env bash
# suggestion-intake.sh — Pull new community_suggestion nodes from Drupal into PM inbox
#
# Usage:
#   ./scripts/suggestion-intake.sh [site]      # site defaults to "forseti"
#
# What it does:
#   1. Queries Drupal for community_suggestion nodes with status = "new"
#   2. Marks each queried suggestion as "under_review" in Drupal
#   3. Writes a PM inbox batch item: sessions/pm-<site>/inbox/<date>-suggestion-intake/
#   4. Each suggestion gets its own sub-file for individual triage
#
# PM then reviews the inbox item and uses suggestion-triage.sh to accept/defer/decline each one.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SITE="${1:-forseti}"
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
        "/home/ubuntu/forseti.life/sites/forseti"
        "/home/ubuntu/forseti.life/sites/forseti"
      )
      ;;
    dungeoncrawler)
      candidates+=(
        "/var/www/html/dungeoncrawler"
        "/home/ubuntu/forseti.life/sites/dungeoncrawler"
        "/home/ubuntu/forseti.life/sites/dungeoncrawler"
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
PM_AGENT="pm-${SITE}"
INBOX_DIR="sessions/${PM_AGENT}/inbox"
DATE_TAG="$(date +%Y%m%d-%H%M%S)"
BATCH_ITEM="${INBOX_DIR}/${DATE_TAG}-suggestion-intake"

if [ ! -f "$DRUSH" ]; then
  echo "ERROR: drush not found at $DRUSH" >&2
  exit 1
fi

echo "[suggestion-intake] Querying new suggestions for site: $SITE"
echo "[suggestion-intake] Drupal root: $DRUPAL_ROOT"

# Query new suggestions via drush php-eval
SUGGESTIONS_JSON="$(cd "$DRUPAL_ROOT" && vendor/bin/drush php:eval '
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
' 2>/dev/null)"

COUNT="$(echo "$SUGGESTIONS_JSON" | python3 -c "import json,sys; print(len(json.loads(sys.stdin.read())))")"

if [ "$COUNT" -eq 0 ]; then
  echo "[suggestion-intake] No new suggestions found. Nothing to do."
  exit 0
fi

echo "[suggestion-intake] Found $COUNT new suggestion(s). Writing PM inbox item..."
mkdir -p "$BATCH_ITEM"

# Write the batch README
python3 - "$SUGGESTIONS_JSON" "$BATCH_ITEM" "$COUNT" "$SITE" "$DATE_TAG" <<'PY'
import json, sys, pathlib, textwrap

suggestions = json.loads(sys.argv[1])
batch_dir = pathlib.Path(sys.argv[2])
count = int(sys.argv[3])
site = sys.argv[4]
date_tag = sys.argv[5]

category_labels = {
    "safety_feature": "Safety Feature",
    "partnership": "Partnership Opportunity",
    "community_initiative": "Community Initiative",
    "technical_improvement": "Technical Improvement",
    "content_update": "Content Update",
    "general_feedback": "General Feedback",
    "other": "Other",
}

# Write batch README
readme = f"""# Suggestion Intake Batch — {date_tag}

**Site:** {site}.life  
**New suggestions:** {count}  
**Status:** Pending PM triage  

## What to do

For each suggestion below:
1. Review summary + original message
2. Update triage decision in `triage/NID-triage.md`
3. Run: `./scripts/suggestion-triage.sh {site} <nid> <accept|defer|decline|escalate> [feature-id]`
   - `accept`  → creates `features/<feature-id>/feature.md`, marks Drupal node `in_progress`
   - `defer`   → marks Drupal node `deferred`, queued for next cycle
   - `decline` → marks Drupal node `declined`
  - `escalate`→ routes to board-security review queue, keeps node `under_review`

## Mandatory security gate

If a suggestion clearly asks for security abuse, release-gate/integrity bypass, intentionally destructive behavior,
or a major architecture replatform/rewrite,
do not accept it at PM level. Use `escalate` for human board review first.
Normal product improvements should continue through standard PM triage.

## Quick summary table

| # | NID | Category | Title |
|---|-----|----------|-------|
"""
for i, s in enumerate(suggestions, 1):
    cat = category_labels.get(s["category"], s["category"])
    title_short = s["title"][:60] + ("..." if len(s["title"]) > 60 else "")
    readme += f'| {i} | {s["nid"]} | {cat} | {title_short} |\n'

readme += "\n## Suggestions (detail)\n\n"
for s in suggestions:
    cat = category_labels.get(s["category"], s["category"])
    conv_link = f"Node {s['conv_nid']}" if s["conv_nid"] else "N/A"
    readme += f"""---
### NID {s['nid']}: {s['title']}

- **Created:** {s['created']}
- **Category:** {cat}
- **Conversation:** {conv_link}
- **Drupal URL:** /node/{s['nid']}/edit

**Summary:**
{textwrap.fill(s['summary'], 100)}

**Original user message:**
{textwrap.fill(s['original_msg'], 100)}

**Triage:** _(see triage/NID-{s['nid']}-triage.md)_

"""

(batch_dir / "README.md").write_text(readme, encoding="utf-8")

# Build cross-site keyword map from product-teams.json (teams other than current site)
import re as _re

def _load_cross_site_keywords(current_site_id):
    hq_root = pathlib.Path.cwd()
    product_teams_path = hq_root / "org-chart" / "products" / "product-teams.json"
    if not product_teams_path.exists():
        return {}
    data = json.loads(product_teams_path.read_text(encoding="utf-8"))
    teams = data.get("teams", []) if isinstance(data, dict) else data

    # Determine the current site's canonical domain so co-hosted teams are excluded
    current_domain = None
    for team in teams:
        if str(team.get("id") or "").strip().lower() == current_site_id.lower():
            current_domain = str(team.get("site") or "").strip().lower()
            break

    cross_site = {}  # team_id -> list of keywords (longest first)
    for team in teams:
        tid = str(team.get("id") or "").strip().lower()
        tsite = str(team.get("site") or "").strip().lower()
        # Skip current site team and any team that lives on the same domain
        if not tid or tid == current_site_id.lower():
            continue
        if current_domain and tsite == current_domain:
            continue
        keywords = {tid}
        if tsite:
            keywords.add(tsite)
            keywords.add(tsite.replace(".life", ""))
        for a in (team.get("aliases") or []):
            a = str(a).strip().lower()
            if a and len(a) >= 4:  # skip very short aliases (avoid false positives)
                keywords.add(a)
        cross_site[tid] = sorted(keywords, key=len, reverse=True)
    return cross_site

_cross_site_keywords = _load_cross_site_keywords(site)

def _detect_cross_site_mentions(text, cross_site_map):
    """Return list of (team_id, keyword) for the first keyword match per team."""
    text_lower = text.lower()
    found = []
    for tid, keywords in cross_site_map.items():
        for kw in keywords:
            if _re.search(r'(?<![a-z0-9])' + _re.escape(kw) + r'(?![a-z0-9])', text_lower):
                found.append((tid, kw))
                break  # one match per team is sufficient
    return found

# Write individual triage stubs
triage_dir = batch_dir / "triage"
triage_dir.mkdir(exist_ok=True)

for s in suggestions:
    cat = category_labels.get(s["category"], s["category"])
    triage_file = triage_dir / f"NID-{s['nid']}-triage.md"

    # Detect cross-site mentions in title + summary + original message
    combined_text = " ".join([
        s.get("title") or "",
        s.get("summary") or "",
        s.get("original_msg") or "",
    ])
    cross_site_mentions = _detect_cross_site_mentions(combined_text, _cross_site_keywords)

    if cross_site_mentions:
        mention_lines = "\n".join(
            f"  - `{kw}` → belongs to: **{tid}**"
            for tid, kw in cross_site_mentions
        )
        cross_site_warning = f"""## ⚠ CROSS-SITE WARNING

This suggestion references content from a site other than **{site}**. Verify attribution before accepting or routing.

**Detected references to other sites:**
{mention_lines}

**Action required:**
- [ ] Confirm this suggestion belongs to **{site}** (not the detected site above)
- [ ] If misfiled: re-run `./scripts/suggestion-intake.sh <correct-site>` with the correct site, or move this triage item to the correct PM inbox manually
- [ ] If a cross-site feature request: escalate to the owning PM seat for that site

---

"""
    else:
        cross_site_warning = ""

    triage_file.write_text(f"""{cross_site_warning}# Triage: NID {s['nid']} — {s['title']}

- **Category:** {cat}
  - **Decision:** [ ] accept  [ ] defer  [ ] decline  [ ] escalate
- **Feature ID** (if accept): {site}-  
- **Priority** (if accept): P0 | P1 | P2
- **PM notes:**

## Rationale

_Why accept/defer/decline? Mission alignment? Scope fit? Effort estimate?_

## Mission alignment check

Does this align with: "Democratize and decentralize internet services by building
community-managed versions of core systems for scientific, technology-focused, and tolerant people."

- [ ] Directly advances mission
- [ ] Neutral / infrastructure
- [ ] Does not align (decline)

## Security / integrity gate (required)

- [ ] No security abuse pattern (auth bypass, secret exposure, exploit primitive)
- [ ] No release-integrity bypass (skip QA/tests/approval, disable logging/guardrails)
- [ ] No stability-destructive action (data destruction, crash/DoS pattern)
- [ ] If any box above is uncertain or false → **escalate** for board review

""", encoding="utf-8")

print(f"Written {len(suggestions)} triage stubs to {triage_dir}")
PY

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

# Write ROI estimate for the PM agent
echo "3" > "$BATCH_ITEM/roi.txt"

echo "[suggestion-intake] Done."
echo "[suggestion-intake] Inbox item: $BATCH_ITEM"
echo "[suggestion-intake] $COUNT suggestion(s) ready for PM triage."
