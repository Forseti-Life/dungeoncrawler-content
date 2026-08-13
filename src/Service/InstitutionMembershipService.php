<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * Synchronizes actor-to-institution memberships for campaign runtime subjects.
 */
class InstitutionMembershipService {
  protected const DEFAULT_ANCESTRY_LABEL = 'Unknown Ancestry';
  protected const DEFAULT_PROFESSION_LABEL = 'Unknown Profession';

  /**
   * Generic NPC class labels that should not seed profession institutions.
   */
  protected const GENERIC_NPC_CLASS_VALUES = [
    'creature',
    'npc',
    'humanoid',
    'person',
    'character',
    'party',
    'quest giver',
    'quest_giver',
    'mentor',
    'patron',
    'townsfolk',
  ];

  /**
   * Structured campaign-character affiliation refs keyed by stored field.
   */
  protected const STRUCTURED_AFFILIATION_REF_FIELDS = [
    'home_settlement_ref' => ['domain' => 'settlement', 'multiple' => FALSE],
    'government_ref' => ['domain' => 'government', 'multiple' => FALSE],
    'faction_refs' => ['domain' => 'allegiance', 'multiple' => TRUE],
    'security_affiliation_refs' => ['domain' => 'security', 'multiple' => TRUE],
    'family_refs' => ['domain' => 'family', 'multiple' => TRUE],
    'religion_refs' => ['domain' => 'religion', 'multiple' => TRUE],
    'employer_refs' => ['domain' => 'employer', 'multiple' => TRUE],
    'order_refs' => ['domain' => 'education', 'multiple' => TRUE],
    'noble_refs' => ['domain' => 'noble', 'multiple' => TRUE],
    'criminal_refs' => ['domain' => 'criminal', 'multiple' => TRUE],
    'culture_refs' => ['domain' => 'culture', 'multiple' => TRUE],
  ];

  /**
   * Institution domains that seed political/social sentiment.
   */
  protected const POLITICAL_SENTIMENT_INSTITUTION_DOMAINS = [
    'allegiance',
    'government',
    'security',
    'religion',
    'employer',
    'education',
    'noble',
    'criminal',
    'culture',
  ];

  public function __construct(
    protected Connection $database,
    protected CampaignSubjectRegistryService $campaignSubjectRegistry,
    protected InstitutionNormalizationService $institutionNormalization,
    protected RelationshipManagerService $relationshipManager,
  ) {}

  /**
   * Synchronizes deterministic memberships for a campaign character.
   */
  public function syncCampaignCharacterMemberships(int $campaign_id, string $character_instance_id, array $character_data): int {
    return $this->syncMemberships(
      $campaign_id,
      'campaign_character',
      $character_instance_id,
      $this->buildCharacterInstitutionInputs($character_data, 'character_creation')
    );
  }

  /**
   * Synchronizes deterministic memberships for a campaign NPC.
   */
  public function syncCampaignNpcMemberships(int $campaign_id, string $npc_entity_ref, array $npc_data): int {
    return $this->syncMemberships(
      $campaign_id,
      'campaign_npc',
      $npc_entity_ref,
      $this->buildNpcInstitutionInputs($npc_data, 'npc_creation')
    );
  }

  /**
   * Builds deterministic institution-bearing inputs for a character payload.
   *
   * @return array<int, array<string, mixed>>
   */
  public function buildCharacterInstitutionInputs(array $character_data, string $seed_source = 'character_creation'): array {
    $inputs = [];

    $inputs[] = $this->buildAncestryInstitutionInput($character_data, $seed_source);
    $inputs[] = $this->buildSeededInstitutionInput(
      'profession',
      $this->resolveCharacterProfessionValue($character_data),
      $seed_source,
      $this->extractNonEmptyString($character_data, ['class']) !== '' ? 'class' : 'profession_default'
    );

    foreach ($this->buildExplicitStructuredAffiliationInputs($character_data, $seed_source) as $input) {
      $inputs[] = $input;
    }

    return $inputs;
  }

  /**
   * Builds deterministic institution-bearing inputs for an NPC payload.
   *
   * @return array<int, array<string, mixed>>
   */
  public function buildNpcInstitutionInputs(array $npc_data, string $seed_source = 'npc_creation'): array {
    $inputs = [];

    $inputs[] = $this->buildAncestryInstitutionInput($npc_data, $seed_source);
    $profession = $this->resolveNpcProfessionValue($npc_data);
    $inputs[] = $this->buildSeededInstitutionInput(
      'profession',
      $this->humanizeValue($profession),
      $seed_source,
      $this->extractNonEmptyString($npc_data, ['occupation']) !== '' ? 'occupation' : (($this->extractNonEmptyString($npc_data, ['class']) !== '') ? 'class' : 'profession_default')
    );

    foreach ($this->buildExplicitStructuredAffiliationInputs($npc_data, $seed_source) as $input) {
      $inputs[] = $input;
    }

    return $inputs;
  }

