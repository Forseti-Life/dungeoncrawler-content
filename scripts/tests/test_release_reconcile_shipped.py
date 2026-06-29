import json
import os
import subprocess
import textwrap
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "release-reconcile-shipped.py"


def _make_root(tmp_path: Path) -> tuple[Path, str]:
    root = tmp_path / "hq"
    release_id = "20260412-dungeoncrawler-release-p"

    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "features").mkdir(parents=True)
    (root / "sessions" / "qa-dungeoncrawler" / "outbox").mkdir(parents=True)
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs").mkdir(parents=True)
    (root / "sessions" / "pm-forseti" / "artifacts" / "release-candidates" / release_id).mkdir(parents=True)

    teams = {
        "teams": [
            {
                "id": "dungeoncrawler",
                "site": "dungeoncrawler",
                "aliases": ["dungeoncrawler", "dungeoncrawler.forseti.life"],
                "pm_agent": "pm-dungeoncrawler",
                "qa_agent": "qa-dungeoncrawler",
                "active": True,
                "coordinated_release_default": True,
                "site_audit": {
                    "drupal_web_root": str(root / "sites" / "dungeoncrawler" / "web"),
                },
            }
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(
        json.dumps(teams),
        encoding="utf-8",
    )

    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / f"{release_id}.md").write_text(
        "# PM signoff\n",
        encoding="utf-8",
    )
    (root / "sessions" / "qa-dungeoncrawler" / "outbox" / f"20260412-gate2-aggregate-approve-{release_id}.md").write_text(
        f"{release_id} — APPROVE\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-forseti" / "artifacts" / "release-candidates" / release_id / "01-change-list.md").write_text(
        textwrap.dedent(
            f"""\
            # Change List

            ### dc-b2-bestiary2
            - Status: done

            ### dc-gng-guns-gears
            - Status: shipped
            """
        ),
        encoding="utf-8",
    )

    for feature_id, status, feature_release in (
        ("dc-b2-bestiary2", "done", release_id),
        ("dc-gng-guns-gears", "shipped", release_id),
        ("dc-som-secrets-of-magic", "done", "20260412-dungeoncrawler-release-q"),
    ):
        feature_dir = root / "features" / feature_id
        feature_dir.mkdir(parents=True)
        (feature_dir / "feature.md").write_text(
            textwrap.dedent(
                f"""\
                # Feature Brief

                - Work item id: {feature_id}
                - Website: dungeoncrawler.forseti.life
                - Status: {status}
                - Release: {feature_release}
                - Dev owner: dev-dungeoncrawler
                - QA owner: qa-dungeoncrawler
                - Source: community_suggestion NID {2 if feature_id == 'dc-b2-bestiary2' else 3} (Talk to Forseti intake)

                ## Latest updates

                - 2026-04-12: Placeholder.
                """
            ),
            encoding="utf-8",
        )

    drupal_root = root / "sites" / "dungeoncrawler"
    (drupal_root / "vendor" / "bin").mkdir(parents=True)
    drush = drupal_root / "vendor" / "bin" / "drush"
    drush.write_text(
        textwrap.dedent(
            """\
            #!/usr/bin/env python3
            import json
            import os
            from pathlib import Path

            capture = os.environ.get("DRUSH_CAPTURE_PATH", "").strip()
            suggestion_capture = os.environ.get("SUGGESTION_STATUS_CAPTURE_PATH", "").strip()
            if capture and os.environ.get("FEATURE_IDS_JSON"):
                Path(capture).write_text(os.environ.get("FEATURE_IDS_JSON", ""), encoding="utf-8")
                print(os.environ.get("DRUSH_RESPONSE_JSON", '{"table_exists": true, "updated_total": 0, "per_feature": {}, "skipped_reason": ""}'))
            elif suggestion_capture and os.environ.get("SUGGESTION_NID"):
                Path(suggestion_capture).write_text(json.dumps({
                    "nid": os.environ.get("SUGGESTION_NID", ""),
                    "status": os.environ.get("SUGGESTION_STATUS", ""),
                }), encoding="utf-8")
                print(json.dumps({
                    "ok": True,
                    "nid": os.environ.get("SUGGESTION_NID", ""),
                    "status": os.environ.get("SUGGESTION_STATUS", ""),
                    "previous_status": "in_progress",
                    "updated": True,
                }))
            else:
                print('{"table_exists": true, "updated_total": 0, "per_feature": {}, "skipped_reason": ""}')
            """
        ),
        encoding="utf-8",
    )
    drush.chmod(0o755)

    return root, release_id


def _run(root: Path, release_id: str, extra_env: dict[str, str] | None = None) -> subprocess.CompletedProcess[str]:
    env = os.environ.copy()
    env["HQ_ROOT_DIR"] = str(root)
    if extra_env:
        env.update(extra_env)
    return subprocess.run(
        ["python3", str(SCRIPT), "dungeoncrawler", release_id],
        cwd=root,
        env=env,
        capture_output=True,
        text=True,
    )


def test_reconciles_done_features_and_requirements(tmp_path):
    root, release_id = _make_root(tmp_path)
    capture = root / "drush-capture.json"
    suggestion_capture = root / "suggestion-capture.json"
    response = {
        "table_exists": True,
        "updated_total": 7,
        "per_feature": {
            "dc-b2-bestiary2": {"matched": 5, "updated": 5},
            "dc-gng-guns-gears": {"matched": 2, "updated": 2},
        },
        "skipped_reason": "",
    }

    result = _run(
        root,
        release_id,
        {
            "DRUSH_CAPTURE_PATH": str(capture),
            "SUGGESTION_STATUS_CAPTURE_PATH": str(suggestion_capture),
            "DRUSH_RESPONSE_JSON": json.dumps(response),
        },
    )

    assert result.returncode == 0, result.stdout + result.stderr
    assert "RECONCILED dungeoncrawler" in result.stdout
    assert "requirements_updated=7" in result.stdout
    assert "suggestions_updated=2" in result.stdout

    shipped_feature = (root / "features" / "dc-b2-bestiary2" / "feature.md").read_text(encoding="utf-8")
    assert "- Status: shipped" in shipped_feature
    assert "Reconciled to shipped after coordinated push" in shipped_feature

    still_shipped = (root / "features" / "dc-gng-guns-gears" / "feature.md").read_text(encoding="utf-8")
    assert "- Status: shipped" in still_shipped

    captured_ids = json.loads(capture.read_text(encoding="utf-8"))
    assert captured_ids == ["dc-b2-bestiary2", "dc-gng-guns-gears"]
    captured_suggestion = json.loads(suggestion_capture.read_text(encoding="utf-8"))
    assert captured_suggestion == {"nid": "3", "status": "implemented"}

    artifact = (
        root
        / "sessions"
        / "pm-dungeoncrawler"
        / "artifacts"
        / "release-reconciliation"
        / f"{release_id}.md"
    )
    assert artifact.is_file()
    text = artifact.read_text(encoding="utf-8")
    assert "Promoted `done -> shipped`: 1" in text
    assert "Already shipped in release: 1" in text
    assert "Requirement rows updated: 7" in text
    assert "Suggestion nodes updated to `implemented`: 2" in text
    assert "- dc-b2-bestiary2: matched=5 updated=5" in text


def test_reports_change_list_metadata_mismatch_and_skips_requirements_without_drush(tmp_path):
    root, release_id = _make_root(tmp_path)

    (root / "sessions" / "pm-forseti" / "artifacts" / "release-candidates" / release_id / "01-change-list.md").write_text(
        textwrap.dedent(
            """\
            # Change List

            ### dc-ancestry-system
            - Status: done
            """
        ),
        encoding="utf-8",
    )
    feature_dir = root / "features" / "dc-ancestry-system"
    feature_dir.mkdir(parents=True)
    (feature_dir / "feature.md").write_text(
        textwrap.dedent(
            """\
            # Feature Brief

            - Work item id: dc-ancestry-system
            - Website: dungeoncrawler.forseti.life
            - Status: done
            - Release:
            """
        ),
        encoding="utf-8",
    )
    (root / "sites" / "dungeoncrawler" / "vendor" / "bin" / "drush").unlink()

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    artifact = (
        root
        / "sessions"
        / "pm-dungeoncrawler"
        / "artifacts"
        / "release-reconciliation"
        / f"{release_id}.md"
    )
    text = artifact.read_text(encoding="utf-8")
    assert "dc-ancestry-system: Release field is '(blank)'" in text
    assert "drush not found" in text


def test_accepts_legacy_gate2_verify_artifact(tmp_path):
    root, release_id = _make_root(tmp_path)
    legacy = root / "sessions" / "qa-dungeoncrawler" / "outbox" / f"20260412-gate2-verify-{release_id}.md"
    legacy.write_text(f"{release_id} — APPROVE\n", encoding="utf-8")
    for approve in (root / "sessions" / "qa-dungeoncrawler" / "outbox").glob("*gate2-aggregate-approve*.md"):
        approve.unlink()

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    assert "RECONCILED dungeoncrawler" in result.stdout


def test_drush_failure_is_recorded_without_aborting_reconciliation(tmp_path):
    root, release_id = _make_root(tmp_path)
    drush = root / "sites" / "dungeoncrawler" / "vendor" / "bin" / "drush"
    drush.write_text(
        textwrap.dedent(
            """\
            #!/usr/bin/env python3
            import sys
            print("boom", file=sys.stderr)
            raise SystemExit(1)
            """
        ),
        encoding="utf-8",
    )
    drush.chmod(0o755)

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    artifact = (
        root
        / "sessions"
        / "pm-dungeoncrawler"
        / "artifacts"
        / "release-reconciliation"
        / f"{release_id}.md"
    )
    text = artifact.read_text(encoding="utf-8")
    assert "drush php:eval failed:" in text


def test_accepts_release_scoped_gate2_approval_outbox_without_legacy_filename(tmp_path):
    root, release_id = _make_root(tmp_path)
    fallback = root / "sessions" / "qa-dungeoncrawler" / "outbox" / "20260412-unit-test-release-approval.md"
    fallback.write_text(
        f"- Status: done\n- Summary: Gate 2 verification complete for release `{release_id}`. APPROVE.\n",
        encoding="utf-8",
    )
    for approve in (root / "sessions" / "qa-dungeoncrawler" / "outbox").glob("*gate2-aggregate-approve*.md"):
        approve.unlink()

    result = _run(root, release_id)

    assert result.returncode == 0, result.stdout + result.stderr
    assert "RECONCILED dungeoncrawler" in result.stdout
