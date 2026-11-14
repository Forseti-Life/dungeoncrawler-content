<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Actor;

use Drupal\dungeoncrawler_content\Service\Actor\ActorDefinitionMirror;
use Drupal\dungeoncrawler_content\Service\EditorGm\DefinitionEditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Shared actor tools for canonical actor definitions.
 *
 * Same tool names and command contract as the character surface. The actor is
 * the library row that mirrors the definition; an applied change is saved by
 * the definition service, which commits that row through the actor authority.
 */
final class DefinitionActorTool implements EditorGmToolInterface {

  private const OPERATIONS = ['inspect_actor', 'plan_actor_changes', 'apply_actor_changes'];

  public function __construct(private readonly string $operation) {
    if (!in_array($operation, self::OPERATIONS, TRUE)) {
      throw new \LogicException('Unsupported actor tool: ' . $operation);
    }
  }

  public function definition(): EditorGmToolDefinition {
    $actor = EditorGmToolDefinition::argument('actor_id', 'string', FALSE, 'Canonical actor definition id. Optional when the editor is already scoped to one actor definition.');
    if ($this->operation === 'inspect_actor') {
      return new EditorGmToolDefinition(
        'inspect_actor',
        EditorGmToolDefinition::FAMILY_CONTEXT,
        'Inspect a canonical actor definition in the actor contract: identity, build, narrative, defaults, revision, validation checks, the paths that can be changed, and recent change history. Read-only.',
        FALSE,
        'ActorDefinitionMirror::inspect',
        [$actor],
      );
    }
    $applying = $this->operation === 'apply_actor_changes';

    return new EditorGmToolDefinition(
      $this->operation,
      $applying ? EditorGmToolDefinition::FAMILY_EXECUTION : EditorGmToolDefinition::FAMILY_PLANNING,
      $applying
        ? 'Execute one typed actor patch on a canonical actor definition. The result is validated against the definition schema and the actor contract; returns a committed receipt with next steps, or a typed failure that changed nothing.'
        : 'Preview one typed actor patch on a canonical actor definition: exact changed paths with before and after values. Read-only.',
      $applying,
      'ActorDefinitionMirror::command',
      [
        $actor,
        EditorGmToolDefinition::argument('type', 'string', TRUE, 'actor.identity.patch or actor.narrative.patch.'),
        EditorGmToolDefinition::argument('operations', 'array', TRUE, 'List of {"op": "set", "path": "/narrative/biography", "value": "..."} or {"op": "unset", "path": "..."}. Paths: ' . implode(', ', array_keys(ActorDefinitionMirror::DEFINITION_PATHS)) . '.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $actor_id = isset($arguments['actor_id']) ? EditorGmToolContext::requireString($arguments, 'actor_id') : NULL;
    if ($actor_id === NULL && $context instanceof DefinitionEditorGmToolContext && $context->family === 'actor') {
      $actor_id = $context->definitionId;
    }
    if ($actor_id === NULL) {
      throw new \InvalidArgumentException('argument_required:actor_id');
    }
    $mirror = \Drupal::service('dungeoncrawler_content.actor_definition_mirror');
    $principal = \Drupal::currentUser();
    if ($this->operation === 'inspect_actor') {
      return $mirror->inspect($actor_id, $principal);
    }
    $result = $mirror->command(
      $actor_id,
      EditorGmToolContext::requireString($arguments, 'type'),
      EditorGmToolContext::requireArray($arguments, 'operations'),
      $this->operation === 'apply_actor_changes',
      $principal,
    );
    if ($this->operation === 'apply_actor_changes' && !empty($result['committed'])) {
      $context->invalidate();
    }

    return $result;
  }

}
