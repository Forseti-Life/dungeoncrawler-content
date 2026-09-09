<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectRef;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateEnvelope;
use Drupal\dungeoncrawler_content\Service\ObjectState\ObjectStateProviderInterface;

/**
 * Canonical current-state retrieval owner for quests (Phase 4).
 *
 * This is the single public authority that answers "what is this quest's
 * current state?". Before Phase 4 that question was answered by competing
 * paths: single-quest truth inferred from QuestTrackerService list helpers
 * (getActiveQuests / getCampaignQuestTracking / getCharacterQuestTracking),
 * campaign/character progress overlays merged inside the tracker, raw
 * `dc_campaign_quests` rows read directly by controllers, and the thin
 * QuestStateService wrapper that delegated straight back to the tracker's
 * list-inference read. Phase 4 collapses that split:
 *
 * - This owner performs DIRECT single-quest retrieval by canonical quest
 *   instance identity (campaign + quest_id) via {@see QuestStateStore}. It
 *   never loads the quest *lists* and never infers a single quest by filtering
 *   a collection.
 * - It binds the mutable runtime instance to its immutable canonical quest
 *   template through {@see CanonicalQuestTemplateService} and hard-fails on a
 *   missing/quarantined template. It never substitutes a template by quest
 *   name/slug.
 * - It merges the canonical template/definition with the single best applicable
 *   runtime progress scope using an explicit precedence contract
 *   (campaign vs character vs party). Ambiguous progress hard-fails.
 * - Every read is guarded by {@see CampaignLifecycleService}: archived/legacy
 *   campaigns hard-fail with `legacy_campaign_archived` and are never read by
 *   the current runtime.
 *
 * There is no compatibility payload, tracker-list fallback, inferred legacy
 * shape, or default authority. Missing/invalid canonical state hard-fails
 * visibly. This owner does not touch storyline generation logic.
 */
class QuestStateService implements ObjectStateProviderInterface {

  public const OBJECT_TYPE = 'quest';

  /**
   * Canonical quest authority source label.
   */
  public const AUTHORITY_SOURCE = 'campaign_tables';

  /**
   * Explicit progress-scope precedence for a character-scoped read.
   *
   * Lower rank wins. For a character read the character's own progress is
   * authoritative, then the party's shared progress (when present), then the
   * unscoped campaign progress.
   */
  private const CHARACTER_SCOPE_PRECEDENCE = ['character' => 0, 'party' => 1, 'campaign' => 2];

  /**
   * Explicit progress-scope precedence for a campaign-scoped read.
   *
   * Lower rank wins. For a campaign read the shared party progress is
   * authoritative, then any character progress, then the unscoped campaign
   * progress.
   */
  private const CAMPAIGN_SCOPE_PRECEDENCE = ['party' => 0, 'character' => 1, 'campaign' => 2];

  public function __construct(
    protected QuestStateStore $questStateStore,
    protected CanonicalQuestTemplateService $canonicalQuestTemplates,
    protected CampaignLifecycleService $campaignLifecycle,
  ) {}

