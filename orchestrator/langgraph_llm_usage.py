from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

REPO_ROOT = Path(__file__).resolve().parent.parent
DEFAULT_USAGE_FILE = REPO_ROOT / "inbox" / "responses" / "langgraph-llm-usage.jsonl"
DEFAULT_MAX_LINES = 5000


def now_iso() -> str:
    return datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")


def estimate_tokens(text: str) -> int:
    return max(0, (len(text) + 3) // 4)


def usage_file_path() -> Path:
    override = os.environ.get("LANGGRAPH_LLM_USAGE_FILE", "").strip()
    if override:
        return Path(override).expanduser()
    return DEFAULT_USAGE_FILE


def _normalize_record(record: dict[str, Any]) -> dict[str, Any]:
    normalized = dict(record)
    normalized.setdefault("schema_version", 1)
    normalized.setdefault("ts", now_iso())
    return normalized


def append_usage_record(record: dict[str, Any]) -> Path:
    target = usage_file_path()
    target.parent.mkdir(parents=True, exist_ok=True)
    normalized = _normalize_record(record)
    with target.open("a", encoding="utf-8") as handle:
        handle.write(json.dumps(normalized, ensure_ascii=False) + "\n")

    max_lines_raw = os.environ.get("LANGGRAPH_LLM_USAGE_MAX_LINES", "").strip()
    max_lines = int(max_lines_raw) if max_lines_raw.isdigit() else DEFAULT_MAX_LINES
    if max_lines > 0:
        try:
            lines = target.read_text(encoding="utf-8").splitlines()
            if len(lines) > max_lines:
                target.write_text("\n".join(lines[-max_lines:]) + "\n", encoding="utf-8")
        except OSError:
            pass

    return target


def _build_record_from_args(args: argparse.Namespace) -> dict[str, Any]:
    record: dict[str, Any] = {
        "backend": args.backend,
        "agent_id": args.agent_id,
        "session_id": args.session_id,
        "source": args.source,
        "phase": args.phase,
        "status": args.status,
        "success": str(args.success).lower() == "true",
        "model_id": args.model_id,
        "region": args.region,
        "api": args.api,
        "duration_ms": args.duration_ms,
        "prompt_chars": args.prompt_chars,
        "response_chars": args.response_chars,
        "prompt_tokens_est": args.prompt_tokens_est,
        "response_tokens_est": args.response_tokens_est,
        "exact_input_tokens": args.exact_input_tokens,
        "exact_output_tokens": args.exact_output_tokens,
        "token_visibility": args.token_visibility,
        "rate_limited": str(args.rate_limited).lower() == "true",
        "error": args.error,
        "note": args.note,
        "operation": args.operation,
    }
    return {key: value for key, value in record.items() if value not in ("", None)}


def main() -> int:
    parser = argparse.ArgumentParser(description="Append LangGraph LLM usage telemetry.")
    parser.add_argument("--stdin-json", action="store_true", help="Read the full record as JSON from stdin.")
    parser.add_argument("--backend", default="")
    parser.add_argument("--agent-id", default="")
    parser.add_argument("--session-id", default="")
    parser.add_argument("--source", default="")
    parser.add_argument("--phase", default="")
    parser.add_argument("--status", default="")
    parser.add_argument("--success", default="true")
    parser.add_argument("--model-id", default="")
    parser.add_argument("--region", default="")
    parser.add_argument("--api", default="")
    parser.add_argument("--duration-ms", type=int, default=0)
    parser.add_argument("--prompt-chars", type=int, default=0)
    parser.add_argument("--response-chars", type=int, default=0)
    parser.add_argument("--prompt-tokens-est", type=int, default=0)
    parser.add_argument("--response-tokens-est", type=int, default=0)
    parser.add_argument("--exact-input-tokens", type=int)
    parser.add_argument("--exact-output-tokens", type=int)
    parser.add_argument("--token-visibility", default="")
    parser.add_argument("--rate-limited", default="false")
    parser.add_argument("--error", default="")
    parser.add_argument("--note", default="")
    parser.add_argument("--operation", default="")
    args = parser.parse_args()

    if args.stdin_json:
      record = json.load(sys.stdin)
      if not isinstance(record, dict):
          raise SystemExit(2)
    else:
      record = _build_record_from_args(args)

    append_usage_record(record)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
