import os
import subprocess
import sys
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "project-progress-audit.py"


def _make_root(tmp_path: Path) -> tuple[Path, Path]:
    root = tmp_path / "hq"
    (root / "scripts").mkdir(parents=True)
    (root / "dashboards").mkdir(parents=True)
    copied_script = root / "scripts" / "project-progress-audit.py"
    copied_script.write_text(SCRIPT.read_text(encoding="utf-8"), encoding="utf-8")
    return root, copied_script


def _write_projects(path: Path) -> None:
    path.write_text(
        "\n".join(
            [
                "# Projects Registry",
                "",
                "## PROJ-999 — Example Project",
                "",
                "**Current state:** Active",
                "**Last scoped release:** 20260516-example-release-a",
                "**Progress SLA:** 7 days",
                "**Next step:** Keep moving.",
                "**Queue status:** queued for PM follow-up",
                "",
            ]
        )
        + "\n",
        encoding="utf-8",
    )


def _run(script: Path, cwd: Path, env: dict[str, str] | None = None) -> subprocess.CompletedProcess[str]:
    run_env = os.environ.copy()
    if env:
        run_env.update(env)
    return subprocess.run(
        [sys.executable, str(script)],
        cwd=cwd,
        env=run_env,
        capture_output=True,
        text=True,
    )


def test_project_progress_audit_defaults_to_script_relative_repo_root(tmp_path):
    root, copied_script = _make_root(tmp_path)
    _write_projects(root / "dashboards" / "PROJECTS.md")

    result = _run(copied_script, cwd=tmp_path)

    assert result.returncode == 0, result.stdout + result.stderr
    assert "OK: 1 active project(s) audited; 0 warning(s)." in result.stdout


def test_project_progress_audit_honors_hq_root_env_override(tmp_path, monkeypatch):
    root, copied_script = _make_root(tmp_path)
    override_root = tmp_path / "override-root"
    (override_root / "dashboards").mkdir(parents=True)
    _write_projects(override_root / "dashboards" / "PROJECTS.md")

    monkeypatch.setenv("HQ_ROOT_DIR", str(override_root))
    result = _run(copied_script, cwd=root)

    assert result.returncode == 0, result.stdout + result.stderr
    assert "OK: 1 active project(s) audited; 0 warning(s)." in result.stdout
