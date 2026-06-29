import os
import subprocess
import sys
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "project-registry-link-audit.py"


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "features").mkdir(parents=True)
    (root / "dashboards").mkdir(parents=True)
    (root / "dashboards" / "PROJECTS.md").write_text(
        "# Projects Registry\n\n| ID | Name |\n|---|---|\n| PROJ-001 | Example |\n| PROJ-009 | Open Source |\n",
        encoding="utf-8",
    )
    return root


def _write_feature(root: Path, name: str, content: str) -> None:
    feature_dir = root / "features" / name
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(content, encoding="utf-8")


def _run(root: Path) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["HQ_ROOT_DIR"] = str(root)
    return subprocess.run(
        [sys.executable, str(SCRIPT)],
        cwd=root,
        env=env,
        capture_output=True,
        text=True,
    )


def test_fails_for_active_legacy_feature_missing_project(tmp_path):
    root = _make_root(tmp_path)
    _write_feature(
        root,
        "forseti-open-source-initiative",
        """# Feature: forseti-open-source-initiative

status: in_progress
owner: pm-open-source
executive_sponsor: ceo-copilot-2
""",
    )

    result = _run(root)

    assert result.returncode == 1
    assert "active legacy-format feature is missing Project link" in result.stdout


def test_passes_when_legacy_feature_has_project_link(tmp_path):
    root = _make_root(tmp_path)
    _write_feature(
        root,
        "forseti-open-source-initiative",
        """# Feature: forseti-open-source-initiative

- Status: in_progress
- Owner: pm-open-source
- Executive sponsor: ceo-copilot-2
- Project: PROJ-009
""",
    )

    result = _run(root)

    assert result.returncode == 0
    assert result.stdout.startswith("OK:")


def test_warns_for_active_standard_feature_missing_project_backlink(tmp_path):
    root = _make_root(tmp_path)
    _write_feature(
        root,
        "example-feature",
        """# Feature Brief

- Work item id: example-feature
- Status: ready
- Website: forseti.life
""",
    )

    result = _run(root)

    assert result.returncode == 0
    assert "WARN  1 active feature(s) are missing a Project backlink" in result.stdout
