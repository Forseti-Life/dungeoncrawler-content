<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Actor;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\ActorDocumentConverter;
use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\dungeoncrawler_content\Service\StateValidationService;

/**
 * Executes typed actor commands (DC-ACTOR-AUTHORITY-20261114 C1-C9).
 *
 * Internal collaborator of the CharacterManager facade; callers use
 * CharacterManager::executeActorCommand() and previewActorCommand().
 *
 * One command changes one actor in one transaction: lock, re-read, apply only
 * the requested operations, validate the whole candidate, write, advance the
 * aggregate revision, append history and store the receipt. A failed contract
 * changes nothing and returns a typed failure; there is no alternate path.
 *
 * Commands address canonical document paths. Until actor rows store the
 * canonical document directly, PATHS maps each supported path to the stored
 * key it currently lives under.
 */
class ActorCommandProcessor {

  public const CONTRACT = 'actor-command-v1';
  public const RECEIPT_CONTRACT = 'actor-command-receipt-v1';

  /**
   * Patchable canonical paths per command type => stored key.
   */
  public const PATHS = [
    'actor.identity.patch' => [
      '/identity/name' => 'name',
      '/identity/alignment' => 'alignment',
      '/identity/personal/age' => 'age',
      '/identity/personal/gender' => 'gender',
      '/identity/personal/deity' => 'deity',
      '/identity/personal/concept' => 'concept',
    ],
    'actor.narrative.patch' => [
      '/narrative/description' => 'description',
      '/narrative/appearance' => 'appearance',
      '/narrative/personality' => 'personality',
      '/narrative/biography' => 'backstory',
      '/narrative/fears' => 'fears',
      '/narrative/bonds' => 'bonds',
    ],
  ];

  /**
   * Row writes a domain rule owner may prepare for the authority to commit.
   *
   * The owner (for example the character creation rules) computes the stored
   * values; the authority locks, compares the revision, writes, validates the
   * result against the actor contract, and records history and a receipt.
   */
  public const PREPARED_WRITES = [
    'actor.creation.save' => [
      'campaign_id', 'name', 'level', 'ancestry', 'class', 'hp_current', 'hp_max', 'armor_class',
      'experience_points', 'position_q', 'position_r', 'last_room_id', 'character_data', 'status',
      'lifecycle_state',
    ],
    'actor.template.import' => [
      'instance_id', 'type', 'role', 'name', 'level', 'ancestry', 'class', 'hp_current', 'hp_max', 'armor_class',
      'character_data', 'status', 'lifecycle_state',
    ],
  ];

  /**
   * Columns a domain rule owner may supply when creating a new actor row.
   *
   * uuid, instance_id, uid, character_id, version and timestamps are
   * allocated by the authority and can never be supplied.
   */
  public const PREPARED_CREATES = [
    'actor.draft.create' => [
      'campaign_id', 'source_character_id', 'type', 'role', 'name', 'level', 'ancestry', 'class', 'hp_current',
      'hp_max', 'armor_class', 'experience_points', 'position_q', 'position_r', 'last_room_id',
      'character_data', 'default_locations', 'portrait', 'status', 'lifecycle_state',
    ],
    // A library template keeps its definition slug as instance_id.
    'actor.template.import' => [
      'instance_id', 'campaign_id', 'type', 'role', 'name', 'level', 'ancestry', 'class', 'hp_current', 'hp_max',
      'armor_class', 'character_data', 'status', 'lifecycle_state',
    ],
  ];

  public const LIFECYCLE_COMMANDS = ['actor.archive', 'actor.restore', 'actor.delete'];

  private const STATUS_ARCHIVED = 2;

  /**
   * Stored containers that may hold a duplicate of a stored key.
   */
  private const DUPLICATE_CONTAINERS = ['basicInfo', 'wizard'];

  private ?array $schema = NULL;

  /**
   * The typed command currently delegating to a domain rule owner, if any.
   */
  private ?array $activeCommand = NULL;

  private ?array $lastPreparedReceipt = NULL;

  public function __construct(
    protected Connection $database,
    protected DefinitionSchemaValidator $schemaValidator,
    protected ActorDocumentConverter $converter,
    protected StateValidationService $validation,
    protected TimeInterface $time,
    protected UuidInterface $uuid,
  ) {}

