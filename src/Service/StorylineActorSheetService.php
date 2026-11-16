<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\dungeoncrawler_content\Service\Actor\ActorDefinitionMirror;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationException;

/**
 * Orchestrates roster sheets through the native character and draft owners.
 */
class StorylineActorSheetService {

  private const LINK_FIELDS = ['character_id', 'character_sheet_revision', 'character_sheet_spec_hash'];
  private const PREFERENCE_FIELDS = ['class', 'ancestry', 'background'];

  public function __construct(
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    private readonly StorylineAuthoringDraftService $drafts,
    private readonly CharacterCreationGmService $characters,
    private readonly CanonicalDefinitionService $definitions,
    private readonly ActorDefinitionMirror $mirror,
    private readonly CanonicalGenerationService $generation,
  ) {}

  public function inspect(string $draft_id): array {
    $draft = $this->drafts->getDraft($draft_id);
    $actors = [];
    foreach ($this->roster($draft) as $spec) {
      $sheet = $this->linkedSheet($spec);
      $actors[] = $sheet === NULL
        ? ['actor_id' => $spec['actor_id'], 'display_name' => $spec['display_name'], 'ready' => FALSE, 'character_id' => NULL]
        : $this->sheetSummary($spec['actor_id'], $sheet) + ['display_name' => $spec['display_name']];
    }
    return [
      'draft_id' => $draft_id,
      'revision' => $draft['revision'],
      'actors' => $actors,
      'ready_count' => count(array_filter($actors, static fn(array $actor): bool => $actor['ready'])),
      'actor_count' => count($actors),
      'character_creation_reference' => $this->characters->getPathfinderCharacterCreationReference(),
    ];
  }

