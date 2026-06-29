import json
from datetime import datetime, timezone
from pathlib import Path

from orchestrator.release_cycle import run_release_cycle_step


def test_release_cycle_stays_idle_without_actionable_work(tmp_path):
    root = tmp_path / "hq"
    teams_dir = root / "org-chart" / "products"
    teams_dir.mkdir(parents=True)
    (teams_dir / "product-teams.json").write_text(
        json.dumps(
            {
                "teams": [
                    {
                        "id": "forseti",
                        "label": "Forseti",
                        "site": "forseti.life",
                        "pm_agent": "pm-forseti",
                        "qa_agent": "qa-forseti",
                        "ba_agent": "ba-forseti",
                        "active": True,
                        "release_preflight_enabled": True,
                    }
                ]
            }
        ),
        encoding="utf-8",
    )
    (root / "features").mkdir(parents=True)
    active_dir = root / "tmp" / "release-cycle-active"
    active_dir.mkdir(parents=True)

    log: list[dict] = []
    run_release_cycle_step(log, root)

    today = datetime.now(timezone.utc).strftime("%Y%m%d")
    assert not (active_dir / "forseti.release_id").exists()
    assert (active_dir / "forseti.next_release_id").read_text().strip() == f"{today}-forseti-release"
    assert not (active_dir / "forseti.started_at").exists()
    assert log == [
        {
            "step": "release_cycle",
            "teams": [
                {
                    "team": "forseti",
                    "action": "idle_waiting_for_work",
                    "current": "",
                    "next": f"{today}-forseti-release",
                    "scoped_count": 0,
                    "ready_backlog_count": 0,
                }
            ],
        }
    ]
