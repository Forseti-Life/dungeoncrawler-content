<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Inline form for regenerating a character portrait.
 */
class CharacterPortraitRegenerateForm extends FormBase {

  public function __construct(
    protected CharacterManager $characterManager,
    protected CharacterPortraitGenerationService $portraitGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.character_portrait_generator'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dungeoncrawler_character_portrait_regenerate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $character_id = 0, int $campaign_id = 0): array {
    $record = $this->characterManager->loadCharacter($character_id);
    if (!$record) {
      throw new NotFoundHttpException();
    }

    if (!$this->characterManager->isOwner($record) && !$this->currentUser()->hasPermission('administer site configuration')) {
      throw new AccessDeniedHttpException();
    }

    $form_state->set('character_id', $character_id);
    $form_state->set('campaign_id', $campaign_id);
    $form['#attributes']['class'][] = 'dc-sheet__portrait-regenerate-form';

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Regenerate'),
      '#attributes' => ['class' => ['dc-btn', 'dc-btn-secondary', 'dc-btn-sm']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $character_id = (int) $form_state->get('character_id');
    $campaign_id = (int) $form_state->get('campaign_id');

    $record = $this->characterManager->loadCharacter($character_id);
    if (!$record) {
      throw new NotFoundHttpException();
    }

    $character_data = $this->characterManager->getCharacterData($record);
    $portrait_result = $this->portraitGenerator->generatePortrait(
      $character_data,
      $character_id,
      (int) $this->currentUser()->id(),
      $campaign_id > 0 ? $campaign_id : NULL,
      [
        'generate' => TRUE,
        'user_prompt' => $character_data['portrait_prompt'] ?? '',
        'force_regenerate' => TRUE,
        'replace_existing' => TRUE,
      ]
    );

    $storage = $portrait_result['storage'] ?? [];
    if (!empty($storage['stored'])) {
      $this->messenger()->addStatus($this->t('Portrait regenerated successfully.'));
    }
    elseif (($portrait_result['reason'] ?? '') === 'provider_unavailable') {
      $provider = strtoupper((string) ($portrait_result['provider'] ?? 'provider'));
      $this->messenger()->addWarning($this->t('Portrait regeneration is currently unavailable because @provider has no configured credentials. Configure an API key or Google application default credentials for the live site.', [
        '@provider' => $provider,
      ]));
    }
    else {
      $reason = (string) ($storage['reason'] ?? ($portrait_result['reason'] ?? 'generation_failed'));
      $this->messenger()->addWarning($this->t('Portrait regeneration did not produce a stored image (@reason).', ['@reason' => $reason]));
    }

    $form_state->setRedirect('dungeoncrawler_content.character_view', ['character_id' => $character_id], $campaign_id > 0 ? ['query' => ['campaign_id' => $campaign_id]] : []);
  }

}
