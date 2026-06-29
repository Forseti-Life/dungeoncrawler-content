from __future__ import annotations

import json
import os
import re
import shlex
import time
from pathlib import Path
from typing import Any, Dict, List, TypedDict

from langgraph.graph import StateGraph  # type: ignore


def consume_replies_step_catalog() -> List[Dict[str, str]]:
    return [
        {
            "id": "resolve_runtime_context",
            "purpose": "Resolve repo root, Forseti Drupal path, and Drush binary before reply ingestion starts.",
            "state_effect": "Sets repo_root, forseti_site_dir, and drush_bin.",
        },
        {
            "id": "load_configured_agents",
            "purpose": "Load configured seat IDs from agents.yaml for target validation.",
            "state_effect": "Sets configured_agents.",
        },
        {
            "id": "resolve_active_ceo_agent",
            "purpose": "Resolve the CEO fallback seat using env override, agents.yaml, and pause-state checks.",
            "state_effect": "Sets active_ceo_agent and warnings when fallback occurs.",
        },
        {
            "id": "check_reply_table",
            "purpose": "Probe Drupal for the copilot_agent_tracker_replies table and branch to explicit no-op when absent.",
            "state_effect": "Sets reply_table_exists and may short-circuit to summary.",
        },
        {
            "id": "load_pending_replies",
            "purpose": "Load up to 25 oldest unconsumed Drupal replies into structured subgraph state.",
            "state_effect": "Sets pending_replies.",
        },
        {
            "id": "filter_invalid_replies",
            "purpose": "Drop rows with missing target/message and track skipped reply IDs explicitly.",
            "state_effect": "Sets valid_replies, skipped_reply_ids, and warnings.",
        },
        {
            "id": "resolve_target_agents",
            "purpose": "Validate targets and reroute unknown seats to the active CEO seat.",
            "state_effect": "Sets normalized_replies and reroute warnings.",
        },
        {
            "id": "build_work_items",
            "purpose": "Generate graph-visible HQ inbox items before filesystem persistence.",
            "state_effect": "Sets work_items and created_item_ids.",
        },
        {
            "id": "persist_work_items",
            "purpose": "Write command.md and roi.txt into sessions/<agent>/inbox/<item-id>/ directories.",
            "state_effect": "Sets consumed_reply_ids and persisted item list.",
        },
        {
            "id": "acknowledge_consumed_replies",
            "purpose": "Mark Drupal reply rows consumed and attach HQ item IDs after persistence succeeds.",
            "state_effect": "Commits Drupal acknowledgment side effects or records errors.",
        },
        {
            "id": "archive_resolved_ceo_items",
            "purpose": "Move referenced CEO inbox items into resolved artifacts when in_reply_to targets exist.",
            "state_effect": "Sets archived_source_items.",
        },
        {
            "id": "summarize",
            "purpose": "Emit structured top-level telemetry for the tick log instead of a shell-only return code.",
            "state_effect": "Sets rc plus pending/created/rerouted/archived/warning/error counts.",
        },
    ]


class PendingReply(TypedDict):
    id: int
    to_agent_id: str
    in_reply_to: str
    message: str
    created: int


class NormalizedReply(TypedDict):
    id: int
    original_to_agent_id: str
    resolved_to_agent_id: str
    in_reply_to: str
    message: str
    created: int
    rerouted_to_ceo: bool


class InboxWorkItem(TypedDict):
    reply_id: int
    target_agent_id: str
    item_id: str
    command_md: str
    roi: int


class ConsumeRepliesState(TypedDict, total=False):
    repo_root: str
    forseti_site_dir: str
    drush_bin: str
    run_cmd: Any

    configured_agents: List[str]
    active_ceo_agent: str
    reply_table_exists: bool

    pending_replies: List[PendingReply]
    valid_replies: List[PendingReply]
    normalized_replies: List[NormalizedReply]
    work_items: List[InboxWorkItem]

    consumed_reply_ids: List[int]
    created_item_ids: List[str]
    archived_source_items: List[str]
    skipped_reply_ids: List[int]

    warnings: List[str]
    errors: List[str]
    rc: int


