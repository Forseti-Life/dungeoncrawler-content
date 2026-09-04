<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Plans link_ports steps for exit/entry pairs that are already sealed.
 *
 * Only geometry that the validator will accept is planned: an unlinked exit
 * port whose facing hex holds an entry port with the opposite edge. Link
 * kind, direction and default state are the author's call and must be given
 * explicitly; the tool never picks them.
 */
final class PlanPortLinksTool implements EditorGmToolInterface {

  private const KINDS = ['hallway', 'archway', 'door', 'hatch', 'portcullis', 'secret_door', 'magical_barrier', 'collapsed', 'bridge', 'one_way_drop'];
  private const DIRECTIONS = ['bidirectional', 'one_way'];
  private const STATES = ['open', 'closed', 'locked', 'barred', 'trapped', 'triggered', 'destroyed'];

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_port_links',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Find unlinked exit ports that already sit flush against an entry port and plan link_ports steps for them. Optionally restrict to one placement or one exit port.',
      FALSE,
      'planning only; RoomPlacementTransformer geometry, execution requires apply_dungeon_commands',
      [
        EditorGmToolDefinition::argument('kind', 'string', TRUE, 'Link kind: ' . implode('|', self::KINDS) . '.'),
        EditorGmToolDefinition::argument('direction', 'string', TRUE, 'Link direction: ' . implode('|', self::DIRECTIONS) . '.'),
        EditorGmToolDefinition::argument('default_state', 'string', TRUE, 'Initial state: ' . implode('|', self::STATES) . '.'),
        EditorGmToolDefinition::argument('placement_id', 'string', FALSE, 'Only consider exit ports on this placement.'),
        EditorGmToolDefinition::argument('port_id', 'string', FALSE, 'Only consider this exit port (requires placement_id).'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $kind = EditorGmToolContext::requireString($arguments, 'kind');
    $direction = EditorGmToolContext::requireString($arguments, 'direction');
    $state = EditorGmToolContext::requireString($arguments, 'default_state');
    if (!in_array($kind, self::KINDS, TRUE)) {
      throw new \InvalidArgumentException(sprintf('link_kind_invalid:%s', $kind));
    }
    if (!in_array($direction, self::DIRECTIONS, TRUE)) {
      throw new \InvalidArgumentException(sprintf('link_direction_invalid:%s', $direction));
    }
    if (!in_array($state, self::STATES, TRUE)) {
      throw new \InvalidArgumentException(sprintf('link_default_state_invalid:%s', $state));
    }
    $only_placement = isset($arguments['placement_id']) ? trim((string) $arguments['placement_id']) : '';
    $only_port = isset($arguments['port_id']) ? trim((string) $arguments['port_id']) : '';
    if ($only_port !== '' && $only_placement === '') {
      throw new \InvalidArgumentException('port_id_requires_placement_id');
    }

    $model = $context->model();
    if ($only_placement !== '') {
      DungeonPlanSteps::placement($model, $only_placement);
    }

    $linked = [];
    foreach ((array) ($model['port_links'] ?? []) as $link) {
      $linked[$link['from']['placement_id'] . ':' . $link['from']['port_id']] = TRUE;
    }

    $exits = [];
    $entries_by_key = [];
    foreach ((array) ($model['placements'] ?? []) as $placement) {
      if (!$placement['resolved']) {
        continue;
      }
      foreach ((array) $placement['room']['ports'] as $port) {
        $level = RoomPlacementTransformer::toLevelPort(['q' => $port['q'], 'r' => $port['r']], (int) $port['edge'], $placement);
        $record = [
          'placement_id' => $placement['placement_id'],
          'label' => $placement['label'],
          'port_id' => $port['port_id'],
          'q' => $level['q'],
          'r' => $level['r'],
          'edge' => $level['edge'],
        ];
        if ($port['kind'] === 'entry') {
          $entries_by_key[$level['q'] . ':' . $level['r'] . ':' . $level['edge']][] = $record;
        }
        else {
          $exits[] = $record;
        }
      }
    }

    $steps = [];
    $unmatched = [];
    foreach ($exits as $exit) {
      if ($only_placement !== '' && $exit['placement_id'] !== $only_placement) {
        continue;
      }
      if ($only_port !== '' && $exit['port_id'] !== $only_port) {
        continue;
      }
      if (isset($linked[$exit['placement_id'] . ':' . $exit['port_id']])) {
        continue;
      }
      $facing = RoomPlacementTransformer::neighbor(['q' => $exit['q'], 'r' => $exit['r']], $exit['edge']);
      $key = $facing['q'] . ':' . $facing['r'] . ':' . RoomPlacementTransformer::opposite($exit['edge']);
      $matches = array_values(array_filter(
        $entries_by_key[$key] ?? [],
        static fn(array $entry): bool => $entry['placement_id'] !== $exit['placement_id'],
      ));
      if ($matches === []) {
        $unmatched[] = ['placement_id' => $exit['placement_id'], 'port_id' => $exit['port_id'], 'level_hex' => ['q' => $exit['q'], 'r' => $exit['r']], 'level_edge' => $exit['edge']];
        continue;
      }
      if (count($matches) > 1) {
        throw new \DomainException(sprintf('entry_port_ambiguous:%s:%s', $exit['placement_id'], $exit['port_id']));
      }
      $entry = $matches[0];
      $steps[] = DungeonPlanSteps::step(count($steps) + 1, 'link_ports', [
        'from' => ['placement_id' => $exit['placement_id'], 'port_id' => $exit['port_id']],
        'to' => ['placement_id' => $entry['placement_id'], 'port_id' => $entry['port_id']],
        'kind' => $kind,
        'direction' => $direction,
        'default_state' => $state,
      ], sprintf('Link exit "%s" on "%s" to entry "%s" on "%s" (%s, %s, %s).', $exit['port_id'], $exit['label'], $entry['port_id'], $entry['label'], $kind, $direction, $state));
    }

    if ($only_port !== '' && $steps === []) {
      throw new \DomainException(sprintf('no_sealed_entry_for_exit:%s:%s', $only_placement, $only_port));
    }
    if ($steps === []) {
      throw new \DomainException('no_sealed_port_pairs');
    }

    return [
      'link_count' => count($steps),
      'unmatched_exits' => $unmatched,
      'command_plan' => DungeonPlanSteps::plan($context, $steps),
    ];
  }

}
