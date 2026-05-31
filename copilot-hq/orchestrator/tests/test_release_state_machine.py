"""Unit tests for release state machine."""

import json
import tempfile
import unittest
from pathlib import Path

from orchestrator.release_state_machine import ReleaseState, ReleaseStateMachine


class TestReleaseStateMachine(unittest.TestCase):
    """Test cases for ReleaseStateMachine."""

    def setUp(self):
        """Create temporary directory for test."""
        self.temp_dir = tempfile.TemporaryDirectory()
        self.repo_root = Path(self.temp_dir.name)

    def tearDown(self):
        """Clean up temporary directory."""
        self.temp_dir.cleanup()

    def test_initial_state_is_created(self):
        """New state machine starts in CREATED state."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")
        state = sm.read()
        self.assertEqual(state["state"], ReleaseState.CREATED)

    def test_transition_forward_succeeds(self):
        """Can transition forward through state sequence."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        # CREATED → SCOPED
        self.assertTrue(sm.transition_to(ReleaseState.SCOPED))
        self.assertEqual(sm.read()["state"], ReleaseState.SCOPED)

        # SCOPED → DEV_EXECUTING
        self.assertTrue(sm.transition_to(ReleaseState.DEV_EXECUTING))
        self.assertEqual(sm.read()["state"], ReleaseState.DEV_EXECUTING)

    def test_transition_backward_fails(self):
        """Cannot transition backward in state sequence."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        sm.transition_to(ReleaseState.QA_VERIFYING)
        # Try to go backward to SCOPED
        self.assertFalse(sm.transition_to(ReleaseState.SCOPED))
        # State should remain QA_VERIFYING
        self.assertEqual(sm.read()["state"], ReleaseState.QA_VERIFYING)

    def test_transition_same_state_fails(self):
        """Cannot transition to same state (use idempotency instead)."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        sm.transition_to(ReleaseState.SCOPED)
        # Try to stay in SCOPED
        self.assertFalse(sm.transition_to(ReleaseState.SCOPED))

    def test_invalid_state_fails(self):
        """Cannot transition to invalid state."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        # Invalid state should fail
        self.assertFalse(sm.transition_to("nonexistent-state"))

    def test_state_file_persists(self):
        """State is persisted to file and can be read back."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        sm.transition_to(ReleaseState.DEV_EXECUTING)

        # Create new instance, should read same state
        sm2 = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")
        self.assertEqual(sm2.read()["state"], ReleaseState.DEV_EXECUTING)

    def test_history_log_records_transitions(self):
        """Transitions are logged to history file."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        sm.transition_to(ReleaseState.SCOPED)
        sm.transition_to(ReleaseState.DEV_EXECUTING)

        # Check history log exists and has entries
        self.assertTrue(sm.history_file.exists())
        history = sm.history_file.read_text(encoding="utf-8")
        self.assertIn("created → scoped", history)
        self.assertIn("scoped → dev-executing", history)

    def test_is_state(self):
        """is_state() correctly identifies current state."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        self.assertTrue(sm.is_state(ReleaseState.CREATED))
        self.assertFalse(sm.is_state(ReleaseState.SCOPED))

        sm.transition_to(ReleaseState.SCOPED)
        self.assertTrue(sm.is_state(ReleaseState.SCOPED))
        self.assertFalse(sm.is_state(ReleaseState.CREATED))

    def test_is_at_or_past(self):
        """is_at_or_past() correctly checks position in sequence."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        sm.transition_to(ReleaseState.QA_VERIFYING)

        # Current state (QA_VERIFYING) is at or past all earlier states
        self.assertTrue(sm.is_at_or_past(ReleaseState.CREATED))
        self.assertTrue(sm.is_at_or_past(ReleaseState.SCOPED))
        self.assertTrue(sm.is_at_or_past(ReleaseState.DEV_EXECUTING))
        self.assertTrue(sm.is_at_or_past(ReleaseState.QA_VERIFYING))

        # Current state is before later states
        self.assertFalse(sm.is_at_or_past(ReleaseState.ALL_GATES_CLEAN))
        self.assertFalse(sm.is_at_or_past(ReleaseState.CLOSED))

    def test_can_sign_off(self):
        """can_sign_off() is true only when all gates clean and not closed."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        # Cannot sign off before gates are clean
        sm.transition_to(ReleaseState.QA_VERIFYING)
        self.assertFalse(sm.can_sign_off())

        # Can sign off once gates are clean
        sm.transition_to(ReleaseState.ALL_GATES_CLEAN)
        self.assertTrue(sm.can_sign_off())

        # Can still sign off at PM_SIGNING_REQUIRED
        sm.transition_to(ReleaseState.PM_SIGNING_REQUIRED)
        self.assertTrue(sm.can_sign_off())

        # Cannot sign off if closed
        sm.transition_to(ReleaseState.PM_SIGNED)
        sm.transition_to(ReleaseState.PUSH_REQUIRED)
        sm.transition_to(ReleaseState.PUSHED)
        sm.transition_to(ReleaseState.POST_RELEASE_QA)
        sm.transition_to(ReleaseState.CLOSED)
        self.assertFalse(sm.can_sign_off())

    def test_can_push(self):
        """can_push() is true only in PUSH_REQUIRED state."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        # Cannot push before PUSH_REQUIRED
        sm.transition_to(ReleaseState.ALL_GATES_CLEAN)
        self.assertFalse(sm.can_push())

        sm.transition_to(ReleaseState.PM_SIGNING_REQUIRED)
        self.assertFalse(sm.can_push())

        sm.transition_to(ReleaseState.PM_SIGNED)
        self.assertFalse(sm.can_push())

        # Can push when PUSH_REQUIRED
        sm.transition_to(ReleaseState.PUSH_REQUIRED)
        self.assertTrue(sm.can_push())

        # Cannot push after push
        sm.transition_to(ReleaseState.PUSHED)
        self.assertFalse(sm.can_push())

    def test_can_close(self):
        """can_close() is true only when post-release-qa and not closed."""
        sm = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")

        # Cannot close before post-release-qa
        sm.transition_to(ReleaseState.PUSHED)
        self.assertFalse(sm.can_close())

        # Can close when post-release-qa
        sm.transition_to(ReleaseState.POST_RELEASE_QA)
        self.assertTrue(sm.can_close())

        # Cannot close when already closed
        sm.transition_to(ReleaseState.CLOSED)
        self.assertFalse(sm.can_close())

    def test_multiple_releases_independent_states(self):
        """Different releases maintain independent state."""
        sm1 = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release")
        sm2 = ReleaseStateMachine(self.repo_root, "forseti", "20260420-forseti-release-next")

        sm1.transition_to(ReleaseState.DEV_EXECUTING)
        sm2.transition_to(ReleaseState.SCOPED)

        # States should be independent
        self.assertEqual(sm1.read()["state"], ReleaseState.DEV_EXECUTING)
        self.assertEqual(sm2.read()["state"], ReleaseState.SCOPED)


if __name__ == "__main__":
    unittest.main()
