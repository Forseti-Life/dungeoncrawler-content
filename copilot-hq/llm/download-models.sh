#!/usr/bin/env bash
# llm/download-models.sh — Download GGUF models from Hugging Face Hub.
#
# Reads model-manifest.yaml and downloads one or all models into llm/models/.
# Already-downloaded models are skipped.
#
# Usage:
#   ./llm/download-models.sh                  # List available models + status
#   ./llm/download-models.sh <model-id>       # Download a specific model
#   ./llm/download-models.sh --all            # Download all models in manifest
#   ./llm/download-models.sh --routing        # Download only models referenced in routing.yaml
#
# Requirements: llm/setup.sh must have been run first (needs huggingface_hub + pyyaml).
#
# Storage: models are saved to llm/models/<filename>.
#          Plan ~2–5 GB per model. See model-manifest.yaml for sizes.

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LLM_DIR="$ROOT_DIR/llm"
MODELS_DIR="$LLM_DIR/models"

# Prefer venv Python if setup.sh was run.
if [ -x "$LLM_DIR/.venv/bin/python3" ]; then
  PYTHON_BIN="$LLM_DIR/.venv/bin/python3"
elif [ -n "${LLM_PYTHON_BIN:-}" ] && [ -x "$LLM_PYTHON_BIN" ]; then
  PYTHON_BIN="$LLM_PYTHON_BIN"
else
  PYTHON_BIN="$(command -v python3 2>/dev/null || true)"
  [ -n "$PYTHON_BIN" ] || { echo "ERROR: python3 not found. Run: llm/setup.sh" >&2; exit 1; }
fi

cd "$ROOT_DIR"

# ── helpers ──────────────────────────────────────────────────────────────────

list_models() {
  "$PYTHON_BIN" - <<'PY'
import yaml, pathlib, sys

manifest_path = pathlib.Path("llm/model-manifest.yaml")
models_dir = pathlib.Path("llm/models")

if not manifest_path.exists():
    print("ERROR: llm/model-manifest.yaml not found", file=sys.stderr)
    sys.exit(1)

manifest = yaml.safe_load(manifest_path.read_text(encoding="utf-8"))
models = manifest.get("models", [])

print(f"\nAvailable models ({len(models)} total):\n")
print(f"  {'ID':<30} {'SIZE':>6}  {'STATUS':<12}  FILENAME")
print(f"  {'-'*30} {'-'*6}  {'-'*12}  {'-'*40}")
for m in models:
    model_id = m.get("id", "?")
    filename = m.get("filename", "")
    size_gb  = m.get("size_gb", 0)
    local    = models_dir / filename
    status   = "downloaded" if local.exists() else "not present"
    print(f"  {model_id:<30} {size_gb:>5.1f}G  {status:<12}  {filename}")
print()
PY
}

download_model_id() {
  local model_id="$1"
  "$PYTHON_BIN" - "$model_id" <<'PY'
import sys, yaml, pathlib

model_id = sys.argv[1]
manifest_path = pathlib.Path("llm/model-manifest.yaml")
models_dir = pathlib.Path("llm/models")
models_dir.mkdir(parents=True, exist_ok=True)

manifest = yaml.safe_load(manifest_path.read_text(encoding="utf-8"))
target = next((m for m in manifest.get("models", []) if m.get("id") == model_id), None)
if not target:
    print(f"ERROR: model '{model_id}' not found in model-manifest.yaml", file=sys.stderr)
    sys.exit(1)

hf_repo   = target["hf_repo"]
hf_file   = target["hf_filename"]
local_name = target["filename"]
local_path = models_dir / local_name
size_gb    = target.get("size_gb", 0)

if local_path.exists():
    print(f"[SKIP] {model_id}: already downloaded at {local_path}")
    sys.exit(0)

print(f"[DOWNLOAD] {model_id}")
print(f"  Repo:     {hf_repo}")
print(f"  File:     {hf_file}")
print(f"  Size:     ~{size_gb:.1f} GB")
print(f"  Dest:     {local_path}")
print()

try:
    from huggingface_hub import hf_hub_download
    import inspect
    # local_dir_use_symlinks was deprecated in huggingface_hub 0.23.0; detect and omit if absent
    sig = inspect.signature(hf_hub_download)
    kwargs = dict(repo_id=hf_repo, filename=hf_file, local_dir=str(models_dir))
    if "local_dir_use_symlinks" in sig.parameters:
        kwargs["local_dir_use_symlinks"] = False
except ImportError:
    print("ERROR: huggingface_hub not installed. Run: llm/setup.sh", file=sys.stderr)
    sys.exit(1)

print("  Downloading (this may take several minutes)...")
downloaded = hf_hub_download(**kwargs)

# Rename to expected filename if HF saved it differently.
downloaded_path = pathlib.Path(downloaded)
if downloaded_path.name != local_name:
    downloaded_path.rename(local_path)

print(f"  [OK] Saved: {local_path}")
PY
}

get_routing_model_ids() {
  "$PYTHON_BIN" - <<'PY'
import yaml, pathlib, sys

routing_path = pathlib.Path("llm/routing.yaml")
manifest_path = pathlib.Path("llm/model-manifest.yaml")

if not routing_path.exists():
    sys.exit(0)

routing = yaml.safe_load(routing_path.read_text(encoding="utf-8"))
manifest = yaml.safe_load(manifest_path.read_text(encoding="utf-8"))
valid_ids = {m["id"] for m in manifest.get("models", [])}

seen = set()
for model_id in list(routing.get("roles", {}).values()) + list(routing.get("agents", {}).values()) + [routing.get("default", "copilot")]:
    if model_id and model_id != "copilot" and model_id in valid_ids:
        if model_id not in seen:
            seen.add(model_id)
            print(model_id)
PY
}

# ── main ─────────────────────────────────────────────────────────────────────

ARG="${1:-}"

if [ -z "$ARG" ]; then
  list_models
  echo "  Run with a model ID to download, e.g.:"
  echo "    ./llm/download-models.sh phi-3-mini"
  echo "    ./llm/download-models.sh --routing   (download only models used by routing.yaml)"
  echo "    ./llm/download-models.sh --all"
  echo ""
  exit 0
fi

if [ "$ARG" = "--all" ]; then
  echo "Downloading all models from manifest..."
  ALL_IDS="$("$PYTHON_BIN" - <<'PY'
import yaml, pathlib, sys
manifest = yaml.safe_load(pathlib.Path("llm/model-manifest.yaml").read_text(encoding="utf-8"))
for m in manifest.get("models", []):
    print(m["id"])
PY
  )"
  echo "$ALL_IDS" | while IFS= read -r mid; do
    [ -n "$mid" ] || continue
    download_model_id "$mid"
  done
  echo ""
  list_models
  exit 0
fi

if [ "$ARG" = "--routing" ]; then
  echo "Downloading models referenced in routing.yaml..."
  IDS="$(get_routing_model_ids)"
  if [ -z "$IDS" ]; then
    echo "  No non-copilot models found in routing.yaml."
    exit 0
  fi
  echo "$IDS" | while IFS= read -r mid; do
    download_model_id "$mid"
  done
  echo ""
  list_models
  exit 0
fi

# Single model download.
download_model_id "$ARG"
