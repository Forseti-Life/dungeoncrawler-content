#!/usr/bin/env bash
set -euo pipefail

# Consume replies entered in Drupal UI and convert them into HQ inbox items.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"
# shellcheck source=lib/site-paths.sh
. "$ROOT_DIR/scripts/lib/site-paths.sh"

FORSITI_SITE_DIR="${FORSITI_SITE_DIR:-/var/www/html/forseti}"
DRUSH_BIN="${FORSITI_SITE_DIR}/vendor/bin/drush"

if [ ! -x "$DRUSH_BIN" ]; then
  echo "Missing drush: $DRUSH_BIN" >&2
  exit 1
fi

active_ceo_agent() {
  local preferred="${ORCHESTRATOR_CEO_AGENT:-}"
  if [ -n "$preferred" ] && [[ "$preferred" == ceo-copilot* ]]; then
    if [ "$(./scripts/is-agent-paused.sh "$preferred" 2>/dev/null || echo false)" != "true" ]; then
      echo "$preferred"
      return
    fi
  fi
  while IFS= read -r id; do
    [ -n "$id" ] || continue
    [[ "$id" == ceo-copilot* ]] || continue
    if [ "$(./scripts/is-agent-paused.sh "$id" 2>/dev/null || echo false)" != "true" ]; then
      echo "$id"
      return
    fi
  done < <(sed -n 's/^[[:space:]]*-[[:space:]]id:[[:space:]]*\([^[:space:]]\+\)[[:space:]]*$/\1/p' org-chart/agents/agents.yaml 2>/dev/null)
  echo "ceo-copilot"
}

CEO_AGENT="$(active_ceo_agent)"

json="$(
  (cd "$FORSITI_SITE_DIR" && "$DRUSH_BIN" -q php:eval '
    $rows = \Drupal::database()->select("copilot_agent_tracker_replies","r")
      ->fields("r", ["id","to_agent_id","in_reply_to","message","created"])
      ->condition("consumed", 0)
      ->orderBy("created", "ASC")
      ->range(0, 25)
      ->execute()
      ->fetchAll();
    print(json_encode($rows));
  ')
)"

result="$(
  python3 - "$json" "$CEO_AGENT" <<'PY'
import json, re, sys, time
from pathlib import Path

data = json.loads(sys.argv[1] or "[]")
ceo_agent = (sys.argv[2] or "ceo-copilot").strip() or "ceo-copilot"
root = Path("/home/ubuntu/forseti.life/copilot-hq")
ids=[]
resolved=[]
mapping={}

# Only allow replies to configured agent "seats".
agents_file = root / "org-chart" / "agents" / "agents.yaml"
configured = set()
if agents_file.exists():
  for ln in agents_file.read_text(encoding="utf-8", errors="ignore").splitlines():
    m = re.match(r"^\s*-\s+id:\s*(\S+)\s*$", ln)
    if m:
      configured.add(m.group(1).strip())

for r in data:
    rid = int(r.get("id"))
    to_agent = (r.get("to_agent_id") or "").strip()
    in_reply_to = (r.get("in_reply_to") or "").strip()
    msg = (r.get("message") or "").rstrip()
    if not to_agent or not msg:
        continue

    intended = to_agent
    if configured and to_agent not in configured:
        # Route unknown agent ids to CEO triage rather than creating new sessions/<id>.
        to_agent = ceo_agent

    slug = re.sub(r'[^A-Za-z0-9._-]+', '-', in_reply_to)[:50] or f"compose-{rid}"
    item = f"{time.strftime('%Y%m%d')}-reply-keith-{slug}-{rid}"
    inbox_dir = root / "sessions" / to_agent / "inbox" / item
    inbox_dir.mkdir(parents=True, exist_ok=True)
    (inbox_dir / "command.md").write_text(
        "- command: |\n"
        f"    Reply from Keith (in_reply_to: {in_reply_to})\n\n"
        f"    Tracking: drupal_reply_id={rid}\n"
        f"    HQ item: {item}\n\n"
        + (f"    NOTE: Original to_agent_id was '{intended}' (not a configured agent seat); routed to {ceo_agent} for triage.\n\n" if intended != to_agent else "")
        + "    " + msg.replace("\n", "\n    ") + "\n",
        encoding="utf-8",
    )
    (inbox_dir / "roi.txt").write_text("5\n", encoding="utf-8")
    ids.append(str(rid))
    mapping[str(rid)] = item
    if in_reply_to:
        resolved.append(in_reply_to)

print("IDS=" + " ".join(ids))
print("RESOLVED=" + " ".join(resolved))
print("MAP=" + json.dumps(mapping, separators=(',',':')))
PY
)"

ids="$(printf '%s\n' "$result" | sed -n 's/^IDS=//p' | head -n1)"
resolved_items="$(printf '%s\n' "$result" | sed -n 's/^RESOLVED=//p' | head -n1)"
map_json="$(printf '%s\n' "$result" | sed -n 's/^MAP=//p' | head -n1)"

if [ -z "$ids" ]; then
  exit 0
fi

now="$(date +%s)"
(cd "$FORSITI_SITE_DIR" && MAP_JSON="$map_json" "$DRUSH_BIN" -q php:eval '
  $ids = preg_split("/\s+/", trim("'"$ids"'"));
  $map = json_decode(getenv("MAP_JSON") ?: "{}", TRUE) ?: [];
  $now = (int) '"$now"';
  foreach ($ids as $id) {
    if ($id === "") { continue; }
    $hq_item_id = (string) ($map[$id] ?? "");
    \Drupal::database()->update("copilot_agent_tracker_replies")
      ->fields(["consumed" => 1, "consumed_at" => $now, "hq_item_id" => $hq_item_id])
      ->condition("id", (int) $id)
      ->execute();
  }
')

echo "Consumed replies: $ids"

# Archive resolved CEO inbox items (best-effort).
if [ -n "$resolved_items" ]; then
  for item in $resolved_items; do
    src="sessions/${CEO_AGENT}/inbox/${item}"
    if [ -d "$src" ]; then
      dest_dir="sessions/${CEO_AGENT}/artifacts/resolved"
      mkdir -p "$dest_dir"
      mv "$src" "${dest_dir}/${item}-$(date +%s)" 2>/dev/null || true
    fi
  done
fi
