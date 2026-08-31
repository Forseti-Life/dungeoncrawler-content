from __future__ import annotations

import os


FORBIDDEN_ACTOR_RUNTIME_ENV_VARS: tuple[str, ...] = (
    "GITHUB_TOKEN",
    "GH_TOKEN",
    "AWS_ACCESS_KEY_ID",
    "AWS_SECRET_ACCESS_KEY",
    "AWS_SESSION_TOKEN",
    "GOOGLE_APPLICATION_CREDENTIALS",
)

ALLOWED_ACTOR_RUNTIME_ENV_VARS: tuple[str, ...] = (
    "PATH",
    "HOME",
    "LANG",
    "LC_ALL",
    "TERM",
    "USER",
    "LOGNAME",
    "SHELL",
    "PWD",
    "PYTHONPATH",
    "VIRTUAL_ENV",
    "COPILOT_HQ_ROOT",
    "HARNESS_LLM_BACKEND",
    "HARNESS_LLM_MODEL_ID",
    "HARNESS_DECIDER_TIMEOUT_SEC",
    "HARNESS_DECIDER_MAX_TOKENS",
    "HQ_AGENTIC_BACKEND",
    "DEEPSEEK_API_KEY",
    "DEEPSEEK_BASE_URL",
    "DEEPSEEK_MODEL",
)


def enforce_actor_runtime_secret_boundary() -> None:
    present = [name for name in FORBIDDEN_ACTOR_RUNTIME_ENV_VARS if (os.environ.get(name) or "").strip() != ""]
    if present:
        raise RuntimeError(
            "actor_runtime_secret_boundary_violation:"
            + ",".join(sorted(present))
        )


def build_actor_runtime_env() -> dict[str, str]:
    env: dict[str, str] = {}
    for key in ALLOWED_ACTOR_RUNTIME_ENV_VARS:
        value = os.environ.get(key)
        if value is None:
            continue
        value = value.strip() if isinstance(value, str) else str(value)
        if value == "":
            continue
        env[key] = value
    return env

