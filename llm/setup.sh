#!/usr/bin/env bash
# llm/setup.sh — Set up the local LLM environment for copilot-sessions-hq.
#
# This script:
#   1. Verifies Python 3.8+ is available
#   2. Creates a virtual environment at llm/.venv (optional, recommended)
#   3. Installs required Python packages
#   4. Creates necessary directories
#   5. Validates the installation
#
# Usage:
#   ./llm/setup.sh              # Install into llm/.venv (recommended)
#   ./llm/setup.sh --system     # Install into system/user Python (no venv)
#   ./llm/setup.sh --check      # Check current state without installing
#
# After setup, activate the venv before running agents:
#   source llm/.venv/bin/activate
# Or add llm/.venv/bin to PATH in your shell profile / cron environment.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LLM_DIR="$ROOT_DIR/llm"
VENV_DIR="$LLM_DIR/.venv"

USE_VENV=true
CHECK_ONLY=false

for arg in "$@"; do
  case "$arg" in
    --system) USE_VENV=false ;;
    --check)  CHECK_ONLY=true ;;
  esac
done

# ── helpers ──────────────────────────────────────────────────────────────────

ok()   { echo "  [OK] $*"; }
info() { echo "  [--] $*"; }
fail() { echo "  [FAIL] $*" >&2; exit 1; }

# ── 1. Python version ─────────────────────────────────────────────────────────

echo ""
echo "=== LLM Setup: copilot-sessions-hq ==="
echo ""
echo "[1/5] Checking Python..."

PYTHON_BIN="$(command -v python3 2>/dev/null || true)"
[ -n "$PYTHON_BIN" ] || fail "python3 not found in PATH. Install Python 3.8+ first."

PY_VERSION="$("$PYTHON_BIN" -c 'import sys; print(f"{sys.version_info.major}.{sys.version_info.minor}")')"
PY_MAJOR="$(echo "$PY_VERSION" | cut -d. -f1)"
PY_MINOR="$(echo "$PY_VERSION" | cut -d. -f2)"

if [ "$PY_MAJOR" -lt 3 ] || { [ "$PY_MAJOR" -eq 3 ] && [ "$PY_MINOR" -lt 8 ]; }; then
  fail "Python 3.8+ required. Found: $PY_VERSION"
fi
ok "Python $PY_VERSION ($PYTHON_BIN)"

if $CHECK_ONLY; then
  echo ""
  echo "[check] Running dry-run validation..."
  "$PYTHON_BIN" "$LLM_DIR/runner.py" --dry-run
  exit 0
fi

# ── 2. Virtual environment ─────────────────────────────────────────────────────

echo ""
echo "[2/5] Setting up Python environment..."

if $USE_VENV; then
  if [ ! -d "$VENV_DIR" ]; then
    info "Creating virtual environment at llm/.venv..."
    "$PYTHON_BIN" -m venv "$VENV_DIR"
    ok "Virtual environment created"
  else
    ok "Virtual environment already exists"
  fi
  PIP_BIN="$VENV_DIR/bin/pip"
  PYTHON_BIN="$VENV_DIR/bin/python3"
else
  PIP_BIN="$(command -v pip3 2>/dev/null || true)"
  [ -n "$PIP_BIN" ] || fail "pip3 not found in PATH."
  info "Using system Python (--system flag)"
fi

ok "Using pip: $PIP_BIN"

# ── 3. Install packages ────────────────────────────────────────────────────────

echo ""
echo "[3/5] Installing Python packages..."

# Upgrade pip silently first.
"$PIP_BIN" install --quiet --upgrade pip

# Core packages (see llm/requirements.txt):
#   llama-cpp-python — CPU-only llama.cpp inference (no GPU needed)
#   huggingface_hub  — download models from Hugging Face Hub
#   pyyaml           — parse model-manifest.yaml and routing.yaml

echo "  Installing llama-cpp-python (CPU build, compiles from source ~2–5 min)..."
if ! CMAKE_ARGS="-DLLAMA_BLAS=OFF -DLLAMA_CUBLAS=OFF" FORCE_CMAKE=1 \
     "$PIP_BIN" install --quiet "llama-cpp-python"; then
  # Pre-built wheel fallback (faster but may not match CPU exactly)
  echo "  Source build failed — trying pre-built wheel..."
  "$PIP_BIN" install --quiet "llama-cpp-python" --prefer-binary \
    || fail "llama-cpp-python installation failed. See: runbooks/local-llm-setup.md#troubleshooting"
fi
ok "llama-cpp-python installed"

echo "  Installing remaining dependencies (llm/requirements.txt)..."
"$PIP_BIN" install --quiet -r "$LLM_DIR/requirements.txt" \
  || fail "Dependency installation failed. See: runbooks/local-llm-setup.md#troubleshooting"
ok "huggingface_hub + pyyaml installed"

# ── 4. Create directories ──────────────────────────────────────────────────────

echo ""
echo "[4/5] Creating directories..."

mkdir -p "$LLM_DIR/models"
mkdir -p "$LLM_DIR/cache/sessions"
[ -f "$LLM_DIR/models/.gitkeep" ] || touch "$LLM_DIR/models/.gitkeep"
[ -f "$LLM_DIR/cache/.gitkeep"  ] || touch "$LLM_DIR/cache/.gitkeep"
ok "llm/models/ ready"
ok "llm/cache/sessions/ ready"

# ── 5. Validate ────────────────────────────────────────────────────────────────

echo ""
echo "[5/5] Validating installation..."

"$PYTHON_BIN" "$LLM_DIR/runner.py" --dry-run

# ── Done ───────────────────────────────────────────────────────────────────────

echo ""
echo "=== Setup complete ==="
echo ""
if $USE_VENV; then
  echo "  To activate the venv in your shell:"
  echo "    source llm/.venv/bin/activate"
  echo ""
  echo "  To configure agent-exec to use the venv Python, set:"
  echo "    export LLM_PYTHON_BIN=\"$VENV_DIR/bin/python3\""
  echo "  (add to ~/.bashrc or the cron environment)"
fi
echo ""
echo "  Next steps:"
echo "    1. Download models:   ./llm/download-models.sh"
echo "    2. Validate runner:   ./llm/validate.sh"
echo "    3. Run agents:        ./scripts/agent-exec-once.sh"
echo ""
