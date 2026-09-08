<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Generation;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;

/**
 * Builds canonical dungeon generation command plans for editor and runtime.
 *
 * This is the shared G1/R5 dungeon layout converter. It has no editor surface
 * dependencies; callers provide draft state, room library data, and the server
 * simulation callback that validates the projected aggregate.
 */
class CanonicalDungeonLayoutPlanService {

  private const PLAN_VERSION = 'editor-gm-command-plan-v1';
  private const GENERATION_PLAN_VERSION = 'editor-generation-plan-v1';
  private const DUNGEON_MAX_PLACEMENTS = 20;
  private const DUNGEON_MAX_LINKS = 40;
  private const DUNGEON_MAX_COMMANDS = 120;

  public function __construct(private readonly CanonicalGenerationService $generation) {}

  /**
   * Generate a validated dungeon command plan from canonical JSON output.
   *
   * @param callable $simulate
   *   Callable with signature function(array $commands, string $profile): array.
   */
  public function generateDungeonLayoutPlan(array $arguments, array $draft, array $dungeon, array $library, callable $simulate): array {
    $input = $this->normalizeDungeonInput($arguments);
    if ($library === []) {
      throw $this->exception('generation_catalog_reference_unresolved', [$this->finding('generation_catalog_reference_unresolved', '/room_library', 'No published room versions are available for dungeon generation.')]);
    }
    $operation = (string) ($arguments['operation'] ?? 'canonical_generation_dungeon_layout');

    return $this->generation->completeJson('generate_dungeon_layout', $operation, $input['seed'], 3000,
      function (array $prior_findings) use ($input, $draft, $dungeon, $library): string {
        return $this->dungeonPrompt($input, $draft, $dungeon, $library, $prior_findings);
      },
      function (array $decoded, array $provenance) use ($draft, $input, $library, $simulate): array {
        return $this->dungeonPlanFromDecoded($decoded, $provenance, $draft, $input, $library, $simulate);
      }
    );
  }

