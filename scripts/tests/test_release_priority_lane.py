import subprocess
from pathlib import Path


LIB = Path(__file__).resolve().parents[1] / "lib" / "release-priority.sh"


def _run_bash(root: Path, script: str) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        ["bash", "-lc", script],
        cwd=root,
        capture_output=True,
        text=True,
        check=False,
    )


def test_signoff_item_gets_release_blocker_lane(tmp_path):
    root = tmp_path / "repo"
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
        "20260412-dungeoncrawler-release-y\n",
        encoding="utf-8",
    )
    item = root / "sessions" / "pm-dungeoncrawler" / "inbox" / "20260429-signoff-reminder-20260412-dungeoncrawler-release-y"
    item.mkdir(parents=True)
    (item / "README.md").write_text(
        "Release `20260412-dungeoncrawler-release-y` is blocked pending PM signoff.\n",
        encoding="utf-8",
    )

    result = _run_bash(
        root,
        f'source "{LIB}" && release_priority__lane_for_item "{item}" "$(basename "{item}")"',
    )

    assert result.returncode == 0, result.stderr
    assert result.stdout.strip() == "0"


def test_backlog_item_stays_in_normal_lane(tmp_path):
    root = tmp_path / "repo"
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
        "20260412-dungeoncrawler-release-y\n",
        encoding="utf-8",
    )
    item = root / "sessions" / "pm-dungeoncrawler" / "inbox" / "20260428-backlog-intake-dc-apg-class-witch"
    item.mkdir(parents=True)
    (item / "README.md").write_text(
        "Backlog intake for a future content slice.\n",
        encoding="utf-8",
    )

    result = _run_bash(
        root,
        f'source "{LIB}" && release_priority__lane_for_item "{item}" "$(basename "{item}")"',
    )

    assert result.returncode == 0, result.stderr
    assert result.stdout.strip() == "1"


def test_release_blocker_lane_sorts_ahead_of_higher_roi_backlog(tmp_path):
    root = tmp_path / "repo"
    inbox = root / "sessions" / "pm-dungeoncrawler" / "inbox"
    inbox.mkdir(parents=True)
    (root / "tmp" / "release-cycle-active").mkdir(parents=True)
    (root / "tmp" / "release-cycle-active" / "dungeoncrawler.release_id").write_text(
        "20260412-dungeoncrawler-release-y\n",
        encoding="utf-8",
    )

    blocker = inbox / "20260429-code-review-followup-20260412-dungeoncrawler-release-y"
    blocker.mkdir()
    (blocker / "README.md").write_text(
        "Release `20260412-dungeoncrawler-release-y` still has a blocking code review follow-up.\n",
        encoding="utf-8",
    )
    (blocker / "roi.txt").write_text("249\n", encoding="utf-8")

    backlog = inbox / "20260428-backlog-intake-dc-apg-class-witch"
    backlog.mkdir()
    (backlog / "README.md").write_text("Backlog intake item.\n", encoding="utf-8")
    (backlog / "roi.txt").write_text("1994\n", encoding="utf-8")

    result = _run_bash(
        root,
        f'''
source "{LIB}"
find "{inbox}" -mindepth 1 -maxdepth 1 -type d | while IFS= read -r dir; do
  name="$(basename "$dir")"
  roi="$(head -n 1 "$dir/roi.txt" | tr -cd '0-9')"
  lane="$(release_priority__lane_for_item "$dir" "$name")"
  printf '%s\\t%s\\t%s\\n' "$lane" "$roi" "$name"
done | sort -t $'\\t' -k1,1n -k2,2nr -k3,3 | head -1 | awk -F'\\t' '{{print $3}}'
''',
    )

    assert result.returncode == 0, result.stderr
    assert result.stdout.strip() == blocker.name
