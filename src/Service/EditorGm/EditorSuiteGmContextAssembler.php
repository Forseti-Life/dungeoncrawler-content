<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

/**
 * Projects the editor suite hub state into the assistant's grounded snapshot.
 */
final class EditorSuiteGmContextAssembler implements EditorGmContextAssemblerInterface {

  public const AUTHORITY_BOUNDARY = [
    'projection_source' => 'EditorSuiteService (read-only over RoomEditorService, DungeonEditorService, CanonicalDefinitionService)',
    'review_flags' => 'dungeoncrawler_content_editor_review_flags (audit hooks write; nobody dismisses)',
    'mutation_gateway' => 'none: the hub owns no draft and registers no mutating tool',
    'campaign_runtime_mutation' => 'forbidden',
  ];

  public function __construct(
    private readonly EditorGmToolRegistry $registry,
    private readonly EditorGmIntentParser $intentParser,
  ) {}

  public function assemble(EditorGmToolContext $context): array {
    $context = EditorSuiteGmToolContext::of($context);
    $summary = $context->summary();
    $validation = $context->validation();

    return [
      'tool_id' => EditorSuiteGmSurface::ID,
      'suite' => [
        'generated_at' => $summary['generated_at'],
        'surfaces' => array_map(static fn(array $s): array => array_diff_key($s, ['route' => 1, 'may_access' => 1]), $summary['surfaces']),
        'recent' => array_map(static fn(array $r): array => [
          'kind' => $r['kind'],
          'id' => $r['id'],
          'draft_id' => $r['draft_id'],
          'label' => $r['label'],
          'state' => $r['state'],
          'revision' => $r['revision'],
          'updated_at' => gmdate(DATE_RFC3339, $r['updated_at']),
        ], $summary['recent']),
        'attention' => array_map(static fn(array $a): array => [
          'code' => $a['code'],
          'severity' => $a['severity'],
          'surface_id' => $a['surface_id'],
          'count' => $a['count'],
        ], $summary['attention']),
      ],
      'validation_summary' => [
        'profile' => $validation['profile'],
        'valid' => $validation['is_valid'],
        'error_count' => $validation['counts']['error'],
        'warning_count' => $validation['counts']['warning'],
        'info_count' => $validation['counts']['info'],
      ],
      'authority_boundary' => self::AUTHORITY_BOUNDARY,
      'assistant' => [
        'natural_language_available' => $this->intentParser->isAvailable(),
        'natural_language_may_mutate' => FALSE,
      ],
      'tools' => $this->registry->manifest(),
    ];
  }

}
