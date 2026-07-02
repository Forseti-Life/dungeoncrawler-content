#!/usr/bin/env php
<?php

/**
 * Test party level scaling in dungeon generation.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal
$autoloader = require_once 'web/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$container = $kernel->getContainer();

$dungeonGenerator = $container->get('dungeoncrawler_content.dungeon_generator');

echo "Testing Party Level Scaling in Dungeon Generation\n";
echo "==================================================\n\n";

// Test different party levels
$party_levels = [1, 5, 10, 15, 20];

foreach ($party_levels as $party_level) {
  echo "Party Level: {$party_level}\n";
  echo str_repeat('-', 50) . "\n";
  
  $context = [
    'campaign_id' => 1,
    'location_x' => 100 + $party_level,
    'location_y' => 200,
    'party_level' => $party_level,
    'party_size' => 4,
    'party_composition' => [
      'fighter' => 1,
      'wizard' => 1,
      'cleric' => 1,
      'rogue' => 1,
    ],
    'seed' => 42,
  ];
  
  $dungeon = $dungeonGenerator->generateDungeon($context);
  
  // Calculate statistics
  $total_rooms = 0;
  $total_creatures = 0;
  $total_hexes = 0;
  $difficulty_counts = [
    'trivial' => 0,
    'low' => 0,
    'moderate' => 0,
    'severe' => 0,
    'extreme' => 0,
  ];
  
  foreach ($dungeon['levels'] as $level) {
    $total_rooms += $level['room_count'];
    foreach ($level['rooms'] as $room) {
      $total_hexes += $room['hex_manifest']['total_hexes'];
      $total_creatures += $room['hex_manifest']['creature_count'];
      
      // Track difficulty from room name/type
      // This is a simplified check - in real implementation would be in room metadata
      if ($room['hex_manifest']['creature_count'] == 0) {
        $difficulty_counts['trivial']++;
      }
      elseif ($room['hex_manifest']['creature_count'] <= 4) {
        $difficulty_counts['low']++;
      }
      elseif ($room['hex_manifest']['creature_count'] <= 7) {
        $difficulty_counts['moderate']++;
      }
      elseif ($room['hex_manifest']['creature_count'] <= 9) {
        $difficulty_counts['severe']++;
      }
      else {
        $difficulty_counts['extreme']++;
      }
    }
  }
  
  echo "  Dungeon: {$dungeon['name']}\n";
  echo "  Theme: {$dungeon['theme']}\n";
  echo "  Depth: {$dungeon['depth']} level(s)\n";
  echo "  Total Rooms: {$total_rooms}\n";
  echo "  Total Hexes: {$total_hexes}\n";
  echo "  Total Creatures: {$total_creatures}\n";
  echo "  Avg Creatures/Room: " . round($total_creatures / max($total_rooms, 1), 1) . "\n";
  echo "  Difficulty Distribution:\n";
  echo "    - Trivial/Empty: {$difficulty_counts['trivial']}\n";
  echo "    - Low (1-4): {$difficulty_counts['low']}\n";
  echo "    - Moderate (5-7): {$difficulty_counts['moderate']}\n";
  echo "    - Severe (8-9): {$difficulty_counts['severe']}\n";
  echo "    - Extreme (10+): {$difficulty_counts['extreme']}\n";
  
  // Show first level's first room for detail
  if (!empty($dungeon['levels'][0]['rooms'][0])) {
    $sample_room = $dungeon['levels'][0]['rooms'][0];
    echo "  Sample Room: {$sample_room['name']}\n";
    echo "    - Size: {$sample_room['hex_manifest']['total_hexes']} hexes\n";
    echo "    - Creatures: {$sample_room['hex_manifest']['creature_count']}\n";
  }
  
  echo "\n";
}

// Now test XP budget scaling directly
echo "\n";
echo "XP Budget Scaling by Party Level & Difficulty\n";
echo "==============================================\n\n";

$encounterGen = $container->get('dungeoncrawler_content.encounter_generator');

$difficulties = ['trivial', 'low', 'moderate', 'severe', 'extreme'];
$test_levels = [1, 5, 10, 20];

foreach ($test_levels as $level) {
  echo "Party Level {$level}:\n";
  foreach ($difficulties as $diff) {
    $context = [
      'party_level' => $level,
      'party_size' => 4,
      'difficulty' => $diff,
      'theme' => 'dungeon',
    ];
    
    $encounter = $encounterGen->generateEncounter($context);
    
    if (!empty($encounter['xp_budget'])) {
      $xp = $encounter['xp_budget']['target_xp'];
      $actual = $encounter['actual_xp'] ?? 0;
      $count = $encounter['combatant_count'] ?? 0;
      
      echo sprintf("  %-10s XP: %3d (actual: %3d, %d creatures)\n", 
        ucfirst($diff), $xp, $actual, $count);
    }
  }
  echo "\n";
}

echo "✅ Party level scaling demonstration complete!\n";
echo "\nKey Scaling Factors:\n";
echo "- Higher level → Deeper dungeons (more levels)\n";
echo "- Higher level → More rooms per level\n";
echo "- Higher level → Larger XP budgets\n";
echo "- Party size → Room dimensions (2-3 hexes per member)\n";
echo "- Party size → XP budget multiplier\n";
