<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Storyline management hub mirroring campaign/character management pages.
 */
class StorylineManagementController extends ControllerBase {

  public function __construct(
    protected Connection $database,
    protected StorylineManagerService $storylineManager,
    protected StorylineAuthoringDraftService $drafts,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('database'),
      $container->get('dungeoncrawler_content.storyline_manager'),
      $container->get('dungeoncrawler_content.storyline_authoring_drafts'),
    );
  }

  /**
   * Render storyline management page.
   */
  public function listStorylines(): array {
    $uid = (int) $this->currentUser()->id();
    $my_storylines = [];
    $other_storylines = [];
    $my_drafts = [];
    $shared_drafts = [];
    $can_edit_storylines = $this->currentUser()->hasPermission('edit canonical dungeoncrawler storylines');
    try {
      foreach ($this->storylineManager->listTemplates() as $template) {
        $template_id = trim((string) ($template['template_id'] ?? ''));
        if ($template_id === '') {
          continue;
        }

        $latest_draft = $can_edit_storylines
          ? $this->drafts->findLatestDraftForTemplate($template_id)
          : NULL;
        $completeness = NULL;
        if ($can_edit_storylines && $latest_draft !== NULL && !empty($latest_draft['draft_id'])) {
          try {
            $completeness = $this->completenessFromDraftStatus(
              $this->drafts->summarizeDraftStatus((string) $latest_draft['draft_id'])
            );
          }
          catch (\Throwable) {
            $completeness = NULL;
          }
        }
        $record = [
          'template_id' => $template_id,
          'name' => (string) ($template['name'] ?? $template_id),
          'status' => 'canonical',
          'source' => (string) ($template['source'] ?? ''),
          'goal' => (string) ($template['synopsis'] ?? ''),
          'updated' => !empty($latest_draft['updated_at'])
            ? date('M j, Y g:i a', (int) $latest_draft['updated_at'])
            : (!empty($template['updated_at']) ? date('M j, Y', (int) $template['updated_at']) : ''),
          'latest_revision' => (int) ($latest_draft['revision'] ?? 0),
          'narrative_completeness' => is_array($completeness) ? (int) $completeness['narrative'] : NULL,
          'structure_object_completeness' => is_array($completeness) ? (int) $completeness['structure_object'] : NULL,
          'overall_completeness' => is_array($completeness) ? (int) $completeness['overall'] : NULL,
          'edit_url' => Url::fromRoute('dungeoncrawler_content.gm_storyline_builder', [], [
            'query' => $latest_draft !== NULL
              ? ['draft_id' => (string) $latest_draft['draft_id']]
              : ['template_id' => $template_id],
          ])->toString(),
          'action_label' => $latest_draft !== NULL ? 'Open Latest Version' : 'Edit Storyline',
        ];

        if ($this->isTemplateAuthoredByCurrentUser($template, $uid)) {
          $my_storylines[] = $record;
        }
        else {
          $other_storylines[] = $record;
        }
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Unable to load canonical storylines: %error', [
        '%error' => $e->getMessage(),
      ]));
    }
    $draft_metrics = [
      'total_drafts' => 0,
      'my_drafts' => 0,
      'shared_drafts' => 0,
      'my_editing_drafts' => 0,
      'my_saved_versions' => 0,
    ];
    try {
      $draft_metrics = $this->drafts->summarizeDraftLibrary();
      $visible_drafts = $can_edit_storylines
        ? $this->drafts->listDrafts()
        : [];
      foreach ($visible_drafts as $draft) {
        $draft_id = (string) ($draft['draft_id'] ?? '');
        if ($draft_id === '') {
          continue;
        }
        $record = [
          'draft_id' => $draft_id,
          'title' => (string) ($draft['title'] ?? 'Untitled storyline'),
          'owner_name' => (string) ($draft['owner_name'] ?? ''),
          'revision' => (int) ($draft['revision'] ?? 0),
          'revision_count' => (int) ($draft['revision_count'] ?? 1),
          'status' => (string) ($draft['status'] ?? 'editing'),
          'updated' => !empty($draft['updated_at']) ? date('M j, Y g:i a', (int) $draft['updated_at']) : '',
          'edit_url' => Url::fromRoute('dungeoncrawler_content.gm_storyline_builder', [], [
            'query' => ['draft_id' => $draft_id],
          ])->toString(),
          'delete_url' => !empty($draft['is_owner'])
            ? Url::fromRoute('dungeoncrawler_content.api.storyline_builder_draft_delete', ['draft_id' => $draft_id])->toString()
            : '',
        ];
        if (!empty($draft['is_owner'])) {
          $my_drafts[] = $record;
        }
        else {
          $shared_drafts[] = $record;
        }
      }
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Unable to load storyline draft metrics: %error', [
        '%error' => $e->getMessage(),
      ]));
    }
    $metrics = [
      'canonical_total' => count($my_storylines) + count($other_storylines),
      'canonical_mine' => count($my_storylines),
      'canonical_shared' => count($other_storylines),
    ] + $draft_metrics;

    return [
      '#theme' => 'storyline_management_list',
      '#my_storylines' => $my_storylines,
      '#other_storylines' => $other_storylines,
      '#my_drafts' => $my_drafts,
      '#shared_drafts' => $shared_drafts,
      '#metrics' => $metrics,
      '#builder_url' => Url::fromRoute('dungeoncrawler_content.gm_storyline_builder')->toString(),
      '#storyline_explorer_url' => Url::fromRoute('dungeoncrawler_content.storyline_explorer')->toString(),
      '#campaigns_url' => Url::fromRoute('dungeoncrawler_content.campaigns')->toString(),
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/character-sheet',
          'dungeoncrawler_content/storyline-management',
        ],
      ],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['dc_canonical_storylines'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Determine authorship bucket for canonical templates.
   */
  private function isTemplateAuthoredByCurrentUser(array $template, int $uid): bool {
    if ($uid <= 0) {
      return FALSE;
    }

    $template_data = is_array($template['template_data'] ?? NULL) ? $template['template_data'] : [];
    $metadata = is_array($template_data['metadata'] ?? NULL) ? $template_data['metadata'] : [];
    $author_uid_candidates = [
      $metadata['authorship']['author_uid'] ?? NULL,
      $metadata['author_uid'] ?? NULL,
      $metadata['created_by_uid'] ?? NULL,
      $metadata['authored_by_uid'] ?? NULL,
      $template_data['author_uid'] ?? NULL,
    ];
    foreach ($author_uid_candidates as $candidate) {
      if ($candidate !== NULL && (int) $candidate === $uid) {
        return TRUE;
      }
    }

    // Runtime-generated canonical templates are authored in-system by the
    // current user flow when explicit author metadata is absent.
    return strtolower(trim((string) ($template['source'] ?? ''))) === 'runtime_generated';
  }

  /**
   * Split draft completion into narrative and structure/object dimensions.
   */
  private function completenessFromDraftStatus(array $status): array {
    $stages = [];
    foreach ((array) ($status['stages'] ?? []) as $stage) {
      $index = isset($stage['stage']) ? (int) $stage['stage'] : 0;
      if ($index > 0) {
        $stages[$index] = (int) ($stage['completion_percent'] ?? 0);
      }
    }

    $narrative = $this->averagePercent([$stages[1] ?? 0, $stages[2] ?? 0]);
    $structure_object = $this->averagePercent([$stages[3] ?? 0, $stages[4] ?? 0, $stages[5] ?? 0]);

    return [
      'narrative' => $narrative,
      'structure_object' => $structure_object,
      'overall' => (int) ($status['completion_percent'] ?? 0),
    ];
  }

  /**
   * Rounded percent average from one or more already-normalized values.
   *
   * @param int[] $values
   */
  private function averagePercent(array $values): int {
    if ($values === []) {
      return 0;
    }
    return (int) round(array_sum($values) / count($values));
  }

  /**
   * Create a campaign storyline from a canonical template.
   */
  public function createFromTemplate(int $campaign_id, string $template_id): RedirectResponse {
    $campaign = $this->loadOwnedCampaign($campaign_id);
    if ($campaign === NULL) {
      throw new AccessDeniedHttpException();
    }

    $this->messenger()->addStatus($this->t('Review the attachment plan before adding %template to %campaign.', [
      '%template' => $template_id,
      '%campaign' => (string) $campaign->name,
    ]));
    return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.campaign_tavernentrance', [
      'campaign_id' => $campaign_id,
    ], [
      'query' => [
        'open_settings' => 'storylines',
        'storyline_template_id' => $template_id,
      ],
    ])->toString());
  }

  /**
   * Set selected storyline active and primary for a campaign.
   */
  public function activateStoryline(int $campaign_id, string $storyline_id): RedirectResponse {
    $campaign = $this->loadOwnedCampaign($campaign_id);
    if ($campaign === NULL) {
      throw new AccessDeniedHttpException();
    }

    try {
      $storyline = $this->storylineManager->activateCampaignStoryline($campaign_id, $storyline_id, TRUE);
      if ($storyline === NULL) {
        throw new NotFoundHttpException();
      }
      $this->messenger()->addStatus($this->t('Storyline %storyline is now active for %campaign.', [
        '%storyline' => (string) ($storyline['name'] ?? $storyline_id),
        '%campaign' => (string) $campaign->name,
      ]));
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Unable to activate storyline %storyline: %error', [
        '%storyline' => $storyline_id,
        '%error' => $e->getMessage(),
      ]));
    }

    return $this->redirectToDestinationOrManagement($campaign_id);
  }

  /**
   * Load campaign owned by current user.
   */
  private function loadOwnedCampaign(int $campaign_id): ?object {
    $campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name', 'uid'])
      ->condition('id', $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$campaign) {
      return NULL;
    }

    if ((int) $campaign->uid !== (int) $this->currentUser()->id()) {
      return NULL;
    }

    return $campaign;
  }

  /**
   * Redirect to provided destination or selected campaign management page.
   */
  private function redirectToDestinationOrManagement(int $campaign_id): RedirectResponse {
    $destination = trim((string) \Drupal::request()->query->get('destination', ''));
    if ($destination !== '' && str_starts_with($destination, '/')) {
      return new RedirectResponse($destination);
    }

    $url = Url::fromRoute('dungeoncrawler_content.storyline_management')
      ->toString();
    return new RedirectResponse($url);
  }

}
