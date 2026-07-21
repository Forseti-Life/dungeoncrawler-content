#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, TypedDict

from langgraph.graph import END, START, StateGraph


REPO_ROOT = Path(__file__).resolve().parent.parent
ISSUE_LOG_DIR = REPO_ROOT / "tmp" / "langgraph-actor-harness" / "issues"
TRACE_LOG_DIR = REPO_ROOT / "tmp" / "langgraph-actor-harness" / "traces"

FORBIDDEN_ACTOR_RUNTIME_ENV_VARS: tuple[str, ...] = (
    "GITHUB_TOKEN",
    "GH_TOKEN",
    "AWS_ACCESS_KEY_ID",
    "AWS_SECRET_ACCESS_KEY",
    "AWS_SESSION_TOKEN",
    "GOOGLE_APPLICATION_CREDENTIALS",
)

ALLOWED_ACTOR_RUNTIME_ENV_VARS: tuple[str, ...] = (
    "PATH",
    "HOME",
    "LANG",
    "LC_ALL",
    "TERM",
    "USER",
    "LOGNAME",
    "SHELL",
    "PWD",
    "PYTHONPATH",
    "VIRTUAL_ENV",
    "COPILOT_HQ_ROOT",
    "HARNESS_LLM_BACKEND",
    "HARNESS_LLM_MODEL_ID",
    "HARNESS_DECIDER_TIMEOUT_SEC",
    "HARNESS_DECIDER_MAX_TOKENS",
    "HQ_AGENTIC_BACKEND",
    "DEEPSEEK_API_KEY",
    "DEEPSEEK_BASE_URL",
    "DEEPSEEK_MODEL",
)


class HarnessState(TypedDict, total=False):
    campaign_id: int
    campaign_name: str
    owner_uid: int
    character_name: str
    character_id: int
    actor_id: str
    room_id: str
    started_quest_id: str
    turn_count: int
    max_turns: int
    snapshot: dict[str, Any]
    decision: dict[str, Any]
    last_result: dict[str, Any]
    run_status: str
    stop_reason: str
    correlation_id: str
    issue_key: str
    default_storyline_seed_checks: int
    gm_clue_requested: bool
    scriptedtesting_enabled: bool
    scriptedtesting_complete: bool
    scripted_step_index: int
    scripted_pending_step_id: str


ACTION_PARAM_REQUIREMENTS: dict[str, tuple[str, ...]] = {
    "transition": ("target_room_id",),
}
DEFAULT_STORYLINE_QUEST_ID = "automation_default_storyline"
DEFAULT_STORYLINE_OBJECTIVE_ID = "talk_to_eldric_for_storyline"
SCRIPTED_TARGET_QUEST_TEMPLATES: tuple[str, ...] = (
    "tavern_storyline_leads",
    "gather_wine",
    "gather_torch_components",
    "collect_spellbooks",
    "follow_the_whispers",
    "recover_blackmail_ledger",
    "stabilize_arcane_resonance",
)
SCRIPTED_TESTING_STEPS: tuple[dict[str, Any], ...] = (
    {
        "id": "ask_eldric_for_work",
        "mode": "chat",
        "chat_message": "Eldric, what work do you have for me?",
    },
    {
        "id": "ask_marta_for_work",
        "mode": "chat",
        "chat_message": "Marta, what work do you have for me?",
    },
    {
        "id": "ask_gribbles_for_work",
        "mode": "chat",
        "chat_message": "Gribbles, what work do you have for me?",
    },
    {
        "id": "search_room_for_items",
        "mode": "action",
        "action_intent": {
            "type": "search",
            "params": {},
        },
    },
    {
        "id": "turn_in_items_to_eldric",
        "mode": "action",
        "action_intent": {
            "type": "talk",
            "target": "tavern_keeper",
            "params": {
                "target": "tavern_keeper",
                "target_name": "Eldric",
                "message": "Eldric, I have the items. Please process my turn-in.",
            },
        },
    },
    {
        "id": "turn_in_items_to_marta",
        "mode": "action",
        "action_intent": {
            "type": "talk",
            "target": "scholar_npc",
            "params": {
                "target": "scholar_npc",
                "target_name": "Marta",
                "message": "Marta, I have the items. Please process my turn-in.",
            },
        },
    },
    {
        "id": "turn_in_items_to_gribbles",
        "mode": "action",
        "action_intent": {
            "type": "talk",
            "target": "gribbles_rindsworth",
            "params": {
                "target": "gribbles_rindsworth",
                "target_name": "Gribbles",
                "message": "Gribbles, I have the items. Please process my turn-in.",
            },
        },
    },
    {
        "id": "ask_eldric_for_more_work",
        "mode": "chat",
        "chat_message": "Eldric, have any more work for me?",
    },
    {
        "id": "navigate_to_absalom_streets",
        "mode": "chat",
        "chat_message": "I leave the tavern and head to Absalom Streets.",
    },
    {
        "id": "navigate_to_grandmas_parlor",
        "mode": "chat",
        "chat_message": "I navigate from Absalom Streets to Grandma's parlor.",
    },
    {
        "id": "talk_to_grandma_about_the_job",
        "mode": "chat",
        "chat_message": "Grandma, tell me about the job.",
    },
)


def is_non_empty_string(value: Any) -> bool:
    return isinstance(value, str) and value.strip() != ""


def to_int_or_default(value: Any, default: int = 0) -> int:
    if value is None or isinstance(value, bool):
        return default
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def summarize_available_action_contract(snapshot: dict[str, Any]) -> list[dict[str, Any]]:
    contract = extract_action_contract(snapshot)
    actions = contract.get("actions")
    if not isinstance(actions, list):
        return []

    summarized: list[dict[str, Any]] = []
    for action in actions:
        if not isinstance(action, dict) or not bool(action.get("available")):
            continue
        summarized.append(
            {
                "id": normalize_action_id(action.get("id")),
                "targeting": str(action.get("targeting") or "").strip(),
                "requires_turn": bool(action.get("requires_turn")),
            }
        )
    return [item for item in summarized if item["id"] != ""]


def summarize_visible_npcs(snapshot: dict[str, Any]) -> list[dict[str, str]]:
    visible_npcs = snapshot.get("visible_npcs")
    if not isinstance(visible_npcs, list):
        return []

    summarized: list[dict[str, str]] = []
    for npc in visible_npcs[:8]:
        if not isinstance(npc, dict):
            continue
        metadata = npc.get("state", {}).get("metadata", {}) if isinstance(npc.get("state"), dict) else {}
        summarized.append(
            {
                "id": str(npc.get("entity_instance_id") or npc.get("instance_id") or "").strip(),
                "name": str(metadata.get("display_name") or metadata.get("name") or "").strip(),
                "role": str(metadata.get("role") or "").strip(),
            }
        )
    return [npc for npc in summarized if npc["id"] != "" or npc["name"] != ""]


def summarize_connected_rooms(snapshot: dict[str, Any]) -> list[dict[str, str]]:
    connected_rooms = snapshot.get("connected_rooms")
    if not isinstance(connected_rooms, list):
        return []

    summarized: list[dict[str, str]] = []
    for room in connected_rooms[:8]:
        if not isinstance(room, dict):
            continue
        summarized.append(
            {
                "room_id": str(room.get("room_id") or "").strip(),
                "name": str(room.get("name") or "").strip(),
            }
        )
    return [room for room in summarized if room["room_id"] != ""]


