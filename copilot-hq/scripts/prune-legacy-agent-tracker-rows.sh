#!/usr/bin/env bash
set -euo pipefail

# Prune legacy per-item "agent ids" from Forseti copilot_agent_tracker tables.
#
# These were accidentally published during a prior bug and look like:
#   pm-foo-20260220-product-...
#   ...-reply-keith-...
#   ...-needs-...
#   ...-clarify-escalation-...
#
# This script deletes ONLY rows that are not configured agent seats in HQ.

HQ_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$HQ_ROOT"
# shellcheck source=lib/site-paths.sh
. "$HQ_ROOT/scripts/lib/site-paths.sh"

FORSITI_SITE_DIR="${FORSITI_SITE_DIR:-/var/www/html/forseti}"
DRUSH_BIN="${FORSITI_SITE_DIR}/vendor/bin/drush"

if [ ! -x "$DRUSH_BIN" ]; then
  echo "Missing drush: $DRUSH_BIN" >&2
  exit 1
fi

configured_ids_json="$(
  python3 - <<'PY'
import json, re
from pathlib import Path
p = Path('org-chart/agents/agents.yaml')
ids=[]
if p.exists():
    for ln in p.read_text(encoding='utf-8', errors='ignore').splitlines():
        m = re.match(r'^\s*-\s+id:\s*(\S+)\s*$', ln)
        if m:
            ids.append(m.group(1))
print(json.dumps(sorted(set(ids))))
PY
)"

# Dry-run shows counts only.
DRY_RUN="${1:-}"

( cd "$FORSITI_SITE_DIR" && CONFIGURED_IDS_JSON="$configured_ids_json" "$DRUSH_BIN" -q php:eval '
  $keep = json_decode(getenv("CONFIGURED_IDS_JSON") ?: "[]", TRUE) ?: [];
  $keep = array_fill_keys($keep, TRUE);

  $conn = \Drupal::database();

  // Candidate legacy patterns.
  $like = [
    "% -reply-keith-%" => "reply",
    "% -needs-%" => "needs",
    "% -clarify-escalation-%" => "clarify",
  ];

  // Fetch all agent ids once.
  $agent_ids = $conn->select("copilot_agent_tracker_agents", "a")
    ->fields("a", ["agent_id"])
    ->execute()
    ->fetchCol();

  $legacy = [];
  foreach ($agent_ids as $aid) {
    $aid = (string) $aid;
    if ($aid === "") { continue; }
    if (isset($keep[$aid])) { continue; }

    // Any -YYYYMMDD segment marks it as legacy.
    if (preg_match("/-\\d{8}(-|$)/", $aid)) {
      $legacy[$aid] = TRUE;
      continue;
    }
    if (strpos($aid, "-reply-keith-") !== FALSE || strpos($aid, "-needs-") !== FALSE || strpos($aid, "-clarify-escalation-") !== FALSE) {
      $legacy[$aid] = TRUE;
      continue;
    }
  }

  $legacy_ids = array_keys($legacy);
  sort($legacy_ids);

  $count = count($legacy_ids);
  print("Legacy agent rows detected (not configured seats): {$count}\n");

  if (getenv("DRY_RUN") === "1") {
    $sample = array_slice($legacy_ids, 0, 30);
    foreach ($sample as $s) { print("- {$s}\n"); }
    if ($count > 30) { print("(…truncated)\n"); }
    return;
  }

  if ($count === 0) {
    print("Nothing to delete.\n");
    return;
  }

  // Delete from agents table first; dependent tables (events) are keyed by agent_id in module storage.
  // If FK constraints exist, this may fail; in that case we should soft-delete instead.
  $deleted = 0;
  foreach ($legacy_ids as $aid) {
    $deleted += (int) $conn->delete("copilot_agent_tracker_agents")
      ->condition("agent_id", $aid)
      ->execute();
  }

  print("Deleted agent rows: {$deleted}\n");
' )
