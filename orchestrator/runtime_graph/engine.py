from __future__ import annotations

import concurrent.futures
import json
import os
import pathlib
from dataclasses import dataclass
from datetime import datetime, timezone
from typing import Any, Callable, Dict, List, Tuple

from langgraph.graph import StateGraph  # type: ignore
from orchestrator.runtime_graph.catalog import HQ_ORCHESTRATOR_TICK_NODE_ORDER
from orchestrator.runtime_graph.consume_replies import run_consume_replies


TickResult = Tuple[Dict[str, Any], int, int]
RunFn = Callable[..., Tuple[int, str]]
DispatchFn = Callable[[List[Any]], None]
ReleaseCycleFn = Callable[[List[Any]], None]
CoordinatedPushFn = Callable[[List[Any]], None]
PrioritizedAgentsFn = Callable[[], List[Any]]
HealthCheckFn = Callable[[Any, List[Any]], None]
NowTsFn = Callable[[], int]


HasEventsFn = Callable[..., bool]
ConsumeEventsFn = Callable[..., None]


@dataclass(frozen=True)
class LangGraphDeps:
    run_cmd: RunFn
    dispatch_commands_step: DispatchFn
    release_cycle_step: ReleaseCycleFn
    coordinated_push_step: CoordinatedPushFn
    prioritized_agents: PrioritizedAgentsFn
    health_check_step: HealthCheckFn
    now_ts: NowTsFn
    kpi_monitor_cmd: List[str]
    has_events: HasEventsFn = lambda *_: False      # noqa: E731
    consume_events: ConsumeEventsFn = lambda *_: None  # noqa: E731


import subprocess as _subprocess


def _refresh_feature_progress(hq_root: pathlib.Path) -> None:
    """Regenerate dashboards/FEATURE_PROGRESS.md from features/*/feature.md. Best-effort."""
    script = hq_root / "scripts" / "generate-feature-progress.py"
    if script.exists():
        try:
            _subprocess.run(
                ["python3", str(script)],
                cwd=str(hq_root),
                timeout=30,
                check=False,
                capture_output=True,
            )
        except Exception:  # noqa: BLE001
            pass


def _exec_worker_limit(agent_cap: int) -> int:
    """Determine how many agent executions may run in parallel this tick."""
    cap = max(1, int(agent_cap))

    explicit = os.environ.get("ORCHESTRATOR_EXEC_WORKERS", "").strip()
    if explicit.isdigit():
        return max(1, min(cap, int(explicit)))

    shared = os.environ.get("AGENT_EXEC_MAX_CONCURRENT", "").strip()
    if shared.isdigit():
        return max(1, min(cap, int(shared)))

    backend = os.environ.get("HQ_AGENTIC_BACKEND", "auto").strip().lower()
    if backend == "bedrock":
        bedrock = os.environ.get("AGENT_EXEC_MAX_CONCURRENT_BEDROCK", "").strip()
        if bedrock.isdigit():
            return max(1, min(cap, int(bedrock)))
        return min(cap, 4)

    return min(cap, 6)


