<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/** Searches the character library so a story can reuse who already exists. */
final class FindLibraryCharactersTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'find_library_characters',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Search the character library (full characters and placed town contacts) by name, role, place or description. Use it before adding any character to a storyline, and to find a quest giver.',
      FALSE,
      'StorylineAuthoringDraftService::findLibraryCharacters()',
      [
        EditorGmToolDefinition::argument('search', 'string', FALSE, 'Words that must all appear in the name, role, home location or description. Omit to list.'),
        EditorGmToolDefinition::argument('quest_givers_only', 'boolean', FALSE, 'Only characters whose role is to give the party work.'),
        EditorGmToolDefinition::argument('limit', 'integer', FALSE, 'Maximum characters returned, 1-50. Default 25.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    foreach (['search' => 'is_string', 'quest_givers_only' => 'is_bool', 'limit' => 'is_int'] as $name => $check) {
      if (array_key_exists($name, $arguments) && !$check($arguments[$name])) {
        throw new \InvalidArgumentException('argument_invalid:' . $name);
      }
    }
    return StorylineBuilderGmToolContext::of($context)->drafts->findLibraryCharacters(
      $arguments['search'] ?? '',
      $arguments['quest_givers_only'] ?? FALSE,
      $arguments['limit'] ?? 25,
    );
  }

}
