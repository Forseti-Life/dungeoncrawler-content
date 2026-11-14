<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Actor;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;

/**
 * Keeps a library actor row for every canonical actor definition.
 *
 * Folds dc_canonical_actors into the single actor library
 * (DC-ACTOR-AUTHORITY-20261114 D21). The definition's authored state is stored
 * verbatim: no default class, ancestry, statistics or level clamp is invented.
 * The actor authority commits the row, so a definition that cannot be
 * represented under the actor contract is rejected, not silently altered.
 */
class ActorDefinitionMirror {

  public const ACTOR_TYPES = ['pc', 'npc'];

  public function __construct(
    protected Connection $database,
    protected CharacterManager $actors,
    protected CanonicalDefinitionService $definitions,
  ) {}

  /**
   * Canonical paths a definition can change => definition state_data key.
   */
  public const DEFINITION_PATHS = [
    '/identity/name' => 'name',
    '/identity/alignment' => 'alignment',
    '/narrative/description' => 'description',
    '/narrative/appearance' => 'appearance',
    '/narrative/personality' => 'personality',
    '/narrative/biography' => 'backstory',
    '/narrative/fears' => 'fears',
    '/narrative/bonds' => 'bonds',
  ];

  /**
   * Read-only actor view of a canonical actor definition.
   *
   * @throws \InvalidArgumentException
   *   `actor_not_found` when the definition has no library actor row.
   */
  public function inspect(string $actor_id, AccountInterface $principal): array {
    $row = $this->libraryRow($actor_id);
    if ($row === NULL) {
      throw new \InvalidArgumentException('actor_not_found', 404);
    }

    return ['definition_id' => $actor_id, 'definition_version' => $this->definitions->currentVersion('actor', $actor_id)]
      + $this->actors->inspectActor($row['uuid'], $principal)
      + ['definition_paths' => array_keys(self::DEFINITION_PATHS)];
  }

  /**
   * Previews or executes a typed patch command on a definition-backed actor.
   *
   * The definition service stays the one writer of definitions: an executed
   * command edits the definition payload and saves it, and that save commits
   * the library actor row through the actor authority.
   *
   * @return array<string, mixed>
   *   A preview, a committed receipt, or a typed failure.
   */
  public function command(string $actor_id, string $type, array $operations, bool $execute, AccountInterface $principal): array {
    $row = $this->libraryRow($actor_id);
    if ($row === NULL) {
      throw new \InvalidArgumentException('actor_not_found', 404);
    }
    if (!in_array($type, ['actor.identity.patch', 'actor.narrative.patch'], TRUE)) {
      throw new \InvalidArgumentException('actor_command_type_unsupported_for_definition:' . $type);
    }
    $command = [
      'contract_version' => 'actor-command-v1',
      'command_id' => \Drupal::service('uuid')->generate(),
      'target' => ['scope' => 'template', 'actor_uuid' => $row['uuid']],
      'type' => $type,
      'base_revision' => $row['version'],
      'payload' => ['operations' => $operations],
    ];
    $preview = $this->actors->previewActorCommand($command, $principal);
    if (($preview['intent'] ?? NULL) !== 'preview') {
      return $preview;
    }
    foreach ($preview['changed'] as $change) {
      if (!isset(self::DEFINITION_PATHS[$change['path']])) {
        return [
          'committed' => FALSE,
          'code' => 'actor_contract_invalid',
          'message' => "A canonical actor definition has no field for {$change['path']}.",
          'next_steps' => ['Use only: ' . implode(', ', array_keys(self::DEFINITION_PATHS)) . '.'],
        ];
      }
    }
    if (!$execute) {
      return $preview;
    }
    if ($preview['changed'] === []) {
      return ['committed' => TRUE, 'command_type' => $type, 'changed' => [], 'new_revision' => $row['version'], 'next_steps' => $preview['next_steps']];
    }
    $payload = $this->definitions->definitionPayload('actor', $actor_id);
    foreach ($preview['changed'] as $change) {
      $key = self::DEFINITION_PATHS[$change['path']];
      if ($change['after'] === NULL) {
        unset($payload['state_data'][$key]);
        continue;
      }
      $payload['state_data'][$key] = $change['after'];
      if ($key === 'name') {
        $payload['display_name'] = $change['after'];
      }
    }
    $saved = $this->definitions->saveDefinition('actor', $actor_id, $payload, (string) $payload['version']);
    $after = $this->libraryRow($actor_id);

    return [
      'committed' => TRUE,
      'command_type' => $type,
      'target' => $command['target'],
      'changed' => $preview['changed'],
      'definition_version' => $saved['version'] ?? $this->definitions->currentVersion('actor', $actor_id),
      'applied_from_revision' => $row['version'],
      'new_revision' => $after['version'],
      'next_steps' => $preview['next_steps'],
    ];
  }

