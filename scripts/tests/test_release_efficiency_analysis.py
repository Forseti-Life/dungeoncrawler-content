import importlib.util
from datetime import datetime, timezone
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "release-efficiency-analysis.py"


def _load_module():
    spec = importlib.util.spec_from_file_location("release_efficiency_analysis", SCRIPT)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


def test_artifact_dt_prefers_filename_timestamp(tmp_path):
    mod = _load_module()
    artifact = tmp_path / "20260420-140050-gate2-approve-release-s.md"
    artifact.write_text("ok\n", encoding="utf-8")
    artifact.touch()

    dt = mod.artifact_dt(artifact)

    assert dt == datetime(2026, 4, 20, 14, 0, 50, tzinfo=timezone.utc)


def test_find_r5_audit_time_uses_filename_timestamp(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"
    mod.ROOT = root

    qa_outbox = root / "sessions" / "qa-dungeoncrawler" / "outbox"
    qa_outbox.mkdir(parents=True)
    artifact = qa_outbox / "20260420-140050-gate2-approve-20260412-dungeoncrawler-release-s.md"
    artifact.write_text("approve\n", encoding="utf-8")
    artifact.touch()

    push_time = datetime(2026, 4, 20, 13, 28, 58, tzinfo=timezone.utc)

    dt = mod.find_r5_audit_time("dungeoncrawler", push_time)

    assert dt == datetime(2026, 4, 20, 14, 0, 50, tzinfo=timezone.utc)


def test_ceo_proxy_sessions_skip_needs_dispatches(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"
    mod.ROOT = root

    outbox = root / "sessions" / "ceo-copilot-2" / "outbox"
    outbox.mkdir(parents=True)
    (outbox / "20260420-needs-pm-forseti-20260420-signoff-reminder-20260412-dungeoncrawler-release-s.md").write_text(
        "delegation\n", encoding="utf-8"
    )
    (outbox / "20260420-groom-20260412-dungeoncrawler-release-s.md").write_text(
        "pm proxy\n", encoding="utf-8"
    )

    proxy = mod.ceo_proxy_sessions(
        "20260412-dungeoncrawler-release-s",
        ["dc-cr-halfling-resolve"],
        "20260412-forseti-release-q",
        None,
    )

    assert len(proxy.get("pm", [])) == 1
    assert proxy["pm"][0].name == "20260420-groom-20260412-dungeoncrawler-release-s.md"


def test_dev_outbox_files_ignore_transcript_noise(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"
    mod.ROOT = root

    outbox = root / "sessions" / "dev-dungeoncrawler" / "outbox"
    outbox.mkdir(parents=True)
    (outbox / "20260410-021500-implement-dc-cr-dwarf-ancestry.md").write_text(
        "# Outbox: dc-cr-dwarf-ancestry\n\n- Status: done\n",
        encoding="utf-8",
    )
    (outbox / "20260410-impl-dc-cr-dwarf-ancestry.md").write_text(
        "Good — the Dwarf base entry exists but is missing data.\n\n- Status: done\n",
        encoding="utf-8",
    )

    files = mod.dev_outbox_files_for_feature("dc-cr-dwarf-ancestry", "dev-dungeoncrawler")

    assert [f.name for f in files] == ["20260410-021500-implement-dc-cr-dwarf-ancestry.md"]


def test_code_review_completed_accepts_embedded_verdict(tmp_path):
    mod = _load_module()
    artifact = tmp_path / "20260420-code-review-dungeoncrawler-20260412-dungeoncrawler-release-r.md"
    artifact.write_text(
        "- Status: needs-info\n\n## CEO Code Review Verdict (2026-04-20)\n\n**VERDICT: APPROVE** — clear for ship.\n",
        encoding="utf-8",
    )

    assert mod.code_review_verdict(artifact).startswith("approve")
    assert mod.code_review_completed(artifact) is True


def test_agent_outbox_files_for_release_matches_body_references(tmp_path):
    mod = _load_module()
    root = tmp_path / "hq"
    mod.ROOT = root

    outbox = root / "sessions" / "agent-code-review" / "outbox"
    outbox.mkdir(parents=True)
    review = outbox / "20260420-manual-cr-generic.md"
    review.write_text(
        "# Manual CR\n\n- Status: done\n- Release: 20260412-dungeoncrawler-release-s\n- Feature: dc-cr-dwarf-ancestry\n",
        encoding="utf-8",
    )

    files = mod.agent_outbox_files_for_release(
        "agent-code-review",
        "20260412-dungeoncrawler-release-s",
        ["dc-cr-dwarf-ancestry"],
    )

    assert [f.name for f in files] == ["20260420-manual-cr-generic.md"]
