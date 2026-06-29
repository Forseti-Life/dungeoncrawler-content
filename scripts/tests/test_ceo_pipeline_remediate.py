import json
import os
import stat
import subprocess
import sys
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "ceo-pipeline-remediate.py"


def _write_executable(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8")
    path.chmod(path.stat().st_mode | stat.S_IXUSR)


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "features").mkdir(parents=True)
    (root / "scripts").mkdir(parents=True)

    teams = {
        "teams": [
            {
                "id": "forseti",
                "pm_agent": "pm-forseti",
                "qa_agent": "qa-forseti",
                "active": True,
                "coordinated_release_default": True,
            },
            {
                "id": "dungeoncrawler",
                "pm_agent": "pm-dungeoncrawler",
                "qa_agent": "qa-dungeoncrawler",
                "active": True,
                "coordinated_release_default": True,
            },
        ]
    }
    (root / "org-chart" / "products" / "product-teams.json").write_text(
        json.dumps(teams),
        encoding="utf-8",
    )

    (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
        "20260412-forseti-release-i\n",
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
        "20260412-dungeoncrawler-release-j\n",
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "forseti.started_at").write_text(
        "2026-04-12T00:00:00+00:00\n",
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "dungeoncrawler.started_at").write_text(
        "2026-04-12T00:00:00+00:00\n",
        encoding="utf-8",
    )

    for agent in [
        "pm-forseti",
        "pm-dungeoncrawler",
        "qa-forseti",
        "qa-dungeoncrawler",
        "dev-dungeoncrawler",
        "ceo-copilot-2",
    ]:
        (root / "sessions" / agent / "inbox").mkdir(parents=True, exist_ok=True)
        (root / "sessions" / agent / "outbox").mkdir(parents=True, exist_ok=True)
        (root / "sessions" / agent / "artifacts" / "release-signoffs").mkdir(parents=True, exist_ok=True)

    (root / "sessions" / "dev-dungeoncrawler" / "outbox" / "20260413-impl-dc-old.md").write_text(
        "dc-old-feature implemented\n",
        encoding="utf-8",
    )

    for feature_id, release_id in [
        ("forseti-feature-a", "20260412-forseti-release-i"),
        ("dc-feature-a", "20260412-dungeoncrawler-release-j"),
    ]:
        feature_dir = root / "features" / feature_id
        feature_dir.mkdir()
        (feature_dir / "feature.md").write_text(
            f"- Status: in_progress\n- Release: {release_id}\n",
            encoding="utf-8",
        )

    old_feature_dir = root / "features" / "dc-old-feature"
    old_feature_dir.mkdir()
    (old_feature_dir / "feature.md").write_text(
        "- Status: in_progress\n- Release: 20260412-dungeoncrawler-release-i\n",
        encoding="utf-8",
    )

    _write_executable(
        root / "scripts" / "sla-report.sh",
        "#!/usr/bin/env bash\nset -euo pipefail\necho 'SLA report @ test'\necho 'BREACH outbox-lag: dev-dungeoncrawler inbox=20260412-old age=7200s'\n",
    )
    _write_executable(
        root / "scripts" / "supervisor-for.sh",
        "#!/usr/bin/env bash\nset -euo pipefail\ncase \"$1\" in\n  dev-dungeoncrawler) echo 'pm-dungeoncrawler' ;;\n  *) echo 'ceo-copilot-2' ;;\nesac\n",
    )
    return root


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


