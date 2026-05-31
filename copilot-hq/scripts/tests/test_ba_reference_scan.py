"""Regression tests for ba-reference-scan.sh queue control and cap tracking."""

import json
import subprocess
from datetime import datetime, timezone
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "ba-reference-scan.sh"
assert SCRIPT.exists(), f"Script not found: {SCRIPT}"


def _make_root(tmp_path: Path, *, progress: dict | None = None) -> tuple[Path, Path]:
    root = tmp_path / "hq"
    scripts_dir = root / "scripts"
    scripts_dir.mkdir(parents=True)
    script_copy = scripts_dir / "ba-reference-scan.sh"
    script_copy.write_text(SCRIPT.read_text(encoding="utf-8"), encoding="utf-8")
    script_copy.chmod(0o755)

    book_path = tmp_path / "PF2E Core Rulebook - Fourth Printing.txt"
    book_path.write_text("\n".join(f"line {i}" for i in range(1, 500)), encoding="utf-8")

    teams_dir = root / "org-chart" / "products"
    teams_dir.mkdir(parents=True)
    teams_dir.joinpath("product-teams.json").write_text(
        json.dumps(
            {
                "teams": [
                    {
                        "id": "dungeoncrawler",
                        "aliases": ["dungeoncrawler"],
                        "ba_agent": "ba-dungeoncrawler",
                        "reference_docs": [
                            {
                                "label": "PF2E Core Rulebook (Fourth Printing)",
                                "path": str(book_path),
                                "outline": "",
                                "type": "rulebook",
                            }
                        ],
                    }
                ]
            }
        ),
        encoding="utf-8",
    )

    progress_file = root / "tmp" / "ba-scan-progress" / "dungeoncrawler.json"
    progress_file.parent.mkdir(parents=True)
    progress_file.write_text(
        json.dumps(
            progress
            or {
                "site": "dungeoncrawler",
                "books": [
                    {
                        "label": "PF2E Core Rulebook (Fourth Printing)",
                        "path": str(book_path),
                        "outline": "",
                        "type": "rulebook",
                        "last_line": 0,
                        "chapters_completed": [],
                        "status": "pending",
                    }
                ],
                "total_features_generated": 0,
                "last_scan_release": None,
            },
            indent=2,
        )
        + "\n",
        encoding="utf-8",
    )
    return root, progress_file


def _run(root: Path, release_id: str) -> subprocess.CompletedProcess:
    return subprocess.run(
        ["bash", str(root / "scripts" / "ba-reference-scan.sh"), "dungeoncrawler", release_id],
        cwd=root,
        capture_output=True,
        text=True,
        env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"},
    )


def _queued_command(root: Path) -> str:
    command_files = sorted((root / "sessions" / "ba-dungeoncrawler" / "inbox").glob("*/command.md"))
    assert len(command_files) == 1, f"expected one command file, got {command_files}"
    return command_files[0].read_text(encoding="utf-8")


class TestBaReferenceScan:
    def test_uses_progress_json_count_for_current_release(self, tmp_path):
        release_id = "20260412-dungeoncrawler-release-n"
        root, _ = _make_root(
            tmp_path,
            progress={
                "site": "dungeoncrawler",
                "books": [
                    {
                        "label": "PF2E Core Rulebook (Fourth Printing)",
                        "path": str(tmp_path / "PF2E Core Rulebook - Fourth Printing.txt"),
                        "outline": "",
                        "type": "rulebook",
                        "last_line": 300,
                        "chapters_completed": [],
                        "status": "in_progress",
                    }
                ],
                "total_features_generated": 27,
                "last_scan_release": release_id,
            },
        )

        result = _run(root, release_id)
        assert result.returncode == 0, result.stderr
        assert "**Features generated this cycle so far:** 27 / 30 cap" in _queued_command(root)

    def test_resets_cycle_count_for_new_release(self, tmp_path):
        root, _ = _make_root(
            tmp_path,
            progress={
                "site": "dungeoncrawler",
                "books": [
                    {
                        "label": "PF2E Core Rulebook (Fourth Printing)",
                        "path": str(tmp_path / "PF2E Core Rulebook - Fourth Printing.txt"),
                        "outline": "",
                        "type": "rulebook",
                        "last_line": 7983,
                        "chapters_completed": [],
                        "status": "in_progress",
                    }
                ],
                "total_features_generated": 77,
                "last_scan_release": "20260412-dungeoncrawler-release-b",
            },
        )

        result = _run(root, "20260412-dungeoncrawler-release-n")
        assert result.returncode == 0, result.stderr
        assert "**Features generated this cycle so far:** 0 / 30 cap" in _queued_command(root)

    def test_skips_when_active_refscan_item_exists(self, tmp_path):
        release_id = "20260412-dungeoncrawler-release-n"
        root, _ = _make_root(tmp_path)
        existing = (
            root
            / "sessions"
            / "ba-dungeoncrawler"
            / "inbox"
            / "20260414-ba-refscan-dungeoncrawler-pf2e-bestiary-1-lvl-1-5"
        )
        existing.mkdir(parents=True)
        (existing / "command.md").write_text("# existing\n", encoding="utf-8")
        (existing / "roi.txt").write_text("5\n", encoding="utf-8")

        result = _run(root, release_id)
        assert result.returncode == 0, result.stderr
        assert "Active BA refscan item already queued" in result.stdout
        queued = list((root / "sessions" / "ba-dungeoncrawler" / "inbox").glob("*/command.md"))
        assert queued == [existing / "command.md"]

    def test_skips_when_feature_cap_reached_for_release(self, tmp_path):
        release_id = "20260412-dungeoncrawler-release-n"
        root, _ = _make_root(
            tmp_path,
            progress={
                "site": "dungeoncrawler",
                "books": [
                    {
                        "label": "PF2E Core Rulebook (Fourth Printing)",
                        "path": str(tmp_path / "PF2E Core Rulebook - Fourth Printing.txt"),
                        "outline": "",
                        "type": "rulebook",
                        "last_line": 600,
                        "chapters_completed": [],
                        "status": "in_progress",
                    }
                ],
                "total_features_generated": 30,
                "last_scan_release": release_id,
            },
        )

        result = _run(root, release_id)
        assert result.returncode == 0, result.stderr
        assert "Feature cap already reached" in result.stdout
        inbox_root = root / "sessions" / "ba-dungeoncrawler" / "inbox"
        assert not inbox_root.exists() or not any(inbox_root.iterdir())