  /**
   * Synchronizes an actor's institution_member edges to the desired inputs.
   */
  public function syncMemberships(int $campaign_id, string $source_type, string $source_id, array $institution_inputs): int {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    if ($source_type === '' || $source_id === '') {
      throw new \InvalidArgumentException('Actor source type and source id are required.');
    }
    if (!$this->campaignSubjectRegistry->isSubjectRegistryReady()) {
      throw new \RuntimeException('Campaign subject registry storage is not installed.');
    }
    if (!$this->relationshipManager->isRelationshipStorageReady()) {
      throw new \RuntimeException('Campaign relationship storage is not installed.');
    }

    $prepared_memberships = $this->prepareMembershipSyncInputs($campaign_id, $institution_inputs);
    $this->database->startTransaction();

    $active_subject_ids = [];
    $desired_membership_ids = [];
    $resolved_memberships = [];
    $existing_membership_edges = $this->loadExistingInstitutionMembershipEdges($campaign_id, $source_type, $source_id);

    foreach ($prepared_memberships as $prepared_membership) {
      $input = $prepared_membership['input'];
      $subject = is_array($prepared_membership['subject'] ?? NULL)
        ? $prepared_membership['subject']
        : $this->campaignSubjectRegistry->resolveOrCreateInstitutionSubject($campaign_id, $input);

      $resolved_subject_id = trim((string) ($subject['subject_id'] ?? ''));
      $resolved_domain = trim((string) ($subject['subject_domain'] ?? ($prepared_membership['normalized']['domain'] ?? '')));
      $resolved_display_name = trim((string) ($subject['display_name'] ?? ($prepared_membership['normalized']['display_name'] ?? '')));
      $resolved_source_asset_type = trim((string) ($subject['source_asset_type'] ?? ($prepared_membership['source_asset_type'] ?? '')));
      $resolved_source_asset_id = trim((string) ($subject['source_asset_id'] ?? ($prepared_membership['source_asset_id'] ?? '')));
      $expected_domain = (string) $prepared_membership['expected_domain'];

      if ($expected_domain !== '' && $resolved_domain !== $expected_domain) {
        throw new \InvalidArgumentException(sprintf('Campaign institution subject "%s" resolved domain "%s", expected "%s".', $resolved_subject_id, $resolved_domain, $expected_domain));
      }

      $relationship_id = $this->composeRelationshipId($source_type, $source_id, 'institution_member', 'institution', $resolved_subject_id);
      $desired_membership_ids[$relationship_id] = TRUE;
      $existing_membership_edge = $existing_membership_edges[$relationship_id] ?? NULL;
      if ($existing_membership_edge !== NULL && !$this->isReconcilableSeededMembershipEdge($existing_membership_edge)) {
        if ($this->isActiveMembershipEdge($existing_membership_edge)) {
          $active_subject_ids[] = $resolved_subject_id;
          $resolved_memberships[] = $this->buildResolvedMembershipFromExistingEdge($existing_membership_edge, $resolved_subject_id);
        }
        continue;
      }

      $active_subject_ids[] = $resolved_subject_id;
      $relationship_state = [
        'edge_kind' => 'institution_membership',
        'institution_domain' => $resolved_domain,
        'institution_display_name' => $resolved_display_name,
        'source_scope' => (string) $prepared_membership['source_scope'],
        'membership_domain' => $this->resolveMembershipDomain($resolved_domain),
        'membership_mutability' => $this->resolveMembershipMutability($resolved_domain),
        'membership_status' => 'active',
        'mutation_state' => 'seeded',
        'mutation_count' => 0,
        'seed_reason' => (string) $prepared_membership['seed_reason'],
      ];
      $sentiment_domain = $this->resolveSentimentDomain($resolved_domain);
      if ($sentiment_domain !== '') {
        $relationship_state['sentiment_domain'] = $sentiment_domain;
      }
      if ($resolved_source_asset_type !== '' && $resolved_source_asset_id !== '') {
        $relationship_state['source_asset_type'] = $resolved_source_asset_type;
        $relationship_state['source_asset_id'] = $resolved_source_asset_id;
      }

      $this->relationshipManager->upsertRuntimeRelationship($campaign_id, [
        'relationship_id' => $relationship_id,
        'source_type' => $source_type,
        'source_id' => $source_id,
        'target_type' => 'institution',
        'target_id' => $resolved_subject_id,
        'relationship_type' => 'institution_member',
        'status' => 'active',
        'relationship_state' => $relationship_state,
      ]);

      $resolved_memberships[] = [
        'subject_id' => $resolved_subject_id,
        'subject_domain' => $resolved_domain,
        'display_name' => $resolved_display_name,
        'source_scope' => (string) $prepared_membership['source_scope'],
        'source_asset_type' => $resolved_source_asset_type,
        'source_asset_id' => $resolved_source_asset_id,
        'membership_domain' => $relationship_state['membership_domain'],
        'membership_mutability' => $relationship_state['membership_mutability'],
        'sentiment_domain' => $sentiment_domain,
      ];
    }

    foreach ($existing_membership_edges as $relationship_id => $existing_membership_edge) {
      if (isset($desired_membership_ids[$relationship_id]) || !$this->isReconcilableSeededMembershipEdge($existing_membership_edge)) {
        continue;
      }

      $this->database->delete('dc_campaign_relationships')
        ->condition('campaign_id', $campaign_id)
        ->condition('relationship_id', $relationship_id)
        ->execute();
    }
    $this->seedActorFactionSentiments($campaign_id, $source_type, $source_id, $resolved_memberships);

    return count(array_values(array_unique($active_subject_ids)));
  }

  /**
   * Applies an explicit runtime mutation to one actor-held institution sentiment.
   */
  public function mutateInstitutionSentiment(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $target_subject_id,
    int $score,
    string $knowledge_state = 'known',
    array $context = []
  ): int {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    $source_type = trim($source_type);
    $source_id = trim($source_id);
    $target_subject_id = trim($target_subject_id);
    if ($source_type === '' || $source_id === '' || $target_subject_id === '') {
      throw new \InvalidArgumentException('Actor source type, source id, and institution target id are required.');
    }
    if (!$this->campaignSubjectRegistry->isSubjectRegistryReady()) {
      throw new \RuntimeException('Campaign subject registry storage is not installed.');
    }
    if (!$this->relationshipManager->isRelationshipStorageReady()) {
      throw new \RuntimeException('Campaign relationship storage is not installed.');
    }

    $knowledge_state = $this->normalizeKnowledgeState($knowledge_state);
    $target_subject = $this->campaignSubjectRegistry->loadInstitutionSubject($campaign_id, $target_subject_id);
    $target_domain = trim((string) ($target_subject['subject_domain'] ?? ''));
    $target_display_name = trim((string) ($target_subject['display_name'] ?? ''));
    $sentiment_domain = $this->resolveSentimentDomain($target_domain);
    if ($sentiment_domain === '') {
      throw new \InvalidArgumentException(sprintf('Institution subject "%s" does not support actor-held sentiment mutations.', $target_subject_id));
    }

    $relationship_id = $this->composeRelationshipId($source_type, $source_id, 'institution_sentiment', 'institution', $target_subject_id);
    $existing_edge = $this->loadExistingInstitutionSentimentEdges($campaign_id, $source_type, $source_id)[$relationship_id] ?? NULL;
    $existing_state = is_array($existing_edge['relationship_state_decoded'] ?? NULL) ? $existing_edge['relationship_state_decoded'] : [];
    $mutation_source = trim((string) ($context['mutation_source'] ?? 'manual'));
    $mutation_reason = trim((string) ($context['reason'] ?? ''));

    return $this->relationshipManager->upsertRuntimeRelationship($campaign_id, [
      'relationship_id' => $relationship_id,
      'source_type' => $source_type,
      'source_id' => $source_id,
      'target_type' => 'institution',
      'target_id' => $target_subject_id,
      'relationship_type' => 'institution_sentiment',
      'attitude' => $this->scoreToAttitude($score),
      'status' => $knowledge_state,
      'relationship_state' => [
        'edge_kind' => 'institution_sentiment',
        'sentiment_domain' => $sentiment_domain,
        'knowledge_state' => $knowledge_state,
        'score' => $score,
        'seed_source' => trim((string) ($existing_state['seed_source'] ?? 'manual')),
        'seed_profile_key' => trim((string) ($existing_state['seed_profile_key'] ?? 'manual-adjustment')),
        'primary_membership_subject_id' => trim((string) ($existing_state['primary_membership_subject_id'] ?? '')),
        'primary_membership_display_name' => trim((string) ($existing_state['primary_membership_display_name'] ?? '')),
        'target_display_name' => $target_display_name,
        'mutation_state' => 'mutated',
        'mutation_count' => max(1, (int) ($existing_state['mutation_count'] ?? 0) + 1),
        'touched_at' => time(),
        'last_mutation_source' => $mutation_source,
        'last_mutation_reason' => $mutation_reason,
      ],
    ]);
  }

