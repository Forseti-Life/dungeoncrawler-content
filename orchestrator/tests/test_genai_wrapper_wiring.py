import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
WRAPPER_SCRIPT = ROOT / "scripts" / "genai-wrapper.sh"
WRAPPER_PY = ROOT / "llm" / "genai_wrapper.py"
COPILOT_LOOP = ROOT / "scripts" / "1-copilot.sh"
BEDROCK_RUNNER = ROOT / "llm" / "bedrock_runner.py"


class TestGenAIWrapperWiring(unittest.TestCase):
    def test_wrapper_files_exist(self):
        self.assertTrue(WRAPPER_SCRIPT.exists())
        self.assertTrue(WRAPPER_PY.exists())

    def test_manual_loop_routes_chat_and_bedrock_through_wrapper(self):
        source = COPILOT_LOOP.read_text(encoding="utf-8")
        self.assertIn('GENAI_WRAPPER="${GENAI_WRAPPER:-$ROOT_DIR/scripts/genai-wrapper.sh}"', source)
        self.assertIn("--backend copilot-chat", source)
        self.assertIn("--backend script", source)

    def test_bedrock_runner_accepts_wrapper_telemetry_fields(self):
        source = BEDROCK_RUNNER.read_text(encoding="utf-8")
        self.assertIn('parser.add_argument("--source"', source)
        self.assertIn('parser.add_argument("--operation"', source)


if __name__ == "__main__":
    unittest.main(verbosity=2)