def _append_warning(state: ConsumeRepliesState, message: str) -> ConsumeRepliesState:
    warnings = list(state.get("warnings", []))
    warnings.append(message)
    state["warnings"] = warnings
    return state


def _append_error(state: ConsumeRepliesState, message: str) -> ConsumeRepliesState:
    errors = list(state.get("errors", []))
    errors.append(message)
    state["errors"] = errors
    return state


def _load_agent_ids(agents_path: Path) -> List[str]:
    if not agents_path.exists():
        return []
    ids: List[str] = []
    for ln in agents_path.read_text(encoding="utf-8", errors="ignore").splitlines():
        match = re.match(r"^\s*-\s+id:\s*(\S+)\s*$", ln)
        if match:
            ids.append(match.group(1).strip())
    return ids


def _run_drush_php(state: ConsumeRepliesState, code: str, *, timeout: int = 60) -> tuple[int, str]:
    run_cmd = state["run_cmd"]
    site_dir = str(state["forseti_site_dir"])
    drush_bin = str(state["drush_bin"])
    shell = f"cd {shlex.quote(site_dir)} && {shlex.quote(drush_bin)} -q php:eval {shlex.quote(code)}"
    return run_cmd(["bash", "-lc", shell], timeout=timeout)


def _is_agent_paused(state: ConsumeRepliesState, agent_id: str) -> bool:
    script = Path(state["repo_root"]) / "scripts" / "is-agent-paused.sh"
    rc, out = state["run_cmd"](["bash", str(script), agent_id], timeout=30)
    return rc == 0 and out.strip().lower() == "true"


def _resolve_runtime_context(state: ConsumeRepliesState) -> ConsumeRepliesState:
    repo_root = Path(state["repo_root"]).resolve()
    forseti_site_dir = Path(os.environ.get("FORSETI_SITE_DIR", "/var/www/html/forseti")).resolve()
    drush_bin = forseti_site_dir / "vendor" / "bin" / "drush"
    state["repo_root"] = str(repo_root)
    state["forseti_site_dir"] = str(forseti_site_dir)
    state["drush_bin"] = str(drush_bin)
    if not drush_bin.exists():
        return _append_error(state, f"Missing drush: {drush_bin}")
    return state


def _load_configured_agents(state: ConsumeRepliesState) -> ConsumeRepliesState:
    agents_path = Path(state["repo_root"]) / "org-chart" / "agents" / "agents.yaml"
    state["configured_agents"] = _load_agent_ids(agents_path)
    if not state["configured_agents"]:
        return _append_warning(state, f"No configured agents found in {agents_path}")
    return state


def _resolve_active_ceo_agent(state: ConsumeRepliesState) -> ConsumeRepliesState:
    configured_agents = list(state.get("configured_agents", []))
    preferred = os.environ.get("ORCHESTRATOR_CEO_AGENT", "").strip()
    if preferred.startswith("ceo-copilot") and not _is_agent_paused(state, preferred):
        state["active_ceo_agent"] = preferred
        return state

    for agent_id in configured_agents:
        if agent_id.startswith("ceo-copilot") and not _is_agent_paused(state, agent_id):
            state["active_ceo_agent"] = agent_id
            if preferred and preferred != agent_id:
                _append_warning(state, f"CEO override {preferred} unavailable; fell back to {agent_id}")
            return state

    state["active_ceo_agent"] = "ceo-copilot"
    return _append_warning(state, "No active CEO seat resolved; fell back to ceo-copilot")


def _check_reply_table(state: ConsumeRepliesState) -> ConsumeRepliesState:
    rc, out = _run_drush_php(
        state,
        'print(\\Drupal::database()->schema()->tableExists("copilot_agent_tracker_replies") ? "yes" : "no");',
        timeout=60,
    )
    if rc != 0:
        return _append_error(state, "Failed to probe copilot_agent_tracker_replies table")
    state["reply_table_exists"] = out.strip() == "yes"
    if not state["reply_table_exists"]:
        _append_warning(state, "copilot_agent_tracker_replies table missing; consume_replies is a no-op")
    return state


