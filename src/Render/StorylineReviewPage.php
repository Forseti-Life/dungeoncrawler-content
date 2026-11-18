<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Render;

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Puts a read-only page about one storyline draft inside the shared page.
 *
 * Used by the scene, actor-graph and institution review pages and by the
 * page of one actor of a draft.
 */
final class StorylineReviewPage {

  /**
   * @param array $page
   *   The page content.
   * @param array $draft
   *   The draft as StorylineAuthoringDraftService::getDraftForViewing()
   *   returns it.
   * @param string $active
   *   Which page this is: scenes, actors or institutions.
   */
  public static function wrap(array $page, array $draft, string $active): array {
    $payload = is_array($draft['payload'] ?? NULL) ? $draft['payload'] : [];
    $query = ['query' => ['draft_id' => (string) $draft['draft_id']]];
    $count = static fn(mixed $rows): string => (string) count(array_filter((array) $rows, 'is_array'));
    $link = static fn(string $key, string $label, string $description, string $route): array => [
      'label' => t($label),
      'description' => t($description),
      'url' => Url::fromRoute($route, [], $query)->toString(),
      'active' => $key === $active,
    ];
    $shell = EditorPageShell::split('@dungeoncrawler_content/storyline-review-page.html.twig', [
      'storyline_title' => trim((string) ($payload['title'] ?? '')),
      'builder_url' => Url::fromRoute('dungeoncrawler_content.gm_storyline_builder', [], $query)->toString(),
      'pages' => [
        $link('draft', 'Storyline draft', 'Edit with the Game Master Agent.', 'dungeoncrawler_content.gm_storyline_builder'),
        $link('scenes', 'Scenes in order', 'What happens, where, and what it leads to.', 'dungeoncrawler_content.gm_storyline_scene_review'),
        $link('actors', 'Actors and relationships', 'The cast and how they stand to each other.', 'dungeoncrawler_content.gm_storyline_actor_graph'),
        $link('institutions', 'Institutions', 'The groups the cast belongs to.', 'dungeoncrawler_content.gm_storyline_institution_review'),
      ],
      'facts' => [
        ['label' => t('Revision'), 'value' => (string) (int) ($draft['revision'] ?? 0)],
        ['label' => t('Scenes'), 'value' => $count($payload['process_flow']['journal_entries'] ?? [])],
        ['label' => t('Tasks'), 'value' => $count($payload['process_flow']['tasks'] ?? [])],
        ['label' => t('Actors'), 'value' => $count($payload['objects']['actors']['generation_specs'] ?? [])],
        ['label' => t('Institutions'), 'value' => $count($payload['objects']['institutions'] ?? [])],
        ['label' => t('Rooms'), 'value' => $count($payload['objects']['rooms']['generation_specs'] ?? [])],
      ],
      'quick_links' => [
        ['label' => t('Storyline Manager'), 'url' => Url::fromRoute('dungeoncrawler_content.storyline_management')->toString()],
        ['label' => t('Dungeon Manager'), 'url' => Url::fromRoute('dungeoncrawler_content.dungeon_management')->toString()],
        ['label' => t('Room Manager'), 'url' => Url::fromRoute('dungeoncrawler_content.room_management')->toString()],
        ['label' => t('Canonical Library'), 'url' => Url::fromRoute('dungeoncrawler_content.definition_index')->toString()],
      ],
    ]);
    return [
      '#prefix' => Markup::create($shell['prefix']),
      '#suffix' => Markup::create($shell['suffix']),
      '#attached' => ['library' => ['dungeoncrawler_content/character-setup-tabs', 'dungeoncrawler_content/editor-gm-chat-style']],
      'page' => $page,
    ];
  }

}
