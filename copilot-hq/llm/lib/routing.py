"""
llm/lib/routing.py — Shared routing resolution for copilot-sessions-hq.

Used by:
  - llm/validate.sh     (inline Python heredocs)
  - llm/runner.py       (direct import)
  - scripts/agent-exec-next.sh  (inline Python heredoc with sys.path injection)

Public API:
  resolve_model_id(agent_id, routing, agents_yaml_path=None) -> str
  resolve_model_file(agent_id, routing, manifest, models_dir) -> Path | None
  load_yaml(path) -> dict
  parse_agents_yaml(path) -> dict[str, dict]
"""

from __future__ import annotations

import re
from pathlib import Path
from typing import Dict, Optional


def load_yaml(path: Path) -> dict:
    """Load a YAML file. Requires pyyaml."""
    import yaml  # intentional late import — callers handle ImportError
    return yaml.safe_load(path.read_text(encoding="utf-8")) or {}


def parse_agents_yaml(path: Path) -> Dict[str, dict]:
    """
    Parse org-chart/agents/agents.yaml into {agent_id: {role, paused}} dict.
    Uses regex line scanning (no yaml dep) to match existing org tooling pattern.
    """
    agents: Dict[str, dict] = {}
    if not path.exists():
        return agents

    cur_id: Optional[str] = None
    cur_role: Optional[str] = None
    cur_paused: bool = False

    for ln in path.read_text(encoding="utf-8", errors="ignore").splitlines():
        m = re.match(r"^\s*-\s+id:\s*(\S+)\s*$", ln)
        if m:
            if cur_id:
                agents[cur_id] = {"role": cur_role, "paused": cur_paused}
            cur_id, cur_role, cur_paused = m.group(1).strip(), None, False
            continue
        if cur_id:
            m = re.match(r"^\s*role:\s*(\S+)\s*$", ln)
            if m:
                cur_role = m.group(1).strip()
                continue
            m = re.match(r"^\s*paused:\s*(true|false)\s*$", ln, re.I)
            if m:
                cur_paused = m.group(1).lower() == "true"

    if cur_id:
        agents[cur_id] = {"role": cur_role, "paused": cur_paused}

    return agents


def resolve_model_id(
    agent_id: str,
    routing: dict,
    agents_yaml_path: Optional[Path] = None,
) -> str:
    """
    Resolve the model ID for an agent.

    Resolution order (first match wins):
      1. routing["agents"][agent_id]
      2. routing["roles"][role]  — role looked up from agents.yaml
      3. routing["default"]
      4. "copilot"  (hard fallback)

    Returns a route string (e.g. "copilot", "bedrock", "phi-3-mini").
    """
    # 1. Per-agent override
    model_id = (routing.get("agents") or {}).get(agent_id)
    if model_id:
        return model_id

    # 2. Role-based
    if agents_yaml_path:
        agents = parse_agents_yaml(agents_yaml_path)
        role = (agents.get(agent_id) or {}).get("role") or ""
        if role:
            model_id = (routing.get("roles") or {}).get(role)
            if model_id:
                return model_id

    # 3. Default
    return routing.get("default") or "copilot"


def resolve_model_file(
    agent_id: str,
    routing: dict,
    manifest: dict,
    models_dir: Path,
    agents_yaml_path: Optional[Path] = None,
) -> Optional[Path]:
    """
    Return the Path to the local model file for agent_id if it exists on disk,
    or None if the route points to a live backend such as Copilot/Bedrock.
    """
    model_id = resolve_model_id(agent_id, routing, agents_yaml_path)
    if not model_id or model_id == "copilot":
        return None

    for m in manifest.get("models", []):
        if m.get("id") == model_id:
            local = models_dir / m.get("filename", "")
            return local if local.exists() else None

    return None
