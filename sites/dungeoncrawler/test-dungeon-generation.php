#!/usr/bin/env php
<?php

/**
 * Test script to generate complete dungeon with multiple rooms.
 *
 * Usage: php test-dungeon-generation.php
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal
$autoloader = require_once 'web/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$container = $kernel->getContainer();

// Get DungeonGeneratorService
$dungeonGenerator = $container->get('dungeoncrawler_content.dungeon_generator');

// Generate a test dungeon
$context = [
  'campaign_id' => 1,
  'location_x' => 100,
  'location_y' => 200,
  'party_level' => 5,
  'party_size' => 4,
  'party_composition' => [
    'fighter' => 1,
    'wizard' => 1,
    'cleric' => 1,
    'rogue' => 1,
  ],
  'seed' => 42,
];

echo "🏰 Generating Complete Dungeon...\n\n";

$start_time = microtime(true);
$dungeon = $dungeonGenerator->generateDungeon($context);
$end_time = microtime(true);
$duration = round(($end_time - $start_time) * 1000, 2);

// Display dungeon header
echo "========================================\n";
echo "DUNGEON GENERATION COMPLETE\n";
echo "========================================\n\n";

echo "Dungeon ID: {$dungeon['dungeon_id']}\n";
echo "Name: {$dungeon['name']}\n";
echo "Theme: {$dungeon['theme']}\n";
echo "Depth: {$dungeon['depth']} levels\n";
echo "Location: ({$dungeon['location_x']}, {$dungeon['location_y']})\n";
echo "Generation Time: {$duration}ms\n\n";

// Display party context
echo "========================================\n";
echo "PARTY CONTEXT\n";
echo "========================================\n\n";

echo "Party Level: {$dungeon['generation_context']['party_level']}\n";
echo "Party Size: {$dungeon['generation_context']['party_size']}\n";
echo "Generated: {$dungeon['generation_context']['generated_at']}\n";
echo "Seed: {$dungeon['generation_context']['seed']}\n\n";

// Display each level
foreach ($dungeon['levels'] as $level_index => $level) {
  echo "========================================\n";
  echo "LEVEL " . ($level_index + 1) . ": {$level['name']}\n";
  echo "========================================\n\n";

  echo "Level ID: {$level['level_id']}\n";
  echo "Theme: {$level['theme']}\n";
  echo "Rooms: {$level['room_count']}\n";
  echo "Connections: " . count($level['connections']) . "\n\n";

  // Display room summary
  echo "Rooms:\n";
  foreach ($level['rooms'] as $room_index => $room) {
    $hex_count = $room['hex_manifest']['total_hexes'];
    $creature_count = $room['hex_manifest']['creature_count'];
    
    echo sprintf("  %d. %s [%s]\n",
      $room_index + 1,
      $room['name'],
      $room['room_type']
    );
    echo "     Size: {$room['size_category']} ({$hex_count} hexes)\n";
    echo "     Terrain: {$room['terrain']['type']}\n";
    echo "     Creatures: {$creature_count}\n";
    echo "     Entry Points: {$room['hex_manifest']['entry_point_count']}\n";
    echo "     Exit Points: {$room['hex_manifest']['exit_point_count']}\n";
    
    if (!empty($room['creatures'])) {
      echo "     Encounter: ";
      $creature_names = array_unique(array_map(fn($c) => $c['entity_ref']['content_id'], $room['creatures']));
      echo implode(', ', array_slice($creature_names, 0, 3));
      if (count($creature_names) > 3) {
        echo ' +' . (count($creature_names) - 3) . ' more';
      }
      echo "\n";
    }
    
    echo "\n";
  }

  // Display connections
  if (!empty($level['connections'])) {
    echo "Room Connections:\n";
    foreach ($level['connections'] as $conn_index => $conn) {
      $locked = $conn['is_locked'] ? ' [LOCKED]' : '';
      $trapped = $conn['is_trapped'] ? ' [TRAPPED]' : '';
      echo sprintf("  %s → %s (%s)%s%s\n",
        $conn['from_room_id'],
        $conn['to_room_id'],
        $conn['connection_type'],
        $locked,
        $trapped
      );
    }
    echo "\n";
  }
}

// Display overall statistics
echo "========================================\n";
echo "DUNGEON STATISTICS\n";
echo "========================================\n\n";

$total_rooms = 0;
$total_hexes = 0;
$total_creatures = 0;
$total_entry_points = 0;
$total_exit_points = 0;

foreach ($dungeon['levels'] as $level) {
  $total_rooms += $level['room_count'];
  foreach ($level['rooms'] as $room) {
    $total_hexes += $room['hex_manifest']['total_hexes'];
    $total_creatures += $room['hex_manifest']['creature_count'];
    $total_entry_points += $room['hex_manifest']['entry_point_count'];
    $total_exit_points += $room['hex_manifest']['exit_point_count'];
  }
}

echo "Total Levels: {$dungeon['depth']}\n";
echo "Total Rooms: {$total_rooms}\n";
echo "Total Hexes: {$total_hexes}\n";
echo "Total Creatures: {$total_creatures}\n";
echo "Total Entry Points: {$total_entry_points}\n";
echo "Total Exit Points: {$total_exit_points}\n\n";

// Sample room detail (first room of first level)
if (!empty($dungeon['levels'][0]['rooms'][0])) {
  $sample_room = $dungeon['levels'][0]['rooms'][0];
  
  echo "========================================\n";
  echo "SAMPLE ROOM DETAIL (Level 1, Room 1)\n";
  echo "========================================\n\n";
  
  echo "Room: {$sample_room['name']}\n";
  echo "Description: {$sample_room['description']}\n\n";
  
  echo "Hex Manifest Sample (first 5 hexes):\n";
  $hex_sample = array_slice($sample_room['hex_manifest']['by_hex'], 0, 5);
  foreach ($hex_sample as $key => $hex_info) {
    echo "  Hex ({$hex_info['q']}, {$hex_info['r']}): {$hex_info['summary']}\n";
  }
  echo "\n";
  
  // Show JSON structure sample
  echo "JSON Structure Sample:\n";
  echo json_encode([
    'dungeon_id' => $dungeon['dungeon_id'],
    'name' => $dungeon['name'],
    'levels_count' => count($dungeon['levels']),
    'first_level' => [
      'level_id' => $dungeon['levels'][0]['level_id'],
      'room_count' => $dungeon['levels'][0]['room_count'],
      'first_room' => [
        'room_id' => $sample_room['room_id'],
        'name' => $sample_room['name'],
        'hex_count' => $sample_room['hex_manifest']['total_hexes'],
        'creature_count' => $sample_room['hex_manifest']['creature_count'],
      ],
    ],
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  echo "\n\n";
}

echo "✅ Dungeon generation complete!\n";
echo "   Full dungeon with {$dungeon['depth']} level(s) and {$total_rooms} room(s) generated.\n";
