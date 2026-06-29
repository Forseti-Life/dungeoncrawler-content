import os
import subprocess
import time
from pathlib import Path


AUTO_CHECKPOINT = Path(__file__).resolve().parents[1] / "auto-checkpoint.sh"


def _init_repo(root: Path) -> None:
    subprocess.run(["git", "init", "-b", "main"], cwd=root, check=True, capture_output=True, text=True)
    subprocess.run(["git", "config", "user.email", "ceo@example.com"], cwd=root, check=True, capture_output=True, text=True)
    subprocess.run(["git", "config", "user.name", "CEO Test"], cwd=root, check=True, capture_output=True, text=True)


def test_auto_checkpoint_skips_when_lock_is_held(tmp_path):
    repo = tmp_path / "repo"
    repo.mkdir()
    _init_repo(repo)

    lockfile = tmp_path / "auto-checkpoint.lock"
    holder = subprocess.Popen(
        ["bash", "-lc", f"exec 9>'{lockfile}' && flock -n 9 && sleep 5"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
    )
    try:
        time.sleep(0.2)
        result = subprocess.run(
            ["bash", str(AUTO_CHECKPOINT)],
            capture_output=True,
            text=True,
            env={
                **os.environ,
                "AUTO_CHECKPOINT_REPOS": str(repo),
                "AUTO_CHECKPOINT_LOCKFILE": str(lockfile),
            },
            check=False,
        )
    finally:
        holder.terminate()
        holder.wait(timeout=5)

    assert result.returncode == 0
    assert "SKIP (auto-checkpoint already running)" in result.stdout


def test_auto_checkpoint_blocks_cleanly_on_index_lock(tmp_path):
    repo = tmp_path / "repo"
    repo.mkdir()
    _init_repo(repo)
    (repo / "tracked.txt").write_text("dirty\n", encoding="utf-8")
    (repo / ".git" / "index.lock").write_text("locked\n", encoding="utf-8")

    result = subprocess.run(
        ["bash", str(AUTO_CHECKPOINT)],
        capture_output=True,
        text=True,
        env={
            **os.environ,
            "AUTO_CHECKPOINT_REPOS": str(repo),
            "AUTO_CHECKPOINT_LOCKFILE": str(tmp_path / "auto-checkpoint.lock"),
        },
        check=False,
    )

    assert result.returncode == 0
    assert f"BLOCKED (git index.lock present): {repo}" in result.stdout