def summarize_active_objective(snapshot: dict[str, Any]) -> dict[str, Any]:
    current = select_current_objective(snapshot)
    if not isinstance(current, dict):
        return {}
    objective = current.get("objective")
    if not isinstance(objective, dict):
        return {}
    return {
        "quest_id": str(current.get("quest_id") or "").strip(),
        "quest_name": str(current.get("quest_name") or "").strip(),
        "source_template_id": str(current.get("source_template_id") or "").strip(),
        "storyline_id": str(current.get("storyline_id") or "").strip(),
        "current_phase": to_int_or_default(current.get("current_phase")),
        "objective_id": str(objective.get("objective_id") or "").strip(),
        "type": str(objective.get("type") or "").strip(),
        "objective_action": str(objective.get("type") or "").strip(),
        "description": str(objective.get("description") or "").strip(),
        "next_step": str(objective.get("next_step") or "").strip(),
        "target": str(objective.get("target") or "").strip(),
        "location_id": str(objective.get("location_id") or "").strip(),
        "target_entity_instance_id": str(objective.get("target_entity_instance_id") or "").strip(),
        "target_display_name": str(objective.get("target_display_name") or "").strip(),
    }


def summarize_current_objective_list(value: Any) -> list[dict[str, Any]]:
    if isinstance(value, str):
        try:
            value = json.loads(value)
        except json.JSONDecodeError:
            return []
    if isinstance(value, dict):
        value = [value]
    if not isinstance(value, list):
        return []

    summarized: list[dict[str, Any]] = []
    for item in value:
        if not isinstance(item, dict):
            continue
        nested = item.get("objectives")
        if isinstance(nested, list):
            for objective in nested:
                if isinstance(objective, dict) and not bool(objective.get("completed")):
                    summarized.append(
                        {
                            "objective_id": str(objective.get("objective_id") or "").strip(),
                            "type": str(objective.get("type") or "").strip(),
                            "objective_action": str(objective.get("type") or "").strip(),
                            "description": str(objective.get("description") or "").strip(),
                            "next_step": str(objective.get("next_step") or "").strip(),
                            "target": str(objective.get("target") or "").strip(),
                            "location_id": str(objective.get("location_id") or "").strip(),
                            "target_entity_instance_id": str(objective.get("target_entity_instance_id") or "").strip(),
                            "target_display_name": str(objective.get("target_display_name") or "").strip(),
                        }
                    )
            continue
        if not bool(item.get("completed")):
            summarized.append(
                {
                    "objective_id": str(item.get("objective_id") or "").strip(),
                    "type": str(item.get("type") or "").strip(),
                    "objective_action": str(item.get("type") or "").strip(),
                    "description": str(item.get("description") or "").strip(),
                    "next_step": str(item.get("next_step") or "").strip(),
                    "target": str(item.get("target") or "").strip(),
                    "location_id": str(item.get("location_id") or "").strip(),
                    "target_entity_instance_id": str(item.get("target_entity_instance_id") or "").strip(),
                    "target_display_name": str(item.get("target_display_name") or "").strip(),
                }
            )
    return [item for item in summarized if item["objective_id"] != "" or item["description"] != ""]


def summarize_quest_context(snapshot: dict[str, Any]) -> list[dict[str, Any]]:
    quest_context = snapshot.get("quest_context")
    if not isinstance(quest_context, list):
        quest_context = snapshot.get("active_quests")
    if not isinstance(quest_context, list):
        return []

    summarized: list[dict[str, Any]] = []
    for quest in quest_context:
        if not isinstance(quest, dict):
            continue
        current_objectives = summarize_current_objective_list(quest.get("current_objectives"))
        if not current_objectives:
            current_objectives = summarize_current_objective_list(quest.get("objective_states"))
        if not current_objectives:
            current_objectives = summarize_current_objective_list(quest.get("generated_objectives"))
        summarized.append(
            {
                "quest_id": str(quest.get("quest_id") or "").strip(),
                "quest_name": str(quest.get("quest_name") or "").strip(),
                "source_template_id": str(quest.get("source_template_id") or "").strip(),
                "storyline_id": str(quest.get("storyline_id") or "").strip(),
                "status": str(quest.get("status") or "").strip(),
                "current_phase": to_int_or_default(quest.get("current_phase")),
                "location_id": str(quest.get("location_id") or "").strip(),
                "current_objectives": current_objectives,
            }
        )
    return [quest for quest in summarized if quest["quest_id"] != ""]


def summarize_storyline_context(snapshot: dict[str, Any]) -> list[dict[str, Any]]:
    storyline_context = snapshot.get("storyline_context")
    if not isinstance(storyline_context, list):
        storyline_context = snapshot.get("storylines")
    if not isinstance(storyline_context, list):
        return []

    summarized: list[dict[str, Any]] = []
    for storyline in storyline_context:
        if not isinstance(storyline, dict):
            continue
        summarized.append(
            {
                "storyline_id": str(storyline.get("storyline_id") or storyline.get("template_id") or "").strip(),
                "template_id": str(storyline.get("template_id") or "").strip(),
                "name": str(storyline.get("name") or "").strip(),
                "status": str(storyline.get("status") or "").strip(),
                "current_phase": to_int_or_default(storyline.get("current_phase")),
            }
        )
    return [storyline for storyline in summarized if storyline["storyline_id"] != ""]


def summarize_last_result_for_decider(last_result: Any) -> dict[str, Any]:
    if not isinstance(last_result, dict):
        return {}
    summary: dict[str, Any] = {}
    for key in ("success", "error", "status", "reason", "action", "phase"):
        if key in last_result:
            summary[key] = last_result[key]
    return summary


def actor_talked_to_other_actor(last_result: Any) -> bool:
    if not isinstance(last_result, dict):
        return False
    events = last_result.get("events")
    if not isinstance(events, list):
        return False
    for event in events:
        if not isinstance(event, dict):
            continue
        if str(event.get("type") or "").strip().lower() != "talk":
            continue
        actor = str(event.get("actor") or "").strip()
        target = str(event.get("target") or "").strip()
        if target != "" and target != actor:
            return True
    return False


def normalize_entity_ref_content_id(entity_ref: Any) -> str:
    if isinstance(entity_ref, dict):
        return normalize_action_id(entity_ref.get("content_id"))
    if isinstance(entity_ref, str):
        candidate = entity_ref.strip()
        if candidate.startswith("{") and candidate.endswith("}"):
            try:
                parsed = json.loads(candidate)
            except json.JSONDecodeError:
                parsed = None
            if isinstance(parsed, dict):
                return normalize_action_id(parsed.get("content_id"))
        return normalize_action_id(candidate)
    return ""


