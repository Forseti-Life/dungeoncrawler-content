#!/usr/bin/env python3
"""
llm/runner.py — Local LLM inference shim for copilot-sessions-hq.

Drop-in replacement for: copilot --resume SESSION_ID --silent -p PROMPT
Usage:
    python3 llm/runner.py --session SESSION_ID --model path/to/model.gguf --prompt "text"
    python3 llm/runner.py --session SESSION_ID --model path/to/model.gguf  # reads prompt from stdin
    python3 llm/runner.py --dry-run  # validate environment without running inference

Session history is stored in llm/cache/sessions/<SESSION_ID>.json as a list of
{role, content} messages, mirroring the Copilot CLI --resume behavior.

Exit codes:
    0 — success, response written to stdout
    1 — unrecoverable error (model not found, bad args)
    2 — inference failed (caller should fall back to Copilot CLI)
"""

import argparse
import json
import os
import sys
import traceback
from pathlib import Path
from typing import List, Optional

# Root of the HQ repo (two levels up: llm/runner.py → llm/ → root)
REPO_ROOT = Path(__file__).resolve().parent.parent
CACHE_DIR = REPO_ROOT / "llm" / "cache" / "sessions"

# Maximum tokens to generate per response.
MAX_TOKENS = 2048
# Context window passed to llama.cpp (clamped to model's actual limit at load time).
N_CTX = 8192
# CPU threads (0 = auto: cpu_count - 1).
N_THREADS = 0
# Suppress llama.cpp verbose stderr output.
LLAMA_VERBOSE = False

# Per-model-family stop tokens aligned with each chat template.
_STOP_TOKENS = {
    "phi":       ["<|user|>", "<|end|>", "<|endoftext|>"],
    "deepseek":  ["### Instruction:", "### Human:"],
    "default":   ["[INST]", "</s>", "[/INST]"],
}


# ── session history ───────────────────────────────────────────────────────────

def load_session(session_id: str) -> List[dict]:
    """Load conversation history for a session. Returns empty list if new."""
    CACHE_DIR.mkdir(parents=True, exist_ok=True)
    session_file = CACHE_DIR / f"{session_id}.json"
    if session_file.exists():
        try:
            return json.loads(session_file.read_text(encoding="utf-8"))
        except Exception:
            return []
    return []


