"""Tests for release-signoff.sh team ownership enforcement."""

import os
import subprocess
import sys
import tempfile
import unittest

REPO_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
SIGNOFF_SCRIPT = os.path.join(REPO_ROOT, 'scripts', 'release-signoff.sh')
PRODUCT_TEAMS_JSON = os.path.join(REPO_ROOT, 'org-chart', 'products', 'product-teams.json')


def _make_tmp_repo(base: str, *, pm_agent: str, qa_agent: str) -> None:
    """Create minimum sessions directory structure under base."""
    os.makedirs(os.path.join(base, 'sessions', pm_agent, 'artifacts', 'release-signoffs'), exist_ok=True)
    os.makedirs(os.path.join(base, 'sessions', qa_agent, 'outbox'), exist_ok=True)


def _run_signoff(tmp_root: str, site: str, release_id: str, extra_args: list = None) -> subprocess.CompletedProcess:
    env = os.environ.copy()
    env['HOME'] = tmp_root  # isolate any home-relative reads
    cmd = ['bash', SIGNOFF_SCRIPT, site, release_id] + (extra_args or [])
    return subprocess.run(cmd, capture_output=True, text=True, cwd=tmp_root, env=env)


def _write_approve(outbox_dir: str, release_id: str, filename: str = None) -> str:
    os.makedirs(outbox_dir, exist_ok=True)
    fname = filename or f'20260408-gate2-approve-{release_id}.md'
    fpath = os.path.join(outbox_dir, fname)
    with open(fpath, 'w') as f:
        f.write(f'# Gate 2\n\n- Release: {release_id}\n- Status: APPROVE\n')
    return fpath


