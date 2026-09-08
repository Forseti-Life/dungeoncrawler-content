<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Geometry\RoomPortEdgePolicy;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationException;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\GenerationVocabulary;

/**
 * Editor GM adapter that projects canonical generation into command plans.
 *
 * The provider/prompt/JSON/retry/provenance pipeline lives in
 * CanonicalGenerationService; this adapter only translates validated generated
 * aggregates to Room/Dungeon Editor command_plan proposals.
 */
class EditorCanonicalGenerationPlanService {

  private const PLAN_VERSION = EditorGmHarnessService::COMMAND_PLAN_CONTRACT_VERSION;
  private const GENERATION_PLAN_VERSION = 'editor-generation-plan-v1';
  private const ROOM_MAX_HEXES = 80;
  private const ROOM_MAX_COMMANDS = 160;
  private const ROOM_MAX_PLACEMENTS = 20;
  private const DUNGEON_MAX_PLACEMENTS = 20;
  private const DUNGEON_MAX_LINKS = 40;
  private const DUNGEON_MAX_COMMANDS = 120;

  public function __construct(
    private readonly CanonicalGenerationService $generation,
    private readonly CanonicalDefinitionService $definitions,
  ) {}

  /**
   * Generates a non-mutating Room Editor command plan.
   */
  public function generateRoomLayout(array $arguments, RoomEditorGmToolContext $context): array {
    $input = $this->normalizeRoomInput($arguments);
    $draft = $context->draft();
    $room = $context->room();
    $catalog = $this->placeableCatalog($input['placeable_families']);
    $operation = 'editor_generation_room_layout';

    return $this->generation->completeJson('generate_room_layout', $operation, $input['seed'], 3000,
      function (array $prior_findings) use ($input, $draft, $room, $catalog): string {
        return $this->roomPrompt($input, $draft, $room, $catalog, $prior_findings);
      },
      function (array $decoded, array $provenance) use ($context, $input, $catalog): array {
        return $this->roomPlanFromDecoded($decoded, $provenance, $context, $input, $catalog);
      }
    );
  }

  /**
   * Generates a non-mutating Dungeon Editor command plan.
   */
  public function generateDungeonLayout(array $arguments, DungeonEditorGmToolContext $context): array {
    $input = $this->normalizeDungeonInput($arguments);
    $draft = $context->draft();
    $dungeon = $context->dungeon();
    $library = $context->roomLibrary();
    if ($library === []) {
      throw $this->exception('generation_catalog_reference_unresolved', [$this->finding('generation_catalog_reference_unresolved', '/room_library', 'No published room versions are available for dungeon generation.')]);
    }
    $operation = 'editor_generation_dungeon_layout';

    return $this->generation->completeJson('generate_dungeon_layout', $operation, $input['seed'], 3000,
      function (array $prior_findings) use ($input, $draft, $dungeon, $library): string {
        return $this->dungeonPrompt($input, $draft, $dungeon, $library, $prior_findings);
      },
      function (array $decoded, array $provenance) use ($context, $input, $library): array {
        return $this->dungeonPlanFromDecoded($decoded, $provenance, $context, $input, $library);
      }
    );
  }