def resolve_visible_eldric(snapshot: dict[str, Any]) -> dict[str, Any] | None:
    visible_npcs = snapshot.get("visible_npcs")
    if not isinstance(visible_npcs, list):
        return None

    for npc in visible_npcs:
        if not isinstance(npc, dict):
            continue
        metadata = npc.get("state", {}).get("metadata", {}) if isinstance(npc.get("state"), dict) else {}
        display_name = normalize_action_id(metadata.get("display_name") or metadata.get("name"))
        content_id = normalize_entity_ref_content_id(npc.get("entity_ref"))
        entity_instance_id = str(npc.get("entity_instance_id") or npc.get("instance_id") or "").strip()
        if content_id == "tavern_keeper" or display_name == "eldric":
            return {
                "entity_instance_id": entity_instance_id,
                "display_name": str(metadata.get("display_name") or metadata.get("name") or "Eldric").strip() or "Eldric",
            }
    return None


def build_non_stop_fallback_decision(state: HarnessState, snapshot: dict[str, Any], reason: str) -> dict[str, Any]:
    available_actions = extract_available_actions(snapshot)
    actor_id = str(state.get("actor_id") or "").strip()

    # Prefer safe progression/turn-closure actions. Keep talk as last resort.
    if "end_turn" in available_actions:
        return {
            "mode": "action",
            "reason": f"fallback_after:{reason}",
            "action_intent": {
                "type": "end_turn",
                "actor": actor_id,
                "params": {"reason": f"fallback_after:{reason}"},
            },
        }
    if "search" in available_actions:
        return {
            "mode": "action",
            "reason": f"fallback_after:{reason}",
            "action_intent": {
                "type": "search",
                "actor": actor_id,
                "params": {},
            },
        }
    if "interact" in available_actions:
        return {
            "mode": "action",
            "reason": f"fallback_after:{reason}",
            "action_intent": {
                "type": "interact",
                "actor": actor_id,
                "params": {},
            },
        }
    if "delay" in available_actions:
        return {
            "mode": "action",
            "reason": f"fallback_after:{reason}",
            "action_intent": {
                "type": "delay",
                "actor": actor_id,
                "params": {},
            },
        }
    if "choose_not_to_act" in available_actions:
        return {
            "mode": "action",
            "reason": f"fallback_after:{reason}",
            "action_intent": {
                "type": "choose_not_to_act",
                "actor": actor_id,
                "params": {"reason": f"fallback_after:{reason}"},
            },
        }
    if "talk" in available_actions:
        return {
            "mode": "action",
            "reason": f"fallback_after:{reason}",
            "action_intent": {
                "type": "talk",
                "actor": actor_id,
                "params": {"message": "I need a concrete next actionable step right now."},
            },
        }
    if "transition" in available_actions:
        connected_rooms = snapshot.get("connected_rooms")
        if isinstance(connected_rooms, list) and connected_rooms:
            first = connected_rooms[0]
            if isinstance(first, dict):
                target_room_id = str(first.get("room_id") or "").strip()
                if target_room_id != "":
                    return {
                        "mode": "action",
                        "reason": f"fallback_after:{reason}",
                        "action_intent": {
                            "type": "transition",
                            "actor": actor_id,
                            "params": {"target_room_id": target_room_id},
                        },
                    }

    # If no gameplay action is available, keep action mode with end_turn-shaped
    # payload so contract validation surfaces the exact capability gap.
    return {
        "mode": "action",
        "reason": f"fallback_after:{reason}",
        "action_intent": {
            "type": "end_turn",
            "actor": actor_id,
            "params": {"reason": f"fallback_after:{reason}"},
        },
    }


def build_active_objective_interact_decision(state: HarnessState, current_objective: dict[str, Any] | None) -> dict[str, Any] | None:
    if not isinstance(current_objective, dict):
        return None
    objective = current_objective.get("objective")
    if not isinstance(objective, dict):
        return None

    objective_action = normalize_action_id(objective.get("type") or objective.get("objective_action"))
    if objective_action != "interact":
        return None

    target = (
        str(objective.get("target_entity_instance_id") or "").strip()
        or str(objective.get("target") or "").strip()
        or str(objective.get("target_display_name") or "").strip()
    )
    if target == "":
        return None

    target_name = str(objective.get("target_display_name") or objective.get("target") or "there").strip() or "there"
    objective_id = str(objective.get("objective_id") or "").strip()
    quest_id = str(current_objective.get("quest_id") or "").strip()
    objective_prompt = str(objective.get("next_step") or objective.get("description") or "").strip()
    if objective_prompt == "":
        objective_prompt = f"{target_name}, let's continue this objective."

    available_actions = extract_available_actions(state.get("snapshot") or {})
    if "talk" in available_actions:
        return {
            "mode": "action",
            "reason": "deterministic_active_objective_interact",
            "action_intent": {
                "type": "talk",
                "actor": state["actor_id"],
                "target": target,
                "params": {
                    "target": target,
                    "message": objective_prompt,
                    "objective_id": objective_id,
                    "quest_id": quest_id,
                },
            },
        }

    return {
        "mode": "chat",
        "reason": "deterministic_active_objective_interact",
        "chat_message": objective_prompt,
    }


def build_default_storyline_objective(snapshot: dict[str, Any]) -> dict[str, Any] | None:
    eldric = resolve_visible_eldric(snapshot)
    if eldric is None:
        return None
    return {
        "quest_id": DEFAULT_STORYLINE_QUEST_ID,
        "quest_name": "Automation Storyline Bootstrap",
        "objective": {
            "objective_id": DEFAULT_STORYLINE_OBJECTIVE_ID,
            "type": "interact",
            "description": "No open objectives remain; talk to Eldric to request a new storyline lead.",
            "next_step": "Talk to Eldric in the current room and ask for a storyline lead.",
            "target": "tavern_keeper",
            "location_id": str(snapshot.get("active_room_id") or "").strip(),
            "target_entity_instance_id": eldric.get("entity_instance_id", ""),
            "target_display_name": eldric.get("display_name", "Eldric"),
        },
    }


def normalize_decision_for_harness_actor(state: HarnessState, decision: dict[str, Any]) -> dict[str, Any]:
    if str(decision.get("mode", "")) != "action":
        return decision
    intent = decision.get("action_intent")
    if not isinstance(intent, dict):
        return decision

    normalized = dict(decision)
    normalized_intent = dict(intent)
    normalized_intent["actor"] = str(state.get("actor_id", "")).strip()
    normalized["action_intent"] = normalized_intent
    return normalized


def summarize_issue_payload(issue_body: dict[str, Any]) -> dict[str, Any]:
    snapshot = issue_body.get("snapshot")
    decision = issue_body.get("decision")
    last_result = issue_body.get("last_result")
    summary: dict[str, Any] = {
        "timestamp": issue_body.get("timestamp"),
        "correlation_id": issue_body.get("correlation_id"),
        "campaign_id": issue_body.get("campaign_id"),
        "character_name": issue_body.get("character_name"),
        "character_id": issue_body.get("character_id"),
        "actor_id": issue_body.get("actor_id"),
        "room_id": issue_body.get("room_id"),
        "stop_reason": issue_body.get("stop_reason"),
        "decision": decision,
        "last_result_summary": summarize_last_result_for_decider(last_result),
    }

    if isinstance(snapshot, dict):
        summary["active_objective"] = summarize_active_objective(snapshot)
        summary["quest_context"] = summarize_quest_context(snapshot)
        summary["storyline_context"] = summarize_storyline_context(snapshot)
        summary["available_actions"] = sorted(extract_available_actions(snapshot))
        summary["active_quest_count"] = (
            len(snapshot.get("active_quests")) if isinstance(snapshot.get("active_quests"), list) else 0
        )
    return summary


