<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Owns campaign-scoped institution->institution disposition matrix edges.
 */
class InstitutionDispositionMatrixService {

  protected const MATRIX_DOMAINS = ['ancestry', 'profession'];
  protected const EDGE_KIND = 'institution_disposition_matrix';
  protected const RELATIONSHIP_TYPE = 'institution_disposition';
  protected const AUTHORITY_SCOPE = 'institution_matrix';
  protected const DEFAULT_KNOWLEDGE_STATE = 'known';
  protected const DEFAULT_SEED_SOURCE = 'institution_matrix_default';
  protected const DEFAULT_SEED_PROFILE_KEY = 'known-neutral-default';
  protected const DEFAULT_SCORE = 0;
  protected const UNIVERSAL_UNDEAD_BIAS_SCORE = -200;
  protected const UNIVERSAL_UNDEAD_TARGET_LABEL = 'undead';

  /**
   * Default profession-target sentiment priors keyed by normalized target label.
   */
  protected const PROFESSION_TARGET_BIAS = [
    'rogue' => -5,
    'witch' => -5,
    'cleric' => 5,
    'bard' => 5,
  ];

  /**
   * Directed ancestry priors keyed by normalized source->target labels.
   *
   * @var array<string,array<string,int>>
   */
  protected const ANCESTRY_DIRECTED_BIAS = [
    'human' => ['halfling' => 5, 'half-elf' => 5, 'goblin' => -5, 'orc' => -5],
    'elf' => ['leshy' => 5, 'half-elf' => 5, 'dwarf' => -5, 'goblin' => -5, 'orc' => -5, 'kobold' => -5],
    'dwarf' => ['human' => 5, 'halfling' => 5, 'elf' => -5, 'goblin' => -5, 'orc' => -5, 'kobold' => -5],
    'gnome' => ['halfling' => 5, 'leshy' => 5, 'goblin' => 5, 'orc' => -5],
    'goblin' => ['kobold' => 5, 'ratfolk' => 5, 'human' => -5, 'elf' => -5, 'dwarf' => -5, 'halfling' => -5],
    'halfling' => ['human' => 5, 'gnome' => 5, 'dwarf' => 5, 'goblin' => -5, 'orc' => -5],
    'half-elf' => ['human' => 5, 'elf' => 5, 'goblin' => -5, 'orc' => -5],
    'half-orc' => ['human' => 5, 'orc' => 5, 'elf' => -5, 'dwarf' => -5, 'halfling' => -5],
    'leshy' => ['elf' => 5, 'gnome' => 5, 'catfolk' => 5, 'goblin' => -5],
    'orc' => ['half-orc' => 5, 'human' => 5, 'elf' => -5, 'dwarf' => -5, 'goblin' => -5, 'halfling' => -5],
    'catfolk' => ['tengu' => 5, 'halfling' => 5, 'ratfolk' => -5, 'kobold' => -5],
    'kobold' => ['goblin' => 5, 'ratfolk' => 5, 'elf' => -5, 'dwarf' => -5, 'catfolk' => -5],
    'ratfolk' => ['goblin' => 5, 'kobold' => 5, 'tengu' => 5, 'catfolk' => -5],
    'tengu' => ['catfolk' => 5, 'ratfolk' => 5, 'human' => 5, 'orc' => -5],
  ];

  public function __construct(
    protected readonly Connection $database,
    protected readonly RelationshipManagerService $relationshipManager,
  ) {}

  /**
   * Load one directed institution disposition edge.
   *
   * @return array<string,mixed>|null
   *   Existing relationship row with decoded state, or NULL if missing.
   */
  public function loadInstitutionDispositionEdge(
    int $campaign_id,
    string $source_subject_id,
    string $target_subject_id
  ): ?array {
    $source_subject_id = trim($source_subject_id);
    $target_subject_id = trim($target_subject_id);
    if (
      $campaign_id <= 0
      || $source_subject_id === ''
      || $target_subject_id === ''
      || !$this->relationshipManager->isRelationshipStorageReady()
    ) {
      return NULL;
    }

    $relationship_id = $this->composeRelationshipId($source_subject_id, $target_subject_id);
    $row = $this->database->select('dc_campaign_relationships', 'r')
      ->fields('r')
      ->condition('campaign_id', $campaign_id)
      ->condition('relationship_id', $relationship_id)
      ->condition('source_type', 'institution')
      ->condition('target_type', 'institution')
      ->condition('relationship_type', self::RELATIONSHIP_TYPE)
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!is_array($row) || $row === []) {
      return NULL;
    }

