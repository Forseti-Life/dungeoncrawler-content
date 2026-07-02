#!/bin/bash

# Quest System Integration Test
# Tests the complete quest system API endpoints

CAMPAIGN_ID=1
SITE_URL="http://localhost:8888"
API_BASE="$SITE_URL/api/campaign/$CAMPAIGN_ID"

echo "================================"
echo "Quest System Integration Tests"
echo "================================"
echo ""

# Test 1: Generate quest from template
echo "Test 1: Generate quest from template (clear_goblin_den)"
TEMPLATE_ID="clear_goblin_den"
curl -X POST "$API_BASE/quests/generate" \
  -H "Content-Type: application/json" \
  -d '{
    "template_id": "'$TEMPLATE_ID'",
    "context": {
      "party_level": 3,
      "difficulty": "moderate"
    }
  }' 2>/dev/null | python3 -m json.tool

echo ""
echo "---"
echo ""

# Test 2: List available quests
echo "Test 2: Get available quests"
curl -X GET "$API_BASE/quests/available" \
  -H "Accept: application/json" 2>/dev/null | python3 -m json.tool

echo ""
echo "---"
echo ""

# Test 3: Start a quest (will use first available quest)
echo "Test 3: Start a quest"
curl -X POST "$API_BASE/quests/test_quest_001/start" \
  -H "Content-Type: application/json" \
  -d '{
    "character_id": "char_001",
    "entity_type": "character"
  }' 2>/dev/null | python3 -m json.tool

echo ""
echo "---"
echo ""

# Test 4: Update quest progress
echo "Test 4: Update quest progress (kill objective)"
curl -X PUT "$API_BASE/quests/test_quest_001/progress" \
  -H "Content-Type: application/json" \
  -d '{
    "objective_id": "kill_enemies",
    "action": "increment",
    "entity_id": "char_001",
    "amount": 3
  }' 2>/dev/null | python3 -m json.tool

echo ""
echo "---"
echo ""

# Test 5: Get quest journal
echo "Test 5: Get character quest journal"
curl -X GET "$API_BASE/character/char_001/quest-journal" \
  -H "Accept: application/json" 2>/dev/null | python3 -m json.tool

echo ""
echo "---"
echo ""

# Test 6: Get reward summary
echo "Test 6: Get reward summary"
curl -X GET "$API_BASE/quests/test_quest_001/rewards" \
  -H "Accept: application/json" 2>/dev/null | python3 -m json.tool

echo ""
echo "================================"
echo "Integration tests complete"
echo "================================"
