"""Tests for ba-source-ledger-audit.py."""

from __future__ import annotations

import json
import subprocess
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "ba-source-ledger-audit.py"
assert SCRIPT.exists(), f"Script not found: {SCRIPT}"


def _make_root(tmp_path: Path, *, mutate=None, ledger_rel: str = "docs/dungeoncrawler/PF2requirements/source-ledger.json") -> Path:
    root = tmp_path / "repo"
    (root / "docs/dungeoncrawler/PF2requirements/audit").mkdir(parents=True)
    (root / "docs/dungeoncrawler/PF2requirements/EXTRACTION_TRACKER.md").write_text(
        "# tracker\n", encoding="utf-8"
    )
    (root / "docs/dungeoncrawler/PF2requirements/audit/core-audit.md").write_text(
        "# audit\n", encoding="utf-8"
    )
    (root / "docs/dungeoncrawler/reference documentation").mkdir(parents=True)
    (root / "docs/dungeoncrawler/reference documentation/PF2E Core Rulebook - Fourth Printing.txt").write_text(
        "source\n", encoding="utf-8"
    )

    ledger = {
        "schema_version": 1,
        "source_documents": [
            {
                "source_id": "core-rulebook-4p",
                "source_path": "docs/dungeoncrawler/reference documentation/PF2E Core Rulebook - Fourth Printing.txt",
                "tracker_path": "docs/dungeoncrawler/PF2requirements/EXTRACTION_TRACKER.md",
                "audit_path": "docs/dungeoncrawler/PF2requirements/audit/core-audit.md",
                "requirements_coverage": {
                    "total_objects": 11,
                    "complete_objects": 10,
                    "skipped_objects": 1,
                    "pending_objects": 0,
                },
                "traceability": {
                    "requirements_status": "complete",
                    "issue_mapping_status": "partial",
                    "feature_mapping_status": "partial",
                    "release_handoff_status": "pending",
                },
            }
        ],
    }
    if mutate:
        mutate(ledger)
    (root / ledger_rel).parent.mkdir(parents=True, exist_ok=True)
    (root / ledger_rel).write_text(
        json.dumps(ledger, indent=2) + "\n", encoding="utf-8"
    )
    return root


def _run(root: Path, ledger_rel: str | None = None) -> subprocess.CompletedProcess:
    cmd = ["python3", str(SCRIPT), str(root)]
    if ledger_rel is not None:
        cmd.append(ledger_rel)
    return subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"},
    )


class TestBaSourceLedgerAudit:
    def test_accepts_valid_ledger(self, tmp_path):
        root = _make_root(tmp_path)
        result = _run(root)
        assert result.returncode == 0, result.stdout + result.stderr
        assert "audit OK" in result.stdout

    def test_rejects_bad_coverage_math(self, tmp_path):
        def mutate(ledger):
            ledger["source_documents"][0]["requirements_coverage"]["pending_objects"] = 1

        root = _make_root(tmp_path, mutate=mutate)
        result = _run(root)
        assert result.returncode == 1
        assert "must equal total_objects" in result.stdout

    def test_rejects_missing_referenced_file(self, tmp_path):
        def mutate(ledger):
            ledger["source_documents"][0]["audit_path"] = "docs/dungeoncrawler/PF2requirements/audit/missing.md"

        root = _make_root(tmp_path, mutate=mutate)
        result = _run(root)
        assert result.returncode == 1
        assert "referenced file does not exist" in result.stdout

    def test_accepts_custom_ledger_path(self, tmp_path):
        ledger_rel = "docs/product/traceability/source-ledger.json"
        root = _make_root(tmp_path, ledger_rel=ledger_rel)
        result = _run(root, ledger_rel)
        assert result.returncode == 0, result.stdout + result.stderr
        assert "audit OK" in result.stdout