  public function build(string $draft_id, int $expected_revision, array $actor_ids = [], array $preferences = []): array {
    $draft = $this->drafts->getDraft($draft_id);
    if ($draft['status'] !== 'editing' || $draft['revision'] !== $expected_revision) {
      throw new \RuntimeException('storyline_actor_sheet_revision_conflict:' . $draft_id, 409);
    }
    $roster = $this->roster($draft);
    $ids = $actor_ids === [] ? array_keys($roster) : $actor_ids;
    if ($ids === [] || !array_is_list($ids) || count(array_filter($ids, 'is_string')) !== count($ids) || count(array_unique($ids)) !== count($ids)) {
      throw new \InvalidArgumentException('storyline_actor_sheet_selection_invalid');
    }
    foreach ($ids as $id) {
      if (!is_string($id) || !isset($roster[$id])) {
        throw new \InvalidArgumentException('storyline_actor_sheet_actor_not_in_roster:' . json_encode($id));
      }
    }
    if (array_diff(array_keys($preferences), $ids) !== []) {
      throw new \InvalidArgumentException('storyline_actor_sheet_preferences_outside_selection');
    }
    $reference = $this->characters->getPathfinderCharacterCreationReference();
    $plans = [];
    $pending = [];
    foreach ($ids as $id) {
      $sheet = $this->linkedSheet($roster[$id]);
      $source_unchanged = ($roster[$id]['character_sheet_spec_hash'] ?? NULL) === $this->specHash($roster[$id]);
      $fixed = $sheet !== NULL && $source_unchanged ? [] : array_intersect_key($roster[$id], array_flip(self::PREFERENCE_FIELDS));
      if (array_key_exists($id, $preferences) && !is_array($preferences[$id])) {
        throw new \InvalidArgumentException('storyline_actor_sheet_preferences_invalid:' . $id);
      }
      $fixed = array_replace($fixed, $preferences[$id] ?? []);
      $this->validatePreferences($fixed, $reference);
      if ($sheet !== NULL && $this->characters->validateData($sheet['payload'], 0)['ready']) {
        $plans[$id] = ['preferences' => $fixed, 'provenance' => []];
      }
      else {
        $pending[$id] = [
          'actor' => $roster[$id],
          'fixed_preferences' => $fixed,
          'existing_sheet' => $sheet === NULL ? NULL : array_intersect_key($sheet['payload'], array_flip(['class', 'ancestry', 'background', 'level'])),
        ];
      }
    }
    foreach (array_chunk($pending, 6, TRUE) as $batch) {
      try {
        $plans += $this->planPreferences($draft, $batch, $reference);
      }
      catch (CanonicalGenerationException $exception) {
        throw new CanonicalGenerationException($exception->getMessage(), [
          ...$exception->getFindings(),
          $this->generation->finding('storyline_actor_sheet_batch_failed', '/objects/actors/generation_specs',
            'Draft ' . $draft_id . '; actors=' . implode(',', array_keys($batch)) . '; no sheets were written by this call.'),
        ], $exception->httpStatus(), $exception);
      }
    }

    $completed = [];
    foreach ($ids as $id) {
      $lock_name = 'dungeoncrawler.storyline_actor_sheet.' . hash('sha256', $id);
      if (!$this->lock->acquire($lock_name, 120.0)) {
        throw new \RuntimeException('storyline_actor_sheet_busy:' . $id . '; completed=' . implode(',', array_column($completed, 'actor_id')), 409);
      }
      $transaction = $this->database->startTransaction();
      try {
        $current = $this->drafts->getDraft($draft_id);
        $current_roster = $this->roster($current);
        if (!isset($current_roster[$id]) || $this->specHash($current_roster[$id]) !== $this->specHash($roster[$id])) {
          throw new \RuntimeException('storyline_actor_sheet_actor_changed:' . $id, 409);
        }
        $spec = $current_roster[$id];
        $sheet = $this->linkedSheet($spec);
        $reused = $sheet !== NULL;
        $source_unchanged = ($spec['character_sheet_spec_hash'] ?? NULL) === $this->specHash($spec);
        if ($sheet !== NULL && ($spec['character_sheet_spec_hash'] ?? NULL) === $this->specHash($spec)
          && ($spec['character_sheet_revision'] ?? NULL) === $sheet['revision']
          && empty($preferences[$id]) && $this->characters->validateData($sheet['payload'], 0)['ready']) {
          $completed[] = $this->sheetSummary($id, $sheet) + ['reused' => TRUE];
          continue;
        }
        if ($sheet === NULL) {
          $sheet = $this->characters->createDraft();
        }
        if (!$reused || !$source_unchanged || !empty($preferences[$id]) || !$this->characters->validateData($sheet['payload'], 0)['ready']) {
          $authored_identity = $source_unchanged ? [] : [
            'name' => $spec['display_name'],
            'concept' => $spec['role'] . ': ' . ($spec['motivation'] ?? ''),
            'personality' => $spec['attitude'] ?? '',
            'backstory' => ($spec['motivation'] ?? '') . "\n" . ($spec['knowledge'] ?? ''),
          ];
          $built = $this->characters->autofillDraft($sheet['character_id'], $plans[$id]['preferences'] + $authored_identity);
          if (empty($built['validation']['ready'])) {
            throw new \RuntimeException('storyline_actor_sheet_incomplete:' . $id . ':' . json_encode($built['validation'], JSON_THROW_ON_ERROR));
          }
          $this->definitions->attachCharacterSheet($id, $sheet['character_id'], $spec['role'], $plans[$id]['provenance']);
          $sheet = $this->characters->getDraft($sheet['character_id']);
        }
        $spec['character_id'] = $sheet['character_id'];
        $spec['display_name'] = $sheet['payload']['name'];
        $spec['character_sheet_revision'] = $sheet['revision'];
        foreach (['class', 'ancestry', 'level'] as $field) {
          $spec[$field] = $sheet['payload'][$field];
        }
        $spec['character_sheet_spec_hash'] = $this->specHash($spec);
        $payload = $current['payload'];
        foreach ($payload['objects']['actors']['generation_specs'] as &$actor) {
          if ($actor['actor_id'] === $id) {
            $actor = $spec;
          }
        }
        unset($actor);
        $this->drafts->saveDraft($draft_id, $current['revision'], $payload);
        $completed[] = $this->sheetSummary($id, $sheet) + ['reused' => $reused];
      }
      catch (\Throwable $exception) {
        $transaction->rollBack();
        throw new \RuntimeException(
          'storyline_actor_sheet_build_failed:' . $draft_id . ':' . $id
          . '; completed=' . implode(',', array_column($completed, 'actor_id'))
          . '; failed actor changes rolled back; ' . $exception->getMessage(),
          is_int($exception->getCode()) ? $exception->getCode() : 500,
          $exception,
        );
      }
      finally {
        unset($transaction);
        $this->lock->release($lock_name);
      }
    }
    return [
      'draft_id' => $draft_id,
      'revision' => $this->drafts->getDraft($draft_id)['revision'],
      'actors' => $completed,
      'completed_count' => count($completed),
      'requested_count' => count($ids),
      'assistant_response' => sprintf('%d/%d roster actors now have complete native character sheets. Existing sheets were reused; no campaign actors were changed.', count($completed), count($ids)),
      'next_steps' => [],
    ];
  }

