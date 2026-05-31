"""Tests for ba-source-coverage-report.py."""

from __future__ import annotations

import json
import subprocess
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "ba-source-coverage-report.py"
assert SCRIPT.exists(), f"Script not found: {SCRIPT}"


def _make_root(tmp_path: Path, *, pending_markers: int, ref_count: int) -> Path:
    root = tmp_path / "repo"
    refs_dir = root / "docs/dungeoncrawler/PF2requirements/references"
    audit_dir = root / "docs/dungeoncrawler/PF2requirements/audit"
    refs_dir.mkdir(parents=True)
    audit_dir.mkdir(parents=True)

    for idx in range(ref_count):
        (refs_dir / f"core-{idx:02d}.md").write_text("# ref\n", encoding="utf-8")
    audit_body = "# audit\n" + ("\n".join("- [ ] item" for _ in range(pending_markers))) + "\n"
    (audit_dir / "core-audit.md").write_text(audit_body, encoding="utf-8")

    ledger = {
        "source_documents": [
            {
                "source_id": "core",
                "label": "Core",
                "audit_path": "docs/dungeoncrawler/PF2requirements/audit/core-audit.md",
                "reference_prefix": "docs/dungeoncrawler/PF2requirements/references/core-",
                "requirements_coverage": {
                    "total_objects": 2,
                    "complete_objects": 2,
                    "skipped_objects": 0,
                    "pending_objects": 0,
                },
                "traceability": {"requirements_status": "complete"},
            }
        ]
    }
    ledger_path = root / "docs/dungeoncrawler/PF2requirements/source-ledger.json"
    ledger_path.parent.mkdir(parents=True, exist_ok=True)
    ledger_path.write_text(json.dumps(ledger, indent=2) + "\n", encoding="utf-8")
    return root


def _run(root: Path) -> subprocess.CompletedProcess:
    return subprocess.run(
        ["python3", str(SCRIPT), str(root)],
        capture_output=True,
        text=True,
        env={"PATH": "/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"},
    )


class TestBaSourceCoverageReport:
    def test_reports_validated_when_audit_and_refs_are_complete(self, tmp_path):
        root = _make_root(tmp_path, pending_markers=0, ref_count=2)
        result = _run(root)
        assert result.returncode == 0, result.stdout + result.stderr
        assert "VALIDATED" in result.stdout

    def test_reports_needs_review_when_audit_has_pending_markers(self, tmp_path):
        root = _make_root(tmp_path, pending_markers=3, ref_count=2)
        result = _run(root)
        assert result.returncode == 1
        assert "NEEDS_REVIEW" in result.stdout
