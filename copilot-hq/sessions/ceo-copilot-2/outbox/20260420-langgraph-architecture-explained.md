# LangGraph Architecture in HQ — Complete Explanation

## What is LangGraph?

**LangGraph** is a library by LangChain that builds **stateful, cyclic computation graphs**. It's used in HQ to define the **orchestrator's tick pipeline** — a deterministic, sequential workflow that runs every 60 seconds to manage agent execution.

LangGraph is NOT a daemon itself — it's a **graph execution framework**. The actual daemon is the orchestrator loop script.

---

## How It All Works Together

### 1. **Daemon: orchestrator-loop.sh (The Wrapper)**

**Location:** `scripts/orchestrator-loop.sh`

**Behavior:**
- Runs as a **long-lived daemon process** (not triggered by cron)
- Starts once via crontab `@reboot` (survives reboots)
- Has a watchdog cron job (`*/5 * * * *`) that restarts it if dead
- Loops forever, executing one **tick** every 60 seconds

**Code structure:**
```bash
scripts/orchestrator-loop.sh start 60
  ↓
setsid spawn child process (detached from terminal)
  ↓
while true; do
  run_orchestrator_once()
  sleep remaining_time
done
```

### 2. **Ticket Execution: orchestrator/run.py (The Entry Point)**

**Location:** `orchestrator/run.py` (134KB, main orchestrator)

**Invocation:**
```bash
run_orchestrator_once() {
  python3 orchestrator/run.py --once \
    --agent-cap 8 \
    --kpi-interval 300 \
    --log-file inbox/responses/orchestrator-latest.log
}
```

**What it does:**
1. Reads state from the prior tick (kpi_last_run, release_cycle_last_run)
2. Sets up dependencies (bash command runners, dispatch functions, etc.)
3. Calls `run_tick()` from the LangGraph engine

**Key logic (simplified):**
```python
# Initialize state
state = {
    "log": [],
    "selected_agents": [],
    "agent_cap": 8,
    "kpi_interval": 300,
    "kpi_last_run": <from prior tick>,
    "release_cycle_last_run": <from prior tick>,
}

# Invoke the langgraph graph
result = run_tick(
    provider=copilot_provider,
    agent_cap=8,
    kpi_interval=300,
    kpi_last_run=<from state>,
    release_cycle_last_run=<from state>,
    deps=LangGraphDeps(...)
)

# State flows through the graph pipeline
# Returns updated state with log entries
```

---

## 3. **Graph Engine: orchestrator/runtime_graph/engine.py (The State Machine)**

**Location:** `orchestrator/runtime_graph/engine.py` (430 lines)

**Core concept:**
LangGraph defines a **StateGraph** — a directed acyclic graph (DAG) where:
- Each **node** is a processing step
- **Edges** define the sequence
- **State** flows from node to node, accumulating changes
- All nodes run sequentially, deterministically

**The HQ Tick Pipeline (9-node graph):**

```
consume_replies → dispatch_commands → release_cycle → coordinated_push 
  ↓                                                          ↓
pick_agents → exec_agents → health_check → kpi_monitor → publish
```

### Node Definitions

