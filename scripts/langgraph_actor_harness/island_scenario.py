from __future__ import annotations

import json
from collections import deque
from typing import Any, Callable


QUEST_TEMPLATE = "island-original-story-v1"
EXPECTED_ROOMS = 10


def island_objectives(snapshot: dict[str, Any]) -> tuple[dict[str, Any], list[dict[str, Any]]]:
    quests = snapshot.get("quest_context")
    if not isinstance(quests, list):
        raise RuntimeError("island_journal_missing: quest_context is unavailable")
    matches = [quest for quest in quests if isinstance(quest, dict) and quest.get("source_template_id") == QUEST_TEMPLATE]
    if len(matches) != 1:
        raise RuntimeError(f"island_journal_missing: expected one {QUEST_TEMPLATE} quest, found {len(matches)}")
    quest = matches[0]
    phases = quest.get("objective_states") or quest.get("generated_objectives")
    if isinstance(phases, str):
        phases = json.loads(phases)
    if not isinstance(phases, list):
        raise RuntimeError("island_journal_invalid: objective phases are missing")
    objectives = [obj for phase in phases if isinstance(phase, dict)
                  for obj in phase.get("objectives", []) if isinstance(obj, dict)]
    if not objectives:
        raise RuntimeError("island_journal_invalid: no objectives")
    return quest, objectives


def next_room(current: str, graph: dict[str, set[str]], visited: set[str], planned: set[str]) -> str | None:
    frontier = deque([(current, [])])
    seen = {current}
    while frontier:
        room, path = frontier.popleft()
        for neighbor in sorted(graph.get(room, set()), key=lambda value: (value not in planned, value)):
            if neighbor not in visited:
                return (path + [neighbor])[0]
            if neighbor not in seen:
                seen.add(neighbor)
                frontier.append((neighbor, path + [neighbor]))
    return None


def connection_for(snapshot: dict[str, Any], target_room: str) -> dict[str, Any]:
    current = snapshot.get("active_room_id")
    for connected in snapshot.get("connected_rooms", []):
        if not isinstance(connected, dict) or connected.get("room_id") != target_room:
            continue
        connection = connected.get("connection")
        if not isinstance(connection, dict) or not connection.get("is_passable") or not connection.get("is_discovered"):
            raise RuntimeError(f"island_route_blocked: {current} -> {target_room}")
        origin = connection.get("from_hex") if connection.get("from_room_id") == current else connection.get("to_hex")
        if not isinstance(origin, dict) or not isinstance(origin.get("q"), int) or not isinstance(origin.get("r"), int):
            raise RuntimeError(f"island_exit_hex_missing: {current} -> {target_room}")
        return {
            "target_room_id": target_room,
            "connection_id": connection["connection_id"],
            "target_hex": {"q": origin["q"], "r": origin["r"]},
        }
    raise RuntimeError(f"island_exit_missing: {current} -> {target_room}")


def room_move_hex(snapshot: dict[str, Any]) -> dict[str, int]:
    position = (snapshot.get("actor_entity") or {}).get("placement", {}).get("hex")
    hexes = (snapshot.get("active_room") or {}).get("hexes")
    if not isinstance(position, dict) or not isinstance(hexes, list):
        raise RuntimeError("island_move_geometry_missing")
    for candidate in hexes:
        if not isinstance(candidate, dict):
            continue
        q, r = candidate.get("q"), candidate.get("r")
        if (isinstance(q, int) and isinstance(r, int)
                and max(abs(q - position.get("q", q)), abs(r - position.get("r", r)),
                        abs(q + r - position.get("q", q) - position.get("r", r))) == 1):
            return {"q": q, "r": r}
    raise RuntimeError(f"island_move_destination_missing: {snapshot.get('active_room_id')}")


def matching_waymark(snapshot: dict[str, Any], definition_id: str) -> str:
    matches = [
        entity.get("instance_id")
        for entity in snapshot.get("room_item_instances", [])
        if isinstance(entity, dict)
        and entity.get("definition_id") == definition_id
        and entity.get("room_id") == snapshot.get("active_room_id")
    ]
    if len(matches) != 1 or not isinstance(matches[0], str) or not matches[0]:
        raise RuntimeError(f"island_waymark_missing_or_ambiguous: {snapshot.get('active_room_id')} {definition_id}")
    return matches[0]


