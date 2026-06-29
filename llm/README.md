# llm/ — Local LLM Integration Layer

This directory manages local LLM inference for copilot-sessions-hq agents. The
production runtime is the host-local `llama-server` at
`http://127.0.0.1:8080`, currently serving `mistral-7b-instruct-v0.2.Q4_K_M.gguf`.
HQ release-management and SDLC automation run local-only with no external LLM fallback.

## Architecture

```
Agent inbox item
      │
      ▼
agent-exec-next.sh
      │
      ├─ check llm/routing.yaml for agent/role
      │
      ├─ route = local-server?
      │     YES → scripts/genai-wrapper.sh --backend local-server ...
      │
       ├─ route = local GGUF model + file present?
       │     YES → scripts/genai-wrapper.sh --backend local ...
       │
       └─ otherwise selected local backend via shared wrapper
                  - scripts/genai-wrapper.sh --backend local-server ...
       │
       ▼
 outbox update written
```

## Role → Model Routing

| Role / Agent | Model | Rationale |
|---|---|---|
| `all seats` | `local-server` | Host-local llama.cpp server on `127.0.0.1:8080` |

Routing is defined in `routing.yaml`. The current default route is `local-server`,
which uses the host-local llama.cpp server. External backup backends are disabled
for HQ release-management and SDLC automation.

## Setup (new machine / fresh clone)

```bash
# 1. Install Python dependencies and create venv
./llm/setup.sh

# 2. Check what optional on-disk models are available and their download sizes
./llm/download-models.sh

# 3. (Optional) Download on-disk GGUF models if you want file-based local routing
./llm/download-models.sh phi-3-mini          # 2.2 GB — QA/explore agents
./llm/download-models.sh mistral-7b-instruct # 4.1 GB — BA/security agents
./llm/download-models.sh deepseek-coder      # 3.8 GB — code review

# Or download everything referenced in routing.yaml if routing uses manifest model IDs:
./llm/download-models.sh --routing

# 4. Validate the environment
./llm/validate.sh

# 5. (Optional) Run a live inference test
./llm/validate.sh --test-run
```

## File Layout

```
llm/
  README.md              # This file
  model-manifest.yaml    # Available models: HF source, filename, size, task tags
  routing.yaml           # agent-id / role → model assignment
  genai_wrapper.py       # Shared backend dispatcher + usage logging
  requirements.txt       # Python package requirements (pip install -r)
  runner.py              # Local-model inference shim: --session, --model, --prompt → stdout
  setup.sh               # Install deps, create venv, validate
  download-models.sh     # Pull GGUF models from Hugging Face Hub
  validate.sh            # Check environment, show routing table, optional test run
  lib/
    __init__.py          # Package marker
    routing.py           # Shared routing resolution (used by runner, validate, agent-exec)
  models/                # .gitignored — GGUF weight files live here
  cache/                 # .gitignored — session conversation history (JSON)
  .venv/                 # .gitignored — Python virtual environment (created by setup.sh)
```

## Environment Variables

| Variable | Default | Description |
|---|---|---|
| `LOCAL_LLM_BASE_URL` | `http://127.0.0.1:8080` | Host-local llama.cpp server endpoint used by `local-server` |
| `LOCAL_LLM_MODEL` | auto-detected | Optional explicit model ID for the local server |
| `LLM_PYTHON_BIN` | auto-detected | Override Python binary path (used by scripts) |
| `LLM_DISABLE` | unset | Reserved; external fallback is disabled in production runtime |

## Adding a New Model

1. Add an entry to `model-manifest.yaml` with `id`, `hf_repo`, `filename`, `hf_filename`, `size_gb`, `tasks`.
2. Assign it in `routing.yaml` under `roles:` or `agents:`.
3. Download it: `./llm/download-models.sh <new-model-id>`

## Updating Agent Routing

Edit `routing.yaml` directly. Changes take effect on the next agent execution cycle —
no restart required. Route values should be `local-server` or a manifest model ID.

## Session History

Each agent has a persistent session file at `llm/cache/sessions/<SESSION_ID>.json`.
This mirrors the Copilot CLI `--resume` behavior, giving local models conversation
context across inbox items. Session files are `.gitignored`.

To clear a session: `rm llm/cache/sessions/<SESSION_ID>.json`

## Disk Space Planning

| Model | Size |
|---|---|
| phi-3-mini (Q4_K_M) | ~2.2 GB |
| mistral-7b-instruct (Q4_K_M) | ~4.1 GB |
| deepseek-coder-6.7b (Q4_K_M) | ~3.8 GB |
| codellama-7b (Q4_K_M) | ~3.8 GB |

A standard routed setup (phi-3-mini + mistral-7b + deepseek-coder) requires
~10 GB. Plan storage accordingly before running `download-models.sh`. Models are
stored in `llm/models/` which is
`.gitignored` — they are **not committed to the repo**.