| Node | Owner | Purpose | Dispatch? | Time |
|------|-------|---------|-----------|------|
| **consume_replies** | Orchestrator | Pull Board replies from Drupal into agent inboxes | No (script) | Fast |
| **dispatch_commands** | Orchestrator | Route inbox/commands/*.md to PM/CEO inboxes | Yes (PM agent) | Fast |
| **release_cycle** | Orchestrator | Start missing release cycles; groom next release | Possibly (QA) | Medium |
| **coordinated_push** | Orchestrator | Check if all gates pass; dispatch push-ready | Possibly (PM) | Medium |
| **pick_agents** | Orchestrator | Scan all agent inboxes; pick highest ROI agent(s) | No (decision) | Fast |
| **exec_agents** | Provider (Copilot API) | Run agent-exec-next.sh for selected agents (parallel) | N/A | Slow (5-30s/agent) |
| **health_check** | Orchestrator | Detect stalled agents; auto-remediate (cooldown-gated) | Possibly (CEO) | Medium |
| **kpi_monitor** | Orchestrator | Detect release stagnation; dispatch escalation | Possibly (CEO) | Medium |
| **publish** | Orchestrator | Push tick telemetry to Drupal dashboard | No (telemetry) | Fast |

### State Flow Example

```python
state = {
    "ts": "2026-04-20T14:59:00Z",
    "log": [],
    "selected_agents": [],
    "agent_cap": 8,
}

# Step 1: consume_replies
state["log"].append({"step": "consume_replies", "rc": 0})
# state flows to dispatch_commands

# Step 2: dispatch_commands  
state["log"].append({"step": "dispatch_commands", "dispatched": [...], "rc": 0})
# state flows to release_cycle

# ... all 9 steps ...

# Final state has:
# {
#   "ts": "2026-04-20T14:59:00Z",
#   "log": [
#     {"step": "consume_replies", "rc": 0},
#     {"step": "dispatch_commands", "dispatched": [...], "rc": 0},
#     ...
#   ],
#   "selected_agents": ["pm-forseti", "dev-dungeoncrawler"],
# }
```

### LangGraph API Usage

```python
from langgraph.graph import StateGraph

# Create graph with dict state type
graph = StateGraph(dict)

# Add all 9 nodes
graph.add_node("consume_replies", consume_replies)
graph.add_node("dispatch_commands", dispatch_commands)
# ... etc ...

# Define sequence (linear pipeline)
graph.set_entry_point("consume_replies")
graph.add_edge("consume_replies", "dispatch_commands")
graph.add_edge("dispatch_commands", "release_cycle")
# ... etc ...
graph.set_finish_point("publish")

# Compile and invoke
executor = graph.compile()
result = executor.invoke(state)  # Runs all 9 nodes sequentially
```

---

## 4. **Dispatch & Inbox Model**

When a node (e.g., dispatch_commands) needs to **queue work** for an agent:

### Example: dispatch_commands node

```python
def dispatch_commands(s: Dict[str, Any]) -> Dict[str, Any]:
    deps.dispatch_commands_step(s["log"])
    return s
```

**Inside dispatch_commands_step (from run.py):**
```python
def dispatch_commands_step(agent_log):
    # 1. Find all command files in inbox/commands/
    for cmd_file in inbox/commands/*.md:
        
        # 2. Parse command (contains "pm: pm-forseti" or "work_item: feature-id")
        if "pm:" in content:
            # 3. Create inbox item in sessions/pm-forseti/inbox/<item_id>/
            # 4. Copy command.md to the item (THIS WAS BROKEN, WE FIXED IT)
            
            agent_log.append({
                "step": "dispatch_commands",
                "dispatched_to": "pm-forseti",
                "type": "pm_signoff",
            })
```

### Pick_agents node: Scan all inboxes

```python
def pick_agents(s):
    all_agents = deps.prioritized_agents()
    # Scan each agent's inbox for items
    # Score by ROI (roi.txt)
    # Select highest-ROI agents up to agent_cap (8)
    s["selected_agents"] = ["pm-forseti", "dev-dungeoncrawler"]
    s["log"].append({"step": "pick_agents", "selected": [...]})
    return s
```

### exec_agents node: Run Copilot API

```python
def exec_agents(s):
    selected = s["selected_agents"]  # e.g., ["pm-forseti", "dev-dungeoncrawler"]
    
    # Run agents in parallel (up to `workers` threads)
    for agent_id in selected:
        rc, out = provider.run_one(agent_id)  # Calls scripts/agent-exec-next.sh
        # agent-exec-next.sh:
        #   1. Pick highest-ROI inbox item
        #   2. Read command.md + README.md
        #   3. Build Copilot prompt
        #   4. Call Copilot API
        #   5. Write Status header + response to outbox
    
    s["log"].append({"step": "exec_agents", "ran": [...]})
    return s
```

---

## 5. **Triggering Patterns**

### Pattern A: Cron-less Daemon

**Not triggered by cron.** Instead:

1. **Boot-time start** (crontab @reboot):
   ```
   @reboot ... /scripts/orchestrator-loop.sh start 60
   ```
   - Starts the daemon on machine reboot

2. **Watchdog keeps it alive** (every 5 min):
   ```
   */5 * * * * /scripts/orchestrator-watchdog.sh
   ```
   - Checks if orchestrator-loop.sh process is alive
   - Restarts if dead

3. **Interval-gated steps** (inside the graph):
   - `release_cycle`: runs if 5+ min elapsed OR event signal fired
   - `kpi_monitor`: runs if 5 min elapsed OR release event signal fired
   - `health_check`: runs with cooldown to avoid hammering

### Pattern B: Event-Driven Optimization

Some steps can be **triggered early** by event signals:

```python
def release_cycle(s):
    _RC_SIGNALS = ("release-signoff-created", "release-cycle-advanced", ...)
    event_triggered = deps.has_events(*_RC_SIGNALS)
    interval_elapsed = (now - last_run) >= 300
    
    if event_triggered or interval_elapsed:
        # Run immediately (don't wait 5 min)
        deps.release_cycle_step(s["log"])
