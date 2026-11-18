<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Database\Connection;

/**
 * The quest entry point to standing: what a quest changes between actors
 * and institutions, written through the services that own each store.
 */
class QuestInstitutionDispositionMutationService {

  /** What a giver thinks of a character who completed, or failed, a quest. */
  public const GIVER_ATTITUDE_DELTA_SUCCESS = 20;
  public const GIVER_ATTITUDE_DELTA_FAILURE = -5;

  /** A character's standing with each authored institution of the giver. */
  public const GIVER_INSTITUTION_DELTA_SUCCESS = 10;
  public const GIVER_INSTITUTION_DELTA_FAILURE = -5;

  public function __construct(
    protected readonly InstitutionDispositionMatrixService $institutionDispositionMatrixService,
    protected readonly Connection $database,
    protected readonly RelationshipAttitudeService $relationshipAttitude,
    protected readonly InstitutionMembershipService $institutionMembership,
    protected readonly CampaignSubjectRegistryService $campaignSubjectRegistry,
    protected readonly InstitutionLibraryService $institutionLibrary,
  ) {}

  /**
   * Applies what finishing a quest does to one character's standing.
   *
   * The giver's attitude toward the character moves, and so does the
   * character's standing with each authored institution the giver belongs
   * to (clan, faction, guild; not ancestry, class or alignment). A
   * successful quest also pays its reputation rewards. The caller makes
   * this once per character and quest.
   *
   * @param array<string, mixed> $quest
   *   The campaign quest row.
   * @param array<int, array{institution: string, amount: int}> $reputation
   *   The quest's reputation rewards; an institution is named by its
   *   library slug or institution ID.
   *
   * @return array{giver_attitude_delta: int, institutions: array<string, int>}
   *   The giver delta applied and each institution's delta by subject ID.
   */
  public function applyQuestCompletionStanding(int $campaign_id, array $quest, string $character_instance_id, bool $success, array $reputation): array {
    $quest_id = trim((string) ($quest['quest_id'] ?? ''));
    $character_instance_id = trim($character_instance_id);
    if ($campaign_id <= 0 || $quest_id === '' || $character_instance_id === '') {
      throw new \InvalidArgumentException('Quest standing requires a campaign, a quest and the character\'s campaign instance.');
    }
    $applied = ['giver_attitude_delta' => 0, 'institutions' => []];
    $deltas = [];

    $giver_row_id = (int) ($quest['giver_npc_id'] ?? 0);
    if ($giver_row_id > 0) {
      $giver = $this->database->select('dc_campaign_characters', 'c')
        ->fields('c', ['instance_id', 'type'])
        ->condition('campaign_id', $campaign_id)
        ->condition('id', $giver_row_id)
        ->execute()
        ->fetchAssoc();
      if (!is_array($giver) || trim((string) $giver['instance_id']) === '') {
        throw new \RuntimeException(sprintf('Quest "%s" names giver %d, who is not an actor of campaign %d.', $quest_id, $giver_row_id, $campaign_id));
      }
      $giver_id = trim((string) $giver['instance_id']);
      $giver_type = in_array(strtolower((string) $giver['type']), ['player', 'character', 'pc'], TRUE) ? 'campaign_character' : 'campaign_npc';
      $delta = $success ? self::GIVER_ATTITUDE_DELTA_SUCCESS : self::GIVER_ATTITUDE_DELTA_FAILURE;
      $this->relationshipAttitude->applyRelationshipDispositionDelta(
        $campaign_id,
        $giver_type,
        $giver_id,
        'campaign_character',
        $character_instance_id,
        $delta,
        $success ? 'Completed a quest for them.' : 'Failed a quest for them.',
        [
          'quest_id' => $quest_id,
          'mutation_source' => 'quest_completion',
          'idempotency_key' => sprintf('quest_completion:%s:%s:%s', $quest_id, $giver_id, $character_instance_id),
        ]
      );
      $applied['giver_attitude_delta'] = $delta;
      // "political" is the sentiment domain of every authored institution.
      foreach ($this->institutionMembership->listActorInstitutionMemberships($campaign_id, $giver_type, $giver_id, 'political') as $membership) {
        $subject_id = trim((string) ($membership['target_id'] ?? ''));
        if ($subject_id !== '') {
          $deltas[$subject_id] = $success ? self::GIVER_INSTITUTION_DELTA_SUCCESS : self::GIVER_INSTITUTION_DELTA_FAILURE;
        }
      }
    }

    if ($success) {
      foreach ($reputation as $reward) {
        $record = $this->institutionLibrary->loadByReference((string) $reward['institution']);
        if ($record === NULL) {
          throw new \RuntimeException(sprintf('Quest "%s" rewards reputation with "%s", which is not in the institution library.', $quest_id, (string) $reward['institution']));
        }
        $subject = $this->campaignSubjectRegistry->resolveOrCreateInstitutionSubject($campaign_id, [
          'domain' => (string) $record['domain'],
          'display_name' => (string) $record['canonicalLabel'],
        ]);
        $subject_id = (string) $subject['subject_id'];
        $deltas[$subject_id] = ($deltas[$subject_id] ?? 0) + (int) $reward['amount'];
      }
    }

    foreach ($deltas as $subject_id => $delta) {
      if ($delta === 0) {
        continue;
      }
      $this->institutionMembership->adjustInstitutionSentiment($campaign_id, 'campaign_character', $character_instance_id, $subject_id, $delta, [
        'reason' => $success ? 'Completed a quest.' : 'Failed a quest.',
        'mutation_source' => 'quest_completion',
        'quest_id' => $quest_id,
      ]);
      $applied['institutions'][$subject_id] = $delta;
    }

    return $applied;
  }

