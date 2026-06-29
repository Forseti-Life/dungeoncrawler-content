import os
import shutil
import subprocess
from pathlib import Path


HQ_BLOCKERS = Path(__file__).resolve().parents[1] / "hq-blockers.sh"
AGENTS_LIB = Path(__file__).resolve().parents[1] / "lib" / "agents.sh"


def _make_root(tmp_path: Path, outbox_text: str) -> Path:
    root = tmp_path / "hq"
    (root / "scripts" / "lib").mkdir(parents=True)
    shutil.copy2(HQ_BLOCKERS, root / "scripts" / "hq-blockers.sh")
    shutil.copy2(AGENTS_LIB, root / "scripts" / "lib" / "agents.sh")
    os.chmod(root / "scripts" / "hq-blockers.sh", 0o755)

    (root / "scripts" / "is-agent-paused.sh").write_text(
        "#!/usr/bin/env bash\nset -euo pipefail\necho false\n",
        encoding="utf-8",
    )
    os.chmod(root / "scripts" / "is-agent-paused.sh", 0o755)

    (root / "org-chart" / "agents").mkdir(parents=True)
    (root / "org-chart" / "agents" / "agents.yaml").write_text(
        "agents:\n  - id: qa-infra\n    paused: false\n",
        encoding="utf-8",
    )

    outbox = root / "sessions" / "qa-infra" / "outbox"
    outbox.mkdir(parents=True)
    (outbox / "20260504-test.md").write_text(outbox_text, encoding="utf-8")
    return root


def _run(root: Path) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["PATH"] = "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
    return subprocess.run(
        ["bash", str(root / "scripts" / "hq-blockers.sh")],
        cwd=root,
        capture_output=True,
        text=True,
        env=env,
    )


def test_hq_blockers_accepts_needs_from_supervisor(tmp_path):
    root = _make_root(
        tmp_path,
        """- Status: needs-info
- Summary: Waiting on supervisor clarification.

## Blockers
- Missing release ID.

## Needs from Supervisor
- Confirm the active release ID for this task.

## Decision needed
- Should this stay in the current release?

## Recommendation
- Confirm the current release ID, then re-run this item.
""",
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr
    assert "[MALFORMED" not in result.stdout
    assert "Needs from up-chain:" in result.stdout
    assert "Confirm the active release ID for this task." in result.stdout


def test_hq_blockers_still_flags_empty_needs_section(tmp_path):
    root = _make_root(
        tmp_path,
        """- Status: needs-info
- Summary: Waiting on supervisor clarification.

## Blockers
- Missing release ID.

## Needs from Supervisor
- N/A

## Decision needed
- Should this stay in the current release?

## Recommendation
- Close this item if it is stale.
""",
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr
    assert "[MALFORMED: needs-info with empty/N/A Needs section" in result.stdout