  /**
   * Read-only inspection of one actor's canonical document and history.
   *
   * Never creates, repairs or converts stored data.
   *
   * @return array<string, mixed>
   *   The actor reference, revision, canonical document, campaign-instance
   *   state, conversion issues, validation report and recent history.
   *
   * @throws \InvalidArgumentException
   *   `actor_not_found` (404) when no actor matches for this principal.
   */
  public function inspect(string $actor_uuid, AccountInterface $principal, int $history_limit = 5): array {
    $row = $this->database->select('dc_campaign_characters', 'c')->fields('c')
      ->condition('uuid', $actor_uuid)->execute()->fetchAssoc();
    if (!$row || !self::mayEdit($row, $principal)) {
      throw new \InvalidArgumentException('actor_not_found', 404);
    }
    $converted = $this->converter->convertRow($row, self::decode($row['character_data']) ?? [], self::decode($row['state_data']) ?? []);
    $scope = (int) $row['campaign_id'] > 0 ? 'campaign_instance' : ($row['lifecycle_state'] === 'draft_library' ? 'draft' : 'template');
    $history = $this->database->select('dc_actor_revisions', 'h')
      ->fields('h', ['revision', 'parent_revision', 'command_type', 'principal_uid', 'changed_paths', 'created'])
      ->condition('actor_uuid', $actor_uuid)
      ->orderBy('revision', 'DESC')
      ->range(0, max(1, min(25, $history_limit)))
      ->execute()->fetchAll(\PDO::FETCH_ASSOC);

    return [
      'contract' => 'actor-inspection-v1',
      'target' => ['scope' => $scope, 'actor_uuid' => $actor_uuid] + ($scope === 'campaign_instance' ? ['campaign_id' => (int) $row['campaign_id']] : []),
      'revision' => (int) $row['version'],
      'archived' => (int) $row['status'] === self::STATUS_ARCHIVED,
      'document' => $converted['document'],
      'instance_state' => $converted['instance_state'],
      'conversion_issues' => $converted['issues'],
      'validation' => $converted['issues'] === [] ? $this->validation->validateActor($converted['document'], 'actor_authoring') : NULL,
      'commands' => array_map('array_keys', self::PATHS) + array_fill_keys(self::LIFECYCLE_COMMANDS, []) + ['actor.build.apply_choices' => ['wizard choice fields; see get_character_creation_reference']],
      'history' => array_map(static fn(array $entry): array => [
        'revision' => (int) $entry['revision'],
        'parent_revision' => (int) $entry['parent_revision'],
        'command_type' => $entry['command_type'],
        'principal_uid' => (int) $entry['principal_uid'],
        'changed_paths' => json_decode($entry['changed_paths'], TRUE),
        'created' => (int) $entry['created'],
      ], $history),
    ];
  }

  /**
   * Validates and plans a command without writing anything.
   *
   * @return array<string, mixed>
   *   A plan, or a typed failure with committed FALSE.
   */
  public function preview(array $command, AccountInterface $principal): array {
    if (($command['type'] ?? NULL) === 'actor.build.apply_choices') {
      if (($envelope = $this->checkEnvelope($command)) !== NULL) {
        return $envelope;
      }
      $located = $this->locate($command, $principal);
      if (isset($located['failure'])) {
        return $located['failure'];
      }
      $planned = $this->creationRules()->planUpdates((int) $located['row']['id'], $command['payload']['choices']);
      if ($planned['blocking_errors'] !== []) {
        return $this->failure($command, 'actor_choice_ineligible', 'These choices are not valid for this character.', FALSE, ['Review the listed choices and pick valid options.'], $planned['blocking_errors']);
      }
      return [
        'contract' => self::RECEIPT_CONTRACT,
        'intent' => 'preview',
        'committed' => FALSE,
        'command_id' => $command['command_id'],
        'target' => $command['target'],
        'applied_from_revision' => (int) $located['row']['version'],
        'applied_fields' => $planned['applied_fields'],
        'next_steps' => $this->creationRules()->validationNextSteps($planned['validation']),
        'executable_command' => $command,
      ];
    }
    $plan = $this->plan($command, $principal, FALSE);
    if (isset($plan['failure'])) {
      return $plan['failure'];
    }

    return [
      'contract' => self::RECEIPT_CONTRACT,
      'intent' => 'preview',
      'committed' => FALSE,
      'command_id' => $command['command_id'],
      'target' => $command['target'],
      'applied_from_revision' => $plan['revision'],
      'changed' => $plan['changed'],
      'validation' => $plan['validation'],
      'next_steps' => $plan['next_steps'],
      'executable_command' => $command,
    ];
  }

