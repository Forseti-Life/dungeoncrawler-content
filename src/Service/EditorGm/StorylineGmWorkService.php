<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\Core\Database\Connection;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Exception\StorylineDraftValidationException;
use Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline\PlanStorylineDraftOperationsTool;

/**
 * Checkpoints bounded GM work while native draft operations own every edit.
 */
class StorylineGmWorkService {

  public const MAX_OPERATIONS = 10;
  public const MAX_PATCH_BYTES = 12000;
  public const MAX_PLAN_BYTES = 6000;
  public const MAX_STEP_FAILURES = 3;
  public const MAX_LABEL_LENGTH = 80;
  public const MAX_INSTRUCTION_LENGTH = 500;

  private readonly KeyValueStoreInterface $store;

  public function __construct(
    private readonly Connection $database,
    KeyValueFactoryInterface $keyValue,
    private readonly LockBackendInterface $lock,
    private readonly StorylineAuthoringDraftService $drafts,
    private readonly EditorGmIntentParser $parser,
  ) {
    $this->store = $keyValue->get('dungeoncrawler.storyline_gm_work');
  }

  /**
   * Builds a bounded plan without saving content or allocating a work record.
   */
  public function plan(string $draft_id, int $revision, array $steps): array {
    $draft = $this->drafts->getDraft($draft_id);
    if ($draft['revision'] !== $revision) {
      throw new \DomainException('storyline_draft_revision_conflict');
    }
    if ($draft['status'] !== 'editing') {
      throw new \DomainException('storyline_draft_not_editable');
    }
    if (!array_is_list($steps) || $steps === [] || count($steps) > 64
      || strlen(json_encode($steps, JSON_THROW_ON_ERROR)) > self::MAX_PLAN_BYTES) {
      throw new \InvalidArgumentException('storyline_work_steps_invalid');
    }
    $steps = $this->normalizeSteps($draft, $steps);
    $job_id = hash('sha256', json_encode([$draft_id, $revision, $steps], JSON_THROW_ON_ERROR));
    while (!empty($this->store->get($job_id)['cancelled'])) {
      $job_id = hash('sha256', $job_id . ':after-cancellation');
    }
    return [
      'has_changes' => TRUE,
      'step_count' => count($steps),
      'proposed_execution' => [
        'tool_name' => 'apply_storyline_work',
        'arguments' => [
          'job_id' => $job_id,
          'expected_revision' => $revision,
          'step' => 0,
          'steps' => $steps,
        ],
      ],
    ];
  }

  /**
   * Expands explicit scene targets before the model generates any operations.
   */
  private function normalizeSteps(array $draft, array $steps): array {
    $scenes = array_column($draft['payload']['process_flow']['journal_entries'] ?? [], NULL, 'scene_id');
    $units = [];
    foreach (array_values($steps) as $index => $step) {
      if (!is_array($step)) {
        throw new \InvalidArgumentException('storyline_work_step_not_object:' . $index);
      }
      $unknown = array_diff(array_keys($step), ['label', 'instruction', 'scene_id', 'scene_ids']);
      if ($unknown !== []) {
        throw new \InvalidArgumentException('storyline_work_step_unknown_field:' . $index . ':' . implode(',', $unknown));
      }
      $label = is_string($step['label'] ?? NULL) ? trim($step['label']) : '';
      $instruction = is_string($step['instruction'] ?? NULL) ? trim($step['instruction']) : '';
      if ($label === '') {
        throw new \InvalidArgumentException('storyline_work_step_label_required:' . $index);
      }
      if ($instruction === '') {
        throw new \InvalidArgumentException('storyline_work_step_instruction_required:' . $index);
      }
      if (mb_strlen($label) > self::MAX_LABEL_LENGTH) {
        throw new \InvalidArgumentException('storyline_work_step_label_too_long:' . $index . ':' . mb_strlen($label));
      }
      if (mb_strlen($instruction) > self::MAX_INSTRUCTION_LENGTH) {
        throw new \InvalidArgumentException('storyline_work_step_instruction_too_long:' . $index . ':' . mb_strlen($instruction));
      }
      $scene_id = $step['scene_id'] ?? NULL;
      $scene_ids = $step['scene_ids'] ?? NULL;
      if ($scene_id !== NULL && $scene_ids !== NULL) {
        throw new \InvalidArgumentException('storyline_work_step_scene_id_and_scene_ids:' . $index);
      }
      if (($scene_ids !== NULL && !is_array($scene_ids)) || ($scene_id !== NULL && !is_string($scene_id))) {
        throw new \InvalidArgumentException('storyline_work_step_scene_target_type_invalid:' . $index);
      }
      $targets = $scene_ids ?? [$scene_id];
      if (!is_array($targets) || !array_is_list($targets) || $targets === []
        || count(array_unique($targets, SORT_REGULAR)) !== count($targets)) {
        throw new \InvalidArgumentException('storyline_work_scene_targets_invalid');
      }
      foreach ($targets as $target) {
        if (($target === NULL && $scene_ids !== NULL)
          || ($target !== NULL && (!is_string($target) || !isset($scenes[$target])))) {
          throw new \InvalidArgumentException('storyline_work_scene_target_unknown');
        }
        $unit = ['label' => $label, 'instruction' => $instruction];
        if ($target !== NULL) {
          $unit['scene_id'] = $target;
          if ($scene_ids !== NULL) {
            $unit['label'] = mb_substr($unit['label'] . ' - ' . $scenes[$target]['title'], 0, 80);
          }
        }
        $units[] = $unit;
      }
    }
    if (count($units) > 64 || strlen(json_encode($units, JSON_THROW_ON_ERROR)) > self::MAX_PLAN_BYTES) {
      throw new \InvalidArgumentException('storyline_work_expanded_plan_too_large');
    }
    return $units;
  }

