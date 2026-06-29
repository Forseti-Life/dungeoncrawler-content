#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from typing import Sequence
from urllib import error as urllib_error
from urllib import request as urllib_request

REPO_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO_ROOT))

from orchestrator.langgraph_llm_usage import append_usage_record, estimate_tokens  # noqa: E402


def _python_bin() -> str:
    venv_python = REPO_ROOT / "llm" / ".venv" / "bin" / "python3"
    if venv_python.exists():
        return str(venv_python)
    explicit = (os.environ.get("LLM_PYTHON_BIN") or "").strip()
    if explicit and Path(explicit).exists():
        return explicit
    return sys.executable or "python3"


def _detect_copilot_bin(explicit: str) -> str:
    if explicit:
        return explicit
    for candidate in (
        os.environ.get("COPILOT_BIN") or "",
        subprocess.run(["bash", "-lc", "command -v copilot 2>/dev/null || true"], capture_output=True, text=True).stdout.strip(),
        str(Path.home() / ".npm-global" / "bin" / "copilot"),
    ):
        if candidate and Path(candidate).exists():
            return candidate
    return ""


def _run_command(cmd: Sequence[str], *, timeout_sec: int, cwd: Path) -> tuple[int, str, bool]:
    try:
        proc = subprocess.run(
            list(cmd),
            cwd=str(cwd),
            stdout=subprocess.PIPE,
            stderr=subprocess.STDOUT,
            text=True,
            timeout=timeout_sec,
            check=False,
        )
        return proc.returncode, proc.stdout or "", False
    except subprocess.TimeoutExpired as exc:
        output = ""
        if exc.stdout:
            output += exc.stdout if isinstance(exc.stdout, str) else exc.stdout.decode("utf-8", errors="ignore")
        if exc.stderr:
            output += exc.stderr if isinstance(exc.stderr, str) else exc.stderr.decode("utf-8", errors="ignore")
        if output:
            output += "\n"
        output += f"TIMEOUT after {timeout_sec}s"
        return 124, output, True


def _log_usage(
    *,
    backend: str,
    agent_id: str,
    session_id: str,
    source: str,
    operation: str,
    model_id: str,
    duration_ms: int,
    prompt: str,
    response: str,
    rc: int,
    timeout: bool,
) -> None:
    success = rc == 0 and bool(response.strip())
    if timeout:
        status = "timeout"
        error = f"TIMEOUT after rc={rc}"
    elif rc != 0:
        status = "failed"
        error = f"command exited rc={rc}"
    elif not response.strip():
        status = "empty"
        error = "empty response"
    else:
        status = "completed"
        error = ""

    append_usage_record(
        {
            "backend": backend,
            "agent_id": agent_id,
            "session_id": session_id,
            "source": source,
            "phase": "primary",
            "status": status,
            "success": success,
            "model_id": model_id or None,
            "duration_ms": duration_ms,
            "prompt_chars": len(prompt),
            "response_chars": len(response),
            "prompt_tokens_est": estimate_tokens(prompt),
            "response_tokens_est": estimate_tokens(response),
            "token_visibility": "estimated",
            "error": error or None,
            "operation": operation,
        }
    )


def _run_copilot_chat(args: argparse.Namespace) -> int:
    copilot_bin = _detect_copilot_bin(args.copilot_bin)
    if not copilot_bin:
        print("ERROR: copilot CLI not found", file=sys.stderr)
        return 2

    cmd = [copilot_bin, "--resume", args.session, "--silent"]
    if args.allow_all:
        cmd.append("--allow-all")
    if args.model_id:
        cmd.extend(["--model", args.model_id])
    cmd.extend(["-p", args.prompt])

    start = time.time()
    rc, output, timed_out = _run_command(cmd, timeout_sec=args.timeout_sec, cwd=REPO_ROOT)
    duration_ms = int((time.time() - start) * 1000)
    _log_usage(
        backend="copilot",
        agent_id=args.agent_id,
        session_id=args.session,
        source=args.source,
        operation=args.operation,
        model_id=args.model_id or "auto",
        duration_ms=duration_ms,
        prompt=args.prompt,
        response=output,
        rc=rc,
        timeout=timed_out,
    )
    sys.stdout.write(output)
    return rc


