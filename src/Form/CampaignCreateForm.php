<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Drupal\dungeoncrawler_content\Service\CampaignInitializationService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Campaign creation form.
 */
class CampaignCreateForm extends FormBase {

  protected Connection $database;
  protected UuidInterface $uuid;
  protected TimeInterface $time;
  protected AccountProxyInterface $currentUser;
  protected SchemaLoader $schemaLoader;
  protected CampaignInitializationService $campaignInitialization;

  public function __construct(
    Connection $database,
    UuidInterface $uuid,
    TimeInterface $time,
    AccountProxyInterface $current_user,
    SchemaLoader $schema_loader,
    CampaignInitializationService $campaign_initialization
  ) {
    $this->database = $database;
    $this->uuid = $uuid;
    $this->time = $time;
    $this->currentUser = $current_user;
    $this->schemaLoader = $schema_loader;
    $this->campaignInitialization = $campaign_initialization;
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
      $container->get('dungeoncrawler_content.schema_loader'),
      $container->get('dungeoncrawler_content.campaign_initialization'),
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

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Campaign Name'),
      '#required' => TRUE,
      '#maxlength' => 255,
      '#default_value' => $this->t('New Campaign'),
      '#attributes' => ['placeholder' => $this->t('Enter your campaign name...')],
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
    // Use the campaign initialization service to create campaign with all defaults
    $campaign_id = $this->campaignInitialization->initializeCampaign(
      (int) $this->currentUser->id(),
      (string) $form_state->getValue('name'),
      (string) $form_state->getValue('theme'),
      (string) $form_state->getValue('difficulty')
    );

    if (!$campaign_id) {
      $this->messenger()->addError($this->t('Failed to create campaign. Please try again.'));
      return;
    }

    $this->messenger()->addStatus($this->t('Campaign created! Your adventure awaits at the tavern entrance.'));

    $form_state->setRedirect('dungeoncrawler_content.campaign_tavernentrance', [
      'campaign_id' => $campaign_id,
    ]);
  }

  /**
   * Build canonical campaign payload for campaign_data.
   */
  private function buildCampaignPayload(): array {
    return [
      'schema_version' => '1.0.0',
      'created_by' => (int) $this->currentUser->id(),
      'started' => FALSE,
      'progress' => [],
    ];
  }

}
