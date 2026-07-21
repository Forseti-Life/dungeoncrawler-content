#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import shutil
import subprocess
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


REPO_ROOT = Path(__file__).resolve().parent.parent
DRUPAL_ROOT = Path("/var/www/html/dungeoncrawler")
DRUSH_BIN = DRUPAL_ROOT / "vendor/bin/drush"
OUTPUT_ROOT = REPO_ROOT / "tmp" / "campaign-analysis"
HARNESS_ROOT = REPO_ROOT / "tmp" / "langgraph-actor-harness"


def now_utc_stamp() -> str:
    return datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")


def parse_json_object_from_mixed_output(output: str, command: list[str]) -> dict[str, Any]:
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

    raise RuntimeError(f"Command did not return valid JSON object: {' '.join(command)}\n{output}")


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
        raise RuntimeError(f"Drush php:eval failed (rc={proc.returncode}): {' '.join(command)}\n{output}")
    if output == "":
        raise RuntimeError(f"Drush php:eval returned empty output: {' '.join(command)}")
    return parse_json_object_from_mixed_output(output, command)


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
        raise RuntimeError("Unable to resolve latest campaign_id from dc_campaigns.")
    return campaign_id


def collect_campaign_snapshot(campaign_id: int, row_limit: int, message_limit: int, watchdog_limit: int) -> dict[str, Any]:
    code = f"""
$cid = {campaign_id};
$row_limit = {row_limit};
$message_limit = {message_limit};
$watchdog_limit = {watchdog_limit};
$db = \\Drupal::database();
$schema = $db->schema();

$required_tables = [
  'dc_campaigns',
  'dc_chat_sessions',
  'dc_chat_messages',
  'dc_campaign_quests',
  'dc_campaign_quest_progress',
  'dc_campaign_quest_log',
  'dc_campaign_storylines',
  'watchdog',
];
foreach ($required_tables as $required_table) {{
  if (!$schema->tableExists($required_table)) {{
    throw new \\RuntimeException('Missing required table: ' . $required_table);
  }}
}}

$campaign = $db->select('dc_campaigns', 'c')
  ->fields('c')
  ->condition('id', $cid)
  ->range(0, 1)
  ->execute()
  ->fetchAssoc();
if (!is_array($campaign) || empty($campaign)) {{
  throw new \\RuntimeException('Campaign not found for id=' . $cid);
}}

$chat_sessions = $db->select('dc_chat_sessions', 's')
  ->fields('s')
  ->condition('campaign_id', $cid)
  ->orderBy('id', 'DESC')
  ->range(0, $row_limit)
  ->execute()
  ->fetchAll(\\PDO::FETCH_ASSOC);

$session_ids = [];
foreach ($chat_sessions as $row) {{
  $id = (int) ($row['id'] ?? 0);
  if ($id > 0) {{
    $session_ids[] = $id;
  }}
}}
$session_ids = array_values(array_unique($session_ids));

$chat_messages = [];
if (!empty($session_ids)) {{
  $chat_messages = $db->select('dc_chat_messages', 'm')
    ->fields('m')
    ->condition('session_id', $session_ids, 'IN')
    ->orderBy('id', 'DESC')
    ->range(0, $message_limit)
    ->execute()
    ->fetchAll(\\PDO::FETCH_ASSOC);
}}

$campaign_quests = $db->select('dc_campaign_quests', 'q')
  ->fields('q')
  ->condition('campaign_id', $cid)
  ->orderBy('id', 'DESC')
  ->range(0, $row_limit)
  ->execute()
  ->fetchAll(\\PDO::FETCH_ASSOC);

$quest_progress = $db->select('dc_campaign_quest_progress', 'qp')
  ->fields('qp')
  ->condition('campaign_id', $cid)
  ->orderBy('id', 'DESC')
  ->range(0, $row_limit)
  ->execute()
  ->fetchAll(\\PDO::FETCH_ASSOC);

$quest_log = $db->select('dc_campaign_quest_log', 'ql')
  ->fields('ql')
  ->condition('campaign_id', $cid)
  ->orderBy('id', 'DESC')
  ->range(0, $row_limit)
  ->execute()
  ->fetchAll(\\PDO::FETCH_ASSOC);

$storylines = $db->select('dc_campaign_storylines', 'cs')
  ->fields('cs')
  ->condition('campaign_id', $cid)
  ->orderBy('id', 'DESC')
  ->range(0, $row_limit)
  ->execute()
  ->fetchAll(\\PDO::FETCH_ASSOC);

$storyline_log = [];
if ($schema->tableExists('dc_campaign_storyline_log')) {{
  $query = $db->select('dc_campaign_storyline_log', 'sl')->fields('sl');
  if ($schema->fieldExists('dc_campaign_storyline_log', 'campaign_id')) {{
    $query->condition('campaign_id', $cid);
  }}
  $storyline_log = $query
    ->orderBy('id', 'DESC')
    ->range(0, $row_limit)
    ->execute()
    ->fetchAll(\\PDO::FETCH_ASSOC);
}}

$storyline_links = [];
if ($schema->tableExists('dc_campaign_storyline_links')) {{
  $query = $db->select('dc_campaign_storyline_links', 'lk')->fields('lk');
  if ($schema->fieldExists('dc_campaign_storyline_links', 'campaign_id')) {{
    $query->condition('campaign_id', $cid);
  }}
  $storyline_links = $query
    ->orderBy('id', 'DESC')
    ->range(0, $row_limit)
    ->execute()
    ->fetchAll(\\PDO::FETCH_ASSOC);
}}

$watchdog_recent = $db->select('watchdog', 'w')
  ->fields('w')
  ->orderBy('wid', 'DESC')
  ->range(0, $watchdog_limit)
  ->execute()
  ->fetchAll(\\PDO::FETCH_ASSOC);

$or = $db->condition('OR');
$or->condition('message', '%' . $cid . '%', 'LIKE');
$or->condition('variables', '%' . $cid . '%', 'LIKE');
$watchdog_campaign = $db->select('watchdog', 'w')
  ->fields('w')
  ->condition($or)
  ->orderBy('wid', 'DESC')
  ->range(0, $watchdog_limit)
  ->execute()
  ->fetchAll(\\PDO::FETCH_ASSOC);

$payload = [
  'generated_at' => gmdate('c'),
  'campaign_id' => $cid,
  'campaign' => $campaign,
  'chat_sessions' => $chat_sessions,
  'chat_messages' => $chat_messages,
  'campaign_quests' => $campaign_quests,
  'quest_progress' => $quest_progress,
  'quest_log' => $quest_log,
  'storylines' => $storylines,
  'storyline_log' => $storyline_log,
  'storyline_links' => $storyline_links,
  'watchdog_recent' => $watchdog_recent,
  'watchdog_campaign' => $watchdog_campaign,
];

print json_encode($payload, JSON_UNESCAPED_SLASHES);
"""
    return run_drush_php_eval(code)