  private function normalizeRoomInput(array $arguments): array {
    $prompt = $this->boundedString($arguments, 'prompt', TRUE, 1, 2000);
    $theme = $this->boundedString($arguments, 'theme', FALSE, 0, 100);
    $size = $this->enum($arguments, 'size_category', GenerationVocabulary::SIZE_CATEGORIES, 'medium');
    $type = $this->enum($arguments, 'room_type', GenerationVocabulary::ROOM_TYPES, 'chamber');
    $level = $this->intRange($arguments, 'level', -1, 25, 1);
    $min_hexes = $this->intRange($arguments, 'min_hexes', 1, self::ROOM_MAX_HEXES, 16);
    $max_default = ['tiny' => 16, 'small' => 20, 'medium' => 32, 'large' => 48, 'huge' => 64, 'gargantuan' => 80][$size];
    $max_hexes = $this->intRange($arguments, 'max_hexes', 1, self::ROOM_MAX_HEXES, $max_default);
    if ($max_hexes < $min_hexes) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/max_hexes', 'max_hexes must be greater than or equal to min_hexes.')], 400);
    }
    $families = GenerationVocabulary::PLACEABLE_FAMILIES;
    if (isset($arguments['placeable_families'])) {
      if (!is_array($arguments['placeable_families']) || array_is_list($arguments['placeable_families']) === FALSE) {
        throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/placeable_families', 'placeable_families must be a list.')], 400);
      }
      $families = [];
      foreach ($arguments['placeable_families'] as $index => $family) {
        if (!is_string($family) || !in_array($family, GenerationVocabulary::PLACEABLE_FAMILIES, TRUE)) {
          throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/placeable_families/' . $index, 'Unknown placeable family.')], 400);
        }
        $families[] = $family;
      }
      $families = array_values(array_unique($families));
    }
    return compact('prompt', 'theme', 'size', 'type', 'level', 'min_hexes', 'max_hexes') + [
      'size_category' => $size,
      'room_type' => $type,
      'placeable_families' => $families,
      'seed' => $this->seed($arguments),
    ];
  }

  private function normalizeDungeonInput(array $arguments): array {
    return [
      'prompt' => $this->boundedString($arguments, 'prompt', TRUE, 1, 2000),
      'theme' => $this->boundedString($arguments, 'theme', FALSE, 0, 100),
      'room_count' => $this->intRange($arguments, 'room_count', 3, self::DUNGEON_MAX_PLACEMENTS, 3),
      'level' => $this->intRange($arguments, 'level', -1, 25, 1),
      'link_kind' => $this->enum($arguments, 'link_kind', GenerationVocabulary::LINK_KINDS, 'door'),
      'link_direction' => $this->enum($arguments, 'link_direction', GenerationVocabulary::LINK_DIRECTIONS, 'bidirectional'),
      'default_state' => $this->enum($arguments, 'default_state', GenerationVocabulary::LINK_STATES, 'closed'),
      'seed' => $this->seed($arguments),
    ];
  }

  private function seed(array $arguments): int {
    if (!array_key_exists('seed', $arguments) || $arguments['seed'] === NULL || $arguments['seed'] === '') {
      return random_int(0, 2147483647);
    }
    if (!is_int($arguments['seed']) || $arguments['seed'] < 0 || $arguments['seed'] > 2147483647) {
      throw $this->exception('generation_seed_invalid', [$this->finding('generation_seed_invalid', '/seed', 'Seed must be an integer from 0 through 2147483647.')], 400);
    }
    return $arguments['seed'];
  }

  private function boundedString(array $arguments, string $key, bool $required, int $min, int $max): string {
    if (!array_key_exists($key, $arguments)) {
      if ($required) {
        throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, $key . ' is required.')], 400);
      }
      return '';
    }
    if (!is_string($arguments[$key])) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, $key . ' must be a string.')], 400);
    }
    $value = trim($arguments[$key]);
    $length = mb_strlen($value);
    if ($length < $min || $length > $max) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, sprintf('%s must be %d..%d characters.', $key, $min, $max))], 400);
    }
    return $value;
  }

  private function intRange(array $arguments, string $key, int $min, int $max, int $default): int {
    if (!array_key_exists($key, $arguments) || $arguments[$key] === NULL || $arguments[$key] === '') {
      return $default;
    }
    if (!is_int($arguments[$key])) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, $key . ' must be an integer.')], 400);
    }
    if ($arguments[$key] < $min || $arguments[$key] > $max) {
      $code = ($max === self::ROOM_MAX_HEXES || $max === self::DUNGEON_MAX_PLACEMENTS) ? 'generation_size_limit_exceeded' : 'generation_input_invalid';
      throw $this->exception($code, [$this->finding($code, '/' . $key, sprintf('%s must be %d..%d.', $key, $min, $max))], $code === 'generation_input_invalid' ? 400 : 422);
    }
    return $arguments[$key];
  }

  private function enum(array $arguments, string $key, array $allowed, string $default): string {
    if (!array_key_exists($key, $arguments) || $arguments[$key] === NULL || $arguments[$key] === '') {
      return $default;
    }
    if (!is_string($arguments[$key]) || !in_array($arguments[$key], $allowed, TRUE)) {
      throw $this->exception('generation_input_invalid', [$this->finding('generation_input_invalid', '/' . $key, $key . ' is not in the allowed set.')], 400);
    }
    return $arguments[$key];
  }

  private function placeableCatalog(array $families): array {
    $catalog = $this->definitions->catalog(NULL, '', 250, 0);
    $definitions = [];
    foreach ((array) ($catalog['definitions'] ?? []) as $definition) {
      if (!is_array($definition)) {
        continue;
      }
      $family = (string) ($definition['family'] ?? '');
      if (!in_array($family, $families, TRUE)) {
        continue;
      }
      $definitions[] = [
        'family' => $family,
        'definition_id' => (string) ($definition['definition_id'] ?? ''),
        'version' => (string) ($definition['version'] ?? '1.0.0'),
        'label' => (string) ($definition['label'] ?? $definition['name'] ?? $definition['definition_id'] ?? ''),
      ];
    }
    return $definitions;
  }

  private function roomPrompt(array $input, array $draft, array $room, array $catalog, array $prior_findings): string {
    $catalog_refs = array_slice($catalog, 0, 60);
    $shape = [
      'name' => 'string 1..200',
      'description' => 'string 1..2000',
      'room_type' => implode('|', GenerationVocabulary::ROOM_TYPES),
      'size_category' => implode('|', GenerationVocabulary::SIZE_CATEGORIES),
      'terrain_type' => implode('|', GenerationVocabulary::CANONICAL_TERRAIN_TYPES),
      'lighting' => implode('|', GenerationVocabulary::LIGHTING_LEVELS),
      'hexes' => [['q' => 0, 'r' => 0, 'terrain_type' => 'stone_floor', 'elevation_ft' => 0, 'lighting' => 'dim_light']],
      'entry_ports' => [['port_id' => 'entry-1', 'hex' => ['q' => -2, 'r' => 0], 'label' => 'Entry', 'arrival_facing' => 0, 'is_default' => TRUE, 'tags' => []]],
      'exit_ports' => [['port_id' => 'exit-1', 'hex' => ['q' => 2, 'r' => 0], 'label' => 'Exit', 'kind' => 'door', 'direction' => 'bidirectional', 'default_state' => 'closed', 'destination_hint' => NULL, 'requirements' => [], 'tags' => []]],
      'placements' => [['family' => 'item', 'definition_id' => 'catalog_id', 'version' => '1.0.0', 'anchor_hex' => ['q' => 0, 'r' => 0], 'facing' => 0, 'overrides' => []]],
    ];
    return $this->promptText('room_editor', $input, $draft, [
      'current_room' => [
        'room_id' => $room['room_id'] ?? '',
        'name' => $room['name'] ?? '',
        'revision' => $draft['revision'] ?? 0,
        'hex_count' => count((array) ($room['hexes'] ?? [])),
      ],
      'catalog' => $catalog_refs,
      'required_output_shape' => $shape,
      'requirements' => [
        'Return one JSON object only. No prose.',
        'Use at least ' . $input['min_hexes'] . ' and at most ' . $input['max_hexes'] . ' hexes.',
        'Use only catalog definition refs for placements; include at least one placement when catalog is non-empty.',
        'Boundary port edge is computed and enforced server-side; provide desired port anchor hexes only.',
      ],
      'prior_findings' => $prior_findings,
    ]);
  }

  private function dungeonPrompt(array $input, array $draft, array $dungeon, array $library, array $prior_findings): string {
    $rooms = array_map(static fn(array $room): array => [
      'room_id' => $room['room_id'],
      'version_id' => $room['version_id'],
      'version' => $room['version'],
      'name' => $room['name'],
      'room_type' => $room['room_type'],
      'hex_count' => $room['hex_count'],
      'entry_port_count' => $room['entry_port_count'],
      'exit_port_count' => $room['exit_port_count'],
      'ports' => array_slice((array) ($room['ports'] ?? []), 0, 8),
    ], array_slice($library, 0, 20));
    $shape = [
      'name' => 'string 1..200',
      'description' => 'string <=8000',
      'theme' => 'string <=100',
      'rooms' => [['room_id' => 'published-room-id', 'role' => 'entrance|connector|goal']]
    ];
    return $this->promptText('dungeon_editor', $input, $draft, [
      'current_dungeon' => [
        'dungeon_id' => $dungeon['dungeon_id'] ?? '',
        'name' => $dungeon['name'] ?? '',
        'revision' => $draft['revision'] ?? 0,
        'placement_count' => count((array) ($dungeon['room_placements'] ?? [])),
      ],
      'published_room_library' => $rooms,
      'required_output_shape' => $shape,
      'requirements' => [
        'Return one JSON object only. No prose.',
        'Return exactly ' . $input['room_count'] . ' room references and use only room_id values from published_room_library.',
        'Server will compute non-overlapping sealed placements using RoomPlacementTransformer.',
      ],
      'prior_findings' => $prior_findings,
    ]);
  }

  private function promptText(string $surface, array $input, array $draft, array $payload): string {
    $document = [
      'surface_id' => $surface,
      'validation_profile' => 'editing',
      'author_inputs' => $input,
      'draft_summary' => [
        'draft_id' => $draft['draft_id'] ?? NULL,
        'revision' => $draft['revision'] ?? NULL,
      ],
      'originality_rule_ad_14' => $this->generation->originalityInstruction(),
    ] + $payload;
    return json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
  }

  private function roomPlanFromDecoded(array $decoded, array $provenance, RoomEditorGmToolContext $context, array $input, array $catalog): array {
    $hexes = $this->generatedHexes($decoded, $input['min_hexes'], $input['max_hexes']);
    $terrain = $this->valueIn($decoded['terrain_type'] ?? 'stone_floor', GenerationVocabulary::CANONICAL_TERRAIN_TYPES, '/terrain_type');
    $lighting = $this->valueIn($decoded['lighting'] ?? 'bright_light', GenerationVocabulary::LIGHTING_LEVELS, '/lighting');
    $room_type = $this->valueIn($decoded['room_type'] ?? $input['room_type'], GenerationVocabulary::ROOM_TYPES, '/room_type');
    $size = $this->valueIn($decoded['size_category'] ?? $input['size_category'], GenerationVocabulary::SIZE_CATEGORIES, '/size_category');
    $current = $context->room();
    $plan_room = $current;
    $plan_room['hexes'] = $this->mergeProjectedHexes((array) ($current['hexes'] ?? []), $hexes, $terrain, $lighting);

    $steps = [];
    $steps[] = $this->step(count($steps) + 1, 'set_room_metadata', [
      'changes' => [
        'name' => $this->requiredString($decoded, 'name', 1, 200),
        'description' => $this->requiredString($decoded, 'description', 1, 2000),
        'room_type' => $room_type,
        'size_category' => $size,
        'metadata' => ['generated_by' => $provenance],
      ],
    ], 'Record generated room metadata and provenance.');

    $existing = RoomPortEdgePolicy::footprint($current);
    foreach ($hexes as $hex) {
      $key = RoomPortEdgePolicy::hexKey($hex);
      if (!isset($existing[$key])) {
        $steps[] = $this->step(count($steps) + 1, 'add_hex', ['hex' => $hex + ['terrain_type' => $terrain, 'elevation_ft' => 0, 'lighting' => $lighting]], sprintf('Add generated hex (%d, %d).', $hex['q'], $hex['r']));
      }
      $steps[] = $this->step(count($steps) + 1, 'set_hex_terrain', ['hex' => ['q' => $hex['q'], 'r' => $hex['r']], 'terrain_type' => $hex['terrain_type']], sprintf('Set generated terrain at (%d, %d).', $hex['q'], $hex['r']));
      $steps[] = $this->step(count($steps) + 1, 'set_hex_elevation', ['hex' => ['q' => $hex['q'], 'r' => $hex['r']], 'elevation_ft' => $hex['elevation_ft']], sprintf('Set generated elevation at (%d, %d).', $hex['q'], $hex['r']));
    }

    $entries = $this->ports($decoded, 'entry_ports', TRUE, $plan_room, $input['seed']);
    $exits = $this->ports($decoded, 'exit_ports', FALSE, $plan_room, $input['seed']);
    if ($entries === [] || $exits === []) {
      throw $this->nonconforming([$this->finding('required_missing', $entries === [] ? '/entry_ports' : '/exit_ports', 'Generated room must include at least one entry and one exit port for G1.')]);
    }
    $steps = array_merge($steps, $this->portSteps($current, $entries, TRUE, count($steps)));
    $steps = array_merge($steps, $this->portSteps($current, $exits, FALSE, count($steps)));

    $placements = $this->placements($decoded, $catalog, $hexes, $input['seed']);
    if ($catalog !== [] && $placements === []) {
      throw $this->nonconforming([$this->finding('required_missing', '/placements', 'At least one canonical place_object placement is required when catalog entries are available.')]);
    }
    foreach ($placements as $placement) {
      $steps[] = $this->step(count($steps) + 1, 'place_object', $placement, sprintf('Place generated %s/%s.', $placement['definition_ref']['family'], $placement['definition_ref']['definition_id']));
    }

    if (count($placements) > self::ROOM_MAX_PLACEMENTS || count($steps) > self::ROOM_MAX_COMMANDS) {
      throw $this->exception('generation_size_limit_exceeded', [$this->finding('generation_size_limit_exceeded', '/command_plan/steps', 'Generated room plan exceeds command or placement caps.')]);
    }

    $commands = $this->commandsFromSteps($steps);
    $simulation = $context->roomEditor->simulateCommands($context->draftId, $commands, 'editing');
    $this->assertRoomSimulation($simulation);
    $projected = $simulation['projected_room'];
    $this->assertGeneratedRoomPortsFollowPolicy($projected);

    $plan = [
      'schema_version' => self::PLAN_VERSION,
      'draft_id' => $context->draftId,
      'base_revision' => (int) ($context->draft()['revision'] ?? 0),
      'steps' => $steps,
    ];
    return [
      'schema_version' => self::GENERATION_PLAN_VERSION,
      'generation_type' => 'room_layout',
      'seed' => $input['seed'],
      'metadata' => ['generated_by' => $provenance],
      'validation' => $simulation['validation'],
      'command_plan' => $plan,
      'preview_summary' => [
        'hex_count' => count((array) ($projected['hexes'] ?? [])),
        'entry_ports' => count((array) ($projected['entry_ports'] ?? [])),
        'exit_ports' => count((array) ($projected['exit_ports'] ?? [])),
        'placement_count' => count((array) ($projected['placements'] ?? [])),
      ],
    ];
  }

  private function dungeonPlanFromDecoded(array $decoded, array $provenance, DungeonEditorGmToolContext $context, array $input, array $library): array {
    $selected = $this->selectDungeonRooms($decoded, $library, $input);
    if (count($selected) > self::DUNGEON_MAX_PLACEMENTS) {
      throw $this->exception('generation_size_limit_exceeded', [$this->finding('generation_size_limit_exceeded', '/rooms', 'Generated dungeon exceeds placement cap.')]);
    }

    $steps = [];
    $steps[] = $this->step(1, 'set_dungeon_metadata', [
      'changes' => [
        'name' => $this->requiredString($decoded, 'name', 1, 200),
        'description' => $this->optionalString($decoded, 'description', 8000, 'Generated dungeon layout.'),
        'theme' => $this->optionalString($decoded, 'theme', 100, $input['theme'] !== '' ? $input['theme'] : 'generated'),
        'depth' => max(0, $input['level']),
        'metadata' => ['generated_by' => $provenance],
      ],
    ], 'Record generated dungeon metadata and provenance.');

    $placements = [];
    $occupied = [];
    $links = [];
    foreach ($selected as $index => $room) {
      $placement_id = $this->deterministicUuid($input['seed'], 'dungeon-placement', $index + 1);
      if ($index === 0) {
        $placement = [
          'placement_id' => $placement_id,
          'room_id' => $room['room_id'],
          'version_id' => $room['version_id'],
          'origin' => ['q' => 0, 'r' => 0],
          'rotation_steps' => 0,
          'label' => (string) ($room['role'] ?? $room['name']),
          'is_level_entrance' => FALSE,
          'tags' => [],
        ];
      }
      else {
        $anchor = $placements[$index - 1];
        $anchor_room = $selected[$index - 1];
        [$placement, $link] = $this->sealedPlacement($anchor, $anchor_room, $room, $occupied, $placement_id, $input, $index + 1);
        $links[] = $link;
      }
      $placements[] = $placement;
      foreach ((array) ($room['footprint'] ?? []) as $hex) {
        $level = RoomPlacementTransformer::toLevel($hex, $placement);
        $occupied[RoomPlacementTransformer::hexKey($level)][] = $placement_id;
      }
      $steps[] = $this->step(count($steps) + 1, 'place_room', [
        'placement_id' => $placement['placement_id'],
        'room_id' => $placement['room_id'],
        'version_id' => $placement['version_id'],
        'origin' => $placement['origin'],
        'rotation_steps' => $placement['rotation_steps'],
        'label' => $placement['label'],
      ], sprintf('Place published room %s as %s.', $placement['room_id'], $placement['label']));
      if ($index === 0) {
        $steps[] = $this->step(count($steps) + 1, 'set_placement_metadata', [
          'placement_id' => $placement_id,
          'changes' => ['is_level_entrance' => TRUE],
        ], 'Mark the first generated placement as the only level entrance.');
      }
    }
    foreach ($links as $link) {
      $steps[] = $this->step(count($steps) + 1, 'link_ports', $link, sprintf('Seal %s:%s to %s:%s.', $link['from']['placement_id'], $link['from']['port_id'], $link['to']['placement_id'], $link['to']['port_id']));
    }

    if (count($links) > self::DUNGEON_MAX_LINKS || count($steps) > self::DUNGEON_MAX_COMMANDS) {
      throw $this->exception('generation_size_limit_exceeded', [$this->finding('generation_size_limit_exceeded', '/command_plan/steps', 'Generated dungeon plan exceeds command or link caps.')]);
    }

    $draft = $context->draft();
    $simulation = $context->dungeonEditor->simulateCommands($context->draftId, $this->envelopesFromSteps($steps, (int) ($draft['revision'] ?? 0), $input['seed']), 'editing');
    $this->assertDungeonSimulation($simulation);

    return [
      'schema_version' => self::GENERATION_PLAN_VERSION,
      'generation_type' => 'dungeon_layout',
      'seed' => $input['seed'],
      'metadata' => ['generated_by' => $provenance],
      'selected_rooms' => array_map(static fn(array $room): array => [
        'room_id' => $room['room_id'],
        'version_id' => $room['version_id'],
        'version' => $room['version'],
        'role' => $room['role'] ?? 'generated',
      ], $selected),
      'validation' => $simulation['validation'],
      'command_plan' => [
        'schema_version' => self::PLAN_VERSION,
        'draft_id' => $context->draftId,
        'base_revision' => (int) ($draft['revision'] ?? 0),
        'steps' => $steps,
      ],
      'preview_summary' => [
        'placement_count' => count((array) ($simulation['dungeon']['room_placements'] ?? [])),
        'port_link_count' => count((array) ($simulation['dungeon']['port_links'] ?? [])),
      ],
    ];
  }

  private function generatedHexes(array $decoded, int $min, int $max): array {
    if (!is_array($decoded['hexes'] ?? NULL) || !array_is_list($decoded['hexes'])) {
      throw $this->nonconforming([$this->finding('required_missing', '/hexes', 'hexes must be a list.')]);
    }
    if (count($decoded['hexes']) > $max || count($decoded['hexes']) > self::ROOM_MAX_HEXES) {
      throw $this->exception('generation_size_limit_exceeded', [$this->finding('generation_size_limit_exceeded', '/hexes', 'Generated room exceeds the hex cap.')]);
    }
    if (count($decoded['hexes']) < $min) {
      throw $this->nonconforming([$this->finding('room_hex_count_below_min', '/hexes', sprintf('Generated room must include at least %d hexes.', $min))]);
    }
    $seen = [];
    $hexes = [];
    foreach ($decoded['hexes'] as $index => $hex) {
      if (!is_array($hex) || !is_int($hex['q'] ?? NULL) || !is_int($hex['r'] ?? NULL)) {
        throw $this->nonconforming([$this->finding('hex_coordinate_invalid', '/hexes/' . $index, 'Hex coordinates must be integer q/r.')]);
      }
      $record = [
        'q' => $hex['q'],
        'r' => $hex['r'],
        'terrain_type' => $this->valueIn($hex['terrain_type'] ?? ($decoded['terrain_type'] ?? 'stone_floor'), GenerationVocabulary::CANONICAL_TERRAIN_TYPES, '/hexes/' . $index . '/terrain_type'),
        'elevation_ft' => isset($hex['elevation_ft']) && is_int($hex['elevation_ft']) ? max(-50, min(200, $hex['elevation_ft'])) : 0,
        'lighting' => $this->valueIn($hex['lighting'] ?? ($decoded['lighting'] ?? 'bright_light'), GenerationVocabulary::LIGHTING_LEVELS, '/hexes/' . $index . '/lighting'),
      ];
      $key = RoomPortEdgePolicy::hexKey($record);
      if (isset($seen[$key])) {
        throw $this->nonconforming([$this->finding('duplicate_hex', '/hexes/' . $index, 'Generated hex coordinates must be unique.')]);
      }
      $seen[$key] = TRUE;
      $hexes[] = $record;
    }
    return $hexes;
  }

  private function mergeProjectedHexes(array $current, array $generated, string $terrain, string $lighting): array {
    $merged = [];
    foreach ($current as $hex) {
      if (is_array($hex)) {
        $merged[RoomPortEdgePolicy::hexKey($hex)] = $hex;
      }
    }
    foreach ($generated as $hex) {
      $merged[RoomPortEdgePolicy::hexKey($hex)] = $hex + ['terrain_type' => $terrain, 'elevation_ft' => 0, 'lighting' => $lighting];
    }
    return array_values($merged);
  }

  private function ports(array $decoded, string $key, bool $entry, array $plan_room, int $seed): array {
    if (!is_array($decoded[$key] ?? NULL) || !array_is_list($decoded[$key])) {
      return [];
    }
    $ports = [];
    foreach ($decoded[$key] as $index => $port) {
      if (!is_array($port) || !is_array($port['hex'] ?? NULL)) {
        throw $this->nonconforming([$this->finding('port_invalid', '/' . $key . '/' . $index, 'Port must include hex.')]);
      }
      if (!is_int($port['hex']['q'] ?? NULL) || !is_int($port['hex']['r'] ?? NULL)) {
        throw $this->nonconforming([$this->finding('port_invalid', '/' . $key . '/' . $index . '/hex', 'Port hex coordinates must be integer q/r.')]);
      }
      $target = RoomPortEdgePolicy::target($plan_room, $port);
      $base = [
        'port_id' => (string) ($port['port_id'] ?? ($entry ? 'entry-' : 'exit-') . ($index + 1)),
        'hex' => $target['hex'],
        'edge' => $target['edge'],
        'label' => (string) ($port['label'] ?? ($entry ? 'Entry' : 'Exit') . ' ' . ($index + 1)),
        'tags' => array_values(array_filter((array) ($port['tags'] ?? []), 'is_string')),
      ];
      if ($entry) {
        $base += [
          'arrival_facing' => isset($port['arrival_facing']) && is_int($port['arrival_facing']) ? max(0, min(5, $port['arrival_facing'])) : RoomPlacementTransformer::opposite($target['edge']),
          'is_default' => $index === 0 ? TRUE : !empty($port['is_default']),
        ];
      }
      else {
        $base += [
          'kind' => $this->valueIn($port['kind'] ?? 'door', GenerationVocabulary::LINK_KINDS, '/' . $key . '/' . $index . '/kind'),
          'direction' => $this->valueIn($port['direction'] ?? 'bidirectional', GenerationVocabulary::LINK_DIRECTIONS, '/' . $key . '/' . $index . '/direction'),
          'default_state' => $this->valueIn($port['default_state'] ?? 'closed', GenerationVocabulary::LINK_STATES, '/' . $key . '/' . $index . '/default_state'),
          'destination_hint' => isset($port['destination_hint']) ? (string) $port['destination_hint'] : NULL,
          'linked_placement_id' => NULL,
          'requirements' => is_array($port['requirements'] ?? NULL) ? $port['requirements'] : [],
        ];
      }
      $ports[] = $base;
    }
    return $ports;
  }

  private function portSteps(array $current, array $ports, bool $entry, int $offset): array {
    $bucket = $entry ? 'entry_ports' : 'exit_ports';
    $command = $entry ? 'add_entry_port' : 'add_exit_port';
    $update = $entry ? 'update_entry_port' : 'update_exit_port';
    $existing = [];
    foreach ((array) ($current[$bucket] ?? []) as $port) {
      $existing[(string) ($port['port_id'] ?? '')] = TRUE;
    }
    $steps = [];
    foreach ($ports as $port) {
      if (isset($existing[$port['port_id']])) {
        $changes = $port;
        unset($changes['port_id']);
        $steps[] = $this->step($offset + count($steps) + 1, $update, ['port_id' => $port['port_id'], 'changes' => $changes], sprintf('Update generated %s port %s.', $entry ? 'entry' : 'exit', $port['port_id']));
      }
      else {
        $steps[] = $this->step($offset + count($steps) + 1, $command, ['port' => $port], sprintf('Add generated %s port %s.', $entry ? 'entry' : 'exit', $port['port_id']));
      }
    }
    return $steps;
  }

  private function placements(array $decoded, array $catalog, array $hexes, int $seed): array {
    if (!is_array($decoded['placements'] ?? NULL) || !array_is_list($decoded['placements'])) {
      return [];
    }
    $catalog_set = [];
    foreach ($catalog as $definition) {
      $catalog_set[$definition['family'] . ':' . $definition['definition_id'] . ':' . $definition['version']] = TRUE;
    }
    $footprint = [];
    foreach ($hexes as $hex) {
      $footprint[RoomPortEdgePolicy::hexKey($hex)] = TRUE;
    }
    $placements = [];
    foreach ($decoded['placements'] as $index => $placement) {
      if (!is_array($placement) || !is_array($placement['anchor_hex'] ?? NULL)) {
        throw $this->nonconforming([$this->finding('placement_invalid', '/placements/' . $index, 'Placement must include anchor_hex.')]);
      }
      if (!is_int($placement['anchor_hex']['q'] ?? NULL) || !is_int($placement['anchor_hex']['r'] ?? NULL)) {
        throw $this->nonconforming([$this->finding('placement_invalid', '/placements/' . $index . '/anchor_hex', 'Placement anchor coordinates must be integer q/r.')]);
      }
      $family = (string) ($placement['family'] ?? '');
      $definition_id = (string) ($placement['definition_id'] ?? '');
      $version = (string) ($placement['version'] ?? '1.0.0');
      $key = $family . ':' . $definition_id . ':' . $version;
      if (!isset($catalog_set[$key]) || !$this->definitions->definitionExists($family, $definition_id, $version)) {
        throw $this->exception('generation_catalog_reference_unresolved', [$this->finding('generation_catalog_reference_unresolved', '/placements/' . $index . '/definition_id', sprintf('%s is not a canonical catalog reference.', $key))]);
      }
      $anchor = ['q' => $placement['anchor_hex']['q'], 'r' => $placement['anchor_hex']['r']];
      if (!isset($footprint[RoomPortEdgePolicy::hexKey($anchor)])) {
        throw $this->nonconforming([$this->finding('placement_outside_room', '/placements/' . $index . '/anchor_hex', 'Placement anchor must be inside generated footprint.')]);
      }
      $placements[] = [
        'instance_id' => $this->deterministicUuid($seed, 'room-placement', $index + 1),
        'definition_ref' => ['family' => $family, 'definition_id' => $definition_id, 'version' => $version],
        'anchor_hex' => $anchor,
        'facing' => isset($placement['facing']) && is_int($placement['facing']) ? max(0, min(5, $placement['facing'])) : 0,
        'elevation_ft' => isset($placement['elevation_ft']) && is_int($placement['elevation_ft']) ? max(-50, min(200, $placement['elevation_ft'])) : 0,
        'overrides' => is_array($placement['overrides'] ?? NULL) ? $placement['overrides'] : [],
      ];
    }
    return $placements;
  }

  private function selectDungeonRooms(array $decoded, array $library, array $input): array {
    $by_id = [];
    foreach ($library as $room) {
      if (($room['entry_port_count'] ?? 0) > 0 && ($room['exit_port_count'] ?? 0) > 0) {
        $by_id[$room['room_id']] = $room;
      }
    }
    if ($by_id === []) {
      throw $this->exception('generation_catalog_reference_unresolved', [$this->finding('generation_catalog_reference_unresolved', '/room_library', 'Published room library has no rooms with both entry and exit ports.')]);
    }
    $requested = is_array($decoded['rooms'] ?? NULL) && array_is_list($decoded['rooms']) ? $decoded['rooms'] : [];
    if (count($requested) < $input['room_count']) {
      throw $this->nonconforming([$this->finding('required_missing', '/rooms', sprintf('Generated dungeon must include at least %d room references.', $input['room_count']))]);
    }
    $selected = [];
    foreach ($requested as $index => $spec) {
      if (count($selected) >= $input['room_count']) {
        break;
      }
      if (!is_array($spec) || !isset($spec['room_id'])) {
        continue;
      }
      $room_id = (string) $spec['room_id'];
      if (!isset($by_id[$room_id])) {
        throw $this->exception('generation_catalog_reference_unresolved', [$this->finding('generation_catalog_reference_unresolved', '/rooms/' . $index . '/room_id', sprintf('%s is not a published room with entry/exit ports.', $room_id))]);
      }
      $selected[] = $by_id[$room_id] + ['role' => (string) ($spec['role'] ?? 'generated')];
    }
    if (count($selected) < $input['room_count']) {
      throw $this->nonconforming([$this->finding('required_missing', '/rooms', sprintf('Generated dungeon must include %d usable published room references.', $input['room_count']))]);
    }
    return $selected;
  }

  private function sealedPlacement(array $anchor, array $anchor_room, array $room, array $occupied, string $placement_id, array $input, int $ordinal): array {
    $exits = array_values(array_filter((array) ($anchor_room['ports'] ?? []), static fn(array $p): bool => ($p['kind'] ?? '') === 'exit'));
    $entries = array_values(array_filter((array) ($room['ports'] ?? []), static fn(array $p): bool => ($p['kind'] ?? '') === 'entry'));
    if ($exits === [] || $entries === []) {
      throw $this->exception('generation_catalog_reference_unresolved', [$this->finding('generation_catalog_reference_unresolved', '/selected_rooms/' . ($ordinal - 1), 'Selected rooms must expose entry and exit ports.')]);
    }
    usort($exits, fn(array $a, array $b): int => [$this->stableScore($input['seed'], $a['port_id']), $a['port_id']] <=> [$this->stableScore($input['seed'], $b['port_id']), $b['port_id']]);
    usort($entries, fn(array $a, array $b): int => [$this->stableScore($input['seed'], $a['port_id']), $a['port_id']] <=> [$this->stableScore($input['seed'], $b['port_id']), $b['port_id']]);

    foreach ($exits as $exit_port) {
      $exit = RoomPlacementTransformer::toLevelPort(['q' => (int) $exit_port['q'], 'r' => (int) $exit_port['r']], (int) $exit_port['edge'], $anchor);
      $target_hex = RoomPlacementTransformer::neighbor(['q' => $exit['q'], 'r' => $exit['r']], $exit['edge']);
      $target_edge = RoomPlacementTransformer::opposite($exit['edge']);
      foreach ($entries as $entry_port) {
        for ($rotation = 0; $rotation < RoomPlacementTransformer::EDGE_COUNT; $rotation++) {
          if (RoomPlacementTransformer::rotateEdge((int) $entry_port['edge'], $rotation) !== $target_edge) {
            continue;
          }
          $rotated = RoomPlacementTransformer::rotate((int) $entry_port['q'], (int) $entry_port['r'], $rotation);
          $placement = [
            'placement_id' => $placement_id,
            'room_id' => $room['room_id'],
            'version_id' => $room['version_id'],
            'origin' => ['q' => $target_hex['q'] - $rotated['q'], 'r' => $target_hex['r'] - $rotated['r']],
            'rotation_steps' => $rotation,
            'label' => (string) ($room['role'] ?? $room['name']),
            'is_level_entrance' => FALSE,
            'tags' => [],
          ];
          if ($this->placementFits($placement, $room, $occupied)) {
            return [$placement, [
              'from' => ['placement_id' => $anchor['placement_id'], 'port_id' => $exit_port['port_id']],
              'to' => ['placement_id' => $placement_id, 'port_id' => $entry_port['port_id']],
              'kind' => $input['link_kind'],
              'direction' => $input['link_direction'],
              'default_state' => $input['default_state'],
            ]];
          }
        }
      }
    }
    throw $this->nonconforming([$this->finding('no_sealed_placement', '/selected_rooms/' . ($ordinal - 1), 'No non-overlapping sealed placement was available for selected room.')]);
  }

  private function placementFits(array $placement, array $room, array $occupied): bool {
    foreach ((array) ($room['footprint'] ?? []) as $hex) {
      $level = RoomPlacementTransformer::toLevel($hex, $placement);
      if (abs($level['q']) > DungeonEditorService::AXIAL_BOUND || abs($level['r']) > DungeonEditorService::AXIAL_BOUND) {
        return FALSE;
      }
      if (isset($occupied[RoomPlacementTransformer::hexKey($level)])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  private function assertRoomSimulation(array $simulation): void {
    if (empty($simulation['applies_cleanly'])) {
      throw $this->nonconforming([$this->finding('command_rejected', '/command_plan/steps', json_encode($simulation['steps'] ?? [], JSON_UNESCAPED_SLASHES))]);
    }
    if (empty($simulation['validation']['valid'])) {
      $findings = array_merge((array) ($simulation['validation']['errors'] ?? []), (array) ($simulation['validation']['warnings'] ?? []));
      throw $this->nonconforming($findings);
    }
  }

  private function assertDungeonSimulation(array $simulation): void {
    if (($simulation['rejected'] ?? NULL) !== NULL) {
      throw $this->nonconforming((array) ($simulation['rejected']['findings'] ?? [$this->finding('command_rejected', '/command_plan/steps', (string) ($simulation['rejected']['code'] ?? 'Command rejected.'))]));
    }
    if (empty($simulation['validation']['is_valid'])) {
      throw $this->nonconforming((array) ($simulation['validation']['findings'] ?? []));
    }
  }

  private function assertGeneratedRoomPortsFollowPolicy(array $room): void {
    foreach (['entry_ports', 'exit_ports'] as $bucket) {
      foreach ((array) ($room[$bucket] ?? []) as $index => $port) {
        $target = RoomPortEdgePolicy::target($room, $port);
        if (($port['hex']['q'] ?? NULL) !== $target['hex']['q'] || ($port['hex']['r'] ?? NULL) !== $target['hex']['r'] || (int) ($port['edge'] ?? -1) !== $target['edge']) {
          throw $this->exception('generation_port_policy_violation', [$this->finding('generation_port_policy_violation', '/' . $bucket . '/' . $index, 'Port does not satisfy the Board port-edge policy.')]);
        }
      }
    }
  }

  private function valueIn(mixed $value, array $allowed, string $pointer): string {
    if (!is_string($value) || !in_array($value, $allowed, TRUE)) {
      throw $this->nonconforming([$this->finding('enum_violation', $pointer, 'Value is not in the allowed set.')]);
    }
    return $value;
  }

  private function requiredString(array $decoded, string $key, int $min, int $max): string {
    if (!is_string($decoded[$key] ?? NULL) || mb_strlen(trim($decoded[$key])) < $min || mb_strlen(trim($decoded[$key])) > $max) {
      throw $this->nonconforming([$this->finding('string_invalid', '/' . $key, sprintf('%s must be %d..%d characters.', $key, $min, $max))]);
    }
    return trim($decoded[$key]);
  }

  private function optionalString(array $decoded, string $key, int $max, string $default): string {
    if (!array_key_exists($key, $decoded) || $decoded[$key] === NULL || $decoded[$key] === '') {
      return $default;
    }
    if (!is_string($decoded[$key]) || mb_strlen($decoded[$key]) > $max) {
      throw $this->nonconforming([$this->finding('string_invalid', '/' . $key, sprintf('%s must be a string under %d characters.', $key, $max))]);
    }
    return trim($decoded[$key]);
  }

  private function commandsFromSteps(array $steps): array {
    return array_map(static fn(array $step): array => ['type' => $step['command_type'], 'payload' => $step['payload'], 'rationale' => $step['rationale']], $steps);
  }

  private function envelopesFromSteps(array $steps, int $base_revision, int $seed): array {
    $envelopes = [];
    foreach ($steps as $index => $step) {
      $envelopes[] = [
        'command_id' => $this->deterministicUuid($seed, 'dungeon-command', $index + 1),
        'expected_revision' => $base_revision + $index,
        'type' => $step['command_type'],
        'payload' => $step['payload'],
        'issued_at' => gmdate(DATE_RFC3339),
      ];
    }
    return $envelopes;
  }

  private function step(int $step, string $type, array $payload, string $rationale): array {
    return ['step' => $step, 'command_type' => $type, 'payload' => $payload, 'rationale' => $rationale];
  }

  private function stableScore(int $seed, string $value): string {
    return hash('sha256', $seed . ':' . $value);
  }

  private function deterministicUuid(int $seed, string $scope, int $index): string {
    $hex = substr(hash('sha256', $seed . ':' . $scope . ':' . $index), 0, 32);
    $hex[12] = '4';
    $variant = hexdec($hex[16]);
    $hex[16] = dechex(($variant & 0x3) | 0x8);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
  }

  private function nonconforming(array $findings): CanonicalGenerationException {
    return $this->exception('generation_nonconforming', $findings);
  }

  private function exception(string $code, array $findings, int $status = 422, ?\Throwable $previous = NULL): CanonicalGenerationException {
    return new CanonicalGenerationException($code, $findings, $status, $previous);
  }

  private function finding(string $code, string $pointer, string $message): array {
    return ['code' => $code, 'pointer' => $pointer, 'message' => $message, 'severity' => 'error'];
  }

}
