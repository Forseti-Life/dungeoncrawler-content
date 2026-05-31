"""
Regression tests for release handoff gaps:

1. Stage 0 scope activation must create a durable Dev work item as well as QA suite activation.
2. Scope-activate nudges must key off in_progress release features, not just release-tagged ready features.
3. Feature-gap remediation must still dispatch Dev work when QA coverage already exists.
"""

import ast
import re
import shutil
import subprocess
import tempfile
import textwrap
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent.parent
RUN_PY = REPO_ROOT / "orchestrator" / "run.py"
SCOPE_ACTIVATE = REPO_ROOT / "scripts" / "pm-scope-activate.sh"


def _write_feature(features_dir: Path, name: str, site: str, release_id: str, status: str, dev_owner: str) -> None:
    feat_dir = features_dir / name
    feat_dir.mkdir(parents=True, exist_ok=True)
    (feat_dir / "feature.md").write_text(
        textwrap.dedent(
            f"""\
            # Feature Brief

            - Work item id: {name}
            - Website: {site}
            - Status: {status}
            - Release: {release_id}
            - Dev owner: {dev_owner}
            - QA owner: qa-{site}

            ## Security acceptance criteria
            - Authentication/permission surface: authenticated users only
            """
        ),
        encoding="utf-8",
    )
    (feat_dir / "01-acceptance-criteria.md").write_text("# AC\n", encoding="utf-8")
    (feat_dir / "03-test-plan.md").write_text("# Test plan\n", encoding="utf-8")


def _count_site_features_for_release(features_root: Path, site_keyword: str, release_id: str) -> int:
    count = 0
    for fm in features_root.glob("*/feature.md"):
        text = fm.read_text(encoding="utf-8", errors="ignore")
        has_status = bool(re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE))
        has_site = bool(re.search(rf"^-\s+Website:.*{re.escape(site_keyword)}", text, re.MULTILINE | re.IGNORECASE))
        has_release = bool(re.search(rf"^-\s+Release:\s*(?:\n\s*)*{re.escape(release_id)}\s*$", text, re.MULTILINE | re.IGNORECASE))
        if has_status and has_site and has_release:
            count += 1
    return count


class TestPMScopeActivateDevHandoff(unittest.TestCase):
    def test_scope_activate_creates_dev_and_qa_items(self):
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            (tmp / "scripts").mkdir(parents=True)
            shutil.copy2(SCOPE_ACTIVATE, tmp / "scripts" / "pm-scope-activate.sh")
            (tmp / "features").mkdir()
            (tmp / "sessions").mkdir()
            (tmp / "tmp" / "release-cycle-active").mkdir(parents=True)
            (tmp / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
                "20990101-forseti-release-b\n", encoding="utf-8"
            )
            _write_feature(
                tmp / "features",
                "forseti-langgraph-console-release-panel",
                "forseti.life",
                "20990101-forseti-release-b",
                "ready",
                "dev-forseti-agent-tracker",
            )

            result = subprocess.run(
                ["bash", "scripts/pm-scope-activate.sh", "forseti", "forseti-langgraph-console-release-panel"],
                cwd=tmp,
                capture_output=True,
                text=True,
                check=False,
            )
            self.assertEqual(result.returncode, 0, msg=result.stderr)

            dev_items = list((tmp / "sessions" / "dev-forseti-agent-tracker" / "inbox").glob("*-impl-forseti-langgraph-console-release-panel"))
            qa_items = list((tmp / "sessions" / "qa-forseti" / "inbox").glob("*-suite-activate-forseti-langgraph-console-release-panel"))
            self.assertEqual(len(dev_items), 1, "Stage 0 activation must create exactly one Dev work item")
            self.assertEqual(len(qa_items), 1, "Stage 0 activation must create exactly one QA suite-activate item")

            updated = (tmp / "features" / "forseti-langgraph-console-release-panel" / "feature.md").read_text(encoding="utf-8")
            self.assertIn("- Status: in_progress", updated)
            self.assertIn("- Release: 20990101-forseti-release-b", updated)


class TestScopeActivateNudgeGuard(unittest.TestCase):
    def test_ready_release_tags_do_not_count_as_activated_scope(self):
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-ready", "forseti.life", "20990101-forseti-release-b", "ready", "dev-forseti")
            count = _count_site_features_for_release(features, "forseti", "20990101-forseti-release-b")
            self.assertEqual(count, 0, "Ready features tagged to a release must not suppress Stage 0 activation nudges")

    def test_in_progress_release_feature_counts_as_activated_scope(self):
        with tempfile.TemporaryDirectory() as td:
            features = Path(td) / "features"
            features.mkdir()
            _write_feature(features, "feat-live", "forseti.life", "20990101-forseti-release-b", "in_progress", "dev-forseti")
            count = _count_site_features_for_release(features, "forseti", "20990101-forseti-release-b")
            self.assertEqual(count, 1)


class TestSourceGuardsPresent(unittest.TestCase):
    def test_scope_activate_nudge_uses_in_progress_release_counter(self):
        source = RUN_PY.read_text(encoding="utf-8")
        self.assertIn(
            "feature_count = _count_site_features_for_release(site, rid)",
            source,
            "Scope activate nudge must key off activated in-progress release features",
        )

    def test_feature_gap_a_depends_only_on_dev_coverage(self):
        source = RUN_PY.read_text(encoding="utf-8")
        self.assertRegex(
            source,
            r"if not dev_has_inbox and not dev_has_outbox:",
            "Feature GAP-A must dispatch Dev work even when QA coverage already exists",
        )

    def test_feature_gap_uses_feature_level_dev_owner(self):
        source = RUN_PY.read_text(encoding="utf-8")
        tree = ast.parse(source, filename=str(RUN_PY))
        self.assertIn("Dev owner:", source)
        self.assertTrue(
            any(isinstance(node, ast.Assign) and any(getattr(t, "id", "") == "dev_agent" for t in node.targets) for node in ast.walk(tree)),
            "run.py must assign dev_agent using feature-level owner parsing",
        )


if __name__ == "__main__":
    unittest.main()