def normalize_action_id(value: Any) -> str:
    return str(value or "").strip().lower()


def extract_available_actions(snapshot: dict[str, Any]) -> set[str]:
    raw = snapshot.get("available_actions")
    if isinstance(raw, list):
        return {normalize_action_id(item) for item in raw if normalize_action_id(item)}
    if isinstance(raw, dict):
        nested = raw.get("available_actions")
        if isinstance(nested, list):
            return {normalize_action_id(item) for item in nested if normalize_action_id(item)}
    return set()


def extract_action_contract(snapshot: dict[str, Any]) -> dict[str, Any]:
    contract = snapshot.get("action_contract")
    if isinstance(contract, dict):
        return contract
    available_actions = snapshot.get("available_actions")
    if isinstance(available_actions, dict):
        nested_contract = available_actions.get("action_contract")
        if isinstance(nested_contract, dict):
            return nested_contract
    return {}


def validate_action_intent_contract(state: HarnessState, decision: dict[str, Any]) -> str | None:
    intent = decision.get("action_intent")
    if not isinstance(intent, dict):
        return "action_intent_missing_or_non_object"

    action_type = normalize_action_id(intent.get("type"))
    if action_type == "":
        return "action_intent_type_required"

    params = intent.get("params")
    if not isinstance(params, dict):
        return "action_intent_params_not_object"

    actor = str(intent.get("actor", "")).strip()
    if actor != "" and actor != str(state.get("actor_id", "")):
        return "action_intent_actor_mismatch"

    # Deterministic transition is harness-authored and may not appear in phase
    # handler availability; validate it minimally but do not require allowlisting.
    if str(decision.get("reason", "")) == "deterministic_wayfinding_transition":
        return None

    snapshot = state.get("snapshot") or {}
    available_actions = extract_available_actions(snapshot)
    if available_actions and action_type not in available_actions:
        return f"action_not_available:{action_type}"

    contract = extract_action_contract(snapshot)
    contract_actions = contract.get("actions")
    if isinstance(contract_actions, list):
        matching = [
            item for item in contract_actions
            if isinstance(item, dict) and normalize_action_id(item.get("id")) == action_type
        ]
        if matching:
            if not bool(matching[0].get("available")):
                return f"action_marked_unavailable_by_contract:{action_type}"
        else:
            return f"action_missing_from_contract:{action_type}"

    for required_param in ACTION_PARAM_REQUIREMENTS.get(action_type, ()):
        if not is_non_empty_string(params.get(required_param)):
            return f"action_intent_params_missing_{required_param}"

    if action_type == "transition":
        target_room_id = str(params.get("target_room_id") or "").strip()
        active_room_id = str(snapshot.get("active_room_id") or state.get("room_id") or "").strip()
        if target_room_id != "" and target_room_id == active_room_id:
            return "action_intent_transition_target_same_room"
        connected_rooms = snapshot.get("connected_rooms")
        if isinstance(connected_rooms, list):
            connected_room_ids = {
                str(item.get("room_id") or "").strip()
                for item in connected_rooms
                if isinstance(item, dict)
            }
            if connected_room_ids == set():
                return "action_intent_transition_no_connected_rooms"
            if connected_room_ids and target_room_id not in connected_room_ids:
                return "action_intent_transition_target_not_connected"

    if action_type == "talk":
        if not is_non_empty_string(params.get("message")):
            return "action_intent_params_missing_talk_message"

    return None


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def drush_bin() -> str:
    candidate = Path("/var/www/html/dungeoncrawler/vendor/bin/drush")
    if not candidate.exists():
        raise RuntimeError("Expected drush at /var/www/html/dungeoncrawler/vendor/bin/drush.")
    return str(candidate)


def run_json_command(args: list[str]) -> dict[str, Any]:
    proc = subprocess.run(
        args,
        cwd="/var/www/html/dungeoncrawler",
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        env=build_actor_runtime_env(),
    )
    output = (proc.stdout or "").strip()
    if proc.returncode != 0:
        raise RuntimeError(f"Command failed (rc={proc.returncode}): {' '.join(args)}\n{output}")
    if output == "":
        raise RuntimeError(f"Command returned empty output: {' '.join(args)}")
    payload = parse_json_object_from_mixed_output(output, args)
    if not isinstance(payload, dict):
        raise RuntimeError(f"Command returned non-object JSON: {' '.join(args)}")
    return payload


def parse_json_object_from_mixed_output(output: str, args: list[str]) -> dict[str, Any]:
    """
    Parse a JSON object from command output that may include warning lines.

    Drush can prepend warning lines before emitting JSON payloads. We hard-fail
    if no valid JSON object can be recovered.
    """
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

    raise RuntimeError(f"Command did not return valid JSON object: {' '.join(args)}\n{output}")


def hq_repo_root() -> Path:
    root_override = (os.environ.get("COPILOT_HQ_ROOT") or "").strip()
    root = Path(root_override) if root_override else Path("/home/ubuntu/forseti.life/copilot-hq")
    if not root.exists():
        raise RuntimeError(f"COPILOT_HQ_ROOT does not exist: {root}")
    return root


def resolve_decider_backend() -> str:
    backend = (
        os.environ.get("HARNESS_LLM_BACKEND")
        or os.environ.get("HQ_AGENTIC_BACKEND")
        or "local-server"
    ).strip().lower()
    if backend != "local-server":
        raise RuntimeError(
            f"Invalid harness decider backend '{backend}'. "
            "Expected local-server to match org-standard methodology."
        )
    return backend


def enforce_actor_runtime_secret_boundary() -> None:
    present = [name for name in FORBIDDEN_ACTOR_RUNTIME_ENV_VARS if (os.environ.get(name) or "").strip() != ""]
    if present:
        raise RuntimeError(
            "actor_runtime_secret_boundary_violation:"
            + ",".join(sorted(present))
        )


def build_actor_runtime_env() -> dict[str, str]:
    env: dict[str, str] = {}
    for key in ALLOWED_ACTOR_RUNTIME_ENV_VARS:
        value = os.environ.get(key)
        if value is None:
            continue
        value = value.strip() if isinstance(value, str) else str(value)
        if value == "":
            continue
        env[key] = value
    return env


