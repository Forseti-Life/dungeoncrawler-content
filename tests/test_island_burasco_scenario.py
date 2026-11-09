import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))

from langgraph_actor_harness.island_scenario import (
    EXPECTED_ROOMS,
    connection_for,
    island_objectives,
    matching_waymark,
    next_room,
    room_move_hex,
    run_island_scenario,
)


class IslandBurascoScenarioTest(unittest.TestCase):
    def test_route_backtracks_and_prioritizes_planned_rooms(self):
        graph = {"a": {"b"}, "b": {"a", "c"}, "c": {"b", "d"}}
        self.assertEqual("b", next_room("a", graph, {"a", "b"}, {"d"}))
        self.assertEqual("c", next_room("b", graph, {"a", "b"}, {"c"}))

    def test_missing_or_ambiguous_waymark_blocks(self):
        snapshot = {"active_room_id": "room-0", "room_item_instances": [
            {"instance_id": "one", "definition_id": "marker", "room_id": "room-0"},
        ]}
        self.assertEqual("one", matching_waymark(snapshot, "marker"))
        with self.assertRaisesRegex(RuntimeError, "island_waymark_missing_or_ambiguous"):
            matching_waymark(snapshot, "unknown")
        with self.assertRaisesRegex(RuntimeError, "island_waymark_missing_or_ambiguous"):
            matching_waymark({**snapshot, "room_item_instances": snapshot["room_item_instances"] * 2}, "marker")

    def test_canonical_move_and_exit_contract(self):
        snapshot = {
            "active_room_id": "room-0",
            "actor_entity": {"placement": {"hex": {"q": 0, "r": 0}}},
            "active_room": {"hexes": [{"q": 0, "r": 0}, {"q": 1, "r": 0}]},
            "connected_rooms": [{"room_id": "room-1", "connection": {
                "connection_id": "exit", "from_room_id": "room-0", "from_hex": {"q": 1, "r": 0},
                "is_passable": True, "is_discovered": True,
            }}],
        }
        self.assertEqual({"q": 1, "r": 0}, room_move_hex(snapshot))
        self.assertEqual({"target_room_id": "room-1", "connection_id": "exit",
                          "target_hex": {"q": 1, "r": 0}}, connection_for(snapshot, "room-1"))

    def run_fake_island(self, include_npc=True, include_markers=True):
        current = [0]
        position = [{"q": 0, "r": 0}]
        completed = set()
        objectives = [
            objective
            for index in range(EXPECTED_ROOMS)
            for objective in (
                {"objective_id": f"explore-{index}", "type": "explore", "location_id": f"room-{index}"},
                {"objective_id": f"interact-{index}", "type": "interact",
                 "location_id": f"room-{index}", "target": f"marker-{index}"},
            )
        ]

        def snapshot_actor(state):
            index = current[0]
            if index == 0:
                completed.add("explore-0")
            neighbors = [neighbor for neighbor in (index - 1, index + 1)
                         if 0 <= neighbor < EXPECTED_ROOMS]
            snapshot = {
                "module_release_id": "fixture",
                "active_room_id": f"room-{index}",
                "actor_entity": {"placement": {"hex": position[0]}},
                "active_room": {"hexes": [{"q": 0, "r": 0}, {"q": 1, "r": 0}]},
                "connected_rooms": [
                    {"room_id": f"room-{neighbor}", "connection": {
                        "connection_id": f"exit-{index}-{neighbor}",
                        "from_room_id": f"room-{index}",
                        "from_hex": {"q": 1, "r": 0},
                        "is_passable": True, "is_discovered": True,
                    }} for neighbor in neighbors
                ],
                "visible_npcs": ([{"instance_id": "npc-0", "entity_ref": {"content_id": "island-npc"}}]
                                 if include_npc and index == 0 else
                                 [{"instance_id": "familiar-0", "entity_ref": {"content_id": "familiar_standard"}}]
                                 if index == 0 else []),
                "room_item_instances": ([{"instance_id": f"waymark-{index}",
                                         "definition_id": f"marker-{index}",
                                         "room_id": f"room-{index}"}]
                                     if include_markers else []),
                "quest_context": [{
                    "source_template_id": "island-original-story-v1",
                    "status": "completed" if len(completed) == len(objectives) else "active",
                    "objective_states": [{"objectives": [
                        {**obj, "completed": obj["objective_id"] in completed} for obj in objectives
                    ]}],
                }],
            }
            return {**state, "snapshot": snapshot}

        def execute_action(state):
            payload = state["decision"]["tool_payload"]
            if payload["type"] == "move":
                position[0] = payload["params"]["to_hex"]
            elif payload["type"] == "transition":
                current[0] = int(payload["params"]["target_room_id"].split("-")[-1])
                position[0] = {"q": 0, "r": 0}
                completed.add(f"explore-{current[0]}")
            elif payload["type"] == "interact":
                completed.add(f"interact-{current[0]}")
            return {**state, "turn_count": state["turn_count"] + 1,
                    "last_result": {"success": True}}

        def decision(name, payload, reason):
            return {"tool_name": name, "tool_payload": payload, "reason": reason}

        return run_island_scenario(
            {"actor_id": "burasco", "room_id": "room-0", "turn_count": 0,
             "max_turns": 80, "module_release_id": "fixture"},
            snapshot_actor, execute_action, decision,
            lambda _state, _decision: None, lambda _state: None,
        )

    def test_full_journal_and_actor_conversation_pass(self):
        result = self.run_fake_island()
        self.assertEqual("completed", result["run_status"])
        self.assertEqual(EXPECTED_ROOMS, len(result["island_report"]["visited_rooms"]))
        self.assertEqual(EXPECTED_ROOMS, len(result["island_report"]["interacted_objective_ids"]))
        self.assertEqual(["npc-0"], result["island_report"]["talked_actor_ids"])

    def test_missing_actors_and_journal_items_are_not_success(self):
        result = self.run_fake_island(include_npc=False, include_markers=False)
        self.assertEqual("blocked", result["run_status"])
        self.assertIn("island_npcs_missing", result["stop_reason"])
        self.assertIn("island_waymark_missing", result["stop_reason"])
        self.assertIn("island_journal_incomplete", result["stop_reason"])

    def test_mismatched_campaign_release_blocks_before_actions(self):
        def snapshot_actor(state):
            return {**state, "snapshot": {"module_release_id": "other-release"}}

        with self.assertRaisesRegex(RuntimeError, "island_campaign_release_mismatch"):
            run_island_scenario(
                {"module_release_id": "fixture", "turn_count": 0, "max_turns": 80},
                snapshot_actor, lambda state: state, lambda name, payload, reason: {},
                lambda state, decision: None, lambda state: None,
            )


if __name__ == "__main__":
    unittest.main()