def _run_copilot_subcommand(args: argparse.Namespace, subcommand: str, extra: Sequence[str] = ()) -> int:
    copilot_bin = _detect_copilot_bin(args.copilot_bin)
    if not copilot_bin:
        print("ERROR: copilot CLI not found", file=sys.stderr)
        return 2

    cmd = [copilot_bin, subcommand, *extra, args.prompt]
    start = time.time()
    rc, output, timed_out = _run_command(cmd, timeout_sec=args.timeout_sec, cwd=REPO_ROOT)
    duration_ms = int((time.time() - start) * 1000)
    _log_usage(
        backend="copilot",
        agent_id=args.agent_id,
        session_id=args.session,
        source=args.source,
        operation=args.operation,
        model_id=subcommand,
        duration_ms=duration_ms,
        prompt=args.prompt,
        response=output,
        rc=rc,
        timeout=timed_out,
    )
    sys.stdout.write(output)
    return rc


def _run_local(args: argparse.Namespace) -> int:
    if not args.local_model:
        print("ERROR: --local-model is required for backend=local", file=sys.stderr)
        return 2

    runner = REPO_ROOT / "llm" / "runner.py"
    cmd = [_python_bin(), str(runner), "--session", args.session, "--model", args.local_model, "--prompt", args.prompt]
    if args.no_history:
        cmd.append("--no-history")

    start = time.time()
    rc, output, timed_out = _run_command(cmd, timeout_sec=args.timeout_sec, cwd=REPO_ROOT)
    duration_ms = int((time.time() - start) * 1000)
    _log_usage(
        backend="local",
        agent_id=args.agent_id,
        session_id=args.session,
        source=args.source,
        operation=args.operation,
        model_id=args.local_model,
        duration_ms=duration_ms,
        prompt=args.prompt,
        response=output,
        rc=rc,
        timeout=timed_out,
    )
    sys.stdout.write(output)
    return rc


def _local_llm_base_url() -> str:
    return (os.environ.get("LOCAL_LLM_BASE_URL") or "http://127.0.0.1:8080").rstrip("/")


def _discover_local_server_model(base_url: str, timeout_sec: int) -> str:
    req = urllib_request.Request(f"{base_url}/v1/models", headers={"Accept": "application/json"})
    with urllib_request.urlopen(req, timeout=timeout_sec) as resp:
        payload = json.loads(resp.read().decode("utf-8", errors="replace") or "{}")

    for item in payload.get("data", []):
        model_id = str(item.get("id") or "").strip()
        if model_id:
            return model_id
    for item in payload.get("models", []):
        model_id = str(item.get("id") or item.get("model") or item.get("name") or "").strip()
        if model_id:
            return model_id
    return ""


def _run_local_server(args: argparse.Namespace) -> int:
    from llm.runner import load_session, save_session

    base_url = _local_llm_base_url()
    model_id = (args.model_id or os.environ.get("LOCAL_LLM_MODEL") or "").strip()

    start = time.time()
    try:
        if not model_id:
            model_id = _discover_local_server_model(base_url, min(args.timeout_sec, 30))

        history = [] if args.no_history else load_session(args.session)
        messages = history + [{"role": "user", "content": args.prompt}]
        payload = {
            "messages": messages,
            "temperature": float(os.environ.get("LOCAL_LLM_TEMPERATURE") or "0"),
            "max_tokens": args.max_tokens,
            "stream": False,
        }
        if model_id:
            payload["model"] = model_id

        req = urllib_request.Request(
            f"{base_url}/v1/chat/completions",
            data=json.dumps(payload).encode("utf-8"),
            headers={"Content-Type": "application/json", "Accept": "application/json"},
        )
        with urllib_request.urlopen(req, timeout=args.timeout_sec) as resp:
            body = resp.read().decode("utf-8", errors="replace")
            parsed = json.loads(body or "{}")
    except urllib_error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        duration_ms = int((time.time() - start) * 1000)
        _log_usage(
            backend="local-server",
            agent_id=args.agent_id,
            session_id=args.session,
            source=args.source,
            operation=args.operation,
            model_id=model_id or "auto",
            duration_ms=duration_ms,
            prompt=args.prompt,
            response=body,
            rc=exc.code,
            timeout=False,
        )
        print(f"ERROR: local llama-server HTTP {exc.code}: {body}".strip(), file=sys.stderr)
        return 2
    except Exception as exc:
        duration_ms = int((time.time() - start) * 1000)
        _log_usage(
            backend="local-server",
            agent_id=args.agent_id,
            session_id=args.session,
            source=args.source,
            operation=args.operation,
            model_id=model_id or "auto",
            duration_ms=duration_ms,
            prompt=args.prompt,
            response=str(exc),
            rc=2,
            timeout=isinstance(exc, TimeoutError),
        )
        print(f"ERROR: local llama-server request failed: {exc}", file=sys.stderr)
        return 2

    response = ""
    choices = parsed.get("choices") or []
    if choices:
        message = choices[0].get("message") or {}
        response = str(message.get("content") or "").strip()
    resolved_model = str(parsed.get("model") or model_id or "auto")

    if not args.no_history and response:
        save_session(args.session, messages + [{"role": "assistant", "content": response}])

    duration_ms = int((time.time() - start) * 1000)
    _log_usage(
        backend="local-server",
        agent_id=args.agent_id,
        session_id=args.session,
        source=args.source,
        operation=args.operation,
        model_id=resolved_model,
        duration_ms=duration_ms,
        prompt=args.prompt,
        response=response,
        rc=0 if response else 2,
        timeout=False,
    )
    if not response:
        print("ERROR: local llama-server returned an empty response", file=sys.stderr)
        return 2

    sys.stdout.write(response)
    return 0


