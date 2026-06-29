import json
import os
import shutil
import subprocess
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "release-signoff-status.sh"
HELPER = Path(__file__).resolve().parents[1] / "lib" / "release_cycle_helpers.py"
GATE1B_HELPER = Path(__file__).resolve().parents[1] / "lib" / "gate1b_artifacts.py"


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "scripts" / "lib").mkdir(parents=True)
    (root / "sessions" / "agent-code-review" / "outbox").mkdir(parents=True)
    (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs").mkdir(parents=True)
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs").mkdir(parents=True)
    shutil.copy2(HELPER, root / "scripts" / "lib" / "release_cycle_helpers.py")
    shutil.copy2(GATE1B_HELPER, root / "scripts" / "lib" / "gate1b_artifacts.py")
    return root


def _run(root: Path, release_id: str) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["HQ_ROOT_DIR"] = str(root)
    return subprocess.run(
        ["bash", str(SCRIPT), release_id, "--json"],
        cwd=root,
        capture_output=True,
        text=True,
        env=env,
    )


def test_independent_release_only_requires_owner_pm(tmp_path):
    root = _make_root(tmp_path)
    teams = {
        "teams": [
            {
                "id": "forseti",
                "site": "forseti.life",
                "pm_agent": "pm-forseti",
                "active": True,
                "release_preflight_enabled": True,
                "coordinated_release_default": True,
                "release_dependencies": [],
            },
            {
                "id": "dungeoncrawler",
                "site": "dungeoncrawler.forseti.life",
                "pm_agent": "pm-dungeoncrawler",
                "active": True,
                "release_preflight_enabled": True,
                "coordinated_release_default": True,
                "release_dependencies": [],
            },
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(json.dumps(teams), encoding="utf-8")
    release_id = "20260412-dungeoncrawler-release-z"
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / f"{release_id}.md").write_text(
        "# PM signoff\n",
        encoding="utf-8",
    )
    (root / "sessions" / "agent-code-review" / "outbox" / f"20260412-code-review-dungeoncrawler-{release_id}.md").write_text(
        f"- Status: done\n- Release: {release_id}\n- Verdict: APPROVE\n",
        encoding="utf-8",
    )

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    payload = json.loads(result.stdout)
    assert payload["required_count"] == 1
    assert payload["ready_for_official_push"] is True
    assert payload["required_pm_signoffs"][0]["pm_agent"] == "pm-dungeoncrawler"


def test_dependent_release_requires_release_cohort(tmp_path):
    root = _make_root(tmp_path)
    teams = {
        "teams": [
            {
                "id": "forseti",
                "site": "forseti.life",
                "pm_agent": "pm-forseti",
                "active": True,
                "release_preflight_enabled": True,
                "coordinated_release_default": True,
                "release_dependencies": ["dungeoncrawler"],
            },
            {
                "id": "dungeoncrawler",
                "site": "dungeoncrawler.forseti.life",
                "pm_agent": "pm-dungeoncrawler",
                "active": True,
                "release_preflight_enabled": True,
                "coordinated_release_default": True,
                "release_dependencies": [],
            },
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(json.dumps(teams), encoding="utf-8")
    release_id = "20260412-forseti-release-v"
    (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / f"{release_id}.md").write_text(
        "# PM signoff\n",
        encoding="utf-8",
    )
    (root / "sessions" / "agent-code-review" / "outbox" / f"20260412-code-review-forseti-{release_id}.md").write_text(
        f"- Status: done\n- Release: {release_id}\n- Verdict: APPROVE\n",
        encoding="utf-8",
    )

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    payload = json.loads(result.stdout)
    assert payload["required_count"] == 2
    assert payload["signed_off_count"] == 1
    assert payload["ready_for_official_push"] is False
    assert {row["pm_agent"] for row in payload["required_pm_signoffs"]} == {
        "pm-forseti",
        "pm-dungeoncrawler",
    }


def test_ready_for_official_push_is_false_without_gate1b_clear(tmp_path):
    root = _make_root(tmp_path)
    teams = {
        "teams": [
            {
                "id": "dungeoncrawler",
                "site": "dungeoncrawler.forseti.life",
                "pm_agent": "pm-dungeoncrawler",
                "active": True,
                "release_preflight_enabled": True,
                "coordinated_release_default": True,
                "release_dependencies": [],
            },
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(json.dumps(teams), encoding="utf-8")
    release_id = "20260412-dungeoncrawler-release-y"
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / f"{release_id}.md").write_text(
        "# PM signoff\n",
        encoding="utf-8",
    )

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    payload = json.loads(result.stdout)
    assert payload["gate1b_clear"] is False
    assert payload["ready_for_official_push"] is False
