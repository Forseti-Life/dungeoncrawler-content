<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\CampaignClockService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Drupal\dungeoncrawler_content\Service\CampaignNameGeneratorService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Campaign creation form.
 */
class CampaignCreateForm extends FormBase {

  protected Connection $database;
  protected UuidInterface $uuid;
  protected TimeInterface $time;
  protected AccountProxyInterface $currentUser;
  protected CampaignClockService $campaignClockService;
  protected SchemaLoader $schemaLoader;
  protected CampaignInitializationService $campaignInitialization;
  protected CampaignNameGeneratorService $campaignNameGenerator;
  protected StorylineManagerService $storylineManager;

  public function __construct(
    Connection $database,
    UuidInterface $uuid,
    TimeInterface $time,
    AccountProxyInterface $current_user,
    CampaignClockService $campaign_clock_service,
    SchemaLoader $schema_loader,
    CampaignInitializationService $campaign_initialization,
    CampaignNameGeneratorService $campaign_name_generator,
    StorylineManagerService $storyline_manager,
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
    $this->currentUser = $current_user;
    $this->campaignClockService = $campaign_clock_service;
    $this->schemaLoader = $schema_loader;
    $this->campaignInitialization = $campaign_initialization;
    $this->campaignNameGenerator = $campaign_name_generator;
    $this->storylineManager = $storyline_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('uuid'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('dungeoncrawler_content.campaign_clock'),
      $container->get('dungeoncrawler_content.schema_loader'),
      $container->get('dungeoncrawler_content.campaign_initialization'),
      $container->get('dungeoncrawler_content.campaign_name_generator'),
      $container->get('dungeoncrawler_content.storyline_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dungeoncrawler_campaign_create_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attributes']['class'][] = 'dc-character-form';
    $selected_theme = (string) ($form_state->getValue('theme') ?: 'classic_dungeon');
    $suggested_name = (string) ($form_state->getValue('name') ?: $this->campaignNameGenerator->generate($selected_theme));
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campaign Name (Editable)'),
      '#required' => FALSE,
      '#maxlength' => 255,
      '#default_value' => $suggested_name,
      '#attributes' => ['placeholder' => $this->t('Leave blank to auto-generate a campaign name.')],
    ];

    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Start Location'),
      '#required' => TRUE,
      '#options' => [
        'classic_dungeon' => $this->t('The Gilded Tavern'),
        'undead_crypt' => $this->t('The Undead Warren'),
      ],
      '#default_value' => 'classic_dungeon',
    ];

    $storyline_options = ['' => $this->t('No storyline yet')];
    foreach ($this->storylineManager->listTemplates() as $template) {
      $template_id = trim((string) ($template['template_id'] ?? ''));
      if ($template_id !== '') {
        $storyline_options[$template_id] = (string) ($template['name'] ?? $template_id);
      }
    }
    $form['storyline_template_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Starting Storyline'),
      '#options' => $storyline_options,
      '#default_value' => '',
      '#description' => $this->t('Optional. After campaign creation, Campaign Settings will review its gateway and content conflicts before attachment.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create Campaign'),
      '#attributes' => ['class' => ['dc-btn', 'dc-btn-primary']],
    ];
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#url' => Url::fromRoute('dungeoncrawler_content.campaigns'),
      '#attributes' => ['class' => ['dc-btn', 'dc-btn-secondary']],
    ];

    $form['#attached']['library'][] = 'dungeoncrawler_content/character-sheet';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $theme = (string) $form_state->getValue('theme');
    if (!in_array($theme, ['classic_dungeon', 'undead_crypt'], TRUE)) {
      $form_state->setErrorByName('theme', $this->t('Select a valid start location.'));
    }
    $storyline_template_id = trim((string) $form_state->getValue('storyline_template_id'));
    if ($storyline_template_id !== '' && $this->storylineManager->getTemplate($storyline_template_id) === NULL) {
      $form_state->setErrorByName('storyline_template_id', $this->t('Select a valid canonical storyline.'));
    }

    $payload = $this->buildCampaignPayload();
    $validation = $this->schemaLoader->validateCampaignData($payload);

    if (!$validation['valid']) {
      $form_state->setErrorByName('name', $this->t('Campaign schema validation failed: @errors', [
        '@errors' => implode(' ', $validation['errors']),
      ]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $theme = (string) $form_state->getValue('theme');
    $source = ['kind' => 'theme', 'theme' => $theme];

    try {
      $campaign_id = $this->campaignInitialization->initializeCampaign(
        (int) $this->currentUser->id(),
        (string) $form_state->getValue('name'),
        $theme,
        'normal',
        $source
      );
    }
    catch (\Throwable $e) {
      $this->messenger()->addError($this->t('Campaign creation failed: @code', [
        '@code' => $e->getMessage(),
      ]));
      return;
    }

    if (!$campaign_id) {
      $this->logger('dungeoncrawler_content')->error('CampaignCreateForm submit failed: initializeCampaign returned 0 (uid={uid}, theme={theme}, difficulty={difficulty}, submitted_name={submitted_name}).', [
        'uid' => (int) $this->currentUser->id(),
        'theme' => $theme,
        'difficulty' => 'normal',
        'submitted_name' => (string) $form_state->getValue('name'),
      ]);
      $this->messenger()->addError($this->t('Failed to create campaign. Please try again.'));
      return;
    }

    $this->messenger()->addStatus($this->t('Campaign created! Your adventure awaits at @start_location.', [
      '@start_location' => $this->resolveStarterLaunchLocationLabel($theme),
    ]));

    $storyline_template_id = trim((string) $form_state->getValue('storyline_template_id'));
    if ($storyline_template_id !== '') {
      $form_state->setRedirect('dungeoncrawler_content.campaign_tavernentrance', [
        'campaign_id' => $campaign_id,
      ], [
        'query' => [
          'open_settings' => 'storylines',
          'storyline_template_id' => $storyline_template_id,
        ],
      ]);
    }
    else {
      $form_state->setRedirect('dungeoncrawler_content.campaign_tavernentrance', [
        'campaign_id' => $campaign_id,
      ]);
    }
  }

  /**
   * Resolve launch copy for the campaign starter location.
   */
  private function resolveStarterLaunchLocationLabel(string $theme): string {
    return match (strtolower(trim($theme))) {
      'undead_crypt' => (string) $this->t('The Undead Warren'),
      default => (string) $this->t('The Gilded Tavern'),
    };
  }

  /**
   * Build canonical campaign payload for campaign_data.
   */
  private function buildCampaignPayload(): array {
    $now = $this->time->getRequestTime();
    $payload = [
      'schema_version' => '1.0.0',
      'created_by' => (int) $this->currentUser->id(),
      'started' => FALSE,
      'progress' => [],
      'created_at' => gmdate('c', $now),
      'updated_at' => gmdate('c', $now),
      CampaignClockService::STATE_KEY => $this->campaignClockService->createClockFromTimestamp($now),
    ];
    $this->campaignClockService->syncLegacyGameTime($payload);

    return $payload;
  }

}
