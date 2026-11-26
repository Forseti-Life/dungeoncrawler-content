<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Geometry\HexShapes;
use Drupal\dungeoncrawler_content\Geometry\PlacementRegion;
use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\RoomEditorGmToolContext;

/**
 * Planning tools that place obstacles or items in a shape.
 *
 * Each computes hexes with HexShapes and returns a command plan of
 * place_objects_bulk steps; apply_room_commands runs it. A hex that cannot
 * take an object does not fail the plan: the command records a placement
 * gap for it. Output is repeatable: scatter, weighted choice and random
 * facing all use the seed.
 */
final class PlaceShapeTool implements EditorGmToolInterface {

  public const NAMES = ['place_line', 'place_ring', 'place_fill', 'place_scatter', 'place_grid', 'place_along_edge'];

  public function __construct(private readonly string $name) {
    if (!in_array($name, self::NAMES, TRUE)) {
      throw new \LogicException('place_shape_tool_invalid:' . $name);
    }
  }

  public function definition(): EditorGmToolDefinition {
    $shape = match ($this->name) {
      'place_line' => [
        EditorGmToolDefinition::argument('from_hex', 'array', TRUE, '{q, r} start of the line.'),
        EditorGmToolDefinition::argument('to_hex', 'array', TRUE, '{q, r} end of the line, included.'),
        EditorGmToolDefinition::argument('every', 'integer', FALSE, 'Keep every Nth hex (default 1: a solid line).'),
      ],
      'place_ring' => [
        EditorGmToolDefinition::argument('center', 'array', FALSE, '{q, r} centre of a ring.'),
        EditorGmToolDefinition::argument('radius', 'integer', FALSE, 'Ring radius in hexes, with center.'),
        EditorGmToolDefinition::argument('around', 'array', FALSE, 'Instead of center/radius: a region {kind: room|disk|hexes|terrain, center, radius, hexes, terrain_type} to ring from outside.'),
        EditorGmToolDefinition::argument('thickness', 'integer', FALSE, 'Rows of hexes around a region (default 1).'),
        EditorGmToolDefinition::argument('every', 'integer', FALSE, 'Keep every Nth hex (default 1).'),
      ],
      'place_fill' => [
        EditorGmToolDefinition::argument('region', 'array', TRUE, 'Where: {kind: room|disk|hexes|terrain, center, radius, hexes, terrain_type}. Only hexes of the room are used.'),
        EditorGmToolDefinition::argument('every', 'integer', FALSE, 'Keep every Nth hex (default 1).'),
      ],
      'place_scatter' => [
        EditorGmToolDefinition::argument('region', 'array', TRUE, 'Where: {kind: room|disk|hexes|terrain, center, radius, hexes, terrain_type}.'),
        EditorGmToolDefinition::argument('count', 'integer', TRUE, 'How many objects.'),
        EditorGmToolDefinition::argument('min_spacing', 'integer', FALSE, 'Minimum distance in hexes between two (default 1).'),
      ],
      'place_grid' => [
        EditorGmToolDefinition::argument('origin', 'array', TRUE, '{q, r} first object.'),
        EditorGmToolDefinition::argument('columns', 'integer', TRUE, 'Objects along q.'),
        EditorGmToolDefinition::argument('rows', 'integer', TRUE, 'Objects along r.'),
        EditorGmToolDefinition::argument('spacing_q', 'integer', FALSE, 'Hexes between columns (default 2).'),
        EditorGmToolDefinition::argument('spacing_r', 'integer', FALSE, 'Hexes between rows (default 2).'),
      ],
      'place_along_edge' => [
        EditorGmToolDefinition::argument('every', 'integer', FALSE, 'Keep every Nth edge hex (default 1: a wall).'),
        EditorGmToolDefinition::argument('inset', 'integer', FALSE, 'Rows inside the edge (default 0: the edge hexes).'),
      ],
    };
    $summary = match ($this->name) {
      'place_line' => 'Place obstacles or items along a straight line of hexes.',
      'place_ring' => 'Place obstacles or items in a ring around a point, or around a region (fence a clearing).',
      'place_fill' => 'Place obstacles or items on every hex of a region.',
      'place_scatter' => 'Scatter a number of obstacles or items over a region; seeded and repeatable, with minimum spacing and weighted choices.',
      'place_grid' => 'Place obstacles or items in rows and columns.',
      'place_along_edge' => 'Place obstacles or items along the inside of the room edge.',
    };
    return new EditorGmToolDefinition(
      $this->name,
      EditorGmToolDefinition::FAMILY_PLANNING,
      $summary . ' Obstacles and items only. A blocked hex is skipped and listed as a placement gap. Returns a command plan; run it with apply_room_commands.',
      FALSE,
      'planning only; execution requires apply_room_commands',
      array_merge([
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'obstacle or item. Give family and definition_id, or choices.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Library definition to place.'),
        EditorGmToolDefinition::argument('version', 'string', FALSE, 'Definition version (default 1.0.0).'),
        EditorGmToolDefinition::argument('choices', 'array', FALSE, '[{family, definition_id, version, weight}] chosen by weight per hex, instead of one definition.'),
        EditorGmToolDefinition::argument('facing', 'string', FALSE, '0-5, or random, outward, inward, follow (along the line). Default 0.'),
        EditorGmToolDefinition::argument('seed', 'integer', FALSE, 'Seed for scatter and random choices; the same seed gives the same result (default 1).'),
        EditorGmToolDefinition::argument('keep_clear', 'array', FALSE, '[{q, r}] hexes to leave empty, such as corridor hexes. Ports and the hex outside each are kept clear unless keep_ports_clear is false.'),
        EditorGmToolDefinition::argument('keep_ports_clear', 'boolean', FALSE, 'Default true.'),
      ], $shape),
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = RoomEditorGmToolContext::of($context);
    $room = $context->room();
    $seed = isset($arguments['seed']) ? (int) $arguments['seed'] : 1;
    $every = isset($arguments['every']) ? (int) $arguments['every'] : 1;
    $follow = NULL;
    $centre = NULL;

    switch ($this->name) {
      case 'place_line':
        $from = $this->hex($arguments['from_hex'] ?? NULL, 'from_hex');
        $to = $this->hex($arguments['to_hex'] ?? NULL, 'to_hex');
        $hexes = HexShapes::every(HexShapes::line($from, $to), $every);
        $follow = [$from, $to];
        break;

      case 'place_ring':
        if (isset($arguments['around'])) {
          $region = PlacementRegion::hexes($arguments['around'], $room);
          $hexes = HexShapes::around($region, isset($arguments['thickness']) ? (int) $arguments['thickness'] : 1);
          $centre = PlacementRegion::centre($region);
        }
        elseif (isset($arguments['center'], $arguments['radius'])) {
          $centre = $this->hex($arguments['center'], 'center');
          $hexes = HexShapes::ring($centre, (int) $arguments['radius']);
        }
        else {
          throw new \InvalidArgumentException('place_ring_needs_center_and_radius_or_around');
        }
        $hexes = HexShapes::every($hexes, $every);
        break;

      case 'place_fill':
        $hexes = HexShapes::every(PlacementRegion::hexes($arguments['region'] ?? NULL, $room), $every);
        break;

      case 'place_scatter':
        $hexes = HexShapes::scatter(
          PlacementRegion::hexes($arguments['region'] ?? NULL, $room),
          EditorGmToolContext::requireInt($arguments, 'count'),
          $seed,
          isset($arguments['min_spacing']) ? (int) $arguments['min_spacing'] : 1
        );
        break;

      case 'place_grid':
        $hexes = HexShapes::grid(
          $this->hex($arguments['origin'] ?? NULL, 'origin'),
          EditorGmToolContext::requireInt($arguments, 'columns'),
          EditorGmToolContext::requireInt($arguments, 'rows'),
          isset($arguments['spacing_q']) ? (int) $arguments['spacing_q'] : 2,
          isset($arguments['spacing_r']) ? (int) $arguments['spacing_r'] : 2
        );
        break;

      case 'place_along_edge':
        $all = PlacementRegion::roomHexes($room);
        $layer = $all;
        for ($i = 0, $inset = (int) ($arguments['inset'] ?? 0); $i < $inset; $i++) {
          $edge_keys = array_flip(array_map([HexShapes::class, 'key'], HexShapes::boundary($layer)));
          $layer = array_values(array_filter($layer, static fn(array $h): bool => !isset($edge_keys[HexShapes::key($h)])));
        }
        $edge = $layer === [] ? [] : HexShapes::boundary($layer);
        // A stable order makes "every Nth" repeatable.
        usort($edge, static fn(array $a, array $b): int => [$a['r'], $a['q']] <=> [$b['r'], $b['q']]);
        $hexes = HexShapes::every($edge, $every);
        $centre = PlacementRegion::centre($all);
        break;
    }

    $choices = $this->choices($arguments);
    $items = [];
    foreach ($hexes as $index => $hex) {
      $items[] = [
        'definition_ref' => $this->pick($choices, $seed, $index),
        'anchor_hex' => ['q' => $hex['q'], 'r' => $hex['r']],
        'facing' => $this->facing($arguments['facing'] ?? 0, $hex, $centre, $follow, $seed, $index),
      ];
    }
    $steps = PlanRoomCommandsTool::bulkPlaceSteps($items, $this->name, array_intersect_key($arguments, ['keep_clear' => 1, 'keep_ports_clear' => 1]));

    $draft = $context->draft();
    return [
      'goal' => $this->name,
      'hex_count' => count($items),
      'seed' => $seed,
      'command_plan' => [
        'schema_version' => 'editor-gm-command-plan-v1',
        'draft_id' => $context->draftId,
        'base_revision' => (int) ($draft['revision'] ?? 0),
        'steps' => $steps,
      ],
    ];
  }

