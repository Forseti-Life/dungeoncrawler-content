from pathlib import Path
import importlib.util
import sys


SCRIPT = Path(__file__).resolve().parents[1] / "reap_stale_copilot.py"
SPEC = importlib.util.spec_from_file_location("reap_stale_copilot", SCRIPT)
MODULE = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

ProcInfo = MODULE.ProcInfo
select_stale_launchers = MODULE.select_stale_launchers


def test_selects_detached_old_copilot_launcher():
    table = {
        100: ProcInfo(100, 90, 100, 80, "?", 200000, "node /usr/bin/copilot"),
        90: ProcInfo(90, 89, 90, 80, "?", 200001, "bash"),
        89: ProcInfo(89, 88, 89, 80, "?", 200002, "su"),
        88: ProcInfo(88, 1, 88, 80, "?", 200003, "sudo su"),
    }

    selected = select_stale_launchers(table, min_age_seconds=7200)

    assert [proc.pid for proc in selected] == [100]


def test_skips_interactive_and_active_executor_ancestry():
    table = {
        100: ProcInfo(100, 90, 100, 80, "pts/25", 200000, "node /usr/bin/copilot"),
        90: ProcInfo(90, 89, 90, 80, "pts/25", 200001, "-bash"),
        200: ProcInfo(200, 190, 200, 180, "?", 200000, "node /usr/bin/copilot"),
        190: ProcInfo(190, 180, 190, 180, "?", 200001, "bash scripts/agent-exec-next.sh pm-dungeoncrawler"),
        180: ProcInfo(180, 170, 180, 180, "?", 200002, "python3 orchestrator/run.py"),
    }

    selected = select_stale_launchers(table, min_age_seconds=7200)

    assert selected == []
