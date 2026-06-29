import json
import os
import tempfile
import unittest
from pathlib import Path

from orchestrator.runtime_graph.consume_replies import run_consume_replies
from orchestrator.runtime_graph.engine import LangGraphDeps, run_tick


def _write_file(path: Path, content: str = "") -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


class TestConsumeRepliesGraph(unittest.TestCase):
    def test_run_consume_replies_noops_when_reply_table_missing(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            _write_file(root / "org-chart" / "agents" / "agents.yaml", "- id: ceo-copilot-2\n")
            _write_file(root / "scripts" / "is-agent-paused.sh", "#!/usr/bin/env bash\nprintf '%s\n' false\n")
            drush = root / "site" / "vendor" / "bin" / "drush"
            _write_file(drush, "#!/usr/bin/env bash\nexit 0\n")
            drush.chmod(0o755)

            old_site_dir = os.environ.get("FORSETI_SITE_DIR")
            os.environ["FORSETI_SITE_DIR"] = str(root / "site")
            try:
                def run_cmd(cmd, timeout=0):
                    shell = cmd[-1] if cmd and cmd[0] == "bash" and cmd[1] == "-lc" else " ".join(cmd)
                    if 'tableExists("copilot_agent_tracker_replies")' in shell:
                        return 0, "no"
                    return 0, ""

                summary = run_consume_replies(repo_root=root, run_cmd=run_cmd)
            finally:
                if old_site_dir is None:
                    os.environ.pop("FORSETI_SITE_DIR", None)
                else:
                    os.environ["FORSETI_SITE_DIR"] = old_site_dir

        self.assertEqual(summary["rc"], 0)
        self.assertFalse(summary["reply_table_exists"])
        self.assertEqual(summary["pending_count"], 0)
        self.assertEqual(summary["created_count"], 0)
        self.assertEqual(summary["warning_count"], 1)

    def test_run_consume_replies_creates_inbox_items_and_reports_reroutes(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            _write_file(root / "org-chart" / "agents" / "agents.yaml", "- id: ceo-copilot-2\n- id: pm-forseti\n")
            _write_file(root / "scripts" / "is-agent-paused.sh", "#!/usr/bin/env bash\nprintf '%s\n' false\n")
            drush = root / "site" / "vendor" / "bin" / "drush"
            _write_file(drush, "#!/usr/bin/env bash\nexit 0\n")
            drush.chmod(0o755)
            _write_file(root / "sessions" / "ceo-copilot-2" / "inbox" / "orig-item" / "command.md", "seed\n")

            old_site_dir = os.environ.get("FORSETI_SITE_DIR")
            os.environ["FORSETI_SITE_DIR"] = str(root / "site")
            try:
                def run_cmd(cmd, timeout=0):
                    shell = cmd[-1] if cmd and cmd[0] == "bash" and cmd[1] == "-lc" else " ".join(cmd)
                    if "is-agent-paused.sh" in shell:
                        return 0, "false"
                    if 'tableExists("copilot_agent_tracker_replies")' in shell:
                        return 0, "yes"
                    if '->select("copilot_agent_tracker_replies","r")' in shell:
                        return 0, json.dumps([
                            {
                                "id": 1,
                                "to_agent_id": "unknown-seat",
                                "in_reply_to": "orig-item",
                                "message": "Please investigate.",
                                "created": 0,
                            }
                        ])
                    if '->update("copilot_agent_tracker_replies")' in shell:
                        return 0, ""
                    return 0, ""

                summary = run_consume_replies(repo_root=root, run_cmd=run_cmd)
            finally:
                if old_site_dir is None:
                    os.environ.pop("FORSETI_SITE_DIR", None)
                else:
                    os.environ["FORSETI_SITE_DIR"] = old_site_dir

            inbox_dir = root / "sessions" / "ceo-copilot-2" / "inbox"
            created_dirs = [p for p in inbox_dir.iterdir() if p.is_dir() and p.name != "orig-item"]
            created_dir_count = len(created_dirs)
            command_exists = created_dir_count == 1 and (created_dirs[0] / "command.md").exists()
            roi_exists = created_dir_count == 1 and (created_dirs[0] / "roi.txt").exists()

        self.assertEqual(summary["rc"], 0)
        self.assertTrue(summary["reply_table_exists"])
        self.assertEqual(summary["pending_count"], 1)
        self.assertEqual(summary["valid_count"], 1)
        self.assertEqual(summary["created_count"], 1)
        self.assertEqual(summary["rerouted_count"], 1)
        self.assertEqual(summary["archived_count"], 1)
        self.assertEqual(created_dir_count, 1)
        self.assertTrue(command_exists)
        self.assertTrue(roi_exists)

    def test_run_tick_logs_structured_consume_replies_summary(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            _write_file(root / "org-chart" / "agents" / "agents.yaml", "- id: ceo-copilot-2\n")
            _write_file(root / "scripts" / "is-agent-paused.sh", "#!/usr/bin/env bash\nprintf '%s\n' false\n")
            drush = root / "site" / "vendor" / "bin" / "drush"
            _write_file(drush, "#!/usr/bin/env bash\nexit 0\n")
            drush.chmod(0o755)

            old_root = os.environ.get("COPILOT_HQ_ROOT")
            old_site_dir = os.environ.get("FORSETI_SITE_DIR")
            os.environ["COPILOT_HQ_ROOT"] = str(root)
            os.environ["FORSETI_SITE_DIR"] = str(root / "site")
            try:
                class Provider:
                    def run_one(self, agent_id: str):
                        return 0, agent_id

                def run_cmd(cmd, timeout=0):
                    shell = cmd[-1] if cmd and cmd[0] == "bash" and cmd[1] == "-lc" else " ".join(cmd)
                    if "is-agent-paused.sh" in shell:
                        return 0, "false"
                    if 'tableExists("copilot_agent_tracker_replies")' in shell:
                        return 0, "no"
                    return 0, ""

                deps = LangGraphDeps(
                    run_cmd=run_cmd,
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
                    publish_enabled=False,
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
                if old_site_dir is None:
                    os.environ.pop("FORSETI_SITE_DIR", None)
                else:
                    os.environ["FORSETI_SITE_DIR"] = old_site_dir

        consume_log = next(entry for entry in state["log"] if entry.get("step") == "consume_replies")
        self.assertIn("pending_count", consume_log)
        self.assertIn("warning_count", consume_log)
        self.assertEqual(consume_log["rc"], 0)
        self.assertFalse(consume_log["reply_table_exists"])


if __name__ == "__main__":
    unittest.main()