  private function hex(mixed $value, string $name): array {
    if (!is_array($value) || !is_int($value['q'] ?? NULL) || !is_int($value['r'] ?? NULL)) {
      throw new \InvalidArgumentException($name . '_invalid');
    }
    return ['q' => $value['q'], 'r' => $value['r']];
  }

  /**
   * @return list<array{definition_ref: array, weight: int}>
   */
  private function choices(array $arguments): array {
    $raw = $arguments['choices'] ?? NULL;
    if ($raw === NULL) {
      $raw = [[
        'family' => EditorGmToolContext::requireString($arguments, 'family'),
        'definition_id' => EditorGmToolContext::requireString($arguments, 'definition_id'),
        'version' => $arguments['version'] ?? '1.0.0',
        'weight' => 1,
      ]];
    }
    if (!is_array($raw) || $raw === [] || !array_is_list($raw)) {
      throw new \InvalidArgumentException('choices_invalid');
    }
    $choices = [];
    foreach ($raw as $choice) {
      if (!is_array($choice) || !isset($choice['family'], $choice['definition_id']) || (int) ($choice['weight'] ?? 1) < 1) {
        throw new \InvalidArgumentException('choices_invalid');
      }
      $choices[] = [
        'definition_ref' => [
          'family' => (string) $choice['family'],
          'definition_id' => (string) $choice['definition_id'],
          'version' => (string) ($choice['version'] ?? '1.0.0'),
        ],
        'weight' => (int) ($choice['weight'] ?? 1),
      ];
    }
    return $choices;
  }

