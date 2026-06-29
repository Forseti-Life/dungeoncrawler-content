import os
import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import orchestrator.run as run


class TestReleaseCycleControl(unittest.TestCase):
    def test_coordinated_push_dispatches_ready_team_independently(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            active = root / "tmp" / "release-cycle-active"
            active.mkdir(parents=True, exist_ok=True)
            (root / "org-chart" / "products").mkdir(parents=True, exist_ok=True)
            (root / "sessions" / "agent-code-review" / "outbox").mkdir(parents=True, exist_ok=True)
            (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs").mkdir(parents=True, exist_ok=True)
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs").mkdir(parents=True, exist_ok=True)
            (root / "tmp" / "auto-push-dispatched").mkdir(parents=True, exist_ok=True)

            teams = {
                "teams": [
                    {
                        "id": "forseti",
                        "site": "forseti.life",
                        "pm_agent": "pm-forseti",
                        "qa_agent": "qa-forseti",
                        "active": True,
                        "release_preflight_enabled": True,
                        "coordinated_release_default": True,
                    },
                    {
                        "id": "dungeoncrawler",
                        "site": "dungeoncrawler.forseti.life",
                        "pm_agent": "pm-dungeoncrawler",
                        "qa_agent": "qa-dungeoncrawler",
                        "active": True,
                        "release_preflight_enabled": True,
                        "coordinated_release_default": True,
                    },
                ]
            }
            (root / "org-chart" / "products" / "product-teams.json").write_text(
                json.dumps(teams),
                encoding="utf-8",
            )
            (active / "forseti.release_id").write_text("20260420-forseti-release-q\n", encoding="utf-8")
            (active / "dungeoncrawler.release_id").write_text("20260420-dungeoncrawler-release-s\n", encoding="utf-8")
            (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / "20260420-forseti-release-q.md").write_text(
                "# signoff\n",
                encoding="utf-8",
            )
            (root / "sessions" / "agent-code-review" / "outbox" / "20260420-code-review-forseti-20260420-forseti-release-q.md").write_text(
                "- Status: done\n- Release: 20260420-forseti-release-q\n- Verdict: APPROVE\n",
                encoding="utf-8",
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0, env=None):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                log = []
                run._coordinated_push_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(
                calls,
                [["gh", "workflow", "run", "deploy.yml", "--repo", "keithaumiller/forseti.life", "--ref", "main"]],
            )
            self.assertEqual(len(log), 1)
            self.assertEqual(log[0]["step"], "coordinated_push")
            self.assertEqual(log[0]["status"], "pushed")
            self.assertEqual(log[0]["pushed"][0]["team_id"], "forseti")
            self.assertEqual(log[0]["waiting_teams"], ["dungeoncrawler"])
            self.assertTrue((root / "tmp" / "auto-push-dispatched" / "20260420-forseti-release-q.pushed").exists())

    def test_release_cycle_step_skips_when_control_disabled(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            control = root / "tmp" / "release-cycle-control.json"
            control.parent.mkdir(parents=True, exist_ok=True)
            control.write_text(
                '{"enabled": false, "reason": "Pause release automation", "updated_by": "test"}\n',
                encoding="utf-8",
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0, env=None):
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

            self.assertEqual(calls, [])
            self.assertEqual(
                log,
                [
                    {
                        "step": "release_cycle",
                        "status": "paused",
                        "state_file": str(control),
                        "reason": "Pause release automation",
                    }
                ],
            )

    def test_failed_push_does_not_write_marker(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            active = root / "tmp" / "release-cycle-active"
            active.mkdir(parents=True, exist_ok=True)
            (root / "org-chart" / "products").mkdir(parents=True, exist_ok=True)
            (root / "sessions" / "agent-code-review" / "outbox").mkdir(parents=True, exist_ok=True)
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs").mkdir(parents=True, exist_ok=True)
            (root / "tmp" / "auto-push-dispatched").mkdir(parents=True, exist_ok=True)

            teams = {
                "teams": [
                    {
                        "id": "dungeoncrawler",
                        "site": "dungeoncrawler.forseti.life",
                        "pm_agent": "pm-dungeoncrawler",
                        "qa_agent": "qa-dungeoncrawler",
                        "active": True,
                        "release_preflight_enabled": True,
                        "coordinated_release_default": True,
                    },
                ]
            }
            (root / "org-chart" / "products" / "product-teams.json").write_text(
                json.dumps(teams),
                encoding="utf-8",
            )
            (active / "dungeoncrawler.release_id").write_text("20260420-dungeoncrawler-release-s\n", encoding="utf-8")
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "20260420-dungeoncrawler-release-s.md").write_text(
                "# signoff\n",
                encoding="utf-8",
            )
            (root / "sessions" / "agent-code-review" / "outbox" / "20260420-code-review-dungeoncrawler-20260420-dungeoncrawler-release-s.md").write_text(
                "- Status: done\n- Release: 20260420-dungeoncrawler-release-s\n- Verdict: APPROVE\n",
                encoding="utf-8",
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root

            def fake_run(cmd, timeout=0, env=None):
                return 1, "gh auth missing"

            run._run = fake_run
            try:
                log = []
                run._coordinated_push_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            assert not (root / "tmp" / "auto-push-dispatched" / "20260420-dungeoncrawler-release-s.pushed").exists()
            assert any(entry.get("status") == "push_failed" for entry in log), log

    def test_coordinated_push_blocks_when_gate1b_missing(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            active = root / "tmp" / "release-cycle-active"
            active.mkdir(parents=True, exist_ok=True)
            (root / "org-chart" / "products").mkdir(parents=True, exist_ok=True)
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs").mkdir(parents=True, exist_ok=True)
            (root / "tmp" / "auto-push-dispatched").mkdir(parents=True, exist_ok=True)

            teams = {
                "teams": [
                    {
                        "id": "dungeoncrawler",
                        "site": "dungeoncrawler.forseti.life",
                        "pm_agent": "pm-dungeoncrawler",
                        "qa_agent": "qa-dungeoncrawler",
                        "active": True,
                        "release_preflight_enabled": True,
                        "coordinated_release_default": True,
                    },
                ]
            }
            (root / "org-chart" / "products" / "product-teams.json").write_text(
                json.dumps(teams),
                encoding="utf-8",
            )
            (active / "dungeoncrawler.release_id").write_text("20260420-dungeoncrawler-release-w\n", encoding="utf-8")
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "20260420-dungeoncrawler-release-w.md").write_text(
                "# signoff\n",
                encoding="utf-8",
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0, env=None):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                log = []
                run._coordinated_push_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [], "Gate 1b gap must block deploy dispatch")
            self.assertFalse((root / "tmp" / "auto-push-dispatched" / "20260420-dungeoncrawler-release-w.pushed").exists())
            self.assertTrue(any(entry.get("status") == "blocked_gate1b" for entry in log), log)

    def test_coordinated_push_skips_when_control_disabled(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            control = root / "tmp" / "release-cycle-control.json"
            control.parent.mkdir(parents=True, exist_ok=True)
            control.write_text(
                '{"enabled": false, "reason": "Pause release automation", "updated_by": "test"}\n',
                encoding="utf-8",
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0, env=None):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                with patch.dict(os.environ, {"RELEASE_CYCLE_CONTROL_FILE": str(control)}):
                    log = []
                    run._coordinated_push_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [])
            self.assertEqual(
                log,
                [
                    {
                        "step": "coordinated_push",
                        "status": "paused",
                        "state_file": str(control),
                        "reason": "Pause release automation",
                    }
                ],
            )


if __name__ == "__main__":
    unittest.main()
