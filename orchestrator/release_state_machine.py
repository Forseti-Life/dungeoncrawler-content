"""Release readiness state machine.

Tracks release lifecycle through 11 states from creation to closure.
Provides deterministic dispatch logic based on state transitions.

State file per-release at: tmp/release-cycle-state/<release-id>.json
History log per-team at: tmp/release-cycle-state-history/<team>/<release-id>.log

States (in order):
1. created      — Release ID generated, initial state
2. scoped       — Feature scope locked (Feature: <release_id> in feature.md)
3. dev-executing — Dev work in progress (at least 1 feature Status: in_progress)
4. qa-verifying  — QA verification in progress (features entering Gate 2)
5. all-gates-clean — All scoped features have Gate 2 APPROVE
6. pm-signing-required — Ready for PM signoff, no signoff yet
7. pm-signed    — PM has written signoff artifact (release-signoffs/<id>.md)
8. push-required — All gates clean and PM signed, ready for push
9. pushed       — coordinated-push.sh has run, code deployed
10. post-release-qa — Post-release validation (Gate R5) in progress
11. closed      — Release cycle complete
"""

import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, Optional, Any


class ReleaseState:
    """Enum-like class for release states."""
    CREATED = "created"
    SCOPED = "scoped"
    DEV_EXECUTING = "dev-executing"
    QA_VERIFYING = "qa-verifying"
    ALL_GATES_CLEAN = "all-gates-clean"
    PM_SIGNING_REQUIRED = "pm-signing-required"
    PM_SIGNED = "pm-signed"
    PUSH_REQUIRED = "push-required"
    PUSHED = "pushed"
    POST_RELEASE_QA = "post-release-qa"
    CLOSED = "closed"

    # Ordered sequence for validation
    SEQUENCE = [
        CREATED,
        SCOPED,
        DEV_EXECUTING,
        QA_VERIFYING,
        ALL_GATES_CLEAN,
        PM_SIGNING_REQUIRED,
        PM_SIGNED,
        PUSH_REQUIRED,
        PUSHED,
        POST_RELEASE_QA,
        CLOSED,
    ]


class ReleaseStateMachine:
    """Tracks and manages release state transitions."""

    def __init__(self, repo_root: Path, team_id: str, release_id: str):
        """Initialize state machine for a release.

        Args:
            repo_root: HQ repository root (e.g., /home/ubuntu/forseti.life)
            team_id: Team identifier (e.g., 'forseti', 'dungeoncrawler')
            release_id: Release identifier (e.g., '20260420-forseti-release')
        """
        self.repo_root = repo_root
        self.team_id = team_id
        self.release_id = release_id

        # State file: tmp/release-cycle-state/<release-id>.json (per-release, not per-team)
        state_dir = repo_root / "tmp" / "release-cycle-state"
        state_dir.mkdir(parents=True, exist_ok=True)
        slug = release_id.replace("/", "-").replace(" ", "-")[:100]
        self.state_file = state_dir / f"{slug}.json"

        # History log: tmp/release-cycle-state-history/<team>/<release-id>.log
        history_dir = repo_root / "tmp" / "release-cycle-state-history" / team_id
        history_dir.mkdir(parents=True, exist_ok=True)
        self.history_file = history_dir / f"{release_id}.log"

    def read(self) -> Dict[str, Any]:
        """Read current state and metadata from state file.

        Returns:
            Dict with keys: state, release_id, created_at, last_transition
        """
        if not self.state_file.exists():
            return {
                "state": ReleaseState.CREATED,
                "release_id": None,
                "created_at": None,
                "last_transition": None,
            }
        try:
            return json.loads(self.state_file.read_text(encoding="utf-8"))
        except Exception:
            return {
                "state": ReleaseState.CREATED,
                "release_id": None,
                "created_at": None,
                "last_transition": None,
            }

    def write(self, state: str) -> None:
        """Write state to file and log transition."""
        data = self.read()
        now_iso = datetime.now(timezone.utc).isoformat()

        # Update state
        old_state = data.get("state")
        data["state"] = state
        data["release_id"] = self.release_id
        data["created_at"] = data.get("created_at") or now_iso
        data["last_transition"] = now_iso

        self.state_file.parent.mkdir(parents=True, exist_ok=True)
        self.state_file.write_text(json.dumps(data, indent=2), encoding="utf-8")

        # Log transition
        self._log_transition(old_state, state, now_iso)

    def _log_transition(self, old_state: Optional[str], new_state: str, timestamp: str) -> None:
        """Append transition to history log."""
        msg = f"{timestamp} | {old_state or 'none'} → {new_state}"
        self.history_file.parent.mkdir(parents=True, exist_ok=True)
        with open(self.history_file, "a", encoding="utf-8") as f:
            f.write(msg + "\n")

    def transition_to(self, new_state: str) -> bool:
        """Attempt transition to new state.

        Returns:
            True if transition succeeded, False if invalid transition.
        """
        current = self.read()
        old_state = current.get("state", ReleaseState.CREATED)

        # Validate transition (only forward, no backtracking)
        old_idx = ReleaseState.SEQUENCE.index(old_state) if old_state in ReleaseState.SEQUENCE else 0
        new_idx = ReleaseState.SEQUENCE.index(new_state) if new_state in ReleaseState.SEQUENCE else -1

        if new_idx < 0:
            return False  # Invalid state
        if new_idx <= old_idx:
            return False  # Can't go backward or stay same (use idempotency instead)

        self.write(new_state)
        return True

    def is_state(self, state: str) -> bool:
        """Check if current state matches given state."""
        return self.read()["state"] == state

    def is_at_or_past(self, state: str) -> bool:
        """Check if current state is at or past given state in sequence."""
        current = self.read().get("state", ReleaseState.CREATED)
        try:
            current_idx = ReleaseState.SEQUENCE.index(current)
            target_idx = ReleaseState.SEQUENCE.index(state)
            return current_idx >= target_idx
        except ValueError:
            return False

    def can_sign_off(self) -> bool:
        """Check if release is ready for PM signoff."""
        current = self.read()["state"]
        return self.is_at_or_past(ReleaseState.ALL_GATES_CLEAN) and current != ReleaseState.CLOSED

    def can_push(self) -> bool:
        """Check if release is ready for push."""
        return self.is_state(ReleaseState.PUSH_REQUIRED)

    def can_close(self) -> bool:
        """Check if release is ready for closure."""
        return self.is_at_or_past(ReleaseState.POST_RELEASE_QA) and not self.is_state(ReleaseState.CLOSED)
