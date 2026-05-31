# llm/ — Local LLM Integration Layer

This directory manages local LLM inference for copilot-sessions-hq agents, reducing
dependence on the external GitHub Copilot CLI for repetitive, structured tasks.

## Architecture

```
Agent inbox item
      │
      ▼
agent-exec-next.sh
      │
      ├─ check llm/routing.yaml for agent/role
      │
      ├─ model assigned + file present?
      │     YES → python3 llm/runner.py --session ... --model ... --prompt ...
      │     NO  → selected backend (Copilot CLI by default, or Bedrock when configured)
      │
      ▼
 outbox update written
```

## Role → Model Routing

| Role / Agent | Model | Rationale |
|---|---|---|
| `ceo-copilot` | Copilot | Complex orchestration, high judgment |
| `pm-*` | Copilot | Product decisions, acceptance criteria |
| `dev-*` | Copilot | Implementation requires frontier reasoning |
| `ba-*` | mistral-7b-instruct | Structured requirements, documentation |
| `qa-*` | phi-3-mini | Checklist evaluation, APPROVE/BLOCK |
| `sec-analyst-*` | mistral-7b-instruct | Security checklists, structured findings |
| `agent-code-review` | deepseek-coder | Code-specialized model |
| `agent-explore-*` | phi-3-mini | Summarization, reading, exploration |
| `agent-task-runner` | phi-3-mini | Structured output for build/test runs |

Routing is defined in `routing.yaml`. If a local model is assigned but not yet
downloaded, execution falls back to the selected agent backend (`HQ_AGENTIC_BACKEND`)
— Copilot CLI by default (`auto`) or Bedrock when configured.

## Setup (new machine / fresh clone)

```bash
# 1. Install Python dependencies and create venv
./llm/setup.sh

# 2. Check what models are available and their download sizes
./llm/download-models.sh

# 3. Download the models you want (ensure sufficient disk space first)
./llm/download-models.sh phi-3-mini          # 2.2 GB — QA/explore agents
./llm/download-models.sh mistral-7b-instruct # 4.1 GB — BA/security agents
./llm/download-models.sh deepseek-coder      # 3.8 GB — code review

# Or download everything referenced in routing.yaml:
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
  requirements.txt       # Python package requirements (pip install -r)
  runner.py              # Inference shim: --session, --model, --prompt → stdout
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
| `LLM_PYTHON_BIN` | auto-detected | Override Python binary path (used by scripts) |
| `LLM_DISABLE` | unset | Set to `1` to force all agents to use Copilot CLI |

## Adding a New Model

1. Add an entry to `model-manifest.yaml` with `id`, `hf_repo`, `filename`, `hf_filename`, `size_gb`, `tasks`.
2. Assign it in `routing.yaml` under `roles:` or `agents:`.
3. Download it: `./llm/download-models.sh <new-model-id>`

## Updating Agent Routing

Edit `routing.yaml` directly. Changes take effect on the next agent execution cycle —
no restart required. If a model is unassigned or its file is missing, that agent
automatically routes to the selected backend (`HQ_AGENTIC_BACKEND`: `auto|copilot|bedrock`).

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

A minimal setup (phi-3-mini + mistral-7b) requires ~7 GB. Plan storage accordingly
before running `download-models.sh`. Models are stored in `llm/models/` which is
`.gitignored` — they are **not committed to the repo**.
