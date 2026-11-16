<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Actor;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;

/**
 * Writes canonical pc/npc actor definitions as library actor rows.
 *
 * The library row is the definition's only store
 * (DC-ACTOR-AUTHORITY-20261114 D21); CanonicalDefinitionService reads it back. The definition's authored state is stored
 * verbatim: no default class, ancestry, statistics or level clamp is invented.
 * The actor authority commits the row, so a definition that cannot be
 * represented under the actor contract is rejected, not silently altered.
 */
class ActorDefinitionMirror {

  public const SOURCE_TABLE = 'dc_campaign_characters';

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
   * Stored pc/npc definitions, read back from their library actor rows.
   *
   * @return array<int, array<string, mixed>>
   *   Records in the shape CanonicalDefinitionService::actorRecords() returns.
   *
   * @throws \RuntimeException
   *   `actor_definition_row_invalid:<id>` when a row lacks the definition
   *   envelope.
   */
  public function definitionRecords(?string $definition_id = NULL, string $search = ''): array {
    $query = $this->database->select(self::SOURCE_TABLE, 'c')
      ->fields('c', ['instance_id', 'type', 'name', 'character_data', 'changed'])
      ->condition('campaign_id', 0)
      ->condition('lifecycle_state', 'canonical_template');
    if ($definition_id !== NULL) {
      $query->condition('instance_id', $definition_id);
    }
    if ($search !== '') {
      $like = '%' . $this->database->escapeLike($search) . '%';
      $query->condition($query->orConditionGroup()->condition('name', $like, 'LIKE')->condition('instance_id', $like, 'LIKE'));
    }
    $records = [];
    foreach ($query->execute()->fetchAll(\PDO::FETCH_ASSOC) as $row) {
      $stored = json_decode((string) $row['character_data'], TRUE);
      $envelope = is_array($stored) ? ($stored['canonical_definition'] ?? NULL) : NULL;
      if (!is_array($envelope) || !isset($stored['canonical_actor_version'])) {
        throw new \RuntimeException('actor_definition_row_invalid:' . $row['instance_id']);
      }
      $records[] = [
        'actor_id' => (string) $row['instance_id'],
        'version' => (string) $stored['canonical_actor_version'],
        'display_name' => (string) $row['name'],
        'actor_type' => (string) $row['type'],
        'source_module' => $envelope['source_module'] ?? NULL,
        'metadata' => $envelope['metadata'] ?? NULL,
        'state_data' => $this->definitionState($stored),
        'updated_at' => (int) $row['changed'],
        'source_table' => self::SOURCE_TABLE,
      ];
    }

    return $records;
  }

  /**
   * A value that changes whenever any library definition row changes.
   */
  public function catalogStamp(): string {
    $query = $this->database->select(self::SOURCE_TABLE, 'c')
      ->condition('campaign_id', 0)
      ->condition('lifecycle_state', 'canonical_template');
    $query->addExpression('MAX(changed)', 'max_changed');
    $query->addExpression('COUNT(*)', 'total');
    $row = $query->execute()->fetchAssoc();

    return ($row['max_changed'] ?: '0') . '|' . $row['total'];
  }

