import importlib.util
import os
from pathlib import Path


MODULE = Path(__file__).resolve().parents[1] / "health_and_audit.py"


def _load_module():
    spec = importlib.util.spec_from_file_location("health_and_audit", MODULE)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


def test_gating_quarantine_ignores_feature_scoped_pm_handoff(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"

    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "features" / "forseti-langgraph-console-admin").mkdir(parents=True)
    (root / "sessions" / "pm-forseti" / "outbox").mkdir(parents=True)
    (root / "sessions" / "ceo-copilot-2" / "inbox").mkdir(parents=True)

    (root / "org-chart" / "products" / "product-teams.json").write_text(
        '{"teams":[{"id":"forseti","pm_agent":"pm-forseti","active":true}]}',
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
        "20260412-forseti-release-q\n",
        encoding="utf-8",
    )
    (root / "features" / "forseti-langgraph-console-admin" / "feature.md").write_text(
        "- Release: 20260412-forseti-release-q\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-forseti" / "outbox" / "20260419-groom-20260412-forseti-release-q.md").write_text(
        "- Status: done\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-forseti" / "outbox" / "20260420-needs-dev-forseti-20260420-164124-impl-forseti-langgraph-console-admin.md").write_text(
        "- Status: needs-info\n",
        encoding="utf-8",
    )

    state = root / "tmp" / "orchestrator-quarantine-escalate-last"
    mod.escalate_quarantined_gating_agents(root, state)

    assert not any((root / "sessions" / "ceo-copilot-2" / "inbox").iterdir())


def test_gating_quarantine_ignores_pm_signoff_reminders_after_signoff(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"

    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "sessions" / "pm-dungeoncrawler" / "outbox").mkdir(parents=True)
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs").mkdir(parents=True)
    (root / "sessions" / "ceo-copilot-2" / "inbox").mkdir(parents=True)

    (root / "org-chart" / "products" / "product-teams.json").write_text(
        '{"teams":[{"id":"dungeoncrawler","pm_agent":"pm-dungeoncrawler","active":true}]}',
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
        "20260412-forseti-release-q\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-dungeoncrawler" / "outbox" / "20260420-signoff-reminder-20260412-forseti-release-q.md").write_text(
        "- Status: needs-info\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "20260412-forseti-release-q.md").write_text(
        "signed\n",
        encoding="utf-8",
    )

    state = root / "tmp" / "orchestrator-quarantine-escalate-last"
    mod.escalate_quarantined_gating_agents(root, state)

    assert not any((root / "sessions" / "ceo-copilot-2" / "inbox").iterdir())


def test_gating_quarantine_ignores_manual_code_review_gate_approval(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"

    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "sessions" / "agent-code-review" / "outbox").mkdir(parents=True)
    (root / "sessions" / "ceo-copilot-2" / "outbox").mkdir(parents=True)
    (root / "sessions" / "ceo-copilot-2" / "inbox").mkdir(parents=True)

    (root / "org-chart" / "products" / "product-teams.json").write_text(
        '{"teams":[{"id":"forseti","pm_agent":"pm-forseti","active":true}]}',
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
        "20260412-forseti-release-q\n",
        encoding="utf-8",
    )
    (root / "sessions" / "agent-code-review" / "outbox" / "20260420-code-review-forseti.life-20260412-forseti-release-q.md").write_text(
        "- Status: needs-info\n",
        encoding="utf-8",
    )
    (root / "sessions" / "ceo-copilot-2" / "outbox" / "20260420-132856-code-review-gate-20260412-forseti-release-q.md").write_text(
        "- Status: done\n- Summary: Verdict: APPROVE\n",
        encoding="utf-8",
    )

    state = root / "tmp" / "orchestrator-quarantine-escalate-last"
    mod.escalate_quarantined_gating_agents(root, state)

    assert not any((root / "sessions" / "ceo-copilot-2" / "inbox").iterdir())


