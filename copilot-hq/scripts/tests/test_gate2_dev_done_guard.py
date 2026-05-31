"""
Tests for the Gate 2 dev-done guard in site-audit-run.sh.

The guard lives inside the dispatch_findings() Python heredoc (lines 48–624).
We extract that block, inject synthetic globals, call _queue_pm_gate2_ready_item()
and verify the correct suppression / dispatch behaviour.
"""
import io
import os
import re
import sys
import textwrap
import types
from pathlib import Path
import tempfile
import pytest


# ---------------------------------------------------------------------------
# Helper: extract and execute the Python heredoc from site-audit-run.sh
# ---------------------------------------------------------------------------

_SCRIPT = Path(__file__).resolve().parents[1] / "site-audit-run.sh"


def _load_dispatch_python(root: Path, *, label="forseti.life", base_url="http://localhost:9999",
                           dev_agent_id="dev-forseti", pm_agent_id="pm-forseti",
                           run_ts="20260408-120000", release_cycle_active=True,
                           release_id="20260408-forseti-release-b", team_id="forseti",
                           open_issue_total_override=0) -> types.ModuleType:
    """
    Load the dispatch_findings Python heredoc as a module, with injected globals
    so that it skips network I/O and reports deterministic state.

    open_issue_total_override lets tests control whether there are open issues.
    """
    raw = _SCRIPT.read_text(encoding="utf-8")
    # Extract lines between "<<'PY'" (line 47) and the first bare "PY" terminator.
    lines = raw.splitlines()
    start = None
    end = None
    for i, ln in enumerate(lines):
        if start is None and ln.strip().endswith("<<'PY'"):
            start = i + 1  # first line *after* the heredoc opener
        elif start is not None and ln.strip() == "PY":
            end = i
            break
    assert start is not None and end is not None, "Could not locate Python heredoc in site-audit-run.sh"
    py_block = "\n".join(lines[start:end])

    # Build a synthetic module that runs in `root` as its CWD.
    mod = types.ModuleType("dispatch_findings_py")
    mod.__file__ = str(_SCRIPT)

    # We need to redirect stdout to capture prints, and override sys.argv.
    out_dir = root / "qa-run-output"
    out_dir.mkdir(parents=True, exist_ok=True)

    original_argv = sys.argv
    original_cwd = os.getcwd()
    try:
        os.chdir(root)
        sys.argv = [
            "dispatch_findings_py",
            label,
            base_url,
            str(out_dir),
            dev_agent_id,
            pm_agent_id,
            run_ts,
            "",  # role_cfg_path
            "1" if release_cycle_active else "0",
            release_id,
            team_id,
        ]

        # Patch: inject synthetic globals *before* the block assigns them.
        # We prepend overrides so the script's own assignments take precedence
        # (the real script sets summary/open_issue_total from network calls —
        # we stub them here so no network access is needed).
        preamble = textwrap.dedent(f"""
            import json, re, sys
            from pathlib import Path
            from datetime import datetime, timezone

            label = sys.argv[1]
            base_url = sys.argv[2]
            out_dir = Path(sys.argv[3])
            dev_agent_id = (sys.argv[4] or '').strip()
            pm_agent_id  = (sys.argv[5] or '').strip()
            run_ts        = sys.argv[6]
            release_cycle_active = (sys.argv[8] or '0').strip() == '1'
            release_id    = (sys.argv[9]  or '').strip()
            team_id       = (sys.argv[10] or '').strip()

            # Synthetic audit results — no network calls needed.
            open_issue_total = {open_issue_total_override}
            role_cfg_path = ""
            config_drift_warnings = []
            summary = {{
                "label": label, "base_url": base_url, "run_ts": run_ts,
                "is_prod": False,
                "counts": {{"missing_assets_404s": 0, "pm_acl_questions": 0,
                            "pm_acl_questions_suppressed": 0,
                            "permission_violations": 0, "failures": 0}},
                "missing_assets_404s": [], "pm_acl_questions": [],
                "permission_violations": [], "failures": [],
                "config_drift_warnings": [],
                "evidence": {{}},
            }}
        """)

        # We only want to exec the helper functions + _queue_pm_gate2_ready_item.
        # Skip the network-heavy crawl/audit section by replacing it with stubs.
        # The functions we need start at "def _slug" onward.
        # Find "def _slug" in the extracted block and splice there.
        slug_idx = py_block.find("def _slug(")
        assert slug_idx != -1, "Could not find def _slug() in heredoc"
        functions_and_call = py_block[slug_idx:]

        combined = preamble + "\n" + functions_and_call

        captured = io.StringIO()
        import builtins
        _real_print = builtins.print
        try:
            builtins.print = lambda *a, **kw: captured.write(" ".join(str(x) for x in a) + "\n")
            exec(combined, mod.__dict__)  # noqa: S102
        finally:
            builtins.print = _real_print

        mod._captured_output = captured.getvalue()
    finally:
        sys.argv = original_argv
        os.chdir(original_cwd)

    return mod


# ---------------------------------------------------------------------------
# Test fixtures helpers
# ---------------------------------------------------------------------------

