<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\ModuleConversion;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorCanonicalGenerationPlanService;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\MovementResolverService;

/**
 * Authors a new, original room from scene geometry, never from source content.
 *
 * This is an explicitly invoked adapter, not a conversion-release hook. The
 * caller supplies a fresh Room Editor draft and reviewed project-original
 * definition references; it must not supply source-module definitions.
 */
final class ModuleSceneGenerationAdapter {

  public function __construct(
    private readonly EditorCanonicalGenerationPlanService $generation,
    private readonly MovementResolverService $movement,
  ) {}

  /**
   * Generates, simulates and applies a room to an active Room Editor draft.
   *
   * $resource is one scene resource from ModuleConversionPlanService::buildPlan().
   * $originalDefinitionRefs is a nonempty, rights-reviewed list of canonical
   * ['family', 'definition_id', 'version', 'rights_status' => 'project_original']
   * refs. This explicit attestation is the caller's rights boundary; the
   * catalog does not itself establish copyright ownership. Only structural numbers and
   * generic instructions reach the generator; no source labels, paths, art,
   * descriptions, catalog labels or identifiers are sent to the provider.
   * Returns ['draft', 'validation', 'generation_plan']; does not publish.
   */
  public function generateRoom(
    array $resource,
    RoomEditorGmToolContext $context,
    array $originalDefinitionRefs,
    ?int $seed = NULL,
    bool $terminal = FALSE,
  ): array {
    $plan = $this->prepareRoom($resource, $context, $originalDefinitionRefs, $seed);
    return $this->applyPreparedRoom($plan, $context, $originalDefinitionRefs, $terminal);
  }

  /**
   * Runs canonical editor generation without applying commands or publishing.
   */
  public function prepareRoom(array $resource, RoomEditorGmToolContext $context, array $originalDefinitionRefs, ?int $seed = NULL, ?string $originalSizeCategory = NULL): array {
    if (($resource['resource_type'] ?? NULL) !== 'scene'
      || ($resource['disposition'] ?? NULL) !== 'requires_authoring'
      || ($resource['target']['family'] ?? NULL) !== 'room_draft') {
      throw new \InvalidArgumentException('module_scene_plan_resource_invalid');
    }
    $geometry = $resource['source']['source_context'] ?? NULL;
    if (!is_array($geometry)) {
      throw new \InvalidArgumentException('module_scene_geometry_required');
    }
    $has_source_geometry = is_int($geometry['width'] ?? NULL) && $geometry['width'] > 0
      && is_int($geometry['height'] ?? NULL) && $geometry['height'] > 0
      && is_int($geometry['grid_size'] ?? NULL) && $geometry['grid_size'] > 0
      && array_intersect(['width', 'height', 'grid_size'], (array) ($resource['source']['missing_source_fields'] ?? [])) === [];
    if ($has_source_geometry === ($originalSizeCategory !== NULL)
      || (!$has_source_geometry && !in_array($originalSizeCategory, ['small', 'medium', 'large'], TRUE))) {
      throw new \InvalidArgumentException('module_scene_geometry_required');
    }
    if ($originalDefinitionRefs === [] || !array_is_list($originalDefinitionRefs) || count($originalDefinitionRefs) > 20) {
      throw new \InvalidArgumentException('module_scene_original_placements_required');
    }
    foreach ($originalDefinitionRefs as $ref) {
      if (!is_array($ref) || !in_array($ref['family'] ?? NULL, ['creature', 'actor', 'item', 'obstacle', 'trap', 'hazard'], TRUE)
        || !is_string($ref['definition_id'] ?? NULL) || $ref['definition_id'] === ''
        || !is_string($ref['version'] ?? NULL) || $ref['version'] === ''
        || ($ref['rights_status'] ?? NULL) !== 'project_original'
        || !$context->definitions->definitionExists($ref['family'], $ref['definition_id'], $ref['version'])) {
        throw new \InvalidArgumentException('module_scene_original_definition_invalid');
      }
    }
    $draft = $context->draft();
    $room = $context->room();
    if (($draft['status'] ?? NULL) !== 'active'
      || ($draft['revision'] ?? NULL) !== 0
      || ($draft['room_id'] ?? NULL) !== NULL
      || !is_string($room['room_id'] ?? NULL)
      || !preg_match('/^new-room-[a-z0-9-]+$/', $room['room_id'])
      || ($room['name'] ?? NULL) !== 'Untitled Room'
      || ($room['description'] ?? NULL) !== 'A new canonical room.'
      || ($room['placements'] ?? NULL) !== []) {
      throw new \DomainException('module_scene_fresh_draft_required');
    }

    $columns = $has_source_geometry ? max(1, (int) ceil($geometry['width'] / $geometry['grid_size'])) : NULL;
    $rows = $has_source_geometry ? max(1, (int) ceil($geometry['height'] / $geometry['grid_size'])) : NULL;
    $area = $has_source_geometry ? min(40, $columns) * min(40, $rows) : NULL;
    $size = $originalSizeCategory ?? ($area <= 20 ? 'small' : ($area <= 48 ? 'medium' : 'large'));
    $arguments = [
      'prompt' => $has_source_geometry
        ? sprintf('Create an original generic chamber with entry and exit ports. Structural scale: %d by %d grid cells. Invent all names and descriptions; do not recreate any source map or story.', min(80, $columns), min(80, $rows))
        : 'Create an original generic chamber with entry and exit ports. Source dimensions are unavailable; invent an original room of the reviewed size category without claiming source geometry. Invent all names and descriptions.',
      'size_category' => $size,
      'room_type' => 'chamber',
      'min_hexes' => 16,
      'max_hexes' => 40,
      'placeable_families' => [],
    ];
    if ($seed !== NULL) {
      $arguments['seed'] = $seed;
    }
    $plan = $this->generation->generateRoomLayout($arguments, $context);
    return $plan;
  }

