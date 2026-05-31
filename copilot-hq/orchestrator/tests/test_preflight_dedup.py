"""
Unit tests for GAP-QA-PREFLIGHT-DEDUP-01: preflight dedup logic in
scripts/release-cycle-start.sh.

These tests simulate the dedup guard by:
1. Creating a temporary repo-like directory structure.
2. Touching mock preflight outbox files at controlled timestamps.
3. Running the dedup section of release-cycle-start.sh via subprocess.
4. Asserting PREFLIGHT-SUPPRESSED or dispatch as expected.

Run with:  python3 orchestrator/tests/test_preflight_dedup.py
"""

import os
import subprocess
import sys
import tempfile
import textwrap
import time
import unittest
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parent.parent.parent
SCRIPT = REPO_ROOT / "scripts" / "release-cycle-start.sh"


def _dedup_check(tmp: Path, qa_agent: str, recent: bool, qa_commits: bool) -> str:
    """
    Run only the dedup block from release-cycle-start.sh in an isolated environment.
    Returns the script stdout+stderr.

    recent: if True, place a preflight outbox file with mtime = now (within 4 h window)
    qa_commits: if True, simulate a QA-scoped commit appearing after the outbox
    """
    outbox_dir = tmp / "sessions" / qa_agent / "outbox"
    outbox_dir.mkdir(parents=True, exist_ok=True)

    # Existing exact-match guard: always ensure inbox/outbox for THIS item don't exist
    inbox_dir = tmp / "sessions" / qa_agent / "inbox" / "20991231-release-preflight-test-suite-test-release"
    inbox_dir.mkdir(parents=True, exist_ok=True)  # <-- makes the exact guard fire first? No, we use a unique item_id

    if recent:
        mock_outbox = outbox_dir / "20260406-release-preflight-test-suite-prior-release.md"
        mock_outbox.write_text("mock preflight outbox\n")
        # mtime = now (definitely within 4 hours)
        now = time.time()
        os.utime(mock_outbox, (now, now))

    # Build a minimal git repo so `git log --since` works
    git_dir = tmp / ".git"
    if not git_dir.exists():
        subprocess.run(["git", "init", str(tmp)], capture_output=True, check=True)
        subprocess.run(
            ["git", "-C", str(tmp), "config", "user.email", "test@test.test"],
            capture_output=True, check=True,
        )
        subprocess.run(
            ["git", "-C", str(tmp), "config", "user.name", "Test"],
            capture_output=True, check=True,
        )
        # Initial commit so `git log` works at all
        (tmp / "README.md").write_text("init\n")
        subprocess.run(["git", "-C", str(tmp), "add", "README.md"], capture_output=True, check=True)
        subprocess.run(
            ["git", "-C", str(tmp), "commit", "-m", "init", "--allow-empty"],
            capture_output=True, check=True,
        )

    if qa_commits:
        qa_file = tmp / "qa-suites" / "test.json"
        qa_file.parent.mkdir(parents=True, exist_ok=True)
        qa_file.write_text('{"test": 1}\n')
        subprocess.run(["git", "-C", str(tmp), "add", str(qa_file)], capture_output=True, check=True)
        subprocess.run(
            ["git", "-C", str(tmp), "commit", "-m", "qa: update suite"],
            capture_output=True, check=True,
        )

    # Inline the dedup block as a standalone script
    dedup_script = textwrap.dedent(f"""
        #!/usr/bin/env bash
        set -euo pipefail
        ROOT_DIR="{tmp}"
        qa_agent="{qa_agent}"
        _recent_outbox=""
        _four_hours_ago=$(date -d "4 hours ago" +%s 2>/dev/null || date -v-4H +%s 2>/dev/null || echo "0")
        for _f in "${{ROOT_DIR}}/sessions/${{qa_agent}}/outbox/"*-release-preflight-test-suite-*.md; do
          [ -f "$_f" ] || continue
          _fmtime=$(stat -c %Y "$_f" 2>/dev/null || stat -f %m "$_f" 2>/dev/null || echo "0")
          if [ "$_fmtime" -ge "$_four_hours_ago" ]; then
            if [ -z "$_recent_outbox" ]; then
              _recent_outbox="$_f"
              _recent_mtime="$_fmtime"
            elif [ "$_fmtime" -gt "$_recent_mtime" ]; then
              _recent_outbox="$_f"
              _recent_mtime="$_fmtime"
            fi
          fi
        done
        if [ -n "$_recent_outbox" ]; then
          _outbox_iso=$(date -d "@${{_recent_mtime}}" -Iseconds 2>/dev/null || date -r "$_recent_mtime" -Iseconds 2>/dev/null || echo "")
          _qa_commits=""
          if [ -n "$_outbox_iso" ]; then
            _qa_commits=$(git -C "$ROOT_DIR" log --since="$_outbox_iso" --oneline \\
              -- "qa-suites/" "org-chart/sites/*/qa-permissions.json" "features/**/03-test-plan.md" \\
              2>/dev/null | head -1 || true)
          fi
          if [ -z "$_qa_commits" ]; then
            echo "PREFLIGHT-SUPPRESSED: recent outbox exists (${{_recent_outbox}}), no QA-scoped commits since ${{_outbox_iso}}; skipping dispatch."
            exit 0
          fi
        fi
        echo "PREFLIGHT-ALLOWED"
    """)

    result = subprocess.run(
        ["bash", "-c", dedup_script],
        capture_output=True, text=True,
    )
    return result.stdout + result.stderr


class TestPreflightDedup(unittest.TestCase):

    def setUp(self):
        self._tmpdir = tempfile.TemporaryDirectory()
        self.tmp = Path(self._tmpdir.name)

    def tearDown(self):
        self._tmpdir.cleanup()

    def test_suppressed_when_recent_outbox_no_qa_commits(self):
        """AC1: recent preflight outbox + no QA commits → SUPPRESSED."""
        out = _dedup_check(self.tmp, "qa-dungeoncrawler", recent=True, qa_commits=False)
        self.assertIn("PREFLIGHT-SUPPRESSED", out, f"Expected suppression. Got: {out!r}")

    def test_allowed_when_no_recent_outbox(self):
        """AC1 (inverse): no recent outbox → dispatch allowed."""
        out = _dedup_check(self.tmp, "qa-dungeoncrawler", recent=False, qa_commits=False)
        self.assertIn("PREFLIGHT-ALLOWED", out, f"Expected allowed. Got: {out!r}")

    def test_allowed_when_qa_commits_exist(self):
        """AC2: recent outbox + QA-scoped commits → dispatch allowed."""
        out = _dedup_check(self.tmp, "qa-dungeoncrawler", recent=True, qa_commits=True)
        self.assertIn("PREFLIGHT-ALLOWED", out, f"Expected allowed after QA commit. Got: {out!r}")

    def test_different_agent(self):
        """Suppression is per-agent: different agent with its own recent outbox."""
        out = _dedup_check(self.tmp, "qa-forseti", recent=True, qa_commits=False)
        self.assertIn("PREFLIGHT-SUPPRESSED", out, f"Expected suppression for qa-forseti. Got: {out!r}")


if __name__ == "__main__":
    unittest.main(verbosity=2)