def _make_hq(tmp: Path, *,
             feature_id="fr-test-feature",
             feature_status="in_progress",
             feature_release="20260408-forseti-release-b",
             feature_website="forseti.life",
             has_dev_outbox=False,
             dev_agent_id="dev-forseti",
             pm_agent_id="pm-forseti",
             release_id="20260408-forseti-release-b",
             team_id="forseti") -> Path:
    root = tmp
    # Feature
    feat_dir = root / "features" / feature_id
    feat_dir.mkdir(parents=True, exist_ok=True)
    (feat_dir / "feature.md").write_text(
        f"# Feature\n"
        f"- Work item id: {feature_id}\n"
        f"- Website: {feature_website}\n"
        f"- Status: {feature_status}\n"
        f"- Release: {feature_release}\n",
        encoding="utf-8"
    )
    # Active release marker
    active_dir = root / "tmp" / "release-cycle-active"
    active_dir.mkdir(parents=True, exist_ok=True)
    (active_dir / f"{team_id}.release_id").write_text(release_id + "\n")
    # PM inbox/outbox dirs
    (root / "sessions" / pm_agent_id / "inbox").mkdir(parents=True, exist_ok=True)
    (root / "sessions" / pm_agent_id / "outbox").mkdir(parents=True, exist_ok=True)
    # Dev outbox (optional)
    dev_outbox = root / "sessions" / dev_agent_id / "outbox"
    dev_outbox.mkdir(parents=True, exist_ok=True)
    if has_dev_outbox:
        (dev_outbox / f"20260408-impl-{feature_id}.md").write_text("- Status: done\n")
    return root


# ---------------------------------------------------------------------------
# Tests
# ---------------------------------------------------------------------------

class TestGate2DevDoneGuard:

    def test_guard_suppresses_when_no_dev_outbox(self, tmp_path):
        """In-progress feature with no dev outbox → gate2-ready suppressed."""
        root = _make_hq(tmp_path, has_dev_outbox=False)
        mod = _load_dispatch_python(root, open_issue_total_override=0)
        out = mod._captured_output
        assert "Gate2-ready suppressed: feature fr-test-feature has no dev outbox yet" in out, (
            f"Expected suppression message; got:\n{out}"
        )
        # No gate2-ready inbox item created
        pm_inbox = root / "sessions" / "pm-forseti" / "inbox"
        gate2_items = [p for p in pm_inbox.iterdir() if "gate2-ready" in p.name]
        assert gate2_items == [], f"gate2-ready item unexpectedly created: {gate2_items}"

    def test_guard_allows_when_dev_outbox_exists(self, tmp_path):
        """In-progress feature with dev outbox present → gate2-ready queued."""
        root = _make_hq(tmp_path, has_dev_outbox=True)
        mod = _load_dispatch_python(root, open_issue_total_override=0)
        out = mod._captured_output
        assert "Gate2-ready suppressed" not in out, (
            f"Unexpected suppression; output:\n{out}"
        )
        pm_inbox = root / "sessions" / "pm-forseti" / "inbox"
        gate2_items = [p for p in pm_inbox.iterdir() if "gate2-ready" in p.name]
        assert len(gate2_items) == 1, f"Expected 1 gate2-ready item; got {gate2_items}"

    def test_guard_skipped_when_open_issues(self, tmp_path):
        """When open_issue_total > 0, function returns before guard (no suppression msg)."""
        root = _make_hq(tmp_path, has_dev_outbox=False)
        mod = _load_dispatch_python(root, open_issue_total_override=3)
        out = mod._captured_output
        # Guard doesn't run, no suppression message, and no gate2-ready item
        assert "Gate2-ready suppressed" not in out
        pm_inbox = root / "sessions" / "pm-forseti" / "inbox"
        gate2_items = [p for p in pm_inbox.iterdir() if "gate2-ready" in p.name]
        assert gate2_items == []

    def test_guard_ignores_done_features(self, tmp_path):
        """Features with Status: done are not checked — gate2-ready proceeds."""
        root = _make_hq(tmp_path, feature_status="done", has_dev_outbox=False)
        mod = _load_dispatch_python(root, open_issue_total_override=0)
        out = mod._captured_output
        assert "Gate2-ready suppressed" not in out
        pm_inbox = root / "sessions" / "pm-forseti" / "inbox"
        gate2_items = [p for p in pm_inbox.iterdir() if "gate2-ready" in p.name]
        assert len(gate2_items) == 1, f"Expected 1 gate2-ready item; got {gate2_items}"

    def test_guard_ignores_different_release(self, tmp_path):
        """Feature belongs to a different release — guard does not block dispatch."""
        root = _make_hq(
            tmp_path,
            feature_release="20260401-forseti-release-a",  # different from active
            has_dev_outbox=False,
        )
        mod = _load_dispatch_python(root, open_issue_total_override=0)
        out = mod._captured_output
        assert "Gate2-ready suppressed" not in out
        pm_inbox = root / "sessions" / "pm-forseti" / "inbox"
        gate2_items = [p for p in pm_inbox.iterdir() if "gate2-ready" in p.name]
        assert len(gate2_items) == 1

    def test_guard_suppression_message_format(self, tmp_path):
        """Suppression message includes the feature ID exactly as specified in AC."""
        feature_id = "fr-my-specific-feature"
        root = _make_hq(tmp_path, feature_id=feature_id, has_dev_outbox=False)
        mod = _load_dispatch_python(root, open_issue_total_override=0)
        out = mod._captured_output
        expected = f"Gate2-ready suppressed: feature {feature_id} has no dev outbox yet"
        assert expected in out, f"Expected exact message '{expected}'; got:\n{out}"

    def test_guard_team_id_website_matching(self, tmp_path):
        """Website: forseti (team_id) matches even when feature.md uses team_id form."""
        # Some feature.md may say Website: forseti instead of forseti.life
        root = _make_hq(tmp_path, feature_website="forseti", has_dev_outbox=False)
        mod = _load_dispatch_python(root, open_issue_total_override=0)
        out = mod._captured_output
        assert "Gate2-ready suppressed" in out, (
            f"Expected guard to fire for Website: forseti; got:\n{out}"
        )