def run_island_scenario(
    state: dict[str, Any],
    snapshot_actor: Callable[[dict[str, Any]], dict[str, Any]],
    execute_action: Callable[[dict[str, Any]], dict[str, Any]],
    make_decision: Callable[[str, dict[str, Any], str], dict[str, Any]],
    validate_decision: Callable[[dict[str, Any], dict[str, Any]], str | None],
    record_progress: Callable[[dict[str, Any]], None],
) -> dict[str, Any]:
    visited: set[str] = set()
    talked: set[str] = set()
    graph: dict[str, set[str]] = {}
    failures: list[str] = []
    processed: set[str] = set()
    state = snapshot_actor(state)
    record_progress(state)
    if state["snapshot"].get("module_release_id") != state.get("module_release_id"):
        raise RuntimeError("island_campaign_release_mismatch: campaign is not pinned to the selected Island release")
    quest, objectives = island_objectives(state["snapshot"])
    planned_rooms = {str(obj.get("location_id") or obj.get("location") or "")
                     for obj in objectives if obj.get("type") == "explore"}
    if len(planned_rooms) != EXPECTED_ROOMS or "" in planned_rooms:
        raise RuntimeError(f"island_journal_room_count_invalid: expected {EXPECTED_ROOMS}, found {len(planned_rooms)}")

    def perform(name: str, payload: dict[str, Any]) -> None:
        nonlocal state
        if state["turn_count"] >= state["max_turns"]:
            raise RuntimeError(f"island_max_turns_reached: {state['max_turns']}")
        state["decision"] = make_decision(name, payload, f"island_burasco:{name}")
        contract_error = validate_decision(state, state["decision"])
        if contract_error is not None:
            raise RuntimeError(f"island_action_contract_invalid: {name}: {contract_error}")
        state = execute_action(state)
        record_progress(state)
        result = state.get("last_result") or {}
        if state.get("run_status") == "blocked" or result.get("success") is not True:
            raise RuntimeError(f"island_action_failed: {name}: {state.get('stop_reason') or result.get('error')}")
        state = snapshot_actor(state)
        record_progress(state)

    while planned_rooms - visited and state["turn_count"] < state["max_turns"]:
        snapshot = state["snapshot"]
        room = str(snapshot.get("active_room_id") or "")
        if not room:
            raise RuntimeError("island_active_room_missing")
        neighbors = {str(entry["room_id"]) for entry in snapshot.get("connected_rooms", [])
                     if isinstance(entry, dict) and entry.get("room_id")}
        graph[room] = neighbors
        if room not in visited:
            visited.add(room)
            destination = room_move_hex(snapshot)
            move_action = "step" if snapshot.get("phase") == "encounter" else "move"
            perform(move_action, {"type": move_action, "params": {"to_hex": destination}})
            position = (state["snapshot"].get("actor_entity") or {}).get("placement", {}).get("hex")
            if not isinstance(position, dict) or (position.get("q"), position.get("r")) != (destination["q"], destination["r"]):
                raise RuntimeError(f"island_move_not_persisted: {room}")

            for entity in state["snapshot"].get("visible_npcs", []):
                if not isinstance(entity, dict):
                    continue
                actor_id = str(entity.get("instance_id") or "")
                if (entity.get("entity_ref") or {}).get("content_id") == "familiar_standard":
                    continue
                if actor_id and actor_id not in talked:
                    perform("talk", {"type": "talk", "target": actor_id,
                                     "params": {"message": "What should we learn about this island?"}})
                    talked.add(actor_id)

            room_objectives = [obj for obj in objectives if obj.get("type") == "interact"
                               and (obj.get("location_id") or obj.get("location")) == room]
            for objective in room_objectives:
                target = str(objective.get("target") or "")
                if not target:
                    failures.append(f"island_journal_target_missing: {room}")
                    continue
                try:
                    marker = matching_waymark(state["snapshot"], target)
                except RuntimeError as exc:
                    failures.append(str(exc))
                    continue
                perform("interact", {"type": "interact", "target": marker, "params": {}})
                processed.add(str(objective.get("objective_id") or ""))

        if not planned_rooms - visited:
            break
        target_room = next_room(room, graph, visited, planned_rooms)
        if target_room is None:
            break
        perform("transition", {"type": "transition", "params": connection_for(state["snapshot"], target_room)})
        if state["snapshot"].get("active_room_id") != target_room:
            raise RuntimeError(f"island_transition_not_persisted: {room} -> {target_room}")

    final_quest, final_objectives = island_objectives(state["snapshot"])
    incomplete = [str(obj.get("objective_id") or "<unnamed>") for obj in final_objectives if not obj.get("completed")]
    if not planned_rooms.issubset(visited):
        failures.append(f"island_rooms_unvisited: {sorted(planned_rooms - visited)}")
    if not talked:
        failures.append("island_npcs_missing: no placed NPCs to talk to")
    if incomplete or final_quest.get("status") != "completed":
        failures.append(f"island_journal_incomplete: status={final_quest.get('status')} objectives={incomplete}")
    state["island_report"] = {
        "release_id": state.get("module_release_id"),
        "quest_template_id": QUEST_TEMPLATE,
        "visited_rooms": sorted(visited),
        "talked_actor_ids": sorted(talked),
        "interacted_objective_ids": sorted(processed),
        "incomplete_objective_ids": incomplete,
        "failures": failures,
    }
    state["run_status"] = "blocked" if failures else "completed"
    state["stop_reason"] = "; ".join(failures) if failures else "island_journal_and_actor_coverage_complete"
    return state
