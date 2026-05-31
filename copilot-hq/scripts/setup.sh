#!/usr/bin/env bash
set -euo pipefail

# HQ setup: installs cron-based auto-checkpoint and ensures required dirs.

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}" )/.." && pwd)"
cd "$ROOT_DIR"

mkdir -p inbox/responses inbox/commands inbox/processed

# Install pre-commit hook to run lint-scripts.sh on every commit.
HOOK_FILE=".git/hooks/pre-commit"
if [ -d ".git/hooks" ]; then
cat > "$HOOK_FILE" << 'HOOK'
#!/usr/bin/env bash
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
bash scripts/lint-scripts.sh
HOOK
chmod +x "$HOOK_FILE"
echo "pre-commit hook installed."
else
  echo "pre-commit hook skipped (.git/hooks not present)."
fi

./scripts/install-cron-auto-checkpoint.sh

# Install Copilot CLI user-level instructions for CEO persona auto-load.
# This enables: "take on the CEO persona" → automatic session context load.
COPILOT_INSTRUCTIONS_DIR="$HOME/.copilot"
COPILOT_INSTRUCTIONS_SRC="$ROOT_DIR/org-chart/agents/instructions/copilot-user-startup.instructions.md"
COPILOT_INSTRUCTIONS_DEST="$COPILOT_INSTRUCTIONS_DIR/copilot-instructions.md"
mkdir -p "$COPILOT_INSTRUCTIONS_DIR"
if [ ! -f "$COPILOT_INSTRUCTIONS_DEST" ] || ! diff -q "$COPILOT_INSTRUCTIONS_SRC" "$COPILOT_INSTRUCTIONS_DEST" >/dev/null 2>&1; then
  cp "$COPILOT_INSTRUCTIONS_SRC" "$COPILOT_INSTRUCTIONS_DEST"
  echo "Copilot user instructions installed: $COPILOT_INSTRUCTIONS_DEST"
else
  echo "Copilot user instructions already up to date."
fi

echo "HQ setup complete."

# ── Local LLM layer ──────────────────────────────────────────────────────────
# Run this separately on machines with sufficient storage/RAM to enable local
# model inference for QA/BA/explore/security agents:
#
#   ./llm/setup.sh          # Install Python deps + create llm/.venv
#   ./llm/download-models.sh --routing   # Download models referenced in routing.yaml
#   ./llm/validate.sh       # Verify environment
#
# See: runbooks/local-llm-setup.md for full instructions.

# Disable the legacy bash agent executor loop (avoid two orchestrators competing).
./scripts/disable-agent-exec-loop.sh || true

# LangGraph orchestrator loop (authoritative scheduler)
./scripts/install-cron-orchestrator-loop.sh

# Forseti dashboard publisher runs inside the orchestrator tick;
# do not add a separate cron or loop publisher.

# HQ automation converge watchdog (ensures enable/disable flips actually start/stop loops).
./scripts/install-cron-hq-automation.sh