class TestReleaseSignoffOwnership(unittest.TestCase):

    def _tmp_root(self) -> str:
        """Create a tmpdir that mirrors the HQ repo layout for release-signoff.sh."""
        d = tempfile.mkdtemp()
        # Symlink scripts/ and org-chart/ into the tmpdir so the script resolves them
        # The script uses ROOT_DIR=$(cd dirname/.. && pwd), so we run it from the tmp dir
        # but we need org-chart/products/product-teams.json accessible relative to ROOT_DIR.
        # Easiest: just run from REPO_ROOT with overridden sessions/ via a wrapper test.
        return d

    def _run_in_real_root(self, site: str, release_id: str, extra_qa_outbox_file: str = None,
                          extra_args: list = None):
        """
        Run release-signoff.sh in REPO_ROOT with a throw-away sessions/ tree.
        We patch sessions/ by creating temp subdirs; clean up afterward.
        """
        import shutil
        # Determine signing team's pm/qa agents from product-teams.json
        import json
        with open(PRODUCT_TEAMS_JSON) as f:
            data = json.load(f)

        def resolve_team(query):
            q = query.lower()
            for t in data.get('teams', []):
                ids = [str(t.get('id', '')).lower()] + [str(a).lower() for a in (t.get('aliases') or [])]
                if q in ids:
                    return t
            return None

        signing_team = resolve_team(site)
        self.assertIsNotNone(signing_team, f"Team not found for site: {site}")

        pm_agent = signing_team['pm_agent']
        qa_agent = signing_team.get('qa_agent') or f"qa-{signing_team['id']}"
        slug = release_id.replace('/', '-')[:80]

        # Setup: ensure sessions dirs exist
        signoff_dir = os.path.join(REPO_ROOT, 'sessions', pm_agent, 'artifacts', 'release-signoffs')
        qa_outbox_dir = os.path.join(REPO_ROOT, 'sessions', qa_agent, 'outbox')
        os.makedirs(signoff_dir, exist_ok=True)
        os.makedirs(qa_outbox_dir, exist_ok=True)

        # Place APPROVE file if given
        placed_file = None
        if extra_qa_outbox_file:
            placed_file = extra_qa_outbox_file

        # Ensure signoff artifact doesn't pre-exist (clean test)
        signoff_artifact = os.path.join(signoff_dir, slug + '.md')
        pre_existed = os.path.exists(signoff_artifact)
        if pre_existed:
            os.rename(signoff_artifact, signoff_artifact + '.bak')

        try:
            result = _run_signoff(REPO_ROOT, site, release_id, extra_args)
            artifact_written = os.path.exists(signoff_artifact)
        finally:
            # Clean up: remove placed_file if we created it
            if placed_file and os.path.exists(placed_file):
                os.remove(placed_file)
            # Remove signoff artifact if we created it
            if os.path.exists(signoff_artifact) and not pre_existed:
                os.remove(signoff_artifact)
            if pre_existed and os.path.exists(signoff_artifact + '.bak'):
                os.rename(signoff_artifact + '.bak', signoff_artifact)

        return result, artifact_written

    # ── AC1: same-team case still works ──────────────────────────────────────
    def test_same_team_approve_in_signing_qa_outbox(self):
        """APPROVE in the signing team's own QA outbox → signoff written."""
        release_id = '20260408-dungeoncrawler-release-x'
        import json
        with open(PRODUCT_TEAMS_JSON) as f:
            data = json.load(f)
        team = next(t for t in data['teams'] if t['id'] == 'dungeoncrawler')
        qa_agent = team['qa_agent']
        qa_outbox = os.path.join(REPO_ROOT, 'sessions', qa_agent, 'outbox')
        approve_file = _write_approve(qa_outbox, release_id)
        result, written = self._run_in_real_root('dungeoncrawler', release_id,
                                                 extra_qa_outbox_file=approve_file)
        self.assertEqual(result.returncode, 0, msg=result.stderr)
        self.assertTrue(written, "Signoff artifact should have been written")

    # ── AC2: cross-team signoff is rejected ────────────────────────────────────
    def test_cross_team_signoff_is_rejected(self):
        """A PM may not sign off another team's release."""
        release_id = '20260408-forseti-release-x'
        owning_qa_outbox = os.path.join(REPO_ROOT, 'sessions', 'qa-forseti', 'outbox')
        approve_file = _write_approve(owning_qa_outbox, release_id)
        result, written = self._run_in_real_root('dungeoncrawler', release_id,
                                                 extra_qa_outbox_file=approve_file)
        self.assertNotEqual(result.returncode, 0, msg="cross-team signoff should fail")
        self.assertIn('Cross-team PM co-signs are no longer allowed', result.stderr)
        self.assertFalse(written, "Cross-team signoff must not write an artifact")

    # ── AC3: no approve anywhere → BLOCKED ───────────────────────────────────
    def test_no_approve_blocks(self):
        """No APPROVE in either QA outbox → exit 1 with BLOCKED message."""
        release_id = '20260408-dungeoncrawler-release-noapp'
        result, written = self._run_in_real_root('dungeoncrawler', release_id)
        self.assertNotEqual(result.returncode, 0, "Should fail when no APPROVE found")
        self.assertIn('BLOCKED', result.stderr)
        self.assertFalse(written, "Signoff artifact must NOT be written without Gate 2")

    # ── AC4: empty-release bypass still works for owning PM ───────────────────
    def test_empty_release_bypass_same_team(self):
        """--empty-release still works for the owning team."""
        release_id = '20260408-dungeoncrawler-release-empty'
        result, written = self._run_in_real_root('dungeoncrawler', release_id,
                                                 extra_args=['--empty-release'])
        self.assertEqual(result.returncode, 0,
                         msg=f"--empty-release should bypass Gate 2\nstderr={result.stderr}")
        self.assertTrue(written, "Signoff artifact should be written with --empty-release")

    def test_noncanonical_approve_file_does_not_unlock_signoff(self):
        """Feature-level APPROVE prose without gate2 filename must not count as Gate 2."""
        release_id = '20260408-dungeoncrawler-release-p'
        qa_outbox = os.path.join(REPO_ROOT, 'sessions', 'qa-dungeoncrawler', 'outbox')
        approve_file = _write_approve(
            qa_outbox,
            release_id,
            filename='20260408-feature-verification-release-p.md',
        )
        result, written = self._run_in_real_root('dungeoncrawler', release_id,
                                                 extra_qa_outbox_file=approve_file)
        self.assertNotEqual(result.returncode, 0, "Noncanonical QA prose must not satisfy Gate 2")
        self.assertIn('canonical Gate 2 verdict artifacts', result.stderr)
        self.assertFalse(written)

    def test_latest_gate2_block_overrides_older_approve(self):
        """A newer gate2-block must prevent signoff even if an older approve exists."""
        release_id = '20260408-dungeoncrawler-release-q'
        qa_outbox = os.path.join(REPO_ROOT, 'sessions', 'qa-dungeoncrawler', 'outbox')
        approve_file = _write_approve(
            qa_outbox,
            release_id,
            filename='20260408-010000-gate2-approve-release-q.md',
        )
        block_file = os.path.join(qa_outbox, '20260408-020000-gate2-block-release-q.md')
        with open(block_file, 'w') as f:
            f.write(f'# Gate 2\n\n- Release: {release_id}\n- Status: BLOCK\n')
        result, written = self._run_in_real_root('dungeoncrawler', release_id,
                                                 extra_qa_outbox_file=approve_file)
        try:
            self.assertNotEqual(result.returncode, 0, "Latest BLOCK should prevent PM signoff")
            self.assertIn('Latest Gate 2 verdict is BLOCK', result.stderr)
            self.assertFalse(written)
        finally:
            if os.path.exists(block_file):
                os.remove(block_file)


if __name__ == '__main__':
    unittest.main()
