import json
import os
import subprocess
import textwrap
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "release-signoff.sh"
GATE1B_HELPER = Path(__file__).resolve().parents[1] / "lib" / "gate1b_artifacts.py"


def _make_root(tmp_path: Path) -> tuple[Path, str]:
    root = tmp_path / "hq"
    release_id = "20260412-dungeoncrawler-release-p"

    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "features").mkdir(parents=True)
    (root / "scripts" / "lib").mkdir(parents=True)
    (root / "sessions" / "agent-code-review" / "outbox").mkdir(parents=True)
    (root / "sessions" / "qa-dungeoncrawler" / "outbox").mkdir(parents=True)
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-candidates" / release_id).mkdir(parents=True)
    (root / "scripts" / "lib" / "gate1b_artifacts.py").write_text(
        GATE1B_HELPER.read_text(encoding="utf-8"),
        encoding="utf-8",
    )

    teams = {
        "teams": [
            {
                "id": "dungeoncrawler",
                "site": "dungeoncrawler",
                "aliases": ["dungeoncrawler", "dungeoncrawler.forseti.life"],
                "pm_agent": "pm-dungeoncrawler",
                "qa_agent": "qa-dungeoncrawler",
                "active": True,
                "coordinated_release_default": False,
            }
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(
        json.dumps(teams),
        encoding="utf-8",
    )
    (root / "sessions" / "qa-dungeoncrawler" / "outbox" / f"20260412-gate2-approve-{release_id}.md").write_text(
        f"{release_id} — APPROVE\n",
        encoding="utf-8",
    )
    (root / "sessions" / "agent-code-review" / "outbox" / f"20260412-code-review-dungeoncrawler-{release_id}.md").write_text(
        "\n".join(
            [
                "- Status: done",
                f"- Release: {release_id}",
                "- Verdict: APPROVE",
            ]
        )
        + "\n",
        encoding="utf-8",
    )
    return root, release_id


def _run(root: Path, release_id: str) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["HQ_ROOT_DIR"] = str(root)
    return subprocess.run(
        ["bash", str(SCRIPT), "dungeoncrawler", release_id],
        cwd=root,
        env=env,
        capture_output=True,
        text=True,
    )


def test_signoff_passes_with_exact_release_metadata(tmp_path):
    root, release_id = _make_root(tmp_path)
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-candidates" / release_id / "01-change-list.md").write_text(
        "### dc-b2-bestiary2\n",
        encoding="utf-8",
    )
    feature_dir = root / "features" / "dc-b2-bestiary2"
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        textwrap.dedent(
            f"""\
            # Feature Brief

            - Work item id: dc-b2-bestiary2
            - Website: dungeoncrawler.forseti.life
            - Status: done
            - Release: {release_id}
            """
        ),
        encoding="utf-8",
    )

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    signoff = root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / f"{release_id}.md"
    assert signoff.is_file()
    assert "SIGNED_OFF" in result.stdout


def test_signoff_fails_when_change_list_feature_release_metadata_is_blank(tmp_path):
    root, release_id = _make_root(tmp_path)
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-candidates" / release_id / "01-change-list.md").write_text(
        "### dc-ancestry-system\n",
        encoding="utf-8",
    )
    feature_dir = root / "features" / "dc-ancestry-system"
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        textwrap.dedent(
            """\
            # Feature Brief

            - Work item id: dc-ancestry-system
            - Website: dungeoncrawler.forseti.life
            - Status: done
            - Release:
            """
        ),
        encoding="utf-8",
    )

    result = _run(root, release_id)

    assert result.returncode == 1, result.stdout + result.stderr
    assert "release metadata mismatch" in result.stderr
    assert "BLOCKED: PM signoff requires release-bound features" in result.stderr


def test_signoff_rejects_auto_filed_gate2_artifact(tmp_path):
    root, release_id = _make_root(tmp_path)
    auto_gate2 = root / "sessions" / "qa-dungeoncrawler" / "outbox" / f"20260412-gate2-approve-{release_id}.md"
    auto_gate2.write_text(
        "\n".join(
            [
                f"# Gate 2 — QA Verification Report: {release_id} — APPROVE",
                "",
                f"- Release: {release_id}",
                "- Status: done",
                "- Summary: Clean site audit for dungeoncrawler is sufficient Gate 2 evidence. APPROVE filed automatically by site-audit-run.sh.",
            ]
        )
        + "\n",
        encoding="utf-8",
    )

    result = _run(root, release_id)

    assert result.returncode == 1, result.stdout + result.stderr
    assert "Gate 2 APPROVE evidence not found" in result.stderr


def test_signoff_rejects_missing_gate1b_artifact(tmp_path):
    root, release_id = _make_root(tmp_path)
    for artifact in (root / "sessions" / "agent-code-review" / "outbox").glob("*.md"):
        artifact.unlink()

    result = _run(root, release_id)

    assert result.returncode == 1, result.stdout + result.stderr
    assert "Gate 1b evidence not found" in result.stderr
    assert "BLOCKED: PM signoff requires completed Gate 1b code review evidence" in result.stderr


def test_signoff_accepts_same_release_gate1b_risk_acceptance(tmp_path):
    root, release_id = _make_root(tmp_path)
    for artifact in (root / "sessions" / "agent-code-review" / "outbox").glob("*.md"):
        artifact.unlink()
    risk_dir = root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "risk-acceptances"
    risk_dir.mkdir(parents=True)
    (risk_dir / f"{release_id}-gate-1b-waiver.md").write_text(
        "\n".join(
            [
                "# Gate 1b Risk Acceptance",
                f"- Release: {release_id}",
                "- Decision: waive Gate 1b",
                "- Rationale: explicit risk acceptance for same release",
            ]
        )
        + "\n",
        encoding="utf-8",
    )

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    assert "Gate 1b cleared by risk acceptance" in result.stdout
