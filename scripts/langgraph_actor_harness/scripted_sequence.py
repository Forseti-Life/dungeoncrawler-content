from __future__ import annotations

import json
from pathlib import Path
from typing import Any


def scripted_config_path(repo_root: Path) -> Path:
    return repo_root / "config" / "harness" / "burasco-script-v1.json"


def load_scripted_testing_steps(repo_root: Path) -> tuple[dict[str, Any], ...]:
    config_path = scripted_config_path(repo_root)
    if not config_path.exists():
        raise RuntimeError(f"Scripted harness config not found: {config_path}")

    try:
        raw = json.loads(config_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise RuntimeError(f"Invalid scripted harness JSON config: {config_path}: {exc}") from exc

    if not isinstance(raw, dict):
        raise RuntimeError(f"Invalid scripted harness config payload: {config_path}")
    steps = raw.get("steps")
    random_transition_count = raw.get("random_nav_exit_transition_count")

    if not isinstance(steps, list):
        raise RuntimeError("Scripted harness config requires a 'steps' array.")
    if not isinstance(random_transition_count, int) or random_transition_count < 0:
        raise RuntimeError("Scripted harness config requires non-negative integer random_nav_exit_transition_count.")

    normalized_steps: list[dict[str, Any]] = []
    for index, step in enumerate(steps):
        if not isinstance(step, dict):
            raise RuntimeError(f"Invalid scripted step at index {index}: expected object.")
        step_id = str(step.get("id") or "").strip()
        tool_name = str(step.get("tool_name") or "").strip().lower()
        tool_payload = step.get("tool_payload")
        if step_id == "":
            raise RuntimeError(f"Invalid scripted step at index {index}: id is required.")
        if tool_name == "":
            raise RuntimeError(f"Invalid scripted step {step_id}: tool_name is required.")
        if not isinstance(tool_payload, dict):
            raise RuntimeError(f"Invalid scripted step {step_id}: tool_payload object is required.")
        normalized_steps.append(
            {
                "id": step_id,
                "tool_name": tool_name,
                "tool_payload": dict(tool_payload),
            }
        )

    random_steps = [
        {
            "id": f"random_nav_exit_transition_{idx:02d}",
            "step_type": "random_navigation_exit_transition",
            "tool_name": "transition",
        }
        for idx in range(1, random_transition_count + 1)
    ]
    return tuple(normalized_steps + random_steps)

