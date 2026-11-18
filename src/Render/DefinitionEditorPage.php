<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Render;

use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;

/**
 * Puts Definition Editor content inside the shared editor page.
 *
 * The one place the Definition Editor's page is laid out. The library index,
 * a family list and the definition form all call wrap() and place their own
 * table or form between the two halves it returns.
 */
final class DefinitionEditorPage {

  /**
   * @param string $page_name
   *   What the workspace heading names.
   * @param string|null $family
   *   The family in view, or NULL on the library index.
   * @param array<int, array{label: string, value: string}> $facts
   *   Rows of the Current state card.
   * @param int|null $percent
   *   Share that conforms to its schema, or NULL when the page has none.
   * @param string[] $needed
   *   What is still needed, in author terms.
   * @param array<int, array{label: string, url: string}> $links
   *   Links for this family or definition.
   *
   * @return array{prefix: string, suffix: string}
   *   Markup before and after the page content.
   */
  public static function wrap(CanonicalDefinitionService $definitions, string $page_name, ?string $family, array $facts, ?int $percent = NULL, array $needed = [], array $links = []): array {
    $families = [];
    foreach ($definitions->families() as $id) {
      $families[] = [
        'id' => $id,
        'label' => ucfirst($id),
        'count' => (int) $definitions->catalog($id, '', 1, 0)['total'],
        'url' => Url::fromRoute('dungeoncrawler_content.definition_family', ['family' => $id])->toString(),
        'active' => $id === $family,
      ];
    }
    $quick = static fn(string $label, string $route): array => ['label' => t($label), 'url' => Url::fromRoute($route)->toString()];
    return EditorPageShell::split('@dungeoncrawler_content/definition-editor-page.html.twig', [
      'families' => $families,
      'page_name' => $page_name,
      'percent' => $percent,
      'facts' => $facts,
      'needed' => $needed,
      'family_links' => $links,
      'quick_links' => [
        $quick('Canonical Library', 'dungeoncrawler_content.definition_index'),
        $quick('Storyline Manager', 'dungeoncrawler_content.storyline_management'),
        $quick('Dungeon Manager', 'dungeoncrawler_content.dungeon_management'),
        $quick('Room Manager', 'dungeoncrawler_content.room_management'),
      ],
    ]);
  }

}
