import json
import os
import subprocess
import sys
from pathlib import Path


SCRIPTS_DIR = Path(__file__).resolve().parents[1]
VALIDATE_SCRIPT = SCRIPTS_DIR / "qa-suite-validate.py"
BUILD_SCRIPT = SCRIPTS_DIR / "qa-suite-build.py"
RUN_SCRIPT = SCRIPTS_DIR / "qa-regression-run.py"
DC_GATE_SCRIPT = SCRIPTS_DIR / "dungeoncrawler-functional-gate.sh"


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "features").mkdir(parents=True)
    (root / "qa-suites" / "products" / "forseti" / "features").mkdir(parents=True)
    return root


def _write_feature(root: Path, feature_id: str, status: str = "ready") -> None:
    feature_dir = root / "features" / feature_id
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        "\n".join(
            [
                "# Feature Brief",
                f"- Work item id: {feature_id}",
                "- Website: forseti.life",
                f"- Status: {status}",
                "- QA owner: qa-forseti",
            ]
        )
        + "\n",
        encoding="utf-8",
    )
    (feature_dir / "03-test-plan.md").write_text(
        "\n".join(
            [
                f"# Test Plan: {feature_id}",
                "",
                "## Suite assignments",
                "- **Suite:** `role-url-audit`",
                f"- **Suite:** `{feature_id}-unit`",
            ]
        )
        + "\n",
        encoding="utf-8",
    )


def _write_manifest(root: Path) -> None:
    manifest = {
        "product_id": "forseti",
        "product_label": "Forseti",
        "tools": ["python"],
        "suites": [
            {
                "id": "role-url-audit",
                "label": "Role audit",
                "type": "audit",
                "command": "python3 -c \"print('role-url-audit')\"",
                "artifacts": ["stdout"],
                "required_for_release": True,
            }
        ],
    }
    path = root / "qa-suites" / "products" / "forseti" / "suite.json"
    path.write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")


def _write_overlay(root: Path, feature_id: str) -> None:
    overlay = {
        "feature_id": feature_id,
        "product_id": "forseti",
        "owner_seat": "qa-forseti",
        "status": "ready",
        "test_plan": f"features/{feature_id}/03-test-plan.md",
        "suites": [
            {
                "id": f"{feature_id}-unit",
                "feature_id": feature_id,
                "label": "Feature unit tests",
                "type": "unit",
                "command": "python3 -c \"print('unit-pass')\"",
                "artifacts": ["stdout"],
                "required_for_release": True,
                "owner_seat": "qa-forseti",
                "source_path": "sites/forseti/web/modules/custom/job_hunter/tests/src/Unit/FakeTest.php",
                "env_requirements": ["python"],
                "release_checkpoint": "final-pre-ship",
            }
        ],
    }
    path = root / "qa-suites" / "products" / "forseti" / "features" / f"{feature_id}.json"
    path.write_text(json.dumps(overlay, indent=2) + "\n", encoding="utf-8")


def _run(script: Path, root: Path, *args: str) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["HQ_ROOT_DIR"] = str(root)
    env["PYTHONPATH"] = f"{SCRIPTS_DIR}:{env.get('PYTHONPATH', '')}"
    return subprocess.run(
        [sys.executable, str(script), *args],
        cwd=root,
        env=env,
        capture_output=True,
        text=True,
    )


def test_validate_accepts_ready_feature_with_overlay(tmp_path):
    root = _make_root(tmp_path)
    feature_id = "forseti-ready-feature"
    _write_manifest(root)
    _write_feature(root, feature_id)
    _write_overlay(root, feature_id)

    result = _run(
        VALIDATE_SCRIPT,
        root,
        "--product",
        "forseti",
        "--feature-id",
        feature_id,
    )

    assert result.returncode == 0, result.stderr
    assert "validated 1 suite manifest(s) and 1 feature overlay(s)" in result.stdout


