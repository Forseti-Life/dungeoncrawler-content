<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Exception\LegacyCampaignArchivedException;
use Drupal\dungeoncrawler_content\Service\CampaignLifecycleService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirmation form for unarchiving a campaign.
 */
class CampaignUnarchiveForm extends ConfirmFormBase {

  protected Connection $database;
  protected TimeInterface $time;
  protected AccountProxyInterface $currentUser;
  protected CampaignLifecycleService $campaignLifecycle;
  protected ?object $campaign = NULL;

  public function __construct(Connection $database, TimeInterface $time, AccountProxyInterface $current_user, CampaignLifecycleService $campaign_lifecycle) {
    $this->database = $database;
    $this->time = $time;
    $this->currentUser = $current_user;
    $this->campaignLifecycle = $campaign_lifecycle;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('current_user'),
      $container->get('dungeoncrawler_content.campaign_lifecycle'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dungeoncrawler_campaign_unarchive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Unarchive %name?', [
      '%name' => $this->campaign->name ?? $this->t('this campaign'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Unarchiving makes this campaign visible again on your /campaigns page and restores its previous status when available.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Unarchive Campaign');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    $destination = \Drupal::request()->query->get('destination');
    if ($destination) {
      return Url::fromUserInput($destination);
    }
    return Url::fromRoute('dungeoncrawler_content.campaigns');
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

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $result = $this->campaignLifecycle->unarchive((int) $this->campaign->id);
    }
    catch (LegacyCampaignArchivedException $e) {
      // Board cutover: legacy archived campaigns can never re-enter runtime.
      $this->messenger()->addError($this->t('%name is a legacy campaign and cannot be unarchived into the current runtime (@code). It remains archived.', [
        '%name' => $this->campaign->name,
        '@code' => LegacyCampaignArchivedException::CODE,
      ]));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    if ($result['status'] === 'not_archived') {
      $this->messenger()->addStatus($this->t('%name is not archived.', ['%name' => $this->campaign->name]));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $this->messenger()->addStatus($this->t('%name unarchived. It is now visible on your campaigns list.', [
      '%name' => $this->campaign->name,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
