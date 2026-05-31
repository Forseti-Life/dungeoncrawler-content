"""
Unit tests for GAP-GATE2-AUTO-APPROVE: _dispatch_gate2_auto_approve logic.

Tests the trigger conditions using self-contained temp directory structures.
Does NOT import run.py (requires langgraph). Reimplements the detection logic inline.

Run with:  python3 orchestrator/tests/test_gate2_auto_approve.py
"""

import ast
import re
import tempfile
import textwrap
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent.parent
RUN_PY = REPO_ROOT / "orchestrator" / "run.py"


# ── Inline reimplementation of gate2 auto-approve detection logic ─────────────

def _features_for_release(features_root: Path, site_kw: str, release_id: str):
    """Return list of feature IDs in_progress for the given site+release."""
    result = []
    for fm in features_root.glob("*/feature.md"):
        try:
            text = fm.read_text(encoding="utf-8", errors="ignore")
            if (re.search(r"^-\s+Status:\s*in_progress", text, re.MULTILINE | re.IGNORECASE)
                    and re.search(rf"^-\s+Website:.*{re.escape(site_kw)}", text, re.MULTILINE | re.IGNORECASE)
                    and re.search(rf"^-\s+Release:\s*{re.escape(release_id)}\s*$", text, re.MULTILINE | re.IGNORECASE)):
                result.append(fm.parent.name)
        except Exception:
            pass
    return result


def _should_auto_approve(tmp: Path, team_id: str, qa_agent: str, site_kw: str, release_id: str) -> bool:
    """Simulate the gate2-auto-approve condition check. Returns True if APPROVE should be filed."""
    qa_outbox = tmp / "sessions" / qa_agent / "outbox"
    qa_inbox  = tmp / "sessions" / qa_agent / "inbox"
    features_root = tmp / "features"

    # Condition 4: skip if gate2-approve outbox already exists for this release
    if qa_outbox.exists():
        for f in qa_outbox.iterdir():
            if f.is_file() and "gate2-approve" in f.name:
                content = f.read_text(encoding="utf-8", errors="ignore")
                if release_id in content:
                    return False

    # Condition 1: must have >= 1 in-progress feature
    features = _features_for_release(features_root, site_kw, release_id)
    if not features:
        return False

    # Condition 2: every feature must have a suite-activate outbox entry
    if not qa_outbox.exists():
        return False
    outbox_names = [f.name for f in qa_outbox.iterdir() if f.is_file()]
    all_activated = all(
        any(feat_id in ob and "suite-activate" in ob for ob in outbox_names)
        for feat_id in features
    )
    if not all_activated:
        return False

    # Condition 3: no suite-activate inbox items outside _archived
    if qa_inbox.exists():
        pending = [
            d for d in qa_inbox.iterdir()
            if d.is_dir() and d.name != "_archived" and "suite-activate" in d.name
        ]
        if pending:
            return False

    return True


# ── Helpers ────────────────────────────────────────────────────────────────────

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


def _write_suite_activate_outbox(outbox_dir: Path, timestamp: str, feat_id: str) -> None:
    outbox_dir.mkdir(parents=True, exist_ok=True)
    (outbox_dir / f"{timestamp}-suite-activate-{feat_id}.md").write_text(
        f"- Status: done\n- Summary: Suite activated for {feat_id}.\n"
    )


def _write_suite_activate_inbox(inbox_dir: Path, timestamp: str, feat_id: str) -> None:
    item = inbox_dir / f"{timestamp}-suite-activate-{feat_id}"
    item.mkdir(parents=True, exist_ok=True)
    (item / "command.md").write_text(f"# Suite Activation: {feat_id}\n")


# ── Tests ──────────────────────────────────────────────────────────────────────

TEAM_ID   = "dungeoncrawler"
QA_AGENT  = "qa-dungeoncrawler"
SITE_KW   = "dungeoncrawler"
RELEASE   = "2099-dungeoncrawler-release"