  /**
   * Applies an explicit runtime mutation to one actor-held institution membership.
   */
  public function mutateInstitutionMembership(
    int $campaign_id,
    string $source_type,
    string $source_id,
    string $target_subject_id,
    string $membership_status,
    array $context = []
  ): int {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    $source_type = trim($source_type);
    $source_id = trim($source_id);
    $target_subject_id = trim($target_subject_id);
    if ($source_type === '' || $source_id === '' || $target_subject_id === '') {
      throw new \InvalidArgumentException('Actor source type, source id, and institution target id are required.');
    }
    if (!$this->campaignSubjectRegistry->isSubjectRegistryReady()) {
      throw new \RuntimeException('Campaign subject registry storage is not installed.');
    }
    if (!$this->relationshipManager->isRelationshipStorageReady()) {
      throw new \RuntimeException('Campaign relationship storage is not installed.');
    }

    $membership_status = $this->normalizeMembershipStatus($membership_status);
    $relationship_id = $this->composeRelationshipId($source_type, $source_id, 'institution_member', 'institution', $target_subject_id);
    $existing_edge = $this->loadExistingInstitutionMembershipEdges($campaign_id, $source_type, $source_id)[$relationship_id] ?? NULL;
    if ($existing_edge === NULL) {
      throw new \InvalidArgumentException(sprintf('Institution membership "%s" was not found for actor "%s:%s".', $target_subject_id, $source_type, $source_id));
    }

    $existing_state = is_array($existing_edge['relationship_state_decoded'] ?? NULL) ? $existing_edge['relationship_state_decoded'] : [];
    if (($existing_state['edge_kind'] ?? '') !== 'institution_membership') {
      throw new \InvalidArgumentException(sprintf('Relationship "%s" is not an institution membership edge.', $relationship_id));
    }

    $membership_mutability = trim((string) ($existing_state['membership_mutability'] ?? ''));
    if ($membership_mutability === '') {
      $target_subject = $this->campaignSubjectRegistry->loadInstitutionSubject($campaign_id, $target_subject_id);
      $membership_mutability = $this->resolveMembershipMutability(trim((string) ($target_subject['subject_domain'] ?? '')));
    }
    if ($membership_mutability === 'immutable') {
      throw new \InvalidArgumentException(sprintf('Institution membership "%s" is immutable and cannot be changed.', $target_subject_id));
    }
    if ($membership_mutability === 'sticky' && empty($context['allow_sticky'])) {
      throw new \InvalidArgumentException(sprintf('Institution membership "%s" is sticky and requires explicit override.', $target_subject_id));
    }

    $mutation_source = trim((string) ($context['mutation_source'] ?? 'manual'));
    $mutation_reason = trim((string) ($context['reason'] ?? ''));
    $status = $membership_status === 'active' ? 'active' : 'inactive';

    return $this->relationshipManager->upsertRuntimeRelationship($campaign_id, [
      'relationship_id' => $relationship_id,
      'source_type' => $source_type,
      'source_id' => $source_id,
      'target_type' => 'institution',
      'target_id' => $target_subject_id,
      'relationship_type' => 'institution_member',
      'status' => $status,
      'relationship_state' => [
        'edge_kind' => 'institution_membership',
        'membership_status' => $membership_status,
        'mutation_state' => 'mutated',
        'mutation_count' => max(1, (int) ($existing_state['mutation_count'] ?? 0) + 1),
        'touched_at' => time(),
        'last_mutation_source' => $mutation_source,
        'last_mutation_reason' => $mutation_reason,
      ],
    ]);
  }

  /**
   * Lists actor-held institution sentiments with explicit neutral-state classification.
   *
   * @return array<int, array<string, mixed>>
   *   Sentiment rows normalized for runtime/query consumers.
   */
  public function listActorInstitutionSentiments(
    int $campaign_id,
    string $source_type,
    string $source_id,
    ?string $sentiment_domain = NULL
  ): array {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    $source_type = trim($source_type);
    $source_id = trim($source_id);
    if ($source_type === '' || $source_id === '') {
      throw new \InvalidArgumentException('Actor source type and source id are required.');
    }

    $sentiment_domain = $sentiment_domain !== NULL ? trim($sentiment_domain) : NULL;
    $edges = $this->loadExistingInstitutionSentimentEdges($campaign_id, $source_type, $source_id);
    $sentiments = [];
    foreach ($edges as $edge) {
      $state = is_array($edge['relationship_state_decoded'] ?? NULL)
        ? $edge['relationship_state_decoded']
        : [];
      $edge_sentiment_domain = trim((string) ($state['sentiment_domain'] ?? ''));
      if ($sentiment_domain !== NULL && $sentiment_domain !== '' && $edge_sentiment_domain !== $sentiment_domain) {
        continue;
      }

      $score = (int) ($state['score'] ?? 0);
      $knowledge_state = $this->normalizeKnowledgeState((string) ($state['knowledge_state'] ?? 'unknown'));
      $sentiments[] = [
        'relationship_id' => trim((string) ($edge['relationship_id'] ?? '')),
        'target_id' => trim((string) ($edge['target_id'] ?? '')),
        'target_display_name' => trim((string) ($state['target_display_name'] ?? '')),
        'sentiment_domain' => $edge_sentiment_domain,
        'score' => $score,
        'attitude' => $this->scoreToAttitude($score),
        'knowledge_state' => $knowledge_state,
        'neutrality_kind' => $this->resolveNeutralityKind($score, $knowledge_state),
        'mutation_state' => trim((string) ($state['mutation_state'] ?? 'seeded')),
        'mutation_count' => (int) ($state['mutation_count'] ?? 0),
      ];
    }

    usort($sentiments, static function (array $a, array $b): int {
      return [$a['sentiment_domain'], $a['target_display_name'], $a['target_id']]
        <=> [$b['sentiment_domain'], $b['target_display_name'], $b['target_id']];
    });

    return $sentiments;
  }