  /**
   * Row values for a canonical actor definition payload.
   *
   * @return array<string, mixed>|null
   *   Stored column values, or NULL when the definition is not an actor
   *   (traps, obstacles and parties are not actors).
   */
  public function rowValues(string $actor_id, array $definition): ?array {
    if (!in_array($definition['actor_type'] ?? NULL, CanonicalDefinitionService::LIBRARY_ACTOR_TYPES, TRUE)) {
      return NULL;
    }
    $state = $definition['state_data'];
    // The row is the definition's one home: state_data verbatim, wrapped by
    // CanonicalDefinitionService::LIBRARY_ROW_KEYS so it reads back exactly.
    $stored = [
      'actor_id' => $actor_id,
      'canonical_actor_version' => (string) $definition['version'],
      'canonical_definition' => [
        'source_module' => $definition['source_module'] ?? NULL,
        'metadata' => $definition['metadata'] ?? NULL,
      ],
    ] + $state;
    $existing = $this->libraryRow($actor_id);
    if ($existing !== NULL) {
      $encoded = $this->database->select(self::SOURCE_TABLE, 'c')->fields('c', ['character_data'])
        ->condition('id', $existing['id'])->execute()->fetchField();
      $native = json_decode((string) $encoded, TRUE, 512, JSON_THROW_ON_ERROR);
      if (($native['schema_version'] ?? NULL) === 'character-state-v1') {
        $before = $this->definitionState($native);
        foreach (array_unique([...array_keys($before), ...array_keys($state)]) as $key) {
          if (($before[$key] ?? NULL) !== ($state[$key] ?? NULL) && !in_array($key, self::DEFINITION_PATHS, TRUE)) {
            throw new \InvalidArgumentException('character_sheet_native_build_required:' . $actor_id . ':' . $key);
          }
        }
        foreach (self::DEFINITION_PATHS as $key) {
          if (array_key_exists($key, $state)) {
            $native[$key] = $state[$key];
            $native['wizard'][$key] = $state[$key];
          }
          else {
            unset($native[$key], $native['wizard'][$key]);
          }
        }
        $native['basicInfo']['name'] = $state['name'];
        $native['wizard']['name'] = $state['name'];
        $native = \Drupal::service('dungeoncrawler_content.character_wizard_hardening')->syncWizardDraftFromCharacterData($native);
        $stored = array_intersect_key($stored, array_flip(CanonicalDefinitionService::LIBRARY_ROW_KEYS)) + $native;
      }
    }
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
   * A schema-sized view; the complete native sheet stays in this same row.
   */
  private function definitionState(array $stored): array {
    if (($stored['schema_version'] ?? NULL) !== 'character-state-v1') {
      return array_diff_key($stored, array_flip(CanonicalDefinitionService::LIBRARY_ROW_KEYS));
    }
    $hot = $this->actors->extractHotColumnsFromData($stored);
    $state = [
      'name' => $stored['name'],
      'class' => $stored['class'],
      'level' => $stored['level'],
      'hp_current' => $hot['hp_current'],
      'max_hp' => $hot['hp_max'],
      'ac' => $hot['armor_class'],
      'conditions' => $stored['conditions'],
    ];
    foreach (['ancestry', 'heritage', 'background', 'role', 'alignment', 'description', 'appearance', 'personality', 'backstory', 'motivations', 'fears', 'bonds'] as $key) {
      if (isset($stored[$key]) && $stored[$key] !== '') {
        $state[$key] = $stored[$key];
      }
    }
    return $state;
  }

  public function attachCharacterSheet(string $actor_id, int $character_id, string $role, array $provenance, AccountInterface $principal): array {
    $record = $this->actors->loadCharacter($character_id);
    if (!$record || (int) $record->campaign_id !== 0 || (int) $record->status === 2
      || ((int) $record->uid !== (int) $principal->id() && !$principal->hasPermission('administer dungeoncrawler content'))) {
      throw new \InvalidArgumentException('storyline_actor_sheet_not_owned_library:' . $actor_id . ':' . $character_id);
    }
    $existing = $this->libraryRow($actor_id);
    if (($existing !== NULL && $existing['id'] !== $character_id)
      || ($record->lifecycle_state === 'canonical_template' && $record->instance_id !== $actor_id)
      || ($existing === NULL && $this->definitions->currentVersion('actor', $actor_id) !== NULL)) {
      throw new \InvalidArgumentException('storyline_actor_sheet_identity_conflict:' . $actor_id . ':' . $character_id);
    }
    $stored = json_decode((string) $record->character_data, TRUE, 512, JSON_THROW_ON_ERROR);
    if (($stored['schema_version'] ?? NULL) !== 'character-state-v1') {
      throw new \InvalidArgumentException('storyline_actor_sheet_native_state_required:' . $actor_id);
    }
    $stored['actor_id'] = $actor_id;
    $stored['role'] = $role;
    $stored['canonical_actor_version'] = isset($stored['canonical_actor_version'])
      ? $this->definitions->incrementPatch($stored['canonical_actor_version']) : '1.0.0';
    $stored['canonical_definition'] = [
      'source_module' => 'dungeoncrawler_content',
      'metadata' => $provenance === [] ? ($stored['canonical_definition']['metadata'] ?? NULL) : [
        'generated_by' => array_intersect_key($provenance, array_flip(['tool', 'model', 'provider', 'prompt_hash', 'seed', 'generated_at'])),
      ],
    ];
    return $this->actors->commitPreparedActorWrite((string) $record->uuid, (int) $record->version, 'actor.template.import', [
      'instance_id' => $actor_id,
      'type' => $existing !== NULL ? $record->type : 'npc',
      'role' => $role,
      'status' => 1,
      'lifecycle_state' => 'canonical_template',
      'character_data' => json_encode($stored, JSON_THROW_ON_ERROR),
    ], $principal);
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