  /**
   * The library actor row for a definition slug, or NULL.
   *
   * @return array{id: int, uuid: string, version: int}|null
   *   Row identity.
   */
  public function libraryRow(string $actor_id): ?array {
    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'uuid', 'version'])
      ->condition('campaign_id', 0)
      ->condition('instance_id', $actor_id)
      ->execute()->fetchAssoc();

    return $row ? ['id' => (int) $row['id'], 'uuid' => (string) $row['uuid'], 'version' => (int) $row['version']] : NULL;
  }

  /**
   * Row values for a canonical actor definition payload.
   *
   * @return array<string, mixed>|null
   *   Stored column values, or NULL when the definition is not an actor
   *   (traps, obstacles and parties are not actors).
   */
  public function rowValues(string $actor_id, array $definition): ?array {
    if (!in_array($definition['actor_type'] ?? NULL, self::ACTOR_TYPES, TRUE)) {
      return NULL;
    }
    $state = $definition['state_data'];
    $stored = $state + ['actor_id' => $actor_id, 'canonical_actor_version' => (string) $definition['version']];
    if (isset($definition['source_module'])) {
      $stored['source_module'] = (string) $definition['source_module'];
    }
    $stored['name'] = (string) $definition['display_name'];
    $max_hp = (int) ($state['max_hp'] ?? 0);
    $values = [
      'type' => $definition['actor_type'],
      'role' => (string) ($state['role'] ?? $definition['actor_type']),
      'name' => (string) $definition['display_name'],
      'level' => (int) $state['level'],
      'ancestry' => (string) ($state['ancestry'] ?? $state['species'] ?? ''),
      'class' => (string) $state['class'],
      'hp_max' => $max_hp,
      'hp_current' => $max_hp,
      'character_data' => json_encode($stored, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
      'status' => 1,
      'lifecycle_state' => 'canonical_template',
    ];
    if (isset($state['ac'])) {
      $values['armor_class'] = (int) $state['ac'];
    }

    return $values;
  }

  /**
   * Creates or updates the library actor row for a definition.
   *
   * @return array{status: string, actor_uuid?: string, revision?: int}
   *   created, updated, unchanged or not_an_actor.
   */
  public function mirror(string $actor_id, array $definition, AccountInterface $principal): array {
    $values = $this->rowValues($actor_id, $definition);
    if ($values === NULL) {
      return ['status' => 'not_an_actor'];
    }
    $existing = $this->libraryRow($actor_id);
    if ($existing === NULL) {
      $receipt = $this->actors->createPreparedActor('actor.template.import', $values + ['instance_id' => $actor_id, 'campaign_id' => 0], $principal);
      return ['status' => 'created', 'actor_uuid' => $receipt['target']['actor_uuid'], 'revision' => 0];
    }
    $current = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', array_keys($values))
      ->condition('id', $existing['id'])
      ->execute()->fetchAssoc();
    $same = TRUE;
    foreach ($values as $column => $value) {
      $same = $same && ($column === 'character_data'
        ? json_decode((string) $current[$column], TRUE) == json_decode($value, TRUE)
        : (string) $current[$column] === (string) $value);
    }
    if ($same) {
      return ['status' => 'unchanged', 'actor_uuid' => $existing['uuid'], 'revision' => $existing['version']];
    }
    $receipt = $this->actors->commitPreparedActorWrite($existing['uuid'], $existing['version'], 'actor.template.import', $values, $principal);

    return ['status' => 'updated', 'actor_uuid' => $existing['uuid'], 'revision' => $receipt['new_revision']];
  }

}
