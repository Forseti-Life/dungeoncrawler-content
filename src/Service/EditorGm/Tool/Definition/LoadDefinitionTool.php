<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Context tool: loads one canonical definition. */
final class LoadDefinitionTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'load_definition',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Load one definition with payload, validation findings and blast radius.',
      FALSE,
      'CanonicalDefinitionService::definitionPayload()',
      [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'Definition family; defaults to the page scope.'),
        EditorGmToolDefinition::argument('definition_id', 'string', FALSE, 'Definition id; defaults to the page scope on edit pages.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DefinitionEditorGmToolContext::of($context);
    $family = $context->scopedFamily($arguments);
    $definition_id = $context->scopedDefinitionId($arguments);
    $payload = $context->definitions->definitionPayload($family, $definition_id);
    return [
      'family' => $family,
      'definition_id' => $definition_id,
      'version' => $context->definitions->currentVersion($family, $definition_id),
      'entry' => $context->definitions->loadCanonicalEntry($family, $definition_id),
      'payload' => $payload,
      'findings' => $context->definitions->validateDefinition($family, $payload),
      'affected_published_rooms' => $context->definitions->publishedRoomsReferencing($family, $definition_id),
    ];
  }

}
