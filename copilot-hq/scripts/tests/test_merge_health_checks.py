import os
import re
import shutil
import subprocess
from datetime import datetime, timedelta
from pathlib import Path


HQ_STATUS = Path(__file__).resolve().parents[1] / "hq-status.sh"
CEO_SYSTEM_HEALTH = Path(__file__).resolve().parents[1] / "ceo-system-health.sh"
AGENTS_LIB = Path(__file__).resolve().parents[1] / "lib" / "agents.sh"
MERGE_HEALTH_LIB = Path(__file__).resolve().parents[1] / "lib" / "merge-health.sh"
RAW_GIT_MERGE_RE = re.compile(r"^\s*(?:if\s+!\s+)?git\s+merge\b")


def _write_executable(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8")
    os.chmod(path, 0o755)


def _init_repo(root: Path) -> None:
    subprocess.run(["git", "init", "-b", "main"], cwd=root, check=True, capture_output=True, text=True)
    subprocess.run(["git", "config", "user.email", "ceo@example.com"], cwd=root, check=True, capture_output=True, text=True)
    subprocess.run(["git", "config", "user.name", "CEO Test"], cwd=root, check=True, capture_output=True, text=True)


def _commit_all(root: Path, message: str) -> None:
    subprocess.run(["git", "add", "."], cwd=root, check=True, capture_output=True, text=True)
    subprocess.run(["git", "commit", "-m", message], cwd=root, check=True, capture_output=True, text=True)


def _create_conflicted_repo(root: Path) -> None:
    _init_repo(root)
    (root / "conflict.txt").write_text("base\n", encoding="utf-8")
    _commit_all(root, "base")

    subprocess.run(["git", "checkout", "-b", "feature"], cwd=root, check=True, capture_output=True, text=True)
    (root / "conflict.txt").write_text("feature\n", encoding="utf-8")
    _commit_all(root, "feature")

    subprocess.run(["git", "checkout", "main"], cwd=root, check=True, capture_output=True, text=True)
    (root / "conflict.txt").write_text("main\n", encoding="utf-8")
    _commit_all(root, "main")

    result = subprocess.run(["git", "merge", "feature"], cwd=root, capture_output=True, text=True)
    assert result.returncode != 0


def _create_dirty_repo(root: Path) -> None:
    _init_repo(root)
    (root / "dirty.txt").write_text("base\n", encoding="utf-8")
    _commit_all(root, "base")
    (root / "dirty.txt").write_text("modified\n", encoding="utf-8")
    (root / "untracked.txt").write_text("extra\n", encoding="utf-8")


def _make_hq_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "scripts" / "lib").mkdir(parents=True)
    shutil.copy2(HQ_STATUS, root / "scripts" / "hq-status.sh")
    shutil.copy2(AGENTS_LIB, root / "scripts" / "lib" / "agents.sh")
    shutil.copy2(MERGE_HEALTH_LIB, root / "scripts" / "lib" / "merge-health.sh")
    os.chmod(root / "scripts" / "hq-status.sh", 0o755)

    _write_executable(root / "scripts" / "is-org-enabled.sh", "#!/usr/bin/env bash\nset -euo pipefail\necho true\n")
    _write_executable(root / "scripts" / "is-agent-paused.sh", "#!/usr/bin/env bash\nset -euo pipefail\necho false\n")
    _write_executable(
        root / "scripts" / "hq-blockers.sh",
        "#!/usr/bin/env bash\nset -euo pipefail\nif [ \"${1:-}\" = \"count\" ]; then echo 0; else echo 'none'; fi\n",
    )

    (root / "org-chart" / "agents").mkdir(parents=True)
    (root / "org-chart" / "agents" / "agents.yaml").write_text("agents: []\n", encoding="utf-8")
    return root


def _make_system_health_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "scripts" / "lib").mkdir(parents=True)
    shutil.copy2(CEO_SYSTEM_HEALTH, root / "scripts" / "ceo-system-health.sh")
    shutil.copy2(MERGE_HEALTH_LIB, root / "scripts" / "lib" / "merge-health.sh")
    os.chmod(root / "scripts" / "ceo-system-health.sh", 0o755)
    (root / "sessions" / "dev-infra" / "inbox").mkdir(parents=True)
    return root


def _apache_log_line(when: datetime, message: str) -> str:
    return f"[{when.strftime('%a %b %d %H:%M:%S.%f %Y')}] {message}\n"


def test_hq_status_reports_clean_merge_health(tmp_path):
    root = _make_hq_root(tmp_path)
    _init_repo(root)
    (root / "README.md").write_text("ok\n", encoding="utf-8")
    _commit_all(root, "init")

    result = subprocess.run(
        ["bash", str(root / "scripts" / "hq-status.sh")],
        cwd=root,
        capture_output=True,
        text=True,
        env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"},
    )

    assert result.returncode == 0, result.stderr
    assert "Merge health:" in result.stdout
    assert "no active merge conflicts, unfinished integration state, or dirty tracked changes" in result.stdout


def test_hq_status_fails_when_merge_conflicts_exist(tmp_path):
    root = _make_hq_root(tmp_path)
    _create_conflicted_repo(root)

    result = subprocess.run(
        ["bash", str(root / "scripts" / "hq-status.sh")],
        cwd=root,
        capture_output=True,
        text=True,
        env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"},
    )

    assert result.returncode == 1
    assert "Merge health:" in result.stdout
    assert "MERGE_HEAD present" in result.stdout
    assert "Unmerged: conflict.txt" in result.stdout


