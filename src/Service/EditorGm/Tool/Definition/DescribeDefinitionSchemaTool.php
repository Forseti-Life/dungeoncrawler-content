<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Definition;

use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/** Context tool: describes a family schema in assistant-grounding form. */
final class DescribeDefinitionSchemaTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'describe_definition_schema',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Return the family JSON Schema and a flattened field description list.',
      FALSE,
      'CanonicalDefinitionService::schemaForFamily()',
      [
        EditorGmToolDefinition::argument('family', 'string', FALSE, 'Definition family; defaults to the page scope.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = DefinitionEditorGmToolContext::of($context);
    $family = $context->scopedFamily($arguments);
    $schema = $context->definitions->schemaForFamily($family);
    return [
      'family' => $family,
      'id_property' => $context->definitions->idProperty($family),
      'name_property' => $context->definitions->nameProperty($family),
      'schema_file' => CanonicalDefinitionService::SCHEMA_FILES[$family],
      'schema' => $schema,
      'fields' => $this->fields($schema),
    ];
  }

  private function fields(array $schema, string $pointer = ''): array {
    if (isset($schema['$ref'])) {
      return [[
        'pointer' => $pointer === '' ? '/' : $pointer,
        'type' => '$ref',
        'required' => FALSE,
        'description' => (string) $schema['$ref'],
      ]];
    }
    $type = $schema['type'] ?? 'object';
    if ($type !== 'object') {
      return [[
        'pointer' => $pointer === '' ? '/' : $pointer,
        'type' => is_array($type) ? implode('|', $type) : (string) $type,
        'required' => FALSE,
        'enum' => $schema['enum'] ?? NULL,
        'description' => (string) ($schema['description'] ?? ''),
      ]];
    }
    $required = array_flip($schema['required'] ?? []);
    $fields = [];
    foreach ($schema['properties'] ?? [] as $name => $child) {
      if (!is_array($child)) {
        continue;
      }
      $child_pointer = $pointer . '/' . str_replace(['~', '/'], ['~0', '~1'], (string) $name);
      $child_type = $child['type'] ?? (isset($child['$ref']) ? '$ref' : 'object');
      $fields[] = [
        'pointer' => $child_pointer,
        'type' => is_array($child_type) ? implode('|', $child_type) : (string) $child_type,
        'required' => isset($required[$name]),
        'enum' => $child['enum'] ?? NULL,
        'description' => (string) ($child['description'] ?? ($child['$ref'] ?? '')),
      ];
      if (($child['type'] ?? NULL) === 'object') {
        $fields = array_merge($fields, $this->fields($child, $child_pointer));
      }
    }
    return $fields;
  }

}
