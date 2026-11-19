<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Geometry\RoomPlacementTransformer;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Plans link_ports steps between exit ports and entry ports.
 *
 * A link joins an exit port to an entry port on another placement; the two
 * rooms may sit anywhere on the level. With a target placement the tool
 * plans that one link. Without one it links every unlinked exit port that
 * already touches an entry port. Link kind, direction and default state are
 * the author's call and must be given explicitly; the tool never picks them.
 */
final class PlanPortLinksTool implements EditorGmToolInterface {

  private const KINDS = ['hallway', 'archway', 'door', 'hatch', 'portcullis', 'secret_door', 'magical_barrier', 'collapsed', 'bridge', 'one_way_drop'];
  private const DIRECTIONS = ['bidirectional', 'one_way'];
  private const STATES = ['open', 'closed', 'locked', 'barred', 'trapped', 'triggered', 'destroyed'];

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'plan_port_links',
      EditorGmToolDefinition::FAMILY_PLANNING,
      'Plan link_ports steps. To connect two rooms wherever they sit, give placement_id (the room with the exit) and to_placement_id (the room with the entry); the rooms do not need to touch. Without to_placement_id, links every unlinked exit port that already touches an entry port, optionally restricted to one placement or one exit port.',
      FALSE,
      'planning only; RoomPlacementTransformer geometry, execution requires apply_dungeon_commands',
      [
        EditorGmToolDefinition::argument('kind', 'string', TRUE, 'Link kind: ' . implode('|', self::KINDS) . '.'),
        EditorGmToolDefinition::argument('direction', 'string', TRUE, 'Link direction: ' . implode('|', self::DIRECTIONS) . '.'),
        EditorGmToolDefinition::argument('default_state', 'string', TRUE, 'Initial state: ' . implode('|', self::STATES) . '.'),
        EditorGmToolDefinition::argument('placement_id', 'string', FALSE, 'Placement whose exit port starts the link. Required with to_placement_id.'),
        EditorGmToolDefinition::argument('port_id', 'string', FALSE, 'Exit port on placement_id. Required when that placement has more than one unlinked exit port.'),
        EditorGmToolDefinition::argument('to_placement_id', 'string', FALSE, 'Placement whose entry port ends the link. May be anywhere on the level.'),
        EditorGmToolDefinition::argument('to_port_id', 'string', FALSE, 'Entry port on to_placement_id. Required when that placement has more than one entry port.'),
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
    $to_placement = isset($arguments['to_placement_id']) ? trim((string) $arguments['to_placement_id']) : '';
    $to_port = isset($arguments['to_port_id']) ? trim((string) $arguments['to_port_id']) : '';
    if ($to_port !== '' && $to_placement === '') {
      throw new \InvalidArgumentException('to_port_id_requires_to_placement_id');
    }
    if ($to_placement !== '' && $only_placement === '') {
      throw new \InvalidArgumentException('to_placement_id_requires_placement_id');
    }
    if ($to_placement !== '' && $to_placement === $only_placement) {
      throw new \InvalidArgumentException('link_requires_two_placements');
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

    if ($to_placement !== '') {
      DungeonPlanSteps::placement($model, $to_placement);
      $exit = $this->onlyPort(array_values(array_filter($exits, static fn(array $port): bool =>
        $port['placement_id'] === $only_placement
        && ($only_port === '' ? !isset($linked[$port['placement_id'] . ':' . $port['port_id']]) : $port['port_id'] === $only_port)
      )), 'exit', $only_placement, $only_port);
      if (isset($linked[$exit['placement_id'] . ':' . $exit['port_id']])) {
        throw new \DomainException(sprintf('port_already_linked:%s:%s', $exit['placement_id'], $exit['port_id']));
      }
      $entries = array_merge(...array_values($entries_by_key ?: [[]]));
      $entry = $this->onlyPort(array_values(array_filter($entries, static fn(array $port): bool =>
        $port['placement_id'] === $to_placement && ($to_port === '' || $port['port_id'] === $to_port)
      )), 'entry', $to_placement, $to_port);
      return [
        'link_count' => 1,
        'unmatched_exits' => [],
        'command_plan' => DungeonPlanSteps::plan($context, [$this->linkStep(1, $exit, $entry, $kind, $direction, $state)]),
      ];
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
      $steps[] = $this->linkStep(count($steps) + 1, $exit, $matches[0], $kind, $direction, $state);
    }

    if ($only_port !== '' && $steps === []) {
      throw new \DomainException(sprintf('no_touching_entry_for_exit:%s:%s', $only_placement, $only_port));
    }
    if ($steps === []) {
      throw new \DomainException('no_touching_port_pairs');
    }

    return [
      'link_count' => count($steps),
      'unmatched_exits' => $unmatched,
      'command_plan' => DungeonPlanSteps::plan($context, $steps),
    ];
  }

  /**
   * The single port the arguments name, or a hard failure naming the choices.
   */
  private function onlyPort(array $ports, string $kind, string $placement_id, string $port_id): array {
    if ($ports === []) {
      throw new \OutOfBoundsException($port_id !== ''
        ? sprintf('%s_port_not_found:%s:%s', $kind, $placement_id, $port_id)
        : sprintf('placement_has_no_free_%s_port:%s', $kind, $placement_id));
    }
    if (count($ports) > 1) {
      throw new \DomainException(sprintf('%s_port_ambiguous:%s:%s', $kind, $placement_id, implode(',', array_column($ports, 'port_id'))));
    }
    return $ports[0];
  }

  private function linkStep(int $number, array $exit, array $entry, string $kind, string $direction, string $state): array {
    return DungeonPlanSteps::step($number, 'link_ports', [
      'from' => ['placement_id' => $exit['placement_id'], 'port_id' => $exit['port_id']],
      'to' => ['placement_id' => $entry['placement_id'], 'port_id' => $entry['port_id']],
      'kind' => $kind,
      'direction' => $direction,
      'default_state' => $state,
    ], sprintf('Link exit "%s" on "%s" to entry "%s" on "%s" (%s, %s, %s).', $exit['port_id'], $exit['label'], $entry['port_id'], $entry['label'], $kind, $direction, $state));
  }

}