def _write_tick_telemetry(state: Dict[str, Any]) -> None:
    """Append a tick record to langgraph-ticks.jsonl for the Drupal dashboard."""
    hq_root = pathlib.Path(os.environ.get("COPILOT_HQ_ROOT", str(pathlib.Path(__file__).parent.parent.parent)))
    responses_dir = hq_root / "inbox" / "responses"
    responses_dir.mkdir(parents=True, exist_ok=True)

    log_entries: List[Dict[str, Any]] = state.get("log", [])

    # Build step_results dict keyed by step name (for dashboard per-node display).
    step_results: Dict[str, Any] = {}
    for entry in log_entries:
        step_name = entry.get("step")
        if step_name:
            step_results[step_name] = {k: v for k, v in entry.items() if k != "step"}

    # Collect top-level errors from any step with non-zero rc or explicit error key.
    errors: List[str] = []
    for entry in log_entries:
        if entry.get("rc", 0) != 0:
            errors.append(f"step {entry.get('step', '?')}: rc={entry['rc']}")
        if "error" in entry:
            errors.append(f"step {entry.get('step', '?')}: {entry['error']}")

    # Synthetic summarize_tick step expected by the troubleshooting panel.
    step_results["summarize_tick"] = {
        "selected_agents": state.get("selected_agents", []),
        "org_enabled": True,
        "errors": errors,
    }

    tick_record = {
        "ts": state.get("ts"),
        "dry_run": not state.get("publish_enabled", True),
        "publish_enabled": state.get("publish_enabled", True),
        "agent_cap": state.get("agent_cap", 0),
        "provider": str(state.get("provider", "")),
        "selected_agents": state.get("selected_agents", []),
        "errors": errors,
        "step_results": step_results,
    }

    ticks_file = responses_dir / "langgraph-ticks.jsonl"
    with open(ticks_file, "a", encoding="utf-8") as fh:
        fh.write(json.dumps(tick_record) + "\n")

    # Keep at most 500 lines to prevent unbounded growth.
    try:
        lines = ticks_file.read_text(encoding="utf-8").splitlines()
        if len(lines) > 500:
            ticks_file.write_text("\n".join(lines[-500:]) + "\n", encoding="utf-8")
    except OSError:
        pass

    # Build parity record with schema expected by DashboardController::langGraphParityHealth().
    expected_steps = list(HQ_ORCHESTRATOR_TICK_NODE_ORDER)
    actual_steps = [entry.get("step") for entry in log_entries if "step" in entry]
    steps_match = actual_steps == expected_steps

    # selected_agents parity: compare pick_agents log entry vs final selected_agents.
    picked = next(
        (entry.get("selected", []) for entry in log_entries if entry.get("step") == "pick_agents"),
        [],
    )
    agents_match = sorted(picked) == sorted(state.get("selected_agents", []))

    all_ok = all(entry.get("rc", 0) == 0 for entry in log_entries if "rc" in entry)

    parity_errors: List[str] = list(errors)
    if not steps_match:
        parity_errors.append(
            f"step order mismatch: expected={expected_steps} actual={actual_steps}"
        )
    if not agents_match:
        parity_errors.append(
            f"selected_agents mismatch: picked={sorted(picked)} actual={sorted(state.get('selected_agents', []))}"
        )

    parity_record = {
        "generated_at": state.get("ts"),
        "parity_ok": bool(all_ok and steps_match and agents_match),
        "selected_agents": {
            "match": agents_match,
            "actual": state.get("selected_agents", []),
        },
        "steps": {
            "match": steps_match,
            "expected": expected_steps,
            "actual": actual_steps,
        },
        "errors": parity_errors,
    }
    parity_file = responses_dir / "langgraph-parity-latest.json"
    parity_file.write_text(json.dumps(parity_record, indent=2) + "\n", encoding="utf-8")

    # Refresh FEATURE_PROGRESS.md so the dashboard always reflects current feature state.
    _refresh_feature_progress(hq_root)


