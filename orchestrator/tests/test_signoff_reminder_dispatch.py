import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import orchestrator.dispatch as dispatch


class TestSignoffReminderDispatch(unittest.TestCase):
    """Exercise dependency-aware signoff reminder dispatch."""

    def setUp(self):
        self.old_root = dispatch.REPO_ROOT
        self.old_state = dispatch._SIGNOFF_REMINDER_STATE

    def tearDown(self):
        dispatch.REPO_ROOT = self.old_root
        dispatch._SIGNOFF_REMINDER_STATE = self.old_state

    def _make_test_env(self):
        td = tempfile.TemporaryDirectory()
        root = Path(td.name)

        org_chart = root / "org-chart" / "products"
        org_chart.mkdir(parents=True, exist_ok=True)
        teams_data = {
            "teams": [
                {
                    "id": "forseti",
                    "pm_agent": "pm-forseti",
                    "dev_agent": "dev-forseti",
                    "active": True,
                    "release_preflight_enabled": True,
                    "release_dependencies": ["dungeoncrawler"],
                },
                {
                    "id": "dungeoncrawler",
                    "pm_agent": "pm-dungeoncrawler",
                    "dev_agent": "dev-dungeoncrawler",
                    "active": True,
                    "release_preflight_enabled": True,
                    "release_dependencies": [],
                },
            ]
        }
        (org_chart / "product-teams.json").write_text(json.dumps(teams_data), encoding="utf-8")

        active_dir = root / "tmp" / "release-cycle-active"
        active_dir.mkdir(parents=True, exist_ok=True)
        (active_dir / "forseti.release_id").write_text("forseti-release\n", encoding="utf-8")
        (active_dir / "dungeoncrawler.release_id").write_text("dungeoncrawler-release\n", encoding="utf-8")

        for pm_id in ["pm-forseti", "pm-dungeoncrawler"]:
            (root / "sessions" / pm_id / "artifacts" / "release-signoffs").mkdir(parents=True, exist_ok=True)
            (root / "sessions" / pm_id / "inbox").mkdir(parents=True, exist_ok=True)

        dispatch.REPO_ROOT = root
        dispatch._SIGNOFF_REMINDER_STATE = root / "tmp" / "dispatch-state" / "signoff-reminder.timestamp"
        return root, td

    def test_no_dependency_signoffs_no_reminder(self):
        root, td = self._make_test_env()
        try:
            dispatch._dispatch_signoff_reminders()

            for pm_id in ["pm-forseti", "pm-dungeoncrawler"]:
                items = list((root / "sessions" / pm_id / "inbox").glob("*signoff-reminder*"))
                self.assertEqual(len(items), 0)
        finally:
            td.cleanup()

    def test_all_dependency_signoffs_present_no_reminder(self):
        root, td = self._make_test_env()
        try:
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "dungeoncrawler-release.md").write_text(
                "# Signoff\n- Status: approved\n",
                encoding="utf-8",
            )

            dispatch._dispatch_signoff_reminders()

            for pm_id in ["pm-forseti", "pm-dungeoncrawler"]:
                items = list((root / "sessions" / pm_id / "inbox").glob("*signoff-reminder*"))
                self.assertEqual(len(items), 0)
        finally:
            td.cleanup()

    def test_existing_dependency_reminder_is_not_overwritten(self):
        root, td = self._make_test_env()
        try:
            (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / "forseti-release.md").write_text(
                "# Signoff\n- Status: approved\n",
                encoding="utf-8",
            )

            dungeoncrawler_inbox = root / "sessions" / "pm-dungeoncrawler" / "inbox"
            reminder_dir = dungeoncrawler_inbox / "20260420-signoff-reminder-forseti-release"
            reminder_dir.mkdir(parents=True, exist_ok=True)
            (reminder_dir / "README.md").write_text("Existing reminder\n", encoding="utf-8")
            (reminder_dir / "roi.txt").write_text("500", encoding="utf-8")

            with patch("orchestrator.dispatch._now_ts", return_value=int(1e10)):
                dispatch._dispatch_signoff_reminders()

            items = list(dungeoncrawler_inbox.glob("*signoff-reminder*"))
            self.assertEqual(len(items), 1)
            self.assertEqual((reminder_dir / "README.md").read_text(encoding="utf-8"), "Existing reminder\n")
        finally:
            td.cleanup()

    def test_dependency_reminder_includes_exact_signoff_command_and_proof(self):
        root, td = self._make_test_env()
        try:
            (
                root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / "forseti-release.md"
            ).write_text(
                "# Signoff\n",
                encoding="utf-8",
            )

            with patch("orchestrator.dispatch._now_ts", return_value=int(1e10)):
                dispatch._dispatch_signoff_reminders()

            reminder = next((root / "sessions" / "pm-dungeoncrawler" / "inbox").glob("*signoff-reminder*"))
            readme = (reminder / "README.md").read_text(encoding="utf-8")
            self.assertIn("- Required signoff release: dungeoncrawler-release", readme)
            self.assertIn("bash scripts/release-signoff.sh dungeoncrawler dungeoncrawler-release", readme)
            self.assertIn(
                "`sessions/pm-dungeoncrawler/artifacts/release-signoffs/dungeoncrawler-release.md` exists",
                readme,
            )
            self.assertIn("bash scripts/release-signoff-status.sh forseti-release", readme)
            self.assertNotIn("Status: approved", readme)
        finally:
            td.cleanup()

    def test_proactive_signoff_queues_code_review_followup_when_gate1b_open(self):
        root, td = self._make_test_env()
        old_proactive = dispatch._PROACTIVE_SIGNOFF_STATE
        try:
            (root / "sessions" / "agent-code-review" / "outbox").mkdir(parents=True, exist_ok=True)
            (
                root
                / "sessions"
                / "agent-code-review"
                / "outbox"
                / "20260428-code-review-dungeoncrawler-dungeoncrawler-release.md"
            ).write_text(
                "- Status: done\n\n"
                "### HIGH\n\n"
                "**H-01 — Missing CSRF token validation**\n",
                encoding="utf-8",
            )
            dispatch._PROACTIVE_SIGNOFF_STATE = root / "tmp" / "dispatch-state" / "proactive-signoff.timestamp"

            dispatch._dispatch_proactive_awaiting_signoff()

            pm_dc_inbox = root / "sessions" / "pm-dungeoncrawler" / "inbox"
            self.assertTrue(any(p.name.endswith("code-review-followup-dungeoncrawler-release") for p in pm_dc_inbox.iterdir()))
            self.assertFalse(any("awaiting-signoff-dungeoncrawler-release" in p.name for p in pm_dc_inbox.iterdir()))
        finally:
            dispatch._PROACTIVE_SIGNOFF_STATE = old_proactive
            td.cleanup()

    def test_awaiting_signoff_includes_exact_command_and_artifact_proof(self):
        root, td = self._make_test_env()
        old_proactive = dispatch._PROACTIVE_SIGNOFF_STATE
        try:
            dispatch._PROACTIVE_SIGNOFF_STATE = root / "tmp" / "dispatch-state" / "proactive-signoff.timestamp"

            dispatch._dispatch_proactive_awaiting_signoff()

            item = next((root / "sessions" / "pm-dungeoncrawler" / "inbox").glob("*awaiting-signoff*"))
            readme = (item / "README.md").read_text(encoding="utf-8")
            self.assertIn("bash scripts/release-signoff.sh dungeoncrawler dungeoncrawler-release", readme)
            self.assertIn(
                "`sessions/pm-dungeoncrawler/artifacts/release-signoffs/dungeoncrawler-release.md` exists",
                readme,
            )
            self.assertIn("bash scripts/release-signoff-status.sh dungeoncrawler-release", readme)
            self.assertNotIn("Status: approved", readme)
        finally:
            dispatch._PROACTIVE_SIGNOFF_STATE = old_proactive
            td.cleanup()
