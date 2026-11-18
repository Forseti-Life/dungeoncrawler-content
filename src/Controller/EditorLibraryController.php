<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Dungeon Manager and Room Manager: the library pages beside the editors.
 *
 * The same shape as the Storyline Manager: totals, the published library,
 * then working drafts with the status the editor itself reports.
 */
final class EditorLibraryController extends ControllerBase {

  public function __construct(
    private readonly DungeonEditorService $dungeonEditor,
    private readonly RoomEditorService $roomEditor,
    private readonly StorylineAuthoringDraftService $storylines,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.dungeon_editor'),
      $container->get('dungeoncrawler_content.room_editor'),
      $container->get('dungeoncrawler_content.storyline_authoring_drafts'),
    );
  }

  public function dungeons(): array {
    $stories = $this->storylines->placeUsageIndex()['dungeons'];
    $all_drafts = $this->currentUser()->hasPermission('administer dungeoncrawler content')
      || $this->currentUser()->hasPermission('publish canonical dungeoncrawler dungeons');
    $drafts = $this->dungeonEditor->listDrafts($all_drafts ? NULL : (int) $this->currentUser()->id());
    $draft_by_dungeon = array_column($drafts, NULL, 'dungeon_id');
    $edit_url = static fn(string $dungeon_id): string => Url::fromRoute('dungeoncrawler_content.dungeon_editor_edit', ['dungeon_id' => $dungeon_id])->toString();

    $entries = [];
    $unattached = 0;
    foreach ($this->dungeonEditor->listDungeons() as $dungeon) {
      $id = (string) $dungeon['dungeon_id'];
      $published = trim((string) $dungeon['published_version_id']) !== '';
      $has_draft = isset($draft_by_dungeon[$id]);
      $unattached += $published && !isset($stories[$id]) ? 1 : 0;
      $entries[] = [
        'id' => $id,
        'name' => (string) $dungeon['name'],
        'state' => $published ? ($has_draft ? 'Published + Draft' : 'Published') : ($has_draft ? 'Draft' : 'No editor version'),
        'facts' => array_values(array_filter([
          $published ? sprintf('Version %s', $dungeon['published_version']) : '',
          $published ? $this->formatPlural($dungeon['published_room_count'], '1 room', '@count rooms') : '',
        ])),
        'storylines' => $this->storyLinks($stories[$id] ?? []),
        'edit_url' => $edit_url($id),
        'action_label' => $has_draft ? 'Continue Draft' : ($published ? 'Open in Dungeon Editor' : 'Rebuild in Dungeon Editor'),
      ];
    }

    return $this->page(
      'Dungeons',
      'Dungeon',
      'One shared library of sites. A dungeon is laid out from published rooms and may serve many storylines. Publishing is a separate action from saving a draft.',
      [
        ['value' => count(array_filter($entries, static fn(array $entry): bool => str_starts_with($entry['state'], 'Published'))), 'label' => 'Published dungeons'],
        ['value' => count($drafts), 'label' => 'Working drafts'],
        ['value' => $unattached, 'label' => 'Published, in no storyline'],
      ],
      $entries,
      array_map(fn(array $draft): array => $this->draftRow(
        (string) $draft['name'],
        $draft,
        $edit_url((string) $draft['dungeon_id']),
        fn(): array => $this->dungeonEditor->summarizeDraftState(
          $this->dungeonEditor->getDraft((string) $draft['draft_id']),
          $this->dungeonEditor->validateDraft((string) $draft['draft_id'], 'editing'),
        ),
      ), $drafts),
      Url::fromRoute('dungeoncrawler_content.dungeon_editor')->toString(),
      ['label' => 'Rooms', 'url' => Url::fromRoute('dungeoncrawler_content.room_management')->toString()],
    );
  }

  public function rooms(): array {
    $stories = $this->storylines->placeUsageIndex()['rooms'];
    $placed = $this->dungeonEditor->roomPlacementIndex();
    $drafts = $this->roomEditor->listAccessibleDrafts();
    $draft_by_room = array_column(array_filter($drafts, static fn(array $draft): bool => (string) $draft['room_id'] !== ''), NULL, 'room_id');
    $edit_url = static fn(string $room_id): string => Url::fromRoute('dungeoncrawler_content.room_editor_edit', ['room_id' => $room_id])->toString();

    $entries = [];
    $unplaced = 0;
    foreach ($this->roomEditor->listRooms() as $room) {
      $id = (string) $room['room_id'];
      $published = trim((string) $room['published_version_id']) !== '';
      $has_draft = isset($draft_by_room[$id]);
      $dungeons = $placed[$id] ?? [];
      $unplaced += $published && $dungeons === [] ? 1 : 0;
      $entries[] = [
        'id' => $id,
        'name' => (string) $room['name'],
        'state' => $published ? ($has_draft ? 'Published + Draft' : 'Published') : ($has_draft ? 'Draft' : 'Unpublished'),
        'facts' => [$dungeons === []
          ? 'In no published dungeon'
          : (string) $this->formatPlural(count($dungeons), 'In 1 dungeon: @names', 'In @count dungeons: @names', ['@names' => implode(', ', array_unique(array_column($dungeons, 'name')))]),
        ],
        'storylines' => $this->storyLinks($stories[$id] ?? []),
        'edit_url' => $edit_url($id),
        'action_label' => $has_draft ? 'Continue Draft' : 'Open in Room Editor',
      ];
    }

    return $this->page(
      'Rooms',
      'Room',
      'One shared library of rooms. A room is published once and may be placed in many dungeons and named by many storylines; check where it is used before changing it.',
      [
        ['value' => count(array_filter($entries, static fn(array $entry): bool => str_starts_with($entry['state'], 'Published'))), 'label' => 'Published rooms'],
        ['value' => count($drafts), 'label' => 'Working drafts'],
        ['value' => $unplaced, 'label' => 'Published, in no dungeon'],
      ],
      $entries,
      array_map(fn(array $draft): array => $this->draftRow(
        (string) $draft['name'],
        $draft,
        (string) $draft['room_id'] !== ''
          ? $edit_url((string) $draft['room_id'])
          : Url::fromRoute('dungeoncrawler_content.room_editor', [], ['query' => ['draft_id' => (string) $draft['draft_id']]])->toString(),
        fn(): array => $this->roomEditor->summarizeDraftState(
          $this->roomEditor->getDraft((string) $draft['draft_id']),
          $this->roomEditor->validateDraft((string) $draft['draft_id'], 'editing'),
        ),
      ), $drafts),
      Url::fromRoute('dungeoncrawler_content.room_editor')->toString(),
      ['label' => 'Dungeons', 'url' => Url::fromRoute('dungeoncrawler_content.dungeon_management')->toString()],
    );
  }

  /**
   * One working draft with the status its editor reports.
   *
   * A draft whose stored content the editor cannot read is listed with the
   * editor's error code instead of a status, so it is seen rather than
   * hidden.
   */
  private function draftRow(string $name, array $draft, string $edit_url, callable $status): array {
    $row = [
      'name' => $name !== '' ? $name : 'Untitled',
      'id' => (string) ($draft['dungeon_id'] ?? $draft['room_id'] ?? ''),
      'revision' => (int) $draft['revision'],
      'updated' => date('M j, Y g:i a', (int) $draft['updated_at']),
      'updated_by' => (string) (User::load((int) $draft['updated_by'])?->getDisplayName() ?? ''),
      'edit_url' => $edit_url,
      'percent' => NULL,
      'validity' => '',
      'next_step' => '',
    ];
    try {
      $state = $status();
    }
    catch (\RuntimeException | \DomainException | \InvalidArgumentException | \JsonException $exception) {
      return ['validity' => 'Cannot be read: ' . $exception->getMessage()] + $row;
    }
    return [
      'percent' => (int) $state['completion_percent'],
      'validity' => $state['canonical_valid']
        ? sprintf('Valid, %d warnings', $state['warning_count'])
        : sprintf('%d validation errors', $state['error_count']),
      'next_step' => (string) ($state['next_steps'][0] ?? ''),
    ] + $row;
  }

  private function storyLinks(array $stories): array {
    return array_map(static fn(array $story): array => [
      'title' => $story['title'] !== '' ? $story['title'] : 'Untitled storyline',
      'status' => $story['status'],
      'url' => Url::fromRoute('dungeoncrawler_content.gm_storyline_scene_review', [], ['query' => ['draft_id' => $story['draft_id']]])->toString(),
    ], $stories);
  }

  private function page(string $title, string $noun, string $intro, array $metrics, array $entries, array $drafts, string $editor_url, array $sibling): array {
    return [
      '#theme' => 'editor_library_list',
      '#title' => $title,
      '#noun' => $noun,
      '#intro' => $intro,
      '#metrics' => $metrics,
      '#entries' => $entries,
      '#drafts' => $drafts,
      '#editor_url' => $editor_url,
      '#sibling' => $sibling,
      '#storylines_url' => Url::fromRoute('dungeoncrawler_content.storyline_management')->toString(),
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/character-sheet',
          'dungeoncrawler_content/storyline-management',
          'dungeoncrawler_content/editor-library',
        ],
      ],
      '#cache' => ['max-age' => 0],
    ];
  }

}
