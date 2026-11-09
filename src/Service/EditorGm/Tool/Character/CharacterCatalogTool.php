<?php

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Character;

use Drupal\dungeoncrawler_content\Service\EditorGm\CharacterCreationGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;

/**
 * Read-only discovery and full descriptions from the native local catalogs.
 */
final class CharacterCatalogTool implements EditorGmToolInterface {

  public function __construct(private readonly bool $search) {}

  public function definition(): EditorGmToolDefinition {
    $arguments = [
      EditorGmToolDefinition::argument('family', 'string', TRUE, 'Local catalog: ancestry, heritage, background, class, feat, spell, equipment, or familiar_ability.'),
    ];
    if ($this->search) {
      $arguments[] = EditorGmToolDefinition::argument('query', 'string', FALSE, 'Search local IDs, names, descriptions, traits, or other stored rules. Empty lists the catalog. No web access.');
      $arguments[] = EditorGmToolDefinition::argument('limit', 'integer', FALSE, 'Page size, 1-25; default 10.');
      $arguments[] = EditorGmToolDefinition::argument('offset', 'integer', FALSE, 'Nonnegative pagination offset; default 0.');
    }
    else {
      $arguments[] = EditorGmToolDefinition::argument('entry_id', 'string', TRUE, 'Exact local entry ID or full display name, discovered through search or the native wizard.');
      $arguments[] = EditorGmToolDefinition::argument('ancestry', 'string', FALSE, 'For heritage lookups, the parent ancestry ID or name. Supply this when using an ancestry-scoped heritage ID, or use the unique full heritage name.');
    }
    return new EditorGmToolDefinition(
      $this->search ? 'search_character_catalog' : 'get_character_catalog_entry',
      EditorGmToolDefinition::FAMILY_CONTEXT,
      $this->search
        ? 'Search and paginate the existing local character catalogs. Returns complete matching records with untruncated descriptions, benefits, prerequisites, and effects; answer catalog questions from these records.'
        : 'Read the complete local catalog entry, including all stored descriptions, benefits, prerequisites, traits, effects, and nested class options. No external lookup or mutation.',
      FALSE,
      'CharacterCreationGmService / native character, feat, spell, and equipment catalogs',
      $arguments,
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = CharacterCreationGmToolContext::of($context);
    $context->draft();
    $family = EditorGmToolContext::requireString($arguments, 'family');
    return $this->search
      ? $context->drafts->searchCatalog($family, $arguments['query'] ?? '', $arguments['limit'] ?? 10, $arguments['offset'] ?? 0)
      : $context->drafts->getCatalogEntry($family, EditorGmToolContext::requireString($arguments, 'entry_id'), $arguments['ancestry'] ?? NULL);
  }

}
