<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Actor;

use Drupal\dungeoncrawler_content\Service\Actor\ActorCommandProcessor;
use Drupal\dungeoncrawler_content\Service\EditorGm\CharacterCreationGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Shared actor tools: thin adapters over the actor command authority.
 *
 * The tools hold no rules and no persistence. inspect_actor and
 * plan_actor_changes are read-only; apply_actor_changes executes a typed
 * command that the authority validates, revisions and records.
 */
final class ActorTool implements EditorGmToolInterface {

  private const OPERATIONS = ['inspect_actor', 'plan_actor_changes', 'apply_actor_changes'];

  public function __construct(private readonly string $operation) {
    if (!in_array($operation, self::OPERATIONS, TRUE)) {
      throw new \LogicException('Unsupported actor tool: ' . $operation);
    }
  }

  public function definition(): EditorGmToolDefinition {
    if ($this->operation === 'inspect_actor') {
      return new EditorGmToolDefinition(
        'inspect_actor',
        EditorGmToolDefinition::FAMILY_CONTEXT,
        'Inspect the actor in the canonical actor contract: identity, build, narrative, defaults, revision, validation checks, the commands and paths that can change it, and recent change history. Read-only; never creates or repairs anything.',
        FALSE,
        'CharacterManager::inspectActor',
      );
    }
    $applying = $this->operation === 'apply_actor_changes';
    $types = implode(', ', array_merge(array_keys(ActorCommandProcessor::PATHS), ['actor.archive', 'actor.restore']));
    // Wizard choices are the typed command actor.build.apply_choices, which
    // the dedicated character draft tools already execute with their own
    // choice reference; it is not exposed twice here.

    return new EditorGmToolDefinition(
      $this->operation,
      $applying ? EditorGmToolDefinition::FAMILY_EXECUTION : EditorGmToolDefinition::FAMILY_PLANNING,
      $applying
        ? 'Execute one typed actor command. The actor authority locks the actor, applies only the requested operations, validates the whole result, and returns a committed receipt with next steps, or a typed failure that changed nothing.'
        : 'Preview one typed actor command against the current actor: exact changed paths with before and after values, validation checks and next steps. Read-only.',
      $applying,
      'CharacterManager::' . ($applying ? 'executeActorCommand' : 'previewActorCommand'),
      [
        EditorGmToolDefinition::argument('type', 'string', TRUE, 'Command type: ' . $types . '. Use inspect_actor to see the paths each patch command accepts.'),
        EditorGmToolDefinition::argument('operations', 'array', FALSE, 'For patch commands: list of {"op": "set", "path": "/narrative/biography", "value": "..."} or {"op": "unset", "path": "..."}. Omit for actor.archive and actor.restore.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = CharacterCreationGmToolContext::of($context);
    $character_id = (int) $context->draft()['character_id'];
    if ($this->operation === 'inspect_actor') {
      return $context->drafts->inspectActor($character_id);
    }
    $type = EditorGmToolContext::requireString($arguments, 'type');
    $payload = array_key_exists('operations', $arguments)
      ? ['operations' => EditorGmToolContext::requireArray($arguments, 'operations')]
      : [];
    $result = $context->drafts->actorCommand($character_id, $type, $payload, $this->operation === 'apply_actor_changes');
    if ($this->operation === 'apply_actor_changes' && !empty($result['committed'])) {
      $context->invalidate();
    }

    return $result;
  }

}