  /**
   * Lists actor-held institution memberships.
   *
   * @return array<int, array<string, mixed>>
   *   Membership rows normalized for runtime/query consumers.
   */
  public function listActorInstitutionMemberships(
    int $campaign_id,
    string $source_type,
    string $source_id,
    ?string $sentiment_domain = NULL
  ): array {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    $source_type = trim($source_type);
    $source_id = trim($source_id);
    if ($source_type === '' || $source_id === '') {
      throw new \InvalidArgumentException('Actor source type and source id are required.');
    }

    $sentiment_domain = $sentiment_domain !== NULL ? trim($sentiment_domain) : NULL;
    $edges = $this->loadExistingInstitutionMembershipEdges($campaign_id, $source_type, $source_id);
    $memberships = [];
    foreach ($edges as $edge) {
      $state = is_array($edge['relationship_state_decoded'] ?? NULL)
        ? $edge['relationship_state_decoded']
        : [];
      $edge_domain = trim((string) ($state['sentiment_domain'] ?? ''));
      if ($sentiment_domain !== NULL && $sentiment_domain !== '' && $edge_domain !== $sentiment_domain) {
        continue;
      }
      if (!$this->isActiveMembershipEdge($edge)) {
        continue;
      }

      $memberships[] = [
        'relationship_id' => trim((string) ($edge['relationship_id'] ?? '')),
        'target_id' => trim((string) ($edge['target_id'] ?? '')),
        'target_display_name' => trim((string) ($state['target_display_name'] ?? '')),
        'sentiment_domain' => $edge_domain,
        'membership_domain' => trim((string) ($state['membership_domain'] ?? '')),
        'membership_mutability' => trim((string) ($state['membership_mutability'] ?? '')),
        'membership_status' => trim((string) ($state['membership_status'] ?? 'active')),
      ];
    }

    usort($memberships, static function (array $a, array $b): int {
      return [$a['sentiment_domain'], $a['target_display_name'], $a['target_id']]
        <=> [$b['sentiment_domain'], $b['target_display_name'], $b['target_id']];
    });

    return $memberships;
  }

  /**
   * Validates and deduplicates desired membership sync inputs before writes.
   *
   * @param array<int, mixed> $institution_inputs
   *   Candidate membership inputs.
   *
   * @return array<int, array<string, mixed>>
   *   Prepared membership specs ready for write-phase resolution.
   */
  protected function prepareMembershipSyncInputs(int $campaign_id, array $institution_inputs): array {
    $prepared = [];
    $seen_keys = [];

    foreach ($institution_inputs as $input) {
      if (!is_array($input)) {
        continue;
      }

      $source_scope = trim((string) (($input['metadata']['seed_source'] ?? '') ?: 'actor_creation'));
      $seed_reason = trim((string) ($input['metadata']['source_field'] ?? ''));
      $expected_domain = trim((string) ($input['metadata']['expected_domain'] ?? ''));
      $explicit_subject_id = trim((string) ($input['subject_id'] ?? ''));
      $explicit_source_asset_type = trim((string) ($input['source_asset_type'] ?? ''));
      $explicit_source_asset_id = trim((string) ($input['source_asset_id'] ?? ''));
      $normalized = [];
      $resolved_subject = NULL;
      $dedupe_key = '';

      if ($explicit_subject_id !== '') {
        $subject_input = $this->buildSubjectInputFromExplicitSubjectId($input, $explicit_subject_id, $expected_domain);
        $resolved_subject = [];

        try {
          $resolved_subject = $this->campaignSubjectRegistry->loadInstitutionSubject($campaign_id, $explicit_subject_id);
        }
        catch (\InvalidArgumentException) {
          // Missing campaign-scoped row is resolved deterministically from the explicit subject id contract.
        }

        $resolved_subject_id = trim((string) ($resolved_subject['subject_id'] ?? ''));
        $resolved_domain = trim((string) ($resolved_subject['subject_domain'] ?? ''));
        $resolved_display_name = trim((string) ($resolved_subject['display_name'] ?? ''));
        if ($resolved_subject_id === '' || $resolved_domain === '' || $resolved_display_name === '') {
          $resolved_subject = $this->campaignSubjectRegistry->resolveOrCreateInstitutionSubject($campaign_id, $subject_input);
        }

        $resolved_subject_id = trim((string) ($resolved_subject['subject_id'] ?? ''));
        $resolved_domain = trim((string) ($resolved_subject['subject_domain'] ?? ''));
        $resolved_display_name = trim((string) ($resolved_subject['display_name'] ?? ''));
        if ($resolved_subject_id === '' || $resolved_domain === '' || $resolved_display_name === '') {
          throw new \RuntimeException(sprintf('Campaign institution subject "%s" is missing required registry fields.', $explicit_subject_id));
        }
        if ($expected_domain !== '' && $resolved_domain !== $expected_domain) {
          throw new \InvalidArgumentException(sprintf('Campaign institution subject "%s" does not match expected domain "%s".', $explicit_subject_id, $expected_domain));
        }
        $dedupe_key = 'subject:' . $resolved_subject_id;
      }
      else {
        $display_name = trim((string) ($input['display_name'] ?? $input['label'] ?? ''));
        if ($display_name === '') {
          continue;
        }

        $normalized = $this->institutionNormalization->normalizeInstitutionInput($input);
        if ($expected_domain !== '' && (string) $normalized['domain'] !== $expected_domain) {
          if ($explicit_source_asset_type !== '' && $explicit_source_asset_id !== '') {
            throw new \InvalidArgumentException(sprintf('Library-backed institution input "%s:%s" normalized domain "%s", expected "%s".', $explicit_source_asset_type, $explicit_source_asset_id, $normalized['domain'], $expected_domain));
          }
          throw new \InvalidArgumentException(sprintf('Institution input "%s" normalized domain "%s", expected "%s".', $display_name, $normalized['domain'], $expected_domain));
        }
        $dedupe_key = (string) $normalized['domain'] . ':' . (string) $normalized['normalized_label'];
      }

      if (isset($seen_keys[$dedupe_key])) {
        continue;
      }
      $seen_keys[$dedupe_key] = TRUE;

      $prepared[] = [
        'input' => $input,
        'subject' => $resolved_subject,
        'normalized' => $normalized,
        'expected_domain' => $expected_domain,
        'source_scope' => $source_scope,
        'seed_reason' => $seed_reason,
        'source_asset_type' => $explicit_source_asset_type,
        'source_asset_id' => $explicit_source_asset_id,
      ];
    }

    return $prepared;
  }

