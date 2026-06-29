import json
import os
import tempfile
import unittest
from pathlib import Path

from orchestrator.runtime_graph.engine import LangGraphDeps, _write_tick_telemetry, run_tick


class TestLangGraphParity(unittest.TestCase):
    def test_write_tick_telemetry_requires_exact_step_sequence(self):
        with tempfile.TemporaryDirectory() as td:
            old_root = os.environ.get("COPILOT_HQ_ROOT")
            os.environ["COPILOT_HQ_ROOT"] = td
            try:
                state = {
                    "ts": "2026-04-17T20:06:24+00:00",
                    "publish_enabled": True,
                    "agent_cap": 1,
                    "provider": "TestProvider",
                    "selected_agents": ["pm-forseti"],
                    "log": [
                        {"step": "consume_replies", "rc": 0},
                        {"step": "dispatch_commands", "dispatched": []},
                        {"step": "release_cycle", "teams": []},
                        {"step": "coordinated_push", "status": "noop"},
                        {"step": "pick_agents", "selected": ["pm-forseti"]},
                        {"step": "exec_agents", "ran": [{"agent": "pm-forseti", "rc": 0}]},
                        {"step": "health_check", "blocked_count": 0, "idle_with_inbox": 0, "remediated": []},
                        {"step": "publish", "rc": 0},
                    ],
                }
                _write_tick_telemetry(state)
                parity = json.loads(
                    (Path(td) / "inbox" / "responses" / "langgraph-parity-latest.json").read_text(encoding="utf-8")
                )
            finally:
                if old_root is None:
                    os.environ.pop("COPILOT_HQ_ROOT", None)
                else:
                    os.environ["COPILOT_HQ_ROOT"] = old_root

        self.assertFalse(parity["steps"]["match"])
        self.assertFalse(parity["parity_ok"])
        self.assertIn("step order mismatch", "\n".join(parity["errors"]))

    def test_write_tick_telemetry_fails_parity_on_selected_agent_drift(self):
        with tempfile.TemporaryDirectory() as td:
            old_root = os.environ.get("COPILOT_HQ_ROOT")
            os.environ["COPILOT_HQ_ROOT"] = td
            try:
                state = {
                    "ts": "2026-04-17T20:06:24+00:00",
                    "publish_enabled": True,
                    "agent_cap": 1,
                    "provider": "TestProvider",
                    "selected_agents": ["qa-forseti"],
                    "log": [
                        {"step": "consume_replies", "rc": 0},
                        {"step": "dispatch_commands", "dispatched": []},
                        {"step": "release_cycle", "teams": []},
                        {"step": "coordinated_push", "status": "noop"},
                        {"step": "pick_agents", "selected": ["pm-forseti"]},
                        {"step": "exec_agents", "ran": [{"agent": "qa-forseti", "rc": 0}]},
                        {"step": "health_check", "blocked_count": 0, "idle_with_inbox": 0, "remediated": []},
                        {"step": "kpi_monitor", "skipped": True},
                        {"step": "publish", "rc": 0},
                    ],
                }
                _write_tick_telemetry(state)
                parity = json.loads(
                    (Path(td) / "inbox" / "responses" / "langgraph-parity-latest.json").read_text(encoding="utf-8")
                )
            finally:
                if old_root is None:
                    os.environ.pop("COPILOT_HQ_ROOT", None)
                else:
                    os.environ["COPILOT_HQ_ROOT"] = old_root

        self.assertFalse(parity["selected_agents"]["match"])
        self.assertFalse(parity["parity_ok"])
        self.assertIn("selected_agents mismatch", "\n".join(parity["errors"]))

    def test_run_tick_logs_kpi_monitor_skip_to_preserve_step_parity(self):
        with tempfile.TemporaryDirectory() as td:
            old_root = os.environ.get("COPILOT_HQ_ROOT")
            os.environ["COPILOT_HQ_ROOT"] = td
            try:
                class Provider:
                    def run_one(self, agent_id: str):
                        return 0, agent_id

                deps = LangGraphDeps(
                    run_cmd=lambda *_args, **_kwargs: (0, ""),
                    dispatch_commands_step=lambda log: log.append({"step": "dispatch_commands", "dispatched": []}),
                    release_cycle_step=lambda log: log.append({"step": "release_cycle", "teams": []}),
                    coordinated_push_step=lambda log: log.append({"step": "coordinated_push", "status": "noop"}),
                    prioritized_agents=lambda: [],
                    health_check_step=lambda _provider, log: log.append(
                        {"step": "health_check", "idle_with_inbox": 0, "blocked_count": 0, "remediated": []}
                    ),
                    now_ts=lambda: 10_000,
                    kpi_monitor_cmd=["true"],
                )

                state, _, _ = run_tick(
                    Provider(),
                    agent_cap=0,
                    publish_enabled=True,
                    kpi_interval=999_999,
                    kpi_last_run=10_000,
                    release_cycle_interval=999_999,
                    release_cycle_last_run=10_000,
                    deps=deps,
                )
                parity = json.loads(
                    (Path(td) / "inbox" / "responses" / "langgraph-parity-latest.json").read_text(encoding="utf-8")
                )
            finally:
                if old_root is None:
                    os.environ.pop("COPILOT_HQ_ROOT", None)
                else:
                    os.environ["COPILOT_HQ_ROOT"] = old_root

        self.assertIn({"step": "kpi_monitor", "skipped": True}, state["log"])
        self.assertTrue(parity["steps"]["match"])
        self.assertTrue(parity["parity_ok"])


if __name__ == "__main__":
    unittest.main()
