<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirmation form for archiving a character.
 */
class CharacterArchiveForm extends ConfirmFormBase {

  protected Connection $database;
  protected TimeInterface $time;
  protected AccountProxyInterface $currentUser;
  protected ?object $character = NULL;

  public function __construct(Connection $database, TimeInterface $time, AccountProxyInterface $current_user) {
    $this->database = $database;
    $this->time = $time;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('datetime.time'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'dungeoncrawler_character_archive_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Archive %name?', [
      '%name' => $this->character->name ?? $this->t('this character'),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Archiving hides this character from your roster without deleting it. Character data will be preserved in the database.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Archive Character');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    // Redirect back to the referrer if available, otherwise to characters list
    $destination = \Drupal::request()->query->get('destination');
    if ($destination) {
      return Url::fromUserInput($destination);
    }
    $campaign_id = (int) ($this->character->campaign_id ?? 0);
    if ($campaign_id > 0) {
      return Url::fromRoute('dungeoncrawler_content.characters', ['campaign_id' => $campaign_id]);
    }
    return Url::fromRoute('dungeoncrawler_content.campaigns');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?int $character_id = NULL) {
    $this->character = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id', 'name', 'uid', 'status', 'character_data'])
      ->condition('id', (int) $character_id)
      ->execute()
      ->fetchObject() ?: NULL;

    if (!$this->character) {
      throw new NotFoundHttpException();
    }

    // Check ownership
    if (
      (int) $this->character->uid !== (int) $this->currentUser->id()
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
    if ((int) $this->character->status === 2) {
      $this->messenger()->addStatus($this->t('%name is already archived.', ['%name' => $this->character->name]));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $character_data = json_decode((string) ($this->character->character_data ?? '{}'), TRUE);
    if (!is_array($character_data)) {
      $character_data = [];
    }

    $character_data['_archive_meta'] = [
      'previous_status' => (int) $this->character->status,
      'archived_at' => $this->time->getRequestTime(),
    ];

    $this->database->update('dc_campaign_characters')
      ->fields([
        'status' => 2,
        'character_data' => json_encode($character_data, JSON_UNESCAPED_UNICODE),
        'changed' => $this->time->getRequestTime(),
      ])
      ->condition('id', (int) $this->character->id)
      ->execute();

    $this->messenger()->addStatus($this->t('%name archived. It is now hidden from your character roster.', [
      '%name' => $this->character->name,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
