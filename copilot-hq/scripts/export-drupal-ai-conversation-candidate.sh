#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
COPILOT_HQ_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
MONOREPO_ROOT="$(cd "$COPILOT_HQ_ROOT/.." && pwd)"
SOURCE_DIR="${2:-$MONOREPO_ROOT/sites/forseti/web/modules/custom/ai_conversation}"
TARGET_DIR="${1:-}"
OVERLAY_DIR="$COPILOT_HQ_ROOT/templates/open-source/drupal-ai-conversation/overlay"

if [ -z "$TARGET_DIR" ]; then
  echo "Usage: $0 <target-dir> [source-dir]" >&2
  exit 2
fi

if [ ! -d "$SOURCE_DIR" ]; then
  echo "ERROR: source directory not found: $SOURCE_DIR" >&2
  exit 1
fi

if [ ! -d "$OVERLAY_DIR" ]; then
  echo "ERROR: overlay directory not found: $OVERLAY_DIR" >&2
  exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "ERROR: rsync is required." >&2
  exit 1
fi

mkdir -p "$TARGET_DIR"

rsync -a --delete "$SOURCE_DIR/" "$TARGET_DIR/"

rm -f \
  "$TARGET_DIR/FORSETI_CONTEXT.md" \
  "$TARGET_DIR/GENAI_CACHING.md" \
  "$TARGET_DIR/src/Controller/UtilityController.php" \
  "$TARGET_DIR/templates/forseti-chat.html.twig" \
  "$TARGET_DIR/templates/forseti-conversations.html.twig"

rsync -a "$OVERLAY_DIR/" "$TARGET_DIR/"

for doc in LICENSE CODE_OF_CONDUCT.md CONTRIBUTING.md SECURITY.md; do
  if [ -f "$MONOREPO_ROOT/$doc" ]; then
    cp "$MONOREPO_ROOT/$doc" "$TARGET_DIR/$doc"
  fi
done

CHAT_CONTROLLER="$TARGET_DIR/src/Controller/ChatController.php"
AI_API_SERVICE="$TARGET_DIR/src/Service/AIApiService.php"
INSTALL_FILE="$TARGET_DIR/ai_conversation.install"

perl -0pi -e "s#/forseti/chat#/ai-conversation/chat#g" "$CHAT_CONTROLLER"
perl -0pi -e "s#/forseti/conversations#/ai-conversation/conversations#g" "$CHAT_CONTROLLER"
perl -0pi -e "s#ai_conversation\\.forseti_chat#ai_conversation.chat_page#g" "$CHAT_CONTROLLER"
perl -0pi -e "s#forseti\\.conversation_export#ai_conversation.conversation_export#g" "$CHAT_CONTROLLER"
perl -0pi -e "s#forseti\\.conversation_delete#ai_conversation.conversation_delete#g" "$CHAT_CONTROLLER"
perl -0pi -e "s#forseti\\.conversations#ai_conversation.conversations#g" "$CHAT_CONTROLLER"
perl -0pi -e "s#'forseti_chat'#'ai_conversation_user_chat'#g" "$CHAT_CONTROLLER"
perl -0pi -e "s#'forseti_conversations'#'ai_conversation_conversations'#g" "$CHAT_CONTROLLER"

perl -0pi -e 's#/\*\*\n   \* Builds a system context string for a new job-seeker conversation\..*?\n  private function buildJobSeekerContext\(\): string \{.*?\n  \}\n\n  /\*\*\n   \* Send message endpoint\.#/**\n   * Builds a default system context string for a new conversation.\n   *\n   * @return string\n   *   Default system context to store as field_context on a new conversation node.\n   */\n  private function buildJobSeekerContext(): string {\n    return '\''You are a helpful AI assistant embedded in a Drupal site. Help users with accurate, concise, and context-aware responses based on the site content and active conversation.'\'';\n  }\n\n  /**\n   * Send message endpoint.#s' "$CHAT_CONTROLLER"

perl -0pi -e 's#\n      // Detect job-suggestion trigger and attach suggestions\..*?\n      return new JsonResponse\(\$response_payload\);\n#\n      return new JsonResponse(\$response_payload);\n#s' "$CHAT_CONTROLLER"
perl -0pi -e 's#\n      if \(\$this->hasJobSuggestionTrigger\(\$message\)\) \{.*?\n      \}\n\n      return new JsonResponse\(\$payload\);\n#\n      return new JsonResponse(\$payload);\n#s' "$CHAT_CONTROLLER"
perl -0pi -e 's#/\*\*\n   \* Returns TRUE if the message contains a job-suggestion trigger phrase\..*?\n  private function addMessageToNode#/**\n   * Add a message to the conversation node and update message count.\n   */\n  private function addMessageToNode#s' "$CHAT_CONTROLLER"

perl -0pi -e "s#optional dynamic content from node 10#optional dynamic content from a node#g" "$AI_API_SERVICE"
perl -0pi -e "s#Use PromptManager to initialize the default Forseti system prompt#Use PromptManager to initialize the default system prompt#g; s#Use PromptManager to update to Forseti system prompt#Use PromptManager to update the system prompt#g" "$INSTALL_FILE"

printf 'Exported drupal-ai-conversation candidate to: %s\n' "$TARGET_DIR"
