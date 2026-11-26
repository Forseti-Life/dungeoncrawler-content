<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorWarningReview;
use Drupal\dungeoncrawler_content\Service\RoomEditorService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Reviews warnings without altering draft content or canonical validation.
 */
final class EditorWarningReviewForm extends FormBase {

  public function __construct(
    protected EditorWarningReview $review,
    protected DungeonEditorService $dungeons,
    protected RoomEditorService $rooms,
    protected StorylineAuthoringDraftService $storylines,
    protected AccountProxyInterface $account,
    protected CsrfTokenGenerator $csrfToken,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.editor_warning_review'),
      $container->get('dungeoncrawler_content.dungeon_editor'),
      $container->get('dungeoncrawler_content.room_editor'),
      $container->get('dungeoncrawler_content.storyline_authoring_drafts'),
      $container->get('current_user'),
      $container->get('csrf_token'),
    );
  }

  public function getFormId(): string {
    return 'dungeoncrawler_editor_warning_review';
  }

  public function buildForm(array $form, FormStateInterface $form_state, string $surface = '', string $draft_id = ''): array {
    [$draft, $warnings] = $this->load($surface, $draft_id);
    $form_state->set('review', [$surface, $draft_id]);
    $snapshot = json_encode([$surface, $draft_id, $draft['revision'], array_map(static fn(array $row): bool => $row['accepted'], $warnings)], JSON_THROW_ON_ERROR);
    $form['#attributes']['class'][] = 'editor-warning-review';
    $form['#attached']['library'][] = 'dungeoncrawler_content/editor-warning-review';
    $form['snapshot'] = ['#type' => 'hidden', '#default_value' => $snapshot];
    $form['snapshot_token'] = ['#type' => 'hidden', '#default_value' => $this->csrfToken->get($this->snapshotKey($snapshot))];
    $form['#cache']['max-age'] = 0;
    $form['intro'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['editor-warning-review__intro']],
      'text' => [
        '#plain_text' => $this->t('Check warnings you accept as intentional or not a concern. Uncheck a warning to reopen it. Acceptance counts toward completion but does not fix the finding or waive an error.'),
      ],
    ];
    $form['back'] = [
      '#type' => 'link',
      '#title' => $this->t('Back to editor'),
      '#url' => $this->editorUrl($surface, $draft_id),
      '#attributes' => ['class' => ['btn', 'btn-secondary', 'editor-warning-review__back']],
    ];
    $form['warnings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Current warnings'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['editor-warning-review__warnings']],
    ];
    foreach ($warnings as $id => $row) {
      $form['warnings'][$id] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Accept: @warning', ['@warning' => $row['message']]),
        '#default_value' => $row['accepted'],
        '#wrapper_attributes' => ['class' => ['editor-warning-review__warning']],
      ];
    }
    if ($warnings === []) {
      $form['warnings']['empty'] = ['#plain_text' => $this->t('There are no current warnings.')];
    }
    else {
      $form['actions'] = [
        '#type' => 'actions',
        '#attributes' => ['class' => ['editor-warning-review__actions']],
      ];
      $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Save warning decisions')];
    }
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    [$surface, $draft_id] = $form_state->get('review');
    $snapshot = (string) $form_state->getValue('snapshot');
    if (!$this->csrfToken->validate((string) $form_state->getValue('snapshot_token'), $this->snapshotKey($snapshot))) {
      $form_state->setErrorByName('warnings', $this->t('The warning review expired. Return to the editor and open it again.'));
      return;
    }
    [$saved_surface, $saved_id, $revision, $previous] = json_decode($snapshot, TRUE, 512, JSON_THROW_ON_ERROR);
    [$draft, $warnings] = $this->load($surface, $draft_id);
    $submitted = $form_state->getValue('warnings', []);
    if ($saved_surface !== $surface || $saved_id !== $draft_id || $draft['revision'] !== $revision || array_diff_key($previous, $warnings) || array_diff_key($warnings, $previous)) {
      $form_state->setErrorByName('warnings', $this->t('The draft or its warnings changed. Return to the editor and open warning review again.'));
      return;
    }
    $updates = [];
    foreach ($previous as $id => $accepted) {
      $new = !empty($submitted[$id]);
      if ($new !== $accepted) {
        $updates[$id] = $new;
      }
    }
    $form_state->set('updates', $updates);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    [$surface, $draft_id] = $form_state->get('review');
    $this->review->save($surface, $draft_id, $form_state->get('updates'), (int) $this->account->id());
    $this->messenger()->addStatus($this->t('Warning decisions saved.'));
    $form_state->setRedirectUrl($this->editorUrl($surface, $draft_id));
  }

  private function snapshotKey(string $snapshot): string {
    return 'editor-warning-review:' . hash('sha256', $snapshot);
  }

  private function load(string $surface, string $draft_id): array {
    $editor = match ($surface) {
      'dungeon' => $this->dungeons,
      'room' => $this->rooms,
      'storyline' => $this->storylines,
      default => throw new NotFoundHttpException(),
    };
    if ($surface === 'storyline' && !$this->account->hasPermission('edit canonical dungeoncrawler storylines')) {
      throw new AccessDeniedHttpException();
    }
    try {
      $draft = $editor->getDraft($draft_id);
      if (!EditorWarningReview::isEditable($surface, $draft)) {
        throw new AccessDeniedHttpException();
      }
      $validation = $editor->validateDraft($draft_id, 'editing');
    }
    catch (\OutOfBoundsException $exception) {
      throw new NotFoundHttpException('', $exception);
    }
    catch (\UnexpectedValueException $exception) {
      if ($exception->getMessage() !== 'draft_access_denied') {
        throw $exception;
      }
      throw new AccessDeniedHttpException('', $exception);
    }
    return [$draft, $this->review->warnings($surface, $draft_id, $validation)];
  }

  private function editorUrl(string $surface, string $draft_id): Url {
    return Url::fromRoute('dungeoncrawler_content.' . match ($surface) {
      'storyline' => 'gm_storyline_builder',
      'dungeon' => 'dungeon_editor',
      'room' => 'room_editor',
    }, [], ['query' => ['draft_id' => $draft_id]]);
  }

}
