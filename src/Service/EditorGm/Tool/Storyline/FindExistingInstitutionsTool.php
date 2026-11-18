<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\InstitutionLibraryService;

/** Finds institutions that already exist so a story reuses them. */
final class FindExistingInstitutionsTool implements EditorGmToolInterface {

  public function __construct(
    private readonly InstitutionLibraryService $library,
  ) {}

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'find_existing_institutions',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      'Search the canonical institution library: every clan, family, settlement, faction, guard, guild, religion and the like that any storyline or the game has defined, and the ancestry, class and alignment institutions. Use it before defining an institution; to reuse one, define it in this storyline with the same domain and name, which gives it the same institution_id. An institution whose membership follows the actor\'s ancestry, profession or alignment is never added to a storyline; it comes from the actor\'s sheet and alignment. They are listed after the authored ones.',
      FALSE,
      'InstitutionLibraryService::find()',
      [
        EditorGmToolDefinition::argument('search', 'string', FALSE, 'Words that must all appear in the name, kind or description. Omit to list.'),
        EditorGmToolDefinition::argument('domain', 'string', FALSE, 'One domain: family, government, allegiance, settlement, religion, employer, education, noble, security, criminal, culture, ancestry, profession or alignment.'),
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
    return $this->library->find(
      $arguments['search'] ?? '',
      $arguments['domain'] ?? '',
      $arguments['limit'] ?? 25,
    );
  }

}
