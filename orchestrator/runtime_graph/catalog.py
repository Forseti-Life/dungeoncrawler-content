from __future__ import annotations

from typing import Any, Dict, List

from orchestrator.runtime_graph.consume_replies import consume_replies_step_catalog


HQ_ORCHESTRATOR_TICK_NODE_ORDER = [
    "consume_replies",
    "dispatch_commands",
    "release_cycle",
    "coordinated_push",
    "pick_agents",
    "exec_agents",
    "health_check",
    "kpi_monitor",
    "publish",
]


def runtime_flow_catalog() -> List[Dict[str, Any]]:
    transitions = []
    for index in range(len(HQ_ORCHESTRATOR_TICK_NODE_ORDER) - 1):
        transitions.append(
            {
                "from_node": HQ_ORCHESTRATOR_TICK_NODE_ORDER[index],
                "to_node": HQ_ORCHESTRATOR_TICK_NODE_ORDER[index + 1],
                "kind": "direct",
                "condition": "",
            }
        )

    return [
        {
            "id": "hq_orchestrator_tick",
            "label": "HQ Orchestrator Tick",
            "description": "Primary LangGraph control-plane flow that coordinates the HQ tick pipeline and worker selection.",
            "owner": "ceo-copilot-2",
            "status": "active",
            "graph_type": "state_graph",
            "primary_section": "run",
            "default_entrypoint": "consume_replies",
            "version": "runtime-observed",
            "source": "runtime_graph",
            "state_schema_summary": "Tick state tracks selected agents, step results, provider metadata, control-plane toggles, and structured consume_replies telemetry.",
            "nodes": list(HQ_ORCHESTRATOR_TICK_NODE_ORDER),
            "routing_rules": [
                "Execute top-level tick nodes in pipeline order.",
                "Allow internal subgraphs to short-circuit locally while preserving top-level tick step parity.",
            ],
            "tools": ["hq_artifacts", "runtime_ticks", "agent_selection", "publish_contract"],
            "prompt_notes": "Runtime-derived from orchestrator/runtime_graph; the flow page should reflect graph source, not a hand-maintained PHP mirror.",
            "transitions": transitions,
            "node_breakdown": [
                {
                    "parent_node": "consume_replies",
                    "internal_step": step["id"],
                    "purpose": step["purpose"],
                    "state_effect": step["state_effect"],
                }
                for step in consume_replies_step_catalog()
            ],
        }
    ]