def call_routed_decider(state: HarnessState) -> dict[str, Any]:
    hq_root = hq_repo_root()
    wrapper_path = hq_root / "llm" / "genai_wrapper.py"
    if not wrapper_path.exists():
        raise RuntimeError(f"GenAI wrapper not found: {wrapper_path}")

    wrapper_python = hq_root / "llm" / ".venv" / "bin" / "python3"
    python_bin = str(wrapper_python if wrapper_python.exists() else Path(sys.executable or "python3"))
    backend = resolve_decider_backend()
    model_id = (os.environ.get("HARNESS_LLM_MODEL_ID") or "").strip()
    timeout_sec = max(30, int(os.environ.get("HARNESS_DECIDER_TIMEOUT_SEC") or "120"))

    snapshot = state.get("snapshot") or {}
    available_actions = sorted(extract_available_actions(snapshot))
    available_action_contract = summarize_available_action_contract(snapshot)
    active_objective = summarize_active_objective(snapshot)
    quest_context = summarize_quest_context(snapshot)
    storyline_context = summarize_storyline_context(snapshot)
    # Manual operator analysis helper:
    # scripts/campaign-analysis.py collects campaign logs, chat, quests, storylines,
    # watchdog rows, and harness artifacts into one RCA bundle.
    # This is documented here for humans only and is not passed to the decider runtime.
    visible_npcs = summarize_visible_npcs(snapshot)
    connected_rooms = summarize_connected_rooms(snapshot)

    system_prompt = (
        "You are controlling Burasco in DungeonCrawler. "
        "Choose exactly one next move to progress active quest objectives. "
        "You must return strict JSON object only (no markdown, no prose) with keys: "
        "mode, reason, action_intent, chat_message. "
        "mode must be one of: action, chat, stop. "
        "NEVER set mode to action names like search/interact/talk/transition. "
        "If you want search/interact/talk/transition, set mode='action' and put the action name in action_intent.type. "
        "For mode=action, action_intent MUST be an object with keys type, params, actor. "
        "action_intent.type MUST be one of allowed_action_ids. params MUST be an object. "
        "For type=transition, params.target_room_id is required. "
        "For type=talk, provide params.message. "
        "If no legal action is appropriate, return mode=stop with a concrete reason. "
        "For mode=chat, provide non-empty chat_message. "
        "Prefer the active quest_context and storyline_context fields when choosing the next move. "
        "Valid example: "
        "{\"mode\":\"action\",\"reason\":\"Need to inspect room for quest items.\","
        "\"action_intent\":{\"type\":\"search\",\"params\":{},\"actor\":\"<actor_id>\"},\"chat_message\":\"\"}"
    )
    user_prompt = {
        "campaign_id": state.get("campaign_id"),
        "character_name": state.get("character_name"),
        "character_id": state.get("character_id"),
        "actor_id": state.get("actor_id"),
        "room_id": snapshot.get("active_room_id") or state.get("room_id"),
        "phase": snapshot.get("phase"),
        "active_objective": active_objective,
        "quest_context": quest_context,
        "storyline_context": storyline_context,
        "visible_npcs": visible_npcs,
        "connected_rooms": connected_rooms,
        "available_actions": available_actions,
        "available_action_contract": available_action_contract,
        "last_result_summary": summarize_last_result_for_decider(state.get("last_result", {})),
        "contract_requirements": {
            "required_mode_values": ["action", "chat", "stop"],
            "required_action_intent_keys_for_action_mode": ["type", "params", "actor"],
            "required_transition_params": ["target_room_id"],
            "required_talk_payload_any_of": ["message", "target", "target_id", "entity_id", "entity_ref"],
        },
    }

    prompt = (
        "SYSTEM:\n"
        f"{system_prompt}\n\n"
        "USER_CONTEXT_JSON:\n"
        f"{json.dumps(user_prompt, ensure_ascii=False)}"
    )
    session_id = (
        f"dungeoncrawler-actor-harness-{state.get('campaign_id', 0)}-"
        f"{state.get('correlation_id', 'run')}"
    )
    command = [
        python_bin,
        str(wrapper_path),
        "--backend",
        backend,
        "--session",
        session_id,
        "--agent-id",
        "dungeoncrawler-actor-harness",
        "--source",
        "scripts/langgraph-actor-harness-run.py",
        "--operation",
        "actor_harness_decide",
        "--timeout-sec",
        str(timeout_sec),
        "--max-tokens",
        str(max(256, int(os.environ.get("HARNESS_DECIDER_MAX_TOKENS") or "800"))),
        "--no-history",
        "--prompt",
        prompt,
    ]
    if model_id:
        command.extend(["--model-id", model_id])

    proc = subprocess.run(
        command,
        cwd=str(hq_root),
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
        env=build_actor_runtime_env(),
    )
    output = (proc.stdout or "").strip()
    if proc.returncode != 0:
        raise RuntimeError(f"Decider command failed (rc={proc.returncode}): {' '.join(command)}\n{output}")
    if output == "":
        raise RuntimeError("Decider command returned empty output.")

    decision = parse_json_object_from_mixed_output(output, command)
    if not isinstance(decision, dict):
        raise RuntimeError("Decider response was not a JSON object.")

    mode = str(decision.get("mode", "")).strip().lower()
    if mode not in {"action", "chat", "stop"}:
        raise RuntimeError(f"Invalid decision mode: {mode or '(empty)'}")
    if mode == "action" and not isinstance(decision.get("action_intent"), dict):
        raise RuntimeError("Decision mode=action requires action_intent object.")
    if mode == "chat" and str(decision.get("chat_message", "")).strip() == "":
        raise RuntimeError("Decision mode=chat requires non-empty chat_message.")

    decision["mode"] = mode
    decision["reason"] = str(decision.get("reason", "")).strip()
    return decision


def node_bootstrap(state: HarnessState) -> HarnessState:
    if state.get("campaign_id", 0) > 0:
        return state

    command = [
        drush_bin(),
        "dc:actor-harness-bootstrap",
        f"--character-name={state['character_name']}",
        f"--campaign-name={state['campaign_name']}",
    ]
    owner_uid = int(state.get("owner_uid", 0))
    if owner_uid > 0:
        command.append(f"--uid={owner_uid}")
    payload = run_json_command(command)

    next_state: HarnessState = dict(state)
    next_state["campaign_id"] = int(payload["campaign_id"])
    next_state["character_id"] = int(payload["character_id"])
    next_state["actor_id"] = str(payload["actor_id"])
    next_state["room_id"] = str(payload["room_id"])
    next_state["started_quest_id"] = str(payload.get("started_quest_id", ""))
    next_state["run_status"] = "running"
    next_state["turn_count"] = 0
    return next_state


def node_snapshot(state: HarnessState) -> HarnessState:
    command = [
        drush_bin(),
        "dc:actor-harness-snapshot",
        str(state["campaign_id"]),
        state["actor_id"],
        f"--character-id={state['character_id']}",
    ]
    payload = run_json_command(command)
    next_state: HarnessState = dict(state)
    next_state["snapshot"] = payload
    next_state["room_id"] = str(payload.get("active_room_id") or state["room_id"])
    return next_state


