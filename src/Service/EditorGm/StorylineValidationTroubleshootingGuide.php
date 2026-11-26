<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Converts one existing validation finding into a scoped GM work packet.
 */
final class StorylineValidationTroubleshootingGuide {

  private static ?array $registry = NULL;

  /**
   * Builds a packet for the current open warning only.
   */
  public static function packet(array $payload, array $validation, array $status): array {
    $selected_message = trim((string) ($status['first_open_warning'] ?? ''));
    [$stage, $finding] = self::selectedFinding($validation, $selected_message);
    if ($stage === []) {
      return [];
    }
    $registry = self::registry();
    $registered_finding = isset($registry['findings'][$finding['code'] ?? '']);
    $entry = self::entry($finding, $stage);
    $route = (string) ($entry['route'] ?? $stage['route'] ?? 'unclassified');
    $route_owner = self::registry()['routes'][$route] ?? [];
    $packet = [
      'finding' => $finding === [] ? [
        'message' => $selected_message,
        'severity' => 'warning',
      ] : $finding,
      'stage' => (string) ($stage['stage'] ?? ''),
      'route' => $route,
      'owner' => (string) ($route_owner['owner'] ?? self::ownerForRoute($route)),
      'tool' => (string) ($route_owner['tool'] ?? ''),
      'resolver' => (string) ($entry['resolver'] ?? 'generic'),
      'verification' => (string) ($entry['verification'] ?? 'selected_code'),
      'code_change_required' => !$registered_finding && ($entry['resolver'] ?? 'generic') === 'generic',
      'coding_handoff' => !$registered_finding && ($entry['resolver'] ?? 'generic') === 'generic'
        ? [
          'required' => TRUE,
          'reason' => 'No object-specific troubleshooting resolver is registered for this finding.',
          'scope' => 'Add a registry entry and, only if necessary, one resolver. Do not alter storyline data in this turn.',
          'defect_tool' => 'submit_backlog_suggestion',
          'user_action' => 'Explain the impasse and route it to a coding session; do not ask the author for workaround data.',
        ]
        : [],
      'scope_rule' => 'Resolve this finding and other findings on the same owning row only. Do not discuss or edit unrelated warnings.',
    ];
    if (($entry['resolver'] ?? 'generic') === 'storyline_quest_objective'
      && ($finding['entity_type'] ?? '') === 'storyline_quest_objective') {
      $packet += self::objectivePacket(
        $payload,
        $validation,
        $finding,
        $stage,
        array_values((array) ($entry['fields'] ?? [])),
      );
    }
    if (($entry['resolver'] ?? 'generic') === 'editor_route') {
      $route_finding = $finding;
      $prerequisite = [];
      if (($finding['code'] ?? '') === 'DCV_AUTHORED_ROOM_NOT_IN_DUNGEON') {
        $missing_room = self::findRelatedFinding(
          $validation,
          'DCV_CANONICAL_ROOM_MISSING',
          (string) ($finding['room_id'] ?? ''),
        );
        if ($missing_room !== []) {
          $route_finding = $missing_room;
          $prerequisite = [
            'selected_error' => $finding['message'],
            'reason' => 'The room must exist canonically before the Dungeon Editor can place it.',
          ];
        }
      }
      $packet['object'] = [
        'type' => (string) ($route_finding['entity_type'] ?? 'object'),
        'id' => (string) ($route_finding['entity_id'] ?? ''),
      ];
      $packet['prerequisite'] = $prerequisite;
      $packet['editor_route'] = [
        'tool' => 'route_to_editor',
        'arguments' => [
          'kind' => (string) ($route_finding['entity_type'] ?? ''),
          'id' => (string) ($route_finding['entity_id'] ?? ''),
        ],
      ];
      $packet['user_action'] = 'Open the owning editor and have its GM create or update this canonical object, then return to revalidate.';
    }
    if (($entry['resolver'] ?? 'generic') === 'storyline_actor_materialization'
      && ($finding['entity_type'] ?? '') === 'storyline_actor_materialization') {
      $packet += self::actorMaterializationPacket($payload, $finding, $entry);
    }
    if (($entry['resolver'] ?? 'generic') === 'storyline_actor_home') {
      $packet += [
        'object' => [
          'type' => 'storyline_actor_home',
          'actors' => array_values((array) ($finding['actors'] ?? [])),
        ],
        'editable' => [
          'path' => '/objects/actors/generation_specs',
          'fields' => ['affiliations'],
          'tool' => 'plan_storyline_draft_operations',
        ],
        'evidence' => [
          'available_institutions' => array_values((array) ($payload['objects']['institutions'] ?? [])),
        ],
        'user_question' => 'Which named actor, and which place or kin institution, should be linked as that actor’s home?',
        'verification_codes' => ['actor_home_missing'],
      ];
    }
    return $packet;
  }

