#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ -x "$ROOT_DIR/llm/.venv/bin/python3" ]; then
  PYTHON_BIN="$ROOT_DIR/llm/.venv/bin/python3"
elif [ -n "${LLM_PYTHON_BIN:-}" ] && [ -x "${LLM_PYTHON_BIN}" ]; then
  PYTHON_BIN="${LLM_PYTHON_BIN}"
else
  PYTHON_BIN="$(command -v python3)"
fi

exec "$PYTHON_BIN" "$ROOT_DIR/llm/genai_wrapper.py" "$@"