  /**
   * Retrieve the canonical current quest state (hard-fail if missing/invalid).
   *
   * @param int $campaign_id
   *   Owning campaign id.
   * @param string $quest_id
   *   Canonical runtime quest id (never a name/slug).
   * @param int|null $character_id
   *   Optional character scope. When provided, the read is character-scoped and
   *   the character's own progress takes precedence; when omitted the read is
   *   campaign-scoped.
   *
   * @return array<string,mixed>
   *   Canonical quest-state: identity, canonical template ref/version, campaign,
   *   owner/scope, status, phase, objective states, progress/evidence,
   *   version/timestamps.
   *
   * @throws \InvalidArgumentException
   *   When the scope is invalid or the quest instance cannot be found.
   * @throws \OutOfBoundsException
   *   When the instance references a missing/quarantined canonical template.
   * @throws \RuntimeException
   *   Ambiguous progress scope (`quest_progress_ambiguous`), or the owning
   *   campaign is archived/noncanonical (`legacy_campaign_archived`).
   */
  public function getState(int $campaign_id, string $quest_id, ?int $character_id = NULL): array {
    $quest_id = trim($quest_id);
    if ($campaign_id <= 0 || $quest_id === '') {
      throw new \InvalidArgumentException('Quest state requires campaign_id and quest_id.');
    }
    if ($character_id !== NULL && $character_id <= 0) {
      throw new \InvalidArgumentException('Quest state character scope must be a positive character id.');
    }

    // 1) Campaign lifecycle guard: archived/legacy campaigns hard-fail before
    // any quest truth is assembled. Wrong/noncanonical campaigns are rejected.
    $this->campaignLifecycle->assertLaunchable($campaign_id);

    // 2) Direct single-quest retrieval by canonical identity (no list inference).
    $instance = $this->questStateStore->loadQuestInstanceRow($campaign_id, $quest_id);
    if ($instance === NULL) {
      throw new \InvalidArgumentException(sprintf(
        'Quest not found for campaign %d: %s',
        $campaign_id,
        $quest_id
      ));
    }

    return $this->assembleQuestState($campaign_id, $instance, $character_id);
  }

  /**
   * Assemble the canonical current-state read from a raw quest instance row.
   *
   * @param array<string,mixed> $instance
   *
   * @return array<string,mixed>
   */
  protected function assembleQuestState(int $campaign_id, array $instance, ?int $character_id): array {
    $quest_id = (string) ($instance['quest_id'] ?? '');
    $template_id = trim((string) ($instance['source_template_id'] ?? ''));
    $template_version = trim((string) ($instance['template_version'] ?? ''));

    // 3) Bind the mutable instance to its immutable canonical, non-quarantined
    // template. Missing/quarantined templates hard-fail; no name/slug fallback.
    if ($template_id === '') {
      throw new \OutOfBoundsException('quest_template_missing: instance references no source template id.');
    }
    $template = $this->canonicalQuestTemplates->requireCanonicalQuestTemplateRef(
      $template_id,
      $template_version !== '' ? $template_version : NULL
    );

    // 4) Select the single best applicable progress scope by explicit precedence.
    $progress_rows = $this->questStateStore->loadProgressRows($campaign_id, $quest_id);
    $selection = $this->selectProgressByScope($campaign_id, $quest_id, $character_id, $progress_rows);
    $progress = $selection['row'];
    $scope = $selection['scope'];

    $instance_status = strtolower(trim((string) ($instance['status'] ?? '')));
    $created_at = isset($instance['created_at']) ? (int) $instance['created_at'] : 0;

    // Objective states come from the selected progress overlay when present;
    // otherwise the not-yet-started generated objectives from the instance.
    $current_phase = 0;
    $progress_evidence = [
      'started_at' => NULL,
      'last_updated' => NULL,
      'completed_at' => NULL,
      'outcome' => NULL,
      'branch_choice' => NULL,
    ];
    $version = $created_at > 0 ? $created_at : NULL;
    $updated_at = NULL;

    if ($progress !== NULL) {
      $objective_states = $this->decodeJson($progress['objective_states'] ?? NULL);
      $current_phase = max(0, (int) ($progress['current_phase'] ?? 0));
      $progress_evidence = [
        'started_at' => $this->intOrNull($progress['started_at'] ?? NULL),
        'last_updated' => $this->intOrNull($progress['last_updated'] ?? NULL),
        'completed_at' => $this->intOrNull($progress['completed_at'] ?? NULL),
        'outcome' => $this->stringOrNull($progress['outcome'] ?? NULL),
        'branch_choice' => $this->stringOrNull($progress['branch_choice'] ?? NULL),
      ];
      $last_updated = (int) ($progress['last_updated'] ?? 0);
      $version = $last_updated > 0 ? $last_updated : $version;
      $updated_at = $last_updated > 0 ? date(DATE_ATOM, $last_updated) : NULL;
    }
    else {
      $objective_states = $this->decodeJson($instance['generated_objectives'] ?? NULL);
    }

    $status = $this->resolveStatus($instance_status, $progress_evidence);

    return [
      'quest_id' => $quest_id,
      'campaign_id' => $campaign_id,
      'template' => [
        'template_id' => $template['template_id'],
        'version' => $template['version'],
        'name' => $template['name'],
        'quest_type' => $template['quest_type'],
        'source_table' => $template['source_table'],
      ],
      'source_template_id' => $template_id,
      'template_version' => $template_version !== '' ? $template_version : $template['version'],
      'quest_name' => (string) ($instance['quest_name'] ?? $template['name']),
      'quest_type' => (string) ($instance['quest_type'] ?? $template['quest_type']),
      'scope' => $scope,
      'owner' => $this->resolveOwner($scope, $progress),
      'status' => $status,
      'instance_status' => $instance_status,
      'current_phase' => $current_phase,
      'objective_states' => $objective_states,
      'has_progress' => $progress !== NULL,
      'progress' => $progress_evidence,
      'version' => $version,
      'updated_at' => $updated_at,
      'created_at' => $created_at,
    ];
  }

