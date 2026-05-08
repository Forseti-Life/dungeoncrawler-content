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

    // 7. Campaign character instances (not library characters).
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

    $this->messenger()->addStatus($this->t('%name has been permanently destroyed. There is no going back.', [
      '%name' => $campaign_name,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
