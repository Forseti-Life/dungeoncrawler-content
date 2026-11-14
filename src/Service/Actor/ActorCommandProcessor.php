<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\Actor;

use Drupal\Component\Datetime\TimeInterface;
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
   * Stored containers that may hold a duplicate of a stored key.
   */
  private const DUPLICATE_CONTAINERS = ['basicInfo', 'wizard'];

  private ?array $schema = NULL;

  public function __construct(
    protected Connection $database,
    protected DefinitionSchemaValidator $schemaValidator,
    protected ActorDocumentConverter $converter,
    protected StateValidationService $validation,
    protected TimeInterface $time,
  ) {}

  /**
   * Validates and plans a command without writing anything.
   *
   * @return array<string, mixed>
   *   A plan, or a typed failure with committed FALSE.
   */
  public function preview(array $command, AccountInterface $principal): array {
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

      $plan = $this->plan($command, $principal, TRUE);
      if (isset($plan['failure'])) {
        return $plan['failure'];
      }
      $row = $plan['row'];
      $revision = $plan['revision'];
      $new_revision = $revision;
      $now = $this->time->getRequestTime();

      if ($plan['changed'] !== []) {
        $new_revision = $revision + 1;
        $fields = ['version' => $new_revision, 'changed' => $now, 'updated' => $now];
        foreach (['character_data', 'state_data'] as $column) {
          if ($plan['stored'][$column] !== NULL) {
            $fields[$column] = json_encode($plan['stored'][$column], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
          }
        }
        if (array_key_exists('name', $plan['columns'])) {
          $fields['name'] = $plan['columns']['name'];
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
        $document_json = json_encode($plan['candidate'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->database->insert('dc_actor_revisions')->fields([
          'actor_uuid' => $row['uuid'],
          'revision' => $new_revision,
          'parent_revision' => $revision,
          'command_id' => $command['command_id'],
          'command_type' => $command['type'],
          'principal_uid' => $uid,
          'changed_paths' => json_encode(array_column($plan['changed'], 'path'), JSON_THROW_ON_ERROR),
          'document' => $document_json,
          'content_hash' => 'sha256:' . hash('sha256', $document_json),
          'created' => $now,
        ])->execute();
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
   * Resolves, authorizes, applies and validates a command in memory.
   *
   * @return array<string, mixed>
   *   Either ['failure' => typed failure] or the planned change set.
   */
  private function plan(array $command, AccountInterface $principal, bool $envelope_checked): array {
    if (!$envelope_checked && ($envelope = $this->checkEnvelope($command)) !== NULL) {
      return ['failure' => $envelope];
    }
    $target = $command['target'];
    $row = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c')
      ->condition('uuid', $target['actor_uuid'])
      ->execute()->fetchAssoc();
    $is_admin = $principal->hasPermission('administer dungeoncrawler content') || $principal->hasPermission('edit any dungeoncrawler characters');
    $is_owner = $row && (int) $row['uid'] === (int) $principal->id() && $principal->hasPermission('edit own dungeoncrawler characters');
    // An unauthorized caller learns nothing about whether the actor exists.
    if (!$row || (!$is_admin && !$is_owner)) {
      return ['failure' => $this->failure($command, 'actor_not_found', 'No actor matches this reference for the current user.', FALSE, ['Inspect the actor again and use its exact reference.'])];
    }
    $scope = (int) $row['campaign_id'] > 0 ? 'campaign_instance' : ($row['lifecycle_state'] === 'draft_library' ? 'draft' : 'template');
    if ($scope !== $target['scope'] || ($scope === 'campaign_instance' && (int) $row['campaign_id'] !== (int) $target['campaign_id'])) {
      return ['failure' => $this->failure($command, 'actor_scope_mismatch', "This actor is a {$scope}; the command addressed a {$target['scope']}.", FALSE, ['Inspect the actor again and address its actual scope.'])];
    }
    if ((int) $row['status'] === 2) {
      return ['failure' => $this->failure($command, 'actor_precondition_failed', 'Archived actors cannot be edited. Restore the actor first.', FALSE, ['Restore the actor, then repeat the edit.'])];
    }

    $stored = ['character_data' => self::decode($row['character_data']), 'state_data' => self::decode($row['state_data'])];
    $before = $this->converter->convertRow($row, $stored['character_data'] ?? [], $stored['state_data'] ?? []);
    if ($before['issues'] !== []) {
      return ['failure' => $this->failure($command, 'actor_migration_blocked', 'This actor has stored values that do not map to the actor contract and must be repaired first.', FALSE, ['Repair the listed stored values, then repeat the edit.'], $before['issues'])];
    }

    $paths = self::PATHS[$command['type']];
    $candidate = $before['document'];
    $changed = [];
    $columns = [];
    $seen = [];
    foreach ($command['payload']['operations'] as $index => $operation) {
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
    if (!$report['valid_for_save']) {
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
        'valid_for_save' => TRUE,
        'ready_for_publication' => $report['ready_for_publication'],
        'checks' => $report['checks'],
      ],
      'next_steps' => $changed === []
        ? array_merge(['Nothing changed: the actor already has these values.'], $report['next_steps'])
        : $report['next_steps'],
    ];
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
