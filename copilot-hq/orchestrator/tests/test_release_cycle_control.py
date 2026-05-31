import os
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import orchestrator.run as run


class TestReleaseCycleControl(unittest.TestCase):
    def test_release_cycle_step_skips_when_control_disabled(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            control = root / "tmp" / "release-cycle-control.json"
            control.parent.mkdir(parents=True, exist_ok=True)
            control.write_text(
                '{"enabled": false, "reason": "Pause release automation", "updated_by": "test"}\n',
                encoding="utf-8",
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                with patch.dict(os.environ, {"RELEASE_CYCLE_CONTROL_FILE": str(control)}):
                    log = []
                    run._release_cycle_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [])
            self.assertEqual(
                log,
                [
                    {
                        "step": "release_cycle",
                        "status": "paused",
                        "state_file": str(control),
                        "reason": "Pause release automation",
                    }
                ],
            )

    def test_coordinated_push_skips_when_control_disabled(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            control = root / "tmp" / "release-cycle-control.json"
            control.parent.mkdir(parents=True, exist_ok=True)
            control.write_text(
                '{"enabled": false, "reason": "Pause release automation", "updated_by": "test"}\n',
                encoding="utf-8",
            )

            old_root = run.REPO_ROOT
            old_run = run._run
            run.REPO_ROOT = root
            calls = []

            def fake_run(cmd, timeout=0):
                calls.append(cmd)
                return 0, ""

            run._run = fake_run
            try:
                with patch.dict(os.environ, {"RELEASE_CYCLE_CONTROL_FILE": str(control)}):
                    log = []
                    run._coordinated_push_step(log)
            finally:
                run.REPO_ROOT = old_root
                run._run = old_run

            self.assertEqual(calls, [])
            self.assertEqual(
                log,
                [
                    {
                        "step": "coordinated_push",
                        "status": "paused",
                        "state_file": str(control),
                        "reason": "Pause release automation",
                    }
                ],
            )


if __name__ == "__main__":
    unittest.main()