def summarize_snapshot(snapshot: dict[str, Any]) -> dict[str, Any]:
    quests = snapshot.get("campaign_quests")
    quest_log = snapshot.get("quest_log")
    chat_messages = snapshot.get("chat_messages")
    storylines = snapshot.get("storylines")
    watchdog_campaign = snapshot.get("watchdog_campaign")

    if not isinstance(quests, list):
        raise RuntimeError("Snapshot is missing campaign_quests list.")
    if not isinstance(quest_log, list):
        raise RuntimeError("Snapshot is missing quest_log list.")
    if not isinstance(chat_messages, list):
        raise RuntimeError("Snapshot is missing chat_messages list.")
    if not isinstance(storylines, list):
        raise RuntimeError("Snapshot is missing storylines list.")
    if not isinstance(watchdog_campaign, list):
        raise RuntimeError("Snapshot is missing watchdog_campaign list.")

    status_counts = Counter(str(row.get("status") or "").strip().lower() for row in quests)
    started_counts: Counter[str] = Counter()
    for row in quest_log:
        if not isinstance(row, dict):
            continue
        event = str(row.get("event_type") or row.get("event") or "").strip().lower()
        if event != "started":
            continue
        quest_id = str(row.get("quest_id") or "").strip()
        if quest_id == "":
            continue
        started_counts[quest_id] += 1

    duplicate_starts = [
        {"quest_id": quest_id, "started_event_count": count}
        for quest_id, count in started_counts.items()
        if count > 1
    ]

    active_missing_objectives = 0
    for quest in quests:
        if not isinstance(quest, dict):
            continue
        status = str(quest.get("status") or "").strip().lower()
        if status not in {"active", "ready_for_turn_in"}:
            continue
        current_objectives = quest.get("current_objectives")
        objective_states = quest.get("objective_states")
        generated_objectives = quest.get("generated_objectives")
        if (
            current_objectives in (None, "", "[]", [])
            and objective_states in (None, "", "[]", [])
            and generated_objectives in (None, "", "[]", [])
        ):
            active_missing_objectives += 1

    return {
        "generated_at": datetime.now(timezone.utc).isoformat().replace("+00:00", "Z"),
        "campaign_id": int(snapshot.get("campaign_id") or 0),
        "chat_session_count": len(snapshot.get("chat_sessions") or []),
        "chat_message_count": len(chat_messages),
        "quest_count": len(quests),
        "quest_progress_count": len(snapshot.get("quest_progress") or []),
        "quest_log_count": len(quest_log),
        "storyline_count": len(storylines),
        "storyline_log_count": len(snapshot.get("storyline_log") or []),
        "storyline_link_count": len(snapshot.get("storyline_links") or []),
        "watchdog_campaign_count": len(watchdog_campaign),
        "quest_status_counts": dict(status_counts),
        "active_quests_missing_current_objectives": active_missing_objectives,
        "duplicate_started_events": duplicate_starts,
    }


