<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Applies explicit quest/storyline-driven institution matrix mutations.
 */
class QuestInstitutionDispositionMutationService {

  public function __construct(
    protected readonly InstitutionDispositionMatrixService $institutionDispositionMatrixService,
  ) {}

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