  private static function actorMaterializationPacket(array $payload, array $finding, array $entry): array {
    $actor_id = (string) ($finding['entity_id'] ?? '');
    $actor = NULL;
    foreach ((array) ($payload['objects']['actors']['generation_specs'] ?? []) as $row) {
      if (is_array($row) && ($row['actor_id'] ?? '') === $actor_id) {
        $actor = $row;
        break;
      }
    }
    if ($actor === NULL) {
      return [
        'code_change_required' => TRUE,
        'coding_handoff' => [
          'required' => TRUE,
          'reason' => 'The runtime materialization warning names an actor not present in the authored actor rows.',
          'scope' => 'Expose a safe actor projection resolver for this finding.',
          'defect_tool' => 'submit_backlog_suggestion',
        ],
      ];
    }
    $fields = array_values((array) ($entry['fields'] ?? []));
    $location_id = trim((string) ($actor['location_id'] ?? ''));
    $derived_only = $fields === [];
    return [
      'object' => [
        'type' => 'storyline_actor',
        'id' => $actor_id,
        'current' => $actor,
      ],
      'editable' => $derived_only ? [] : [
        'path' => '/objects/actors/generation_specs',
        'match' => ['field' => 'actor_id', 'value' => $actor_id],
        'fields' => $fields,
        'tool' => 'plan_storyline_draft_operations',
      ],
      'derived_contract' => [
        'entity_type' => 'npc_template',
        'required_before_activation' => TRUE,
        'registry_entity_type' => 'campaign_npc',
        'canonical_actor_id' => $actor_id,
        'canonical_location_id' => $location_id,
      ],
      'system_resolves' => $derived_only,
      'suggested_values' => [],
      'user_question' => $location_id === '' && in_array('location_id', $fields, TRUE)
        ? 'Which storyline location is this actor materialized in?'
        : NULL,
      'verification_codes' => [(string) ($finding['code'] ?? '')],
      'code_change_required' => FALSE,
      'coding_handoff' => [],
    ];
  }

  private static function findRelatedFinding(array $validation, string $code, string $entity_id): array {
    foreach ((array) ($validation['stages'] ?? []) as $stage) {
      foreach ((array) ($stage['findings'] ?? []) as $finding) {
        if (is_array($finding)
          && ($finding['code'] ?? '') === $code
          && ($finding['entity_id'] ?? '') === $entity_id) {
          return $finding + ['route' => (string) ($stage['route'] ?? '')];
        }
      }
    }
    return [];
  }