def test_queues_release_and_sla_followups(tmp_path):
    root = _make_root(tmp_path)
    (root / "sessions" / "dev-forseti" / "outbox").mkdir(parents=True, exist_ok=True)
    (root / "sessions" / "dev-forseti" / "outbox" / "20260413-impl-forseti-feature-a.md").write_text(
        "forseti-feature-a implemented\n",
        encoding="utf-8",
    )
    (root / "sessions" / "qa-forseti" / "outbox" / "20260413-gate2-approve-forseti-release-i.md").write_text(
        "20260412-forseti-release-i\nAPPROVE\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / "20260412-forseti-release-i.md").write_text(
        "signed\n",
        encoding="utf-8",
    )
    (root / "sessions" / "dev-dungeoncrawler" / "outbox" / "20260413-impl-dc-feature-a.md").write_text(
        "dc-feature-a implemented\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr
    assert "Queued" in result.stdout

    pm_forseti_items = sorted(p.name for p in (root / "sessions" / "pm-forseti" / "inbox").iterdir())
    pm_dc_items = sorted(p.name for p in (root / "sessions" / "pm-dungeoncrawler" / "inbox").iterdir())
    qa_forseti_items = sorted(p.name for p in (root / "sessions" / "qa-forseti" / "inbox").iterdir())
    qa_dc_items = sorted(p.name for p in (root / "sessions" / "qa-dungeoncrawler" / "inbox").iterdir())

    assert not any("signoff-reminder-20260412-forseti-release-i" in name for name in pm_forseti_items)
    assert not any("signoff-reminder-20260412-dungeoncrawler-release-j" in name for name in pm_forseti_items)
    assert not any("signoff-reminder-20260412-dungeoncrawler-release-j" in name for name in pm_dc_items)
    assert not any("signoff-reminder-20260412-forseti-release-i" in name for name in pm_dc_items)
    assert any("release-cleanup-dungeoncrawler-orphans" in name for name in pm_dc_items)
    assert any("sla-outbox-lag-dev-dungeoncrawler-20260412-old" in name for name in pm_dc_items)
    assert not any("gate2-followup-20260412-forseti-release-i" in name for name in qa_forseti_items)
    assert any("gate2-followup-20260412-dungeoncrawler-release-j" in name for name in qa_dc_items)
    reminder = next(
        p
        for p in (root / "sessions" / "pm-dungeoncrawler" / "inbox").iterdir()
        if "signoff-reminder-20260412-forseti-release-i" in p.name
    )
    readme = (reminder / "README.md").read_text(encoding="utf-8")
    assert "bash scripts/release-signoff.sh" in readme
    assert "20260412-forseti-release-i" in readme
    assert "`sessions/pm-dungeoncrawler/artifacts/release-signoffs/20260412-forseti-release-i.md`" in readme
    assert "bash scripts/release-signoff-status.sh 20260412-forseti-release-i" in readme


def test_skips_signoff_and_gate2_until_dev_outbox_exists(tmp_path):
    root = _make_root(tmp_path)

    result = _run(root)

    assert result.returncode == 0, result.stderr

    pm_dc_items = sorted(p.name for p in (root / "sessions" / "pm-dungeoncrawler" / "inbox").iterdir())
    qa_dc_items = sorted(p.name for p in (root / "sessions" / "qa-dungeoncrawler" / "inbox").iterdir())

    assert not any("signoff-reminder-20260412-dungeoncrawler-release-j" in name for name in pm_dc_items)
    assert not any("gate2-followup-20260412-dungeoncrawler-release-j" in name for name in qa_dc_items)
    assert any("release-cleanup-dungeoncrawler-orphans" in name for name in pm_dc_items)


def test_gate2_followup_bundles_release_evidence(tmp_path):
    root = _make_root(tmp_path)
    (root / "features" / "dc-feature-a" / "01-acceptance-criteria.md").write_text(
        "# AC\n- route works\n",
        encoding="utf-8",
    )
    (root / "sessions" / "dev-dungeoncrawler" / "outbox" / "20260413-complete-dc-feature-a.md").write_text(
        "- Status: done\n- Summary: implemented dc-feature-a\n",
        encoding="utf-8",
    )
    (root / "sessions" / "qa-dungeoncrawler" / "outbox" / "20260413-gate2-block-dc-release-j.md").write_text(
        "- Status: blocked\n- Summary: prior block\n\n20260412-dungeoncrawler-release-j\nBLOCK\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr
    item = next(
        p for p in (root / "sessions" / "qa-dungeoncrawler" / "inbox").iterdir()
        if "gate2-followup-20260412-dungeoncrawler-release-j" in p.name
    )
    command = (item / "command.md").read_text(encoding="utf-8")
    release_ac = (item / "01-acceptance-criteria.md").read_text(encoding="utf-8")
    bundled_feature = (item / "evidence" / "dc-feature-a" / "feature.md").read_text(encoding="utf-8")
    bundled_ac = (item / "evidence" / "dc-feature-a" / "01-acceptance-criteria.md").read_text(encoding="utf-8")
    bundled_dev = (item / "evidence" / "dc-feature-a" / "dev-outbox.md").read_text(encoding="utf-8")
    prior_qa = (item / "evidence" / "prior-qa-outbox.md").read_text(encoding="utf-8")

    assert "Use the bundled inbox evidence as your primary working set." in command
    assert "Do not assume additional repo-read or shell capabilities are available." in command
    assert "Feature brief — dc-feature-a" in command
    assert "Acceptance criteria — dc-feature-a" in command
    assert "Latest dev outbox — dc-feature-a" in command
    assert "Latest related QA outbox" in command
    assert "Produce exactly one release-scoped QA verdict" in release_ac
    assert "- Status: in_progress\n- Release: 20260412-dungeoncrawler-release-j\n" == bundled_feature
    assert "# AC\n- route works\n" == bundled_ac
    assert "- Status: done\n- Summary: implemented dc-feature-a\n" == bundled_dev
    assert "prior block" in prior_qa


def test_does_not_queue_cross_team_signoff_reminders(tmp_path):
    root = _make_root(tmp_path)
    (root / "sessions" / "dev-dungeoncrawler" / "outbox" / "20260413-impl-dc-feature-a.md").write_text(
        "dc-feature-a implemented\n",
        encoding="utf-8",
    )
    (root / "sessions" / "qa-dungeoncrawler" / "outbox" / "20260413-gate2-approve-dc-release-j.md").write_text(
        "20260412-dungeoncrawler-release-j\nAPPROVE\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr

    pm_forseti_items = sorted(p.name for p in (root / "sessions" / "pm-forseti" / "inbox").iterdir())
    assert not any("signoff-reminder-20260412-dungeoncrawler-release-j" in name for name in pm_forseti_items)


def test_is_idempotent_for_existing_items(tmp_path):
    root = _make_root(tmp_path)

    first = _run(root)
    second = _run(root)

    assert first.returncode == 0, first.stderr
    assert second.returncode == 0, second.stderr
    assert second.stdout.strip() == "Queued 0 CEO remediation item(s)"


def test_missing_escalation_items_include_structured_metadata(tmp_path):
    root = _make_root(tmp_path)
    (root / "scripts" / "sla-report.sh").write_text(
        "#!/usr/bin/env bash\n"
        "set -euo pipefail\n"
        "echo 'SLA report @ test'\n"
        "echo 'BREACH missing-escalation: pm-dungeoncrawler status=blocked "
        "outbox=20260414-release-close-now-20260412-dungeoncrawler-release-m.md "
        "supervisor=ceo-copilot-2'\n",
        encoding="utf-8",
    )
    (root / "scripts" / "sla-report.sh").chmod(
        (root / "scripts" / "sla-report.sh").stat().st_mode | stat.S_IXUSR
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr

    items = sorted((root / "sessions" / "ceo-copilot-2" / "inbox").iterdir())
    readme = (items[0] / "README.md").read_text(encoding="utf-8")
    assert "- Escalated agent: pm-dungeoncrawler" in readme
    assert "- Escalated item: 20260414-release-close-now-20260412-dungeoncrawler-release-m" in readme
    assert "- Escalated status: blocked" in readme


def test_missing_escalation_dedupes_existing_pending_metadata_item(tmp_path):
    root = _make_root(tmp_path)
    (root / "scripts" / "sla-report.sh").write_text(
        "#!/usr/bin/env bash\n"
        "set -euo pipefail\n"
        "echo 'SLA report @ test'\n"
        "echo 'BREACH missing-escalation: pm-dungeoncrawler status=blocked "
        "outbox=20260414-release-close-now-20260412-dungeoncrawler-release-m.md "
        "supervisor=ceo-copilot-2'\n",
        encoding="utf-8",
    )
    (root / "scripts" / "sla-report.sh").chmod(
        (root / "scripts" / "sla-report.sh").stat().st_mode | stat.S_IXUSR
    )

    existing = (
        root / "sessions" / "ceo-copilot-2" / "inbox"
        / "20260425-sla-missing-escalation-pm-dungeoncrawler-20260414-release-close-now"
    )
    existing.mkdir(parents=True)
    (existing / "README.md").write_text(
        "# SLA breach: missing escalation for pm-dungeoncrawler\n\n"
        "- Agent: ceo-copilot-2\n"
        "- Escalated agent: pm-dungeoncrawler\n"
        "- Escalated item: 20260414-release-close-now-20260412-dungeoncrawler-release-m\n"
        "- Escalated status: needs-info\n"
        "- Status: pending\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr
    items = sorted(p.name for p in (root / "sessions" / "ceo-copilot-2" / "inbox").iterdir() if p.is_dir())
    assert items == ["20260425-sla-missing-escalation-pm-dungeoncrawler-20260414-release-close-now"]


def test_in_progress_age_breach_creates_ceo_alert(tmp_path):
    root = _make_root(tmp_path)
    (root / "scripts" / "sla-report.sh").write_text(
        "#!/usr/bin/env bash\n"
        "set -euo pipefail\n"
        "echo 'SLA report @ test'\n"
        "echo 'BREACH in-progress-age: pm-forseti inbox=20260428-flow-feature_request_intake-suggestion-forseti-nid-2-pm-scope-decision-r1 age=5400s status=in-progress'\n",
        encoding="utf-8",
    )
    (root / "scripts" / "sla-report.sh").chmod(
        (root / "scripts" / "sla-report.sh").stat().st_mode | stat.S_IXUSR
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr
    items = sorted((root / "sessions" / "ceo-copilot-2" / "inbox").iterdir())
    readme = (items[0] / "README.md").read_text(encoding="utf-8")
    assert "# SLA breach: aging in-progress item for pm-forseti" in readme
    assert "- Escalated agent: pm-forseti" in readme
    assert (
        "- Escalated item: 20260428-flow-feature_request_intake-suggestion-forseti-nid-2-pm-scope-decision-r1"
        in readme
    )
    assert "- Escalated status: in-progress" in readme


def test_skips_signoff_reminder_when_stale_inbox_item_exists(tmp_path):
    """Guard 2: if a signoff-reminder for the same release already exists in the
    inbox (e.g. from a prior day's DATE_PREFIX run), no duplicate should be created."""
    root = _make_root(tmp_path)

    # Simulate full readiness: dev outbox + Gate 2 approve exist but signoff artifact
    # has NOT been written yet.
    (root / "sessions" / "dev-dungeoncrawler" / "outbox" / "20260413-impl-dc.md").write_text(
        "dc-feature implemented\n", encoding="utf-8"
    )
    (root / "sessions" / "qa-dungeoncrawler" / "outbox").mkdir(parents=True, exist_ok=True)
    (root / "sessions" / "qa-dungeoncrawler" / "outbox" / "20260414-gate2-approve-dc.md").write_text(
        "APPROVE 20260412-dungeoncrawler-release-j\n", encoding="utf-8"
    )

    # Stale inbox item from a prior date prefix (simulates cross-day re-run).
    stale_item = (
        root / "sessions" / "pm-dungeoncrawler" / "inbox"
        / "20260418-signoff-reminder-20260412-dungeoncrawler-release-j"
    )
    stale_item.mkdir(parents=True)
    (stale_item / "README.md").write_text("stale reminder\n", encoding="utf-8")

    result = _run(root)

    assert result.returncode == 0, result.stderr
    pm_dc_items = [
        p.name
        for p in (root / "sessions" / "pm-dungeoncrawler" / "inbox").iterdir()
        if p.is_dir()
    ]
    # Should NOT create a new signoff-reminder — stale one is still pending.
    new_reminders = [n for n in pm_dc_items if "signoff-reminder" in n and "20260418" not in n]
    assert new_reminders == [], f"Unexpected new signoff-reminder items: {new_reminders}"


def test_skips_gate2_followup_when_stale_inbox_item_exists(tmp_path):
    """Guard gate2-followup dedup across DATE_PREFIX changes."""
    root = _make_root(tmp_path)

    (root / "sessions" / "dev-dungeoncrawler" / "outbox" / "20260413-impl-dc-feature-a.md").write_text(
        "dc-feature implemented\n", encoding="utf-8"
    )

    stale_item = (
        root / "sessions" / "qa-dungeoncrawler" / "inbox"
        / "20260418-gate2-followup-20260412-dungeoncrawler-release-j"
    )
    stale_item.mkdir(parents=True)
    (stale_item / "README.md").write_text("stale gate2 followup\n", encoding="utf-8")

    result = _run(root)

    assert result.returncode == 0, result.stderr
    qa_dc_items = [
        p.name
        for p in (root / "sessions" / "qa-dungeoncrawler" / "inbox").iterdir()
        if p.is_dir()
    ]
    new_followups = [n for n in qa_dc_items if "gate2-followup" in n and "20260418" not in n]
    assert new_followups == [], f"Unexpected new gate2-followup items: {new_followups}"


def test_skips_signoff_reminder_when_artifact_exists(tmp_path):
    """Guard 1: if the signoff artifact is already present, no inbox item should be created."""
    root = _make_root(tmp_path)

    (root / "sessions" / "dev-dungeoncrawler" / "outbox" / "20260413-impl-dc.md").write_text(
        "dc-feature implemented\n", encoding="utf-8"
    )
    (root / "sessions" / "qa-dungeoncrawler" / "outbox").mkdir(parents=True, exist_ok=True)
    (root / "sessions" / "qa-dungeoncrawler" / "outbox" / "20260414-gate2-approve-dc.md").write_text(
        "APPROVE 20260412-dungeoncrawler-release-j\n", encoding="utf-8"
    )
    # Signoff artifact already written — no reminder needed.
    (
        root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs"
        / "20260412-dungeoncrawler-release-j.md"
    ).write_text("signed\n", encoding="utf-8")

    result = _run(root)

    assert result.returncode == 0, result.stderr
    pm_dc_items = [
        p.name
        for p in (root / "sessions" / "pm-dungeoncrawler" / "inbox").iterdir()
        if p.is_dir()
    ]
    assert not any("signoff-reminder" in n for n in pm_dc_items), (
        f"Unexpected signoff-reminder created when artifact exists: {pm_dc_items}"
    )


def test_queues_code_review_followup_instead_of_signoff_reminder(tmp_path):
    root = _make_root(tmp_path)
    (root / "sessions" / "dev-forseti" / "outbox").mkdir(parents=True, exist_ok=True)
    (root / "sessions" / "dev-forseti" / "outbox" / "20260413-impl-forseti-feature-a.md").write_text(
        "forseti-feature-a implemented\n",
        encoding="utf-8",
    )
    (root / "sessions" / "qa-forseti" / "outbox").mkdir(parents=True, exist_ok=True)
    (root / "sessions" / "qa-forseti" / "outbox" / "20260414-gate2-approve-forseti.md").write_text(
        "APPROVE 20260412-forseti-release-i\n", encoding="utf-8"
    )
    (
        root / "sessions" / "pm-forseti" / "artifacts" / "release-signoffs" / "20260412-forseti-release-i.md"
    ).write_text("signed\n", encoding="utf-8")
    (root / "sessions" / "agent-code-review" / "outbox").mkdir(parents=True, exist_ok=True)
    (
        root
        / "sessions"
        / "agent-code-review"
        / "outbox"
        / "20260428-code-review-forseti.life-20260412-forseti-release-i.md"
    ).write_text(
        "- Status: done\n\n"
        "### HIGH\n\n"
        "**H-01 — Missing CSRF token validation**\n",
        encoding="utf-8",
    )

    result = _run(root)

    assert result.returncode == 0, result.stderr
    pm_dc_items = [
        p.name
        for p in (root / "sessions" / "pm-dungeoncrawler" / "inbox").iterdir()
        if p.is_dir()
    ]
    assert any("code-review-followup-20260412-forseti-release-i" in n for n in pm_dc_items)
    assert not any("signoff-reminder-20260412-forseti-release-i" in n for n in pm_dc_items)
