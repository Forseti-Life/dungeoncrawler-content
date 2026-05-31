import os
import tempfile
import threading
import time
import unittest
from pathlib import Path

from orchestrator.runtime_graph.engine import LangGraphDeps, _exec_worker_limit, run_tick


class TestExecAgentParallelism(unittest.TestCase):
    def test_bedrock_worker_limit_defaults_to_four(self):
        old_backend = os.environ.get("HQ_AGENTIC_BACKEND")
        old_bedrock = os.environ.get("AGENT_EXEC_MAX_CONCURRENT_BEDROCK")
        old_shared = os.environ.get("AGENT_EXEC_MAX_CONCURRENT")
        old_workers = os.environ.get("ORCHESTRATOR_EXEC_WORKERS")
        os.environ["HQ_AGENTIC_BACKEND"] = "bedrock"
        os.environ.pop("AGENT_EXEC_MAX_CONCURRENT_BEDROCK", None)
        os.environ.pop("AGENT_EXEC_MAX_CONCURRENT", None)
        os.environ.pop("ORCHESTRATOR_EXEC_WORKERS", None)
        try:
            self.assertEqual(_exec_worker_limit(8), 4)
            self.assertEqual(_exec_worker_limit(3), 3)
        finally:
            if old_backend is None:
                os.environ.pop("HQ_AGENTIC_BACKEND", None)
            else:
                os.environ["HQ_AGENTIC_BACKEND"] = old_backend
            if old_bedrock is None:
                os.environ.pop("AGENT_EXEC_MAX_CONCURRENT_BEDROCK", None)
            else:
                os.environ["AGENT_EXEC_MAX_CONCURRENT_BEDROCK"] = old_bedrock
            if old_shared is None:
                os.environ.pop("AGENT_EXEC_MAX_CONCURRENT", None)
            else:
                os.environ["AGENT_EXEC_MAX_CONCURRENT"] = old_shared
            if old_workers is None:
                os.environ.pop("ORCHESTRATOR_EXEC_WORKERS", None)
            else:
                os.environ["ORCHESTRATOR_EXEC_WORKERS"] = old_workers

    def test_exec_agents_runs_in_parallel_up_to_worker_limit(self):
        scheduled = [
            type("Agent", (), {"agent_id": "pm-forseti"})(),
            type("Agent", (), {"agent_id": "pm-dungeoncrawler"})(),
            type("Agent", (), {"agent_id": "qa-dungeoncrawler"})(),
        ]

        class Provider:
            def __init__(self):
                self.lock = threading.Lock()
                self.current = 0
                self.max_seen = 0

            def run_one(self, agent_id: str):
                with self.lock:
                    self.current += 1
                    self.max_seen = max(self.max_seen, self.current)
                time.sleep(0.05)
                with self.lock:
                    self.current -= 1
                return 0, agent_id

        provider = Provider()

        old_root = os.environ.get("COPILOT_HQ_ROOT")
        old_backend = os.environ.get("HQ_AGENTIC_BACKEND")
        old_bedrock = os.environ.get("AGENT_EXEC_MAX_CONCURRENT_BEDROCK")
        with tempfile.TemporaryDirectory() as td:
            os.environ["COPILOT_HQ_ROOT"] = td
            os.environ["HQ_AGENTIC_BACKEND"] = "bedrock"
            os.environ["AGENT_EXEC_MAX_CONCURRENT_BEDROCK"] = "2"
            try:
                deps = LangGraphDeps(
                    run_cmd=lambda *_args, **_kwargs: (0, ""),
                    dispatch_commands_step=lambda log: log.append({"step": "dispatch_commands", "dispatched": []}),
                    release_cycle_step=lambda log: log.append({"step": "release_cycle", "teams": []}),
                    coordinated_push_step=lambda log: log.append({"step": "coordinated_push", "status": "noop"}),
                    prioritized_agents=lambda: scheduled,
                    health_check_step=lambda _provider, log: log.append(
                        {"step": "health_check", "idle_with_inbox": 0, "blocked_count": 0, "remediated": []}
                    ),
                    now_ts=lambda: 10_000,
                    kpi_monitor_cmd=["true"],
                )

                state, _, _ = run_tick(
                    provider,
                    agent_cap=3,
                    publish_enabled=True,
                    kpi_interval=999_999,
                    kpi_last_run=10_000,
                    release_cycle_interval=999_999,
                    release_cycle_last_run=10_000,
                    deps=deps,
                )
            finally:
                if old_root is None:
                    os.environ.pop("COPILOT_HQ_ROOT", None)
                else:
                    os.environ["COPILOT_HQ_ROOT"] = old_root
                if old_backend is None:
                    os.environ.pop("HQ_AGENTIC_BACKEND", None)
                else:
                    os.environ["HQ_AGENTIC_BACKEND"] = old_backend
                if old_bedrock is None:
                    os.environ.pop("AGENT_EXEC_MAX_CONCURRENT_BEDROCK", None)
                else:
                    os.environ["AGENT_EXEC_MAX_CONCURRENT_BEDROCK"] = old_bedrock

        exec_log = next(entry for entry in state["log"] if entry.get("step") == "exec_agents")
        self.assertEqual(exec_log["workers"], 2)
        self.assertEqual(provider.max_seen, 2)
        self.assertEqual([item["agent"] for item in exec_log["ran"]], [a.agent_id for a in scheduled])


if __name__ == "__main__":
    unittest.main()
