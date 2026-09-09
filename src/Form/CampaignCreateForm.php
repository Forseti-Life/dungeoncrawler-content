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
use Drupal\dungeoncrawler_content\Service\DungeonEditorService;
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
  protected DungeonEditorService $dungeonEditor;

  public function __construct(
    Connection $database,
    UuidInterface $uuid,
    TimeInterface $time,
    AccountProxyInterface $current_user,
    CampaignClockService $campaign_clock_service,
    SchemaLoader $schema_loader,
    CampaignInitializationService $campaign_initialization,
    CampaignNameGeneratorService $campaign_name_generator,
    DungeonEditorService $dungeon_editor
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
    $this->currentUser = $current_user;
    $this->campaignClockService = $campaign_clock_service;
    $this->schemaLoader = $schema_loader;
    $this->campaignInitialization = $campaign_initialization;
    $this->campaignNameGenerator = $campaign_name_generator;
    $this->dungeonEditor = $dungeon_editor;
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
      $container->get('dungeoncrawler_content.dungeon_editor'),
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
    $published_dungeons = $this->publishedDungeonOptions();

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campaign Name'),
      '#required' => FALSE,
      '#maxlength' => 255,
      '#default_value' => $suggested_name,
      '#description' => $this->t('Editable local-generated suggestion. Leave it as-is or type your own.'),
      '#attributes' => ['placeholder' => $this->t('Leave blank to auto-generate a campaign name.')],
    ];

    $form['start_from'] = [
      '#type' => 'radios',
      '#title' => $this->t('Start from'),
      '#required' => TRUE,
      '#options' => [
        'theme' => $this->t('Theme'),
        'published_dungeon' => $this->t('Published dungeon'),
      ],
      '#default_value' => (string) ($form_state->getValue('start_from') ?: 'theme'),
      '#description' => $this->t('Choose Theme to generate a new campaign from a setting style. Choose Published dungeon to play a specific, fully authored dungeon from the canonical library, including its published rooms, objects, and connections.'),
    ];

    $form['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#required' => TRUE,
      '#options' => [
        'classic_dungeon' => $this->t('Classic Dungeon'),
        'goblin_warrens' => $this->t('Goblin Warrens'),
        'undead_crypt' => $this->t('Undead Crypt'),
      ],
      '#default_value' => 'classic_dungeon',
      '#description' => $this->t('The selected theme guides procedural generation; the campaign is not tied to a pre-authored dungeon layout.'),
      '#states' => [
        'visible' => [
          ':input[name="start_from"]' => ['value' => 'theme'],
        ],
      ],
    ];

    $form['published_dungeon'] = [
      '#type' => 'select',
      '#title' => $this->t('Published dungeon'),
      '#required' => FALSE,
      '#empty_option' => $this->t('- Select a published dungeon -'),
      '#options' => $published_dungeons['options'],
      '#description' => $published_dungeons['licence_notice'] !== ''
        ? $this->t('Starts from the selected canonical dungeon exactly as published. Only dungeons with a current published version are listed. Licence notice: @notice', ['@notice' => $published_dungeons['licence_notice']])
        : $this->t('Starts from the selected canonical dungeon exactly as published. Only dungeons with a current published version are listed.'),
      '#states' => [
        'visible' => [
          ':input[name="start_from"]' => ['value' => 'published_dungeon'],
        ],
        'required' => [
          ':input[name="start_from"]' => ['value' => 'published_dungeon'],
        ],
      ],
    ];

    $form['difficulty'] = [
      '#type' => 'select',
      '#title' => $this->t('Difficulty'),
      '#required' => TRUE,
      '#options' => [
        'normal' => $this->t('Normal'),
        'hard' => $this->t('Hard'),
        'extreme' => $this->t('Extreme'),
      ],
      '#default_value' => 'normal',
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
    if ((string) $form_state->getValue('start_from') === 'published_dungeon') {
      $selected = trim((string) $form_state->getValue('published_dungeon'));
      if ($selected === '') {
        $form_state->setErrorByName('published_dungeon', $this->t('Select a published dungeon.'));
      }
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
    if ((string) $form_state->getValue('start_from') === 'published_dungeon') {
      $dungeon_id = trim((string) $form_state->getValue('published_dungeon'));
      $version_id = $this->publishedDungeonVersionId($dungeon_id);
      $source = [
        'kind' => 'published_dungeon',
        'dungeon_id' => $dungeon_id,
        'version_id' => $version_id,
      ];
    }

    try {
      $campaign_id = $this->campaignInitialization->initializeCampaign(
        (int) $this->currentUser->id(),
        (string) $form_state->getValue('name'),
        $theme,
        (string) $form_state->getValue('difficulty'),
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
        'difficulty' => (string) $form_state->getValue('difficulty'),
        'submitted_name' => (string) $form_state->getValue('name'),
      ]);
      $this->messenger()->addError($this->t('Failed to create campaign. Please try again.'));
      return;
    }

    $this->messenger()->addStatus($this->t('Campaign created! Your adventure awaits at @start_location.', [
      '@start_location' => $source['kind'] === 'published_dungeon' ? $this->t('the published dungeon entrance') : $this->resolveStarterLaunchLocationLabel($theme),
    ]));

    $form_state->setRedirect('dungeoncrawler_content.campaign_tavernentrance', [
      'campaign_id' => $campaign_id,
    ]);
  }

  /**
   * Resolve launch copy for the campaign starter location.
   */
  private function resolveStarterLaunchLocationLabel(string $theme): string {
    return match (strtolower(trim($theme))) {
      'undead_crypt' => (string) $this->t('the undead crypt antechamber'),
      default => (string) $this->t('the campaign starter location'),
    };
  }

  /**
   * Build published dungeon select options from DungeonEditorService.
   *
   * @return array{options:array<string,string>,licence_notice:string}
   *   Select options and first available licence notice.
   */
  private function publishedDungeonOptions(): array {
    $options = [];
    $licence_notice = '';
    foreach ($this->dungeonEditor->listDungeons() as $dungeon) {
      $version_id = trim((string) ($dungeon['published_version_id'] ?? ''));
      if ($version_id === '') {
        continue;
      }
      $dungeon_id = (string) $dungeon['dungeon_id'];
      $version = trim((string) ($dungeon['published_version'] ?? ''));
      $room_count = (int) ($dungeon['published_room_count'] ?? 0);
      $options[$dungeon_id] = sprintf(
        '%s — v%s, %d rooms',
        (string) ($dungeon['name'] ?? $dungeon_id),
        $version !== '' ? $version : $version_id,
        $room_count
      );
      $notice = $dungeon['metadata']['module_source']['licence_notice'] ?? NULL;
      if ($licence_notice === '' && is_string($notice) && trim($notice) !== '') {
        $licence_notice = trim($notice);
      }
    }
    return ['options' => $options, 'licence_notice' => $licence_notice];
  }

  /**
   * Resolve the current published version id for a selected dungeon.
   */
  private function publishedDungeonVersionId(string $dungeon_id): string {
    foreach ($this->dungeonEditor->listDungeons() as $dungeon) {
      if ((string) ($dungeon['dungeon_id'] ?? '') === $dungeon_id) {
        $version_id = trim((string) ($dungeon['published_version_id'] ?? ''));
        if ($version_id !== '') {
          return $version_id;
        }
      }
    }
    throw new \RuntimeException(sprintf('campaign_source_dungeon_version_not_published: dungeon_id=%s.', $dungeon_id));
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
