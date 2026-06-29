import importlib.util
from datetime import datetime, timezone
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "ceo-ops-scheduler.py"
SPEC = importlib.util.spec_from_file_location("ceo_ops_scheduler", SCRIPT)
assert SPEC is not None and SPEC.loader is not None
MODULE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(MODULE)

extract_blockers = MODULE.extract_blockers
should_run_full_cycle = MODULE.should_run_full_cycle
update_blocker_state = MODULE.update_blocker_state


def test_should_run_full_cycle_relaxed_only_on_boundary():
    boundary = datetime(2026, 4, 24, 2, 0, tzinfo=timezone.utc)
    off_boundary = datetime(2026, 4, 24, 2, 10, tzinfo=timezone.utc)

    assert should_run_full_cycle(boundary, fast_mode=False, relaxed_interval_hours=2) is True
    assert should_run_full_cycle(off_boundary, fast_mode=False, relaxed_interval_hours=2) is False
    assert should_run_full_cycle(off_boundary, fast_mode=True, relaxed_interval_hours=2) is True


def test_extract_blockers_returns_fail_lines_only():
    output = "\n".join(
        [
            "✅ PASS Something healthy",
            "❌ FAIL Merge health: dirty tracked changes",
            "⚠️  WARN Audit stale",
            "❌ FAIL Orchestrator: pid file exists but process 123 is not running",
            "❌ FAIL Merge health: dirty tracked changes",
        ]
    )

    assert extract_blockers(output) == [
        "Merge health: dirty tracked changes",
        "Orchestrator: pid file exists but process 123 is not running",
    ]


def test_update_blocker_state_increments_only_persistent_blockers():
    previous = {
        "blockers": {
            "Merge health: dirty tracked changes": {"count": 1, "first_seen": "t1", "last_seen": "t1"},
            "Old blocker": {"count": 3, "first_seen": "t0", "last_seen": "t1"},
        }
    }

    updated = update_blocker_state(
        previous,
        ["Merge health: dirty tracked changes", "New blocker"],
        "t2",
    )

    assert updated == {
        "Merge health: dirty tracked changes": {"count": 2, "first_seen": "t1", "last_seen": "t2"},
        "New blocker": {"count": 1, "first_seen": "t2", "last_seen": "t2"},
    }
