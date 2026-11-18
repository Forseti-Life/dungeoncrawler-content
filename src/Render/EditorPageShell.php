<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Render;

/**
 * Lays a page out in the shared editor page and leaves room for its content.
 *
 * A page whose content is a render array or a Drupal form cannot be written
 * inside a template. Its template embeds editor-page-shell.html.twig and
 * prints `content_marker` where the content belongs; split() returns the
 * markup before and after that point, for use as #prefix and #suffix.
 */
final class EditorPageShell {

  private const CONTENT_MARKER = '<!--editor-page-content-->';

  /**
   * @param string $template
   *   A template that embeds the shared page, e.g.
   *   '@dungeoncrawler_content/definition-editor-page.html.twig'.
   *
   * @return array{prefix: string, suffix: string}
   */
  public static function split(string $template, array $context): array {
    $html = (string) \Drupal::service('twig')->render($template, $context + ['content_marker' => self::CONTENT_MARKER]);
    $parts = explode(self::CONTENT_MARKER, $html);
    if (count($parts) !== 2) {
      throw new \LogicException('editor_page_content_marker_missing:' . $template);
    }
    return ['prefix' => $parts[0], 'suffix' => $parts[1]];
  }

}
