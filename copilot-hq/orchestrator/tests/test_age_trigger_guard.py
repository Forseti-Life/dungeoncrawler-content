"""
Unit tests for GAP-AGE-EMPTY-RELEASE-GUARD (AC1-AC4):
- AC1: AGE trigger does NOT fire when release_feature_count == 0
- AC2: AGE trigger DOES fire when release_feature_count >= 1 and age >= threshold
- AC3: preflight 0-feature guard in release-cycle-start.sh (tested in test_preflight_dedup.py)
- AC4: _dispatch_release_close_triggers is defined in run.py (no NameError)

These tests are self-contained and do NOT import run.py (which requires langgraph).
The feature-count logic is re-implemented inline for unit testing.

Run with:  python3 orchestrator/tests/test_age_trigger_guard.py
"""

import ast
import re
import tempfile
import textwrap
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent.parent
RUN_PY = REPO_ROOT / "orchestrator" / "run.py"

# --- Inline implementation of _count_site_features_for_release for unit testing ---
# This mirrors the logic in run.py without requiring the langgraph import chain.

def _count_site_features_for_release(features_root: Path, site_keyword: str, release_id: str) -> int:
    """Mirror of run.py:_count_site_features_for_release with injectable features root."""
    count = 0
    for fm in features_root.glob("*/feature.md"):
        try:
            text = fm.read_text(encoding="utf-8", errors="ignore")
            has_status  = bool(re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE))
            has_site    = bool(re.search(rf"^-\s+Website:.*{re.escape(site_keyword)}", text, re.MULTILINE | re.IGNORECASE))
            has_release = bool(re.search(rf"^-\s+Release:\s*{re.escape(release_id)}\s*$", text, re.MULTILINE | re.IGNORECASE))
            if has_status and has_site and has_release:
                count += 1
        except Exception:
            pass
    return count


def _write_feature(features_dir: Path, name: str, site: str, release_id: str, status: str = "in_progress") -> None:
    feat_dir = features_dir / name
    feat_dir.mkdir(parents=True, exist_ok=True)
    (feat_dir / "feature.md").write_text(
        textwrap.dedent(f"""\
            # {name}
            - Status: {status}
            - Website: {site}
            - Release: {release_id}
        """)
    )


_RELEASE_CLOSE_AGE_HOURS = 24  # mirror of run.py constant


def _age_trigger_fires(features_root: Path, site_kw: str, release_id: str, age_hours: float) -> list:
    """Simulate AGE trigger logic from run.py. Returns list of trigger strings."""
    feature_count = _count_site_features_for_release(features_root, site_kw, release_id)
    triggers = []
    if age_hours >= _RELEASE_CLOSE_AGE_HOURS and feature_count > 0:
        triggers.append(
            f"AGE: release {release_id} started {age_hours:.1f}h ago "
            f"(threshold {_RELEASE_CLOSE_AGE_HOURS}h)"
        )
    return triggers


class TestCountSiteFeaturesForRelease(unittest.TestCase):
    """Unit tests for the feature-count logic (mirrors run.py:_count_site_features_for_release)."""

    def test_zero_features_returns_zero(self):
        """No features at all → count is 0."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            self.assertEqual(_count_site_features_for_release(features, "forseti", "2099-forseti-release"), 0)

    def test_matching_feature_counted(self):
        """One matching in_progress feature → count is 1."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-alpha", "forseti", "2099-forseti-release")
            self.assertEqual(_count_site_features_for_release(features, "forseti", "2099-forseti-release"), 1)

    def test_wrong_release_not_counted(self):
        """Feature for a different release_id → not counted."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-beta", "forseti", "2099-forseti-OTHER")
            self.assertEqual(_count_site_features_for_release(features, "forseti", "2099-forseti-release"), 0)

    def test_wrong_site_not_counted(self):
        """Feature for a different site → not counted."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-gamma", "dungeoncrawler", "2099-forseti-release")
            self.assertEqual(_count_site_features_for_release(features, "forseti", "2099-forseti-release"), 0)

    def test_completed_feature_not_counted(self):
        """Status: done → not counted (only in_progress counts)."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-done", "forseti", "2099-forseti-release", status="done")
            self.assertEqual(_count_site_features_for_release(features, "forseti", "2099-forseti-release"), 0)

    def test_multiple_features_counted(self):
        """Three matching in_progress features → count is 3."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            for i in range(3):
                _write_feature(features, f"feat-{i}", "forseti", "2099-forseti-release")
            self.assertEqual(_count_site_features_for_release(features, "forseti", "2099-forseti-release"), 3)


