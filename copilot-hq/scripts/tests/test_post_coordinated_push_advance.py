"""Tests for post-coordinated-push.sh Step 3: release_id advancement."""
import json
import shutil
import subprocess
import tempfile
import textwrap
from datetime import datetime, timezone
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "post-coordinated-push.sh"
BOUNDARY_SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "ceo-release-boundary-health.sh"
RELEASE_CYCLE_START = Path(__file__).resolve().parents[2] / "scripts" / "release-cycle-start.sh"
HELPER_MODULE = Path(__file__).resolve().parents[2] / "scripts" / "lib" / "release_cycle_helpers.py"
assert SCRIPT.exists(), f"Script not found: {SCRIPT}"
assert BOUNDARY_SCRIPT.exists(), f"Script not found: {BOUNDARY_SCRIPT}"
assert RELEASE_CYCLE_START.exists(), f"Script not found: {RELEASE_CYCLE_START}"
assert HELPER_MODULE.exists(), f"Script not found: {HELPER_MODULE}"

_TEAMS_JSON = {
    "teams": [
        {
            "id": "forseti",
            "pm_agent": "pm-forseti",
            "qa_agent": "qa-forseti",
            "dev_agent": "dev-forseti",
            "active": True,
            "release_preflight_enabled": True,
            "coordinated_release_default": True,
            "site": "forseti.life",
            "site_audit": {"drupal_web_root": "/nonexistent/web"},
        },
        {
            "id": "dungeoncrawler",
            "pm_agent": "pm-dungeoncrawler",
            "qa_agent": "qa-dungeoncrawler",
            "dev_agent": "dev-dungeoncrawler",
            "active": True,
            "release_preflight_enabled": True,
            "coordinated_release_default": True,
            "site": "dungeoncrawler.forseti.life",
            "site_audit": {"drupal_web_root": "/nonexistent/web"},
        },
    ]
}


def _make_root(tmp: Path, *, signoffs_done: bool = True) -> Path:
    """Build minimal HQ directory structure in tmp for post-coordinated-push.sh."""
    root = tmp / "hq"

    # product-teams.json
    teams_dir = root / "org-chart" / "products"
    teams_dir.mkdir(parents=True)
    (teams_dir / "product-teams.json").write_text(json.dumps(_TEAMS_JSON))

    # tmp/release-cycle-active/
    active = root / "tmp" / "release-cycle-active"
    active.mkdir(parents=True)

    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    for team_id in ("forseti", "dungeoncrawler"):
        current = f"{today}-{team_id}-release-b"
        nxt = f"{today}-{team_id}-release-c"
        (active / f"{team_id}.release_id").write_text(current + "\n")
        (active / f"{team_id}.next_release_id").write_text(nxt + "\n")
        (active / f"{team_id}.started_at").write_text(datetime.now(timezone.utc).isoformat() + "\n")

        if signoffs_done:
            # Pre-create signoff files so release-signoff.sh is skipped.
            signoff_dir = root / "sessions" / f"pm-{team_id}" / "artifacts" / "release-signoffs"
            signoff_dir.mkdir(parents=True)
            (signoff_dir / f"{current}.md").write_text("## Release Signoff\n")

    # scripts/ symlink so the script can find release-signoff.sh via path discovery.
    # We mock release-signoff.sh with a no-op stub that always exits 0.
    scripts_dir = root / "scripts"
    scripts_dir.mkdir(parents=True)
    lib_dir = scripts_dir / "lib"
    lib_dir.mkdir(parents=True)
    stub = scripts_dir / "release-signoff.sh"
    stub.write_text("#!/usr/bin/env bash\nexit 0\n")
    stub.chmod(0o755)
    shutil.copy2(BOUNDARY_SCRIPT, scripts_dir / "ceo-release-boundary-health.sh")
    shutil.copy2(RELEASE_CYCLE_START, scripts_dir / "release-cycle-start.sh")
    shutil.copy2(HELPER_MODULE, lib_dir / "release_cycle_helpers.py")
    (scripts_dir / "ceo-release-boundary-health.sh").chmod(0o755)
    (scripts_dir / "release-cycle-start.sh").chmod(0o755)

    return root


def _run(root: Path) -> subprocess.CompletedProcess:
    return subprocess.run(
        ["bash", str(SCRIPT)],
        cwd=str(root),
        capture_output=True,
        text=True,
        env={
            "PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
            "HQ_ROOT_DIR": str(root),
        },
    )