  /**
   * Select the single best applicable progress row using explicit precedence.
   *
   * Character-scoped read precedence: character-owned > party (if present) >
   * unscoped campaign. Campaign-scoped read precedence: party > character >
   * unscoped campaign. Within one precedence tier the most recently updated row
   * wins; a genuine tie (equal recency at the winning tier) is ambiguous and
   * hard-fails rather than guessing.
   *
   * @param array<int,array<string,mixed>> $progress_rows
   *
   * @return array{row: array<string,mixed>|null, scope: string}
   */
  protected function selectProgressByScope(
    int $campaign_id,
    string $quest_id,
    ?int $character_id,
    array $progress_rows
  ): array {
    $default_scope = $character_id !== NULL ? 'character' : 'campaign';
    if ($progress_rows === []) {
      return ['row' => NULL, 'scope' => $default_scope];
    }

    $tracking_ids = $character_id !== NULL
      ? $this->questStateStore->resolveTrackingCharacterIds($campaign_id, $character_id)
      : [];
    $precedence = $character_id !== NULL
      ? self::CHARACTER_SCOPE_PRECEDENCE
      : self::CAMPAIGN_SCOPE_PRECEDENCE;

    $candidates = [];
    foreach ($progress_rows as $row) {
      if (!is_array($row)) {
        continue;
      }
      $row_scope = $this->classifyProgressScope($row);

      // For a character read, character-scoped progress must belong to the
      // requested character's tracking id set; foreign character progress is
      // never applicable and is skipped (never silently substituted).
      if ($character_id !== NULL && $row_scope === 'character') {
        $row_character = (int) ($row['character_id'] ?? 0);
        if (!in_array($row_character, $tracking_ids, TRUE)) {
          continue;
        }
      }

      if (!array_key_exists($row_scope, $precedence)) {
        continue;
      }

      $candidates[] = [
        'row' => $row,
        'scope' => $row_scope,
        'rank' => $precedence[$row_scope],
        'updated' => (int) ($row['last_updated'] ?? 0),
      ];
    }

    if ($candidates === []) {
      return ['row' => NULL, 'scope' => $default_scope];
    }

    // Best tier = lowest rank; within it, newest last_updated.
    usort($candidates, static function (array $a, array $b): int {
      if ($a['rank'] !== $b['rank']) {
        return $a['rank'] <=> $b['rank'];
      }
      return $b['updated'] <=> $a['updated'];
    });

    $best = $candidates[0];
    foreach (array_slice($candidates, 1) as $other) {
      if ($other['rank'] !== $best['rank']) {
        break;
      }
      if ($other['updated'] === $best['updated']) {
        throw new \RuntimeException(sprintf(
          'quest_progress_ambiguous: campaign %d quest %s has multiple equally applicable %s-scope progress rows; cannot resolve a single current state.',
          $campaign_id,
          $quest_id,
          $best['scope']
        ));
      }
    }

    return ['row' => $best['row'], 'scope' => $best['scope']];
  }

