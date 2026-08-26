#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


REPO_ROOT = Path(__file__).resolve().parent.parent
DRUPAL_ROOT = Path("/var/www/html/dungeoncrawler")
DRUSH_BIN = DRUPAL_ROOT / "vendor/bin/drush"
OUTPUT_ROOT = REPO_ROOT / "tmp" / "room-scene-hostility-rca"


def utc_stamp() -> str:
    return datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")


def parse_json_object(output: str, command: list[str]) -> dict[str, Any]:
    try:
        parsed = json.loads(output)
        if isinstance(parsed, dict):
            return parsed
    except json.JSONDecodeError:
        pass

    for index, ch in enumerate(output):
        if ch != "{":
            continue
        candidate = output[index:].strip()
        try:
            parsed = json.loads(candidate)
        except json.JSONDecodeError:
            continue
        if isinstance(parsed, dict):
            return parsed

    raise RuntimeError(f"Expected JSON object from {' '.join(command)}; got:\n{output}")


def run_drush_php_eval(code: str) -> dict[str, Any]:
    if not DRUSH_BIN.exists():
        raise RuntimeError(f"Expected drush at {DRUSH_BIN}.")
    command = [str(DRUSH_BIN), "php:eval", code]
    proc = subprocess.run(
        command,
        cwd=str(DRUPAL_ROOT),
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
    )
    output = (proc.stdout or "").strip()
    if proc.returncode != 0:
        raise RuntimeError(f"drush php:eval failed (rc={proc.returncode}): {' '.join(command)}\n{output}")
    if output == "":
        raise RuntimeError(f"drush php:eval returned empty output: {' '.join(command)}")
    return parse_json_object(output, command)


def resolve_campaign_id(explicit_campaign_id: int) -> int:
    if explicit_campaign_id > 0:
        return explicit_campaign_id

    payload = run_drush_php_eval(
        """
$row = \\Drupal::database()->select('dc_campaigns', 'c')
  ->fields('c', ['id'])
  ->orderBy('id', 'DESC')
  ->range(0, 1)
  ->execute()
  ->fetchAssoc();
if (!is_array($row) || !isset($row['id'])) {
  throw new \\RuntimeException('No campaign rows found in dc_campaigns.');
}
print json_encode(['campaign_id' => (int) $row['id']]);
"""
    )
    campaign_id = int(payload.get("campaign_id") or 0)
    if campaign_id <= 0:
        raise RuntimeError("Unable to resolve latest campaign id.")
    return campaign_id


def collect_snapshot(campaign_id: int, event_limit: int, relationship_limit: int) -> dict[str, Any]:
    code = f"""
$cid = {campaign_id};
$event_limit = {event_limit};
$relationship_limit = {relationship_limit};
$db = \\Drupal::database();
$schema = $db->schema();

$campaign = $db->select('dc_campaigns', 'c')
  ->fields('c')
  ->condition('id', $cid)
  ->range(0, 1)
  ->execute()
  ->fetchAssoc();
if (!is_array($campaign)) {{
  throw new \\RuntimeException('Campaign not found for id=' . $cid);
}}

$runtime_row = [];
if ($schema->tableExists('dc_campaign_runtime_state')) {{
  $runtime_row = $db->select('dc_campaign_runtime_state', 'rs')
    ->fields('rs')
    ->condition('campaign_id', $cid)
    ->orderBy('id', 'DESC')
    ->range(0, 1)
    ->execute()
    ->fetchAssoc();
  if (!is_array($runtime_row)) {{
    $runtime_row = [];
  }}
}}

$decode_state = static function (array $row): array {{
  foreach (['game_state', 'state_data', 'runtime_state'] as $field) {{
    $raw = $row[$field] ?? NULL;
    if (!is_string($raw) || trim($raw) === '') {{
      continue;
    }}
    $decoded = json_decode($raw, TRUE);
    if (is_array($decoded)) {{
      return $decoded;
    }}
  }}
  return [];
}};

$runtime_state = $decode_state($runtime_row);
$encounter_id = (int) ($runtime_state['encounter_id'] ?? 0);
$encounter = [];
if ($encounter_id > 0 && $schema->tableExists('combat_encounters')) {{
  $encounter = $db->select('combat_encounters', 'e')
    ->fields('e')
    ->condition('id', $encounter_id)
    ->range(0, 1)
    ->execute()
    ->fetchAssoc();
  if (!is_array($encounter)) {{
    $encounter = [];
  }}
}}

$recent_events = [];
if ($schema->tableExists('dc_campaign_log')) {{
  $rows = $db->select('dc_campaign_log', 'l')
    ->fields('l')
    ->condition('campaign_id', $cid)
    ->orderBy('id', 'DESC')
    ->range(0, $event_limit)
    ->execute()
    ->fetchAll(\\PDO::FETCH_ASSOC);
  foreach ($rows as $row) {{
    $recent_events[] = [
      'id' => (int) ($row['id'] ?? 0),
      'type' => (string) ($row['event_type'] ?? ($row['action_type'] ?? ($row['type'] ?? ''))),
      'actor' => (string) ($row['actor_ref'] ?? ($row['actor_id'] ?? '')),
      'target' => (string) ($row['target_ref'] ?? ($row['target_id'] ?? '')),
      'created' => $row['created'] ?? ($row['timestamp'] ?? NULL),
      'raw' => $row,
    ];
  }}
}}

$hostile_relationships = [];
if ($schema->tableExists('dc_campaign_relationships')) {{
  $query = $db->select('dc_campaign_relationships', 'r')
    ->fields('r')
    ->condition('campaign_id', $cid)
    ->condition('attitude', 'hostile');
  if ($schema->fieldExists('dc_campaign_relationships', 'updated')) {{
    $query->orderBy('updated', 'DESC');
  }}
  elseif ($schema->fieldExists('dc_campaign_relationships', 'changed')) {{
    $query->orderBy('changed', 'DESC');
  }}
  else {{
    $query->orderBy('id', 'DESC');
  }}
  $rows = $query
    ->range(0, $relationship_limit)
    ->execute()
    ->fetchAll(\\PDO::FETCH_ASSOC);
  foreach ($rows as $row) {{
    $hostile_relationships[] = $row;
  }}
}}

$payload = [
  'generated_at' => gmdate('c'),
  'campaign_id' => $cid,
  'campaign' => $campaign,
  'runtime_row' => $runtime_row,
  'runtime_state' => $runtime_state,
  'encounter' => $encounter,
  'recent_events' => $recent_events,
  'hostile_relationships' => $hostile_relationships,
];
print json_encode($payload, JSON_UNESCAPED_SLASHES);
"""
    return run_drush_php_eval(code)