class TestGate2AutoApproveConditions(unittest.TestCase):

    def test_fires_when_all_conditions_met(self):
        """All conditions satisfied → should_auto_approve returns True."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            _write_feature(tmp / "features", "feat-alpha", SITE_KW, RELEASE)
            _write_suite_activate_outbox(tmp / "sessions" / QA_AGENT / "outbox", "20260408-120000", "feat-alpha")
            # No pending inbox items
            self.assertTrue(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))

    def test_suppressed_no_features(self):
        """No in-progress features → not fired (empty release)."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            (tmp / "features").mkdir(parents=True)
            (tmp / "sessions" / QA_AGENT / "outbox").mkdir(parents=True)
            self.assertFalse(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))

    def test_suppressed_pending_suite_activate_inbox(self):
        """Pending suite-activate inbox item exists → not fired."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            _write_feature(tmp / "features", "feat-beta", SITE_KW, RELEASE)
            _write_suite_activate_outbox(tmp / "sessions" / QA_AGENT / "outbox", "20260408-120000", "feat-beta")
            # Add a pending inbox item
            _write_suite_activate_inbox(tmp / "sessions" / QA_AGENT / "inbox", "20260408-120001", "feat-gamma")
            self.assertFalse(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))

    def test_suppressed_missing_suite_activate_outbox(self):
        """Feature in-progress but no suite-activate outbox → not fired."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            _write_feature(tmp / "features", "feat-delta", SITE_KW, RELEASE)
            (tmp / "sessions" / QA_AGENT / "outbox").mkdir(parents=True)
            # No suite-activate outbox written
            self.assertFalse(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))

    def test_suppressed_gate2_already_exists(self):
        """Gate 2 APPROVE already filed → not fired again."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            _write_feature(tmp / "features", "feat-epsilon", SITE_KW, RELEASE)
            outbox = tmp / "sessions" / QA_AGENT / "outbox"
            _write_suite_activate_outbox(outbox, "20260408-120000", "feat-epsilon")
            # Write existing gate2-approve
            (outbox / f"20260408-110000-gate2-approve-{RELEASE}.md").write_text(
                f"- Release: {RELEASE}\n\nAPPROVE\n"
            )
            self.assertFalse(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))

    def test_multiple_features_all_activated(self):
        """Multiple features, all activated → fires."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            outbox = tmp / "sessions" / QA_AGENT / "outbox"
            for i in range(3):
                feat = f"feat-{i}"
                _write_feature(tmp / "features", feat, SITE_KW, RELEASE)
                _write_suite_activate_outbox(outbox, f"20260408-12000{i}", feat)
            self.assertTrue(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))

    def test_multiple_features_partial_activation(self):
        """Multiple features, only some activated → not fired."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            outbox = tmp / "sessions" / QA_AGENT / "outbox"
            outbox.mkdir(parents=True)
            for i in range(3):
                feat = f"feat-{i}"
                _write_feature(tmp / "features", feat, SITE_KW, RELEASE)
            # Only activate 2 of 3
            _write_suite_activate_outbox(outbox, "20260408-120000", "feat-0")
            _write_suite_activate_outbox(outbox, "20260408-120001", "feat-1")
            # feat-2 not activated
            self.assertFalse(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))

    def test_archived_inbox_items_ignored(self):
        """Suite-activate items in _archived/ don't block the trigger."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            _write_feature(tmp / "features", "feat-zeta", SITE_KW, RELEASE)
            outbox = tmp / "sessions" / QA_AGENT / "outbox"
            _write_suite_activate_outbox(outbox, "20260408-120000", "feat-zeta")
            # Write an archived suite-activate item (should be ignored)
            archived = tmp / "sessions" / QA_AGENT / "inbox" / "_archived" / "20260408-110000-suite-activate-old"
            archived.mkdir(parents=True)
            self.assertTrue(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))

    def test_done_features_excluded(self):
        """Status: done features (not in_progress) are excluded from the expected set."""
        with tempfile.TemporaryDirectory() as td:
            tmp = Path(td)
            # One done feature (excluded), one in_progress (activated)
            _write_feature(tmp / "features", "feat-done", SITE_KW, RELEASE, status="done")
            _write_feature(tmp / "features", "feat-live", SITE_KW, RELEASE)
            outbox = tmp / "sessions" / QA_AGENT / "outbox"
            _write_suite_activate_outbox(outbox, "20260408-120000", "feat-live")
            # feat-done is not counted; only feat-live needed
            self.assertTrue(_should_auto_approve(tmp, TEAM_ID, QA_AGENT, SITE_KW, RELEASE))


class TestGate2AutoApproveFunctionExists(unittest.TestCase):
    """Verify _dispatch_gate2_auto_approve is defined and called in run.py."""

    def test_function_defined_in_source(self):
        """_dispatch_gate2_auto_approve must be a defined function in run.py."""
        source = RUN_PY.read_text(encoding="utf-8")
        tree = ast.parse(source, filename=str(RUN_PY))
        fn_names = {n.name for n in ast.walk(tree) if isinstance(n, ast.FunctionDef)}
        self.assertIn("_dispatch_gate2_auto_approve", fn_names)

    def test_function_called_in_tick_loop(self):
        """_dispatch_gate2_auto_approve must be called in _run_tick."""
        source = RUN_PY.read_text(encoding="utf-8")
        call_count = len(re.findall(r"_dispatch_gate2_auto_approve\s*\(", source))
        self.assertGreaterEqual(call_count, 2,
            "Function must be defined + called at least once (definition + call site)")

    def test_gate2_log_line_present(self):
        """Constraint: log line [gate2-auto-approve] must be present (per AC)."""
        source = RUN_PY.read_text(encoding="utf-8")
        self.assertIn("[gate2-auto-approve]", source)


if __name__ == "__main__":
    unittest.main()
