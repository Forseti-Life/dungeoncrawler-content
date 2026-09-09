<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CampaignLifecycleService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirmation form for archiving a campaign.
 */
class CampaignArchiveForm extends ConfirmFormBase {

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
    return 'dungeoncrawler_campaign_archive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Archive %name?', [
      '%name' => $this->campaign->name ?? $this->t('this campaign'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Archiving hides this campaign from your /campaigns page without deleting it. You can still keep campaign data in the database.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Archive Campaign');
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

    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $result = $this->campaignLifecycle->archive((int) $this->campaign->id);

    if ($result['status'] === 'already_archived') {
      $this->messenger()->addStatus($this->t('%name is already archived.', ['%name' => $this->campaign->name]));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $this->messenger()->addStatus($this->t('%name archived. It is now hidden from your campaigns list.', [
      '%name' => $this->campaign->name,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
