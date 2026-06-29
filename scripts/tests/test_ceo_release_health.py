import json
import os
import subprocess
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "ceo-release-health.sh"


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "features").mkdir(parents=True)
    (root / "sessions" / "qa-forseti" / "outbox").mkdir(parents=True)
    (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs").mkdir(parents=True)

    teams = {
        "teams": [
            {
                "id": "forseti",
                "site": "forseti.life",
                "pm_agent": "pm-forseti",
                "qa_agent": "qa-forseti",
                "active": True,
                "coordinated_release_default": True,
            }
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(
        json.dumps(teams),
        encoding="utf-8",
    )

    release_id = "20260418-forseti-release-m"
    (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
        release_id + "\n",
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "forseti.next_release_id").write_text(
        "20260418-forseti-release-n\n",
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "forseti.started_at").write_text(
        "2026-04-18T12:00:00+00:00\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / f"{release_id}.md").write_text(
        "# PM signoff\n",
        encoding="utf-8",
    )
    (root / "sessions" / "qa-forseti" / "outbox" / f"20260418-000000-empty-release-self-cert-{release_id}.md").write_text(
        f"# Gate 2 Self-Certification — Empty Release\n\n{release_id} — APPROVE — empty release self-certified by PM\n",
        encoding="utf-8",
    )
    return root


def _run(root: Path) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["HQ_ROOT_DIR"] = str(root)
    return subprocess.run(
        ["bash", str(SCRIPT)],
        cwd=root,
        env=env,
        capture_output=True,
        text=True,
    )


def test_empty_release_self_cert_counts_as_gate2_evidence(tmp_path):
    root = _make_root(tmp_path)

    result = _run(root)

    assert result.returncode == 0, result.stdout + result.stderr
    assert "Gate 2 evidence:" in result.stdout
    assert "empty-release-self-cert" in result.stdout
    assert "PM signoff (pm-forseti): found" in result.stdout
    assert "All checks PASSED — release cycle is healthy" in result.stdout


def test_in_progress_feature_with_needs_info_dev_outbox_fails_health(tmp_path):
    root = _make_root(tmp_path)
    release_id = "20260418-forseti-release-m"

    feature_dir = root / "features" / "forseti-langgraph-console-admin"
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        "\n".join(
            [
                "# Feature Brief",
                "",
                "- Work item id: forseti-langgraph-console-admin",
                "- Website: forseti.life",
                "- Status: in_progress",
                f"- Release: {release_id}",
                "- Dev owner: dev-forseti",
                "- QA owner: qa-forseti",
            ]
        )
        + "\n",
        encoding="utf-8",
    )

    dev_outbox = root / "sessions" / "dev-forseti" / "outbox"
    dev_outbox.mkdir(parents=True)
    (dev_outbox / "20260420-impl-forseti-langgraph-console-admin.md").write_text(
        "- Status: needs-info\n- Summary: Awaiting clarification.\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 1, result.stdout + result.stderr
    assert "dev outbox unresolved" in result.stdout
    assert "status=needs-info" in result.stdout


def test_in_progress_feature_prefers_latest_matching_dev_outbox(tmp_path):
    root = _make_root(tmp_path)
    release_id = "20260418-forseti-release-m"

    feature_dir = root / "features" / "forseti-langgraph-console-admin"
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        "\n".join(
            [
                "# Feature Brief",
                "",
                "- Work item id: forseti-langgraph-console-admin",
                "- Website: forseti.life",
                "- Status: in_progress",
                f"- Release: {release_id}",
                "- Dev owner: dev-forseti",
                "- QA owner: qa-forseti",
            ]
        )
        + "\n",
        encoding="utf-8",
    )

    dev_outbox = root / "sessions" / "dev-forseti" / "outbox"
    dev_outbox.mkdir(parents=True)
    (dev_outbox / "20260420-164124-impl-forseti-langgraph-console-admin.md").write_text(
        "- Status: needs-info\n- Summary: Awaiting clarification.\n",
        encoding="utf-8",
    )
    (dev_outbox / "20260420-172644-impl-forseti-langgraph-console-admin.md").write_text(
        "- Status: done\n- Summary: Implemented.\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 0, result.stdout + result.stderr
    assert "dev outbox: 20260420-172644-impl-forseti-langgraph-console-admin.md status=done" in result.stdout
    assert "needs-info" not in result.stdout


def test_in_progress_feature_prefers_newer_matching_dev_outbox_by_mtime_not_name(tmp_path):
    root = _make_root(tmp_path)
    release_id = "20260418-forseti-release-m"

    feature_dir = root / "features" / "forseti-langgraph-console-admin"
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        "\n".join(
            [
                "# Feature Brief",
                "",
                "- Work item id: forseti-langgraph-console-admin",
                "- Website: forseti.life",
                "- Status: in_progress",
                f"- Release: {release_id}",
                "- Dev owner: dev-forseti",
                "- QA owner: qa-forseti",
            ]
        )
        + "\n",
        encoding="utf-8",
    )

    dev_outbox = root / "sessions" / "dev-forseti" / "outbox"
    dev_outbox.mkdir(parents=True)
    older = dev_outbox / "20260505-finish-forseti-langgraph-console-admin.md"
    older.write_text(
        "- Status: blocked\n- Summary: Earlier blocked attempt.\n",
        encoding="utf-8",
    )
    newer = dev_outbox / "20260505-complete-forseti-langgraph-console-admin-per-board.md"
    newer.write_text(
        "- Status: done\n- Summary: Later completion.\n",
        encoding="utf-8",
    )
    os.utime(older, (1_746_448_000, 1_746_448_000))
    os.utime(newer, (1_746_451_200, 1_746_451_200))

    result = _run(root)

    assert result.returncode == 0, result.stdout + result.stderr
    assert "dev outbox: 20260505-complete-forseti-langgraph-console-admin-per-board.md status=done" in result.stdout
    assert "20260505-finish-forseti-langgraph-console-admin.md status=blocked" not in result.stdout


def test_advanced_release_done_feature_fails_reconciliation_health(tmp_path):
    root = _make_root(tmp_path)
    release_id = "20260418-forseti-release-m"

    (root / "tmp" / "auto-push-dispatched").mkdir(parents=True)
    (root / "tmp" / "auto-push-dispatched" / "forseti.advanced").write_text(
        release_id + "\n",
        encoding="utf-8",
    )

    feature_dir = root / "features" / "forseti-langgraph-console-admin"
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        "\n".join(
            [
                "# Feature Brief",
                "",
                "- Work item id: forseti-langgraph-console-admin",
                "- Website: forseti.life",
                "- Status: done",
                f"- Release: {release_id}",
                "- Dev owner: dev-forseti",
                "- QA owner: qa-forseti",
            ]
        )
        + "\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 1, result.stdout + result.stderr
    assert "still has unreconciled done features" in result.stdout
    assert "forseti-langgraph-console-admin" in result.stdout


def test_push_marker_detected_from_auto_push_dispatched(tmp_path):
    root = _make_root(tmp_path)
    release_id = "20260418-forseti-release-m"

    (root / "tmp" / "auto-push-dispatched").mkdir(parents=True, exist_ok=True)
    (root / "tmp" / "auto-push-dispatched" / f"{release_id}.pushed").write_text(
        "2026-04-18T12:30:00+00:00\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 0, result.stdout + result.stderr
    assert f"[forseti] push already dispatched ({release_id}.pushed)" in result.stdout


def test_auto_filed_gate2_artifact_does_not_count_as_gate2_evidence(tmp_path):
    root = _make_root(tmp_path)
    release_id = "20260418-forseti-release-m"

    feature_dir = root / "features" / "forseti-langgraph-console-admin"
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        "\n".join(
            [
                "# Feature Brief",
                "",
                "- Work item id: forseti-langgraph-console-admin",
                "- Website: forseti.life",
                "- Status: in_progress",
                f"- Release: {release_id}",
                "- Dev owner: dev-forseti",
                "- QA owner: qa-forseti",
            ]
        )
        + "\n",
        encoding="utf-8",
    )

    dev_outbox = root / "sessions" / "dev-forseti" / "outbox"
    dev_outbox.mkdir(parents=True)
    (dev_outbox / "20260420-172644-impl-forseti-langgraph-console-admin.md").write_text(
        "- Status: done\n- Summary: Implemented.\n",
        encoding="utf-8",
    )
    (root / "sessions" / "qa-forseti" / "outbox" / f"20260418-gate2-approve-{release_id}.md").write_text(
        "\n".join(
            [
                f"# Gate 2 — QA Verification Report: {release_id} — APPROVE",
                "",
                f"- Release: {release_id}",
                "- Status: done",
                "- Summary: Clean site audit for forseti is sufficient Gate 2 evidence. APPROVE filed automatically by site-audit-run.sh.",
            ]
        )
        + "\n",
        encoding="utf-8",
    )
    empty_self_cert = root / "sessions" / "qa-forseti" / "outbox" / f"20260418-000000-empty-release-self-cert-{release_id}.md"
    if empty_self_cert.exists():
        empty_self_cert.unlink()
    signoff = root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / f"{release_id}.md"
    if signoff.exists():
        signoff.unlink()

    result = _run(root)

    assert result.returncode == 1, result.stdout + result.stderr
    assert "Gate 2 evidence:" not in result.stdout
    assert "PM signoff pending Gate 2 APPROVE" in result.stdout


def test_gate2_block_artifact_is_reported_explicitly(tmp_path):
    root = _make_root(tmp_path)
    release_id = "20260418-forseti-release-m"

    signoff = root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / f"{release_id}.md"
    if signoff.exists():
        signoff.unlink()
    self_cert = root / "sessions" / "qa-forseti" / "outbox" / f"20260418-000000-empty-release-self-cert-{release_id}.md"
    if self_cert.exists():
        self_cert.unlink()
    (root / "sessions" / "qa-forseti" / "outbox" / f"20260418-gate2-block-{release_id}.md").write_text(
        f"- Status: blocked\n- Summary: Gate 2 BLOCK for {release_id}.\n\n{release_id}\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 1, result.stdout + result.stderr
    assert f"Gate 2 BLOCK: 20260418-gate2-block-{release_id}.md" in result.stdout