def node_decide(state: HarnessState) -> HarnessState:
    snapshot = state.get("snapshot") or {}
    current_objective = select_current_objective(snapshot)
    seed_checks = int(state.get("default_storyline_seed_checks", -1))
    if (
        isinstance(current_objective, dict)
        and str(current_objective.get("quest_id")) == DEFAULT_STORYLINE_QUEST_ID
    ):
        available_actions = extract_available_actions(snapshot)
        if seed_checks >= 2:
            next_state: HarnessState = dict(state)
            next_state["decision"] = build_non_stop_fallback_decision(
                state,
                snapshot,
                "default_storyline_seed_unresolved",
            )
            return next_state
        if seed_checks == 1:
            if "end_turn" in available_actions:
                next_state: HarnessState = dict(state)
                next_state["decision"] = {
                    "mode": "action",
                    "reason": "deterministic_default_storyline_seed_end_turn",
                    "action_intent": {
                        "type": "end_turn",
                        "actor": state["actor_id"],
                        "params": {
                            "reason": "Awaiting storyline lead after seeded Eldric prompt.",
                        },
                    },
                }
                next_state["default_storyline_seed_checks"] = 2
                return next_state
            next_state = dict(state)
            next_state["decision"] = build_non_stop_fallback_decision(
                state,
                snapshot,
                "default_storyline_seed_unresolved",
            )
            return next_state
        objective = current_objective.get("objective") if isinstance(current_objective.get("objective"), dict) else {}
        target_name = str(objective.get("target_display_name") or "Eldric").strip() or "Eldric"
        target_entity_instance_id = str(objective.get("target_entity_instance_id") or "npc_tavern_keeper").strip()
        next_state: HarnessState = dict(state)
        if "talk" in available_actions:
            next_state["decision"] = {
                "mode": "action",
                "reason": "deterministic_default_storyline_seed",
                "action_intent": {
                    "type": "talk",
                    "actor": state["actor_id"],
                    "target": target_entity_instance_id,
                    "params": {
                        "message": (
                            f"{target_name}, I heard you know about work or danger in this tavern. "
                            "Do you have a storyline lead for me to follow?"
                        ),
                        "objective_id": DEFAULT_STORYLINE_OBJECTIVE_ID,
                    },
                },
            }
        else:
            next_state["decision"] = {
                "mode": "chat",
                "reason": "deterministic_default_storyline_seed",
                "chat_message": (
                    f"{target_name}, I heard you know about work or danger in this tavern. "
                    "Do you have a storyline lead for me to follow?"
                ),
            }
        next_state["default_storyline_seed_checks"] = 0
        return next_state

    wayfinding = snapshot.get("deterministic_wayfinding")
    if isinstance(wayfinding, dict):
        if bool(wayfinding.get("available")):
            target_room_id = str(wayfinding.get("target_room_id", "")).strip()
            if target_room_id == "":
                raise RuntimeError("deterministic_wayfinding.available=true but target_room_id is empty.")
            decision = {
                "mode": "action",
                "reason": "deterministic_wayfinding_transition",
                "action_intent": {
                    "type": "transition",
                    "actor": state["actor_id"],
                    "params": {
                        "target_room_id": target_room_id,
                        "quest_id": str(wayfinding.get("quest_id", "")),
                        "objective_id": str(wayfinding.get("objective_id", "")),
                    },
                },
            }
            next_state: HarnessState = dict(state)
            next_state["decision"] = decision
            return next_state

        # Hard-fail instead of falling back to non-deterministic routing when a
        # destination objective exists in a different room but no canonical
        # navigation capability resolves.
        if str(wayfinding.get("reason", "")) == "no_available_quest_destination_capability":
            destination = str(wayfinding.get("destination", "")).strip()
            active_room_id = str(snapshot.get("active_room_id") or state.get("room_id") or "").strip()
            if destination == "" or destination == active_room_id:
                # Objective destination is local to the active room; let normal
                # decisioning resolve the in-room action (talk/interact/etc).
                pass
            else:
                next_state: HarnessState = dict(state)
                next_state["decision"] = build_non_stop_fallback_decision(
                    state,
                    snapshot,
                    "objective_wayfinding_unresolved",
                )
                return next_state

    objective_decision = None
    if current_objective is not None:
        objective_decision = build_active_objective_interact_decision(state, current_objective)
    if objective_decision is not None:
        next_state: HarnessState = dict(state)
        next_state["decision"] = objective_decision
        return next_state

    if actor_talked_to_other_actor(state.get("last_result")) and not bool(state.get("gm_clue_requested")):
        next_state: HarnessState = dict(state)
        next_state["decision"] = {
            "mode": "chat",
            "reason": "deterministic_gm_clue_request_after_actor_talk",
            "chat_message": (
                "Game Master, based on the conversations I just had, give me concrete clues and the single best "
                "next actionable step to progress my active objective."
            ),
        }
        next_state["gm_clue_requested"] = True
        return next_state

    try:
        decision = normalize_decision_for_harness_actor(state, call_routed_decider(state))
    except RuntimeError as exc:
        decision = build_non_stop_fallback_decision(
            state,
            snapshot,
            f"invalid_decider_response:{str(exc).strip()}",
        )
    if decision.get("mode") == "action":
        contract_error = validate_action_intent_contract(state, decision)
        if contract_error is not None:
            decision = build_non_stop_fallback_decision(
                state,
                snapshot,
                f"invalid_action_intent_contract:{contract_error}",
            )
    next_state: HarnessState = dict(state)
    next_state["decision"] = decision
    return next_state


def node_scriptedtesting(state: HarnessState) -> HarnessState:
    next_state: HarnessState = dict(state)
    if not bool(state.get("scriptedtesting_enabled", True)):
        next_state["scriptedtesting_complete"] = True
        next_state.pop("scripted_pending_step_id", None)
        return next_state
    if bool(state.get("scriptedtesting_complete", False)):
        next_state.pop("scripted_pending_step_id", None)
        return next_state

    step_index = int(state.get("scripted_step_index", 0))
    pending_step_id = str(state.get("scripted_pending_step_id", "")).strip()
    last_result = state.get("last_result")
    if pending_step_id != "":
        if not isinstance(last_result, dict) or not bool(last_result.get("success", True)):
            next_state["run_status"] = "blocked"
            next_state["stop_reason"] = f"scriptedtesting_step_failed:{pending_step_id}"
            return next_state
        step_index += 1
        next_state["scripted_step_index"] = step_index
        next_state["scripted_pending_step_id"] = ""

    if step_index >= len(SCRIPTED_TESTING_STEPS):
        next_state["scriptedtesting_complete"] = True
        next_state.pop("scripted_pending_step_id", None)
        return next_state

    step = SCRIPTED_TESTING_STEPS[step_index]
    step_id = str(step.get("id") or f"step_{step_index}").strip() or f"step_{step_index}"
    mode = str(step.get("mode") or "").strip().lower()
    if mode == "chat":
        message = str(step.get("chat_message") or "").strip()
        if message == "":
            next_state["run_status"] = "blocked"
            next_state["stop_reason"] = f"scriptedtesting_invalid_step:{step_id}"
            return next_state
        next_state["decision"] = {
            "mode": "chat",
            "reason": f"scriptedtesting:{step_id}",
            "chat_message": message,
        }
        next_state["scripted_pending_step_id"] = step_id
        return next_state

    if mode == "action":
        action_intent = step.get("action_intent")
        if not isinstance(action_intent, dict) or str(action_intent.get("type") or "").strip() == "":
            next_state["run_status"] = "blocked"
            next_state["stop_reason"] = f"scriptedtesting_invalid_step:{step_id}"
            return next_state
        next_state["decision"] = {
            "mode": "action",
            "reason": f"scriptedtesting:{step_id}",
            "action_intent": dict(action_intent),
        }
        next_state["scripted_pending_step_id"] = step_id
        return next_state

    next_state["run_status"] = "blocked"
    next_state["stop_reason"] = f"scriptedtesting_invalid_mode:{step_id}"
    return next_state


