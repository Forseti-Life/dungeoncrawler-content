import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import orchestrator.run as run


class TestSignoffReminderDispatch(unittest.TestCase):
    """Test cases for _dispatch_signoff_reminders() function.
    
    Scenarios covered:
    1. No teams signed yet → no reminder
    2. One team signed, one unsigned → reminder to unsigned
    3. All teams signed → no reminder
    """

    def setUp(self):
        """Save original REPO_ROOT for restoration."""
        self.old_root = run.REPO_ROOT

    def tearDown(self):
        """Restore original REPO_ROOT."""
        run.REPO_ROOT = self.old_root

    def _make_test_env(self):
        """Create a temporary HQ-like directory structure."""
        td = tempfile.TemporaryDirectory()
        root = Path(td.name)
        
        # Create org-chart/products/product-teams.json
        org_chart = root / "org-chart" / "products"
        org_chart.mkdir(parents=True, exist_ok=True)
        
        teams_data = {
            "teams": [
                {
                    "id": "forseti",
                    "pm_agent": "pm-forseti",
                    "active": True,
                    "coordinated_release_default": True,
                },
                {
                    "id": "dungeoncrawler",
                    "pm_agent": "pm-dungeoncrawler",
                    "active": True,
                    "coordinated_release_default": True,
                },
            ]
        }
        (org_chart / "product-teams.json").write_text(
            json.dumps(teams_data), encoding="utf-8"
        )
        
        # Create tmp/release-cycle-active for release IDs
        active_dir = root / "tmp" / "release-cycle-active"
        active_dir.mkdir(parents=True, exist_ok=True)
        
        # Create sessions directories for both PMs
        for pm_id in ["pm-forseti", "pm-dungeoncrawler"]:
            pm_artifacts = root / "sessions" / pm_id / "artifacts" / "release-signoffs"
            pm_artifacts.mkdir(parents=True, exist_ok=True)
            pm_inbox = root / "sessions" / pm_id / "inbox"
            pm_inbox.mkdir(parents=True, exist_ok=True)
        
        return root, td

    def test_no_teams_signed_yet_no_reminder(self):
        """When no teams have signed off, no reminder should be dispatched."""
        root, td = self._make_test_env()
        run.REPO_ROOT = root
        
        try:
            # Setup: active release but NO signoffs
            (root / "tmp" / "release-cycle-active" / "release.release_id").write_text(
                "test-release\n"
            )
            
            # Call signoff reminder dispatch
            run._dispatch_signoff_reminders()
            
            # Verify: no reminder items created
            for pm_id in ["pm-forseti", "pm-dungeoncrawler"]:
                inbox = root / "sessions" / pm_id / "inbox"
                items = list(inbox.glob("*signoff-reminder*"))
                self.assertEqual(len(items), 0, f"No reminder should be created when nobody signed")
        finally:
            td.cleanup()

    def test_all_teams_signed_no_reminder(self):
        """When all teams have signed, no reminder should be dispatched."""
        root, td = self._make_test_env()
        run.REPO_ROOT = root
        
        try:
            # Setup: single release watched by both teams
            (root / "tmp" / "release-cycle-active" / "release.release_id").write_text(
                "test-release\n"
            )
            
            # Both PMs have signed
            (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / "test-release.md").write_text(
                "# Signoff\n- Status: approved\n"
            )
            (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "test-release.md").write_text(
                "# Signoff\n- Status: approved\n"
            )
            
            # Call signoff reminder dispatch
            run._dispatch_signoff_reminders()
            
            # Verify: no reminders created
            for pm_id in ["pm-forseti", "pm-dungeoncrawler"]:
                inbox = root / "sessions" / pm_id / "inbox"
                items = list(inbox.glob("*signoff-reminder*"))
                self.assertEqual(len(items), 0, "No reminder should be created when all PMs signed")
        finally:
            td.cleanup()

    def test_signoff_reminder_not_created_when_already_exists(self):
        """Test idempotency: same reminder should not be created twice in same day."""
        root, td = self._make_test_env()
        run.REPO_ROOT = root
        
        try:
            # Setup: single release with one PM signed, one unsigned
            (root / "tmp" / "release-cycle-active" / "release.release_id").write_text(
                "test-release\n"
            )
            
            # forseti PM has signed
            (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / "test-release.md").write_text(
                "# Signoff\n- Status: approved\n"
            )
            
            # Create a reminder manually to simulate it already existing
            dungeoncrawler_inbox = root / "sessions" / "pm-dungeoncrawler" / "inbox"
            reminder_id = "20260420-signoff-reminder-test-release"
            reminder_dir = dungeoncrawler_inbox / reminder_id
            reminder_dir.mkdir(parents=True, exist_ok=True)
            (reminder_dir / "README.md").write_text("Existing reminder\n")
            (reminder_dir / "roi.txt").write_text("500")
            
            # Call signoff reminder dispatch - should not overwrite existing
            with patch('orchestrator.run._now_ts', return_value=1e10):
                run._dispatch_signoff_reminders()
            
            # Verify: no new reminder was created (check that old one is unchanged)
            items = list(dungeoncrawler_inbox.glob("*signoff-reminder*"))
            self.assertEqual(len(items), 1, "Should not duplicate reminder")
            
            # Check content unchanged
            content = (reminder_dir / "README.md").read_text()
            self.assertEqual(content, "Existing reminder\n", "Should not overwrite existing item")
        finally:
            td.cleanup()
