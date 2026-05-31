# Runbook: Local LLM Setup

## Purpose

This runbook covers the full setup of the local LLM inference layer (`llm/`) on a new or
upgraded machine. Follow this when storage and hardware are ready. Until then, all agents
continue running on the Copilot CLI with zero changes required.

## Status Tracking

When you are ready to set up the models, search for this file:
```
runbooks/local-llm-setup.md
```
Or check the feature tracker:
```
features/local-llm-integration/feature.md
```

---

## Prerequisites

### 1. Storage
Models are large. Ensure sufficient free disk space **before** downloading.

| Model | Use case | Size |
|---|---|---|
| `phi-3-mini` | QA, explore agents | ~2.2 GB |
| `mistral-7b-instruct` | BA, security analysts | ~4.1 GB |
| `deepseek-coder` | Code review agent | ~3.8 GB |
| `codellama-7b-instruct` | Alternate code review | ~3.8 GB |

**Minimum recommended:** phi-3-mini + mistral-7b ≈ **7 GB free**  
**Full stack:** all four models ≈ **14 GB free**

If models will be stored on a separate mount (e.g., `/mnt/models`, external drive):
```bash
# Symlink llm/models to your storage location before running setup:
rmdir /home/keithaumiller/copilot-sessions-hq/llm/models
ln -s /mnt/models /home/keithaumiller/copilot-sessions-hq/llm/models
```

### 2. Python
- Python 3.8+ required (`python3 --version`)
- `pip3` must be available

### 3. Hugging Face access
- Models are public; no HF account required for downloads
- If you want to use private/gated models, set: `export HF_TOKEN=<your-token>`

### 4. CPU / RAM
- All models run on CPU (no GPU required) via `llama-cpp-python`
- Minimum RAM: model size + ~1 GB overhead
  - phi-3-mini: ~3 GB RAM needed
  - mistral-7b: ~5 GB RAM needed
- Inference will be slow on low-RAM machines; phi-3-mini is the practical minimum here

---

## Setup Steps

### Step 1 — Install Python dependencies

```bash
cd /home/keithaumiller/copilot-sessions-hq
./llm/setup.sh
```

This creates `llm/.venv/` and installs:
- `llama-cpp-python` (CPU inference)
- `huggingface_hub` (model downloads)
- `pyyaml` (config parsing)

If the build takes a long time, that is normal — `llama-cpp-python` compiles from source.

**System-wide install** (skip venv):
```bash
./llm/setup.sh --system
```

### Step 2 — Check available disk space and model list

```bash
./llm/download-models.sh
```

This shows all models, their sizes, and download status. No download happens yet.

### Step 3 — Download models

Start with the smallest/highest-ROI model:
```bash
# Option A: download by individual model ID (recommended — control what you pull)
./llm/download-models.sh phi-3-mini
./llm/download-models.sh mistral-7b-instruct
./llm/download-models.sh deepseek-coder

# Option B: download only models referenced in routing.yaml
./llm/download-models.sh --routing

# Option C: download everything in the manifest
./llm/download-models.sh --all
```

Downloads resume if interrupted — `huggingface_hub` handles partial files.

### Step 4 — Validate environment

```bash
./llm/validate.sh
```

This prints a full routing table showing every active agent, which model it will use,
and whether that model file is present. Example output:

```
  AGENT                                    ROLE                 MODEL                     STATUS
  ---------------------------------------- -------------------- ------------------------- ---------------
  agent-code-review                        tester               deepseek-coder            local [ready]
  agent-explore-forseti                    software-developer   phi-3-mini                local [ready]
  ba-forseti                               business-analyst     mistral-7b-instruct       local [not downloaded]
  ceo-copilot                              ceo                  copilot                   copilot (external)
  dev-forseti                              software-developer   copilot                   copilot (external)
```

### Step 5 — Run a live inference test

```bash
./llm/validate.sh --test-run
```

This sends a minimal prompt to the first available model and confirms output is returned.

### Step 6 — Verify agent execution picks up local models

Run one agent cycle and confirm routing is working:
```bash
./scripts/agent-exec-once.sh
```

Check an outbox file — it will process normally. If the model was used, you will see
the session history file created at:
```
llm/cache/sessions/<SESSION_ID>.json
```

---

## Checking a Specific Agent's Routing

```bash
./llm/validate.sh --agent qa-forseti
./llm/validate.sh --agent agent-code-review
./llm/validate.sh --agent ceo-copilot
```

---

## Changing Which Model an Agent Uses

Edit `llm/routing.yaml`:
```yaml
agents:
  qa-forseti: mistral-7b-instruct   # override: use mistral instead of phi-3-mini
```

Changes take effect immediately on the next agent execution. No restart needed.

---

## Disabling Local LLM (Force Copilot for All Agents)

```bash
export LLM_DISABLE=1
```

Add to cron environment or `~/.bashrc` to make permanent. All agents will route to
Copilot CLI regardless of routing.yaml.

---

## Troubleshooting

### `llama-cpp-python` build fails
```bash
# Try installing a pre-built wheel instead:
pip install llama-cpp-python --prefer-binary
# Or force CPU build explicitly:
CMAKE_ARGS="-DLLAMA_BLAS=OFF" pip install llama-cpp-python
```

### Model download stalls or fails
```bash
# Re-run the download command — it resumes automatically:
./llm/download-models.sh phi-3-mini

# If HF Hub rate limits: set a token
export HF_TOKEN=your_token_here
./llm/download-models.sh phi-3-mini
```

### Agent falls back to Copilot unexpectedly
1. Check model file is present: `./llm/validate.sh --agent <agent-id>`
2. Check Python venv is activated or `LLM_PYTHON_BIN` is set
3. Run runner directly to see errors:
   ```bash
   source llm/.venv/bin/activate
   python3 llm/runner.py --dry-run
   python3 llm/runner.py --model llm/models/Phi-3-mini-4k-instruct-q4.gguf --prompt "Hello"
   ```

### Inference is extremely slow
- phi-3-mini is the fastest model; switch lighter agents to it in `routing.yaml`
- Increase CPU threads in `runner.py`: change `N_THREADS = 0` to the number of cores
- Use a more aggressively quantized model (Q2_K variants, ~1.5 GB each — add to manifest)

### Session history is corrupted
```bash
rm llm/cache/sessions/<SESSION_ID>.json
```
The session restarts fresh on next execution.

---

## File Reference

| File | Description |
|---|---|
| `llm/model-manifest.yaml` | Available models, HF sources, sizes, task tags |
| `llm/routing.yaml` | Agent/role → model assignment |
| `llm/requirements.txt` | Python package requirements (`pip install -r`) |
| `llm/lib/routing.py` | Shared routing resolution module (used by runner, validate, agent-exec) |
| `llm/runner.py` | Inference shim (drop-in for `copilot --silent`) |
| `llm/setup.sh` | One-time setup: deps + venv + validation |
| `llm/download-models.sh` | Download GGUF models from Hugging Face Hub |
| `llm/validate.sh` | Environment check + routing table + optional test run |
| `llm/models/` | Model weight files live here (.gitignored) |
| `llm/cache/sessions/` | Per-agent conversation history (.gitignored) |
| `llm/.venv/` | Python virtual environment (.gitignored) |
| `scripts/agent-exec-next.sh` | Core agent runner — contains routing shim (lines ~400–460) |