  private function pick(array $choices, int $seed, int $index): array {
    if (count($choices) === 1) {
      return $choices[0]['definition_ref'];
    }
    $roll = crc32($seed . ':pick:' . $index) % array_sum(array_column($choices, 'weight'));
    foreach ($choices as $choice) {
      $roll -= $choice['weight'];
      if ($roll < 0) {
        return $choice['definition_ref'];
      }
    }
    return $choices[0]['definition_ref'];
  }

  private function facing(mixed $mode, array $hex, ?array $centre, ?array $follow, int $seed, int $index): int {
    if (is_int($mode) || (is_string($mode) && ctype_digit($mode))) {
      return max(0, min(5, (int) $mode));
    }
    return match ($mode) {
      'random' => crc32($seed . ':face:' . $index) % 6,
      'outward' => $centre === NULL ? throw new \InvalidArgumentException('facing_needs_a_centre') : self::edgeToward($centre, $hex),
      'inward' => $centre === NULL ? throw new \InvalidArgumentException('facing_needs_a_centre') : self::edgeToward($hex, $centre),
      'follow' => $follow === NULL ? throw new \InvalidArgumentException('facing_follow_needs_a_line') : self::edgeToward($follow[0], $follow[1]),
      default => throw new \InvalidArgumentException('facing_invalid'),
    };
  }

  /**
   * The edge index that points most nearly from `$from` toward `$to`.
   */
  public static function edgeToward(array $from, array $to): int {
    $xy = static fn(array $h): array => [sqrt(3) * ($h['q'] + $h['r'] / 2), 1.5 * $h['r']];
    [$fx, $fy] = $xy($from);
    [$tx, $ty] = $xy($to);
    $best = 0;
    $best_dot = -INF;
    foreach (RoomPlacementTransformer::EDGE_DIRECTIONS as $edge => $direction) {
      [$dx, $dy] = $xy($direction);
      $dot = ($tx - $fx) * $dx + ($ty - $fy) * $dy;
      if ($dot > $best_dot + 1e-9) {
        $best_dot = $dot;
        $best = $edge;
      }
    }
    return $best;
  }

}