def test_hq_status_fails_when_tracked_changes_would_block_merge(tmp_path):
    root = _make_hq_root(tmp_path)
    _create_dirty_repo(root)

    result = subprocess.run(
        ["bash", str(root / "scripts" / "hq-status.sh")],
        cwd=root,
        capture_output=True,
        text=True,
        env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"},
    )

    assert result.returncode == 1
    assert "Merge health:" in result.stdout
    assert "tracked local change(s)" in result.stdout
    assert "Tracked change: dirty.txt" in result.stdout


def test_ceo_system_health_dispatches_merge_remediation(tmp_path):
    root = _make_system_health_root(tmp_path)
    _create_conflicted_repo(root)

    result = subprocess.run(
        ["bash", str(root / "scripts" / "ceo-system-health.sh"), "--dispatch"],
        cwd=root,
        capture_output=True,
        text=True,
        env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"},
    )

    assert "Merge Health" in result.stdout
    assert "❌ FAIL Merge health:" in result.stdout

    items = list((root / "sessions" / "dev-infra" / "inbox").glob("*-syshealth-merge-health-remediation"))
    assert len(items) == 1
    readme = (items[0] / "README.md").read_text(encoding="utf-8")
    assert "Summary: MERGE_HEAD present, 1 unmerged file(s)" in readme
    assert "HQ repo has merge/integration blockers" in readme
    assert "git merge --abort" in readme
    assert "checkpoint/stash/clean" in readme


def test_ceo_system_health_downgrades_quiet_php_fatals_to_warning(tmp_path):
    root = _make_system_health_root(tmp_path)
    _init_repo(root)
    (root / "README.md").write_text("ok\n", encoding="utf-8")
    _commit_all(root, "init")

    apache_dir = root / "apache"
    apache_dir.mkdir()
    old_line = _apache_log_line(
        datetime.now() - timedelta(minutes=45),
        '[php:notice] Uncaught PHP Exception Symfony\\Component\\Routing\\Exception\\RouteNotFoundException: "Route \\"job_hunter.profile\\" does not exist."',
    )
    (apache_dir / "forseti_error.log").write_text(old_line, encoding="utf-8")

    result = subprocess.run(
        ["bash", str(root / "scripts" / "ceo-system-health.sh")],
        cwd=root,
        capture_output=True,
        text=True,
        env={
            "PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
            "APACHE_LOG_DIR": str(apache_dir),
            "APACHE_FATAL_QUIET_MINUTES": "30",
        },
    )

    assert result.returncode == 0, result.stderr
    assert "⚠️  WARN [forseti] PHP Fatal/Parse/Exception errors: 1 in last 24h, but none in last 30m" in result.stdout
    assert "❌ FAIL [forseti] PHP Fatal/Parse/Exception errors" not in result.stdout


def test_ceo_system_health_fails_active_php_fatals_and_dispatches_followup(tmp_path):
    root = _make_system_health_root(tmp_path)
    _init_repo(root)
    (root / "README.md").write_text("ok\n", encoding="utf-8")
    _commit_all(root, "init")
    (root / "sessions" / "dev-forseti" / "inbox").mkdir(parents=True)

    apache_dir = root / "apache"
    apache_dir.mkdir()
    recent_line = _apache_log_line(
        datetime.now() - timedelta(minutes=5),
        '[php:notice] Uncaught PHP Exception RuntimeException: "Boom"',
    )
    (apache_dir / "forseti_error.log").write_text(recent_line, encoding="utf-8")

    result = subprocess.run(
        ["bash", str(root / "scripts" / "ceo-system-health.sh"), "--dispatch"],
        cwd=root,
        capture_output=True,
        text=True,
        env={
            "PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin",
            "APACHE_LOG_DIR": str(apache_dir),
            "APACHE_FATAL_QUIET_MINUTES": "30",
        },
    )

    assert result.returncode == 1
    assert "❌ FAIL [forseti] PHP Fatal/Parse/Exception errors: 1 in last 24h (1 in last 30m)" in result.stdout

    items = list((root / "sessions" / "dev-forseti" / "inbox").glob("*-syshealth-php-fatal-forseti"))
    assert len(items) == 1
    readme = (items[0] / "README.md").read_text(encoding="utf-8")
    assert "Active window: last 30 minutes." in readme
    assert "Verify site returns HTTP 200 after fix." in readme


def test_hq_shell_scripts_use_workspace_merge_safe_wrapper():
    scripts_root = Path(__file__).resolve().parents[1]
    allowed_path = scripts_root / "workspace-merge-safe.sh"
    violations = []

    for path in sorted(scripts_root.rglob("*.sh")):
        if path == allowed_path:
            continue

        for line_number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
            stripped = line.lstrip()
            if stripped.startswith("#"):
                continue
            if RAW_GIT_MERGE_RE.search(line):
                violations.append(f"{path.relative_to(scripts_root)}:{line_number}:{stripped}")

    assert not violations, "raw git merge found outside workspace-merge-safe.sh:\n" + "\n".join(violations)
