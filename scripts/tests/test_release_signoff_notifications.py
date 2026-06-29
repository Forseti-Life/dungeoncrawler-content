import json
import os
import subprocess
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "release-signoff.sh"
GATE2_HELPER = Path(__file__).resolve().parents[2] / "scripts" / "lib" / "gate2_artifacts.py"


def _write(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def _make_root(tmp_path: Path) -> Path:
    root = tmp_path / "hq"
    _write(
        root / "scripts" / "check-code-review-routing.py",
        "#!/usr/bin/env python3\nprint('OK: no unresolved findings')\n",
    )
    (root / "scripts" / "check-code-review-routing.py").chmod(0o755)
    _write(
        root / "scripts" / "lib" / "gate2_artifacts.py",
        GATE2_HELPER.read_text(encoding="utf-8"),
    )
    _write(
        root / "org-chart" / "products" / "product-teams.json",
        json.dumps(
            {
                "teams": [
                    {
                        "id": "forseti",
                        "aliases": ["forseti.life"],
                        "site": "forseti.life",
                        "pm_agent": "pm-forseti",
                        "qa_agent": "qa-forseti",
                        "active": True,
                        "release_preflight_enabled": True,
                        "coordinated_release_default": True,
                        "release_dependencies": ["dungeoncrawler"],
                    },
                    {
                        "id": "dungeoncrawler",
                        "aliases": ["dungeoncrawler"],
                        "site": "dungeoncrawler",
                        "pm_agent": "pm-dungeoncrawler",
                        "qa_agent": "qa-dungeoncrawler",
                        "active": True,
                        "release_preflight_enabled": True,
                        "coordinated_release_default": True,
                        "release_dependencies": [],
                    },
                ]
            }
        ),
    )
    _write(
        root / "org-chart" / "board.conf",
        "BOARD_EMAIL=board@example.com\nHQ_FROM_EMAIL=hq@example.com\nHQ_SITE_NAME='HQ Test'\n",
    )
    _write(
        root / "sessions" / "qa-forseti" / "outbox" / "gate2-approve-u.md",
        "20260412-forseti-release-u\nAPPROVE\n",
    )
    _write(
        root / "tmp" / "release-cycle-active" / "forseti.release_id",
        "20260412-forseti-release-u\n",
    )
    _write(
        root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id",
        "20260412-dungeoncrawler-release-v\n",
    )
    _write(
        root / "sessions" / "pm-dungeoncrawler" / "artifacts" / "release-signoffs" / "20260412-dungeoncrawler-release-v.md",
        "# PM signoff\n",
    )
    return root


def _make_sendmail(tmp_path: Path):
    log = tmp_path / "sendmail.log"
    sendmail = tmp_path / "sendmail"
    sendmail.write_text(
        "#!/usr/bin/env bash\ncat >> \"$MOCK_SENDMAIL_LOG\"\n",
        encoding="utf-8",
    )
    sendmail.chmod(0o755)
    return sendmail, log


def test_release_signoff_only_sends_email_when_push_ready_is_new(tmp_path):
    root = _make_root(tmp_path)
    sendmail, log = _make_sendmail(tmp_path)
    env = os.environ.copy()
    env.update(
        {
            "HQ_ROOT_DIR": str(root),
            "SENDMAIL_BIN": str(sendmail),
            "MOCK_SENDMAIL_LOG": str(log),
        }
    )

    first = subprocess.run(
        ["bash", str(SCRIPT), "forseti.life", "20260412-forseti-release-u"],
        cwd=root,
        capture_output=True,
        text=True,
        env=env,
        check=False,
    )
    assert first.returncode == 0, first.stderr
    assert "queued push-ready item for pm-forseti" in first.stdout
    assert "Board notification sent" in first.stdout
    assert log.read_text(encoding="utf-8").count("Subject:") == 1

    second = subprocess.run(
        ["bash", str(SCRIPT), "dungeoncrawler", "20260412-forseti-release-u"],
        cwd=root,
        capture_output=True,
        text=True,
        env=env,
        check=False,
    )
    assert second.returncode == 0, second.stderr
    assert "SIGNED_OFF: pm-dungeoncrawler" in second.stdout
    assert "Board notification skipped" in second.stdout
    assert log.read_text(encoding="utf-8").count("Subject:") == 1
