import json
import os
import subprocess
import sys
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "gate2-clean-audit-backstop.py"


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "sessions" / "qa-dungeoncrawler" / "artifacts" / "auto-site-audit" / "latest").mkdir(parents=True)
    (root / "sessions" / "qa-dungeoncrawler" / "outbox").mkdir(parents=True)
    (root / "sessions" / "ceo-copilot-2" / "inbox").mkdir(parents=True)
    (root / "sessions" / "ceo-copilot-2" / "outbox").mkdir(parents=True)

    teams = {
        "teams": [
            {
                "id": "dungeoncrawler",
                "site": "dungeoncrawler.forseti.life",
                "pm_agent": "pm-dungeoncrawler",
                "qa_agent": "qa-dungeoncrawler",
                "active": True,
            }
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(
        json.dumps(teams),
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
        "20260413-dungeoncrawler-release-i\n",
        encoding="utf-8",
    )
    return root


def _write_findings(root: Path, *, clean: bool, config_drift: bool = False) -> None:
    payload = {
        "counts": {
            "missing_assets_404s": 0 if clean else 1,
            "permission_violations": 0,
            "failures": 0,
        },
        "config_drift_warnings": [{"config_item": "user.role.foo"}] if config_drift else [],
    }
    (root / "sessions" / "qa-dungeoncrawler" / "artifacts" / "auto-site-audit" / "latest" / "findings-summary.json").write_text(
        json.dumps(payload),
        encoding="utf-8",
    )


def _run(root: Path, *args: str) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["HQ_ROOT_DIR"] = str(root)
    return subprocess.run(
        [sys.executable, str(SCRIPT), *args],
        cwd=root,
        env=env,
        capture_output=True,
        text=True,
    )


def test_writes_gate2_approve_for_clean_audit(tmp_path):
    root = _make_root(tmp_path)
    _write_findings(root, clean=True)

    result = _run(root, "--team", "dungeoncrawler", "--source", "pytest")

    assert result.returncode == 0, result.stderr
    assert "FIXED dungeoncrawler" in result.stdout

    outbox = root / "sessions" / "qa-dungeoncrawler" / "outbox"
    approve_files = list(outbox.glob("*gate2-approve*.md"))
    assert len(approve_files) == 1
    content = approve_files[0].read_text(encoding="utf-8")
    assert "20260413-dungeoncrawler-release-i" in content
    assert "APPROVE" in content
    assert "pytest" in content


def test_skips_when_audit_not_clean(tmp_path):
    root = _make_root(tmp_path)
    _write_findings(root, clean=False)

    result = _run(root, "--team", "dungeoncrawler")

    assert result.returncode == 0, result.stderr
    assert "latest QA audit is not clean" in result.stdout
    assert not list((root / "sessions" / "qa-dungeoncrawler" / "outbox").glob("*gate2-approve*.md"))


def test_skips_when_config_drift_exists(tmp_path):
    root = _make_root(tmp_path)
    _write_findings(root, clean=True, config_drift=True)

    result = _run(root, "--team", "dungeoncrawler")

    assert result.returncode == 0, result.stderr
    assert "latest QA audit is not clean" in result.stdout
    assert not list((root / "sessions" / "qa-dungeoncrawler" / "outbox").glob("*gate2-approve*.md"))


def test_queue_followup_when_backstop_intervenes(tmp_path):
    root = _make_root(tmp_path)
    _write_findings(root, clean=True)

    result = _run(root, "--team", "dungeoncrawler", "--queue-followup", "--source", "ceo-ops-once.sh")

    assert result.returncode == 0, result.stderr
    assert "queued CEO follow-up" in result.stdout

    inbox_items = [p for p in (root / "sessions" / "ceo-copilot-2" / "inbox").iterdir() if p.is_dir()]
    assert len(inbox_items) == 1
    command = (inbox_items[0] / "command.md").read_text(encoding="utf-8")
    assert "Root-cause review" in command
    assert "20260413-dungeoncrawler-release-i" in command


def test_existing_approve_is_idempotent(tmp_path):
    root = _make_root(tmp_path)
    _write_findings(root, clean=True)
    existing = root / "sessions" / "qa-dungeoncrawler" / "outbox" / "20260413-gate2-approve-existing.md"
    existing.write_text(
        "# Gate 2\n\n20260413-dungeoncrawler-release-i — APPROVE\n",
        encoding="utf-8",
    )

    result = _run(root, "--team", "dungeoncrawler", "--queue-followup")

    assert result.returncode == 0, result.stderr
    assert "Gate 2 APPROVE already exists" in result.stdout
    assert len(list((root / "sessions" / "qa-dungeoncrawler" / "outbox").glob("*gate2-approve*.md"))) == 1
    assert not list((root / "sessions" / "ceo-copilot-2" / "inbox").iterdir())
