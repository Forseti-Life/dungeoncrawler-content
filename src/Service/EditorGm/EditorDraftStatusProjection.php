<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Builds the draft status projection shared by the Dungeon and Room editors.
 *
 * Same shape as StorylineAuthoringDraftService::summarizeDraftState(). Each
 * editor supplies its own checklist and stage checks, computed from the saved
 * draft and its validation; this only derives the totals from them.
 */
final class EditorDraftStatusProjection {

  /**
   * @param array $draft
   *   The saved draft: draft_id, revision, status.
   * @param array $validation
   *   Validation in the editor result shape: profile, is_valid, counts.
   * @param array<int, array{id: string, label: string, complete: bool, group: string, detail: string}> $checklist
   *   Items in group minimum_content feed missing_content.
   * @param array<int, array{label: string, checks: bool[], metrics: array}> $stages
   *   Keyed by stage number from 1; the last stage is publication.
   */
  public static function project(string $schema_version, array $draft, array $validation, array $checklist, array $stages, array $counts): array {
    $last_stage = array_key_last($stages);
    $current_stage = $last_stage;
    $stage_rows = [];
    foreach ($stages as $number => $stage) {
      $done = count(array_filter($stage['checks']));
      $total = count($stage['checks']);
      if ($current_stage === $last_stage && $number < $last_stage && $done !== $total) {
        $current_stage = $number;
      }
      $stage_rows[] = [
        'stage' => $number,
        'label' => $stage['label'],
        'completion_percent' => (int) round(($done / $total) * 100),
        'complete' => $done === $total,
        'complete_checks' => $done,
        'total_checks' => $total,
        'metrics' => $stage['metrics'],
      ];
    }

    return [
      'schema_version' => $schema_version,
      'draft_id' => (string) $draft['draft_id'],
      'revision' => (int) $draft['revision'],
      'status' => (string) $draft['status'],
      'validation_profile' => (string) $validation['profile'],
      'completion_percent' => (int) round(array_sum(array_column($stage_rows, 'completion_percent')) / count($stage_rows)),
      'completed_checks' => count(array_filter($checklist, static fn(array $item): bool => $item['complete'])),
      'total_checks' => count($checklist),
      'canonical_valid' => $validation['is_valid'],
      'error_count' => $validation['counts']['error'],
      'warning_count' => $validation['counts']['warning'],
      'current_stage' => $current_stage,
      'counts' => $counts,
      'stages' => $stage_rows,
      'missing_content' => array_values(array_map(
        static fn(array $item): string => $item['label'],
        array_filter($checklist, static fn(array $item): bool => $item['group'] === 'minimum_content' && !$item['complete']),
      )),
      'checklist' => $checklist,
    ];
  }

}
