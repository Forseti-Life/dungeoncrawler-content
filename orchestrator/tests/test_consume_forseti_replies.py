import os
import shutil
import stat
import subprocess
import tempfile
import textwrap
import unittest
from pathlib import Path


REPO_ROOT = Path("/home/ubuntu/forseti.life")
SCRIPT_SRC = REPO_ROOT / "scripts" / "consume-forseti-replies.sh"
SITE_PATHS_SRC = REPO_ROOT / "scripts" / "lib" / "site-paths.sh"


def _write_executable(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8")
    path.chmod(path.stat().st_mode | stat.S_IXUSR | stat.S_IXGRP | stat.S_IXOTH)


def _build_temp_repo(root: Path) -> Path:
    (root / "scripts" / "lib").mkdir(parents=True, exist_ok=True)
    (root / "org-chart" / "agents").mkdir(parents=True, exist_ok=True)
    shutil.copy2(SCRIPT_SRC, root / "scripts" / "consume-forseti-replies.sh")
    shutil.copy2(SITE_PATHS_SRC, root / "scripts" / "lib" / "site-paths.sh")
    _write_executable(
        root / "scripts" / "is-agent-paused.sh",
        "#!/usr/bin/env bash\nprintf '%s\n' false\n",
    )
    (root / "org-chart" / "agents" / "agents.yaml").write_text(
        "- id: ceo-copilot-2\n",
        encoding="utf-8",
    )
    site_dir = root / "site" / "vendor" / "bin"
    site_dir.mkdir(parents=True, exist_ok=True)
    _write_executable(
        site_dir / "drush",
        textwrap.dedent(
            """\
            #!/usr/bin/env bash
            set -euo pipefail
            code="${*: -1}"
            if [[ "$code" == *'tableExists("copilot_agent_tracker_replies")'* ]]; then
              printf '%s' "${DRUSH_TABLE_EXISTS:-no}"
              exit 0
            fi
            if [[ "$code" == *'->select("copilot_agent_tracker_replies","r")'* ]]; then
              printf '%s' "${DRUSH_REPLY_JSON:-[]}"
              exit 0
            fi
            if [[ "$code" == *'->update("copilot_agent_tracker_replies")'* ]]; then
              exit 0
            fi
            exit 0
            """
        ),
    )
    return root / "site"


class TestConsumeForsetiReplies(unittest.TestCase):
    def test_skips_cleanly_when_tracker_table_missing(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            site_root = _build_temp_repo(root)
            env = os.environ.copy()
            env["FORSETI_SITE_DIR"] = str(site_root)
            env["DRUSH_TABLE_EXISTS"] = "no"

            proc = subprocess.run(
                ["bash", str(root / "scripts" / "consume-forseti-replies.sh")],
                cwd=root,
                env=env,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                check=False,
            )

            self.assertEqual(proc.returncode, 0, proc.stdout)
            self.assertIn("table missing", proc.stdout)
            self.assertFalse((root / "sessions").exists())

    def test_consumes_reply_into_temp_repo_root(self):
        with tempfile.TemporaryDirectory() as td:
            root = Path(td)
            site_root = _build_temp_repo(root)
            env = os.environ.copy()
            env["FORSETI_SITE_DIR"] = str(site_root)
            env["DRUSH_TABLE_EXISTS"] = "yes"
            env["DRUSH_REPLY_JSON"] = (
                '[{"id":1,"to_agent_id":"ceo-copilot-2","in_reply_to":"orig-item","message":"Please investigate.","created":0}]'
            )

            proc = subprocess.run(
                ["bash", str(root / "scripts" / "consume-forseti-replies.sh")],
                cwd=root,
                env=env,
                stdout=subprocess.PIPE,
                stderr=subprocess.STDOUT,
                text=True,
                check=False,
            )

            self.assertEqual(proc.returncode, 0, proc.stdout)
            self.assertIn("Consumed replies: 1", proc.stdout)

            inbox_dir = root / "sessions" / "ceo-copilot-2" / "inbox"
            items = list(inbox_dir.iterdir())
            self.assertEqual(len(items), 1)
            command = (items[0] / "command.md").read_text(encoding="utf-8")
            self.assertIn("Please investigate.", command)
            self.assertTrue((items[0] / "roi.txt").exists())


if __name__ == "__main__":
    unittest.main()