  /**
   * Builds deterministic institution input fields from an explicit subject id.
   *
   * @param array<string, mixed> $input
   *   Source membership input.
   * @return array<string, mixed>
   *   Normalized input fields required by the subject registry.
   */
  protected function buildSubjectInputFromExplicitSubjectId(array $input, string $subject_id, string $expected_domain): array {
    $subject_input = $input;
    $subject_input['subject_id'] = $subject_id;

    $domain = trim((string) ($subject_input['domain'] ?? $expected_domain));
    $display_name = trim((string) ($subject_input['display_name'] ?? $subject_input['label'] ?? ''));

    if ($domain === '' || $display_name === '') {
      [$derived_domain, $derived_display_name] = $this->deriveInstitutionFieldsFromSubjectId($subject_id);
      if ($domain === '') {
        $domain = $derived_domain;
      }
      if ($display_name === '') {
        $display_name = $derived_display_name;
      }
    }

    if ($expected_domain !== '' && $domain !== $expected_domain) {
      throw new \InvalidArgumentException(sprintf('Campaign institution subject "%s" does not match expected domain "%s".', $subject_id, $expected_domain));
    }
    if ($domain === '' || $display_name === '') {
      throw new \RuntimeException(sprintf('Campaign institution subject "%s" is missing required registry fields.', $subject_id));
    }

    $subject_input['domain'] = $domain;
    $subject_input['display_name'] = $display_name;

    return $subject_input;
  }

  /**
   * Derives canonical domain/display fields from a stable institution subject id.
   *
   * @return array{0:string,1:string}
   *   Tuple of [domain, display_name].
   */
  protected function deriveInstitutionFieldsFromSubjectId(string $subject_id): array {
    if (!preg_match('/^institution_([a-z0-9-]+)_(.+)$/', $subject_id, $matches)) {
      throw new \InvalidArgumentException(sprintf('Campaign institution subject "%s" is not a valid institution subject id.', $subject_id));
    }

    $domain = $this->institutionNormalization->normalizeDomain((string) $matches[1]);
    $normalized_label = trim((string) $matches[2]);
    if ($domain === '' || $normalized_label === '') {
      throw new \RuntimeException(sprintf('Campaign institution subject "%s" is missing required registry fields.', $subject_id));
    }

    $display_name = $this->humanizeValue($normalized_label);
    if ($display_name === '') {
      throw new \RuntimeException(sprintf('Campaign institution subject "%s" is missing required registry fields.', $subject_id));
    }

    return [$domain, $display_name];
  }

  /**
   * Seeds domain-scoped actor sentiment edges for represented memberships.
   *
   * @param array<int, array<string, string>> $resolved_memberships
   *   Normalized membership records resolved during sync.
   */
  protected function seedActorFactionSentiments(int $campaign_id, string $source_type, string $source_id, array $resolved_memberships): void {
    $primary_memberships = [];
    $processed_domains = [];
    foreach ($resolved_memberships as $membership) {
      $sentiment_domain = trim((string) ($membership['sentiment_domain'] ?? ''));
      if ($sentiment_domain === '' || isset($primary_memberships[$sentiment_domain])) {
        continue;
      }
      $primary_memberships[$sentiment_domain] = $membership;
    }

    $existing_edges = $this->loadExistingInstitutionSentimentEdges($campaign_id, $source_type, $source_id);
    foreach ($existing_edges as $relationship_id => $edge) {
      $domain = trim((string) ($edge['sentiment_domain'] ?? ''));
      if ($domain !== '') {
        $processed_domains[$domain] = TRUE;
      }
    }
    foreach ($primary_memberships as $sentiment_domain => $primary_membership) {
      $processed_domains[$sentiment_domain] = TRUE;
      $peer_subjects = $this->buildSentimentPeerSubjects($campaign_id, $sentiment_domain, $primary_membership, $resolved_memberships);
      $known_subject_ids = [];
      foreach ($resolved_memberships as $membership) {
        if (($membership['sentiment_domain'] ?? '') === $sentiment_domain) {
          $known_subject_ids[(string) $membership['subject_id']] = TRUE;
        }
      }

      $desired_edges = [];
      foreach ($peer_subjects as $peer_subject) {
        $target_id = trim((string) ($peer_subject['subject_id'] ?? ''));
        if ($target_id === '') {
          continue;
        }

        $relationship_id = $this->composeRelationshipId($source_type, $source_id, 'institution_sentiment', 'institution', $target_id);
        $is_known = isset($known_subject_ids[$target_id]);
        $score = 0;
        $knowledge_state = $is_known ? 'known' : 'unknown';
        $profile_key = $is_known
          ? 'known-neutral-default'
          : 'unknown-neutral-default';

        $desired_edges[$relationship_id] = [
          'relationship_id' => $relationship_id,
          'source_type' => $source_type,
          'source_id' => $source_id,
          'target_type' => 'institution',
          'target_id' => $target_id,
          'relationship_type' => 'institution_sentiment',
          'attitude' => $this->scoreToAttitude($score),
          'status' => $knowledge_state,
          'relationship_state' => [
            'edge_kind' => 'institution_sentiment',
            'sentiment_domain' => $sentiment_domain,
            'knowledge_state' => $knowledge_state,
            'score' => $score,
            'seed_source' => 'actor_creation',
            'seed_profile_key' => $profile_key,
            'mutation_state' => 'seeded',
            'mutation_count' => 0,
            'primary_membership_subject_id' => (string) ($primary_membership['subject_id'] ?? ''),
            'primary_membership_display_name' => (string) ($primary_membership['display_name'] ?? ''),
            'target_display_name' => (string) ($peer_subject['display_name'] ?? ''),
          ],
        ];
      }

      foreach ($desired_edges as $relationship_id => $relationship) {
        $existing_edge = $existing_edges[$relationship_id] ?? NULL;
        if ($existing_edge !== NULL && !$this->isReconcilableSeededSentimentEdge($existing_edge)) {
          continue;
        }

        $this->relationshipManager->upsertRuntimeRelationship($campaign_id, $relationship);
      }
    }

    foreach ($existing_edges as $relationship_id => $existing_edge) {
      $domain = trim((string) ($existing_edge['sentiment_domain'] ?? ''));
      if ($domain === '' || !isset($processed_domains[$domain]) || !$this->isReconcilableSeededSentimentEdge($existing_edge)) {
        continue;
      }

      if (isset($primary_memberships[$domain])) {
        $peer_subjects = $this->buildSentimentPeerSubjects($campaign_id, $domain, $primary_memberships[$domain], $resolved_memberships);
        $desired_target_ids = [];
        foreach ($peer_subjects as $peer_subject) {
          $target_id = trim((string) ($peer_subject['subject_id'] ?? ''));
          if ($target_id !== '') {
            $desired_target_ids[$target_id] = TRUE;
          }
        }
        if (isset($desired_target_ids[(string) ($existing_edge['target_id'] ?? '')])) {
          continue;
        }
      }

      $this->database->delete('dc_campaign_relationships')
        ->condition('campaign_id', $campaign_id)
        ->condition('relationship_id', $relationship_id)
        ->execute();
    }
  }

