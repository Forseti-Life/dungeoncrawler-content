<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;
use Drupal\dungeoncrawler_content\Service\StorylineInstitutionCatalog;

/** Finds institutions that already exist so a story reuses them. */
final class FindExistingInstitutionsTool implements EditorGmToolInterface {

  public function __construct(
    private readonly StorylineInstitutionCatalog $catalog,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'find_existing_institutions',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Search the institutions that already exist in other storylines, in campaigns and on library characters: clans, families, settlements, factions, guards, guilds, religions and the like. Use it before defining an institution; to reuse one, define it in this storyline with exactly the same domain and name.',
      FALSE,
      'StorylineInstitutionCatalog::find()',
      [
        EditorGmToolDefinition::argument('search', 'string', FALSE, 'Words that must all appear in the name, kind or description. Omit to list.'),
        EditorGmToolDefinition::argument('domain', 'string', FALSE, 'One domain: family, government, allegiance, settlement, religion, employer, education, noble, security, criminal or culture.'),
        EditorGmToolDefinition::argument('limit', 'integer', FALSE, 'Maximum institutions returned, 1-50. Default 25.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    foreach (['search' => 'is_string', 'domain' => 'is_string', 'limit' => 'is_int'] as $name => $check) {
      if (array_key_exists($name, $arguments) && !$check($arguments[$name])) {
        throw new \InvalidArgumentException('argument_invalid:' . $name);
      }
    }
    return $this->catalog->find(
      $arguments['search'] ?? '',
      $arguments['domain'] ?? '',
      StorylineBuilderGmToolContext::of($context)->draftId,
      $arguments['limit'] ?? 25,
    );
  }

}