def _route_after_table_check(state: ConsumeRepliesState) -> str:
    if state.get("errors") or not state.get("reply_table_exists", False):
        return "summarize"
    return "load"


def _load_pending_replies(state: ConsumeRepliesState) -> ConsumeRepliesState:
    rc, out = _run_drush_php(
        state,
        '\n'.join([
            '$rows = \\Drupal::database()->select("copilot_agent_tracker_replies","r")',
            '  ->fields("r", ["id","to_agent_id","in_reply_to","message","created"])',
            '  ->condition("consumed", 0)',
            '  ->orderBy("created", "ASC")',
            '  ->range(0, 25)',
            '  ->execute()',
            '  ->fetchAll();',
            'print(json_encode($rows));',
        ]),
        timeout=120,
    )
    if rc != 0:
        return _append_error(state, "Failed to read pending replies from Drupal")
    try:
        raw = json.loads(out or "[]")
    except json.JSONDecodeError:
        return _append_error(state, "Drupal reply query returned invalid JSON")

    pending: List[PendingReply] = []
    for item in raw if isinstance(raw, list) else []:
        if not isinstance(item, dict):
            continue
        pending.append(
            {
                "id": int(item.get("id", 0)),
                "to_agent_id": str(item.get("to_agent_id", "") or "").strip(),
                "in_reply_to": str(item.get("in_reply_to", "") or "").strip(),
                "message": str(item.get("message", "") or "").rstrip(),
                "created": int(item.get("created", 0) or 0),
            }
        )
    state["pending_replies"] = pending
    return state


def _filter_invalid_replies(state: ConsumeRepliesState) -> ConsumeRepliesState:
    valid: List[PendingReply] = []
    skipped: List[int] = list(state.get("skipped_reply_ids", []))
    for reply in state.get("pending_replies", []):
        if not reply["to_agent_id"] or not reply["message"]:
            skipped.append(reply["id"])
            _append_warning(state, f"Skipped reply {reply['id']} due to missing target or message")
            continue
        valid.append(reply)
    state["valid_replies"] = valid
    state["skipped_reply_ids"] = skipped
    return state


def _route_after_filter(state: ConsumeRepliesState) -> str:
    if state.get("errors") or not state.get("valid_replies", []):
        return "summarize"
    return "normalize"


def _resolve_target_agents(state: ConsumeRepliesState) -> ConsumeRepliesState:
    configured = set(state.get("configured_agents", []))
    active_ceo_agent = str(state.get("active_ceo_agent", "ceo-copilot") or "ceo-copilot")
    normalized: List[NormalizedReply] = []
    for reply in state.get("valid_replies", []):
        target = reply["to_agent_id"]
        rerouted = bool(configured and target not in configured)
        resolved = active_ceo_agent if rerouted else target
        if rerouted:
            _append_warning(state, f"Reply {reply['id']} rerouted from {target} to {active_ceo_agent}")
        normalized.append(
            {
                "id": reply["id"],
                "original_to_agent_id": target,
                "resolved_to_agent_id": resolved,
                "in_reply_to": reply["in_reply_to"],
                "message": reply["message"],
                "created": reply["created"],
                "rerouted_to_ceo": rerouted,
            }
        )
    state["normalized_replies"] = normalized
    return state


