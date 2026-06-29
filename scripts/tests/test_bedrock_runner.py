import sys
import tempfile
import unittest
import json
from pathlib import Path
from unittest import mock


REPO_ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(REPO_ROOT))

from llm import bedrock_runner  # noqa: E402


class _FakeClient:
    def __init__(self, response_text: str, usage: dict | None = None):
        self.response_text = response_text
        self.usage = usage or {}
        self.calls = []

    def converse(self, **kwargs):
        self.calls.append(kwargs)
        return {
            "output": {
                "message": {
                    "content": [
                        {"text": self.response_text},
                    ]
                }
            },
            "usage": self.usage,
        }


class TestBedrockRunner(unittest.TestCase):
    def test_run_bedrock_uses_session_history_and_saves_response(self):
        with tempfile.TemporaryDirectory() as td:
            old_root = bedrock_runner.REPO_ROOT
            try:
                bedrock_runner.REPO_ROOT = Path(td)
                session_id = "test-session"
                bedrock_runner.save_session(session_id, [{"role": "assistant", "content": "Prior reply"}])

                usage_file = Path(td) / "langgraph-llm-usage.jsonl"
                fake_client = _FakeClient("- Status: done\n- Summary: ok", {"inputTokens": 12, "outputTokens": 7})
                with mock.patch("boto3.client", return_value=fake_client), mock.patch.dict("os.environ", {
                    "LANGGRAPH_LLM_USAGE_FILE": str(usage_file),
                }, clear=False):
                    text = bedrock_runner.run_bedrock(
                        "agent-bedrock",
                        session_id,
                        "Prompt body",
                        model_id="test-model",
                        max_tokens=321,
                        no_history=False,
                        region_name="us-east-1",
                    )

                self.assertEqual(text, "- Status: done\n- Summary: ok")
                self.assertEqual(len(fake_client.calls), 1)
                call = fake_client.calls[0]
                self.assertEqual(call["modelId"], "test-model")
                self.assertEqual(call["inferenceConfig"]["maxTokens"], 321)
                self.assertEqual(call["messages"][0]["role"], "assistant")
                self.assertEqual(call["messages"][-1]["role"], "user")
                self.assertEqual(call["messages"][-1]["content"][0]["text"], "Prompt body")

                saved = bedrock_runner.load_session(session_id)
                self.assertEqual(saved[-2]["role"], "user")
                self.assertEqual(saved[-1]["role"], "assistant")
                usage_rows = [json.loads(line) for line in usage_file.read_text(encoding="utf-8").splitlines() if line.strip()]
                self.assertEqual(len(usage_rows), 1)
                self.assertEqual(usage_rows[0]["backend"], "bedrock")
                self.assertEqual(usage_rows[0]["agent_id"], "agent-bedrock")
                self.assertEqual(usage_rows[0]["token_visibility"], "exact")
                self.assertEqual(usage_rows[0]["exact_input_tokens"], 12)
                self.assertEqual(usage_rows[0]["exact_output_tokens"], 7)
            finally:
                bedrock_runner.REPO_ROOT = old_root

    def test_run_bedrock_raises_on_empty_response(self):
        with tempfile.TemporaryDirectory() as td:
            old_root = bedrock_runner.REPO_ROOT
            try:
                bedrock_runner.REPO_ROOT = Path(td)
                fake_client = _FakeClient("")
                with mock.patch("boto3.client", return_value=fake_client):
                    with self.assertRaisesRegex(RuntimeError, "empty response"):
                        bedrock_runner.run_bedrock(
                            "agent-bedrock",
                            "test-session",
                            "Prompt body",
                            model_id="test-model",
                            max_tokens=100,
                            no_history=True,
                            region_name="us-east-1",
                        )
            finally:
                bedrock_runner.REPO_ROOT = old_root


if __name__ == "__main__":
    unittest.main(verbosity=2)
