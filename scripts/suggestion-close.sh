#!/usr/bin/env bash
# suggestion-close.sh — Close out a community_suggestion node after a feature ships
#
# Run at Stage 7 (ship) for each feature that originated from a community suggestion.
#
# What this does:
#   1. Updates the Drupal community_suggestion node: status → implemented (or declined)
#   2. Posts a reply message to the original AI conversation so the requester is notified
#      next time they open the chat
#   3. Optionally sends a Drupal email notification to the requester
#
# Usage:
#   ./scripts/suggestion-close.sh <site> <nid> <release-id> [--declined "reason"]
#
# Examples:
#   ./scripts/suggestion-close.sh forseti 42 20260226-forseti-r1
#   ./scripts/suggestion-close.sh forseti 43 20260226-forseti-r1 --declined "Out of scope for mission"
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"
# shellcheck source=lib/site-paths.sh
. "$ROOT_DIR/scripts/lib/site-paths.sh"

SITE="${1:-}"
NID="${2:-}"
RELEASE_ID="${3:-}"
DECISION="implemented"
DECLINE_REASON=""

if [ "${4:-}" = "--declined" ]; then
  DECISION="declined"
  DECLINE_REASON="${5:-No reason provided}"
fi

if [ -z "$SITE" ] || [ -z "$NID" ] || [ -z "$RELEASE_ID" ]; then
  echo "Usage: $0 <site> <nid> <release-id> [--declined \"reason\"]" >&2
  echo "" >&2
  echo "  $0 forseti 42 20260226-forseti-r1" >&2
  echo "  $0 forseti 43 20260226-forseti-r1 --declined \"Out of scope for mission\"" >&2
  exit 1
fi

case "$SITE" in
  forseti)       DRUPAL_ROOT="/var/www/html/forseti" ;;
  dungeoncrawler) DRUPAL_ROOT="/var/www/html/dungeoncrawler" ;;
  *) echo "ERROR: Unknown site '$SITE'" >&2; exit 1 ;;
esac

echo "[suggestion-close] Site: $SITE | NID: $NID | Release: $RELEASE_ID | Decision: $DECISION"

# Build the notification message text
if [ "$DECISION" = "implemented" ]; then
  NOTIFICATION_MSG="Great news! Your suggestion has been implemented and shipped in release ${RELEASE_ID}. Thank you for helping improve the community — your feedback made a real difference. If you have more ideas, keep sharing them!"
else
  NOTIFICATION_MSG="Thank you for your suggestion. After review, we've decided not to include it in our current roadmap. Reason: ${DECLINE_REASON}. We genuinely appreciate your input and encourage you to keep sharing ideas — every suggestion is read and considered."
fi

# Update Drupal: set status, post reply to original conversation, send email
cd "$DRUPAL_ROOT" && vendor/bin/drush php:eval "
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

\$nid = $NID;
\$decision = '${DECISION}';
\$release_id = '${RELEASE_ID}';
\$notification_msg = '${NOTIFICATION_MSG}';

// Load the suggestion node
\$suggestion = Node::load(\$nid);
if (!\$suggestion || \$suggestion->bundle() !== 'community_suggestion') {
  echo 'ERROR: community_suggestion NID ' . \$nid . ' not found' . PHP_EOL;
  exit(1);
}

// 1. Update suggestion status
\$suggestion->set('field_suggestion_status', \$decision);
\$suggestion->save();
echo 'UPDATED: NID ' . \$nid . ' status → ' . \$decision . PHP_EOL;

// 2. Post reply to the original conversation
\$conv_ref = \$suggestion->get('field_conversation_reference')->getValue();
if (!empty(\$conv_ref[0]['target_id'])) {
  \$conv_nid = (int)\$conv_ref[0]['target_id'];
  \$conversation = Node::load(\$conv_nid);
  if (\$conversation && \$conversation->hasField('field_messages')) {
    // Load existing messages
    \$existing = [];
    foreach (\$conversation->get('field_messages') as \$item) {
      \$val = \$item->getValue()['value'] ?? '';
      if (\$val) \$existing[] = json_decode(\$val, true);
    }
    // Append Forseti reply
    \$existing[] = [
      'role' => 'assistant',
      'content' => \$notification_msg,
      'timestamp' => time(),
      'type' => 'suggestion_closure',
      'suggestion_nid' => \$nid,
      'release_id' => \$release_id,
    ];
    // Write back
    \$field_values = [];
    foreach (\$existing as \$msg) {
      \$field_values[] = ['value' => json_encode(\$msg)];
    }
    \$conversation->set('field_messages', \$field_values);
    \$conversation->save();
    echo 'NOTIFIED: Appended closure message to conversation NID ' . \$conv_nid . PHP_EOL;
  } else {
    echo 'WARN: Conversation NID ' . \$conv_nid . ' not found or has no field_messages — skipping chat notification' . PHP_EOL;
  }
} else {
  echo 'WARN: No conversation_reference on suggestion NID ' . \$nid . ' — skipping chat notification' . PHP_EOL;
}

// 3. Send email notification to the original requester
\$requester = User::load(\$suggestion->getOwnerId());
if (\$requester && \$requester->getEmail()) {
  \$mail_manager = \Drupal::service('plugin.manager.mail');
  \$params = [
    'subject' => \$decision === 'implemented'
      ? 'Your Forseti suggestion has been shipped!'
      : 'Update on your Forseti suggestion',
    'body' => \$notification_msg,
    'suggestion_nid' => \$nid,
    'release_id' => \$release_id,
    'decision' => \$decision,
  ];
  \$result = \$mail_manager->mail(
    'ai_conversation',
    'suggestion_closure',
    \$requester->getEmail(),
    \$requester->getPreferredLangcode(),
    \$params,
    NULL,
    TRUE
  );
  if (\$result['result']) {
    echo 'EMAILED: ' . \$requester->getEmail() . PHP_EOL;
  } else {
    echo 'WARN: Email to ' . \$requester->getEmail() . ' failed (mail system may not be configured)' . PHP_EOL;
  }
} else {
  echo 'WARN: No email address for requester — skipping email' . PHP_EOL;
}
" 2>/dev/null
cd "$ROOT_DIR"

echo "[suggestion-close] Done. NID $NID closed as $DECISION."