  private function roster(array $draft): array {
    $roster = [];
    foreach ($draft['payload']['objects']['actors']['generation_specs'] ?? [] as $spec) {
      if (!is_array($spec) || empty($spec['actor_id']) || isset($roster[$spec['actor_id']])) {
        throw new \InvalidArgumentException('storyline_actor_sheet_roster_identity_invalid:' . $draft['draft_id']);
      }
      $roster[$spec['actor_id']] = $spec;
    }
    return $roster;
  }

  private function specHash(array $spec): string {
    $source = array_diff_key($spec, array_flip(self::LINK_FIELDS));
    ksort($source);
    return hash('sha256', json_encode($source, JSON_THROW_ON_ERROR));
  }

  private function linkedSheet(array $spec): ?array {
    $row = $this->mirror->libraryRow($spec['actor_id']);
    if (isset($spec['character_id']) && ($row === NULL || $row['id'] !== $spec['character_id'])) {
      throw new \InvalidArgumentException('storyline_actor_sheet_link_conflict:' . $spec['actor_id']);
    }
    if ($row === NULL) {
      if ($this->definitions->currentVersion('actor', $spec['actor_id']) !== NULL) {
        throw new \InvalidArgumentException('storyline_actor_sheet_not_native_actor:' . $spec['actor_id']);
      }
      return NULL;
    }
    $sheet = $this->characters->getDraft($row['id']);
    if ($sheet['campaign_id'] !== 0 || in_array($sheet['lifecycle_state'], ['campaign_runtime', 'campaign_draft'], TRUE)) {
      throw new \InvalidArgumentException('storyline_actor_sheet_campaign_runtime_forbidden:' . $spec['actor_id']);
    }
    if ($sheet['status'] === 2 || $sheet['lifecycle_state'] === 'archived') {
      throw new \InvalidArgumentException('storyline_actor_sheet_archived:' . $spec['actor_id']);
    }
    return $sheet;
  }

  private function sheetSummary(string $id, array $sheet): array {
    return [
      'actor_id' => $id,
      'display_name' => $sheet['payload']['name'],
      'character_id' => $sheet['character_id'],
      'character_sheet_revision' => $sheet['revision'],
      'sheet_url' => '/characters/' . $sheet['character_id'],
      'class' => $sheet['payload']['class'],
      'ancestry' => $sheet['payload']['ancestry'],
      'level' => $sheet['payload']['level'],
      'ready' => (bool) $this->characters->validateData($sheet['payload'], 0)['ready'],
    ];
  }

  private function validatePreferences(array $preferences, array $reference): void {
    if (array_diff(array_keys($preferences), self::PREFERENCE_FIELDS) !== []) {
      throw new \InvalidArgumentException('storyline_actor_sheet_preference_field_unsupported');
    }
    foreach (['class' => 'classes', 'ancestry' => 'ancestries', 'background' => 'backgrounds'] as $field => $catalog) {
      if (array_key_exists($field, $preferences) && (!is_string($preferences[$field]) || !in_array($preferences[$field], array_column($reference[$catalog], 'id'), TRUE))) {
        throw new \InvalidArgumentException('storyline_actor_sheet_native_choice_invalid:' . $field . ':' . json_encode($preferences[$field]));
      }
    }
  }

