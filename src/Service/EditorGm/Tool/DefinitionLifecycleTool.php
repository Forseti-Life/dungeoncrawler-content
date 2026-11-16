<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

final class DefinitionLifecycleTool implements EditorGmToolInterface {

  public function __construct(private readonly string $name) {
    if (!in_array($name, ['inspect_definition_lifecycle', 'retire_canonical_definition', 'restore_canonical_definition'], TRUE)) {
      throw new \LogicException('definition_lifecycle_tool_invalid:' . $name);
    }
  }

  public function definition(): EditorGmToolDefinition {
    $mutating = $this->name !== 'inspect_definition_lifecycle';
    $arguments = [
      EditorGmToolDefinition::argument('family', 'string', TRUE, 'Canonical definition family.'),
      EditorGmToolDefinition::argument('definition_id', 'string', TRUE, 'Existing definition identity; resolve from catalog, never invent it.'),
    ];
    if ($mutating) {
      $arguments[] = EditorGmToolDefinition::argument('expected_version', 'string', TRUE, 'Definition version from lifecycle inspection.');
      $arguments[] = EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Lifecycle revision from inspection, not room draft revision.');
      $arguments[] = EditorGmToolDefinition::argument('reason', 'string', TRUE, 'Auditable reason, 1..2000 characters.');
    }
    return new EditorGmToolDefinition(
      $this->name,
      EditorGmToolDefinition::FAMILY_DEFINITION,
      $mutating
        ? 'Administrator-only reversible selection lifecycle change. Existing payloads and published references remain intact; no deletion.'
        : 'Inspect definition eligibility and lifecycle revision before retirement/restoration.',
      $mutating,
      'CanonicalDefinitionService',
      $arguments,
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $family = EditorGmToolContext::requireString($arguments, 'family');
    $id = EditorGmToolContext::requireString($arguments, 'definition_id');
    if ($this->name === 'inspect_definition_lifecycle') {
      return $context->definitions->definitionLifecycle($family, $id);
    }
    return $context->definitions->setDefinitionLifecycle(
      $family, $id,
      $this->name === 'retire_canonical_definition' ? 'retired' : 'active',
      EditorGmToolContext::requireString($arguments, 'expected_version'),
      EditorGmToolContext::requireInt($arguments, 'expected_revision'),
      EditorGmToolContext::requireString($arguments, 'reason'),
      \Drupal::currentUser(),
    );
  }
}