  /**
   * Applies a prepared canonical plan to its original, still-fresh draft.
   */
  public function applyPreparedRoom(array $plan, RoomEditorGmToolContext $context, array $originalDefinitionRefs, bool $terminal = FALSE): array {
    $context->invalidate();
    $draft = $context->draft();
    $room = $context->room();
    if (($draft['status'] ?? NULL) !== 'active'
      || ($draft['revision'] ?? NULL) !== 0
      || ($draft['room_id'] ?? NULL) !== NULL
      || !is_string($room['room_id'] ?? NULL)
      || !preg_match('/^new-room-[a-z0-9-]+$/', $room['room_id'])
      || ($room['name'] ?? NULL) !== 'Untitled Room'
      || ($room['description'] ?? NULL) !== 'A new canonical room.'
      || ($room['placements'] ?? NULL) !== []
      || ($plan['command_plan']['draft_id'] ?? NULL) !== $context->draftId) {
      throw new \DomainException('module_scene_fresh_draft_required');
    }
    $commandPlan = $plan['command_plan'] ?? NULL;
    if (!is_array($commandPlan) || ($commandPlan['base_revision'] ?? NULL) !== 0 || !is_array($commandPlan['steps'] ?? NULL) || $commandPlan['steps'] === []) {
      throw new \DomainException('module_scene_generation_plan_invalid');
    }
    $commands = [];
    foreach ($commandPlan['steps'] as $step) {
      if (!is_array($step) || !is_string($step['command_type'] ?? NULL) || !is_array($step['payload'] ?? NULL)) {
        throw new \DomainException('module_scene_generation_plan_invalid');
      }
      if (!$terminal || $step['command_type'] !== 'add_exit_port') {
        $commands[] = ['type' => $step['command_type'], 'payload' => $step['payload']];
      }
    }
    $initial = $context->roomEditor->simulateCommands($context->draftId, $commands, 'editing');
    $hexes = $initial['projected_room']['hexes'] ?? NULL;
    if (empty($initial['applies_cleanly']) || empty($initial['validation']['valid']) || !is_array($hexes) || $hexes === []) {
      throw new \DomainException('module_scene_generation_projection_invalid');
    }
    $anchor = $hexes[0];
    if (!is_int($anchor['q'] ?? NULL) || !is_int($anchor['r'] ?? NULL) || count($commands) + count($originalDefinitionRefs) > 160) {
      throw new \DomainException('module_scene_generation_projection_invalid');
    }
    $available = [];
    foreach ($hexes as $hex) {
      if (is_int($hex['q'] ?? NULL) && is_int($hex['r'] ?? NULL)) {
        $available[$hex['q'] . ':' . $hex['r']] = ['q' => $hex['q'], 'r' => $hex['r']];
      }
    }
    if (count($available) < count($originalDefinitionRefs)) {
      throw new \DomainException('module_scene_placement_anchors_absent');
    }
    $anchors = array_values($available);
    foreach ($originalDefinitionRefs as $index => $ref) {
      $commands[] = [
        'type' => 'place_object',
        'payload' => [
          'instance_id' => $this->uuid(),
          'definition_ref' => ['family' => $ref['family'], 'definition_id' => $ref['definition_id'], 'version' => $ref['version']],
          'anchor_hex' => $anchors[$index],
          'facing' => 0,
          'elevation_ft' => 0,
          'overrides' => [],
        ],
      ];
    }
    $simulation = $context->roomEditor->simulateCommands($context->draftId, $commands, 'publication');
    if (empty($simulation['applies_cleanly']) || empty($simulation['validation']['valid'])
      || count((array) ($simulation['projected_room']['placements'] ?? [])) < count($originalDefinitionRefs)
      || empty($simulation['projected_room']['entry_ports'])
      || count((array) ($simulation['projected_room']['exit_ports'] ?? [])) !== ($terminal ? 0 : 1)) {
      throw new \DomainException('module_scene_required_placements_or_ports_absent');
    }

    $revision = 0;
    foreach ($commands as $command) {
      $result = $context->roomEditor->applyCommand($context->draftId, $command + [
        'command_id' => $this->uuid(),
        'expected_revision' => $revision,
      ]);
      $revision = $result['draft']['revision'];
    }
    $context->invalidate();
    $validation = $context->validation('publication');
    $final = $context->draft();
    if (empty($validation['valid'])
      || count((array) ($final['room']['placements'] ?? [])) < count($originalDefinitionRefs)
      || empty($final['room']['entry_ports'])
      || count((array) ($final['room']['exit_ports'] ?? [])) !== ($terminal ? 0 : 1)) {
      throw new \DomainException('module_scene_room_validation_failed');
    }
    $this->assertTraversableRoom($final['room']);
    return ['draft' => $final, 'validation' => $validation, 'generation_plan' => $plan];
  }