  /**
   * Exposes a saved continuation without leaking another draft's work.
   */
  public function activeWork(string $draft_id): ?array {
    $draft = $this->drafts->getDraft($draft_id);
    $id = $this->store->get('active:' . $draft_id);
    if ($id === NULL) {
      return NULL;
    }
    $job = $this->store->get($id);
    if (!is_array($job) || $job['draft_id'] !== $draft_id) {
      throw new \LogicException('storyline_work_checkpoint_invalid:' . $draft_id);
    }
    return $this->progress($job);
  }

  /**
   * Closes saved work the draft has moved past, and reports what it was.
   *
   * Saved work is pinned to the draft revision it last saved. Once the
   * draft is changed any other way, its remaining units were planned
   * against content that no longer exists and can never run, so the work
   * is closed here rather than left to block every later request. What was
   * saved stays saved.
   *
   * @return array{job_revision: int, draft_revision: int, completed: string[], not_done: string[]}|null
   *   NULL when there is no saved work, or it is still current.
   */
  public function closeSupersededWork(string $draft_id): ?array {
    $draft = $this->drafts->getDraft($draft_id);
    $id = $this->store->get('active:' . $draft_id);
    if ($id === NULL) {
      // The notice stands until the author changes the draft again.
      $notice = $this->store->get('closed:' . $draft_id);
      if (is_array($notice) && $notice['draft_revision'] !== $draft['revision']) {
        $this->store->delete('closed:' . $draft_id);
        return NULL;
      }
      return is_array($notice) ? $notice : NULL;
    }
    $lock_name = 'storyline_gm_work:' . $draft_id;
    if (!$this->lock->acquire($lock_name, 30)) {
      // A unit is being applied right now; its revision is about to move.
      return NULL;
    }
    try {
      $job = $this->store->get($id);
      if (!is_array($job) || $job['draft_id'] !== $draft_id) {
        throw new \LogicException('storyline_work_checkpoint_invalid:' . $draft_id);
      }
      if ($draft['revision'] === $job['revision']) {
        return NULL;
      }
      $job['cancelled'] = TRUE;
      $job['superseded_by_revision'] = $draft['revision'];
      $notice = [
        'job_revision' => (int) $job['revision'],
        'draft_revision' => (int) $draft['revision'],
        'completed' => array_values(array_map('strval', (array) ($job['completed_labels'] ?? []))),
        'not_done' => array_values(array_map(
          static fn(array $step): string => (string) ($step['label'] ?? ''),
          array_slice((array) $job['steps'], (int) $job['cursor']),
        )),
      ];
      $transaction = $this->database->startTransaction();
      try {
        $this->store->set($id, $job);
        $this->store->delete('active:' . $draft_id);
        $this->store->set('closed:' . $draft_id, $notice);
        $transaction->commitOrRelease();
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      return $notice;
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Executes exactly one bounded model response and one native operation batch.
   */
  public function advance(string $draft_id, string $job_id, int $expected_revision, int $step, array $steps = []): array {
    $this->drafts->getDraft($draft_id);
    if (preg_match('/^[a-f0-9]{64}$/', $job_id) !== 1 || $step < 0) {
      throw new \InvalidArgumentException('storyline_work_request_invalid');
    }
    $lock_name = 'storyline_gm_work:' . $draft_id;
    if (!$this->lock->acquire($lock_name, 180)) {
      throw new \DomainException('storyline_work_already_running:' . $draft_id);
    }
    try {
      $job = $this->store->get($job_id);
      if ($job === NULL) {
        $plan = $this->plan($draft_id, $expected_revision, $steps);
        if ($step !== 0 || $plan['proposed_execution']['arguments']['job_id'] !== $job_id) {
          throw new \DomainException('storyline_work_plan_conflict');
        }
        $active = $this->store->get('active:' . $draft_id);
        if ($active !== NULL && $active !== $job_id) {
          throw new \DomainException('storyline_work_unfinished:' . $draft_id);
        }
        $job = [
          'job_id' => $job_id,
          'draft_id' => $draft_id,
          'revision' => $expected_revision,
          'steps' => $plan['proposed_execution']['arguments']['steps'],
          'cursor' => 0,
          'pending_operations' => NULL,
          'step_failures' => 0,
          'completed_labels' => [],
          'skipped_labels' => [],
          'open_error_hashes' => $this->errorHashes($this->validationErrors($this->drafts->validateDraft($draft_id, 'editing'))),
          'last_outcome' => NULL,
          'total_cleared_errors' => 0,
          'total_introduced_errors' => 0,
        ];
        $transaction = $this->database->startTransaction();
        try {
          $this->store->set($job_id, $job);
          $this->store->set('active:' . $draft_id, $job_id);
          $transaction->commitOrRelease();
        }
        catch (\Throwable $exception) {
          $transaction->rollBack();
          throw $exception;
        }
      }
      if (!is_array($job) || $job['draft_id'] !== $draft_id
        || ($steps !== [] && $steps !== $job['steps']) || $step > $job['cursor']) {
        throw new \DomainException('storyline_work_plan_conflict');
      }
      if (!empty($job['cancelled'])) {
        throw new \DomainException('storyline_work_cancelled:' . $job_id);
      }
      $draft = $this->drafts->getDraft($draft_id);
      if ($draft['revision'] !== $job['revision']) {
        throw new \DomainException('storyline_work_revision_conflict:' . $draft_id);
      }
      if ($step < $job['cursor'] || $job['cursor'] === count($job['steps'])) {
        return $this->result($job, $draft, TRUE);
      }
      if ($draft['status'] !== 'editing' || $expected_revision !== $job['revision']) {
        throw new \DomainException('storyline_work_revision_conflict:' . $draft_id);
      }
      if ($job['pending_operations'] === NULL && isset($job['last_failure'])
        && (int) ($job['step_failures'] ?? 0) >= self::MAX_STEP_FAILURES) {
        throw new \DomainException('storyline_work_unit_attempts_exhausted:' . $job['cursor']);
      }
      if ($job['pending_operations'] === NULL) {
        $operations = [];
        try {
          $operations = $this->planStep($draft, $job);
          $plan = $this->drafts->planDraftOperations($draft_id, $job['revision'], $operations);
          if (isset($job['steps'][$job['cursor']]['scene_id'])) {
            $this->assertSceneScope($draft['payload'], $plan['payload'], $job['steps'][$job['cursor']]['scene_id']);
          }
        }
        catch (\DomainException | \InvalidArgumentException $exception) {
          $findings = $exception instanceof StorylineDraftValidationException ? $exception->findings : [];
          if ($findings === [] && str_starts_with($exception->getMessage(), 'storyline_draft_operation_path_not_found')) {
            $paths = array_values(array_filter(array_map(static fn(array $operation): string => (string) ($operation['path'] ?? ''), $operations)));
            $findings = [[
              'code' => 'operation_path_not_found',
              'pointer' => '/operations',
              'message' => $paths === []
                ? 'One or more operation paths do not exist in the current draft payload. Paths must be rooted at /player_hook, /ending, /process_flow/... or /objects/....'
                : sprintf('Operation path not found in the current draft payload: %s. Paths must be rooted at /player_hook, /ending, /process_flow/... or /objects/....', implode(', ', $paths)),
            ]];
          }
          if ($findings === [] && str_starts_with($exception->getMessage(), 'storyline_draft_batch_key_invalid_or_duplicate')) {
            $keys = array_values(array_filter(array_map(static fn(array $operation): string => (string) ($operation['key'] ?? ''), $operations)));
            $duplicates = array_values(array_unique(array_keys(array_filter(array_count_values(array_filter($keys, static fn(string $key): bool => $key !== '')), static fn(int $count): bool => $count > 1))));
            $findings = [[
              'code' => 'operation_batch_key_invalid_or_duplicate',
              'pointer' => '/operations',
              'message' => $duplicates === []
                ? 'Append operation keys must be valid and unique within one batch when using $draft_ref links.'
                : sprintf('Duplicate append keys in this batch: %s. Every append key must be unique within one operation batch when using $draft_ref links.', implode(', ', $duplicates)),
            ]];
          }
          if ($findings === [] && str_starts_with($exception->getMessage(), 'storyline_draft_operation_match_count_invalid')) {
            $parts = explode(':', $exception->getMessage());
            $op_index = isset($parts[1]) ? max(0, (int) $parts[1]) : -1;
            $matched_count = isset($parts[2]) ? (int) $parts[2] : 0;
            $op = ($op_index >= 0 && isset($operations[$op_index]) && is_array($operations[$op_index])) ? $operations[$op_index] : [];
            $path = (string) ($op['path'] ?? '/unknown');
            $match = is_array($op['match'] ?? NULL) ? $op['match'] : [];
            $match_label = isset($match['field'], $match['value'])
              ? sprintf('%s=%s', (string) $match['field'], json_encode($match['value']))
              : json_encode($match, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $findings = [[
              'code' => 'operation_match_not_unique',
              'pointer' => '/operations',
              'message' => sprintf('Operation match must resolve exactly one row, but matched %d rows at %s using %s. Use a unique match.fields selector or resolve duplicate rows first.', $matched_count, $path, $match_label),
            ]];
          }
          if ($findings === [] && str_starts_with($exception->getMessage(), 'storyline_work_step_requires_bounded_patch')) {
            $findings = [[
              'code' => 'work_step_not_patchable',
              'pointer' => '/bounded_work_step',
              'message' => 'This unit did not produce draft operations. If it targets canonical content, stop or skip it and use remediation instead.',
            ]];
          }
          $this->renewLock($lock_name);
          $job['step_failures'] = ((int) ($job['step_failures'] ?? 0)) + 1;
          $job['last_failure'] = [
            'error' => $exception->getMessage(),
            'step' => $job['cursor'],
            'label' => (string) ($job['steps'][$job['cursor']]['label'] ?? ''),
            'findings' => $findings,
            'rejected_operations' => $operations,
          ];
          $this->store->set($job_id, $job);
          throw $exception;
        }
        $this->renewLock($lock_name);
        $job['pending_operations'] = $operations;
        unset($job['last_failure']);
        $this->store->set($job_id, $job);
      }
      $transaction = $this->database->startTransaction();
      try {
        $receipt = $this->drafts->applyDraftOperations(
          $draft_id,
          $job['revision'],
          'work-' . substr($job_id, 0, 48) . '-' . $job['cursor'],
          $job['pending_operations'],
        );
        $job['completed_labels'][] = $job['steps'][$job['cursor']]['label'];
        $job['cursor']++;
        $job['revision'] = $receipt['saved']['revision'];
        $job['pending_operations'] = NULL;
        $job['step_failures'] = 0;
        unset($job['last_failure']);
        $validation = $this->drafts->validateDraft($draft_id, 'editing');
        $error_messages = $this->validationErrors($validation);
        $new_hashes = $this->errorHashes($error_messages);
        $previous_hashes = array_fill_keys(array_values(array_filter((array) ($job['open_error_hashes'] ?? []), 'is_string')), TRUE);
        $current_hashes = array_fill_keys($new_hashes, TRUE);
        $cleared = 0;
        foreach ($previous_hashes as $hash => $present) {
          if (!isset($current_hashes[$hash])) {
            $cleared++;
          }
        }
        $introduced_messages = [];
        $introduced_count = 0;
        foreach ($error_messages as $message) {
          $hash = sha1($message);
          if (!isset($previous_hashes[$hash])) {
            $introduced_count++;
            if (count($introduced_messages) < 5) {
              $introduced_messages[] = $message;
            }
          }
        }
        $job['open_error_hashes'] = $new_hashes;
        $job['last_outcome'] = [
          'cleared' => $cleared,
          'introduced' => $introduced_messages,
          'introduced_count' => $introduced_count,
          'remaining' => count($new_hashes),
        ];
        $job['total_cleared_errors'] = ((int) ($job['total_cleared_errors'] ?? 0)) + $cleared;
        $job['total_introduced_errors'] = ((int) ($job['total_introduced_errors'] ?? 0)) + $introduced_count;
        $this->store->set($job_id, $job);
        if ($job['cursor'] === count($job['steps'])) {
          $this->store->delete('active:' . $draft_id);
        }
        $transaction->commitOrRelease();
        return $this->result($job, $receipt['saved'], FALSE, $receipt['changed']);
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Preserves all content outside a scene-scoped unit's native narrative rows.
   */
  private function assertSceneScope(array $before, array $after, string $scene_id): void {
    $task_ids = [];
    foreach ([$before, $after] as $payload) {
      foreach ($payload['process_flow']['tasks'] ?? [] as $task) {
        if ($task['scene_id'] === $scene_id) {
          $task_ids[] = $task['task_id'];
        }
      }
    }
    $project = static function (array $payload) use ($scene_id, $task_ids): array {
      foreach (['journal_entries', 'tasks'] as $collection) {
        $payload['process_flow'][$collection] = array_values(array_filter(
          $payload['process_flow'][$collection] ?? [],
          static fn(array $row): bool => $row['scene_id'] !== $scene_id,
        ));
      }
      $payload['process_flow']['branch_choices'] = array_values(array_filter(
        $payload['process_flow']['branch_choices'] ?? [],
        static fn(array $choice): bool => !in_array($choice['source_task_id'], $task_ids, TRUE),
      ));
      return $payload;
    };
    if ($project($before) !== $project($after)) {
      $before_rows = [
        'journal_entries' => array_column($before['process_flow']['journal_entries'] ?? [], 'scene_id'),
        'tasks' => array_column($before['process_flow']['tasks'] ?? [], 'task_id'),
        'branch_choices' => array_column($before['process_flow']['branch_choices'] ?? [], 'choice_id'),
      ];
      $after_rows = [
        'journal_entries' => array_column($after['process_flow']['journal_entries'] ?? [], 'scene_id'),
        'tasks' => array_column($after['process_flow']['tasks'] ?? [], 'task_id'),
        'branch_choices' => array_column($after['process_flow']['branch_choices'] ?? [], 'choice_id'),
      ];
      $changed_rows = [];
      foreach ($before_rows as $collection => $ids) {
        $delta = array_values(array_unique(array_merge(
          array_diff($ids, $after_rows[$collection] ?? []),
          array_diff($after_rows[$collection] ?? [], $ids),
        )));
        if ($delta !== []) {
          $changed_rows[$collection] = $delta;
        }
      }
      throw new StorylineDraftValidationException('storyline_work_patch_outside_scene_scope:' . $scene_id, [[
        'code' => 'scene_scope_violation',
        'pointer' => '/process_flow',
        'message' => "This scene-scoped unit may edit only the current scene and must source new branches from that scene's quests, not from a destination quest. Link newly appended source quests by append key using \$draft_ref. Unrelated scene and shared content must remain unchanged.",
        'rows' => $changed_rows,
      ]]);
    }
    if ($before === $after) {
      throw new StorylineDraftValidationException('storyline_work_patch_scene_noop:' . $scene_id, [[
        'code' => 'scene_scope_noop',
        'pointer' => '/process_flow',
        'message' => 'The proposed scene-scoped patch changed nothing in the current scene. Update scene-local tasks/choices or append a local source quest before branching.',
      ]]);
    }
  }

  /**
   * Never write an old checkpoint after a slow model call lost its lease.
   */
  private function renewLock(string $name): void {
    if (!$this->lock->acquire($name, 180)) {
      throw new \DomainException('storyline_work_lock_lost:' . $name);
    }
  }

  /**
   * Explicitly stops unfinished work without undoing any saved draft edits.
   */
  public function cancel(string $draft_id, string $job_id, int $revision, int $step): array {
    $draft = $this->drafts->getDraft($draft_id);
    $lock_name = 'storyline_gm_work:' . $draft_id;
    if (!$this->lock->acquire($lock_name, 180)) {
      throw new \DomainException('storyline_work_already_running:' . $draft_id);
    }
    try {
      $job = $this->store->get($job_id);
      if (!is_array($job) || $job['draft_id'] !== $draft_id
        || $job['revision'] !== $revision || $job['cursor'] !== $step) {
        throw new \DomainException('storyline_work_plan_conflict');
      }
      if (!empty($job['cancelled'])) {
        return $this->result($job, $draft, TRUE);
      }
      if ($this->store->get('active:' . $draft_id) !== $job_id) {
        throw new \DomainException('storyline_work_not_active:' . $draft_id);
      }
      $job['cancelled'] = TRUE;
      $transaction = $this->database->startTransaction();
      try {
        $this->store->set($job_id, $job);
        $this->store->delete('active:' . $draft_id);
        $transaction->commitOrRelease();
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      return $this->result($job, $draft, FALSE);
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Skips one paused unit without discarding prior saved edits.
   */
  public function skip(string $draft_id, string $job_id, int $revision, int $step): array {
    $draft = $this->drafts->getDraft($draft_id);
    $lock_name = 'storyline_gm_work:' . $draft_id;
    if (!$this->lock->acquire($lock_name, 180)) {
      throw new \DomainException('storyline_work_already_running:' . $draft_id);
    }
    try {
      $job = $this->store->get($job_id);
      if (!is_array($job) || $job['draft_id'] !== $draft_id
        || $job['revision'] !== $revision || $job['cursor'] !== $step) {
        throw new \DomainException('storyline_work_plan_conflict');
      }
      if (!isset($job['last_failure']) || $job['pending_operations'] !== NULL) {
        throw new \DomainException('storyline_work_skip_not_paused:' . $draft_id);
      }
      if (!empty($job['cancelled'])) {
        return $this->result($job, $draft, TRUE);
      }
      if ($this->store->get('active:' . $draft_id) !== $job_id) {
        throw new \DomainException('storyline_work_not_active:' . $draft_id);
      }
      $job['skipped_labels'][] = (string) ($job['steps'][$job['cursor']]['label'] ?? '');
      $job['cursor']++;
      $job['step_failures'] = 0;
      $job['pending_operations'] = NULL;
      unset($job['last_failure']);
      $transaction = $this->database->startTransaction();
      try {
        $this->store->set($job_id, $job);
        if ($job['cursor'] === count($job['steps'])) {
          $this->store->delete('active:' . $draft_id);
        }
        $transaction->commitOrRelease();
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw $exception;
      }
      return $this->result($job, $draft, FALSE);
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Uses the same intent parser and native planner with a bounded step contract.
   */
  private function planStep(array $draft, array $job): array {
    $payload = $draft['payload'];
    unset($payload['canonical_template']);
    $registry = new EditorGmToolRegistry([new PlanStorylineDraftOperationsTool()], [], NULL);
    $scene_id = $job['steps'][$job['cursor']]['scene_id'] ?? NULL;
    $scenes = array_column($payload['process_flow']['journal_entries'] ?? [], NULL, 'scene_id');
    if ($scene_id !== NULL && !isset($scenes[$scene_id])) {
      throw new \DomainException('storyline_work_scene_scope_missing:' . $scene_id);
    }
    $intent = $this->parser->parse(
      $job['steps'][$job['cursor']]['instruction'],
      [
        'draft' => ['draft_id' => $draft['draft_id'], 'revision' => $draft['revision'], 'status' => $draft['status']],
        'storyline' => $payload,
        'bounded_work_step' => [
          'current' => $job['cursor'] + 1,
          'total' => count($job['steps']),
          'plan' => $job['steps'],
          'completed' => $job['completed_labels'],
          'max_operations' => self::MAX_OPERATIONS,
          'max_patch_bytes' => self::MAX_PATCH_BYTES,
          'previous_rejected_attempt' => $job['last_failure'] ?? NULL,
          'current_scene' => $scene_id === NULL ? NULL : $scenes[$scene_id],
          'current_scene_task_ids' => $scene_id === NULL ? [] : array_values(array_column(array_filter(
            $payload['process_flow']['tasks'] ?? [],
            static fn(array $task): bool => $task['scene_id'] === $scene_id,
          ), 'task_id')),
        ],
        'authoring_workflow' => [
          'identity_contract' => StorylineAuthoringDraftService::identityAuthoringContract(),
          'collection_contracts' => StorylineAuthoringDraftService::collectionAuthoringContract(),
          'branching_contract' => StorylineAuthoringDraftService::branchAuthoringContract(),
        ],
      ],
      $registry,
      'Storyline Builder',
    );
    if (($intent['type'] ?? '') !== 'tool_call' || ($intent['tool_name'] ?? '') !== 'plan_storyline_draft_operations') {
      throw new \DomainException('storyline_work_step_requires_bounded_patch:' . $job['cursor']);
    }
    if (($intent['arguments']['expected_revision'] ?? NULL) !== $draft['revision']) {
      throw new \DomainException('storyline_work_step_revision_invalid:' . $job['cursor']);
    }
    $operations = $intent['arguments']['operations'] ?? NULL;
    if (!is_array($operations) || !array_is_list($operations) || $operations === []
      || count($operations) > self::MAX_OPERATIONS || strlen(json_encode($operations, JSON_THROW_ON_ERROR)) > self::MAX_PATCH_BYTES) {
      throw new \DomainException('storyline_work_step_patch_too_large:' . $job['cursor']);
    }
    return $operations;
  }

  private function progress(array $job): array {
    $status = !empty($job['cancelled'])
      ? 'cancelled'
      : ($job['cursor'] === count($job['steps'])
        ? 'completed'
        : (isset($job['last_failure']) && $job['pending_operations'] === NULL ? 'paused' : 'running'));
    $actions = match (TRUE) {
      in_array($status, ['cancelled', 'completed'], TRUE) => [],
      $status === 'paused' && ((int) ($job['step_failures'] ?? 0) >= self::MAX_STEP_FAILURES) => ['skip', 'cancel'],
      $status === 'paused' => ['resume', 'skip', 'cancel'],
      default => ['advance', 'cancel'],
    };
    $failure = NULL;
    if (isset($job['last_failure'])) {
      $failure = [
        'error' => (string) ($job['last_failure']['error'] ?? 'storyline_work_failed'),
        'label' => (string) ($job['last_failure']['label'] ?? ''),
        'findings' => array_slice(array_values(array_filter((array) ($job['last_failure']['findings'] ?? []), 'is_array')), 0, 5),
      ];
    }
    return [
      'job_id' => $job['job_id'],
      'expected_revision' => $job['revision'],
      'step' => $job['cursor'],
      'total_steps' => count($job['steps']),
      'status' => $status,
      'allowed_actions' => $actions,
      'failed_attempts' => (int) ($job['step_failures'] ?? 0),
      'max_failed_attempts' => self::MAX_STEP_FAILURES,
      'failure' => $failure,
      'completed_labels' => $job['completed_labels'],
      'skipped_labels' => array_values(array_filter((array) ($job['skipped_labels'] ?? []), 'is_string')),
      'last_outcome' => is_array($job['last_outcome'] ?? NULL) ? $job['last_outcome'] : NULL,
      'total_cleared_errors' => (int) ($job['total_cleared_errors'] ?? 0),
      'total_introduced_errors' => (int) ($job['total_introduced_errors'] ?? 0),
    ];
  }

  private function result(array $job, array $draft, bool $replay, bool $changed = FALSE): array {
    return [
      'saved' => $draft,
      'work_progress' => $this->progress($job, FALSE),
      'idempotent_replay' => $replay,
      'changed' => $changed,
    ];
  }

  /**
   * @return array<int, string>
   */
  private function validationErrors(array $validation): array {
    return array_values(array_filter(
      array_map('strval', (array) ($validation['errors'] ?? [])),
      static fn(string $error): bool => trim($error) !== '',
    ));
  }

  /**
   * @param array<int, string> $errors
   *
   * @return array<int, string>
   */
  private function errorHashes(array $errors): array {
    return array_values(array_unique(array_map(
      static fn(string $error): string => sha1($error),
      $errors,
    )));
  }

}
