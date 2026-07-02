#!/usr/bin/env php
<?php

/**
 * Test script to generate room with complete hex manifest.
 *
 * Usage: php test-room-manifest.php
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal
$autoloader = require_once 'web/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$container = $kernel->getContainer();

// Get RoomGeneratorService
$roomGenerator = $container->get('dungeoncrawler_content.room_generator');

// Generate a test room
$context = [
  'campaign_id' => 1,
  'dungeon_id' => 10,
  'level_id' => 1,
  'room_index' => 0,
  'room_type' => 'chamber',
  'terrain_type' => 'stone_floor',
  'theme' => 'dungeon',
  'party_level' => 3,
  'difficulty' => 'moderate',
  'width' => 7,
  'height' => 5,
  'seed' => 12345,
];

echo "🏰 Generating Test Room...\n\n";

$room = $roomGenerator->generateRoom($context);

// Display header information
echo "========================================\n";
echo "ROOM GENERATION COMPLETE\n";
echo "========================================\n\n";

echo "Room ID: {$room['room_id']}\n";
echo "Name: {$room['name']}\n";
echo "Type: {$room['room_type']}\n";
echo "Size: {$room['size_category']} ({$room['hex_manifest']['total_hexes']} hexes)\n";
echo "Terrain: {$room['terrain']['type']}\n";
echo "Lighting: {$room['lighting']['level']}\n\n";

// Display entry and exit points
echo "========================================\n";
echo "ENTRY & EXIT POINTS\n";
echo "========================================\n\n";

echo "Entry Points:\n";
foreach ($room['entry_points'] as $entry) {
  $hex = $entry['hex'];
  echo "  - Hex ({$hex['q']}, {$hex['r']}) - {$entry['direction']} {$entry['type']}";
  echo $entry['locked'] ? " [LOCKED]" : "";
  echo $entry['hidden'] ? " [HIDDEN]" : "";
  echo "\n";
}

echo "\nExit Points:\n";
foreach ($room['exit_points'] as $exit) {
  $hex = $exit['hex'];
  echo "  - Hex ({$hex['q']}, {$hex['r']}) - {$exit['direction']} {$exit['type']}";
  echo $exit['locked'] ? " [LOCKED]" : "";
  echo $exit['hidden'] ? " [HIDDEN]" : "";
  echo "\n";
}

// Display hex manifest
echo "\n========================================\n";
echo "HEX MANIFEST SUMMARY\n";
echo "========================================\n\n";

$manifest = $room['hex_manifest'];
echo "Total Hexes: {$manifest['total_hexes']}\n";
echo "Passable Hexes: {$manifest['passable_hexes']}\n";
echo "Occupied Hexes: {$manifest['occupied_hexes']}\n";
echo "Entry Points: {$manifest['entry_point_count']}\n";
echo "Exit Points: {$manifest['exit_point_count']}\n";
echo "Creatures: {$manifest['creature_count']}\n";

// Display detailed hex-by-hex breakdown
echo "\n========================================\n";
echo "COMPLETE HEX-BY-HEX BREAKDOWN\n";
echo "========================================\n\n";

foreach ($manifest['by_hex'] as $key => $hex_info) {
  echo "Hex ({$hex_info['q']}, {$hex_info['r']}) - Coord: [{$key}]\n";
  echo "  Terrain: {$hex_info['terrain']}\n";
  echo "  Elevation: {$hex_info['elevation_ft']} ft\n";
  echo "  Passable: " . ($hex_info['passable'] ? 'Yes' : 'No') . "\n";
  
  if ($hex_info['entry_point']) {
    echo "  ⭐ ENTRY POINT - Party enters here\n";
  }
  
  if ($hex_info['exit_point']) {
    echo "  🚪 EXIT POINT - Leads to another room\n";
  }
  
  if (!empty($hex_info['objects'])) {
    echo "  Objects: " . implode(', ', $hex_info['objects']) . "\n";
  }
  
  if (!empty($hex_info['creature_details'])) {
    echo "  🔴 Creatures:\n";
    foreach ($hex_info['creature_details'] as $creature) {
      echo "     - {$creature['name']} (facing {$creature['facing_direction']} / {$creature['facing']}°)\n";
    }
  }
  
  echo "  Summary: {$hex_info['summary']}\n";
  echo "\n";
}

// Display creature details
if (!empty($room['creatures'])) {
  echo "========================================\n";
  echo "CREATURE PLACEMENT DETAILS\n";
  echo "========================================\n\n";
  
  foreach ($room['creatures'] as $creature) {
    $hex = $creature['placement']['hex'];
    echo "Creature: {$creature['entity_ref']['content_id']}\n";
    echo "  Instance ID: {$creature['entity_instance_id']}\n";
    echo "  Type: {$creature['entity_type']}\n";
    echo "  Hex: ({$hex['q']}, {$hex['r']})\n";
    echo "  Facing: {$creature['placement']['facing']}°\n";
    
    if (isset($creature['game_state']['hp_current'])) {
      echo "  HP: {$creature['game_state']['hp_current']}\n";
    }
    
    if (isset($creature['game_state']['conditions'])) {
      echo "  Conditions: " . (empty($creature['game_state']['conditions']) ? 'None' : implode(', ', $creature['game_state']['conditions'])) . "\n";
    }
    
    echo "\n";
  }
}

// Display JSON excerpt
echo "========================================\n";
echo "JSON OUTPUT SAMPLE (First 3 Hexes)\n";
echo "========================================\n\n";

$sample_hexes = array_slice($room['hexes'], 0, 3);
echo json_encode(['hexes' => $sample_hexes], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
echo "\n";

echo "\n✅ Room generation complete with full hex manifest!\n";