  private static function selectedFinding(array $validation, string $message): array {
    foreach ((array) ($validation['stages'] ?? []) as $stage) {
      if (!is_array($stage) || (array) ($stage['errors'] ?? []) === []) {
        continue;
      }
      $errors = (array) $stage['errors'];
      $selected = (string) reset($errors);
      foreach ((array) ($stage['findings'] ?? []) as $finding) {
        if (is_array($finding) && ($finding['message'] ?? '') === $selected) {
          return [$stage, $finding];
        }
      }
      return [$stage, []];
    }
    foreach ((array) ($validation['stages'] ?? []) as $stage) {
      if (!is_array($stage)) {
        continue;
      }
      $warnings = (array) ($stage['warnings'] ?? []);
      if ($message !== '' && in_array($message, $warnings, TRUE)) {
        foreach ((array) ($stage['findings'] ?? []) as $finding) {
          if (is_array($finding) && ($finding['message'] ?? '') === $message) {
            return [$stage, $finding];
          }
        }
        return [$stage, []];
      }
    }
    foreach ((array) ($validation['stages'] ?? []) as $stage) {
      if (is_array($stage) && (array) ($stage['warnings'] ?? []) !== []) {
        return [$stage, []];
      }
    }
    return [[], []];
  }

  private static function objectivePacket(array $payload, array $validation, array $finding, array $stage, array $configured_fields): array {
    $quest_id = (string) ($finding['entity_id'] ?? '');
    $rows = array_values(array_filter(
      (array) ($payload['process_flow']['objective_completion_criteria'] ?? []),
      static fn(mixed $row): bool => is_array($row) && ($row['task_id'] ?? '') === $quest_id,
    ));
    $index = (int) ($finding['objective_index'] ?? -1);
    $objective = $rows[$index] ?? [];
    $actors = array_values(array_filter((array) ($payload['objects']['actors']['generation_specs'] ?? []), 'is_array'));
    $actor_map = [];
    foreach ($actors as $actor) {
      $actor_map[(string) ($actor['actor_id'] ?? '')] = $actor;
      $actor_map[(string) ($actor['contact_id'] ?? '')] = $actor;
    }
    $target = $actor_map[(string) ($objective['target'] ?? '')] ?? [];
    $suggested = [];
    $display_name = trim((string) ($target['display_name'] ?? ''));
    $fields = [];
    if ($display_name !== '') {
      $suggested['target_label'] = $display_name;
      $aliases = array_values(array_unique(array_filter([
        str_contains($display_name, ' ') ? explode(' ', $display_name)[0] : $display_name,
      ])));
      if ($aliases !== []) {
        $suggested['target_aliases'] = $aliases;
      }
    }
    foreach ((array) ($stage['findings'] ?? []) as $related) {
      if (($related['entity_id'] ?? '') === $quest_id
        && ($related['objective_index'] ?? NULL) === $index
        && ($related['field'] ?? NULL) !== NULL) {
        $fields[] = $related['field'];
      }
    }
    if ($fields === []) {
      $fields = array_filter([(string) ($finding['field'] ?? '')]);
    }
    $fields = array_values(array_intersect(array_unique($fields), $configured_fields));
    $replacement_value = [];
    if (($finding['code'] ?? '') === 'DCV_OBJECTIVE_TYPE_FIELD_CONFLICT' && $objective !== []) {
      $replacement_value = $objective;
      foreach ($fields as $field) {
        unset($replacement_value[$field]);
      }
    }
    $needs_input = $objective === [] || (
      in_array('target_label', $fields, TRUE) && !isset($suggested['target_label'])
    ) || in_array('target_label', $fields, TRUE) && $display_name === '';
    $question = $needs_input
      ? 'Which exact player-facing target label should this objective use?'
      : NULL;
    return [
      'object' => [
        'type' => 'storyline_objective',
        'id' => (string) ($objective['objective_id'] ?? ''),
        'quest_id' => $quest_id,
        'phase_index' => (int) ($finding['phase_index'] ?? 0),
        'objective_index' => $index,
        'current' => $objective,
      ],
      'evidence' => [
        'task' => self::taskContext($payload, $quest_id),
        'target_actor' => $target === [] ? [] : array_intersect_key($target, array_flip([
          'actor_id', 'contact_id', 'display_name', 'role', 'chapter_id', 'scene_id', 'location_id',
        ])),
      ],
      'editable' => [
        'path' => '/process_flow/objective_completion_criteria',
        'match' => ['field' => 'objective_id', 'value' => (string) ($objective['objective_id'] ?? '')],
        'fields' => array_values(array_unique($fields)),
        'tool' => 'plan_storyline_draft_operations',
      ],
      'suggested_values' => $suggested,
      'replacement_value' => $replacement_value,
      'user_question' => $question,
      'verification_codes' => array_values(array_unique(array_map(
        static fn(array $item): string => (string) ($item['code'] ?? ''),
        array_filter(
          (array) ($stage['findings'] ?? []),
          static fn(mixed $item): bool => is_array($item)
            && ($item['entity_id'] ?? '') === $quest_id
            && ($item['objective_index'] ?? -1) === $index,
        ),
      ))),
    ];
  }