    $row['relationship_state'] = $this->decodeJsonColumn($row['relationship_state'] ?? NULL);
    return $row;
  }

  /**
   * Upsert one directed institution disposition edge with explicit score.
   */
  public function upsertInstitutionDispositionEdge(
    int $campaign_id,
    string $source_subject_id,
    string $target_subject_id,
    int $score,
    array $context = []
  ): int {
    $source_subject_id = trim($source_subject_id);
    $target_subject_id = trim($target_subject_id);
    if (
      $campaign_id <= 0
      || $source_subject_id === ''
      || $target_subject_id === ''
      || !$this->relationshipManager->isRelationshipStorageReady()
    ) {
      return 0;
    }

    $score = DispositionAuthorityContract::clampScore($score);
    $mutated = (bool) ($context['mutated'] ?? FALSE);
    $now = time();
    $seed_source = trim((string) ($context['seed_source'] ?? self::DEFAULT_SEED_SOURCE));
    $seed_profile_key = trim((string) ($context['seed_profile_key'] ?? self::DEFAULT_SEED_PROFILE_KEY));
    $knowledge_state = $this->normalizeKnowledgeState((string) ($context['knowledge_state'] ?? self::DEFAULT_KNOWLEDGE_STATE));
    $rationale = trim((string) ($context['rationale'] ?? ''));
    $mutated_by_uid = isset($context['mutated_by_uid']) && is_numeric($context['mutated_by_uid'])
      ? (int) $context['mutated_by_uid']
      : 0;
    $existing = $this->loadInstitutionDispositionEdge($campaign_id, $source_subject_id, $target_subject_id);
    $existing_state = is_array($existing['relationship_state'] ?? NULL) ? $existing['relationship_state'] : [];
    $mutation_count = $mutated ? ((int) ($existing_state['mutation_count'] ?? 0) + 1) : (int) ($existing_state['mutation_count'] ?? 0);

    return $this->relationshipManager->upsertRuntimeRelationship($campaign_id, [
      'relationship_id' => $this->composeRelationshipId($source_subject_id, $target_subject_id),
      'source_type' => 'institution',
      'source_id' => $source_subject_id,
      'target_type' => 'institution',
      'target_id' => $target_subject_id,
      'relationship_type' => self::RELATIONSHIP_TYPE,
      'attitude' => DispositionAuthorityContract::scoreToAttitude($score),
      'status' => $knowledge_state,
      'relationship_state' => [
        'edge_kind' => self::EDGE_KIND,
        'authority_scope' => self::AUTHORITY_SCOPE,
        'score' => $score,
        'knowledge_state' => $knowledge_state,
        'seed_source' => $seed_source,
        'seed_profile_key' => $seed_profile_key,
        'matrix_state' => $mutated ? 'mutated' : 'defaulted',
        'mutation_state' => $mutated ? 'mutated' : 'seeded',
        'mutation_count' => $mutation_count,
        'rationale' => $rationale,
        'touched_at' => $mutated ? $now : (int) ($existing_state['touched_at'] ?? 0),
        'mutated_by_uid' => $mutated ? $mutated_by_uid : (int) ($existing_state['mutated_by_uid'] ?? 0),
      ],
    ]);
  }

  /**
   * Ensure one neutral default matrix edge exists.
   */
  public function ensureDefaultInstitutionDispositionEdge(
    int $campaign_id,
    string $source_subject_id,
    string $target_subject_id
  ): int {
    return $this->ensureSeededPolicyInstitutionDispositionEdge(
      $campaign_id,
      $source_subject_id,
      $target_subject_id,
      self::DEFAULT_SCORE,
      self::DEFAULT_SEED_PROFILE_KEY
    );
  }

  /**
   * Ensure one seeded policy/default matrix edge exists without overriding mutations.
   */
  protected function ensureSeededPolicyInstitutionDispositionEdge(
    int $campaign_id,
    string $source_subject_id,
    string $target_subject_id,
    int $score,
    string $seed_profile_key
  ): int {
    $existing = $this->loadInstitutionDispositionEdge($campaign_id, $source_subject_id, $target_subject_id);
    if ($existing !== NULL) {
      $state = is_array($existing['relationship_state'] ?? NULL) ? $existing['relationship_state'] : [];
      if (($state['mutation_state'] ?? 'seeded') === 'mutated' || ($state['matrix_state'] ?? 'defaulted') === 'mutated') {
        return 0;
      }
    }

    return $this->upsertInstitutionDispositionEdge(
      $campaign_id,
      $source_subject_id,
      $target_subject_id,
      $score,
      [
        'mutated' => FALSE,
        'seed_source' => self::DEFAULT_SEED_SOURCE,
        'seed_profile_key' => trim($seed_profile_key) !== '' ? $seed_profile_key : self::DEFAULT_SEED_PROFILE_KEY,
        'knowledge_state' => self::DEFAULT_KNOWLEDGE_STATE,
      ]
    );
  }

  /**
   * Seed default policy matrix edges for one campaign.
   *
   * @return array<string,int>
   *   Summary counts.
   */
  public function seedNeutralDefaultsForCampaign(int $campaign_id): array {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    if (!$this->relationshipManager->isRelationshipStorageReady()) {
      throw new \RuntimeException('Campaign relationship storage is not installed.');
    }

    $rows = $this->loadInstitutionSubjectsByDomains($campaign_id, self::MATRIX_DOMAINS);
    $ancestries = [];
    $professions = [];
    foreach ($rows as $row) {
      $subject_id = trim((string) ($row['subject_id'] ?? ''));
      $subject_domain = trim((string) ($row['subject_domain'] ?? ''));
      $display_name = trim((string) ($row['display_name'] ?? ''));
      if ($subject_id === '' || $subject_domain === '') {
        continue;
      }
      $subject = [
        'subject_id' => $subject_id,
        'display_name' => $display_name,
        'normalized_label' => $this->normalizeLabel($display_name),
      ];
      if ($subject_domain === 'ancestry') {
        $ancestries[$subject_id] = $subject;
      }
      elseif ($subject_domain === 'profession') {
        $professions[$subject_id] = $subject;
      }
    }

    $seeded = 0;
    foreach ($ancestries as $ancestry) {
      foreach ($professions as $profession) {
        $seeded += $this->ensureSeededPolicyInstitutionDispositionEdge(
          $campaign_id,
          (string) $ancestry['subject_id'],
          (string) $profession['subject_id'],
          0,
          self::DEFAULT_SEED_PROFILE_KEY
        );
        $seeded += $this->ensureSeededPolicyInstitutionDispositionEdge(
          $campaign_id,
          (string) $profession['subject_id'],
          (string) $ancestry['subject_id'],
          0,
          self::DEFAULT_SEED_PROFILE_KEY
        );
      }
    }

    foreach ($professions as $source_profession) {
      foreach ($professions as $target_profession) {
        $score = $this->resolveProfessionTargetBias((string) $target_profession['normalized_label']);
        $seeded += $this->ensureSeededPolicyInstitutionDispositionEdge(
          $campaign_id,
          (string) $source_profession['subject_id'],
          (string) $target_profession['subject_id'],
          $score,
          $score === 0 ? self::DEFAULT_SEED_PROFILE_KEY : 'profession-target-bias-default'
        );
      }
    }

    foreach ($ancestries as $source_ancestry) {
      foreach ($ancestries as $target_ancestry) {
        $score = $this->resolveAncestryDirectedBias(
          (string) $source_ancestry['normalized_label'],
          (string) $target_ancestry['normalized_label']
        );
        $seed_profile_key = self::DEFAULT_SEED_PROFILE_KEY;
        if ($score === self::UNIVERSAL_UNDEAD_BIAS_SCORE) {
          $seed_profile_key = 'ancestry-undead-bias-default';
        }
        elseif ($score !== 0) {
          $seed_profile_key = 'ancestry-directed-bias-default';
        }

        $seeded += $this->ensureSeededPolicyInstitutionDispositionEdge(
          $campaign_id,
          (string) $source_ancestry['subject_id'],
          (string) $target_ancestry['subject_id'],
          $score,
          $seed_profile_key
        );
      }
    }

    return [
      'campaign_id' => $campaign_id,
      'subjects_considered' => count($rows),
      'neutral_edges_seeded' => $seeded,
    ];
  }

  /**
   * Mutate one directed matrix edge.
   */
  public function mutateInstitutionDispositionEdge(
    int $campaign_id,
    string $source_subject_id,
    string $target_subject_id,
    int $score,
    array $context = []
  ): int {
    return $this->upsertInstitutionDispositionEdge(
      $campaign_id,
      $source_subject_id,
      $target_subject_id,
      $score,
      $context + ['mutated' => TRUE]
    );
  }

  /**
   * Load institution registry rows for a campaign/domain filter.
   *
   * @param array<int,string> $domains
   *   Target institution domains.
   *
   * @return array<int,array<string,mixed>>
   *   Matching institution rows.
   */
  protected function loadInstitutionSubjectsByDomains(int $campaign_id, array $domains): array {
    if ($campaign_id <= 0 || $domains === []) {
      return [];
    }

    $normalized_domains = array_values(array_unique(array_filter(array_map(
      static fn(string $value): string => strtolower(trim($value)),
      $domains
    ), static fn(string $value): bool => $value !== '')));
    if ($normalized_domains === []) {
      return [];
    }

    return $this->database->select('dc_campaign_subject_registry', 'r')
      ->fields('r', ['subject_id', 'subject_domain', 'display_name'])
      ->condition('campaign_id', $campaign_id)
      ->condition('subject_kind', 'institution')
      ->condition('subject_domain', $normalized_domains, 'IN')
      ->condition('status', 'active')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC) ?: [];
  }

  /**
   * Compose deterministic relationship id for directed matrix edge.
   */
  protected function composeRelationshipId(string $source_subject_id, string $target_subject_id): string {
    return sprintf(
      'institution--%s--%s--institution--%s',
      $this->normalizeIdentifier($source_subject_id),
      $this->normalizeIdentifier(self::RELATIONSHIP_TYPE),
      $this->normalizeIdentifier($target_subject_id)
    );
  }

  /**
   * Normalize identifier fragments for deterministic relationship ids.
   */
  protected function normalizeIdentifier(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    return trim($value, '-_');
  }

  /**
   * Decode JSON payload into an array.
   *
   * @return array<string,mixed>
   *   Decoded associative array.
   */
  protected function decodeJsonColumn(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }
    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

  /**
   * Normalize persisted knowledge state values.
   */
  protected function normalizeKnowledgeState(string $knowledge_state): string {
    $knowledge_state = strtolower(trim($knowledge_state));
    return match ($knowledge_state) {
      'known', 'unknown' => $knowledge_state,
      default => 'known',
    };
  }

  /**
   * Resolve default profession-target bias score from normalized label.
   */
  protected function resolveProfessionTargetBias(string $target_profession_label): int {
    return (int) (self::PROFESSION_TARGET_BIAS[$target_profession_label] ?? 0);
  }

  /**
   * Resolve directed ancestry bias score from normalized source/target labels.
   */
  protected function resolveAncestryDirectedBias(string $source_ancestry_label, string $target_ancestry_label): int {
    if ($target_ancestry_label === self::UNIVERSAL_UNDEAD_TARGET_LABEL) {
      return self::UNIVERSAL_UNDEAD_BIAS_SCORE;
    }
    return (int) (self::ANCESTRY_DIRECTED_BIAS[$source_ancestry_label][$target_ancestry_label] ?? 0);
  }

  /**
   * Normalize display labels into stable lowercase lookup keys.
   */
  protected function normalizeLabel(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    return trim($value, '-');
  }

}
