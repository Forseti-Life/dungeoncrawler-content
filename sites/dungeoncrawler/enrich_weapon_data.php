#!/usr/bin/env php
<?php

/**
 * @file
 * Enriches weapon data in dungeoncrawler_content_registry with weapon_category and weapon_group.
 * 
 * This script:
 * 1. Reads all weapon items from the registry
 * 2. For items missing weapon_category or weapon_group, adds them using defaults
 * 3. Updates the schema_data JSON in the database
 * 
 * Usage:
 *   php enrich_weapon_data.php
 */

use Drupal\Core\DrupalKernel;
use Symfony\Component\HttpFoundation\Request;

// Bootstrap Drupal
$autoloader = require_once __DIR__ . '/vendor/autoload.php';
$request = Request::createFromGlobals();
$kernel = DrupalKernel::createFromRequest($request, $autoloader, 'prod');
$kernel->boot();
$container = $kernel->getContainer();
$container->get('request_stack')->push($request);

// Get database connection
$database = \Drupal::database();

// Weapon category defaults (from ItemCombatDataService)
$category_defaults = [
  'club' => 'simple',
  'dagger' => 'simple',
  'mace' => 'simple',
  'spear' => 'simple',
  'staff' => 'simple',
  'crossbow' => 'simple',
  'sling' => 'simple',
  'battleaxe' => 'martial',
  'falchion' => 'martial',
  'greatsword' => 'martial',
  'longsword' => 'martial',
  'rapier' => 'martial',
  'scimitar' => 'martial',
  'shortsword' => 'martial',
  'warhammer' => 'martial',
  'greataxe' => 'martial',
  'composite-longbow' => 'martial',
  'composite-shortbow' => 'martial',
  'longbow' => 'martial',
  'shortbow' => 'martial',
  'fist' => 'unarmed',
];

// Weapon group defaults (from ItemCombatDataService)
$group_defaults = [
  'club' => 'club',
  'dagger' => 'knife',
  'mace' => 'club',
  'spear' => 'spear',
  'staff' => 'club',
  'crossbow' => 'bow',
  'sling' => 'sling',
  'battleaxe' => 'axe',
  'falchion' => 'sword',
  'greatsword' => 'sword',
  'longsword' => 'sword',
  'rapier' => 'sword',
  'scimitar' => 'sword',
  'shortsword' => 'sword',
  'warhammer' => 'hammer',
  'greataxe' => 'axe',
  'composite-longbow' => 'bow',
  'composite-shortbow' => 'bow',
  'longbow' => 'bow',
  'shortbow' => 'bow',
  'fist' => 'brawling',
];

echo "Starting weapon data enrichment...\n";

// Query all weapon items
$result = $database->select('dungeoncrawler_content_registry', 'r')
  ->fields('r', ['id', 'content_id', 'name', 'schema_data'])
  ->condition('content_type', 'item')
  ->execute();

$updated_count = 0;
$skipped_count = 0;
$error_count = 0;

foreach ($result as $row) {
  $schema_data = json_decode($row->schema_data, TRUE);
  
  if (!is_array($schema_data)) {
    echo "  [ERROR] {$row->content_id}: Invalid JSON in schema_data\n";
    $error_count++;
    continue;
  }
  
  // Skip non-weapons
  if (($schema_data['item_type'] ?? '') !== 'weapon') {
    $skipped_count++;
    continue;
  }
  
  $content_id = $row->content_id;
  $name = $row->name;
  $modified = FALSE;
  
  // Check if weapon_category is missing
  if (empty($schema_data['weapon_category']) && isset($category_defaults[$content_id])) {
    $schema_data['weapon_category'] = $category_defaults[$content_id];
    $modified = TRUE;
    echo "  [UPDATE] {$name} ({$content_id}): Added weapon_category = {$category_defaults[$content_id]}\n";
  }
  
  // Check if weapon_group is missing
  if (empty($schema_data['weapon_group']) && isset($group_defaults[$content_id])) {
    $schema_data['weapon_group'] = $group_defaults[$content_id];
    $modified = TRUE;
    echo "  [UPDATE] {$name} ({$content_id}): Added weapon_group = {$group_defaults[$content_id]}\n";
  }
  
  // Normalize traits if present (convert underscore form to display form)
  if (!empty($schema_data['traits']) && is_array($schema_data['traits'])) {
    $normalized_traits = [];
    foreach ($schema_data['traits'] as $trait) {
      // Convert "thrown_10ft" to "Thrown 10 ft"
      $normalized = preg_replace_callback(
        '/^([a-z]+)_(\d+)ft$/',
        function($matches) {
          return ucfirst($matches[1]) . ' ' . $matches[2] . ' ft';
        },
        $trait
      );
      
      // Convert "versatile_s" to "Versatile S"
      $normalized = preg_replace_callback(
        '/^([a-z]+)_([a-z])$/i',
        function($matches) {
          return ucfirst($matches[1]) . ' ' . strtoupper($matches[2]);
        },
        $normalized
      );
      
      // Capitalize first letter if not already done
      if ($normalized === $trait) {
        $normalized = ucfirst($trait);
      }
      
      $normalized_traits[] = $normalized;
    }
    
    if ($normalized_traits !== $schema_data['traits']) {
      $schema_data['traits'] = $normalized_traits;
      $modified = TRUE;
      echo "  [UPDATE] {$name} ({$content_id}): Normalized traits\n";
    }
  }
  
  // Update database if modified
  if ($modified) {
    $database->update('dungeoncrawler_content_registry')
      ->fields(['schema_data' => json_encode($schema_data, JSON_UNESCAPED_SLASHES)])
      ->condition('id', $row->id)
      ->execute();
    $updated_count++;
  } else {
    $skipped_count++;
  }
}

echo "\nEnrichment complete:\n";
echo "  Updated: {$updated_count}\n";
echo "  Skipped: {$skipped_count}\n";
echo "  Errors: {$error_count}\n";
echo "\nRun 'drush cr' to clear cache.\n";