def _build_work_items(state: ConsumeRepliesState) -> ConsumeRepliesState:
    work_items: List[InboxWorkItem] = []
    created_item_ids: List[str] = []
    for reply in state.get("normalized_replies", []):
        slug = re.sub(r"[^A-Za-z0-9._-]+", "-", reply["in_reply_to"])[:50] or f"compose-{reply['id']}"
        item_id = f"{time.strftime('%Y%m%d')}-reply-keith-{slug}-{reply['id']}"
        note = ""
        if reply["rerouted_to_ceo"]:
            note = (
                f"    NOTE: Original to_agent_id was '{reply['original_to_agent_id']}' "
                f"(not a configured agent seat); routed to {reply['resolved_to_agent_id']} for triage.\n\n"
            )
        command_md = (
            "- command: |\n"
            f"    Reply from Keith (in_reply_to: {reply['in_reply_to']})\n\n"
            f"    Tracking: drupal_reply_id={reply['id']}\n"
            f"    HQ item: {item_id}\n\n"
            f"{note}"
            f"    {reply['message'].replace(chr(10), chr(10) + '    ')}\n"
        )
        work_items.append(
            {
                "reply_id": reply["id"],
                "target_agent_id": reply["resolved_to_agent_id"],
                "item_id": item_id,
                "command_md": command_md,
                "roi": 5,
            }
        )
        created_item_ids.append(item_id)
    state["work_items"] = work_items
    state["created_item_ids"] = created_item_ids
    return state


def _persist_work_items(state: ConsumeRepliesState) -> ConsumeRepliesState:
    repo_root = Path(state["repo_root"])
    consumed: List[int] = []
    created: List[str] = []
    for item in state.get("work_items", []):
        inbox_dir = repo_root / "sessions" / item["target_agent_id"] / "inbox" / item["item_id"]
        try:
            inbox_dir.mkdir(parents=True, exist_ok=True)
            (inbox_dir / "command.md").write_text(item["command_md"], encoding="utf-8")
            (inbox_dir / "roi.txt").write_text(f"{item['roi']}\n", encoding="utf-8")
        except OSError as exc:
            _append_error(state, f"Failed to persist inbox item {item['item_id']}: {exc}")
            continue
        consumed.append(item["reply_id"])
        created.append(item["item_id"])
    state["consumed_reply_ids"] = consumed
    state["created_item_ids"] = created
    return state


def _acknowledge_consumed_replies(state: ConsumeRepliesState) -> ConsumeRepliesState:
    reply_ids = list(state.get("consumed_reply_ids", []))
    if not reply_ids:
        return state
    item_map = {str(item["reply_id"]): item["item_id"] for item in state.get("work_items", [])}
    ids_text = " ".join(str(reply_id) for reply_id in reply_ids)
    map_json = json.dumps(item_map, separators=(",", ":"))
    now = int(time.time())
    rc, _ = _run_drush_php(
        state,
        '\n'.join([
            f'$ids = preg_split("/\\\\s+/", trim("{ids_text}"));',
            f'$map = json_decode({json.dumps(map_json)}, TRUE) ?: [];',
            f'$now = (int) {now};',
            'foreach ($ids as $id) {',
            '  if ($id === "") { continue; }',
            '  $hq_item_id = (string) ($map[$id] ?? "");',
            '  \\Drupal::database()->update("copilot_agent_tracker_replies")',
            '    ->fields(["consumed" => 1, "consumed_at" => $now, "hq_item_id" => $hq_item_id])',
            '    ->condition("id", (int) $id)',
            '    ->execute();',
            '}',
        ]),
        timeout=120,
    )
    if rc != 0:
        return _append_error(state, "Failed to acknowledge consumed replies in Drupal")
    return state


def _route_after_ack(state: ConsumeRepliesState) -> str:
    if state.get("errors"):
        return "summarize"
    return "archive"


def _archive_resolved_ceo_items(state: ConsumeRepliesState) -> ConsumeRepliesState:
    repo_root = Path(state["repo_root"])
    ceo_agent = str(state.get("active_ceo_agent", "ceo-copilot") or "ceo-copilot")
    archived: List[str] = []
    for reply in state.get("normalized_replies", []):
        in_reply_to = reply["in_reply_to"]
        if not in_reply_to:
            continue
        src = repo_root / "sessions" / ceo_agent / "inbox" / in_reply_to
        if not src.is_dir():
            continue
        dest_dir = repo_root / "sessions" / ceo_agent / "artifacts" / "resolved"
        dest_dir.mkdir(parents=True, exist_ok=True)
        dest = dest_dir / f"{in_reply_to}-{int(time.time())}"
        try:
            src.rename(dest)
        except OSError:
            continue
        archived.append(in_reply_to)
    state["archived_source_items"] = archived
    return state