  /**
   * Executes a command atomically and returns its receipt.
   *
   * @return array<string, mixed>
   *   A committed receipt, or a typed failure with committed FALSE.
   */
  public function execute(array $command, AccountInterface $principal): array {
    $envelope = $this->checkEnvelope($command);
    if ($envelope !== NULL) {
      return $envelope;
    }
    $uid = (int) $principal->id();
    $request_hash = 'sha256:' . hash('sha256', json_encode(self::canonicalize($command), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

    $transaction = $this->database->startTransaction();
    try {
      // Lock the actor first so concurrent retries of one command serialize.
      $this->database->select('dc_campaign_characters', 'c')->fields('c', ['id'])
        ->condition('uuid', $command['target']['actor_uuid'])->forUpdate()->execute()->fetchField();

      $stored = $this->database->select('dc_actor_command_receipts', 'r')
        ->fields('r', ['request_hash', 'receipt'])
        ->condition('principal_uid', $uid)
        ->condition('command_id', $command['command_id'])
        ->execute()->fetchAssoc();
      if ($stored) {
        return $stored['request_hash'] === $request_hash
          ? json_decode($stored['receipt'], TRUE, 512, JSON_THROW_ON_ERROR)
          : $this->failure($command, 'actor_command_id_reused', 'This command id was already used for a different request.', FALSE, ['Send the new request with a new command id.']);
      }

      if ($command['type'] === 'actor.build.apply_choices') {
        $receipt = $this->executeChoices($command, $principal);
        if (!empty($receipt['committed'])) {
          $this->storeReceipt($uid, $command['command_id'], $request_hash, $command['type'], $command['target']['actor_uuid'], $receipt, $this->time->getRequestTime());
        }
        return $receipt;
      }
      $plan = $this->plan($command, $principal, TRUE);
      if (isset($plan['failure'])) {
        return $plan['failure'];
      }
      $row = $plan['row'];
      if ($row['lifecycle_state'] === 'canonical_template' && (int) $row['campaign_id'] === 0) {
        // The row is a canonical definition; its versioning and room impact
        // are owned by CanonicalDefinitionService::saveDefinition().
        return $this->failure($command, 'actor_definition_owned', 'This actor is a canonical definition and is changed through the definition tools.', FALSE, ['Use the actor tools on the Definition Editor or Storyline Builder.']);
      }
      $revision = $plan['revision'];
      $new_revision = $revision;
      $now = $this->time->getRequestTime();

      if ($command['type'] === 'actor.delete') {
        $new_revision = $revision + 1;
        $deleted = $this->database->delete('dc_campaign_characters')
          ->condition('id', (int) $row['id'])
          ->condition('version', $revision)
          ->execute();
        if ($deleted !== 1) {
          throw new \RuntimeException('actor_projection_invariant_failed: delete affected ' . $deleted . ' rows.');
        }
        $this->appendRevision($row['uuid'], $new_revision, $revision, $command, $uid, $plan, $now);
      }
      elseif ($plan['changed'] !== []) {
        $new_revision = $revision + 1;
        $fields = ['version' => $new_revision, 'changed' => $now, 'updated' => $now];
        foreach (['character_data', 'state_data'] as $column) {
          if ($plan['stored'][$column] !== NULL) {
            $fields[$column] = json_encode($plan['stored'][$column], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
          }
        }
        foreach ($plan['columns'] as $column => $value) {
          $fields[$column] = $value;
        }
        $updated = $this->database->update('dc_campaign_characters')
          ->fields($fields)
          ->condition('id', (int) $row['id'])
          ->condition('version', $revision)
          ->execute();
        if ($updated !== 1) {
          throw new \RuntimeException('actor_projection_invariant_failed: revision compare-and-swap affected ' . $updated . ' rows.');
        }
        // The stored row must read back as exactly the validated candidate.
        $reloaded = $this->database->select('dc_campaign_characters', 'c')->fields('c')
          ->condition('id', (int) $row['id'])->execute()->fetchAssoc();
        $persisted = $this->converter->convertRow($reloaded, self::decode($reloaded['character_data']) ?? [], self::decode($reloaded['state_data']) ?? []);
        if ($persisted['issues'] !== [] || $persisted['document'] != $plan['candidate']) {
          throw new \RuntimeException('actor_projection_invariant_failed: the stored actor does not read back as the validated candidate.');
        }
        $this->appendRevision($row['uuid'], $new_revision, $revision, $command, $uid, $plan, $now);
      }

      $receipt = [
        'contract' => self::RECEIPT_CONTRACT,
        'committed' => TRUE,
        'command_id' => $command['command_id'],
        'command_type' => $command['type'],
        'target' => $command['target'],
        'inspected_revision' => $command['base_revision'],
        'applied_from_revision' => $revision,
        'new_revision' => $new_revision,
        'changed' => $plan['changed'],
        'validation' => $plan['validation'],
        'next_steps' => $plan['next_steps'],
      ];
      $this->database->insert('dc_actor_command_receipts')->fields([
        'principal_uid' => $uid,
        'command_id' => $command['command_id'],
        'request_hash' => $request_hash,
        'command_type' => $command['type'],
        'actor_uuid' => $row['uuid'],
        'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        'created' => $now,
      ])->execute();

      return $receipt;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Commits a write prepared by a domain rule owner.
   *
   * @param string $actor_uuid
   *   Actor to change.
   * @param int $expected_revision
   *   Revision the owner prepared against; a mismatch is a conflict.
   * @param string $type
   *   One of the PREPARED_WRITES keys.
   * @param array $fields
   *   Stored column values, limited to the columns that type may write.
   * @param \Drupal\Core\Session\AccountInterface $principal
   *   Authenticated principal.
   *
   * @return array<string, mixed>
   *   A committed receipt.
   *
   * @throws \RuntimeException
   *   `revision_conflict` (409) when the actor changed since it was prepared.
   * @throws \InvalidArgumentException
   *   `actor_contract_invalid` (422) when the result violates the contract.
   */
  public function commitPrepared(string $actor_uuid, int $expected_revision, string $type, array $fields, AccountInterface $principal): array {
    $allowed = self::PREPARED_WRITES[$type] ?? NULL;
    if ($allowed === NULL || ($unknown = array_diff(array_keys($fields), $allowed)) !== []) {
      throw new \LogicException('actor_prepared_write_invalid:' . $type . ':' . implode(',', $unknown ?? []));
    }
    $transaction = $this->database->startTransaction();
    try {
      $row = $this->database->select('dc_campaign_characters', 'c')->fields('c')
        ->condition('uuid', $actor_uuid)->forUpdate()->execute()->fetchAssoc();
      if (!$row || !self::mayEdit($row, $principal)) {
        throw new \InvalidArgumentException('actor_not_found', 404);
      }
      if ((int) $row['version'] !== $expected_revision) {
        throw new \RuntimeException('revision_conflict', 409);
      }
      $before = $this->converter->convertRow($row, self::decode($row['character_data']) ?? [], self::decode($row['state_data']) ?? []);
      $now = $this->time->getRequestTime();
      $new_revision = $expected_revision + 1;
      $updated = $this->database->update('dc_campaign_characters')
        ->fields($fields + ['version' => $new_revision, 'changed' => $now])
        ->condition('id', (int) $row['id'])
        ->condition('version', $expected_revision)
        ->execute();
      if ($updated !== 1) {
        throw new \RuntimeException('revision_conflict', 409);
      }
      $reloaded = $this->database->select('dc_campaign_characters', 'c')->fields('c')
        ->condition('id', (int) $row['id'])->execute()->fetchAssoc();
      $after = $this->converter->convertRow($reloaded, self::decode($reloaded['character_data']) ?? [], self::decode($reloaded['state_data']) ?? []);
      if ($after['issues'] !== []) {
        throw new \InvalidArgumentException('actor_contract_invalid: ' . implode('; ', array_map(static fn(array $issue): string => $issue['path'] . ' ' . $issue['code'] . ' ' . $issue['detail'], $after['issues'])), 422);
      }
      $report = $this->validation->validateActor($after['document'], 'actor_authoring');
      if (!$report['valid_for_save']) {
        $blocking = array_filter($report['findings'], static fn(array $finding): bool => $finding['blocking']);
        throw new \InvalidArgumentException('actor_contract_invalid: ' . implode('; ', array_map(static fn(array $finding): string => $finding['path'] . ' ' . $finding['message'], $blocking)), 422);
      }
      $changed = [];
      foreach (self::changedPaths($before['document'], $after['document']) as $path) {
        $changed[] = ['path' => $path, 'before' => self::get($before['document'], $path), 'after' => self::get($after['document'], $path)];
      }
      // Inside a typed command, the write belongs to that command.
      $command = $this->activeCommand ?? ['command_id' => $this->uuid->generate(), 'type' => $type];
      $type = $command['type'];
      $this->appendRevision($actor_uuid, $new_revision, $expected_revision, $command, (int) $principal->id(), ['candidate' => $after['document'], 'changed' => $changed], $now);
      $receipt = [
        'contract' => self::RECEIPT_CONTRACT,
        'committed' => TRUE,
        'command_id' => $command['command_id'],
        'command_type' => $type,
        'target' => ['actor_uuid' => $actor_uuid],
        'inspected_revision' => $expected_revision,
        'applied_from_revision' => $expected_revision,
        'new_revision' => $new_revision,
        'changed' => $changed,
        'validation' => [
          'profile' => $report['profile'],
          'rules_version' => $report['rules_version'],
          'valid_for_save' => TRUE,
          'ready_for_publication' => $report['ready_for_publication'],
          'checks' => $report['checks'],
        ],
        'next_steps' => $report['next_steps'],
      ];
      $this->lastPreparedReceipt = $receipt;
      if ($this->activeCommand === NULL) {
        $this->storeReceipt((int) $principal->id(), $command['command_id'], 'sha256:' . hash('sha256', $type . ':' . $actor_uuid . ':' . $new_revision), $type, $actor_uuid, $receipt, $now);
      }

      return $receipt;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Creates a new actor row from values prepared by a domain rule owner.
   *
   * The authority allocates the identity, owner and revision; the caller
   * supplies only the stored values its type may write.
   *
   * @return array<string, mixed>
   *   A committed receipt with actor_row_id and target.actor_uuid.
   *
   * @throws \InvalidArgumentException
   *   `actor_contract_invalid` (422) when the new actor violates the contract.
   */
  public function createPrepared(string $type, array $fields, AccountInterface $principal): array {
    $allowed = self::PREPARED_CREATES[$type] ?? NULL;
    if ($allowed === NULL || ($unknown = array_diff(array_keys($fields), $allowed)) !== []) {
      throw new \LogicException('actor_prepared_create_invalid:' . $type . ':' . implode(',', $unknown ?? []));
    }
    $transaction = $this->database->startTransaction();
    try {
      $now = $this->time->getRequestTime();
      $actor_uuid = $this->uuid->generate();
      $id = (int) $this->database->insert('dc_campaign_characters')->fields($fields + [
        'instance_id' => $actor_uuid,
      ] + [
        'uuid' => $actor_uuid,
        'uid' => (int) $principal->id(),
        'character_id' => 0,
        'version' => 0,
        'created' => $now,
        'changed' => $now,
      ])->execute();
      $row = $this->database->select('dc_campaign_characters', 'c')->fields('c')->condition('id', $id)->execute()->fetchAssoc();
      $created = $this->converter->convertRow($row, self::decode($row['character_data']) ?? [], self::decode($row['state_data']) ?? []);
      if ($created['issues'] !== []) {
        throw new \InvalidArgumentException('actor_contract_invalid: ' . implode('; ', array_map(static fn(array $issue): string => $issue['path'] . ' ' . $issue['code'] . ' ' . $issue['detail'], $created['issues'])), 422);
      }
      $report = $this->validation->validateActor($created['document'], 'actor_authoring');
      if (!$report['valid_for_save']) {
        $blocking = array_filter($report['findings'], static fn(array $finding): bool => $finding['blocking']);
        throw new \InvalidArgumentException('actor_contract_invalid: ' . implode('; ', array_map(static fn(array $finding): string => $finding['path'] . ' ' . $finding['message'], $blocking)), 422);
      }
      $command = ['command_id' => $this->uuid->generate(), 'type' => $type];
      $changed = [['path' => '/lifecycle/created', 'before' => FALSE, 'after' => TRUE]];
      $this->appendRevision($actor_uuid, 0, 0, $command, (int) $principal->id(), ['candidate' => $created['document'], 'changed' => $changed], $now);
      $scope = (int) $row['campaign_id'] > 0 ? 'campaign_instance' : ($row['lifecycle_state'] === 'draft_library' ? 'draft' : 'template');
      $receipt = [
        'contract' => self::RECEIPT_CONTRACT,
        'committed' => TRUE,
        'command_id' => $command['command_id'],
        'command_type' => $type,
        'target' => ['scope' => $scope, 'actor_uuid' => $actor_uuid] + ($scope === 'campaign_instance' ? ['campaign_id' => (int) $row['campaign_id']] : []),
        'actor_row_id' => $id,
        'new_revision' => 0,
        'changed' => $changed,
        'validation' => [
          'profile' => $report['profile'],
          'rules_version' => $report['rules_version'],
          'valid_for_save' => TRUE,
          'ready_for_publication' => $report['ready_for_publication'],
          'checks' => $report['checks'],
        ],
        'next_steps' => $report['next_steps'],
      ];
      $this->storeReceipt((int) $principal->id(), $command['command_id'], 'sha256:' . hash('sha256', $type . ':' . $actor_uuid . ':0'), $type, $actor_uuid, $receipt, $now);

      return $receipt;
    }
    catch (\Throwable $exception) {
      $transaction->rollBack();
      throw $exception;
    }
  }

  /**
   * Whether the principal may read and change this actor.
   *
   * Owners and character administrators may; definition editors may edit
   * library templates that mirror canonical actor definitions.
   */
  private static function mayEdit(array $row, AccountInterface $principal): bool {
    return (int) $row['uid'] === (int) $principal->id()
      || $principal->hasPermission('administer dungeoncrawler content')
      || $principal->hasPermission('edit any dungeoncrawler characters')
      || ($row['lifecycle_state'] === 'canonical_template' && (int) $row['campaign_id'] === 0
        && $principal->hasPermission('edit canonical dungeoncrawler definitions'));
  }

  /**
   * Stores one idempotent command receipt.
   */
  private function storeReceipt(int $uid, string $command_id, string $request_hash, string $type, string $actor_uuid, array $receipt, int $now): void {
    $this->database->insert('dc_actor_command_receipts')->fields([
      'principal_uid' => $uid,
      'command_id' => $command_id,
      'request_hash' => $request_hash,
      'command_type' => $type,
      'actor_uuid' => $actor_uuid,
      'receipt' => json_encode($receipt, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
      'created' => $now,
    ])->execute();
  }

  /**
   * Leaf pointers whose value differs between two documents.
   *
   * @return array<int, string>
   *   Sorted RFC 6901 pointers. A list is compared as one value.
   */
  private static function changedPaths(array $before, array $after, string $prefix = ''): array {
    $paths = [];
    foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
      $left = $before[$key] ?? NULL;
      $right = $after[$key] ?? NULL;
      if ($left === $right) {
        continue;
      }
      $both_maps = is_array($left) && is_array($right) && !array_is_list($left) && !array_is_list($right);
      $paths = array_merge($paths, $both_maps ? self::changedPaths($left, $right, $prefix . '/' . $key) : [$prefix . '/' . $key]);
    }
    sort($paths);

    return $paths;
  }

  /**
   * Resolves and authorizes the command target.
   *
   * @return array<string, mixed>
   *   Either ['failure' => typed failure] or ['row' => array, 'scope' => string].
   */
  private function locate(array $command, AccountInterface $principal): array {
    $target = $command['target'];
    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('uuid', $target['actor_uuid'])
      ->execute()->fetchAssoc();
    // An unauthorized caller learns nothing about whether the actor exists.
    if (!$row || !self::mayEdit($row, $principal)) {
      return ['failure' => $this->failure($command, 'actor_not_found', 'No actor matches this reference for the current user.', FALSE, ['Inspect the actor again and use its exact reference.'])];
    }
    $scope = (int) $row['campaign_id'] > 0 ? 'campaign_instance' : ($row['lifecycle_state'] === 'draft_library' ? 'draft' : 'template');
    if ($scope !== $target['scope'] || ($scope === 'campaign_instance' && (int) $row['campaign_id'] !== (int) $target['campaign_id'])) {
      return ['failure' => $this->failure($command, 'actor_scope_mismatch', "This actor is a {$scope}; the command addressed a {$target['scope']}.", FALSE, ['Inspect the actor again and address its actual scope.'])];
    }
    $is_lifecycle = in_array($command['type'], self::LIFECYCLE_COMMANDS, TRUE);
    if ((int) $row['status'] === self::STATUS_ARCHIVED && !$is_lifecycle) {
      return ['failure' => $this->failure($command, 'actor_precondition_failed', 'Archived actors cannot be edited. Restore the actor first.', FALSE, ['Restore the actor, then repeat the edit.'])];
    }

    return ['row' => $row, 'scope' => $scope];

  }

  /**
   * Executes wizard choices through the character creation rules owner.
   *
   * The rules owner validates eligibility and computes the stored values; its
   * write is committed by commitPrepared() under this command's identity.
   */
  private function executeChoices(array $command, AccountInterface $principal): array {
    $located = $this->locate($command, $principal);
    if (isset($located['failure'])) {
      return $located['failure'];
    }
    $this->activeCommand = ['command_id' => $command['command_id'], 'type' => $command['type']];
    $this->lastPreparedReceipt = NULL;
    try {
      $result = $this->creationRules()->applyValidatedChoices((int) $located['row']['id'], (int) $command['base_revision'], $command['payload']['choices']);
    }
    catch (\InvalidArgumentException $exception) {
      if ($exception->getCode() !== 422) {
        throw $exception;
      }
      return $this->failure($command, 'actor_choice_ineligible', $exception->getMessage(), FALSE, ['Review the listed choices and pick valid options.']);
    }
    finally {
      $this->activeCommand = NULL;
    }
    if ($this->lastPreparedReceipt === NULL) {
      throw new \RuntimeException('actor_projection_invariant_failed: creation rules reported success without committing through the actor authority.');
    }

    return [
      'command_id' => $command['command_id'],
      'command_type' => $command['type'],
      'target' => $command['target'],
      'inspected_revision' => $command['base_revision'],
      'applied_fields' => $result['applied_fields'],
      'next_steps' => $result['next_steps'],
      'assistant_response' => $result['assistant_response'],
    ] + $this->lastPreparedReceipt;
  }

  /**
   * The character creation rules owner, resolved on use to avoid a cycle.
   */
  private function creationRules(): \Drupal\dungeoncrawler_content\Service\CharacterCreationGmService {
    return \Drupal::service('dungeoncrawler_content.character_creation_gm');
  }

  /**
   * Appends the immutable history record for one changed revision.
   */
  private function appendRevision(string $actor_uuid, int $revision, int $parent, array $command, int $uid, array $plan, int $now): void {
    $document_json = json_encode($plan['candidate'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $this->database->insert('dc_actor_revisions')->fields([
      'actor_uuid' => $actor_uuid,
      'revision' => $revision,
      'parent_revision' => $parent,
      'command_id' => $command['command_id'],
      'command_type' => $command['type'],
      'principal_uid' => $uid,
      'changed_paths' => json_encode(array_column($plan['changed'], 'path'), JSON_THROW_ON_ERROR),
      'document' => $document_json,
      'content_hash' => 'sha256:' . hash('sha256', $document_json),
      'created' => $now,
    ])->execute();
  }

  /**
   * Resolves, authorizes, applies and validates a command in memory.
   *
   * @return array<string, mixed>
   *   Either ['failure' => typed failure] or the planned change set.
   */
  private function plan(array $command, AccountInterface $principal, bool $envelope_checked): array {
    if (!$envelope_checked && ($envelope = $this->checkEnvelope($command)) !== NULL) {
      return ['failure' => $envelope];
    }
    $located = $this->locate($command, $principal);
    if (isset($located['failure'])) {
      return $located;
    }
    $row = $located['row'];
    $scope = $located['scope'];
    $is_lifecycle = in_array($command['type'], self::LIFECYCLE_COMMANDS, TRUE);

    $stored = ['character_data' => self::decode($row['character_data']), 'state_data' => self::decode($row['state_data'])];
    $before = $this->converter->convertRow($row, $stored['character_data'] ?? [], $stored['state_data'] ?? []);
    // Deleting needs no valid document; every other command does.
    if ($before['issues'] !== [] && $command['type'] !== 'actor.delete') {
      return ['failure' => $this->failure($command, 'actor_migration_blocked', 'This actor has stored values that do not map to the actor contract and must be repaired first.', FALSE, ['Repair the listed stored values, then repeat the edit.'], $before['issues'])];
    }

    $paths = self::PATHS[$command['type']] ?? [];
    $candidate = $before['document'];
    $changed = [];
    $columns = [];
    $seen = [];
    if ($command['type'] === 'actor.delete') {
      if ($scope === 'campaign_instance') {
        return ['failure' => $this->failure($command, 'actor_precondition_failed', 'A campaign actor cannot be deleted; its campaign state would be orphaned.', FALSE, ['Archive the actor instead.'])];
      }
      $instances = (int) $this->database->select('dc_campaign_characters', 'i')
        ->condition('source_character_id', (int) $row['id'])
        ->condition('id', (int) $row['id'], '<>')
        ->countQuery()->execute()->fetchField();
      if ($instances > 0) {
        return ['failure' => $this->failure($command, 'actor_precondition_failed', "This actor is the source of {$instances} campaign actor(s) and cannot be deleted.", FALSE, ['Archive the actor instead.'])];
      }
      $changed[] = ['path' => '/lifecycle/deleted', 'before' => FALSE, 'after' => TRUE];
    }
    elseif ($is_lifecycle && ($problem = $this->planLifecycle($command['type'], $row, $stored, $columns, $changed)) !== NULL) {
      return ['failure' => $this->failure($command, 'actor_precondition_failed', $problem, FALSE, ['An operator must set the status this actor returns to before it can be restored.'])];
    }
    foreach ($command['payload']['operations'] ?? [] as $index => $operation) {
      $path = $operation['path'];
      if (!isset($paths[$path]) || isset($seen[$path])) {
        $reason = isset($seen[$path]) ? 'is addressed more than once' : 'cannot be changed by ' . $command['type'];
        return ['failure' => $this->failure($command, 'actor_contract_invalid', "Operation {$index}: path {$path} {$reason}.", FALSE, ['Use only the paths this command type supports: ' . implode(', ', array_keys($paths)) . '.'])];
      }
      $seen[$path] = TRUE;
      if ($operation['op'] === 'unset' && $path === '/identity/name') {
        return ['failure' => $this->failure($command, 'actor_contract_invalid', 'An actor must keep a name.', FALSE, ['Set a new name instead of removing it.'])];
      }
      $old = self::get($candidate, $path);
      $new = $operation['op'] === 'set' ? $operation['value'] : NULL;
      if ($old === $new) {
        continue;
      }
      self::put($candidate, $path, $new);
      $changed[] = ['path' => $path, 'before' => $old, 'after' => $new];
      $this->writeStored($stored, $paths[$path], $new);
      if ($path === '/identity/name') {
        $columns['name'] = $new;
      }
    }

    $report = $this->validation->validateActor($candidate, 'actor_authoring');
    if ($command['type'] === 'actor.delete') {
      $report['next_steps'] = ['The actor was deleted. Its final state is kept in the revision history.'];
    }
    elseif (!$report['valid_for_save']) {
      $blocking = array_values(array_filter($report['findings'], static fn(array $finding): bool => $finding['blocking']));
      return ['failure' => $this->failure($command, 'actor_contract_invalid', 'The requested change does not satisfy the actor contract.', FALSE, $report['next_steps'], $blocking)];
    }

    return [
      'row' => $row,
      'revision' => (int) $row['version'],
      'candidate' => $candidate,
      'stored' => $stored,
      'columns' => $columns,
      'changed' => $changed,
      'validation' => [
        'profile' => $report['profile'],
        'rules_version' => $report['rules_version'],
        'valid_for_save' => $report['valid_for_save'],
        'ready_for_publication' => $report['ready_for_publication'],
        'checks' => $report['checks'],
      ],
      'next_steps' => $changed === []
        ? array_merge(['Nothing changed: the actor already has these values.'], $report['next_steps'])
        : $report['next_steps'],
    ];
  }

  /**
   * Plans archive or restore; archiving remembers the status to return to.
   *
   * @return string|null
   *   A precondition problem, or NULL when the plan is valid.
   */
  private function planLifecycle(string $type, array $row, array &$stored, array &$columns, array &$changed): ?string {
    $status = (int) $row['status'];
    $meta = is_array($stored['character_data']['_archive_meta'] ?? NULL) ? $stored['character_data']['_archive_meta'] : NULL;
    if ($type === 'actor.archive' && $status !== self::STATUS_ARCHIVED) {
      $stored['character_data'] ??= [];
      $stored['character_data']['_archive_meta'] = ['previous_status' => $status, 'archived_at' => $this->time->getRequestTime()];
      $columns['status'] = self::STATUS_ARCHIVED;
      $changed[] = ['path' => '/lifecycle/archived', 'before' => FALSE, 'after' => TRUE];
    }
    elseif ($type === 'actor.restore' && $status === self::STATUS_ARCHIVED) {
      if (!is_int($meta['previous_status'] ?? NULL) || $meta['previous_status'] === self::STATUS_ARCHIVED) {
        return 'This archived actor does not record the status it should return to.';
      }
      unset($stored['character_data']['_archive_meta']);
      $columns['status'] = $meta['previous_status'];
      $changed[] = ['path' => '/lifecycle/archived', 'before' => TRUE, 'after' => FALSE];
    }

    return NULL;
  }

  /**
   * Writes one stored key wherever it already lives, keeping copies equal.
   */
  private function writeStored(array &$stored, string $key, mixed $value): void {
    $written = FALSE;
    foreach (['character_data', 'state_data'] as $column) {
      if (!is_array($stored[$column])) {
        continue;
      }
      if (array_key_exists($key, $stored[$column])) {
        self::assign($stored[$column], $key, $value);
        $written = TRUE;
      }
      foreach (self::DUPLICATE_CONTAINERS as $container) {
        if (is_array($stored[$column][$container] ?? NULL) && array_key_exists($key, $stored[$column][$container])) {
          self::assign($stored[$column][$container], $key, $value);
        }
      }
    }
    if (!$written && $value !== NULL) {
      $stored['character_data'] ??= [];
      $stored['character_data'][$key] = $value;
    }
    if ($key === 'name' && is_array($stored['character_data']) && array_key_exists('display_name', $stored['character_data'])) {
      $stored['character_data']['display_name'] = $value;
    }
  }

  private static function assign(array &$container, string $key, mixed $value): void {
    if ($value === NULL) {
      unset($container[$key]);
      return;
    }
    $container[$key] = $value;
  }

  /**
   * @return array<string, mixed>|null
   *   A typed failure when the envelope violates the command schema.
   */
  private function checkEnvelope(array $command): ?array {
    $findings = $this->schemaValidator->validate($this->schema(), $command);
    if ($findings === []) {
      return NULL;
    }

    return $this->failure($command, 'actor_contract_invalid', 'The command envelope does not match actor-command-v1.', FALSE, ['Correct the listed fields and send the command again.'], $findings);
  }

  /**
   * @return array<string, mixed>
   *   The C8 failure shape.
   */
  private function failure(array $command, string $code, string $message, bool $retryable, array $next_steps, array $findings = []): array {
    return [
      'contract' => self::RECEIPT_CONTRACT,
      'committed' => FALSE,
      'code' => $code,
      'message' => $message,
      'boundary' => 'CharacterManager::executeActorCommand',
      'command_id' => is_string($command['command_id'] ?? NULL) ? $command['command_id'] : NULL,
      'retryable' => $retryable,
      'findings' => $findings,
      'next_steps' => $next_steps,
    ];
  }

  private static function decode(?string $json): ?array {
    if ($json === NULL || trim($json) === '') {
      return NULL;
    }
    $decoded = json_decode($json, TRUE);

    return is_array($decoded) ? $decoded : NULL;
  }

  private static function get(array $document, string $path): mixed {
    $node = $document;
    foreach (explode('/', substr($path, 1)) as $segment) {
      if (!is_array($node) || !array_key_exists($segment, $node)) {
        return NULL;
      }
      $node = $node[$segment];
    }

    return $node;
  }

  private static function put(array &$document, string $path, mixed $value): void {
    $segments = explode('/', substr($path, 1));
    $leaf = array_pop($segments);
    $node = &$document;
    foreach ($segments as $segment) {
      if (!isset($node[$segment]) || !is_array($node[$segment])) {
        if ($value === NULL) {
          return;
        }
        $node[$segment] = [];
      }
      $node = &$node[$segment];
    }
    if ($value === NULL) {
      unset($node[$leaf]);
      return;
    }
    $node[$leaf] = $value;
  }

  /**
   * Key-sorted copy so equal requests hash equally.
   */
  private static function canonicalize(mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }
    if (!array_is_list($value)) {
      ksort($value);
    }

    return array_map([self::class, 'canonicalize'], $value);
  }

  private function schema(): array {
    if ($this->schema === NULL) {
      $path = dirname(__DIR__, 3) . '/config/schemas/actor_command.schema.json';
      $decoded = is_file($path) ? json_decode((string) file_get_contents($path), TRUE) : NULL;
      if (!is_array($decoded)) {
        throw new \RuntimeException('actor_schema_missing:actor_command.schema.json');
      }
      $this->schema = $decoded;
    }

    return $this->schema;
  }

}
