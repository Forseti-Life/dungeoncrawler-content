"""Configuration — loaded from environment or /etc/forseti-meshd/config.env."""

import os
import secrets
from functools import lru_cache
from pathlib import Path

from dotenv import load_dotenv

# Load private config if present (not in synced config)
_env_path = Path("/etc/forseti-meshd/config.env")
if _env_path.exists():
    load_dotenv(_env_path)
else:
    load_dotenv()


class Settings:
    database_url: str = os.getenv(
        "MESHD_DATABASE_URL",
        "postgresql://forseti_mesh:forseti_mesh_dev@localhost/forseti_mesh",
    )
    host: str = os.getenv("MESHD_HOST", "127.0.0.1")
    port: int = int(os.getenv("MESHD_PORT", "8765"))
    base_url: str = os.getenv("MESHD_BASE_URL", "https://forseti.life")
    # Installation identity (generated on first run, persisted in DB)
    log_level: str = os.getenv("MESHD_LOG_LEVEL", "info")
    # Replay window: reject messages with timestamp outside ±N seconds
    replay_window_seconds: int = int(os.getenv("MESHD_REPLAY_WINDOW", "300"))
    # Key material dir (private, not synced config)
    key_dir: str = os.getenv("MESHD_KEY_DIR", "/etc/forseti-meshd/keys")
    # Mission attestation version for this installation
    mission_version: str = os.getenv("MESHD_MISSION_VERSION", "1.0")
    # Admin API key (required for mutating admin endpoints)
    admin_api_key: str = os.getenv("MESHD_ADMIN_API_KEY", "")


@lru_cache
def get_settings() -> Settings:
    return Settings()