def node_execute_action(state: HarnessState) -> HarnessState:
    decision = state["decision"]
    intent = dict(decision["action_intent"])
    intent["actor_id"] = state["actor_id"]
    if int(state.get("character_id", 0)) > 0:
        params = intent.get("params")
        if not isinstance(params, dict):
            params = {}
        params["character_id"] = int(state["character_id"])
        intent["params"] = params
    intent["client_state_version"] = (
        intent.get("client_state_version")
        or (((state.get("snapshot") or {}).get("state_version")) or 1)
    )

    command = [
        drush_bin(),
        "dc:actor-harness-action",
        str(state["campaign_id"]),
        f"--payload={json.dumps(intent, separators=(',', ':'))}",
    ]
    try:
        result = run_json_command(command)
    except RuntimeError as exc:
        next_state: HarnessState = dict(state)
        next_state["decision"] = build_non_stop_fallback_decision(
            state,
            state.get("snapshot") or {},
            f"action_execution_failed:{str(exc).strip()}",
        )
        next_state["turn_count"] = int(state.get("turn_count", 0)) + 1
        next_state["last_result"] = {
            "success": False,
            "error": str(exc),
        }
        return next_state

    next_state: HarnessState = dict(state)
    next_state["turn_count"] = int(state.get("turn_count", 0)) + 1
    next_state["last_result"] = result
    return next_state


def node_execute_chat(state: HarnessState) -> HarnessState:
    decision = state["decision"]
    message = str(decision["chat_message"]).strip()
    command = [
        drush_bin(),
        "dc:gm-actor-run",
        str(state["campaign_id"]),
        state["room_id"],
        message,
        f"--actor-id={state['actor_id']}",
        f"--character-id={state['character_id']}",
        f"--speaker={state['character_name']}",
    ]
    try:
        result = run_json_command(command)
    except RuntimeError as exc:
        next_state: HarnessState = dict(state)
        next_state["decision"] = build_non_stop_fallback_decision(
            state,
            state.get("snapshot") or {},
            f"chat_execution_failed:{str(exc).strip()}",
        )
        next_state["turn_count"] = int(state.get("turn_count", 0)) + 1
        next_state["last_result"] = {
            "success": False,
            "error": str(exc),
        }
        return next_state

    next_state: HarnessState = dict(state)
    next_state["turn_count"] = int(state.get("turn_count", 0)) + 1
    next_state["last_result"] = result
    return next_state


def select_current_objective(snapshot: dict[str, Any]) -> dict[str, Any] | None:
    def normalize_objective_list(value: Any) -> list[dict[str, Any]]:
        if isinstance(value, str):
            try:
                value = json.loads(value)
            except json.JSONDecodeError:
                return []
        if isinstance(value, dict):
            value = [value]
        if not isinstance(value, list):
            return []

        normalized: list[dict[str, Any]] = []
        for item in value:
            if not isinstance(item, dict):
                continue
            # Phase-bucket shape: {"phase":1,"objectives":[...]}
            nested = item.get("objectives")
            if isinstance(nested, list):
                for objective in nested:
                    if isinstance(objective, dict):
                        normalized.append(objective)
                continue
            normalized.append(item)
        return normalized

    active_quests = snapshot.get("active_quests") or []
    for quest in active_quests:
        if not isinstance(quest, dict):
            continue
        # Standardize on current_objectives as the authoritative runtime objective feed.
        objective_candidates = normalize_objective_list(quest.get("current_objectives"))

        for objective in objective_candidates:
            if bool(objective.get("completed")):
                continue
            return {
                "quest_id": quest.get("quest_id"),
                "quest_name": quest.get("quest_name"),
                "objective": objective,
            }
    return build_default_storyline_objective(snapshot)


def validate_scripted_target_state(snapshot: dict[str, Any]) -> tuple[bool, str]:
    quest_context = snapshot.get("quest_context")
    if not isinstance(quest_context, list):
        return False, "scripted_target_state_missing_quest_context"

    quest_by_template: dict[str, dict[str, Any]] = {}
    for quest in quest_context:
        if not isinstance(quest, dict):
            continue
        template_id = str(quest.get("source_template_id") or "").strip()
        if template_id in SCRIPTED_TARGET_QUEST_TEMPLATES and template_id not in quest_by_template:
            quest_by_template[template_id] = quest

    missing_templates = [template_id for template_id in SCRIPTED_TARGET_QUEST_TEMPLATES if template_id not in quest_by_template]
    if missing_templates:
        return False, f"scripted_target_state_missing_quests:{','.join(missing_templates)}"

    incomplete_templates: list[str] = []
    for template_id in SCRIPTED_TARGET_QUEST_TEMPLATES:
        quest = quest_by_template[template_id]
        status = str(quest.get("status") or "").strip().lower()
        completed_at = quest.get("completed_at")
        if status != "completed" or not completed_at:
            incomplete_templates.append(template_id)

    if incomplete_templates:
        return False, f"scripted_target_state_incomplete:{','.join(incomplete_templates)}"

    return True, "scripted_target_state_complete"


def has_unresolved_active_quest(snapshot: dict[str, Any]) -> bool:
    active_quests = snapshot.get("active_quests") or []
    if not isinstance(active_quests, list):
        return False
    for quest in active_quests:
        if not isinstance(quest, dict):
            continue
        status = str(quest.get("status") or "").strip().lower()
        if status in {"active", "ready_for_turn_in"}:
            return True
    return False


def node_assess(state: HarnessState) -> HarnessState:
    next_state: HarnessState = dict(state)
    snapshot = state.get("snapshot") or {}
    active_quests = snapshot.get("active_quests") or []
    if not isinstance(active_quests, list):
        raise RuntimeError("Snapshot active_quests is not a list.")

    current_objective = select_current_objective(snapshot)
    if (
        isinstance(current_objective, dict)
        and str(current_objective.get("quest_id")) == DEFAULT_STORYLINE_QUEST_ID
        and bool(state.get("scriptedtesting_complete", False))
        and not has_unresolved_active_quest(snapshot)
    ):
        current_objective = None

    if current_objective is None:
        if bool(state.get("scriptedtesting_complete", False)):
            target_state_complete, target_state_reason = validate_scripted_target_state(snapshot)
            if target_state_complete:
                next_state["run_status"] = "completed"
                next_state["stop_reason"] = target_state_reason
                return next_state
            next_state["run_status"] = "blocked"
            next_state["stop_reason"] = target_state_reason
            return next_state
        if bool(state.get("scriptedtesting_enabled", True)):
            next_state["run_status"] = "running"
            return next_state
        if has_unresolved_active_quest(snapshot):
            next_state["run_status"] = "blocked"
            next_state["stop_reason"] = "active_quest_missing_objectives"
            return next_state
        next_state["run_status"] = "completed"
        next_state["stop_reason"] = "no_open_objectives"
        return next_state

    if str(current_objective.get("quest_id")) == DEFAULT_STORYLINE_QUEST_ID:
        if int(state.get("default_storyline_seed_checks", -1)) == 0 and int(state.get("turn_count", 0)) > 0:
            # Allow one snapshot refresh after deterministic seed before unresolved stop.
            next_state["default_storyline_seed_checks"] = 1
            next_state["run_status"] = "running"
            return next_state
    else:
        next_state["default_storyline_seed_checks"] = -1

    if int(state.get("turn_count", 0)) >= int(state.get("max_turns", 0)):
        next_state["run_status"] = "blocked"
        next_state["stop_reason"] = "max_turns_reached"
        return next_state

    next_state["run_status"] = "running"
    return next_state