class TestAGETriggerGuard(unittest.TestCase):
    """AC1 and AC2: AGE trigger empty-release guard."""

    def test_ac1_age_trigger_suppressed_zero_features(self):
        """AC1: AGE trigger must NOT fire when release has 0 features (empty release guard)."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            triggers = _age_trigger_fires(features, "forseti", "2099-forseti-release", age_hours=30)
            self.assertEqual(triggers, [], "AGE trigger must be suppressed for empty release")

    def test_ac2_age_trigger_fires_with_features(self):
        """AC2: AGE trigger MUST fire when age >= threshold AND release has ≥1 features."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-live", "forseti", "2099-forseti-release")
            triggers = _age_trigger_fires(features, "forseti", "2099-forseti-release", age_hours=25)
            self.assertEqual(len(triggers), 1, "AGE trigger must fire for non-empty release past threshold")
            self.assertIn("AGE:", triggers[0])

    def test_age_trigger_suppressed_below_threshold(self):
        """AGE trigger must NOT fire when age < threshold, even with features present."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-young", "forseti", "2099-forseti-release")
            triggers = _age_trigger_fires(features, "forseti", "2099-forseti-release", age_hours=10)
            self.assertEqual(triggers, [], "AGE trigger must not fire before age threshold")

    def test_age_trigger_boundary_exactly_at_threshold(self):
        """AGE trigger fires at exactly threshold hours with ≥1 features."""
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-boundary", "forseti", "2099-forseti-release")
            triggers = _age_trigger_fires(features, "forseti", "2099-forseti-release",
                                          age_hours=_RELEASE_CLOSE_AGE_HOURS)
            self.assertEqual(len(triggers), 1, "AGE trigger must fire at exactly the threshold")


class TestDispatchReleaseCloseTriggersDefinition(unittest.TestCase):
    """AC4: _dispatch_release_close_triggers must be defined in run.py (no NameError possible)."""

    def test_function_defined_in_source(self):
        """Parse run.py AST and verify _dispatch_release_close_triggers is a top-level function."""
        source = RUN_PY.read_text(encoding="utf-8")
        tree = ast.parse(source, filename=str(RUN_PY))
        fn_names = {
            node.name
            for node in ast.walk(tree)
            if isinstance(node, ast.FunctionDef)
        }
        self.assertIn(
            "_dispatch_release_close_triggers",
            fn_names,
            "_dispatch_release_close_triggers must be defined in orchestrator/run.py"
        )

    def test_age_guard_present_in_source(self):
        """Verify the AGE trigger guard (release_feature_count > 0) is present in run.py source."""
        source = RUN_PY.read_text(encoding="utf-8")
        self.assertRegex(
            source,
            r"release_feature_count\s*>\s*0",
            "AGE trigger guard 'release_feature_count > 0' must be present in run.py"
        )

    def test_dispatch_called_in_source(self):
        """Verify _dispatch_release_close_triggers is called (not just defined) in run.py."""
        source = RUN_PY.read_text(encoding="utf-8")
        # Should appear as a call, not just a def
        call_count = len(re.findall(r"_dispatch_release_close_triggers\s*\(", source))
        self.assertGreaterEqual(
            call_count,
            2,  # definition + at least one call site
            "_dispatch_release_close_triggers must be called in run.py (not just defined)"
        )


if __name__ == "__main__":
    unittest.main()
