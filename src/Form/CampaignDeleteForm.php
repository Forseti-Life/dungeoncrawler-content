<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\ChatSessionManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirmation form for permanently deleting a campaign.
 *
 * Cascading delete removes:
 *   - dc_campaigns row
 *   - dc_campaign_dungeons rows
 *   - dc_campaign_rooms rows
 *   - dc_campaign_room_states rows
 *   - dc_campaign_content_registry rows
 *   - dc_campaign_characters (campaign instances only)
 *   - dc_campaign_quests rows
 *   - dc_chat_sessions + dc_chat_messages via ChatSessionManager
 */
class CampaignDeleteForm extends ConfirmFormBase {

  protected Connection $database;
  protected TimeInterface $time;
  protected AccountProxyInterface $currentUser;
  protected ?ChatSessionManager $chatSessionManager;
  protected ?object $campaign = NULL;

  public function __construct(
    Connection $database,
    TimeInterface $time,
    AccountProxyInterface $current_user,
    ?ChatSessionManager $chat_session_manager = NULL,
  ) {
    $this->database = $database;
    $this->time = $time;
    $this->currentUser = $current_user;
    $this->chatSessionManager = $chat_session_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('dungeoncrawler_content.chat_session_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dungeoncrawler_campaign_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Permanently delete %name?', [
      '%name' => $this->campaign->name ?? $this->t('this campaign'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This action <strong>cannot be undone</strong>. The campaign, all its dungeons, rooms, quests, chat sessions, and campaign character instances will be permanently destroyed. Library characters will not be affected.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('☠️ Delete Forever');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $destination = \Drupal::request()->query->get('destination');
    if ($destination) {
      return Url::fromUserInput($destination);
    }
    return Url::fromRoute('dungeoncrawler_content.campaigns_archived');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $campaign_id = NULL) {
    $this->campaign = $this->database->select('dc_campaigns', 'c')
      ->fields('c', ['id', 'name', 'uid', 'status', 'campaign_data'])
      ->condition('id', (int) $campaign_id)
      ->execute()
      ->fetchObject();

    if (!$this->campaign) {
      throw new NotFoundHttpException();
    }

    if (
      (int) $this->campaign->uid !== (int) $this->currentUser->id()
      && !$this->currentUser->hasPermission('administer dungeoncrawler content')
    ) {
      throw new AccessDeniedHttpException();
    }

    $form = parent::buildForm($form, $form_state);

    // Add a danger warning.
    $form['danger_warning'] = [
      '#markup' => '<div class="messages messages--warning"><strong>⚠️ ' . $this->t('Campaign: %name', ['%name' => $this->campaign->name]) . '</strong></div>',
      '#weight' => -100,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $campaign_id = (int) $this->campaign->id;
    $campaign_name = (string) $this->campaign->name;
    $preserved_player_characters = $this->preservePlayerCharacters($campaign_id);

    // Cascading delete — order matters for foreign key safety.
    // 1. Chat sessions + messages.
    if ($this->chatSessionManager) {
      try {
        $this->chatSessionManager->deleteAllForCampaign($campaign_id);
      }
      catch (\Exception $e) {
        \Drupal::logger('dungeoncrawler_content')->error('Failed to delete chat sessions for campaign {id}: {error}', [
          'id' => $campaign_id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    // 2. Campaign quests.
    if ($this->database->schema()->tableExists('dc_campaign_quests')) {
      $this->database->delete('dc_campaign_quests')
        ->condition('campaign_id', $campaign_id)
        ->execute();
    }

    // 3. Content registry.
    $this->database->delete('dc_campaign_content_registry')
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // 4. Room states.
    $this->database->delete('dc_campaign_room_states')
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // 5. Rooms.
    $this->database->delete('dc_campaign_rooms')
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // 6. Dungeons.
    $this->database->delete('dc_campaign_dungeons')
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // 7. Campaign character instances. Preserve player PCs by detaching them
    // back to the library slot before deleting campaign-only runtime rows.
    $this->database->delete('dc_campaign_characters')
      ->condition('campaign_id', $campaign_id)
      ->execute();

    // 8. Campaign record itself.
    $this->database->delete('dc_campaigns')
      ->condition('id', $campaign_id)
      ->execute();

    // Invalidate caches.
    Cache::invalidateTags([
      'dc_campaigns',
      'dc_campaign:' . $campaign_id,
    ]);

    \Drupal::logger('dungeoncrawler_content')->info('Campaign {id} ({name}) permanently deleted by uid {uid}.', [
      'id' => $campaign_id,
      'name' => $campaign_name,
      'uid' => (int) $this->currentUser->id(),
    ]);
    if ($preserved_player_characters > 0) {
      \Drupal::logger('dungeoncrawler_content')->notice('Preserved {count} player characters while deleting campaign {id}.', [
        'count' => $preserved_player_characters,
        'id' => $campaign_id,
      ]);
    }

    $this->messenger()->addStatus($this->t('%name has been permanently destroyed. There is no going back.', [
      '%name' => $campaign_name,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

  /**
   * Preserve player characters so campaign deletion does not destroy the roster.
   */
  protected function preservePlayerCharacters(int $campaign_id): int {
    $records = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', [
        'id',
        'uuid',
        'uid',
        'campaign_id',
        'character_id',
        'instance_id',
        'role',
        'type',
        'status',
        'character_data',
        'state_data',
      ])
      ->condition('campaign_id', $campaign_id)
      ->execute()
      ->fetchAll();

    if ($records === []) {
      return 0;
    }

    $preserved = 0;
    $now = $this->time->getRequestTime();
    foreach ($records as $record) {
      if (!$this->isPreservablePlayerCharacter($record)) {
        continue;
      }

      $canonical_character_id = (int) ($record->character_id ?? 0);
      $has_library_record = FALSE;
      if ($canonical_character_id > 0 && (int) ($record->id ?? 0) !== $canonical_character_id) {
        $has_library_record = (bool) $this->database->select('dc_campaign_characters', 'c')
          ->fields('c', ['id'])
          ->condition('id', $canonical_character_id)
          ->condition('campaign_id', 0)
          ->range(0, 1)
          ->execute()
          ->fetchField();
      }

      if ($has_library_record) {
        continue;
      }

      $this->database->update('dc_campaign_characters')
        ->fields($this->buildDetachedPlayerCharacterFields($record, $now))
        ->condition('id', (int) $record->id)
        ->execute();
      $preserved++;
    }

    return $preserved;
  }

  /**
   * Determine whether a campaign character row should be preserved as a PC.
   */
  protected function isPreservablePlayerCharacter(object $record): bool {
    return (int) ($record->uid ?? 0) > 0
      && strtolower((string) ($record->type ?? '')) === 'pc'
      && strtolower((string) ($record->role ?? '')) === 'player';
  }

  /**
   * Build field updates that detach a player character from a campaign.
   */
  protected function buildDetachedPlayerCharacterFields(object $record, int $now): array {
    $instance_id = trim((string) ($record->uuid ?? ''));
    if ($instance_id === '') {
      $instance_id = trim((string) ($record->instance_id ?? ''));
    }
    if ($instance_id === '') {
      $instance_id = 'character-' . (int) ($record->id ?? 0);
    }

    return [
      'campaign_id' => 0,
      'character_id' => 0,
      'instance_id' => $instance_id,
      'location_type' => 'roster',
      'location_ref' => '',
      'position_q' => 0,
      'position_r' => 0,
      'last_room_id' => '',
      'is_active' => 0,
      'updated' => $now,
      'changed' => $now,
    ];
  }

}
