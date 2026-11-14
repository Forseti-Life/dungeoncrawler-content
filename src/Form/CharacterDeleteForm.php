<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirmation form for deleting a character.
 */
class CharacterDeleteForm extends ConfirmFormBase {

  protected CharacterManager $characterManager;
  protected ?object $character = NULL;

  public function __construct(CharacterManager $character_manager) {
    $this->characterManager = $character_manager;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
    );
  }

  public function getFormId() {
    return 'dungeoncrawler_character_delete';
  }

  public function getQuestion() {
    return $this->t('Are you sure you want to permanently delete %name?', [
      '%name' => $this->character->name ?? 'this character',
    ]);
  }

  public function getDescription() {
    return $this->t('This action cannot be undone. The character and all their data will be permanently lost in the dungeon depths.');
  }

  public function getCancelUrl() {
    $destination = \Drupal::request()->query->get('destination');
    if ($destination) {
      return Url::fromUserInput($destination);
    }
    if ((int) ($this->character->status ?? 0) === 2) {
      return Url::fromRoute('dungeoncrawler_content.characters_archived');
    }
    $campaign_id = (int) ($this->character->campaign_id ?? 0);
    if ($campaign_id > 0) {
      return Url::fromRoute('dungeoncrawler_content.characters', ['campaign_id' => $campaign_id]);
    }
    return Url::fromRoute('dungeoncrawler_content.characters_roster');
  }

  public function getConfirmText() {
    return $this->t('☠️ Delete Forever');
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $character_id = NULL) {
    $this->character = $this->characterManager->loadCharacter($character_id);
    if (!$this->character || !$this->characterManager->isOwner($this->character)) {
      throw new NotFoundHttpException();
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $campaign_id = (int) $this->character->campaign_id;
    $receipt = $this->characterManager->executeActorCommand([
      'contract_version' => 'actor-command-v1',
      'command_id' => \Drupal::service('uuid')->generate(),
      'target' => ['actor_uuid' => $this->character->uuid] + ($campaign_id > 0
        ? ['scope' => 'campaign_instance', 'campaign_id' => $campaign_id]
        : ['scope' => $this->character->lifecycle_state === 'draft_library' ? 'draft' : 'template']),
      'type' => 'actor.delete',
      'base_revision' => (int) $this->character->version,
      'payload' => [],
    ]);

    if (empty($receipt['committed'])) {
      $this->messenger()->addError($this->t('Unable to delete %name: @reason @next', [
        '%name' => $this->character->name,
        '@reason' => (string) $receipt['message'],
        '@next' => (string) ($receipt['next_steps'][0] ?? ''),
      ]));
      $form_state->setRedirectUrl($this->getCancelUrl());
      return;
    }

    $this->messenger()->addStatus($this->t('%name has fallen. Their tale ends here.', [
      '%name' => $this->character->name,
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