  /**
   * Builds peer subjects for one sentiment domain.
   *
   * @param array<string, string> $primary_membership
   * @param array<int, array<string, string>> $resolved_memberships
   *
   * @return array<int, array<string, string>>
   *   Peer subject rows keyed like campaign subject registry records.
   */
  protected function buildSentimentPeerSubjects(int $campaign_id, string $sentiment_domain, array $primary_membership, array $resolved_memberships): array {
    if ($sentiment_domain === 'ancestry') {
      $subjects = [];
      foreach (array_keys(CharacterManager::ANCESTRIES) as $display_name) {
        $subject = $this->campaignSubjectRegistry->resolveOrCreateInstitutionSubject($campaign_id, [
          'domain' => 'ancestry',
          'display_name' => $display_name,
          'metadata' => [
            'seed_source' => 'actor_sentiment_seed',
            'sentiment_domain' => 'ancestry',
          ],
        ]);
        $subject_id = trim((string) ($subject['subject_id'] ?? ''));
        if ($subject_id !== '') {
          $subjects[$subject_id] = [
            'subject_id' => $subject_id,
            'display_name' => trim((string) ($subject['display_name'] ?? $display_name)),
          ];
        }
      }
      $primary_subject_id = trim((string) ($primary_membership['subject_id'] ?? ''));
      if ($primary_subject_id !== '') {
        $subjects[$primary_subject_id] = [
          'subject_id' => $primary_subject_id,
          'display_name' => trim((string) ($primary_membership['display_name'] ?? '')),
        ];
      }
      return array_values($subjects);
    }

    if ($sentiment_domain === 'class') {
      $subjects = [];
      foreach (CharacterManager::CLASSES as $definition) {
        $display_name = trim((string) ($definition['name'] ?? ''));
        if ($display_name === '') {
          continue;
        }
        $subject = $this->campaignSubjectRegistry->resolveOrCreateInstitutionSubject($campaign_id, [
          'domain' => 'profession',
          'display_name' => $display_name,
          'metadata' => [
            'seed_source' => 'actor_sentiment_seed',
            'sentiment_domain' => 'class',
          ],
        ]);
        $subject_id = trim((string) ($subject['subject_id'] ?? ''));
        if ($subject_id !== '') {
          $subjects[$subject_id] = [
            'subject_id' => $subject_id,
            'display_name' => trim((string) ($subject['display_name'] ?? $display_name)),
          ];
        }
      }
      $primary_subject_id = trim((string) ($primary_membership['subject_id'] ?? ''));
      if ($primary_subject_id !== '') {
        $subjects[$primary_subject_id] = [
          'subject_id' => $primary_subject_id,
          'display_name' => trim((string) ($primary_membership['display_name'] ?? '')),
        ];
      }
      return array_values($subjects);
    }

    if ($sentiment_domain !== 'political') {
      return [];
    }

    $subjects = [];
    $rows = $this->database->select('dc_campaign_subject_registry', 'r')
      ->fields('r', ['subject_id', 'display_name'])
      ->condition('campaign_id', $campaign_id)
      ->condition('subject_kind', 'institution')
      ->condition('subject_domain', self::POLITICAL_SENTIMENT_INSTITUTION_DOMAINS, 'IN')
      ->orderBy('display_name')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($rows as $row) {
      $subject_id = trim((string) ($row['subject_id'] ?? ''));
      if ($subject_id === '') {
        continue;
      }
      $subjects[$subject_id] = [
        'subject_id' => $subject_id,
        'display_name' => trim((string) ($row['display_name'] ?? '')),
      ];
    }

    foreach ($resolved_memberships as $membership) {
      if (($membership['sentiment_domain'] ?? '') !== 'political') {
        continue;
      }
      $subject_id = trim((string) ($membership['subject_id'] ?? ''));
      if ($subject_id === '') {
        continue;
      }
      $subjects[$subject_id] = [
        'subject_id' => $subject_id,
        'display_name' => trim((string) ($membership['display_name'] ?? '')),
      ];
    }

    return array_values($subjects);
  }

  /**
   * Loads existing institution sentiment edges for one actor.
   *
   * @return array<string, array<string, mixed>>
   *   Existing rows keyed by relationship id.
   */
  protected function loadExistingInstitutionSentimentEdges(int $campaign_id, string $source_type, string $source_id): array {
    $rows = $this->database->select('dc_campaign_relationships', 'r')
      ->fields('r', ['id', 'relationship_id', 'target_id', 'relationship_state'])
      ->condition('campaign_id', $campaign_id)
      ->condition('source_type', $source_type)
      ->condition('source_id', $source_id)
      ->condition('target_type', 'institution')
      ->condition('relationship_type', 'institution_sentiment')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $edges = [];
    foreach ($rows as $row) {
      $relationship_id = trim((string) ($row['relationship_id'] ?? ''));
      if ($relationship_id === '') {
        continue;
      }
      $state = $this->decodeJsonColumn($row['relationship_state'] ?? NULL);
      $edges[$relationship_id] = $row + [
        'sentiment_domain' => trim((string) ($state['sentiment_domain'] ?? '')),
        'target_id' => trim((string) ($row['target_id'] ?? '')),
        'relationship_state_decoded' => $state,
      ];
    }

    return $edges;
  }

  /**
   * Loads existing institution membership edges for one actor.
   *
   * @return array<string, array<string, mixed>>
   *   Existing rows keyed by relationship id.
   */
  protected function loadExistingInstitutionMembershipEdges(int $campaign_id, string $source_type, string $source_id): array {
    $rows = $this->database->select('dc_campaign_relationships', 'r')
      ->fields('r', ['id', 'relationship_id', 'target_id', 'status', 'relationship_state'])
      ->condition('campaign_id', $campaign_id)
      ->condition('source_type', $source_type)
      ->condition('source_id', $source_id)
      ->condition('target_type', 'institution')
      ->condition('relationship_type', 'institution_member')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    $edges = [];
    foreach ($rows as $row) {
      $relationship_id = trim((string) ($row['relationship_id'] ?? ''));
      if ($relationship_id === '') {
        continue;
      }
      $state = $this->decodeJsonColumn($row['relationship_state'] ?? NULL);
      $edges[$relationship_id] = $row + [
        'target_id' => trim((string) ($row['target_id'] ?? '')),
        'status' => trim((string) ($row['status'] ?? 'active')),
        'relationship_state_decoded' => $state,
      ];
    }

    return $edges;
  }