def _deepseek_base_url() -> str:
    return (os.environ.get("DEEPSEEK_BASE_URL") or "https://api.deepseek.com/v1").rstrip("/")


def _run_deepseek(args: argparse.Namespace) -> int:
    from llm.runner import load_session, save_session

    api_key = (os.environ.get("DEEPSEEK_API_KEY") or "").strip()
    if not api_key:
        print("ERROR: DEEPSEEK_API_KEY is not configured", file=sys.stderr)
        return 2

    base_url = _deepseek_base_url()
    model_id = (args.model_id or os.environ.get("DEEPSEEK_MODEL") or "deepseek-chat").strip()

    start = time.time()
    try:
        history = [] if args.no_history else load_session(args.session)
        messages = history + [{"role": "user", "content": args.prompt}]
        payload = {
            "model": model_id,
            "messages": messages,
            "temperature": float(os.environ.get("DEEPSEEK_TEMPERATURE") or "0"),
            "max_tokens": args.max_tokens,
            "stream": False,
        }
        req = urllib_request.Request(
            f"{base_url}/chat/completions",
            data=json.dumps(payload).encode("utf-8"),
            headers={
                "Content-Type": "application/json",
                "Accept": "application/json",
                "Authorization": f"Bearer {api_key}",
            },
        )
        with urllib_request.urlopen(req, timeout=args.timeout_sec) as resp:
            body = resp.read().decode("utf-8", errors="replace")
            parsed = json.loads(body or "{}")
    except urllib_error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        duration_ms = int((time.time() - start) * 1000)
        _log_usage(
            backend="deepseek",
            agent_id=args.agent_id,
            session_id=args.session,
            source=args.source,
            operation=args.operation,
            model_id=model_id,
            duration_ms=duration_ms,
            prompt=args.prompt,
            response=body,
            rc=exc.code,
            timeout=False,
        )
        print(f"ERROR: DeepSeek HTTP {exc.code}: {body}".strip(), file=sys.stderr)
        return 2
    except Exception as exc:
        duration_ms = int((time.time() - start) * 1000)
        _log_usage(
            backend="deepseek",
            agent_id=args.agent_id,
            session_id=args.session,
            source=args.source,
            operation=args.operation,
            model_id=model_id,
            duration_ms=duration_ms,
            prompt=args.prompt,
            response=str(exc),
            rc=2,
            timeout=isinstance(exc, TimeoutError),
        )
        print(f"ERROR: DeepSeek request failed: {exc}", file=sys.stderr)
        return 2

    response = ""
    choices = parsed.get("choices") or []
    if choices:
        message = choices[0].get("message") or {}
        response = str(message.get("content") or "").strip()
    resolved_model = str(parsed.get("model") or model_id or "deepseek-chat")

    if not args.no_history and response:
        save_session(args.session, messages + [{"role": "assistant", "content": response}])

    duration_ms = int((time.time() - start) * 1000)
    _log_usage(
        backend="deepseek",
        agent_id=args.agent_id,
        session_id=args.session,
        source=args.source,
        operation=args.operation,
        model_id=resolved_model,
        duration_ms=duration_ms,
        prompt=args.prompt,
        response=response,
        rc=0 if response else 2,
        timeout=False,
    )
    if not response:
        print("ERROR: DeepSeek returned an empty response", file=sys.stderr)
        return 2

    sys.stdout.write(response)
    return 0


