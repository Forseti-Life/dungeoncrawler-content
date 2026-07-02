#!/usr/bin/env php
<?php

/**
 * Test Character Tracking and Reappearance System.
 *
 * Demonstrates:
 * - Survivor processing after encounters
 * - AI-powered backstory generation
 * - Character reuse in future encounters
 * - Status and disposition tracking
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal
$autoloader = require_once 'web/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$container = $kernel->getContainer();

$characterTracking = $container->get('dungeoncrawler_content.character_tracking');
$encounterGen = $container->get('dungeoncrawler_content.encounter_generator');
$roomGen = $container->get('dungeoncrawler_content.room_generator');

echo "Testing Character Tracking & Continuity System\n";
echo "==============================================\n\n";

// Clean up test data
$database = $container->get('database');
$database->delete('dc_campaign_npcs')
  ->condition('campaign_id', 999)
  ->execute();

echo "SCENARIO 1: First Encounter - Goblin Ambush\n";
echo "--------------------------------------------\n";

// For demonstration, manually create entity instances (simulating an encounter)
// In production, these would come from the encounter generator
$sample_entities = [
  [
    'entity_instance_id' => 'npc_goblin_1',
    'entity_type' => 'creature',
    'entity_ref' => [
      'content_type' => 'creature',
      'content_id' => 'goblin_warrior',
      'version' => NULL,
    ],
    'placement' => [
      'room_id' => 'test_room_1',
      'hex' => ['q' => 2, 'r' => 1],
      'elevation' => 0,
      'facing' => 3,
    ],
    'state' => [
      'active' => TRUE,
      'hit_points' => ['current' => 12, 'max' => 15],
    ],
  ],
  [
    'entity_instance_id' => 'npc_goblin_2',
    'entity_type' => 'creature',
    'entity_ref' => [
      'content_type' => 'creature',
      'content_id' => 'goblin_warrior',
      'version' => NULL,
    ],
    'placement' => [
      'room_id' => 'test_room_1',
      'hex' => ['q' => 3, 'r' => 2],
      'elevation' => 0,
      'facing' => 2,
    ],
    'state' => [
      'active' => TRUE,
      'hit_points' => ['current' => 15, 'max' => 15],
    ],
  ],
  [
    'entity_instance_id' => 'npc_hobgoblin_1',
    'entity_type' => 'creature',
    'entity_ref' => [
      'content_type' => 'creature',
      'content_id' => 'hobgoblin_soldier',
      'version' => NULL,
    ],
    'placement' => [
      'room_id' => 'test_room_1',
      'hex' => ['q' => 1, 'r' => 3],
      'elevation' => 0,
      'facing' => 1,
    ],
    'state' => [
      'active' => TRUE,
      'hit_points' => ['current' => 20, 'max' => 25],
    ],
  ],
];

echo "Simulated encounter with " . count($sample_entities) . " creatures\n";
echo "Location: Dark cavern passage\n\n";

// Simulate combat outcomes
echo "COMBAT SIMULATION:\n";
$survivors = [];

foreach ($sample_entities as $index => $entity) {
  $entity_id = $entity['entity_ref']['content_id'] ?? 'unknown';
  $outcome = 'dead'; // Default
  $final_hp = 0;
  
  // Simulate different outcomes (30% survive in some way)
  $roll = mt_rand(1, 100);
  if ($roll <= 10) {
    $outcome = 'escaped';
    $final_hp = mt_rand(1, 10);
    echo "  Creature #{$index} ({$entity_id}): ESCAPED (fled at {$final_hp} HP) ⚡\n";
  }
  elseif ($roll <= 20) {
    $outcome = 'friendly';
    $final_hp = mt_rand(5, 15);
    echo "  Creature #{$index} ({$entity_id}): SURRENDERED (befriended at {$final_hp} HP) 🤝\n";
  }
  else {
    echo "  Creature #{$index} ({$entity_id}): KILLED ☠️\n";
  }
  
  // Track survivors
  if ($outcome !== 'dead') {
    $survivors[] = [
      'entity_instance' => $entity,
      'outcome' => $outcome,
      'final_hp' => $final_hp,
    ];
  }
}

echo "\n💾 Processing " . count($survivors) . " survivors...\n";

// Process survivors
if (!empty($survivors)) {
  $character_ids = $characterTracking->processSurvivors([
    'campaign_id' => 999,
    'room_id' => 'test_room_1',
    'party_level' => 3,
    'survivors' => $survivors,
  ]);
  
  echo "Created " . count($character_ids) . " character records\n\n";
  
  // Show character details
  echo "SURVIVOR PROFILES:\n";
  echo "------------------\n";
  foreach ($character_ids as $char_id) {
    $char = $database->select('dc_campaign_npcs', 'c')
      ->fields('c')
      ->condition('character_id', $char_id)
      ->execute()
      ->fetchAssoc();
    
    if ($char) {
      echo "Character ID: {$char['character_id']}\n";
      echo "  Entity: {$char['entity_id']}\n";
      echo "  Status: {$char['status']}\n";
      echo "  Disposition: {$char['disposition']}\n";
      echo "  HP: {$char['current_hp']}\n";
      echo "  Level: {$char['current_level']}\n";
      echo "  Can Reappear: " . ($char['can_reappear'] ? 'Yes' : 'No') . "\n";
      if ($char['backstory']) {
        echo "  Backstory: {$char['backstory']}\n";
      }
      if ($char['personality_traits']) {
        echo "  Traits: {$char['personality_traits']}\n";
      }
      if ($char['motivations']) {
        echo "  Motivation: {$char['motivations']}\n";
      }
      echo "\n";
    }
  }
}
else {
  echo "No survivors from first encounter.\n\n";
}

// Wait a moment for dramatic effect
sleep(1);

echo "\n";
echo "SCENARIO 2: Second Encounter (30 minutes later)\n";
echo "------------------------------------------------\n";

// In a real scenario, room generation would check for reusable characters
// and have a 20% chance per creature to reuse an existing character
echo "Party ventures deeper into the dungeon...\n";

// Check for reappearing characters
echo "CHECKING FOR RETURNING CHARACTERS:\n";
$reusable = $characterTracking->findReusableCharacters([
  'campaign_id' => 999,
  'location_type' => 'dungeon',
  'disposition_filter' => ['hostile', 'fearful', 'neutral'],
  'max_count' => 10,
]);

echo "Found " . count($reusable) . " characters eligible for reappearance\n\n";

if (!empty($reusable)) {
  echo "ELIGIBLE RETURNING CHARACTERS:\n";
  foreach ($reusable as $char) {
    echo "  - {$char['entity_id']} (Status: {$char['status']}, Disposition: {$char['disposition']})\n";
    echo "    Last seen: {$char['last_seen_date']}\n";
    echo "    Times reappeared: {$char['reappearance_count']}\n";
    if (!empty($char['backstory'])) {
      echo "    Story: " . substr($char['backstory'], 0, 100) . "...\n";
    }
    echo "\n";
  }
}

// Statistics
echo "\n";
echo "CAMPAIGN CHARACTER STATISTICS:\n";
echo "------------------------------\n";

$stats = $database->query("
  SELECT 
    status,
    disposition,
    COUNT(*) as count
  FROM dc_campaign_npcs
  WHERE campaign_id = 999
  GROUP BY status, disposition
  ORDER BY status, disposition
")->fetchAll();

echo "Status & Disposition Breakdown:\n";
foreach ($stats as $stat) {
  echo sprintf("  %-12s %-12s %d character(s)\n", 
    ucfirst($stat->status), 
    ucfirst($stat->disposition), 
    $stat->count
  );
}

$total = $database->query("
  SELECT COUNT(*) FROM dc_campaign_npcs WHERE campaign_id = 999
")->fetchField();

echo "\nTotal Tracked Characters: {$total}\n";

$can_reappear = $database->query("
  SELECT COUNT(*) FROM dc_campaign_npcs 
  WHERE campaign_id = 999 AND can_reappear = 1
")->fetchField();

echo "Available for Reappearance: {$can_reappear}\n";

echo "\n";
echo "SCENARIO 3: Tavern Encounter (Party returns to town)\n";
echo "-----------------------------------------------------\n";

// Simulate a friendly survivor showing up in town
if (!empty($survivors)) {
  echo "Later that evening at the Rusty Tankard tavern...\n";
  echo "A familiar face approaches the party!\n\n";
  
  $tavern_characters = $characterTracking->findReusableCharacters([
    'campaign_id' => 999,
    'location_type' => 'tavern',
    'disposition_filter' => ['friendly', 'grateful', 'curious'],
    'max_count' => 3,
  ]);
  
  if (!empty($tavern_characters)) {
    $npc = $tavern_characters[0];
    echo "🍺 NPC INTERACTION:\n";
    echo "  \"Well met, adventurers! Remember me?\"\n";
    echo "  Entity: {$npc['entity_id']}\n";
    echo "  Disposition: {$npc['disposition']}\n";
    echo "  {$npc['backstory']}\n\n";
    
    // Record the tavern meeting
    $characterTracking->recordReappearance($npc['character_id'], [
      'room_id' => 'tavern_rusty_tankard',
      'outcome' => 'peaceful_meeting',
    ]);
    
    echo "  ✅ Interaction recorded - reappearance count updated\n";
  }
  else {
    echo "  No friendly survivors to encounter in tavern.\n";
  }
}

echo "\n";
echo "SCENARIO 4: Revenge Encounter (Escaped enemy returns)\n";
echo "------------------------------------------------------\n";

$vengeance_seekers = $characterTracking->findReusableCharacters([
  'campaign_id' => 999,
  'location_type' => 'dungeon',
  'disposition_filter' => ['hostile', 'fearful'],
  'max_count' => 1,
]);

if (!empty($vengeance_seekers)) {
  $enemy = $vengeance_seekers[0];
  echo "⚔️  An old enemy ambushes the party!\n\n";
  echo "  Entity: {$enemy['entity_id']}\n";
  echo "  Status: {$enemy['status']}\n";
  echo "  Disposition: {$enemy['disposition']}\n";  
  echo "  First Encounter: {$enemy['first_encounter_date']}\n";
  echo "  {$enemy['backstory']}\n\n";
  echo "  \"You thought you'd seen the last of me!\" the {$enemy['entity_id']} snarls.\n";
  echo "  (This character remembers the party from level {$enemy['first_encounter_level']})\n\n";
  
  // Simulate this enemy being defeated permanently
  echo "  After a fierce battle, the party defeats the vengeful enemy.\n";
  $characterTracking->markCharacterDead($enemy['character_id'], [
    'room_id' => 'dungeon_level_2',
  ]);
  echo "  ☠️  Character marked as dead - will not reappear again.\n";
}
else {
  echo "  No vengeful enemies seeking revenge (all were killed or friendly).\n";
}

echo "\n";
echo "✅ Character Tracking System Test Complete!\n\n";

echo "FEATURE SUMMARY:\n";
echo "- Survivors from encounters are tracked in database\n";
echo "- Each survivor gets AI-generated backstory (template fallback)\n";
echo "- Status tracked: alive, dead, escaped, friendly, captured\n";
echo "- Disposition tracked: friendly, hostile, fearful, grateful, etc.\n";
echo "- Characters can reappear in future encounters (20% chance per creature)\n";
echo "- Reappearance context-aware (taverns = friendly, dungeons = hostile)\n";
echo "- Dead characters marked non-reusable\n";
echo "- Reappearance count prevents excessive returns\n";
echo "- Full campaign continuity maintained\n";

// Cleanup
echo "\n🧹 Cleaning up test data...\n";
$database->delete('dc_campaign_npcs')
  ->condition('campaign_id', 999)
  ->execute();
echo "Test data removed.\n";
