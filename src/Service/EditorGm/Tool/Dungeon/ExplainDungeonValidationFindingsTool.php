<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Dungeon;

use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmSurface;
use Drupal\dungeoncrawler_content\Service\EditorGm\DungeonEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Groups dungeon validation findings by code, severity and subject.
 */
final class ExplainDungeonValidationFindingsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'explain_validation_findings',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Group current validation findings by code and severity, listing the placements, links, regions and hexes each finding names.',
      FALSE,
      'DungeonEditorService::validateDraft()',
      [
        EditorGmToolDefinition::argument('profile', 'string', FALSE, 'One of ' . implode(', ', DungeonEditorGmSurface::VALIDATION_PROFILES) . '.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DungeonEditorGmToolContext::of($context);
    $profile = isset($arguments['profile']) ? (string) $arguments['profile'] : $context->validationProfile;
    if (!in_array($profile, DungeonEditorGmSurface::VALIDATION_PROFILES, TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    $validation = $context->validation($profile);

    $by_code = [];
    foreach ((array) ($validation['findings'] ?? []) as $finding) {
      $code = (string) ($finding['code'] ?? 'unknown');
      $by_code[$code] ??= [
        'code' => $code,
        'severity' => (string) ($finding['severity'] ?? 'error'),
        'count' => 0,
        'messages' => [],
        'subjects' => [],
        'hexes' => [],
      ];
      $by_code[$code]['count']++;
      $message = (string) ($finding['message'] ?? '');
      if ($message !== '' && !in_array($message, $by_code[$code]['messages'], TRUE)) {
        $by_code[$code]['messages'][] = $message;
      }
      foreach ((array) ($finding['subjects'] ?? []) as $subject) {
        if (!in_array($subject, $by_code[$code]['subjects'], TRUE)) {
          $by_code[$code]['subjects'][] = $subject;
        }
      }
      if (isset($finding['hex']) && !in_array($finding['hex'], $by_code[$code]['hexes'], TRUE)) {
        $by_code[$code]['hexes'][] = $finding['hex'];
      }
    }
    ksort($by_code);

    return [
      'profile' => $validation['profile'],
      'is_valid' => (bool) $validation['is_valid'],
      'counts' => $validation['counts'],
      'by_code' => array_values($by_code),
    ];
  }

}