  /**
   * Classify a progress row's scope from its ownership columns.
   */
  protected function classifyProgressScope(array $row): string {
    $party_id = (int) ($row['party_id'] ?? 0);
    $character_id = (int) ($row['character_id'] ?? 0);
    if ($party_id > 0) {
      return 'party';
    }
    if ($character_id > 0) {
      return 'character';
    }
    return 'campaign';
  }

  /**
   * Resolve the owning scope reference for the selected progress row.
   *
   * @param array<string,mixed>|null $progress
   *
   * @return array{scope:string,type:string,ref:string|null}
   */
  protected function resolveOwner(string $scope, ?array $progress): array {
    if ($progress === NULL) {
      return ['scope' => $scope, 'type' => $scope, 'ref' => NULL];
    }
    if ($scope === 'party') {
      return ['scope' => 'party', 'type' => 'party', 'ref' => (string) ($progress['party_id'] ?? '')];
    }
    if ($scope === 'character') {
      return ['scope' => 'character', 'type' => 'character', 'ref' => (string) ($progress['character_id'] ?? '')];
    }
    return ['scope' => 'campaign', 'type' => 'campaign', 'ref' => NULL];
  }

  /**
   * Resolve the effective quest status from instance status + progress outcome.
   *
   * @param array<string,mixed> $progress_evidence
   */
  protected function resolveStatus(string $instance_status, array $progress_evidence): string {
    $outcome = strtolower(trim((string) ($progress_evidence['outcome'] ?? '')));
    if ($outcome !== '') {
      // Terminal outcomes are authoritative over the instance status.
      if (in_array($outcome, ['completed', 'complete', 'success'], TRUE)) {
        return 'completed';
      }
      if (in_array($outcome, ['failed', 'failure'], TRUE)) {
        return 'failed';
      }
      if (in_array($outcome, ['abandoned', 'abandon'], TRUE)) {
        return 'abandoned';
      }
    }
    if (($progress_evidence['completed_at'] ?? NULL) !== NULL) {
      return 'completed';
    }
    return $instance_status !== '' ? $instance_status : 'unknown';
  }

  /**
   * Decode a JSON array column, returning [] on any non-array value.
   *
   * @return array<int|string,mixed>
   */
  protected function decodeJson(mixed $raw): array {
    if (is_array($raw)) {
      return $raw;
    }
    $decoded = json_decode((string) ($raw ?? ''), TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Positive-int-or-null helper.
   */
  protected function intOrNull(mixed $value): ?int {
    if ($value === NULL || $value === '' || !is_numeric($value)) {
      return NULL;
    }
    $int = (int) $value;
    return $int > 0 ? $int : NULL;
  }

  /**
   * Trimmed-string-or-null helper.
   */
  protected function stringOrNull(mixed $value): ?string {
    if ($value === NULL) {
      return NULL;
    }
    $string = trim((string) $value);
    return $string !== '' ? $string : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function objectType(): string {
    return self::OBJECT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function supports(string $object_type): bool {
    return $object_type === self::OBJECT_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function getObjectState(ObjectRef $ref): ObjectStateEnvelope {
    $campaign_id = $ref->requireContextInt('campaign_id', 'Quest state requires campaign_id context.');
    $state = $this->getState($campaign_id, $ref->objectId, $ref->contextInt('character_id'));

    $template = is_array($state['template'] ?? NULL) ? $state['template'] : [];

    return ObjectStateEnvelope::create(
      self::OBJECT_TYPE,
      $ref->objectId,
      self::class,
      self::class,
      self::AUTHORITY_SOURCE,
      $state,
      isset($state['version']) ? (int) $state['version'] : NULL,
      isset($state['updated_at']) ? (string) $state['updated_at'] : NULL,
      [
        'scope' => (string) ($state['scope'] ?? 'campaign'),
        'status' => (string) ($state['status'] ?? 'unknown'),
        'current_phase' => (int) ($state['current_phase'] ?? 0),
        'has_progress' => (bool) ($state['has_progress'] ?? FALSE),
        'template_version' => (string) ($template['version'] ?? ''),
      ],
    );
  }

}
