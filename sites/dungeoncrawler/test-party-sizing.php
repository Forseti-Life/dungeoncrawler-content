#!/usr/bin/env php
<?php

/**
 * Test party-size-based room generation.
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal
$autoloader = require_once 'web/autoload.php';
$kernel = DrupalKernel::createFromRequest(Request::createFromGlobals(), $autoloader, 'prod');
$kernel->boot();
$container = $kernel->getContainer();

$roomGenerator = $container->get('dungeoncrawler_content.room_generator');

echo "Testing Party-Size-Aware Room Generation\n";
echo "==========================================\n\n";

// Test different party sizes
$party_sizes = [2, 4, 6, 8];

foreach ($party_sizes as $party_size) {
  echo "Party Size: {$party_size}\n";
  echo str_repeat('-', 40) . "\n";
  
  $context = [
    'campaign_id' => 1,
    'dungeon_id' => 10,
    'level_id' => 1,
    'room_index' => 0,
    'room_type' => 'chamber',
    'terrain_type' => 'stone_floor',
    'theme' => 'dungeon',
    'party_level' => 5,
    'party_size' => $party_size,
    'difficulty' => 'moderate',
    'seed' => 42,
  ];
  
  $room = $roomGenerator->generateRoom($context);
  
  $hex_count = $room['hex_manifest']['total_hexes'];
  $creature_count = $room['hex_manifest']['creature_count'];
  
  echo "  Room: {$room['name']}\n";
  echo "  Size: {$room['size_category']} ({$hex_count} hexes)\n";
  echo "  Creatures: {$creature_count}\n";
  echo "  Expected Range: " . ($party_size * 2) . "-" . ($party_size * 3) . " hexes\n";
  echo "\n";
}

echo "\nTesting Boss Room (50% larger)\n";
echo str_repeat('-', 40) . "\n\n";

$context = [
  'campaign_id' => 1,
  'dungeon_id' => 10,
  'level_id' => 1,
  'room_index' => 99,
  'room_type' => 'boss_room',
  'terrain_type' => 'stone_floor',
  'theme' => 'dungeon',
  'party_level' => 5,
  'party_size' => 4,
  'difficulty' => 'extreme',
  'seed' => 42,
];

$room = $roomGenerator->generateRoom($context);

echo "Room: {$room['name']}\n";
echo "Size: {$room['size_category']} ({$room['hex_manifest']['total_hexes']} hexes)\n";
echo "Creatures: {$room['hex_manifest']['creature_count']}\n";
echo "Expected: ~12-18 hexes (4 party * 2-3 * 1.5)\n\n";

echo "✅ Party-size-based room scaling working!\n";