def test_validate_rejects_ready_feature_without_overlay_or_live_suite(tmp_path):
    root = _make_root(tmp_path)
    feature_id = "forseti-missing-overlay"
    _write_manifest(root)
    _write_feature(root, feature_id)

    result = _run(
        VALIDATE_SCRIPT,
        root,
        "--product",
        "forseti",
        "--feature-id",
        feature_id,
    )

    assert result.returncode != 0
    assert "has a test plan but no live suite entry or feature overlay manifest" in result.stderr


def test_build_compiles_requested_feature_overlay(tmp_path):
    root = _make_root(tmp_path)
    feature_id = "forseti-compiled-feature"
    output_path = root / "compiled" / "suite.json"
    _write_manifest(root)
    _write_feature(root, feature_id)
    _write_overlay(root, feature_id)

    result = _run(
        BUILD_SCRIPT,
        root,
        "--product",
        "forseti",
        "--include-feature",
        feature_id,
        "--write",
        str(output_path),
    )

    assert result.returncode == 0, result.stderr
    compiled = json.loads(output_path.read_text(encoding="utf-8"))
    suite_ids = {suite["id"] for suite in compiled["suites"]}
    assert "role-url-audit" in suite_ids
    assert f"{feature_id}-unit" in suite_ids


def test_regression_runner_writes_evidence(tmp_path):
    root = _make_root(tmp_path)
    evidence_path = root / "artifacts" / "evidence.md"
    _write_manifest(root)

    result = _run(
        RUN_SCRIPT,
        root,
        "--product",
        "forseti",
        "--evidence-output",
        str(evidence_path),
    )

    assert result.returncode == 0, result.stderr
    evidence = evidence_path.read_text(encoding="utf-8")
    assert "role-url-audit — PASS" in evidence
    assert "Overall QA status: APPROVE" in evidence


def test_dungeoncrawler_gate_requires_db_contract(tmp_path):
    fake_site = tmp_path / "site"
    (fake_site / "tests").mkdir(parents=True)

    result = subprocess.run(
        ["bash", str(DC_GATE_SCRIPT), "--dry-run"],
        env={
            "DUNGEONCRAWLER_SITE_ROOT": str(fake_site),
            "DUNGEONCRAWLER_QA_ARTIFACTS_ROOT": str(tmp_path / "artifacts"),
        },
        capture_output=True,
        text=True,
    )

    assert result.returncode == 2
    assert "requires SIMPLETEST_DB" in result.stderr


def test_dungeoncrawler_gate_derives_local_dsn_for_dry_run(tmp_path):
    fake_site = tmp_path / "site"
    (fake_site / "tests").mkdir(parents=True)

    result = subprocess.run(
        ["bash", str(DC_GATE_SCRIPT), "--dry-run"],
        env={
            "DUNGEONCRAWLER_SITE_ROOT": str(fake_site),
            "DUNGEONCRAWLER_QA_ARTIFACTS_ROOT": str(tmp_path / "artifacts"),
            "DB_PASSWORD": "test-password",
        },
        capture_output=True,
        text=True,
    )

    assert result.returncode == 0, result.stderr
    assert "SIMPLETEST_DB configured=yes" in result.stdout


def test_required_role_url_audits_explicitly_cover_recursive_url_validation():
    manifests = [
        SCRIPTS_DIR.parent / "qa-suites" / "products" / "forseti" / "suite.json",
        SCRIPTS_DIR.parent / "qa-suites" / "products" / "dungeoncrawler" / "suite.json",
    ]

    for manifest_path in manifests:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        role_audit = next(
            suite for suite in manifest["suites"] if suite["id"] == "role-url-audit"
        )
        cases = role_audit.get("test_cases") or []
        assert any(
            "recursive url validation" in (case.get("description", "")).lower()
            or "validate.json" in (case.get("command", "")).lower()
            for case in cases
        ), f"{manifest_path} must explicitly cover recursive URL validation"


def test_site_audit_run_invokes_recursive_url_validation():
    script = (SCRIPTS_DIR / "site-audit-run.sh").read_text(encoding="utf-8")
    assert script.count("python3 scripts/site-validate-urls.py") >= 2
