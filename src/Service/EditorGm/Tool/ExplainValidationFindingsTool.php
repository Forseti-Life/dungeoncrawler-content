<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Validation tool: groups deterministic findings into an explainable shape.
 *
 * This never invents findings. It only regroups what validateDraft() produced
 * so the assistant panel can render findings by code and by path.
 */
final class ExplainValidationFindingsTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'explain_validation_findings',
      EditorGmToolDefinition::FAMILY_VALIDATION,
      'Group current validation findings by code and path for review.',
      FALSE,
      'RoomEditorService::validateDraft()',
      [
        EditorGmToolDefinition::argument('profile', 'string', FALSE, 'One of editing, preview, publication.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $profile = isset($arguments['profile'])
      ? (string) $arguments['profile']
      : $context->validationProfile;
    if (!in_array($profile, ['editing', 'preview', 'publication'], TRUE)) {
      throw new \InvalidArgumentException('validation_profile_invalid');
    }
    $validation = $context->validation($profile);

    $by_code = [];
    $by_path = [];
    foreach (['errors', 'warnings'] as $bucket) {
      foreach ((array) ($validation[$bucket] ?? []) as $finding) {
        $code = (string) ($finding['code'] ?? 'unknown');
        $path = (string) ($finding['path'] ?? '');
        $by_code[$code] ??= ['code' => $code, 'severity' => (string) ($finding['severity'] ?? 'error'), 'count' => 0, 'messages' => []];
        $by_code[$code]['count']++;
        $message = (string) ($finding['message'] ?? '');
        if ($message !== '' && !in_array($message, $by_code[$code]['messages'], TRUE)) {
          $by_code[$code]['messages'][] = $message;
        }
        $by_path[$path] = ($by_path[$path] ?? 0) + 1;
      }
    }

    return [
      'profile' => $profile,
      'valid' => (bool) ($validation['valid'] ?? FALSE),
      'error_count' => count((array) ($validation['errors'] ?? [])),
      'warning_count' => count((array) ($validation['warnings'] ?? [])),
      'by_code' => array_values($by_code),
      'by_path' => $by_path,
    ];
  }

}