  /**
   * Checks runtime movement reachability of a canonical room in memory.
   */
  public function assertTraversableRoom(array $room, bool $requireEveryHexPassable = FALSE): void {
    $hexes = $room['hexes'] ?? NULL;
    if (!is_array($hexes) || $hexes === []) {
      throw new \DomainException('module_scene_room_traversal_invalid');
    }
    $scope = ['__scope_type' => 'movement', 'room_hexes' => $hexes, 'hexes' => []];
    $walkable = [];
    foreach ($hexes as $hex) {
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
        throw new \DomainException('module_scene_room_traversal_invalid');
      }
      if ($this->movement->isPassable(['q' => $hex['q'], 'r' => $hex['r']], $scope)) {
        $walkable[$hex['q'] . ':' . $hex['r']] = $hex;
      }
      elseif ($requireEveryHexPassable) {
        throw new \DomainException('module_scene_room_traversal_invalid');
      }
    }
    $entry = $room['entry_ports'][0]['hex'] ?? NULL;
    if (!is_array($entry) || !is_int($entry['q'] ?? NULL) || !is_int($entry['r'] ?? NULL)) {
      throw new \DomainException('module_scene_room_traversal_invalid');
    }
    $start = $entry['q'] . ':' . $entry['r'];
    if (!isset($walkable[$start])) {
      throw new \DomainException('module_scene_room_traversal_invalid');
    }
    $reached = [$start => TRUE];
    $queue = [$walkable[$start]];
    $neighbors = [[1, 0], [0, 1], [-1, 1], [-1, 0], [0, -1], [1, -1]];
    for ($cursor = 0; $cursor < count($queue); $cursor++) {
      foreach ($neighbors as [$dq, $dr]) {
        $key = ($queue[$cursor]['q'] + $dq) . ':' . ($queue[$cursor]['r'] + $dr);
        if (isset($walkable[$key]) && !isset($reached[$key])) {
          $reached[$key] = TRUE;
          $queue[] = $walkable[$key];
        }
      }
    }
    if (count($reached) !== count($walkable)) {
      throw new \DomainException('module_scene_room_traversal_invalid');
    }
    foreach (array_merge((array) ($room['exit_ports'] ?? []), (array) ($room['placements'] ?? [])) as $target) {
      $hex = $target['hex'] ?? $target['anchor_hex'] ?? NULL;
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)
        || !isset($reached[$hex['q'] . ':' . $hex['r']])) {
        throw new \DomainException('module_scene_room_traversal_invalid');
      }
    }
  }

  private function uuid(): string {
    $hex = bin2hex(random_bytes(16));
    $hex[12] = '4';
    $hex[16] = dechex((hexdec($hex[16]) & 3) | 8);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
  }

}
