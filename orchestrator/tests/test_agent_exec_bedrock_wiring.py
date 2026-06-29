import unittest
from pathlib import Path


SCRIPT = Path(__file__).resolve().parents[2] / "scripts" / "agent-exec-next.sh"
ROUTING = Path(__file__).resolve().parents[2] / "llm" / "routing.yaml"


class TestAgentExecBedrockWiring(unittest.TestCase):
    def test_tester_routing_uses_current_default(self):
        source = ROUTING.read_text(encoding="utf-8")
        self.assertIn("  tester: copilot", source)
        self.assertNotIn("  tester: bedrock", source)

    def test_run_bedrock_uses_shared_genai_wrapper(self):
        source = SCRIPT.read_text(encoding="utf-8")
        self.assertIn('GENAI_WRAPPER="${GENAI_WRAPPER:-$ROOT_DIR/scripts/genai-wrapper.sh}"', source)
        self.assertIn('BEDROCK_RUNNER="$ROOT_DIR/llm/bedrock_runner.py"', source)
        self.assertIn('"$GENAI_WRAPPER" \\', source)
        self.assertIn('--backend bedrock', source)
        self.assertNotIn('"$BEDROCK_ASSIST_SCRIPT" "$site"', source)

    def test_prompt_does_not_unconditionally_promise_tool_access(self):
        source = SCRIPT.read_text(encoding="utf-8")
        self.assertIn("Do NOT assume live tool access.", source)
        self.assertNotIn("You have full read/write tool access (--allow-all)", source)

    def test_bedrock_supports_mediated_bash_tools(self):
        source = SCRIPT.read_text(encoding="utf-8")
        self.assertIn("This execution HAS live mediated bash access", source)
        self.assertIn("<<TOOL:bash>>", source)
        self.assertIn("bedrock_execute_bash()", source)


if __name__ == "__main__":
    unittest.main(verbosity=2)
