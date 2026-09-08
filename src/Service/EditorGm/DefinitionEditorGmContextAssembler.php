<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;

/**
 * Projects definition-editor state into the GM harness context snapshot.
 */
final class DefinitionEditorGmContextAssembler implements EditorGmContextAssemblerInterface {

  public const AUTHORITY_BOUNDARY = [
    'projection_source' => 'CanonicalDefinitionService',
    'mutation_gateway' => 'CanonicalDefinitionService::saveDefinition()',
    'campaign_runtime_mutation' => 'forbidden',
    'draft_requirement' => 'none: definition scope is family plus optional definition_id',
  ];

  public function __construct(
    private readonly EditorGmToolRegistry $registry,
    private readonly EditorGmIntentParser $intentParser,
  ) {}

  public function assemble(EditorGmToolContext $context): array {
    $context = DefinitionEditorGmToolContext::of($context);
    $validation = $context->validation();
    $catalog = $context->catalog();
    $payload = $context->payload();
    $entry = $context->entry();

    return [
      'tool_id' => DefinitionEditorGmSurface::ID,
      'scope' => [
        'family' => $context->family,
        'definition_id' => $context->definitionId,
      ],
      'definition' => $entry === NULL ? NULL : [
        'family' => $entry['family'],
        'definition_id' => $entry['definition_id'],
        'name' => $entry['name'],
        'category' => $entry['category'],
        'version' => $entry['version'],
        'source_table' => $entry['source_table'],
        'affected_published_rooms' => $context->definitions->publishedRoomsReferencing($context->family, $context->definitionId),
      ],
      'payload' => $payload,
      'family' => [
        'family' => $context->family,
        'total' => $catalog['total'],
        'catalog_version' => $catalog['catalog_version'],
        'id_property' => $context->definitions->idProperty($context->family),
        'name_property' => $context->definitions->nameProperty($context->family),
        'schema_file' => CanonicalDefinitionService::SCHEMA_FILES[$context->family],
        'families' => $context->definitions->families(),
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
