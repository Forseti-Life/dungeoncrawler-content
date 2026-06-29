import tempfile
import unittest
from pathlib import Path

import orchestrator.run as run
from orchestrator.runtime_graph.engine import LangGraphDeps, run_tick


class TestParallelReleasePriority(unittest.TestCase):
    def test_next_release_agents_fill_spare_capacity_before_other_work(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "org-chart" / "agents").mkdir(parents=True)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)

            (root / "org-chart" / "agents" / "agents.yaml").write_text(
                "\n".join(
                    [
                        "agents:",
                        "  - id: dev-forseti",
                        "  - id: pm-forseti",
                        "  - id: pm-infra",
                    ]
                )
                + "\n",
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
                "20260412-forseti-release-l\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "forseti.next_release_id").write_text(
                "20260412-forseti-release-m\n", encoding="utf-8"
            )

            current_item = root / "sessions" / "dev-forseti" / "inbox" / "20260415-impl-20260412-forseti-release-l"
            current_item.mkdir(parents=True)
            (current_item / "roi.txt").write_text("7\n", encoding="utf-8")

            next_item = root / "sessions" / "pm-forseti" / "inbox" / "20260415-groom-20260412-forseti-release-m"
            next_item.mkdir(parents=True)
            (next_item / "roi.txt").write_text("25\n", encoding="utf-8")

            other_item = root / "sessions" / "pm-infra" / "inbox" / "20260415-ops-cleanup"
            other_item.mkdir(parents=True)
            (other_item / "roi.txt").write_text("999\n", encoding="utf-8")

            old_root = run.REPO_ROOT
            run.REPO_ROOT = root
            try:
                agents = run._prioritized_agents()
            finally:
                run.REPO_ROOT = old_root

            ordered = [agent.agent_id for agent in agents]
            self.assertEqual(ordered, ["dev-forseti", "pm-forseti", "pm-infra"])
            self.assertTrue(agents[0].has_release_work)
            self.assertTrue(agents[1].has_next_release_work)
            self.assertFalse(agents[2].has_release_work)
            self.assertFalse(agents[2].has_next_release_work)

    def test_next_release_spillover_precedes_second_same_team_current_release(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "org-chart" / "agents").mkdir(parents=True)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)

            (root / "org-chart" / "agents" / "agents.yaml").write_text(
                "\n".join(
                    [
                        "agents:",
                        "  - id: dev-forseti",
                        "    role: software-developer",
                        "  - id: qa-forseti",
                        "    role: tester",
                        "  - id: pm-forseti",
                        "    role: product-manager",
                    ]
                )
                + "\n",
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
                "20260412-forseti-release-l\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "forseti.next_release_id").write_text(
                "20260412-forseti-release-m\n", encoding="utf-8"
            )

            current_dev = root / "sessions" / "dev-forseti" / "inbox" / "20260415-impl-20260412-forseti-release-l"
            current_dev.mkdir(parents=True)
            (current_dev / "roi.txt").write_text("20\n", encoding="utf-8")

            current_qa = root / "sessions" / "qa-forseti" / "inbox" / "20260415-suite-20260412-forseti-release-l"
            current_qa.mkdir(parents=True)
            (current_qa / "roi.txt").write_text("10\n", encoding="utf-8")

            next_pm = root / "sessions" / "pm-forseti" / "inbox" / "20260415-groom-20260412-forseti-release-m"
            next_pm.mkdir(parents=True)
            (next_pm / "roi.txt").write_text("25\n", encoding="utf-8")

            old_root = run.REPO_ROOT
            run.REPO_ROOT = root
            try:
                agents = run._prioritized_agents()
            finally:
                run.REPO_ROOT = old_root

            ordered = [agent.agent_id for agent in agents]
            self.assertEqual(ordered, ["dev-forseti", "pm-forseti", "qa-forseti"])

    def test_current_release_phrase_counts_as_current_release_work(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "org-chart" / "agents").mkdir(parents=True)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)

            (root / "org-chart" / "agents" / "agents.yaml").write_text(
                "\n".join(
                    [
                        "agents:",
                        "  - id: qa-forseti",
                        "    role: tester",
                    ]
                )
                + "\n",
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
                "20260412-forseti-release-l\n", encoding="utf-8"
            )

            item = root / "sessions" / "qa-forseti" / "inbox" / "20260415-suite-activate-feature-x"
            item.mkdir(parents=True)
            (item / "command.md").write_text(
                "This feature has been selected into the current release scope.\n",
                encoding="utf-8",
            )
            (item / "roi.txt").write_text("10\n", encoding="utf-8")

            old_root = run.REPO_ROOT
            run.REPO_ROOT = root
            try:
                agents = run._prioritized_agents()
            finally:
                run.REPO_ROOT = old_root

            self.assertEqual([agent.agent_id for agent in agents], ["qa-forseti"])
            self.assertTrue(agents[0].has_release_work)

    def test_only_pm_ba_next_release_items_get_spillover_priority(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "org-chart" / "agents").mkdir(parents=True)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)

            (root / "org-chart" / "agents" / "agents.yaml").write_text(
                "\n".join(
                    [
                        "agents:",
                        "  - id: dev-forseti",
                        "    role: software-developer",
                        "  - id: pm-forseti",
                        "    role: product-manager",
                        "  - id: qa-forseti",
                        "    role: tester",
                    ]
                )
                + "\n",
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
                "20260412-forseti-release-l\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "forseti.next_release_id").write_text(
                "20260412-forseti-release-m\n", encoding="utf-8"
            )

            current_dev = root / "sessions" / "dev-forseti" / "inbox" / "20260415-impl-20260412-forseti-release-l"
            current_dev.mkdir(parents=True)
            (current_dev / "roi.txt").write_text("20\n", encoding="utf-8")

            next_pm = root / "sessions" / "pm-forseti" / "inbox" / "20260415-groom-20260412-forseti-release-m"
            next_pm.mkdir(parents=True)
            (next_pm / "roi.txt").write_text("25\n", encoding="utf-8")

            next_qa = root / "sessions" / "qa-forseti" / "inbox" / "20260415-verify-20260412-forseti-release-m"
            next_qa.mkdir(parents=True)
            (next_qa / "command.md").write_text("Work for the next release only.\n", encoding="utf-8")
            (next_qa / "roi.txt").write_text("999\n", encoding="utf-8")

            old_root = run.REPO_ROOT
            run.REPO_ROOT = root
            try:
                agents = run._prioritized_agents()
            finally:
                run.REPO_ROOT = old_root

            ordered = [agent.agent_id for agent in agents]
            self.assertEqual(ordered, ["dev-forseti", "pm-forseti", "qa-forseti"])
            self.assertTrue(agents[1].has_next_release_work)
            self.assertFalse(agents[2].has_next_release_work)

    def test_engine_preserves_next_release_spillover_order(self):
        scheduled = [
            run.ScheduledAgent("dev-forseti", level=200, top_roi=20, has_release_work=True, team_id="forseti"),
            run.ScheduledAgent("pm-forseti", level=400, top_roi=25, has_next_release_work=True, team_id="forseti"),
            run.ScheduledAgent("qa-forseti", level=150, top_roi=10, has_release_work=True, team_id="forseti"),
        ]

        class Provider:
            def run_one(self, agent_id: str):
                return 0, agent_id

        deps = LangGraphDeps(
            run_cmd=lambda *_args, **_kwargs: (0, ""),
            dispatch_commands_step=lambda _log: None,
            release_cycle_step=lambda _log: None,
            coordinated_push_step=lambda _log: None,
            prioritized_agents=lambda: scheduled,
            health_check_step=lambda _provider, _log: None,
            now_ts=lambda: 10_000,
            kpi_monitor_cmd=["true"],
        )

        state, _, _ = run_tick(
            Provider(),
            agent_cap=3,
            publish_enabled=False,
            kpi_interval=999999,
            kpi_last_run=10_000,
            release_cycle_interval=999999,
            release_cycle_last_run=10_000,
            deps=deps,
        )

        self.assertEqual(state["selected_agents"], ["dev-forseti", "pm-forseti", "qa-forseti"])
        pick_log = next(entry for entry in state["log"] if entry.get("step") == "pick_agents")
        self.assertEqual(pick_log["release_priority"], ["dev-forseti", "qa-forseti"])
        self.assertEqual(pick_log["next_release_spillover"], ["pm-forseti"])


if __name__ == "__main__":
    unittest.main()
