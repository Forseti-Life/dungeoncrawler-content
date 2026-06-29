#!/usr/bin/env python3
"""
llm/bedrock_runner.py — Bedrock live inference shim for copilot-sessions-hq agents.

Drop-in replacement for the live backend side of:
  agent-exec-next.sh -> run_bedrock()

Unlike scripts/bedrock-assist.sh, this runner does not depend on site-local Drupal
services. It uses the HQ session cache model so backend choice does not change
seat/runtime semantics.
"""

from __future__ import annotations

import argparse
import json
import os
import sys
import time
from pathlib import Path
from typing import List

REPO_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO_ROOT))

from orchestrator.langgraph_llm_usage import append_usage_record, estimate_tokens  # noqa: E402
from llm.runner import load_session, save_session  # noqa: E402


DEFAULT_MODEL_ID = "us.anthropic.claude-sonnet-4-6"
DEFAULT_MAX_TOKENS = 2048
DEFAULT_REGION = os.environ.get("AWS_REGION") or os.environ.get("AWS_DEFAULT_REGION") or "us-east-1"


def _build_messages(history: List[dict], prompt: str) -> List[dict]:
    messages: List[dict] = []
    for msg in history:
        role = str(msg.get("role") or "").strip().lower()
        content = str(msg.get("content") or "")
        if role not in {"user", "assistant"} or not content.strip():
            continue
        messages.append({"role": role, "content": [{"type": "text", "text": content}]})
    messages.append({"role": "user", "content": [{"type": "text", "text": prompt}]})
    return messages


def _extract_text(response: dict) -> str:
    output = (((response or {}).get("output") or {}).get("message") or {})
    content = output.get("content") or []
    parts: List[str] = []
    for block in content:
        text = block.get("text")
        if text:
            parts.append(str(text))
    return "\n".join(parts).strip()


def _extract_invoke_model_text(body: dict) -> str:
    content = body.get("content") or []
    parts: List[str] = []
    for block in content:
        text = block.get("text")
        if text:
            parts.append(str(text))
    return "\n".join(parts).strip()