def save_session(session_id: str, history: List[dict]) -> None:
    """Persist conversation history. Best-effort — never raises."""
    CACHE_DIR.mkdir(parents=True, exist_ok=True)
    session_file = CACHE_DIR / f"{session_id}.json"
    try:
        session_file.write_text(
            json.dumps(history, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )
    except Exception:
        pass


# ── model path resolution ─────────────────────────────────────────────────────

def resolve_model_path(model_arg: str) -> Path:
    """Resolve model path: absolute → relative to cwd → relative to llm/models/."""
    p = Path(model_arg)
    if p.is_absolute():
        return p
    if p.exists():
        return p.resolve()
    candidate = REPO_ROOT / "llm" / "models" / model_arg
    if candidate.exists():
        return candidate
    return p  # Caller checks .exists()


# ── chat templates ────────────────────────────────────────────────────────────

def _model_family(model_path: Path) -> str:
    name = model_path.name.lower()
    if "phi-3" in name or "phi3" in name:
        return "phi"
    if "deepseek" in name:
        return "deepseek"
    return "default"  # Mistral / Llama instruct


def stop_tokens_for(model_path: Path) -> List[str]:
    return _STOP_TOKENS.get(_model_family(model_path), _STOP_TOKENS["default"])


def messages_to_prompt(messages: List[dict], model_path: Path) -> str:
    """Format a message list into the model's native prompt string."""
    family = _model_family(model_path)

    if family == "phi":
        parts = []
        for msg in messages:
            role, content = msg["role"], msg["content"]
            if role == "system":
                parts.append(f"<|system|>\n{content}<|end|>")
            elif role == "user":
                parts.append(f"<|user|>\n{content}<|end|>")
            elif role == "assistant":
                parts.append(f"<|assistant|>\n{content}<|end|>")
        parts.append("<|assistant|>")
        return "\n".join(parts)

    if family == "deepseek":
        parts = []
        for msg in messages:
            role, content = msg["role"], msg["content"]
            if role == "system":
                parts.append(content)
            elif role == "user":
                parts.append(f"### Instruction:\n{content}")
            elif role == "assistant":
                parts.append(f"### Response:\n{content}")
        parts.append("### Response:")
        return "\n\n".join(parts)

    # Mistral / Llama [INST] format
    result = ""
    system_content = ""
    for msg in messages:
        role, content = msg["role"], msg["content"]
        if role == "system":
            system_content = content
        elif role == "user":
            if system_content:
                result += f"[INST] {system_content}\n\n{content} [/INST]"
                system_content = ""
            else:
                result += f"[INST] {content} [/INST]"
        elif role == "assistant":
            result += f" {content}</s>"
    return result


# ── inference ─────────────────────────────────────────────────────────────────

def run_inference(model_path: Path, messages: List[dict]) -> str:
    """
    Run llama-cpp-python inference against a fully-assembled message list.
    Raises ImportError if llama-cpp-python is not installed.
    Raises RuntimeError on inference failure.
    """
    try:
        from llama_cpp import Llama
    except ImportError as e:
        raise ImportError("llama-cpp-python is not installed. Run: llm/setup.sh") from e

    prompt_str = messages_to_prompt(messages, model_path)
    n_threads = N_THREADS if N_THREADS > 0 else max(1, (os.cpu_count() or 2) - 1)

    try:
        llm = Llama(
            model_path=str(model_path),
            n_ctx=N_CTX,
            n_threads=n_threads,
            verbose=LLAMA_VERBOSE,
        )
        output = llm(
            prompt_str,
            max_tokens=MAX_TOKENS,
            stop=stop_tokens_for(model_path),
            echo=False,
        )
        return output["choices"][0]["text"].strip()
    except Exception as e:
        raise RuntimeError(f"Inference failed: {e}") from e


# ── dry-run validation ────────────────────────────────────────────────────────

def dry_run() -> None:
    """Check deps, config files, and model presence without running inference."""
    print("=== LLM Runner: dry-run validation ===")

    for pkg, import_name in [("llama-cpp-python", "llama_cpp"), ("pyyaml", "yaml")]:
        try:
            __import__(import_name)
            print(f"[OK]      {pkg} installed")
        except ImportError:
            print(f"[MISSING] {pkg} not installed — run: llm/setup.sh")

    for label, rel_path in [
        ("model-manifest.yaml", "llm/model-manifest.yaml"),
        ("routing.yaml",        "llm/routing.yaml"),
    ]:
        p = REPO_ROOT / rel_path
        print(f"[OK]      {label}: {p}" if p.exists() else f"[MISSING] {label}: {p}")

    models_dir = REPO_ROOT / "llm" / "models"
    if models_dir.exists():
        gguf_files = sorted(models_dir.glob("*.gguf"))
        if gguf_files:
            print(f"[OK]      {len(gguf_files)} model(s) present:")
            for f in gguf_files:
                size_gb = f.stat().st_size / (1024 ** 3)
                print(f"            {f.name} ({size_gb:.1f} GB)")
        else:
            print("[INFO]    No .gguf models downloaded yet — run: llm/download-models.sh")
    else:
        print(f"[MISSING] models directory: {models_dir}")

    print(
        f"[OK]      session cache: {CACHE_DIR}"
        if CACHE_DIR.exists()
        else f"[INFO]    session cache will be created on first use: {CACHE_DIR}"
    )
    print("=== dry-run complete ===")


# ── main ─────────────────────────────────────────────────────────────────────

def main() -> None:
    parser = argparse.ArgumentParser(
        description="Local LLM inference shim for copilot-sessions-hq agents."
    )
    parser.add_argument("--session", default="default",
                        help="Session ID for conversation history.")
    parser.add_argument("--model", default="",
                        help="Model filename or path (GGUF).")
    parser.add_argument("--prompt", default="",
                        help="Prompt text. Reads from stdin if omitted.")
    parser.add_argument("--dry-run", action="store_true",
                        help="Validate environment and exit.")
    parser.add_argument("--no-history", action="store_true",
                        help="Ignore session history (stateless mode).")
    args = parser.parse_args()

    if args.dry_run:
        dry_run()
        sys.exit(0)

    if not args.model:
        print("ERROR: --model is required (unless --dry-run)", file=sys.stderr)
        sys.exit(1)

    model_path = resolve_model_path(args.model)
    if not model_path.exists():
        print(f"ERROR: model file not found: {model_path}", file=sys.stderr)
        sys.exit(1)

    prompt = args.prompt or sys.stdin.read()
    if not prompt.strip():
        print("ERROR: prompt is empty", file=sys.stderr)
        sys.exit(1)

    history = [] if args.no_history else load_session(args.session)
    messages = history + [{"role": "user", "content": prompt}]

    try:
        response = run_inference(model_path, messages)
    except ImportError as e:
        print(f"ERROR: {e}", file=sys.stderr)
        sys.exit(2)
    except RuntimeError as e:
        print(f"ERROR: {e}", file=sys.stderr)
        sys.exit(2)
    except Exception as e:
        print(f"ERROR: unexpected failure: {e}", file=sys.stderr)
        traceback.print_exc(file=sys.stderr)
        sys.exit(2)

    if not args.no_history:
        save_session(args.session, messages + [{"role": "assistant", "content": response}])

    print(response)


if __name__ == "__main__":
    main()
