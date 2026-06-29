import os
import tempfile
import unittest
from pathlib import Path
from unittest import mock

from orchestrator import langgraph_request_paths


class TestLangGraphRequestPaths(unittest.TestCase):
    def test_private_root_override_wins_for_all_artifacts(self):
        with tempfile.TemporaryDirectory() as td:
            with mock.patch.dict(os.environ, {
                "DRUPAL_LANGGRAPH_PRIVATE_ROOT": td,
                "FORSETI_ROOT": "/tmp/forseti-root-ignored",
            }, clear=False):
                self.assertEqual(langgraph_request_paths.control_requests_root(), Path(td) / "control-requests")
                self.assertEqual(langgraph_request_paths.checkpoint_replays_root(), Path(td) / "checkpoint-replays")
                self.assertEqual(langgraph_request_paths.flow_versions_root(), Path(td) / "flow-versions")
                self.assertEqual(langgraph_request_paths.release_requests_root(), Path(td) / "release-requests")
                self.assertEqual(langgraph_request_paths.promoted_versions_root(), Path(td) / "promoted-versions")

    def test_default_private_root_is_used_when_present(self):
        with tempfile.TemporaryDirectory() as td:
            private_root = Path(td) / "drupal_langgraph"
            private_root.mkdir()
            with mock.patch.dict(os.environ, {}, clear=True):
                with mock.patch.object(langgraph_request_paths, "DEFAULT_PRIVATE_ROOT", private_root):
                    self.assertEqual(langgraph_request_paths.control_requests_root(), private_root / "control-requests")
                    self.assertEqual(langgraph_request_paths.checkpoint_replays_root(), private_root / "checkpoint-replays")
                    self.assertEqual(langgraph_request_paths.flow_versions_root(), private_root / "flow-versions")
                    self.assertEqual(langgraph_request_paths.release_requests_root(), private_root / "release-requests")
                    self.assertEqual(langgraph_request_paths.promoted_versions_root(), private_root / "promoted-versions")

    def test_forseti_root_fallback_matches_drupal_service_contract(self):
        with tempfile.TemporaryDirectory() as td:
            forseti_root = Path(td) / "forseti"
            fallback_root = forseti_root / "tmp" / "langgraph-control-requests"
            with mock.patch.dict(os.environ, {"FORSETI_ROOT": str(forseti_root)}, clear=True):
                with mock.patch.object(langgraph_request_paths, "DEFAULT_PRIVATE_ROOT", Path(td) / "missing-private-root"):
                    self.assertEqual(langgraph_request_paths.control_requests_root(), fallback_root)
                    self.assertEqual(langgraph_request_paths.checkpoint_replays_root(), fallback_root / "checkpoint-replays")
                    self.assertEqual(langgraph_request_paths.flow_versions_root(), fallback_root / "versions")
                    self.assertEqual(langgraph_request_paths.release_requests_root(), fallback_root / "release-requests")
                    self.assertEqual(langgraph_request_paths.promoted_versions_root(), fallback_root / "promoted-versions")


if __name__ == "__main__":
    unittest.main()