def run_tick(
    provider: Any,
    *,
    agent_cap: int,
    publish_enabled: bool,
    kpi_interval: int,
    kpi_last_run: int,
    release_cycle_interval: int,
    release_cycle_last_run: int,
    deps: LangGraphDeps,
    kpi_max_output_chars: int = 500,
) -> TickResult:
    state: Dict[str, Any] = {
        "ts": datetime.now(timezone.utc).isoformat(),
        "log": [],
        "selected_agents": [],
        "agent_cap": max(0, int(agent_cap)),
        "publish_enabled": bool(publish_enabled),
        "kpi_interval": max(0, int(kpi_interval)),
        "kpi_last_run": int(kpi_last_run),
        "release_cycle_interval": max(0, int(release_cycle_interval)),
        "release_cycle_last_run": int(release_cycle_last_run),
        "provider": type(provider).__name__,
    }

    def consume_replies(s: Dict[str, Any]) -> Dict[str, Any]:
        summary = run_consume_replies(
            repo_root=pathlib.Path(
                os.environ.get(
                    "COPILOT_HQ_ROOT",
                    str(pathlib.Path(__file__).resolve().parent.parent.parent),
                )
            ),
            run_cmd=deps.run_cmd,
        )
        s["log"].append({"step": "consume_replies", **summary})
        return s

    def dispatch_commands(s: Dict[str, Any]) -> Dict[str, Any]:
        deps.dispatch_commands_step(s["log"])
        return s

    def release_cycle(s: Dict[str, Any]) -> Dict[str, Any]:
        release_signals = (
            "release-signoff-created",
            "release-cycle-advanced",
            "gate2-approved",
            "coordinated-push-done",
        )
        event_triggered = deps.has_events(*release_signals)
        interval_elapsed = (deps.now_ts() - s["release_cycle_last_run"]) >= s["release_cycle_interval"]
        should_run = event_triggered or interval_elapsed
        s["_release_cycle_should_run"] = should_run
        s["_release_cycle_trigger"] = "event" if event_triggered else "interval" if interval_elapsed else "skipped"

        if event_triggered:
            deps.consume_events(*release_signals)

        if should_run:
            deps.release_cycle_step(s["log"])
            s["release_cycle_last_run"] = deps.now_ts()
        else:
            s["log"].append({"step": "release_cycle", "skipped": True})
        return s

    def coordinated_push(s: Dict[str, Any]) -> Dict[str, Any]:
        if s.get("_release_cycle_should_run"):
            deps.coordinated_push_step(s["log"])
        else:
            s["log"].append({"step": "coordinated_push", "skipped": True})
        return s

    def pick_agents(s: Dict[str, Any]) -> Dict[str, Any]:
        all_agents = deps.prioritized_agents()
        # Only consider CEO for bonus slot if agent_cap > 0
        ceo_agents = []
        if s["agent_cap"] > 0:
            ceo_agents = [a for a in all_agents if str(getattr(a, "agent_id", "")).startswith("ceo-copilot")][:1]
        
        other_agents = [a for a in all_agents if not str(getattr(a, "agent_id", "")).startswith("ceo-copilot")]
        # CEO gets a guaranteed bonus slot that does NOT reduce the team cap.
        # This restores one full team slot that was previously consumed by the
        # CEO reservation (ISSUE-003 fix).
        other_cap = max(0, s["agent_cap"])
        # Respect the ordering returned by prioritized_agents(). It already
        # encodes: current-release first, then spare-capacity spillover into
        # next-release prep, then other work.
        selected_other = other_agents[:other_cap]

        agents = ceo_agents + selected_other
        selected = [str(getattr(a, "agent_id", "")) for a in agents]
        exec_queue = [str(getattr(a, "agent_id", "")) for a in (ceo_agents + other_agents)]
        current_release = [str(getattr(a, "agent_id", "")) for a in selected_other if getattr(a, "has_release_work", False)]
        next_release = [str(getattr(a, "agent_id", "")) for a in selected_other if getattr(a, "has_next_release_work", False)]
        s["selected_agents"] = selected
        s["_agent_exec_queue"] = exec_queue

        # ISSUE-008: warn when per-tick agent coverage falls below 20%.
        # At 4 workers / 48 agents = 8% coverage; this fires a visible log line
        # so operators know the scheduling ratio is degraded.
        queued_count = len(all_agents)
        if queued_count > 0:
            coverage_pct = 100 * len(agents) / queued_count
            if coverage_pct < 20:
                print(
                    f"COVERAGE-WARN: scheduling {len(agents)}/{queued_count} "
                    f"queued agents this tick ({coverage_pct:.0f}% coverage); "
                    f"consider raising ORCHESTRATOR_AGENT_CAP or AGENT_EXEC_MAX_CONCURRENT_BEDROCK"
                )

        s["log"].append({
            "step": "pick_agents",
            "selected": selected,
            "exec_queue_depth": len(exec_queue),
            "release_priority": current_release,
            "next_release_spillover": next_release,
            "queued_agents": queued_count,
            "coverage_pct": round(100 * len(agents) / queued_count, 1) if queued_count else 100,
        })
        return s

    def exec_agents(s: Dict[str, Any]) -> Dict[str, Any]:
        ran: List[Dict[str, Any]] = []
        selected_agents = [str(agent_id) for agent_id in (s.get("selected_agents") or [])]
        exec_queue = [str(agent_id) for agent_id in (s.pop("_agent_exec_queue", None) or selected_agents)]
        if not exec_queue:
            s["log"].append({"step": "exec_agents", "ran": ran, "workers": 0})
            return s

        # AGENT_EXEC_BURST: how many inbox items each agent may process per tick.
        # Burst > 1 reduces queue-drain latency for agents with deep inboxes.
        # Each additional run processes the next inbox item; agent-exec-next.sh
        # exits cleanly (rc=0) when the inbox is empty, so over-bursting is safe.
        burst = max(1, int(os.environ.get("AGENT_EXEC_BURST", "1")))

        def _run_agent_burst(agent_id: str) -> Dict[str, Any]:
            run_results: List[int] = []
            for _ in range(burst):
                rc_exec, _ = provider.run_one(agent_id)
                run_results.append(rc_exec)
                if rc_exec != 0:
                    break  # stop burst on failure
            return {"agent": agent_id, "rc": run_results[-1], "runs": len(run_results)}

        # ISSUE-005: Start kpi_monitor logic concurrently while agents execute.
        # kpi_monitor only reads/writes files — safe to run in parallel with exec.
        # Results are stashed in state so the kpi_monitor node can consume them.
        _KPI_SIGNALS_EXEC = (
            "release-cycle-advanced",
            "coordinated-push-done",
            "gate2-approved",
        )
        kpi_future: "concurrent.futures.Future[Dict[str, Any]] | None" = None
        kpi_pool: "concurrent.futures.ThreadPoolExecutor | None" = None
        kpi_event_triggered = deps.has_events(*_KPI_SIGNALS_EXEC)
        kpi_interval_elapsed = (deps.now_ts() - s["kpi_last_run"]) >= s["kpi_interval"]
        if kpi_event_triggered or kpi_interval_elapsed:
            deps.consume_events(*_KPI_SIGNALS_EXEC)
            kpi_trigger = "event" if kpi_event_triggered else "interval"
            def _kpi_task() -> Dict[str, Any]:
                rc, out = deps.run_cmd(deps.kpi_monitor_cmd, timeout=300)
                return {"rc": rc, "out": out, "trigger": kpi_trigger}
            kpi_pool = concurrent.futures.ThreadPoolExecutor(max_workers=1)
            kpi_future = kpi_pool.submit(_kpi_task)

        workers = min(_exec_worker_limit(max(len(selected_agents), 1)), len(exec_queue))
        with concurrent.futures.ThreadPoolExecutor(max_workers=workers) as pool:
            future_map: Dict[concurrent.futures.Future[Dict[str, Any]], str] = {}
            results: Dict[str, Dict[str, Any]] = {}
            queue_iter = iter(exec_queue)

            def _submit_next_agent() -> bool:
                try:
                    agent_id = next(queue_iter)
                except StopIteration:
                    return False
                future_map[pool.submit(_run_agent_burst, agent_id)] = agent_id
                return True

            for _ in range(workers):
                if not _submit_next_agent():
                    break

            while future_map:
                future = next(concurrent.futures.as_completed(tuple(future_map.keys())))
                agent_id = future_map.pop(future)
                results[agent_id] = future.result()
                _submit_next_agent()

        for agent_id in exec_queue:
            ran.append(results.get(agent_id, {"agent": agent_id, "rc": 1, "runs": 0}))
        s["log"].append({
            "step": "exec_agents",
            "ran": ran,
            "workers": workers,
            "selected_count": len(selected_agents),
            "queue_depth": len(exec_queue),
        })

        # Collect kpi result — it ran concurrently during agent execution.
        if kpi_future is not None and kpi_pool is not None:
            try:
                kpi_result = kpi_future.result(timeout=60)
            except Exception:
                kpi_result = {"rc": 1, "out": "", "trigger": "unknown"}
            finally:
                kpi_pool.shutdown(wait=False)
            s["_kpi_prefetched"] = kpi_result
            s["kpi_last_run"] = deps.now_ts()

        return s

    def health_check(s: Dict[str, Any]) -> Dict[str, Any]:
        deps.health_check_step(provider, s["log"])
        return s

    def kpi_monitor(s: Dict[str, Any]) -> Dict[str, Any]:
        # If exec_agents already ran kpi concurrently, consume the pre-fetched result.
        prefetched = s.pop("_kpi_prefetched", None)
        if prefetched is not None:
            rc_kpi = prefetched["rc"]
            out_kpi = prefetched["out"]
            trigger = prefetched["trigger"]
            s["log"].append({"step": "kpi_monitor", "rc": rc_kpi, "out": out_kpi[:kpi_max_output_chars], "trigger": trigger})
            if "HANDOFF-GAP" in out_kpi:
                print(f"AUTO-HANDOFF: detected {out_kpi.count('HANDOFF-GAP')} HANDOFF-GAP state(s)")
            return s

        # Bypass the interval gate when any release lifecycle event signal fired.
        _KPI_SIGNALS = (
            "release-cycle-advanced",
            "coordinated-push-done",
            "gate2-approved",
        )
        event_triggered = deps.has_events(*_KPI_SIGNALS)
        interval_elapsed = (deps.now_ts() - s["kpi_last_run"]) >= s["kpi_interval"]

        if event_triggered or interval_elapsed:
            trigger = "event" if event_triggered else "interval"
            deps.consume_events(*_KPI_SIGNALS)
            rc_kpi, out_kpi = deps.run_cmd(deps.kpi_monitor_cmd, timeout=300)
            s["log"].append({"step": "kpi_monitor", "rc": rc_kpi, "out": out_kpi[:kpi_max_output_chars], "trigger": trigger})
            if "HANDOFF-GAP" in out_kpi:
                print(f"AUTO-HANDOFF: detected {out_kpi.count('HANDOFF-GAP')} HANDOFF-GAP state(s)")
            s["kpi_last_run"] = deps.now_ts()
        else:
            s["log"].append({"step": "kpi_monitor", "skipped": True})
        return s

    def publish(s: Dict[str, Any]) -> Dict[str, Any]:
        if s["publish_enabled"]:
            # Fire-and-forget: Drupal telemetry sync should not block the next tick.
            # The 1200s timeout previously caused up to 20-minute tick stalls (ISSUE-004).
            proc = _subprocess.Popen(
                ["bash", "scripts/publish-forseti-agent-tracker.sh"],
                cwd=str(pathlib.Path(os.environ.get("COPILOT_HQ_ROOT", str(pathlib.Path(__file__).parent.parent.parent)))),
                stdout=_subprocess.DEVNULL,
                stderr=_subprocess.DEVNULL,
            )
            s["log"].append({"step": "publish", "rc": 0, "bg_pid": proc.pid, "note": "fire-and-forget"})
        else:
            s["log"].append({"step": "publish", "skipped": True})

        # Write tick telemetry for the Drupal LangGraph dashboard.
        _write_tick_telemetry(s)

        return s

    graph = StateGraph(dict)
    graph.add_node("consume_replies", consume_replies)
    graph.add_node("dispatch_commands", dispatch_commands)
    graph.add_node("release_cycle", release_cycle)
    graph.add_node("coordinated_push", coordinated_push)
    graph.add_node("pick_agents", pick_agents)
    graph.add_node("exec_agents", exec_agents)
    graph.add_node("health_check", health_check)
    graph.add_node("kpi_monitor", kpi_monitor)
    graph.add_node("publish", publish)
    graph.set_entry_point("consume_replies")
    graph.add_edge("consume_replies", "dispatch_commands")
    graph.add_edge("dispatch_commands", "release_cycle")
    graph.add_edge("release_cycle", "coordinated_push")
    graph.add_edge("coordinated_push", "pick_agents")
    graph.add_edge("pick_agents", "exec_agents")
    graph.add_edge("exec_agents", "health_check")
    graph.add_edge("health_check", "kpi_monitor")
    graph.add_edge("kpi_monitor", "publish")
    graph.set_finish_point("publish")

    result = graph.compile().invoke(state)
    selected = result.get("selected_agents") or []
    print(f"tick: agents={','.join(selected) if selected else '-'}")
    return (
        result,
        int(result.get("kpi_last_run", kpi_last_run)),
        int(result.get("release_cycle_last_run", release_cycle_last_run)),
    )
