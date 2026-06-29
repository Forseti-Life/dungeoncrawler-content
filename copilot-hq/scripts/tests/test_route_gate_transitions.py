import json
import os
import shutil
import subprocess
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "route-gate-transitions.sh"


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "scripts").mkdir(parents=True)
    shutil.copy2(SCRIPT, root / "scripts" / "route-gate-transitions.sh")
    os.chmod(root / "scripts" / "route-gate-transitions.sh", 0o755)

    (root / "org-chart" / "products").mkdir(parents=True)
    teams = {
        "teams": [
            {
                "id": "infrastructure",
                "qa_agent": "qa-infra",
                "dev_agent": "dev-infra",
                "pm_agent": "pm-infra",
                "active": True,
            }
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(
        json.dumps(teams),
        encoding="utf-8",
    )
    return root


def _run(root: Path, item_name: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["bash", str(root / "scripts" / "route-gate-transitions.sh"), "qa-infra", item_name],
        cwd=root,
        capture_output=True,
        text=True,
        env={
            "PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
        },
    )


def test_routes_standard_qa_block_to_dev(tmp_path):
    root = _make_root(tmp_path)
    outbox = root / "sessions" / "qa-infra" / "outbox"
    outbox.mkdir(parents=True)
    (outbox / "20260414-standard-block.md").write_text(
        """- Status: done
- Summary: QA verification complete. BLOCK.

## Next actions
- Fix the failing implementation.
""",
        encoding="utf-8",
    )

    result = _run(root, "20260414-standard-block")

    assert result.returncode == 0, result.stderr
    inbox = root / "sessions" / "dev-infra" / "inbox" / "20260414-fix-from-qa-block-infrastructure"
    assert inbox.is_dir()
    assert (inbox / "command.md").exists()


def test_skips_dev_routing_when_qa_outbox_explicitly_delegates(tmp_path):
    root = _make_root(tmp_path)
    outbox = root / "sessions" / "qa-infra" / "outbox"
    outbox.mkdir(parents=True)
    (outbox / "20260414-open-source-block.md").write_text(
        """- Status: done
- Summary: QA verification complete. BLOCK.

## Next actions
- Dev-open-source should remediate the blockers listed in the audit artifact.
- No new Dev inbox items created (per delegation rule); dev-open-source consumes the audit artifact directly.
""",
        encoding="utf-8",
    )

    result = _run(root, "20260414-open-source-block")

    assert result.returncode == 0, result.stderr
    inbox = root / "sessions" / "dev-infra" / "inbox" / "20260414-fix-from-qa-block-infrastructure"
    assert not inbox.exists()
    assert "explicit delegation" in result.stderr