def run_bedrock(
    agent_id: str,
    session_id: str,
    prompt: str,
    *,
    model_id: str,
    max_tokens: int,
    no_history: bool,
    region_name: str,
    source: str,
    operation: str,
) -> str:
    try:
        import boto3
        from botocore.exceptions import BotoCoreError, ClientError
    except ImportError as exc:  # pragma: no cover
        raise RuntimeError("boto3/botocore are required for Bedrock runner") from exc

    history = [] if no_history else load_session(session_id)
    messages = _build_messages(history, prompt)

    client = boto3.client("bedrock-runtime", region_name=region_name)
    start_time = time.time()
    api_name = "converse" if hasattr(client, "converse") else "invoke_model"
    try:
        if hasattr(client, "converse"):
            response = client.converse(
                modelId=model_id,
                messages=messages,
                inferenceConfig={
                    "maxTokens": max_tokens,
                    "temperature": 0,
                },
            )
            usage = response.get("usage") or {}
            text = _extract_text(response)
        else:
            payload = {
                "anthropic_version": "bedrock-2023-05-31",
                "max_tokens": max_tokens,
                "temperature": 0,
                "messages": messages,
            }
            response = client.invoke_model(
                modelId=model_id,
                body=json.dumps(payload),
                contentType="application/json",
                accept="application/json",
            )
            raw_body = response.get("body")
            if hasattr(raw_body, "read"):
                raw_body = raw_body.read()
            if isinstance(raw_body, bytes):
                raw_body = raw_body.decode("utf-8")
            parsed_body = json.loads(raw_body or "{}")
            usage = parsed_body.get("usage") or {}
            text = _extract_invoke_model_text(parsed_body)
    except (BotoCoreError, ClientError, ValueError) as exc:
        duration_ms = int((time.time() - start_time) * 1000)
        append_usage_record({
            "backend": "bedrock",
            "agent_id": agent_id,
            "session_id": session_id,
            "source": source,
            "phase": "primary",
            "status": "failed",
            "success": False,
            "model_id": model_id,
            "region": region_name,
            "api": api_name,
            "duration_ms": duration_ms,
            "prompt_chars": len(prompt),
            "response_chars": 0,
            "prompt_tokens_est": estimate_tokens(prompt),
            "response_tokens_est": 0,
            "token_visibility": "estimated",
            "error": str(exc),
            "operation": operation,
        })
        raise RuntimeError(f"Bedrock inference failed: {exc}") from exc

    duration_ms = int((time.time() - start_time) * 1000)
    exact_input_tokens = usage.get("inputTokens")
    exact_output_tokens = usage.get("outputTokens")
    token_visibility = "exact" if isinstance(exact_input_tokens, int) and isinstance(exact_output_tokens, int) else "estimated"

    if not text:
        append_usage_record({
            "backend": "bedrock",
            "agent_id": agent_id,
            "session_id": session_id,
            "source": source,
            "phase": "primary",
            "status": "empty",
            "success": False,
            "model_id": model_id,
            "region": region_name,
            "api": api_name,
            "duration_ms": duration_ms,
            "prompt_chars": len(prompt),
            "response_chars": 0,
            "prompt_tokens_est": estimate_tokens(prompt),
            "response_tokens_est": 0,
            "exact_input_tokens": exact_input_tokens if isinstance(exact_input_tokens, int) else None,
            "exact_output_tokens": exact_output_tokens if isinstance(exact_output_tokens, int) else None,
            "token_visibility": token_visibility,
            "error": "Bedrock returned an empty response",
            "operation": operation,
        })
        raise RuntimeError("Bedrock returned an empty response")

    append_usage_record({
        "backend": "bedrock",
        "agent_id": agent_id,
        "session_id": session_id,
        "source": source,
        "phase": "primary",
        "status": "completed",
        "success": True,
        "model_id": model_id,
        "region": region_name,
        "api": api_name,
        "duration_ms": duration_ms,
        "prompt_chars": len(prompt),
        "response_chars": len(text),
        "prompt_tokens_est": estimate_tokens(prompt),
        "response_tokens_est": estimate_tokens(text),
        "exact_input_tokens": exact_input_tokens if isinstance(exact_input_tokens, int) else None,
        "exact_output_tokens": exact_output_tokens if isinstance(exact_output_tokens, int) else None,
        "token_visibility": token_visibility,
        "operation": operation,
    })

    if not no_history:
        history.append({"role": "user", "content": prompt})
        history.append({"role": "assistant", "content": text})
        save_session(session_id, history)
    return text


def main() -> None:
    parser = argparse.ArgumentParser(description="Bedrock live inference shim for HQ agents.")
    parser.add_argument("--session", default="default", help="Session ID for conversation history.")
    parser.add_argument("--agent-id", default="", help="Agent ID associated with this call.")
    parser.add_argument("--prompt", default="", help="Prompt text. Reads from stdin if omitted.")
    parser.add_argument("--model-id", default=DEFAULT_MODEL_ID, help="Bedrock model ID.")
    parser.add_argument("--max-tokens", type=int, default=DEFAULT_MAX_TOKENS, help="Maximum output tokens.")
    parser.add_argument("--region", default=DEFAULT_REGION, help="AWS region for Bedrock runtime.")
    parser.add_argument("--no-history", action="store_true", help="Ignore and do not write session history.")
    parser.add_argument("--source", default="llm/bedrock_runner.py", help="Telemetry source identifier.")
    parser.add_argument("--operation", default="langgraph_agent_exec", help="Telemetry operation label.")
    args = parser.parse_args()

    prompt = args.prompt or sys.stdin.read()
    if not prompt.strip():
        print("ERROR: prompt is required", file=sys.stderr)
        raise SystemExit(1)

    try:
        text = run_bedrock(
            args.agent_id,
            args.session,
            prompt,
            model_id=args.model_id,
            max_tokens=max(1, int(args.max_tokens)),
            no_history=bool(args.no_history),
            region_name=str(args.region),
            source=str(args.source),
            operation=str(args.operation),
        )
    except RuntimeError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        raise SystemExit(2) from exc

    print(text)


if __name__ == "__main__":
    main()