  /**
   * Convert command-plan steps into revision-checked command envelopes.
   */
  public function envelopesFromSteps(array $steps, int $base_revision, int $seed): array {
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
      'publication_ready' => !empty($arguments['publication_ready']),
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
      $code = $max === self::DUNGEON_MAX_PLACEMENTS ? 'generation_size_limit_exceeded' : 'generation_input_invalid';
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

  private function dungeonPrompt(array $input, array $draft, array $dungeon, array $library, array $prior_findings): string {
    $publication_ready = !empty($input['publication_ready']);
    $nonterminal_library = array_values(array_filter($library, static fn(array $room): bool => ($room['entry_port_count'] ?? 0) > 0 && (!$publication_ready || (int) ($room['exit_port_count'] ?? 0) === 1) && ($room['exit_port_count'] ?? 0) > 0));
    $terminal_library = array_values(array_filter($library, static fn(array $room): bool => ($room['entry_port_count'] ?? 0) > 0 && ($room['exit_port_count'] ?? 0) === 0));
    $prompt_library = $publication_ready
      ? array_merge(array_slice($nonterminal_library, 0, 15), array_slice($terminal_library, 0, 5))
      : array_slice($nonterminal_library, 0, 20);
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
    ], $prompt_library);
    $allowed_room_ids = array_values(array_map(static fn(array $room): string => (string) $room['room_id'], $rooms));
    $shape = [
      'name' => 'string 1..200',
      'description' => 'string <=8000',
      'theme' => 'string <=100',
      'rooms' => [['room_id' => 'published-room-id', 'role' => 'entrance|connector|goal']],
    ];
    return $this->promptText('canonical_dungeon_runtime', $input, $draft, [
      'current_dungeon' => [
        'dungeon_id' => $dungeon['dungeon_id'] ?? '',
        'name' => $dungeon['name'] ?? '',
        'revision' => $draft['revision'] ?? 0,
        'placement_count' => count((array) ($dungeon['room_placements'] ?? [])),
      ],
      'published_room_library' => $rooms,
      'nonterminal_room_ids' => array_values(array_map(static fn(array $room): string => (string) $room['room_id'], array_slice($nonterminal_library, 0, 20))),
      'terminal_room_ids' => array_values(array_map(static fn(array $room): string => (string) $room['room_id'], array_slice($terminal_library, 0, 20))),
      'allowed_room_ids' => $allowed_room_ids,
      'required_output_shape' => $shape,
      'requirements' => [
        'Return one JSON object only. No prose.',
        'Return exactly ' . $input['room_count'] . ' room references and use only exact room_id values from allowed_room_ids.',
        $publication_ready ? 'Use nonterminal_room_ids for every room except the final room; use terminal_room_ids for the final goal room so no exit port is left dangling at publication.' : 'Use nonterminal_room_ids for every generated room.',
        'Do not invent room_id values and do not use room names as room_id values.',
        $publication_ready ? 'Every nonterminal listed room has exactly one exit port; every terminal listed room has no exit ports.' : 'Every listed room is a published version with both entry and exit ports; no other rooms are eligible.',
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

  private function dungeonPlanFromDecoded(array $decoded, array $provenance, array $draft, array $input, array $library, callable $simulate): array {
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

    $simulation = $simulate($this->envelopesFromSteps($steps, (int) ($draft['revision'] ?? 0), $input['seed']), 'editing');
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
        'draft_id' => $draft['draft_id'],
        'base_revision' => (int) ($draft['revision'] ?? 0),
        'steps' => $steps,
      ],
      'preview_summary' => [
        'placement_count' => count((array) ($simulation['dungeon']['room_placements'] ?? [])),
        'port_link_count' => count((array) ($simulation['dungeon']['port_links'] ?? [])),
      ],
    ];
  }

  private function selectDungeonRooms(array $decoded, array $library, array $input): array {
    $by_id = [];
    foreach ($library as $room) {
      if (($room['entry_port_count'] ?? 0) > 0 && ($room['exit_port_count'] ?? 0) > 0) {
        $by_id[$room['room_id']] = $room;
      }
      elseif (!empty($input['publication_ready']) && ($room['entry_port_count'] ?? 0) > 0 && (int) ($room['exit_port_count'] ?? 0) === 0) {
        $by_id[$room['room_id']] = $room;
      }
    }
    if ($by_id === []) {
      throw $this->exception('generation_catalog_reference_unresolved', [$this->finding('generation_catalog_reference_unresolved', '/room_library', 'Published room library has no rooms with eligible terminal/nonterminal ports.')]);
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
        throw $this->nonconforming([$this->finding('generation_catalog_reference_unresolved', '/rooms/' . $index . '/room_id', sprintf('%s is not an eligible published room id.', $room_id))]);
      }
      $room = $by_id[$room_id];
      $is_terminal = count($selected) === $input['room_count'] - 1;
      $exit_count = (int) ($room['exit_port_count'] ?? 0);
      if (!empty($input['publication_ready']) && !$is_terminal && $exit_count !== 1) {
        throw $this->nonconforming([$this->finding('generation_catalog_reference_unresolved', '/rooms/' . $index . '/room_id', sprintf('%s cannot be used before the terminal position because it does not expose exactly one exit port.', $room_id))]);
      }
      if (!empty($input['publication_ready']) && $is_terminal && $exit_count !== 0) {
        throw $this->nonconforming([$this->finding('generation_catalog_reference_unresolved', '/rooms/' . $index . '/room_id', sprintf('%s cannot be used as the terminal room because it has dangling exit ports.', $room_id))]);
      }
      $selected[] = $room + ['role' => (string) ($spec['role'] ?? ($is_terminal ? 'goal' : 'generated'))];
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

  private function assertDungeonSimulation(array $simulation): void {
    if (($simulation['rejected'] ?? NULL) !== NULL) {
      throw $this->nonconforming((array) ($simulation['rejected']['findings'] ?? [$this->finding('command_rejected', '/command_plan/steps', (string) ($simulation['rejected']['code'] ?? 'Command rejected.'))]));
    }
    if (empty($simulation['validation']['is_valid'])) {
      throw $this->nonconforming((array) ($simulation['validation']['findings'] ?? []));
    }
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