  /**
   * Apply quest-specified institution disposition mutations.
   *
   * @param array<int,array<string,mixed>> $mutations
   *   Directed institution matrix mutation rows.
   * @param array<string,mixed> $context
   *   Shared context metadata.
   *
   * @return array<string,int>
   *   Summary counts.
   */
  public function applyQuestInstitutionDispositionMutations(int $campaign_id, array $mutations, array $context = []): array {
    if ($campaign_id <= 0 || $mutations === []) {
      return [
        'processed' => 0,
        'applied' => 0,
      ];
    }

    $processed = 0;
    $applied = 0;
    foreach ($mutations as $mutation) {
      if (!is_array($mutation)) {
        continue;
      }

      $source_subject_id = trim((string) ($mutation['source_subject_id'] ?? ''));
      $target_subject_id = trim((string) ($mutation['target_subject_id'] ?? ''));
      if ($source_subject_id === '' || $target_subject_id === '') {
        continue;
      }
      if (!isset($mutation['score']) || !is_numeric($mutation['score'])) {
        continue;
      }
      $processed++;
      $score = (int) $mutation['score'];
      $quest_id = trim((string) ($context['quest_id'] ?? ''));
      $storyline_id = trim((string) ($context['storyline_id'] ?? ''));
      $mutation_key = sha1(json_encode([
        'campaign_id' => $campaign_id,
        'quest_id' => $quest_id,
        'storyline_id' => $storyline_id,
        'source_subject_id' => $source_subject_id,
        'target_subject_id' => $target_subject_id,
        'score' => $score,
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '');
      $existing = $this->institutionDispositionMatrixService->loadInstitutionDispositionEdge(
        $campaign_id,
        $source_subject_id,
        $target_subject_id
      );
      $existing_state = is_array($existing['relationship_state'] ?? NULL) ? $existing['relationship_state'] : [];
      if (($existing_state['last_quest_mutation_key'] ?? '') === $mutation_key) {
        continue;
      }

      $applied += $this->institutionDispositionMatrixService->mutateInstitutionDispositionEdge(
        $campaign_id,
        $source_subject_id,
        $target_subject_id,
        $score,
        [
          'seed_source' => 'quest_storyline_mutation',
          'seed_profile_key' => 'quest-storyline-explicit-mutation',
          'knowledge_state' => 'known',
          'rationale' => trim((string) ($mutation['rationale'] ?? ($context['rationale'] ?? 'quest_storyline_mutation'))),
          'mutated' => TRUE,
          'mutated_by_uid' => isset($context['mutated_by_uid']) && is_numeric($context['mutated_by_uid'])
            ? (int) $context['mutated_by_uid']
            : 0,
          'quest_id' => $quest_id,
          'storyline_id' => $storyline_id,
          'last_quest_mutation_key' => $mutation_key,
        ]
      );
    }

    return [
      'processed' => $processed,
      'applied' => $applied,
    ];
  }

}
