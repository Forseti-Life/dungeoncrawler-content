<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Context tool: summarizes room shape without shipping the whole aggregate.
 */
final class SummarizeRoomTopologyTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'summarize_room_topology',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Summarize hexes, terrain spread, elevation range, ports, and placements for the active draft.',
      FALSE,
      'draft room payload',
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    $room = $context->room();
    $hexes = is_array($room['hexes'] ?? NULL) ? $room['hexes'] : [];
    $placements = is_array($room['placements'] ?? NULL) ? $room['placements'] : [];

    $terrain = [];
    $lighting = [];
    $elevations = [];
    foreach ($hexes as $hex) {
      $terrain_type = (string) ($hex['terrain_type'] ?? 'unknown');
      $terrain[$terrain_type] = ($terrain[$terrain_type] ?? 0) + 1;
      $lighting_level = (string) ($hex['lighting'] ?? 'unknown');
      $lighting[$lighting_level] = ($lighting[$lighting_level] ?? 0) + 1;
      $elevations[] = (int) ($hex['elevation_ft'] ?? 0);
    }

    $families = [];
    foreach ($placements as $placement) {
      $family = (string) ($placement['definition_ref']['family'] ?? 'unknown');
      $families[$family] = ($families[$family] ?? 0) + 1;
    }

    return [
      'room_id' => (string) ($room['room_id'] ?? ''),
      'name' => (string) ($room['name'] ?? ''),
      'room_type' => (string) ($room['room_type'] ?? ''),
      'size_category' => (string) ($room['size_category'] ?? ''),
      'hex_count' => count($hexes),
      'terrain_distribution' => $terrain,
      'lighting_distribution' => $lighting,
      'elevation_range_ft' => $elevations === [] ? NULL : ['min' => min($elevations), 'max' => max($elevations)],
      'placement_count' => count($placements),
      'placement_families' => $families,
      'entry_port_count' => count((array) ($room['entry_ports'] ?? [])),
      'exit_port_count' => count((array) ($room['exit_ports'] ?? [])),
    ];
  }

}
