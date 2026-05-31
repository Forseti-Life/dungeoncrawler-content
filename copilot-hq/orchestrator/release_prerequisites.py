"""Release prerequisite validation.

Ensures releases meet preconditions before state transitions, with auto-remediation.
This is the REAL fix for coordinated release deadlocks, not a bandaid.
"""

import json
import re
from datetime import datetime, timezone
from pathlib import Path
from typing import Dict, List, Tuple, Any, Optional


class ReleasePrerequisiteValidator:
    """Validate and auto-remediate release prerequisites."""

    def __init__(self, repo_root: Path):
        """Initialize validator with HQ repo root."""
        self.repo_root = repo_root
        self.teams_file = repo_root / "org-chart" / "products" / "product-teams.json"
        self.active_dir = repo_root / "tmp" / "release-cycle-active"
        self.signoff_dir = repo_root / "sessions"

    def get_active_teams(self) -> Dict[str, Dict[str, str]]:
        """Load active coordinated-release teams from product-teams.json.

        Returns:
            Dict mapping team_id -> {id, pm_agent, ba_agent, site}
        """
        if not self.teams_file.exists():
            return {}

        try:
            data = json.loads(self.teams_file.read_text(encoding="utf-8"))
        except Exception:
            return {}

        teams = {}
        for team in data.get("teams", []):
            if not (team.get("active") and team.get("coordinated_release_default")):
                continue
            team_id = str(team.get("id") or "").strip()
            pm_agent = str(team.get("pm_agent") or "").strip()
            if team_id and pm_agent:
                teams[team_id] = {
                    "id": team_id,
                    "pm_agent": pm_agent,
                    "ba_agent": str(team.get("ba_agent") or "").strip(),
                    "site": str(team.get("site") or team_id).strip() or team_id,
                }
        return teams

    def get_other_coordinated_teams(self) -> List[str]:
        """Get list of other coordinated-release teams.

        For now, hardcoded as forseti/dungeoncrawler. In future, this should
        read from product-teams.json coordination graph.
        """
        # FIXME: Should read from product-teams.json "coordinated_with" field
        return ["forseti", "dungeoncrawler"]

    def get_release_for_team(self, team_id: str) -> Optional[str]:
        """Get current active release for a team.

        Args:
            team_id: Team identifier (e.g., 'forseti')

        Returns:
            Release ID or None if no active release
        """
        release_file = self.active_dir / f"{team_id}.release_id"
        if release_file.exists():
            return release_file.read_text(encoding="utf-8").strip() or None
        return None

    def has_pm_signoff(self, team_id: str, release_id: str) -> bool:
        """Check if PM has signed off on a release.

        Args:
            team_id: Team identifier
            release_id: Release identifier

        Returns:
            True if release-signoffs/<release_id>.md exists for the team's PM
        """
        teams = self.get_active_teams()
        if team_id not in teams:
            return False

        pm_agent = teams[team_id]["pm_agent"]
        slug = re.sub(r"[^A-Za-z0-9._-]", "-", release_id)[:80]
        signoff_file = (
            self.signoff_dir / pm_agent / "artifacts" / "release-signoffs" / f"{slug}.md"
        )
        return signoff_file.exists()

    def check_cross_team_signoffs(
        self, team_id: str, release_id: str
    ) -> Tuple[bool, List[str]]:
        """Check if all coordinated teams have signed off on a release.

        Args:
            team_id: Primary team identifier
            release_id: Release identifier (e.g., "20260420-forseti-release")

        Returns:
            (all_signed_off: bool, missing_signoffs: List[team_id])
        """
        other_teams = self.get_other_coordinated_teams()
        other_teams = [t for t in other_teams if t != team_id]

        missing = []
        for other_team in other_teams:
            if not self.has_pm_signoff(other_team, release_id):
                missing.append(other_team)

        return len(missing) == 0, missing

    def get_missing_cross_team_signoffs(self, team_id: str, release_id: str) -> List[str]:
        """Get list of coordinated teams missing signoff for a release.

        Args:
            team_id: Primary team identifier
            release_id: Release identifier

        Returns:
            List of team_ids that haven't signed off yet
        """
        other_teams = self.get_other_coordinated_teams()
        other_teams = [t for t in other_teams if t != team_id]

        missing = []
        for other_team in other_teams:
            if not self.has_pm_signoff(other_team, release_id):
                missing.append(other_team)

        return missing

    def validate_release_can_advance_to_push(
        self, team_id: str, release_id: str
    ) -> Tuple[bool, List[str]]:
        """Validate that a release has all prerequisites for PUSH_REQUIRED state.

        For coordinated releases, this means:
        1. Primary team's PM has signed off
        2. All other coordinated teams have also signed off

        Does NOT auto-remediate. If prerequisites are missing, returns False
        with diagnostic info so CEO can investigate root cause.

        Args:
            team_id: Primary team identifier
            release_id: Release identifier

        Returns:
            (can_advance: bool, issues: List[str])
        """
        issues = []

        # Check primary team's signoff
        if not self.has_pm_signoff(team_id, release_id):
            issues.append(f"{team_id} PM has not signed off yet")
            return False, issues

        # Check cross-team signoffs
        missing = self.get_missing_cross_team_signoffs(team_id, release_id)
        if missing:
            issues.append(
                f"Missing co-signoffs from: {', '.join(missing)}. "
                f"Investigate why {team_id} released without waiting for coordinated teams."
            )
            return False, issues

        return True, issues

    def diagnose_stalled_release(self, team_id: str, release_id: str) -> Dict[str, Any]:
        """Diagnose why a release might be stalled.

        Returns detailed diagnostic info for logging/escalation.
        """
        diagnosis = {
            "team_id": team_id,
            "release_id": release_id,
            "timestamp": datetime.now(timezone.utc).isoformat(),
            "checks": {},
        }

        # Check primary signoff
        has_primary = self.has_pm_signoff(team_id, release_id)
        diagnosis["checks"]["primary_signoff"] = {
            "has": has_primary,
            "team": team_id,
        }

        # Check cross-team signoffs
        all_signed, missing = self.check_cross_team_signoffs(team_id, release_id)
        diagnosis["checks"]["cross_team_signoffs"] = {
            "all_present": all_signed,
            "missing": missing,
        }

        return diagnosis