def test_gating_quarantine_dedupes_recent_matching_ceo_item(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"

    ceo_inbox = root / "sessions" / "ceo-copilot-2" / "inbox"
    existing = ceo_inbox / "20260426-120000-gating-agent-quarantine-escalation"
    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "sessions" / "pm-forseti" / "outbox").mkdir(parents=True)
    existing.mkdir(parents=True)

    (root / "org-chart" / "products" / "product-teams.json").write_text(
        '{"teams":[{"id":"forseti","pm_agent":"pm-forseti","active":true}]}',
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
        "20260412-forseti-release-t\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-forseti" / "outbox" / "20260426-signoff-reminder-20260412-forseti-release-t.md").write_text(
        "- Status: needs-info\n",
        encoding="utf-8",
    )
    (existing / "command.md").write_text(
        "# Gating Agent Quarantine Escalation\n\n"
        "## Quarantined Gating Agents\n"
        "- pm-forseti (1/1 = 100% quarantined, release=20260412-forseti-release-t)\n",
        encoding="utf-8",
    )

    state = root / "tmp" / "orchestrator-quarantine-escalate-last"
    mod.escalate_quarantined_gating_agents(root, state)

    pending = [p.name for p in ceo_inbox.iterdir() if p.is_dir()]
    assert pending == ["20260426-120000-gating-agent-quarantine-escalation"]
    assert not state.exists()


def test_gating_quarantine_allows_new_item_after_stale_window(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"

    ceo_inbox = root / "sessions" / "ceo-copilot-2" / "inbox"
    existing = ceo_inbox / "20260425-120000-gating-agent-quarantine-escalation"
    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "sessions" / "pm-forseti" / "outbox").mkdir(parents=True)
    existing.mkdir(parents=True)

    (root / "org-chart" / "products" / "product-teams.json").write_text(
        '{"teams":[{"id":"forseti","pm_agent":"pm-forseti","active":true}]}',
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
        "20260412-forseti-release-t\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-forseti" / "outbox" / "20260426-signoff-reminder-20260412-forseti-release-t.md").write_text(
        "- Status: needs-info\n",
        encoding="utf-8",
    )
    (existing / "command.md").write_text(
        "# Gating Agent Quarantine Escalation\n\n"
        "## Quarantined Gating Agents\n"
        "- pm-forseti (1/1 = 100% quarantined, release=20260412-forseti-release-t)\n",
        encoding="utf-8",
    )
    stale_at = existing.stat().st_mtime - (mod._QUARANTINE_ITEM_STALE_SECS + 60)
    os.utime(existing, (stale_at, stale_at))

    state = root / "tmp" / "orchestrator-quarantine-escalate-last"
    mod.escalate_quarantined_gating_agents(root, state)

    pending = sorted(p.name for p in ceo_inbox.iterdir() if p.is_dir())
    assert len(pending) == 2
    assert any(name.endswith("-gating-agent-quarantine-escalation") and name != existing.name for name in pending)
    assert state.exists()


def test_gating_quarantine_dedupes_same_agent_when_release_drifts(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"

    ceo_inbox = root / "sessions" / "ceo-copilot-2" / "inbox"
    existing = ceo_inbox / "20260425-120000-gating-agent-quarantine-escalation"
    (root / "org-chart" / "products").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "sessions" / "pm-forseti" / "outbox").mkdir(parents=True)
    existing.mkdir(parents=True)

    (root / "org-chart" / "products" / "product-teams.json").write_text(
        '{"teams":[{"id":"forseti","pm_agent":"pm-forseti","active":true}]}',
        encoding="utf-8",
    )
    (root / "tmp" / "release-cycle-active" / "forseti.release_id").write_text(
        "20260412-forseti-release-t\n",
        encoding="utf-8",
    )
    (root / "sessions" / "pm-forseti" / "outbox" / "20260426-signoff-reminder-20260412-forseti-release-t.md").write_text(
        "- Status: needs-info\n",
        encoding="utf-8",
    )
    (existing / "command.md").write_text(
        "# Gating Agent Quarantine Escalation\n\n"
        "## Quarantined Gating Agents\n"
        "- pm-forseti (1/2 = 50% quarantined, release=20260412-forseti-release-s)\n",
        encoding="utf-8",
    )

    state = root / "tmp" / "orchestrator-quarantine-escalate-last"
    mod.escalate_quarantined_gating_agents(root, state)

    pending = sorted(p.name for p in ceo_inbox.iterdir() if p.is_dir())
    assert pending == ["20260425-120000-gating-agent-quarantine-escalation"]
    assert not state.exists()