def _summarize(state: ConsumeRepliesState) -> ConsumeRepliesState:
    warnings = list(state.get("warnings", []))
    errors = list(state.get("errors", []))
    state["rc"] = 0 if not errors else 1
    state["warnings"] = warnings
    state["errors"] = errors
    return state


def run_consume_replies(*, repo_root: Path, run_cmd: Any) -> Dict[str, Any]:
    state: ConsumeRepliesState = {
        "repo_root": str(repo_root),
        "run_cmd": run_cmd,
        "warnings": [],
        "errors": [],
        "pending_replies": [],
        "valid_replies": [],
        "normalized_replies": [],
        "work_items": [],
        "consumed_reply_ids": [],
        "created_item_ids": [],
        "archived_source_items": [],
        "skipped_reply_ids": [],
    }

    graph = StateGraph(ConsumeRepliesState)
    graph.add_node("resolve_runtime_context", _resolve_runtime_context)
    graph.add_node("load_configured_agents", _load_configured_agents)
    graph.add_node("resolve_active_ceo_agent", _resolve_active_ceo_agent)
    graph.add_node("check_reply_table", _check_reply_table)
    graph.add_node("load_pending_replies", _load_pending_replies)
    graph.add_node("filter_invalid_replies", _filter_invalid_replies)
    graph.add_node("resolve_target_agents", _resolve_target_agents)
    graph.add_node("build_work_items", _build_work_items)
    graph.add_node("persist_work_items", _persist_work_items)
    graph.add_node("acknowledge_consumed_replies", _acknowledge_consumed_replies)
    graph.add_node("archive_resolved_ceo_items", _archive_resolved_ceo_items)
    graph.add_node("summarize", _summarize)

    graph.set_entry_point("resolve_runtime_context")
    graph.add_edge("resolve_runtime_context", "load_configured_agents")
    graph.add_edge("load_configured_agents", "resolve_active_ceo_agent")
    graph.add_edge("resolve_active_ceo_agent", "check_reply_table")
    graph.add_conditional_edges(
        "check_reply_table",
        _route_after_table_check,
        {"summarize": "summarize", "load": "load_pending_replies"},
    )
    graph.add_edge("load_pending_replies", "filter_invalid_replies")
    graph.add_conditional_edges(
        "filter_invalid_replies",
        _route_after_filter,
        {"summarize": "summarize", "normalize": "resolve_target_agents"},
    )
    graph.add_edge("resolve_target_agents", "build_work_items")
    graph.add_edge("build_work_items", "persist_work_items")
    graph.add_edge("persist_work_items", "acknowledge_consumed_replies")
    graph.add_conditional_edges(
        "acknowledge_consumed_replies",
        _route_after_ack,
        {"summarize": "summarize", "archive": "archive_resolved_ceo_items"},
    )
    graph.add_edge("archive_resolved_ceo_items", "summarize")
    graph.set_finish_point("summarize")

    result = graph.compile().invoke(state)
    return {
        "rc": int(result.get("rc", 0)),
        "reply_table_exists": bool(result.get("reply_table_exists", False)),
        "pending_count": len(result.get("pending_replies", [])),
        "valid_count": len(result.get("valid_replies", [])),
        "created_count": len(result.get("created_item_ids", [])),
        "rerouted_count": sum(1 for reply in result.get("normalized_replies", []) if reply.get("rerouted_to_ceo")),
        "archived_count": len(result.get("archived_source_items", [])),
        "skipped_count": len(result.get("skipped_reply_ids", [])),
        "warning_count": len(result.get("warnings", [])),
        "error_count": len(result.get("errors", [])),
        "active_ceo_agent": str(result.get("active_ceo_agent", "")),
    }