  /**
   * Returns TRUE when an existing edge is still an untouched seeded sentiment row.
   */
  protected function isReconcilableSeededSentimentEdge(array $edge): bool {
    $state = is_array($edge['relationship_state_decoded'] ?? NULL)
      ? $edge['relationship_state_decoded']
      : $this->decodeJsonColumn($edge['relationship_state'] ?? NULL);

    return ($state['edge_kind'] ?? '') === 'institution_sentiment'
      && ($state['seed_source'] ?? '') === 'actor_creation'
      && ($state['mutation_state'] ?? 'seeded') === 'seeded'
      && empty($state['touched_at'])
      && in_array(($state['seed_profile_key'] ?? ''), ['membership-self-default', 'known-neutral-default', 'unknown-neutral-default'], TRUE);
  }

  /**
   * Returns TRUE when an existing membership edge is still an untouched seeded row.
   */
  protected function isReconcilableSeededMembershipEdge(array $edge): bool {
    $state = is_array($edge['relationship_state_decoded'] ?? NULL)
      ? $edge['relationship_state_decoded']
      : $this->decodeJsonColumn($edge['relationship_state'] ?? NULL);

    return ($state['edge_kind'] ?? '') === 'institution_membership'
      && ($state['mutation_state'] ?? 'seeded') === 'seeded'
      && empty($state['touched_at']);
  }

  /**
   * Returns TRUE when the membership edge should count as an active membership.
   */
  protected function isActiveMembershipEdge(array $edge): bool {
    $state = is_array($edge['relationship_state_decoded'] ?? NULL)
      ? $edge['relationship_state_decoded']
      : $this->decodeJsonColumn($edge['relationship_state'] ?? NULL);
    $membership_status = trim((string) ($state['membership_status'] ?? 'active'));
    $row_status = trim((string) ($edge['status'] ?? 'active'));

    return $membership_status === 'active' && $row_status !== 'inactive';
  }

  /**
   * Rebuilds a resolved-membership record from an existing runtime edge.
   *
   * @return array<string, string>
   *   Membership record compatible with sentiment seeding.
   */
  protected function buildResolvedMembershipFromExistingEdge(array $edge, string $subject_id): array {
    $state = is_array($edge['relationship_state_decoded'] ?? NULL)
      ? $edge['relationship_state_decoded']
      : $this->decodeJsonColumn($edge['relationship_state'] ?? NULL);

    return [
      'subject_id' => $subject_id,
      'subject_domain' => trim((string) ($state['institution_domain'] ?? '')),
      'display_name' => trim((string) ($state['institution_display_name'] ?? '')),
      'source_scope' => trim((string) ($state['source_scope'] ?? '')),
      'source_asset_type' => trim((string) ($state['source_asset_type'] ?? '')),
      'source_asset_id' => trim((string) ($state['source_asset_id'] ?? '')),
      'membership_domain' => trim((string) ($state['membership_domain'] ?? '')),
      'membership_mutability' => trim((string) ($state['membership_mutability'] ?? '')),
      'sentiment_domain' => trim((string) ($state['sentiment_domain'] ?? '')),
    ];
  }

  /**
   * Normalizes persisted knowledge state values.
   */
  protected function normalizeKnowledgeState(string $knowledge_state): string {
    $knowledge_state = strtolower(trim($knowledge_state));
    return match ($knowledge_state) {
      'known', 'unknown' => $knowledge_state,
      default => throw new \InvalidArgumentException(sprintf('Unsupported knowledge state "%s".', $knowledge_state)),
    };
  }

  /**
   * Normalizes persisted membership status values.
   */
  protected function normalizeMembershipStatus(string $membership_status): string {
    $membership_status = strtolower(trim($membership_status));
    return match ($membership_status) {
      'active', 'abandoned' => $membership_status,
      default => throw new \InvalidArgumentException(sprintf('Unsupported membership status "%s".', $membership_status)),
    };
  }

  /**
   * Resolves the explicit neutrality classification for a sentiment row.
   */
  protected function resolveNeutralityKind(int $score, string $knowledge_state): string {
    if ($score !== 0) {
      return 'non-neutral';
    }

    return $knowledge_state === 'known'
      ? 'known-neutral'
      : 'unknown-neutral';
  }

  /**
   * Resolves the membership domain classification for an institution domain.
   */
  protected function resolveMembershipDomain(string $institution_domain): string {
    return match ($institution_domain) {
      'ancestry' => 'identity',
      'profession' => 'vocation',
      default => 'allegiance',
    };
  }

  /**
   * Resolves the membership mutability for an institution domain.
   */
  protected function resolveMembershipMutability(string $institution_domain): string {
    return match ($institution_domain) {
      'ancestry' => 'immutable',
      'profession' => 'sticky',
      default => 'mutable',
    };
  }

  /**
   * Resolves the actor sentiment domain for an institution domain.
   */
  protected function resolveSentimentDomain(string $institution_domain): string {
    if ($institution_domain === 'ancestry') {
      return 'ancestry';
    }
    if ($institution_domain === 'profession') {
      return 'class';
    }

    return in_array($institution_domain, self::POLITICAL_SENTIMENT_INSTITUTION_DOMAINS, TRUE)
      ? 'political'
      : '';
  }

  /**
   * Builds a deterministic runtime relationship id.
   */
  protected function composeRelationshipId(string $source_type, string $source_id, string $relationship_type, string $target_type, string $target_id): string {
    return implode('--', [
      $this->normalizeIdentifier($source_type),
      $this->normalizeIdentifier($source_id),
      $this->normalizeIdentifier($relationship_type),
      $this->normalizeIdentifier($target_type),
      $this->normalizeIdentifier($target_id),
    ]);
  }

  /**
   * Normalizes identifier fragments.
   */
  protected function normalizeIdentifier(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
    return trim($value, '-_');
  }

  /**
   * Resolves the PF2e-friendly attitude for a seeded sentiment score.
   */
  protected function scoreToAttitude(int $score): string {
    if ($score >= 75) {
      return 'helpful';
    }
    if ($score > 0) {
      return 'friendly';
    }
    if ($score <= -75) {
      return 'hostile';
    }
    if ($score < 0) {
      return 'unfriendly';
    }

    return 'indifferent';
  }

  /**
   * Decodes a JSON payload into an array.
   *
   * @return array<string, mixed>
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
   * Builds an ancestry institution input from actor payload fields.
   */
  protected function buildAncestryInstitutionInput(array $actor_data, string $seed_source): array {
    $ancestry = $this->extractNonEmptyString($actor_data, ['ancestry', 'species']);
    if ($ancestry === '') {
      return $this->buildSeededInstitutionInput(
        'ancestry',
        self::DEFAULT_ANCESTRY_LABEL,
        $seed_source,
        'ancestry_default'
      );
    }
    $canonical_ancestry = CharacterManager::resolveAncestryCanonicalName($ancestry);
    $display_name = $canonical_ancestry !== ''
      ? $canonical_ancestry
      : $this->humanizeValue($ancestry);

    return $this->buildSeededInstitutionInput(
      'ancestry',
      $display_name,
      $seed_source,
      'ancestry'
    );
  }

