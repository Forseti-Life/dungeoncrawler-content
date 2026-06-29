#!/bin/bash

# Room Chat API Integration Test
# Tests persistent chat storage per room

echo "=== Room Chat API Integration Test ==="
echo "Testing GET/POST endpoints for room dialogue system"
echo

# Configuration - Update these values for your environment
CAMPAIGN_ID=1
ROOM_ID="test-room-001"
BASE_URL="http://localhost:8080"

echo "Configuration:"
echo "  Campaign ID: $CAMPAIGN_ID"
echo "  Room ID: $ROOM_ID"
echo "  Base URL: $BASE_URL"
echo

echo "1. Test GET /api/campaign/{id}/room/{room_id}/chat"
echo "=================================================="
echo "Fetching initial chat history..."
RESPONSE=$(curl -s "$BASE_URL/api/campaign/$CAMPAIGN_ID/room/$ROOM_ID/chat")
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "Response: $RESPONSE"
echo

echo "2. Test POST /api/campaign/{id}/room/{room_id}/chat"
echo "===================================================="
echo "Posting test message..."
POST_DATA='{
  "speaker": "Test Player",
  "message": "Hello from test script!",
  "type": "player",
  "character_id": 1
}'

POST_RESPONSE=$(curl -s -X POST \
  -H "Content-Type: application/json" \
  -d "$POST_DATA" \
  "$BASE_URL/api/campaign/$CAMPAIGN_ID/room/$ROOM_ID/chat")

echo "$POST_RESPONSE" | jq '.' 2>/dev/null || echo "Response: $POST_RESPONSE"
echo

echo "3. Verify message persistence"
echo "=============================="
echo "Fetching chat history again to verify persistence..."
VERIFY_RESPONSE=$(curl -s "$BASE_URL/api/campaign/$CAMPAIGN_ID/room/$ROOM_ID/chat")
MESSAGE_COUNT=$(echo "$VERIFY_RESPONSE" | jq '.data.messages | length' 2>/dev/null)

if [ -n "$MESSAGE_COUNT" ] && [ "$MESSAGE_COUNT" -gt 0 ]; then
  echo "✓ Success: Found $MESSAGE_COUNT message(s) in chat history"
  echo
  echo "Recent messages:"
  echo "$VERIFY_RESPONSE" | jq '.data.messages[-3:]' 2>/dev/null || echo "$VERIFY_RESPONSE"
else
  echo "✗ Failed: No messages found or error occurred"
  echo "$VERIFY_RESPONSE"
fi
echo

echo "4. Test multiple message types"
echo "==============================="
echo "Posting NPC message..."
NPC_DATA='{
  "speaker": "Guard NPC",
  "message": "Who goes there?",
  "type": "npc"
}'

curl -s -X POST \
  -H "Content-Type: application/json" \
  -d "$NPC_DATA" \
  "$BASE_URL/api/campaign/$CAMPAIGN_ID/room/$ROOM_ID/chat" | jq '.success' 2>/dev/null

echo "Posting system message..."
SYSTEM_DATA='{
  "speaker": "System",
  "message": "A door creaks open...",
  "type": "system"
}'

curl -s -X POST \
  -H "Content-Type: application/json" \
  -d "$SYSTEM_DATA" \
  "$BASE_URL/api/campaign/$CAMPAIGN_ID/room/$ROOM_ID/chat" | jq '.success' 2>/dev/null
echo

echo "5. Final chat history"
echo "====================="
FINAL_RESPONSE=$(curl -s "$BASE_URL/api/campaign/$CAMPAIGN_ID/room/$ROOM_ID/chat")
echo "$FINAL_RESPONSE" | jq '.data.messages | .[] | "\(.speaker) [\(.type)]: \(.message)"' 2>/dev/null || echo "$FINAL_RESPONSE"
echo

echo "=== Test Complete ==="
echo
echo "Note: To test with authenticated session, use browser cookies:"
echo "  curl -b /path/to/cookies.txt $BASE_URL/api/campaign/$CAMPAIGN_ID/room/$ROOM_ID/chat"