class TestReleaseIdAdvancement:
    def test_release_id_advanced_after_push(self, tmp_path):
        """After first run, release_id files are set to the former next_release_id."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        result = _run(root)
        assert result.returncode == 0, result.stderr

        active = root / "tmp" / "release-cycle-active"
        for team_id in ("forseti", "dungeoncrawler"):
            content = (active / f"{team_id}.release_id").read_text().strip()
            assert content == f"{today}-{team_id}-release-c", (
                f"{team_id}: expected release-c, got {content!r}"
            )

    def test_next_release_id_updated_after_push(self, tmp_path):
        """After first run, next_release_id is set to a new value distinct from the current."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        _run(root)

        active = root / "tmp" / "release-cycle-active"
        for team_id in ("forseti", "dungeoncrawler"):
            current = (active / f"{team_id}.release_id").read_text().strip()
            nxt = (active / f"{team_id}.next_release_id").read_text().strip()
            assert nxt != current, f"{team_id}: next_release_id == release_id ({current!r})"
            assert nxt.startswith(f"{today}-{team_id}-"), f"{team_id}: unexpected next prefix: {nxt!r}"

    def test_idempotent_second_run(self, tmp_path):
        """Second run detects marker already exists and does NOT overwrite release_id files."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")

        # First run — advances
        _run(root)

        active = root / "tmp" / "release-cycle-active"
        rid_after_first = {}
        for team_id in ("forseti", "dungeoncrawler"):
            rid_after_first[team_id] = (active / f"{team_id}.release_id").read_text().strip()

        # Simulate a new, different next_release_id being written externally
        for team_id in ("forseti", "dungeoncrawler"):
            (active / f"{team_id}.next_release_id").write_text(f"{today}-{team_id}-release-z\n")

        # Second run — should be a no-op for release_id files
        result = _run(root)
        assert result.returncode == 0

        for team_id in ("forseti", "dungeoncrawler"):
            rid_after_second = (active / f"{team_id}.release_id").read_text().strip()
            assert rid_after_second == rid_after_first[team_id], (
                f"{team_id}: idempotency violated — release_id changed on second run"
            )
        assert any(
            f"SKIP {t}: release_id already advanced" in result.stdout
            for t in ("forseti", "dungeoncrawler")
        ), f"Expected SKIP message for idempotent second run; got:\n{result.stdout}"

    def test_stale_legacy_team_sentinel_does_not_block_new_push_pair(self, tmp_path):
        """A previous team-level sentinel must not prevent a newer pushed pair from advancing."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        active = root / "tmp" / "release-cycle-active"
        pushed_dir = root / "tmp" / "auto-push-dispatched"
        pushed_dir.mkdir(parents=True)

        current_pair = "__".join(
            [
                f"{today}-dungeoncrawler-release-b",
                f"{today}-forseti-release-b",
            ]
        )
        (pushed_dir / f"{current_pair}.pushed").write_text("2026-04-17T01:04:19+00:00\n")
        for team_id in ("forseti", "dungeoncrawler"):
            current_rid = (active / f"{team_id}.release_id").read_text().strip()
            (pushed_dir / f"{team_id}.advanced").write_text(current_rid + "\n")

        result = _run(root)
        assert result.returncode == 0, result.stderr

        for team_id in ("forseti", "dungeoncrawler"):
            advanced_rid = (active / f"{team_id}.release_id").read_text().strip()
            assert advanced_rid == f"{today}-{team_id}-release-c"
            pair_sentinel = pushed_dir / f"{current_pair}.{team_id}.advanced"
            assert pair_sentinel.exists(), f"{team_id}: expected pair sentinel {pair_sentinel}"
            assert pair_sentinel.read_text().strip() == advanced_rid

    def test_missing_next_release_id_file_warns_and_skips(self, tmp_path):
        """If next_release_id file is absent, warns and skips that team but exits 0."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        active = root / "tmp" / "release-cycle-active"

        # Remove next_release_id for dungeoncrawler
        (active / "dungeoncrawler.next_release_id").unlink()

        result = _run(root)
        assert result.returncode == 1
        assert "WARN dungeoncrawler" in result.stdout
        assert "missing next release file" in result.stdout
        # forseti should still be advanced
        forseti_rid = (active / "forseti.release_id").read_text().strip()
        assert forseti_rid == f"{today}-forseti-release-c"
        # dungeoncrawler stays at release-b
        dc_rid = (active / "dungeoncrawler.release_id").read_text().strip()
        assert dc_rid == f"{today}-dungeoncrawler-release-b"

    def test_started_at_updated(self, tmp_path):
        """started_at file is updated with a recent timestamp after advancement."""
        root = _make_root(tmp_path)
        active = root / "tmp" / "release-cycle-active"

        old_ts = "2020-01-01T00:00:00+00:00"
        for team_id in ("forseti", "dungeoncrawler"):
            (active / f"{team_id}.started_at").write_text(old_ts + "\n")

        _run(root)

        for team_id in ("forseti", "dungeoncrawler"):
            new_ts = (active / f"{team_id}.started_at").read_text().strip()
            assert new_ts != old_ts, f"{team_id}: started_at not updated"
            assert new_ts > "2024", f"{team_id}: started_at appears stale: {new_ts!r}"

    def test_pm_grooming_seeded_for_new_next_release(self, tmp_path):
        """Advancing a release should immediately seed PM grooming for the new next release."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")

        result = _run(root)
        assert result.returncode == 0, result.stderr

        for team_id in ("forseti", "dungeoncrawler"):
            pm_inbox = root / "sessions" / f"pm-{team_id}" / "inbox"
            expected = pm_inbox / f"{today}-groom-{today}-{team_id}-release-d"
            assert expected.is_dir(), f"{team_id}: expected grooming item {expected}"

    def test_pm_grooming_seeded_even_when_preflight_is_suppressed(self, tmp_path):
        """QA preflight suppression must not skip PM grooming for the next release."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")

        qa_outbox = root / "sessions" / "qa-forseti" / "outbox"
        qa_outbox.mkdir(parents=True)
        (
            qa_outbox / f"{today}-release-preflight-test-suite-{today}-forseti-release-b.md"
        ).write_text("- Status: done\n", encoding="utf-8")

        result = _run(root)
        assert result.returncode == 0, result.stderr

        expected = (
            root
            / "sessions"
            / "pm-forseti"
            / "inbox"
            / f"{today}-groom-{today}-forseti-release-d"
        )
        assert expected.is_dir(), f"forseti: expected grooming item {expected}"

    def test_boundary_health_queues_scope_activate_immediately(self, tmp_path):
        """A freshly advanced empty release should get a PM scope-activate item right away."""
        root = _make_root(tmp_path)
        features = root / "features"
        features.mkdir()
        today = datetime.now(timezone.utc).strftime("%Y%m%d")
        for team_id, website in (
            ("forseti", "forseti.life"),
            ("dungeoncrawler", "dungeoncrawler.forseti.life"),
        ):
            feat_dir = features / f"{team_id}-feat-ready"
            feat_dir.mkdir()
            (feat_dir / "feature.md").write_text(
                textwrap.dedent(
                    f"""\
                    # Feature Brief

                    - Work item id: {team_id}-feat-ready
                    - Website: {website}
                    - Status: ready
                    - Release:
                    - Dev owner: dev-{team_id}
                    - QA owner: qa-{team_id}
                    """
                ),
                encoding="utf-8",
            )

        result = _run(root)
        assert result.returncode == 0, result.stderr

        for team_id in ("forseti", "dungeoncrawler"):
            pm_inbox = root / "sessions" / f"pm-{team_id}" / "inbox"
            items = list(pm_inbox.glob(f"*-scope-activate-{today}-{team_id}-release-c"))
            assert len(items) == 1, f"{team_id}: expected one scope-activate item, got {items}"

    def test_archives_stale_pm_release_bound_items_on_advance(self, tmp_path):
        """Release advancement should archive obsolete PM release-bound items for old/current transitions."""
        root = _make_root(tmp_path)
        today = datetime.now(timezone.utc).strftime("%Y%m%d")

        stale_items = [
            root / "sessions" / "pm-forseti" / "inbox" / f"{today}-release-close-now-{today}-forseti-release-b",
            root / "sessions" / "pm-forseti" / "inbox" / f"{today}-push-ready-{today}-dungeoncrawler-release-b",
            root / "sessions" / "pm-forseti" / "inbox" / f"{today}-groom-{today}-forseti-release-c",
            root / "sessions" / "pm-dungeoncrawler" / "inbox" / f"{today}-signoff-reminder-{today}-forseti-release-b",
            root / "sessions" / "pm-dungeoncrawler" / "inbox" / f"{today}-groom-{today}-dungeoncrawler-release-c",
        ]
        keep_items = [
            root / "sessions" / "pm-forseti" / "inbox" / f"{today}-groom-{today}-forseti-release-d",
            root / "sessions" / "pm-dungeoncrawler" / "inbox" / f"{today}-scope-activate-{today}-dungeoncrawler-release-c",
        ]
        for path in stale_items + keep_items:
            path.mkdir(parents=True, exist_ok=True)
            (path / "README.md").write_text("placeholder\n", encoding="utf-8")
            (path / "roi.txt").write_text("5\n", encoding="utf-8")

        result = _run(root)
        assert result.returncode == 0, result.stderr

        for path in stale_items:
            archived = path.parent / "_archived" / path.name
            assert archived.is_dir(), f"expected archived stale item {archived}"
            assert not path.exists(), f"stale item should have been archived: {path}"
            outbox = path.parents[1] / "outbox" / f"{path.name}.md"
            assert outbox.is_file(), f"expected superseded outbox {outbox}"
            text = outbox.read_text(encoding='utf-8')
            assert "- Status: done" in text
            assert "Superseded by coordinated release advancement" in text

        for path in keep_items:
            assert path.is_dir(), f"non-stale item should remain in inbox: {path}"