  private function planPreferences(array $draft, array $batch, array $reference): array {
    $seed = (int) hexdec(substr(hash('sha256', $draft['draft_id'] . ':' . implode(',', array_keys($batch))), 0, 7));
    $catalogs = [];
    foreach (['classes', 'ancestries', 'backgrounds'] as $catalog) {
      $catalogs[$catalog] = array_map(static fn(array $entry): array => array_intersect_key($entry, array_flip(['id', 'name'])), $reference[$catalog]);
    }
    $relationships = array_values(array_filter(
      $draft['payload']['objects']['actors']['relationships'] ?? [],
      static fn(array $relationship): bool => isset($batch[$relationship['source_actor_id']]) || isset($batch[$relationship['target_actor_id']]),
    ));
    $related_ids = array_unique([...array_column($relationships, 'source_actor_id'), ...array_column($relationships, 'target_actor_id')]);
    $neighbors = array_map(
      static fn(array $actor): array => array_intersect_key($actor, array_flip(['actor_id', 'display_name', 'role', 'faction'])),
      array_intersect_key($this->roster($draft), array_flip($related_ids)),
    );
    return $this->generation->completeJson(
      'build_storyline_actor_sheets',
      'storyline_actor_sheet_preferences',
      $seed,
      4096,
      static fn(array $findings): string => 'Create original role-driven character build preferences, not statistics or sheets. '
        . 'Return exactly one JSON object: {"actors":[{"actor_id":"grounded id","preferences":{"class":"native id","ancestry":"native id","background":"native id"}}]}. '
        . 'Include each requested actor exactly once. Use only catalog IDs. Respect every fixed_preference; preserve usable existing choices. '
        . 'Derive class and background from authored role, motivation, attitude and household; derive ancestry from authored clan/story context. '
        . 'Do not ask questions. The native wizard owns level and statistics; do not set them. Never invent game options. '
        . json_encode([
          'storyline' => array_intersect_key($draft['payload'], array_flip(['title', 'premise', 'description', 'level_range'])),
          'requested_actors' => array_values($batch),
          'actor_relationships' => $relationships,
          'related_actors' => array_values($neighbors),
          'native_catalogs' => $catalogs,
          'prior_findings' => $findings,
        ], JSON_THROW_ON_ERROR),
      function (array $output, array $provenance) use ($batch, $reference): array {
        if (array_keys($output) !== ['actors'] || !is_array($output['actors']) || !array_is_list($output['actors'])) {
          throw new CanonicalGenerationException('generation_nonconforming', [
            $this->generation->finding('actor_sheet_plan_shape_invalid', '/', 'Return one actors array and no other root fields.'),
          ]);
        }
        $plans = [];
        $errors = [];
        foreach ($output['actors'] ?? [] as $actor) {
          $id = $actor['actor_id'] ?? '';
          if (!is_string($id) || !isset($batch[$id]) || isset($plans[$id]) || !is_array($actor['preferences'] ?? NULL)) {
            $errors[] = $this->generation->finding('actor_sheet_plan_identity_invalid', '/actors', 'Use every grounded actor ID exactly once with native preferences.');
            continue;
          }
          try {
            $choices = $actor['preferences'];
            $this->validatePreferences($choices, $reference);
            if (!isset($choices['class'], $choices['ancestry'], $choices['background'])) {
              throw new \InvalidArgumentException('Provide class, ancestry and background, preserving every fixed preference.');
            }
            foreach ($batch[$id]['fixed_preferences'] as $field => $value) {
              if ($choices[$field] !== $value) {
                throw new \InvalidArgumentException('Preserve fixed preference: ' . $field);
              }
            }
            $plans[$id] = ['preferences' => $choices, 'provenance' => $provenance];
          }
          catch (\InvalidArgumentException $exception) {
            $errors[] = $this->generation->finding('actor_sheet_plan_choice_invalid', '/actors/' . $id, $exception->getMessage());
          }
        }
        if (array_diff(array_keys($batch), array_keys($plans)) !== []) {
          $errors[] = $this->generation->finding('actor_sheet_plan_incomplete', '/actors', 'Missing requested actors: ' . implode(',', array_diff(array_keys($batch), array_keys($plans))));
        }
        if ($errors !== []) {
          throw new CanonicalGenerationException('generation_nonconforming', $errors);
        }
        return $plans;
      },
    );
  }

}
