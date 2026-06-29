import json
import subprocess
import unittest
from pathlib import Path

from orchestrator.runtime_graph.catalog import HQ_ORCHESTRATOR_TICK_NODE_ORDER, runtime_flow_catalog


class TestRuntimeFlowCatalog(unittest.TestCase):
    def test_runtime_catalog_matches_tick_node_order(self):
        flow = next(item for item in runtime_flow_catalog() if item["id"] == "hq_orchestrator_tick")
        self.assertEqual(flow["nodes"], HQ_ORCHESTRATOR_TICK_NODE_ORDER)
        self.assertEqual(flow["default_entrypoint"], "consume_replies")
        self.assertEqual(len(flow["transitions"]), len(HQ_ORCHESTRATOR_TICK_NODE_ORDER) - 1)
        self.assertEqual(flow["transitions"][0]["from_node"], "consume_replies")
        self.assertEqual(flow["transitions"][-1]["to_node"], "publish")
        self.assertGreaterEqual(len(flow["node_breakdown"]), 1)

    def test_export_flow_catalog_script_outputs_json(self):
        script = Path(__file__).resolve().parent.parent / "runtime_graph" / "export_flow_catalog.py"
        proc = subprocess.run(
            ["python3", str(script)],
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE,
            text=True,
            check=False,
        )
        self.assertEqual(proc.returncode, 0, proc.stderr)
        payload = json.loads(proc.stdout)
        self.assertIn("flows", payload)
        flow = next(item for item in payload["flows"] if item["id"] == "hq_orchestrator_tick")
        self.assertEqual(flow["nodes"], HQ_ORCHESTRATOR_TICK_NODE_ORDER)
        self.assertEqual(len(flow["transitions"]), len(HQ_ORCHESTRATOR_TICK_NODE_ORDER) - 1)


if __name__ == "__main__":
    unittest.main()
