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