def build_summary(snapshot: dict[str, Any]) -> dict[str, Any]:
    runtime_state = snapshot.get("runtime_state") if isinstance(snapshot.get("runtime_state"), dict) else {}
    encounter = snapshot.get("encounter") if isinstance(snapshot.get("encounter"), dict) else {}
    events = snapshot.get("recent_events") if isinstance(snapshot.get("recent_events"), list) else []
    hostile_relationships = snapshot.get("hostile_relationships") if isinstance(snapshot.get("hostile_relationships"), list) else []

    mode = str((runtime_state or {}).get("encounter_context", {}).get("mode", "")).strip()
    room_id = str((runtime_state or {}).get("encounter_context", {}).get("room_id", "")).strip()
    encounter_id = int(runtime_state.get("encounter_id") or 0)

    event_types: dict[str, int] = {}
    for event in events:
        if not isinstance(event, dict):
            continue
        event_type = str(event.get("type") or "").strip()
        if event_type == "":
            continue
        event_types[event_type] = event_types.get(event_type, 0) + 1

    return {
        "campaign_id": int(snapshot.get("campaign_id") or 0),
        "mode": mode,
        "room_id": room_id,
        "encounter_id": encounter_id,
        "encounter_status": str(encounter.get("status") or ""),
        "round": int(runtime_state.get("round") or 0),
        "turn_entity": str(((runtime_state.get("turn") or {}) if isinstance(runtime_state.get("turn"), dict) else {}).get("entity", "")),
        "recent_event_count": len(events),
        "recent_event_types": event_types,
        "hostile_relationship_count": len(hostile_relationships),
    }


def write_markdown(path: Path, summary: dict[str, Any], snapshot_path: Path) -> None:
    lines = [
        "# Room-scene hostility RCA snapshot",
        "",
        f"- generated_at: {datetime.now(timezone.utc).isoformat()}",
        f"- campaign_id: {summary.get('campaign_id', 0)}",
        f"- mode: {summary.get('mode', '')}",
        f"- room_id: {summary.get('room_id', '')}",
        f"- encounter_id: {summary.get('encounter_id', 0)}",
        f"- encounter_status: {summary.get('encounter_status', '')}",
        f"- round: {summary.get('round', 0)}",
        f"- turn_entity: {summary.get('turn_entity', '')}",
        f"- recent_event_count: {summary.get('recent_event_count', 0)}",
        f"- hostile_relationship_count: {summary.get('hostile_relationship_count', 0)}",
        "",
        "## Event type counts",
        "",
    ]

    event_types = summary.get("recent_event_types")
    if isinstance(event_types, dict) and event_types:
        for event_type, count in sorted(event_types.items(), key=lambda item: (-int(item[1]), str(item[0]))):
            lines.append(f"- {event_type}: {count}")
    else:
        lines.append("- none")

    lines.extend([
        "",
        "## Artifacts",
        "",
        f"- snapshot_json: `{snapshot_path}`",
    ])
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser(description="Capture room-scene hostility RCA snapshot.")
    parser.add_argument("--campaign-id", type=int, default=0, help="Campaign ID to inspect (defaults to latest).")
    parser.add_argument("--event-limit", type=int, default=60, help="Number of recent campaign log events to capture.")
    parser.add_argument("--relationship-limit", type=int, default=120, help="Number of hostile relationship rows to capture.")
    parser.add_argument("--output-dir", default="", help="Optional explicit output directory.")
    args = parser.parse_args()

    if args.event_limit <= 0 or args.relationship_limit <= 0:
        raise RuntimeError("--event-limit and --relationship-limit must be greater than zero.")

    campaign_id = resolve_campaign_id(int(args.campaign_id))
    output_dir = Path(args.output_dir) if str(args.output_dir).strip() else OUTPUT_ROOT / f"campaign-{campaign_id}-{utc_stamp()}"
    output_dir.mkdir(parents=True, exist_ok=False)

    snapshot = collect_snapshot(campaign_id, int(args.event_limit), int(args.relationship_limit))
    summary = build_summary(snapshot)

    snapshot_path = output_dir / "snapshot.json"
    summary_json_path = output_dir / "summary.json"
    summary_md_path = output_dir / "summary.md"

    snapshot_path.write_text(json.dumps(snapshot, indent=2) + "\n", encoding="utf-8")
    summary_json_path.write_text(json.dumps(summary, indent=2) + "\n", encoding="utf-8")
    write_markdown(summary_md_path, summary, snapshot_path)

    result = {
        "status": "ok",
        "campaign_id": campaign_id,
        "output_dir": str(output_dir),
        "snapshot_path": str(snapshot_path),
        "summary_json_path": str(summary_json_path),
        "summary_md_path": str(summary_md_path),
        "summary": summary,
    }
    print(json.dumps(result, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
