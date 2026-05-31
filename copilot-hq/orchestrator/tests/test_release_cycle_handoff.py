"""
Regression tests for release-cycle handoff ordering.

Confirms the orchestrator does not advance the active release on PM signoff
alone; pointer advancement belongs to post-coordinated-push after the actual
deploy handoff completes.
"""

import json
import os
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import orchestrator.run as run


class TestReleaseCycleHandoff(unittest.TestCase):
    def test_signed_off_release_waits_for_push(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)
            control = root / "tmp" / "release-cycle-control.json"
            (root / "org-chart" / "products").mkdir(parents=True)
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs").mkdir(parents=True)
            control.write_text('{"enabled": true}\n', encoding="utf-8")

            (root / "org-chart" / "products" / "product-teams.json").write_text(
                json.dumps(
                    {
                        "teams": [
                            {
                                "id": "dungeoncrawler",
                                "pm_agent": "pm-dungeoncrawler",
                                "active": True,
                                "release_preflight_enabled": True,
                                "coordinated_release_default": True,
                            }
                        ]
                    }
                ),
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
                "20260412-dungeoncrawler-release-e\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.next_release_id").write_text(
                "20260412-dungeoncrawler-release-f\n", encoding="utf-8"
            )
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "20260412-dungeoncrawler-release-e.md").write_text(
                "# signoff\n", encoding="utf-8"
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                with patch.dict(os.environ, {"RELEASE_CYCLE_CONTROL_FILE": str(control)}):
                    log = []
                    run._release_cycle_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [], "Signed-off release must not auto-advance before push")
            team = log[0]["teams"][0]
            self.assertEqual(team["action"], "signed_off_waiting_push")
            self.assertEqual(team["current"], "20260412-dungeoncrawler-release-e")
            self.assertEqual(team["next"], "20260412-dungeoncrawler-release-f")

    def test_active_release_repairs_stale_next_without_advancing(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)
            control = root / "tmp" / "release-cycle-control.json"
            (root / "org-chart" / "products").mkdir(parents=True)
            (root / "sessions" / "pm-dungeoncrawler" / "inbox").mkdir(parents=True)
            control.write_text('{"enabled": true}\n', encoding="utf-8")

            (root / "org-chart" / "products" / "product-teams.json").write_text(
                json.dumps(
                    {
                        "teams": [
                            {
                                "id": "dungeoncrawler",
                                "pm_agent": "pm-dungeoncrawler",
                                "site": "dungeoncrawler.forseti.life",
                                "active": True,
                                "release_preflight_enabled": True,
                                "coordinated_release_default": True,
                            }
                        ]
                    }
                ),
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
                "20260412-dungeoncrawler-release-e\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.next_release_id").write_text(
                "20260412-dungeoncrawler-release-b\n", encoding="utf-8"
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                with patch.dict(os.environ, {"RELEASE_CYCLE_CONTROL_FILE": str(control)}):
                    log = []
                    run._release_cycle_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [], "Repairing stale next_release_id must not start a new cycle")
            team = log[0]["teams"][0]
            self.assertEqual(team["action"], "next_fixed")
            self.assertEqual(team["current"], "20260412-dungeoncrawler-release-e")
            self.assertEqual(team["next"], "20260412-dungeoncrawler-release-f")
            self.assertEqual(
                (root / "tmp" / "release-cycle-active" / "dungeoncrawler.next_release_id").read_text(encoding="utf-8").strip(),
                "20260412-dungeoncrawler-release-f",
            )
            groom_items = sorted((root / "sessions" / "pm-dungeoncrawler" / "inbox").iterdir())
            self.assertEqual(len(groom_items), 1)
            self.assertIn("groom-20260412-dungeoncrawler-release-f", groom_items[0].name)
            self.assertEqual((groom_items[0] / "roi.txt").read_text(encoding="utf-8").strip(), "25")

    def test_active_release_reprioritizes_existing_next_release_grooming(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            (root / "tmp" / "release-cycle-active").mkdir(parents=True)
            control = root / "tmp" / "release-cycle-control.json"
            (root / "org-chart" / "products").mkdir(parents=True)
            pm_inbox = root / "sessions" / "pm-dungeoncrawler" / "inbox"
            pm_inbox.mkdir(parents=True)
            control.write_text('{"enabled": true}\n', encoding="utf-8")

            (root / "org-chart" / "products" / "product-teams.json").write_text(
                json.dumps(
                    {
                        "teams": [
                            {
                                "id": "dungeoncrawler",
                                "pm_agent": "pm-dungeoncrawler",
                                "site": "dungeoncrawler.forseti.life",
                                "active": True,
                                "release_preflight_enabled": True,
                                "coordinated_release_default": True,
                            }
                        ]
                    }
                ),
                encoding="utf-8",
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
                "20260412-dungeoncrawler-release-e\n", encoding="utf-8"
            )
            (root / "tmp" / "release-cycle-active" / "dungeoncrawler.next_release_id").write_text(
                "20260412-dungeoncrawler-release-f\n", encoding="utf-8"
            )
            existing = pm_inbox / "20260412-groom-20260412-dungeoncrawler-release-f"
            existing.mkdir()
            (existing / "roi.txt").write_text("5\n", encoding="utf-8")

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                with patch.dict(os.environ, {"RELEASE_CYCLE_CONTROL_FILE": str(control)}):
                    log = []
                    run._release_cycle_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [], "Prioritizing next-release grooming must not start a new cycle")
            self.assertEqual((existing / "roi.txt").read_text(encoding="utf-8").strip(), "25")
            team = log[0]["teams"][0]
            self.assertIn("pm-groom-prioritized:20260412-groom-20260412-dungeoncrawler-release-f", team["parallel"])


if __name__ == "__main__":
    unittest.main()
