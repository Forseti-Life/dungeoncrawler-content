import os
import subprocess
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[1] / "hq-health-heartbeat.sh"


def _write_executable(path: Path, content: str) -> None:
    path.write_text(content, encoding="utf-8")
    path.chmod(0o755)


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    scripts_dir = root / "scripts"
    scripts_dir.mkdir(parents=True)

    _write_executable(
        scripts_dir / "orchestrator-loop.sh",
        """#!/usr/bin/env bash
set -euo pipefail
case "${1:-}" in
  verify) exit 1 ;;
  start) exit 1 ;;
  *) exit 0 ;;
esac
""",
    )
    _write_executable(
        scripts_dir / "agent-exec-loop.sh",
        """#!/usr/bin/env bash
set -euo pipefail
case "${1:-}" in
  verify) exit 1 ;;
  stop) exit 0 ;;
  *) exit 0 ;;
esac
""",
    )
    _write_executable(
        scripts_dir / "publish-forseti-agent-tracker-loop.sh",
        """#!/usr/bin/env bash
exit 0
""",
    )

    board_conf = root / "org-chart" / "board.conf"
    board_conf.parent.mkdir(parents=True)
    board_conf.write_text(
        'BOARD_EMAIL="board@example.com"\nHQ_FROM_EMAIL="hq@example.com"\n',
        encoding="utf-8",
    )
    return root


def test_critical_failure_email_is_throttled_to_once_per_hour(tmp_path):
    root = _make_root(tmp_path)
    sendmail_log = tmp_path / "sendmail.log"
    mock_sendmail = tmp_path / "sendmail"
    _write_executable(
        mock_sendmail,
        """#!/usr/bin/env bash
set -euo pipefail
cat >> "${MOCK_SENDMAIL_LOG:?}"
printf '\\n===END===\\n' >> "${MOCK_SENDMAIL_LOG:?}"
""",
    )

    failure_count = tmp_path / "failures.count"
    failure_count.write_text("2\n", encoding="utf-8")
    alert_log = tmp_path / "alert.log"
    heartbeat_log = tmp_path / "heartbeat.log"
    email_state = tmp_path / "critical-email.last"

    env = {
        **os.environ,
        "HQ_ROOT_DIR": str(root),
        "SENDMAIL_BIN": str(mock_sendmail),
        "MOCK_SENDMAIL_LOG": str(sendmail_log),
        "FAILURE_COUNT_FILE": str(failure_count),
        "ALERT_LOG": str(alert_log),
        "HEARTBEAT_LOG": str(heartbeat_log),
        "CRITICAL_EMAIL_STATE_FILE": str(email_state),
        "CRITICAL_EMAIL_COOLDOWN_SECONDS": "3600",
    }

    first = subprocess.run(
        ["bash", str(SCRIPT)],
        cwd=root,
        env=env,
        capture_output=True,
        text=True,
        check=False,
    )
    second = subprocess.run(
        ["bash", str(SCRIPT)],
        cwd=root,
        env=env,
        capture_output=True,
        text=True,
        check=False,
    )

    assert first.returncode == 1
    assert second.returncode == 1
    assert sendmail_log.read_text(encoding="utf-8").count("Subject:") == 1
    alert_text = alert_log.read_text(encoding="utf-8")
    assert "CRITICAL EMAIL SENT" in alert_text
    assert "CRITICAL EMAIL SUPPRESSED" in alert_text
    assert failure_count.read_text(encoding="utf-8").strip() == "4"