  /**
   * Builds a deterministic seed input payload with canonical metadata fields.
   *
   * @return array<string, mixed>
   */
  protected function buildSeededInstitutionInput(string $domain, string $display_name, string $seed_source, string $source_field): array {
    return [
      'domain' => $domain,
      'display_name' => $display_name,
      'metadata' => [
        'seed_source' => $seed_source,
        'source_field' => $source_field,
      ],
    ];
  }

  /**
   * Resolves the best deterministic profession seed for an NPC.
   */
  protected function resolveNpcProfessionValue(array $npc_data): string {
    $occupation = $this->extractNonEmptyString($npc_data, ['occupation']);
    if ($occupation !== '') {
      return $occupation;
    }

    $class = $this->extractNonEmptyString($npc_data, ['class']);
    if ($class === '') {
      return self::DEFAULT_PROFESSION_LABEL;
    }

    $normalized_class = strtolower(trim(str_replace('-', ' ', $class)));
    return in_array($normalized_class, self::GENERIC_NPC_CLASS_VALUES, TRUE)
      ? self::DEFAULT_PROFESSION_LABEL
      : $class;
  }

  /**
   * Resolves deterministic profession seed for a campaign character payload.
   */
  protected function resolveCharacterProfessionValue(array $character_data): string {
    $class = $this->extractNonEmptyString($character_data, ['class']);
    if ($class !== '') {
      return $this->humanizeValue($class);
    }

    return self::DEFAULT_PROFESSION_LABEL;
  }

  /**
   * Extracts the first non-empty string from a keyed array.
   *
   * @param array<string, mixed> $values
   * @param string[] $keys
   */
  protected function extractNonEmptyString(array $values, array $keys): string {
    foreach ($keys as $key) {
      $value = trim((string) ($values[$key] ?? ''));
      if ($value !== '') {
        return $value;
      }
    }

    return '';
  }

  /**
   * Humanizes storage-friendly label values into display labels.
   */
  protected function humanizeValue(string $value): string {
    $normalized = preg_replace('/[_-]+/', ' ', trim($value));
    $normalized = preg_replace('/\s+/', ' ', (string) $normalized);
    return ucwords((string) $normalized);
  }

  /**
   * Builds subject inputs from structured actor affiliation refs.
   *
   * @return array<int, array<string, mixed>>
   *   Structured institution inputs keyed to campaign subject ids or library assets.
   */
  protected function buildExplicitStructuredAffiliationInputs(array $actor_data, string $seed_source): array {
    $inputs = [];

    foreach (self::STRUCTURED_AFFILIATION_REF_FIELDS as $field => $definition) {
      $selections = $this->normalizeStructuredAffiliationSelections($actor_data[$field] ?? [], !empty($definition['multiple']));

      foreach ($selections as $selection) {
        $metadata = is_array($selection['metadata'] ?? NULL) ? $selection['metadata'] : [];
        $metadata += [
          'seed_source' => $seed_source,
          'source_field' => $field,
          'expected_domain' => $definition['domain'],
        ];

        $input = [
          'metadata' => $metadata,
        ];

        $subject_id = trim((string) ($selection['subject_id'] ?? ''));
        if ($subject_id !== '') {
          $input['subject_id'] = $subject_id;
          $inputs[] = $input;
          continue;
        }

        $source_asset_type = trim((string) ($selection['source_asset_type'] ?? ''));
        $source_asset_id = trim((string) ($selection['source_asset_id'] ?? ''));
        if ($source_asset_type === '' || $source_asset_id === '') {
          continue;
        }

        $input += [
          'domain' => trim((string) (($selection['domain'] ?? '') ?: $definition['domain'])),
          'display_name' => trim((string) ($selection['display_name'] ?? $selection['label'] ?? '')),
          'source_asset_type' => $source_asset_type,
          'source_asset_id' => $source_asset_id,
        ];

        $parent_subject_id = trim((string) ($selection['parent_subject_id'] ?? ''));
        if ($parent_subject_id !== '') {
          $input['parent_subject_id'] = $parent_subject_id;
        }

        $entity_ref = trim((string) ($selection['entity_ref'] ?? ''));
        if ($entity_ref !== '') {
          $input['entity_ref'] = $entity_ref;
        }

        $inputs[] = $input;
      }
    }

    return $inputs;
  }

  /**
   * Normalizes structured affiliation selections into a unique flat list.
   *
   * @return array<int, array<string, mixed>>
   *   Unique structured affiliation selections.
   */
  protected function normalizeStructuredAffiliationSelections(mixed $value, bool $multiple): array {
    if (!is_array($value)) {
      $value = [trim((string) $value)];
    }
    elseif (!$multiple || !array_is_list($value)) {
      $value = [$value];
    }

    $normalized = [];
    foreach ($value as $item) {
      if (is_array($item)) {
        $subject_id = trim((string) ($item['subject_id'] ?? ''));
        if ($subject_id !== '') {
          $normalized['subject:' . $subject_id] = [
            'subject_id' => $subject_id,
            'metadata' => is_array($item['metadata'] ?? NULL) ? $item['metadata'] : [],
          ];
          continue;
        }

        $source_asset_type = trim((string) ($item['source_asset_type'] ?? $item['library_asset_type'] ?? ''));
        $source_asset_id = trim((string) ($item['source_asset_id'] ?? $item['library_asset_id'] ?? ''));
        if ($source_asset_type === '' || $source_asset_id === '') {
          continue;
        }

        $display_name = trim((string) ($item['display_name'] ?? $item['label'] ?? ''));
        if ($display_name === '') {
          throw new \InvalidArgumentException(sprintf('Structured affiliation source asset "%s:%s" requires a display name.', $source_asset_type, $source_asset_id));
        }

        $normalized['asset:' . $source_asset_type . ':' . $source_asset_id] = [
          'domain' => trim((string) ($item['domain'] ?? '')),
          'display_name' => $display_name,
          'source_asset_type' => $source_asset_type,
          'source_asset_id' => $source_asset_id,
          'parent_subject_id' => trim((string) ($item['parent_subject_id'] ?? '')),
          'entity_ref' => trim((string) ($item['entity_ref'] ?? '')),
          'metadata' => is_array($item['metadata'] ?? NULL) ? $item['metadata'] : [],
        ];
        continue;
      }

      $subject_id = trim((string) $item);
      if ($subject_id !== '') {
        $normalized['subject:' . $subject_id] = [
          'subject_id' => $subject_id,
          'metadata' => [],
        ];
      }
    }

    return array_values($normalized);
  }

}