```

**Where do signals come from?**
- When pm-forseti runs `scripts/release-signoff.sh`, it creates the file `tmp/<event-signal>.txt`
- Orchestrator checks for these files at the start of each tick
- If found, it "consumes" the event (deletes the file) and triggers early execution

---

## 6. **Telemetry & Observability**

Every tick generates telemetry for the Drupal dashboard:

### langgraph-ticks.jsonl (append-only log)

```json
{
  "ts": "2026-04-20T14:59:00Z",
  "dry_run": false,
  "publish_enabled": true,
  "agent_cap": 8,
  "provider": "Copilot",
  "selected_agents": ["pm-forseti", "dev-dungeoncrawler"],
  "errors": [],
  "step_results": {
    "consume_replies": { "rc": 0 },
    "dispatch_commands": { "dispatched": [...], "rc": 0 },
    "release_cycle": { "rc": 0, "skipped": false, "trigger": "interval" },
    "coordinated_push": { "rc": 0 },
    "pick_agents": { "selected": [...] },
    "exec_agents": { "ran": [...], "workers": 4 },
    "health_check": { "rc": 0 },
    "kpi_monitor": { "rc": 0, "out": "...", "trigger": "interval" },
    "publish": { "rc": 0 },
    "summarize_tick": { "selected_agents": [...], "errors": [], "org_enabled": true }
  }
}
```

### langgraph-parity-latest.json (diagnostic)

Validates that:
- Steps ran in expected order
- selected_agents list is consistent
- No critical errors

---

## 7. **Why LangGraph? (Architecture Rationale)**

| Aspect | Benefit |
|--------|---------|
| **Deterministic sequencing** | Each tick is reproducible; state flows linearly |
| **Visibility** | Log every step + state changes → telemetry dashboard |
| **Extensibility** | Add new nodes without breaking existing ones |
| **Parallelization** | exec_agents runs agents in parallel safely (thread pool) |
| **Checkpointing** | (Future) Can checkpoint state between ticks |
| **Type safety** | State is a typed dict; easier to track changes |

---

## 8. **Comparison: Cron vs Daemon Loop**

| Aspect | Cron | Daemon Loop + LangGraph |
|--------|------|--------------------------|
| **Trigger** | Scheduled by system | Continuous; interval-gated steps |
| **Overhead** | High (new process per run) | Low (single process, no fork) |
| **Parallelism** | One job at a time | Multiple agents in parallel |
| **State sharing** | Via files only | In-memory dict between steps |
| **Visibility** | Logs scattered | Centralized tick log + parity checks |
| **Graceful shutdown** | N/A | Scripts respect .orchestrator-loop.pid |

**HQ's choice: Daemon loop + LangGraph** = low overhead + high observability.

---

## 9. **Summary Diagram**

```
┌─────────────────────────────────────────────────────────────┐
│ scripts/orchestrator-loop.sh (daemon process)                │
│ ├─ Loop interval: 60 seconds                                 │
│ └─ Survives reboots via @reboot crontab                      │
│    └─ Watchdog cron checks every 5 min                       │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ↓
           ┌─────────────────────────────┐
           │ orchestrator/run.py --once   │
           │ (entry point per tick)       │
           └──────────────┬────────────────┘
                         │
                         ↓
      ┌──────────────────────────────────────────┐
      │ LangGraph StateGraph (runtime_graph/)     │
      │                                          │
      │  consume_replies                         │
      │     ↓                                    │
      │  dispatch_commands  ← (queue PM work)   │
      │     ↓                                    │
      │  release_cycle      ← (check gates)     │
      │     ↓                                    │
      │  coordinated_push   ← (push trigger)    │
      │     ↓                                    │
      │  pick_agents        ← (scan inboxes)    │
      │     ↓                                    │
      │  exec_agents        ← (run agents)      │
      │     ↓                                    │
      │  health_check       ← (auto-remediate)  │
      │     ↓                                    │
      │  kpi_monitor        ← (detect stall)    │
      │     ↓                                    │
      │  publish            ← (Drupal telemetry)│
      └──────────────────────────────────────────┘
                         │
                         ↓
      ┌────────────────────────────────────────┐
      │ inbox/responses/                        │
      │ ├─ langgraph-ticks.jsonl (append-log)   │
      │ ├─ langgraph-parity-latest.json (diag)  │
      │ └─ orchestrator-latest.log (summary)    │
      └────────────────────────────────────────┘
```

---

## 10. **Key Files**

- **`scripts/orchestrator-loop.sh`**: Daemon wrapper; loop every 60s
- **`orchestrator/run.py`**: Entry point; orchestrates tick
- **`orchestrator/runtime_graph/engine.py`**: LangGraph StateGraph definition
- **`inbox/responses/langgraph-ticks.jsonl`**: Tick telemetry (for dashboard)
- **`inbox/responses/orchestrator-latest.log`**: Summary of latest tick
