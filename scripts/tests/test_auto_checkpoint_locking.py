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
    result = subprocess.run(
        ["bash", str(AUTO_CHECKPOINT)],
        capture_output=True,
        text=True,
        env={**os.environ},
        check=False,
    )

    assert result.returncode == 0
    assert "DISABLED: auto-checkpoint is permanently turned off" in result.stdout


def test_auto_checkpoint_blocks_cleanly_on_index_lock(tmp_path):
    result = subprocess.run(
        ["bash", str(AUTO_CHECKPOINT)],
        capture_output=True,
        text=True,
        env={**os.environ},
        check=False,
    )

    assert result.returncode == 0
    assert "DISABLED: auto-checkpoint is permanently turned off" in result.stdout