def _run_bedrock(args: argparse.Namespace) -> int:
    runner = REPO_ROOT / "llm" / "bedrock_runner.py"
    model_id = args.model_id or os.environ.get("BEDROCK_MODEL_ID") or "us.amazon.nova-lite-v1:0"
    cmd = [
        _python_bin(),
        str(runner),
        "--agent-id",
        args.agent_id,
        "--session",
        args.session,
        "--prompt",
        args.prompt,
        "--model-id",
        model_id,
        "--max-tokens",
        str(args.max_tokens),
        "--region",
        args.region,
        "--source",
        args.source,
        "--operation",
        args.operation,
    ]
    if args.no_history:
        cmd.append("--no-history")

    rc, output, _ = _run_command(cmd, timeout_sec=args.timeout_sec, cwd=REPO_ROOT)
    sys.stdout.write(output)
    return rc


def _run_script(args: argparse.Namespace) -> int:
    if not args.script_path:
        print("ERROR: --script-path is required for backend=script", file=sys.stderr)
        return 2

    cmd = [args.script_path, *args.script_arg, args.prompt]
    start = time.time()
    rc, output, timed_out = _run_command(cmd, timeout_sec=args.timeout_sec, cwd=REPO_ROOT)
    duration_ms = int((time.time() - start) * 1000)
    _log_usage(
        backend=args.usage_backend or "script",
        agent_id=args.agent_id,
        session_id=args.session,
        source=args.source,
        operation=args.operation,
        model_id=Path(args.script_path).name,
        duration_ms=duration_ms,
        prompt=args.prompt,
        response=output,
        rc=rc,
        timeout=timed_out,
    )
    sys.stdout.write(output)
    return rc


def main() -> int:
    parser = argparse.ArgumentParser(description="Unified GenAI invocation wrapper for HQ backends.")
    parser.add_argument("--backend", required=True, choices=[
        "local-server",
        "deepseek",
        "copilot-chat",
        "copilot-shell",
        "copilot-git",
        "copilot-gh",
        "copilot-suggest",
        "copilot-explain",
        "local",
        "bedrock",
        "script",
    ])
    parser.add_argument("--agent-id", default="")
    parser.add_argument("--session", default="default")
    parser.add_argument("--prompt", default="")
    parser.add_argument("--source", default="llm/genai_wrapper.py")
    parser.add_argument("--operation", default="genai_wrapper")
    parser.add_argument("--model-id", default="")
    parser.add_argument("--local-model", default="")
    parser.add_argument("--copilot-bin", default="")
    parser.add_argument("--timeout-sec", type=int, default=900)
    parser.add_argument("--allow-all", action="store_true")
    parser.add_argument("--no-history", action="store_true")
    parser.add_argument("--region", default=os.environ.get("AWS_REGION") or os.environ.get("AWS_DEFAULT_REGION") or "us-east-1")
    parser.add_argument("--max-tokens", type=int, default=2048)
    parser.add_argument("--script-path", default="")
    parser.add_argument("--script-arg", action="append", default=[])
    parser.add_argument("--usage-backend", default="")
    args = parser.parse_args()

    prompt = args.prompt or sys.stdin.read()
    args.prompt = prompt
    if not prompt.strip():
        print("ERROR: prompt is required", file=sys.stderr)
        return 2

    if args.backend == "copilot-chat":
        return _run_copilot_chat(args)
    if args.backend == "copilot-shell":
        return _run_copilot_subcommand(args, "what-the-shell")
    if args.backend == "copilot-git":
        return _run_copilot_subcommand(args, "git-assist")
    if args.backend == "copilot-gh":
        return _run_copilot_subcommand(args, "gh-assist")
    if args.backend == "copilot-suggest":
        target = os.environ.get("COPILOT_SUGGEST_TARGET", "shell")
        return _run_copilot_subcommand(args, "suggest", ["-t", target])
    if args.backend == "copilot-explain":
        return _run_copilot_subcommand(args, "explain")
    if args.backend == "local-server":
        return _run_local_server(args)
    if args.backend == "deepseek":
        return _run_deepseek(args)
    if args.backend == "local":
        return _run_local(args)
    if args.backend == "bedrock":
        return _run_bedrock(args)
    if args.backend == "script":
        return _run_script(args)

    print(f"ERROR: unsupported backend {args.backend}", file=sys.stderr)
    return 2


if __name__ == "__main__":
    raise SystemExit(main())