def find_latest_harness_artifact_for_campaign(directory: Path, campaign_id: int) -> Path | None:
    if not directory.exists():
        return None
    for candidate in sorted(directory.glob("*.json"), key=lambda p: p.stat().st_mtime, reverse=True):
        if candidate.name.endswith(".summary.json"):
            continue
        try:
            payload = json.loads(candidate.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            continue
        if not isinstance(payload, dict):
            continue
        candidate_campaign_id = int(payload.get("campaign_id") or 0)
        if candidate_campaign_id <= 0:
            state = payload.get("state")
            if isinstance(state, dict):
                candidate_campaign_id = int(state.get("campaign_id") or 0)
        if candidate_campaign_id == campaign_id:
            return candidate
    return None


def write_json(path: Path, payload: dict[str, Any]) -> None:
    path.write_text(json.dumps(payload, indent=2) + "\n", encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser(description="Collect campaign logs/chat/quest/storyline state for analysis.")
    parser.add_argument("--campaign-id", type=int, default=0, help="Campaign ID to inspect. Defaults to latest campaign.")
    parser.add_argument("--row-limit", type=int, default=300, help="Max rows for quest/storyline/session/log datasets.")
    parser.add_argument("--message-limit", type=int, default=1000, help="Max chat messages to export.")
    parser.add_argument("--watchdog-limit", type=int, default=400, help="Max watchdog rows for recent and campaign filters.")
    parser.add_argument("--output-dir", default="", help="Optional explicit output directory.")
    args = parser.parse_args()

    if args.row_limit <= 0 or args.message_limit <= 0 or args.watchdog_limit <= 0:
        raise RuntimeError("row-limit, message-limit, and watchdog-limit must be greater than zero.")

    campaign_id = resolve_campaign_id(int(args.campaign_id))
    output_dir = Path(args.output_dir) if str(args.output_dir).strip() != "" else OUTPUT_ROOT / f"campaign-{campaign_id}-{now_utc_stamp()}"
    output_dir.mkdir(parents=True, exist_ok=False)

    snapshot = collect_campaign_snapshot(campaign_id, int(args.row_limit), int(args.message_limit), int(args.watchdog_limit))
    summary = summarize_snapshot(snapshot)

    snapshot_path = output_dir / "snapshot.json"
    summary_path = output_dir / "summary.json"
    write_json(snapshot_path, snapshot)
    write_json(summary_path, summary)

    trace_path = find_latest_harness_artifact_for_campaign(HARNESS_ROOT / "traces", campaign_id)
    issue_path = find_latest_harness_artifact_for_campaign(HARNESS_ROOT / "issues", campaign_id)
    if trace_path is not None:
        shutil.copy2(trace_path, output_dir / "latest-harness-trace.json")
    if issue_path is not None:
        shutil.copy2(issue_path, output_dir / "latest-harness-issue.json")

    result = {
        "status": "ok",
        "campaign_id": campaign_id,
        "output_dir": str(output_dir),
        "snapshot_path": str(snapshot_path),
        "summary_path": str(summary_path),
        "latest_trace_source": str(trace_path) if trace_path is not None else "",
        "latest_issue_source": str(issue_path) if issue_path is not None else "",
        "summary": summary,
    }
    print(json.dumps(result, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
