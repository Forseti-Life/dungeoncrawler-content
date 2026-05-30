<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\InstitutionReviewApplicationService;
use Drupal\dungeoncrawler_content\Service\InstitutionReviewDecisionService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Structured decision form for institution review queue items.
 */
class InstitutionReviewResolutionForm extends FormBase {

  /**
   * @var array<string, mixed>|null
   */
  protected ?array $reviewRow = NULL;

  public function __construct(
    protected InstitutionReviewDecisionService $reviewDecisionService,
    protected InstitutionReviewApplicationService $reviewApplicationService,
    protected AccountProxyInterface $currentUser,
    RequestStack $requestStack,
  ) {
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.institution_review_decision'),
      $container->get('dungeoncrawler_content.institution_review_application'),
      $container->get('current_user'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_content_institution_review_resolution_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $queue_type = '', int $row_id = 0): array {
    if (!$this->reviewDecisionService->isDecisionStorageReady($queue_type)) {
      throw new ServiceUnavailableHttpException(NULL, 'Institution review resolution storage is not ready yet. Apply the pending schema updates first.');
    }

    $this->reviewRow = $this->reviewDecisionService->loadReviewRow($queue_type, $row_id);
    if ($this->reviewRow === []) {
      throw new NotFoundHttpException();
    }

    $current_status = (string) ($this->reviewRow['status'] ?? InstitutionReviewDecisionService::STATUS_OPEN);
    $current_action = (string) ($this->reviewRow['resolution_action'] ?? '');
    $current_payload = $this->decodeJsonArray($this->reviewRow['resolution_payload_json'] ?? NULL);
    $default_action = $current_action !== ''
      ? $current_action
      : ($current_status === InstitutionReviewDecisionService::STATUS_OPEN ? 'reopen' : array_key_first($this->reviewDecisionService->getAllowedActionsByStatus()[$current_status]));

    $form['queue_type'] = [
      '#type' => 'value',
      '#value' => $queue_type,
    ];
    $form['row_id'] = [
      '#type' => 'value',
      '#value' => $row_id,
    ];

    $form['summary'] = [
      '#type' => 'item',
      '#title' => $this->t('Review item'),
      '#markup' => $this->buildSummaryMarkup($queue_type, $this->reviewRow),
    ];
    $form['details'] = [
      '#type' => 'details',
      '#title' => $this->t('Structured source details'),
      '#open' => FALSE,
      'payload' => [
        '#markup' => '<pre class="small mb-0">' . Html::escape($this->encodeJson($this->reviewRow['details_json'] ?? NULL)) . '</pre>',
      ],
    ];

    $form['status'] = [
      '#type' => 'select',
      '#title' => $this->t('Workflow status'),
      '#options' => [
        InstitutionReviewDecisionService::STATUS_OPEN => $this->t('Open'),
        InstitutionReviewDecisionService::STATUS_RESOLVED => $this->t('Resolved'),
        InstitutionReviewDecisionService::STATUS_DEFERRED => $this->t('Deferred'),
      ],
      '#default_value' => $current_status,
      '#required' => TRUE,
    ];
    $form['resolution_action'] = [
      '#type' => 'select',
      '#title' => $this->t('Resolution action'),
      '#options' => $this->buildActionOptions(),
      '#default_value' => $default_action,
      '#required' => TRUE,
      '#description' => $this->t('Use reopen to clear a prior decision. Use defer for rows that still need operator follow-up.'),
    ];
    $form['decision_summary'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Decision summary'),
      '#default_value' => (string) ($current_payload['decision_summary'] ?? ''),
      '#maxlength' => 255,
      '#description' => $this->t('Short operator-readable summary of the decision. Required for resolved and deferred states.'),
    ];
    $form['canonical_domain'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Canonical domain'),
      '#default_value' => (string) ($current_payload['canonical_domain'] ?? ''),
      '#maxlength' => 64,
      '#description' => $this->t('Required when the decision creates a new canonical institution.'),
    ];
    $form['canonical_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Canonical label'),
      '#default_value' => (string) ($current_payload['canonical_label'] ?? ''),
      '#maxlength' => 191,
    ];
    $form['target_identifier'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Target identifier'),
      '#default_value' => (string) ($current_payload['target_identifier'] ?? ''),
      '#maxlength' => 191,
      '#description' => $this->t('Use a canonical institution subject id or other stable target key when mapping to an existing institution.'),
    ];
    $form['note'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Operator note'),
      '#default_value' => (string) ($current_payload['note'] ?? ''),
      '#rows' => 4,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save review decision'),
      '#button_type' => 'primary',
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => $this->getCancelUrl(),
      '#attributes' => ['class' => ['button']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $status = (string) $form_state->getValue('status');
    $action = (string) $form_state->getValue('resolution_action');
    $summary = trim((string) $form_state->getValue('decision_summary'));
    $canonical_domain = trim((string) $form_state->getValue('canonical_domain'));
    $canonical_label = trim((string) $form_state->getValue('canonical_label'));
    $target_identifier = trim((string) $form_state->getValue('target_identifier'));

    $allowed_actions = $this->reviewDecisionService->getAllowedActionsByStatus()[$status] ?? [];
    if (!in_array($action, $allowed_actions, TRUE)) {
      $form_state->setErrorByName('resolution_action', $this->t('That action does not match the selected workflow status.'));
    }

    if ($status !== InstitutionReviewDecisionService::STATUS_OPEN && $summary === '') {
      $form_state->setErrorByName('decision_summary', $this->t('Resolved and deferred review items require a decision summary.'));
    }
    if ($status === InstitutionReviewDecisionService::STATUS_RESOLVED && $action === 'map_existing' && $target_identifier === '') {
      $form_state->setErrorByName('target_identifier', $this->t('Mapping to an existing institution requires a target identifier.'));
    }
    if ($status === InstitutionReviewDecisionService::STATUS_RESOLVED && $action === 'create_institution') {
      if ($canonical_domain === '') {
        $form_state->setErrorByName('canonical_domain', $this->t('Creating an institution requires a canonical domain.'));
      }
      if ($canonical_label === '') {
        $form_state->setErrorByName('canonical_label', $this->t('Creating an institution requires a canonical label.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $queue_type = (string) $form_state->getValue('queue_type');
    $row_id = (int) $form_state->getValue('row_id');
    $status = (string) $form_state->getValue('status');
    $action = (string) $form_state->getValue('resolution_action');
    $actor_uid = (int) $this->currentUser->id();
    $payload = [
      'decision_summary' => $form_state->getValue('decision_summary'),
      'canonical_domain' => $form_state->getValue('canonical_domain'),
      'canonical_label' => $form_state->getValue('canonical_label'),
      'target_identifier' => $form_state->getValue('target_identifier'),
      'note' => $form_state->getValue('note'),
    ];

    $this->reviewDecisionService->saveDecision(
      $queue_type,
      $row_id,
      (int) $this->currentUser->id(),
      $status,
      $action,
      $payload
    );

    $message = 'Institution review decision saved.';
    if ($status === InstitutionReviewDecisionService::STATUS_RESOLVED) {
      $this->reviewApplicationService->applyPendingDecision($queue_type, $row_id, $status, $action, $payload, $actor_uid);
      $message = 'Institution review decision saved and applied.';
    }

    $this->reviewDecisionService->saveDecision(
      $queue_type,
      $row_id,
      $actor_uid,
      $status,
      $action,
      $payload
    );

    if ($status === InstitutionReviewDecisionService::STATUS_RESOLVED) {
      $message = 'Institution review decision saved and applied.';
    }

    $this->messenger()->addStatus($this->t($message));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Builds the action select options.
   *
   * @return array<string, string>
   */
  protected function buildActionOptions(): array {
    return [
      'reopen' => (string) $this->t('Reopen / clear prior decision'),
      'map_existing' => (string) $this->t('Map to existing canonical institution'),
      'create_institution' => (string) $this->t('Create new canonical institution'),
      'mark_blank' => (string) $this->t('Mark intentionally blank / not represented'),
      'defer' => (string) $this->t('Defer for later follow-up'),
    ];
  }

  /**
   * Builds a summary for the review item.
   */
  protected function buildSummaryMarkup(string $queue_type, array $row): string {
    if ($queue_type === 'library') {
      return '<p class="mb-1"><strong>' . Html::escape((string) $this->t('Source file')) . ':</strong> '
        . Html::escape((string) ($row['source_file'] ?? '')) . '</p>'
        . '<p class="mb-1"><strong>' . Html::escape((string) $this->t('Asset')) . ':</strong> '
        . Html::escape((string) ($row['source_asset_id'] ?? '')) . '</p>'
        . '<p class="mb-0"><strong>' . Html::escape((string) $this->t('Reason')) . ':</strong> '
        . Html::escape((string) ($row['review_reason'] ?? '')) . '</p>';
    }

    return '<p class="mb-1"><strong>' . Html::escape((string) $this->t('Campaign')) . ':</strong> '
      . Html::escape((string) ($row['campaign_id'] ?? '')) . '</p>'
      . '<p class="mb-1"><strong>' . Html::escape((string) $this->t('Actor')) . ':</strong> '
      . Html::escape((string) ($row['source_id'] ?? '')) . '</p>'
      . '<p class="mb-0"><strong>' . Html::escape((string) $this->t('Reason')) . ':</strong> '
      . Html::escape((string) ($row['review_reason'] ?? '')) . '</p>';
  }

  /**
   * Returns the destination back to the review queue.
   */
  protected function getCancelUrl(): Url {
    $destination = (string) $this->requestStack->getCurrentRequest()->query->get('destination', '');
    if ($destination !== '') {
      return Url::fromUserInput($destination);
    }

    return Url::fromRoute('dungeoncrawler_content.institution_review_browser');
  }

  /**
   * Encodes a structured payload for display.
   */
  protected function encodeJson(mixed $value): string {
    $payload = $this->decodeJsonArray($value);
    $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $encoded !== FALSE ? $encoded : '{}';
  }

  /**
   * Decodes a JSON payload to an array.
   *
   * @return array<string, mixed>
   */
  protected function decodeJsonArray(mixed $value): array {
    if (is_array($value)) {
      return $value;
    }
    if (!is_string($value) || trim($value) === '') {
      return [];
    }

    $decoded = json_decode($value, TRUE);
    return is_array($decoded) ? $decoded : [];
  }

}