def node_notify_and_log_issue(state: HarnessState) -> HarnessState:
    issue_key = state["issue_key"]
    correlation_id = state["correlation_id"]
    reason = state.get("stop_reason", "unknown")
    snapshot = state.get("snapshot") or {}
    decision = state.get("decision") or {}
    last_result = state.get("last_result") or {}

    issue_title = f"Actor harness blocked: {reason}"
    issue_body = {
        "timestamp": now_iso(),
        "correlation_id": correlation_id,
        "campaign_id": state.get("campaign_id"),
        "character_name": state.get("character_name"),
        "character_id": state.get("character_id"),
        "actor_id": state.get("actor_id"),
        "room_id": state.get("room_id"),
        "stop_reason": reason,
        "decision": decision,
        "last_result": last_result,
        "snapshot": snapshot,
    }
    issue_summary = summarize_issue_payload(issue_body)

    ISSUE_LOG_DIR.mkdir(parents=True, exist_ok=True)
    issue_path = ISSUE_LOG_DIR / f"{issue_key}.json"
    issue_path.write_text(json.dumps(issue_body, indent=2) + "\n", encoding="utf-8")

    issue_summary_path = ISSUE_LOG_DIR / f"{issue_key}.summary.json"
    issue_summary_path.write_text(json.dumps(issue_summary, indent=2) + "\n", encoding="utf-8")

    next_state: HarnessState = dict(state)
    next_state["last_result"] = {
        "issue_title": issue_title,
        "issue_summary": issue_summary,
        "issue_path": str(issue_path),
        "issue_summary_path": str(issue_summary_path),
    }
    next_state["run_status"] = "blocked"
    return next_state


def route_after_decision(state: HarnessState) -> str:
    mode = str((state.get("decision") or {}).get("mode", ""))
    if mode == "action":
        return "execute_action"
    if mode == "chat":
        return "execute_chat"
    return "assess"


def route_after_scriptedtesting(state: HarnessState) -> str:
    status = str(state.get("run_status", ""))
    if status == "blocked":
        return "notify_and_log_issue"
    if bool(state.get("scriptedtesting_complete", False)):
        return "decide"
    mode = str((state.get("decision") or {}).get("mode", ""))
    if mode == "action":
        return "execute_action"
    if mode == "chat":
        return "execute_chat"
    return "notify_and_log_issue"


def route_after_assess(state: HarnessState) -> str:
    status = str(state.get("run_status", ""))
    if status == "running":
        return "snapshot"
    if status == "completed":
        return END
    return "notify_and_log_issue"


def build_graph():
    graph = StateGraph(HarnessState)
    graph.add_node("bootstrap", node_bootstrap)
    graph.add_node("snapshot", node_snapshot)
    graph.add_node("scriptedtesting", node_scriptedtesting)
    graph.add_node("decide", node_decide)
    graph.add_node("execute_action", node_execute_action)
    graph.add_node("execute_chat", node_execute_chat)
    graph.add_node("assess", node_assess)
    graph.add_node("notify_and_log_issue", node_notify_and_log_issue)

    graph.add_edge(START, "bootstrap")
    graph.add_edge("bootstrap", "snapshot")
    graph.add_edge("snapshot", "scriptedtesting")
    graph.add_conditional_edges("scriptedtesting", route_after_scriptedtesting)
    graph.add_conditional_edges("decide", route_after_decision)
    graph.add_edge("execute_action", "assess")
    graph.add_edge("execute_chat", "assess")
    graph.add_conditional_edges("assess", route_after_assess)
    graph.add_edge("notify_and_log_issue", END)
    return graph.compile()


def main() -> int:
    parser = argparse.ArgumentParser(description="Run LangGraph actor harness for Burasco quest play.")
    parser.add_argument("--character-name", default="Burasco")
    parser.add_argument("--uid", type=int, default=int(os.environ.get("HARNESS_OWNER_UID") or "0"), help="Owner UID for bootstrap campaign/character resolution.")
    parser.add_argument("--campaign-name", default=f"Burasco LangGraph Run {datetime.now(timezone.utc).strftime('%Y%m%d-%H%M%S')}")
    parser.add_argument("--max-turns", type=int, default=12)
    parser.add_argument("--campaign-id", type=int, default=0, help="Optional existing campaign id.")
    parser.add_argument("--character-id", type=int, default=0, help="Required if --campaign-id is set.")
    parser.add_argument("--actor-id", default="", help="Required if --campaign-id is set.")
    parser.add_argument("--room-id", default="", help="Required if --campaign-id is set.")
    parser.add_argument("--skip-scriptedtesting", action="store_true", help="Skip scripted testing pre-routine and start autonomous harness immediately.")
    args = parser.parse_args()
    enforce_actor_runtime_secret_boundary()

    if args.campaign_id > 0:
        if args.character_id <= 0 or args.actor_id.strip() == "" or args.room_id.strip() == "":
            raise RuntimeError("--campaign-id requires --character-id, --actor-id, and --room-id.")
    elif args.uid <= 0:
        raise RuntimeError("Bootstrap mode requires --uid (or HARNESS_OWNER_UID).")

    correlation_id = f"langgraph-harness-{datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S')}"
    issue_key = f"harness-{datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S')}"
    initial_state: HarnessState = {
        "campaign_id": int(args.campaign_id),
        "campaign_name": args.campaign_name.strip(),
        "owner_uid": int(args.uid),
        "character_name": args.character_name.strip(),
        "character_id": int(args.character_id),
        "actor_id": args.actor_id.strip(),
        "room_id": args.room_id.strip(),
        "turn_count": 0,
        "max_turns": max(1, int(args.max_turns)),
        "run_status": "running",
        "correlation_id": correlation_id,
        "issue_key": issue_key,
        "scriptedtesting_enabled": not bool(args.skip_scriptedtesting),
        "scriptedtesting_complete": bool(args.skip_scriptedtesting),
        "scripted_step_index": 0,
        "scripted_pending_step_id": "",
    }
    if initial_state["character_name"] == "":
        raise RuntimeError("--character-name cannot be empty.")
    if initial_state["campaign_name"] == "" and initial_state["campaign_id"] <= 0:
        raise RuntimeError("--campaign-name cannot be empty when creating a new campaign.")

    graph = build_graph()
    final_state = graph.invoke(initial_state)
    TRACE_LOG_DIR.mkdir(parents=True, exist_ok=True)
    trace_path = TRACE_LOG_DIR / f"{correlation_id}.json"
    trace_path.write_text(json.dumps(final_state, indent=2) + "\n", encoding="utf-8")
    print(json.dumps({"status": final_state.get("run_status"), "trace_path": str(trace_path), "state": final_state}, indent=2))
    return 0 if final_state.get("run_status") == "completed" else 1


if __name__ == "__main__":
    raise SystemExit(main())
