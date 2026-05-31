"""Unit tests for check_stale_inbox_items() in release-kpi-monitor.py."""
import importlib.util
import os
import sys
import time
from pathlib import Path
import pytest
import tempfile
import shutil

# Load the function from release-kpi-monitor.py without running main()
_SCRIPT = Path(__file__).resolve().parents[1] / "release-kpi-monitor.py"
spec = importlib.util.spec_from_file_location("release_kpi_monitor", _SCRIPT)
_mod = importlib.util.module_from_spec(spec)
sys.modules["release_kpi_monitor"] = _mod
spec.loader.exec_module(_mod)
check_stale_inbox_items = _mod.check_stale_inbox_items


def _make_sessions(base: Path, items: list[dict]) -> Path:
    """Helper: create sessions directory structure.
    Each item dict: agent, item_name, roi, age_seconds, has_outbox (bool).
    """
    sessions = base / "sessions"
    now = time.time()
    for entry in items:
        agent = entry["agent"]
        item_name = entry["item_name"]
        inbox_item = sessions / agent / "inbox" / item_name
        inbox_item.mkdir(parents=True, exist_ok=True)
        (inbox_item / "roi.txt").write_text(str(entry["roi"]))
        # Set mtime to simulate age
        mtime = now - entry.get("age_seconds", 0)
        os.utime(inbox_item, (mtime, mtime))
        if entry.get("has_outbox"):
            outbox_dir = sessions / agent / "outbox"
            outbox_dir.mkdir(parents=True, exist_ok=True)
            (outbox_dir / f"{item_name}.md").write_text("- Status: done\n")
    return sessions


def test_detects_stale_high_roi_item():
    """A high-ROI item older than 24h with no outbox should be detected."""
    with tempfile.TemporaryDirectory() as tmp:
        sessions = _make_sessions(Path(tmp), [
            {"agent": "dev-infra", "item_name": "20260320-my-task", "roi": 15, "age_seconds": 90000},
        ])
        result = check_stale_inbox_items(sessions, threshold_roi=10, age_seconds=86400)
        assert len(result) == 1
        assert result[0]["agent"] == "dev-infra"
        assert result[0]["item"] == "20260320-my-task"
        assert result[0]["roi"] == 15
        assert result[0]["age_hours"] > 24


def test_skips_item_with_outbox():
    """A high-ROI stale item that already has an outbox counterpart must be skipped."""
    with tempfile.TemporaryDirectory() as tmp:
        sessions = _make_sessions(Path(tmp), [
            {"agent": "dev-infra", "item_name": "20260320-done-task", "roi": 20, "age_seconds": 100000, "has_outbox": True},
        ])
        result = check_stale_inbox_items(sessions, threshold_roi=10, age_seconds=86400)
        assert result == []


def test_skips_low_roi_item():
    """Items with roi < threshold should not be reported even if old."""
    with tempfile.TemporaryDirectory() as tmp:
        sessions = _make_sessions(Path(tmp), [
            {"agent": "qa-infra", "item_name": "20260301-low-roi", "roi": 5, "age_seconds": 200000},
        ])
        result = check_stale_inbox_items(sessions, threshold_roi=10, age_seconds=86400)
        assert result == []


def test_skips_recent_item():
    """A high-ROI item that is less than 24h old must not be reported."""
    with tempfile.TemporaryDirectory() as tmp:
        sessions = _make_sessions(Path(tmp), [
            {"agent": "pm-infra", "item_name": "20260322-new-task", "roi": 12, "age_seconds": 3600},
        ])
        result = check_stale_inbox_items(sessions, threshold_roi=10, age_seconds=86400)
        assert result == []


def test_multiple_agents_multiple_items():
    """Should detect stale items across multiple agents, skipping ones with outbox or low roi."""
    with tempfile.TemporaryDirectory() as tmp:
        sessions = _make_sessions(Path(tmp), [
            {"agent": "dev-infra", "item_name": "20260320-stale-a", "roi": 10, "age_seconds": 90000},
            {"agent": "dev-infra", "item_name": "20260320-done-b", "roi": 10, "age_seconds": 90000, "has_outbox": True},
            {"agent": "qa-infra", "item_name": "20260321-stale-c", "roi": 15, "age_seconds": 90000},
            {"agent": "qa-infra", "item_name": "20260321-low-roi", "roi": 3, "age_seconds": 90000},
        ])
        result = check_stale_inbox_items(sessions, threshold_roi=10, age_seconds=86400)
        names = {r["item"] for r in result}
        assert "20260320-stale-a" in names
        assert "20260321-stale-c" in names
        assert "20260320-done-b" not in names
        assert "20260321-low-roi" not in names
        assert len(result) == 2


def test_empty_sessions_dir():
    """Should return empty list when sessions directory does not exist."""
    result = check_stale_inbox_items(Path("/nonexistent/sessions"), threshold_roi=10, age_seconds=86400)
    assert result == []


def test_missing_roi_txt_skipped():
    """Items without roi.txt should be silently skipped."""
    with tempfile.TemporaryDirectory() as tmp:
        sessions = Path(tmp) / "sessions"
        item = sessions / "dev-infra" / "inbox" / "20260320-no-roi"
        item.mkdir(parents=True, exist_ok=True)
        # No roi.txt written
        mtime = time.time() - 100000
        os.utime(item, (mtime, mtime))
        result = check_stale_inbox_items(sessions, threshold_roi=10, age_seconds=86400)
        assert result == []