  private static function taskContext(array $payload, string $task_id): array {
    foreach ((array) ($payload['process_flow']['tasks'] ?? []) as $task) {
      if (is_array($task) && ($task['task_id'] ?? '') === $task_id) {
        return array_intersect_key($task, array_flip(['task_id', 'chapter_id', 'scene_id', 'goal_text', 'status']));
      }
    }
    return [];
  }

  private static function ownerForRoute(string $route): string {
    return match ($route) {
      'draft_operation' => 'StorylineAuthoringDraftService',
      'canonical_definition' => 'CanonicalDefinitionService',
      'actor_sheet_build' => 'StorylineActorSheetService',
      'dungeon_editor' => 'DungeonEditorGmService',
      'room_editor' => 'RoomEditorGmService',
      default => 'unresolved_validation_owner',
    };
  }

  private static function entry(array $finding, array $stage): array {
    $registry = self::registry();
    $finding_entry = is_array($registry['findings'][$finding['code'] ?? ''] ?? NULL)
      ? $registry['findings'][$finding['code']]
      : [];
    $stage_entry = is_array($registry['stages'][$stage['stage'] ?? ''] ?? NULL)
      ? $registry['stages'][$stage['stage']]
      : [];
    return array_replace((array) ($registry['default'] ?? []), $stage_entry, $finding_entry);
  }

  private static function registry(): array {
    if (self::$registry !== NULL) {
      return self::$registry;
    }
    $path = dirname(__DIR__, 3) . '/config/storyline_validation_troubleshooting.json';
    $registry = json_decode((string) file_get_contents($path), TRUE, 512, JSON_THROW_ON_ERROR);
    if (($registry['schema_version'] ?? '') !== 'storyline-validation-troubleshooting-v1'
      || !is_array($registry['routes'] ?? NULL)
      || !is_array($registry['stages'] ?? NULL)
      || !is_array($registry['findings'] ?? NULL)) {
      throw new \UnexpectedValueException('storyline_validation_troubleshooting_registry_invalid');
    }
    foreach ($registry['stages'] as $stage => $entry) {
      if (!is_array($entry)
        || !isset($registry['routes'][$entry['route'] ?? ''])
        || !in_array($entry['resolver'] ?? '', ['generic', 'storyline_quest_objective', 'storyline_actor_materialization', 'storyline_actor_home', 'editor_route'], TRUE)) {
        throw new \UnexpectedValueException('storyline_validation_troubleshooting_stage_invalid:' . $stage);
      }
    }
    foreach ($registry['findings'] as $code => $entry) {
      if (!is_array($entry)
        || !isset($registry['routes'][$entry['route'] ?? ''])
        || !in_array($entry['resolver'] ?? '', ['generic', 'storyline_quest_objective', 'storyline_actor_materialization', 'storyline_actor_home', 'editor_route'], TRUE)) {
        throw new \UnexpectedValueException('storyline_validation_troubleshooting_entry_invalid:' . $code);
      }
    }
    self::$registry = $registry;
    return $registry;
  }

}
