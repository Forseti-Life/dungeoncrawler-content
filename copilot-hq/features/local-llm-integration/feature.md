# Feature: Local LLM Integration

## Status: Scaffolded — Awaiting Model Installation

The infrastructure is complete and committed. Models have not been downloaded yet.
This feature is ready to activate when the target machine has sufficient storage.

## Setup Runbook

**→ `runbooks/local-llm-setup.md`**

Follow that document end-to-end when ready. The short version:

```bash
./llm/setup.sh                           # install Python deps
./llm/download-models.sh                 # check sizes + status
./llm/download-models.sh phi-3-mini      # download first (smallest, 2.2 GB)
./llm/validate.sh                        # confirm routing + model presence
```

## What Was Built

| Component | File | State |
|---|---|---|
| Model catalog | `llm/model-manifest.yaml` | ✅ committed |
| Agent routing | `llm/routing.yaml` | ✅ committed |
| Shared routing lib | `llm/lib/routing.py` | ✅ committed |
| Inference runner | `llm/runner.py` | ✅ committed |
| Setup script | `llm/setup.sh` | ✅ committed |
| Package requirements | `llm/requirements.txt` | ✅ committed |
| Download script | `llm/download-models.sh` | ✅ committed |
| Validation script | `llm/validate.sh` | ✅ committed |
| Agent-exec shim | `scripts/agent-exec-next.sh` | ✅ patched |
| .gitignore | `.gitignore` | ✅ updated |
| Models | `llm/models/` | ⏳ not downloaded |
| Python venv | `llm/.venv/` | ⏳ not created |

## Routing Summary (when models are downloaded)

| Role | Model | Agents |
|---|---|---|
| QA / tester | phi-3-mini | `qa-*`, `agent-task-runner` |
| Business analyst | mistral-7b-instruct | `ba-*` |
| Security analyst | mistral-7b-instruct | `sec-analyst-*` |
| Code review | deepseek-coder | `agent-code-review` |
| Explore | phi-3-mini | `agent-explore-*` |
| CEO / PM / Dev | **Copilot CLI** | `ceo-*`, `pm-*`, `dev-*` |

## Storage Required

| Scenario | Models | Disk |
|---|---|---|
| Minimal | phi-3-mini | ~2.2 GB |
| Recommended | phi-3-mini + mistral-7b | ~6.3 GB |
| Full | all four models | ~14 GB |

## Fallback Behavior

Until models are downloaded, **all agents continue to use the Copilot CLI**. The routing
shim in `agent-exec-next.sh` only activates when a model file is present on disk.
There is no breakage on fresh clone or before setup.

## Decision Log

- **2026-02-22** — Board direction: local LLMs for structured/repetitive roles (QA, BA,
  sec, explore, code review); CEO/PM/dev remain on Copilot CLI for high-judgment work.
  Models: Hugging Face GGUF, CPU-only (llama-cpp-python), no GPU required.
  Storage deferred pending machine provisioning.
