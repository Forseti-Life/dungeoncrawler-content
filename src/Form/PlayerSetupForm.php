<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;
use Drupal\dungeoncrawler_content\Service\PlayerSetupService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Party-owned setup dungeon; editing never moves live campaign actors.
 */
class PlayerSetupForm extends FormBase {

  public function __construct(
    private PlayerSetupService $playerSetup,
    private CampaignAuthorizationService $authorization,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.player_setup'),
      $container->get('dungeoncrawler_content.campaign_authorization'),
    );
  }

  public function getFormId(): string {
    return 'dungeoncrawler_player_setup';
  }

  public function access(AccountInterface $account, int $campaign_id): AccessResult {
    return AccessResult::allowedIf(!$account->isAnonymous() && (
      $account->hasPermission('administer dungeoncrawler content')
      || $this->authorization->canAccessCampaign($campaign_id, (int) $account->id())
    ))->cachePerUser()->cachePerPermissions()->addCacheTags(['dc_campaign:' . $campaign_id]);
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?int $campaign_id = NULL): array {
    $dungeon = $this->playerSetup->dungeon((int) $campaign_id);
    $mode = $this->getRequest()->query->get('room', 'camping');
    if (!is_string($mode) || !isset(PlayerSetupService::MODES[$mode])) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Unknown Playersetup room.');
    }
    $form_state->set('campaign_id', $campaign_id);
    $form_state->set('mode', $mode);
    $room = $dungeon['rooms'][$mode];
    $layout = $dungeon['layouts'][$mode] ?? [];
    $form['#attributes']['data-player-setup'] = $mode;
    $form['#cache'] = ['max-age' => 0];
    $form['#attached']['library'][] = 'dungeoncrawler_content/player-setup';
    $form['#attached']['drupalSettings']['playerSetup'] = [
      'room' => $room,
      'roster' => $dungeon['roster'],
    ];
    $form['intro'] = [
      '#type' => 'item',
      '#title' => $this->t('Playersetup dungeon - @room', ['@room' => $room['name']]),
      '#plain_text' => $this->t('Save reusable party defaults for camping and road ambushes. These are planning rooms: no time passes, no resources are spent, and nobody leaves the current adventure room.'),
    ];
    $form['rooms'] = ['#type' => 'container', '#attributes' => ['class' => ['player-setup__rooms']]];
    foreach (PlayerSetupService::MODES as $key => $label) {
      $form['rooms'][$key] = [
        '#type' => 'link',
        '#title' => $label . (isset($dungeon['layouts'][$key]) ? ' (saved)' : ' (needs setup)'),
        '#url' => Url::fromRoute('dungeoncrawler_content.player_setup', ['campaign_id' => $campaign_id], ['query' => ['room' => $key]]),
        '#attributes' => ['aria-current' => $key === $mode ? 'page' : 'false'],
      ];
    }
    if ($requirement = $this->playerSetup->restRequirement((int) $campaign_id)) {
      $form['status'] = ['#type' => 'item', '#plain_text' => $requirement['error']];
    }
    else {
      $form['status'] = ['#type' => 'item', '#plain_text' => $this->t('Both rooms are saved and ready for this party.')];
    }
    $form['instructions'] = ['#type' => 'item', '#plain_text' => $room['description'] . ' Select an actor below, then choose a hex on the map or use the position selector.'];
    $form['map'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['player-setup__map'], 'data-player-setup-map' => 'true'],
    ];
    $form['map_status'] = [
      '#type' => 'container',
      '#attributes' => ['data-player-setup-status' => 'true', 'role' => 'status', 'aria-live' => 'polite'],
    ];
    $options = ['' => (string) $this->t('- Choose a hex -')];
    foreach ($room['hexes'] as $hex) {
      $options[$hex['q'] . ':' . $hex['r']] = $hex['q'] . ', ' . $hex['r'];
    }
    $form['placements'] = [
      '#type' => 'table',
      '#header' => [$this->t('Include'), $this->t('Actor'), $this->t('Position (q, r)'), $this->t('Facing'), $this->t('Activity')],
      '#empty' => $this->t('No active party members. Join the campaign with a character before arranging a formation.'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['player-setup__placements']],
    ];
    foreach ($dungeon['roster'] as $id => $actor) {
      $placement = $layout[$id] ?? NULL;
      $form['placements'][$id]['include'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Include @actor', ['@actor' => $actor['name']]),
        '#title_display' => 'invisible',
        '#default_value' => $actor['required'] || $placement !== NULL,
        '#disabled' => $actor['required'],
      ];
      $form['placements'][$id]['actor'] = [
        '#type' => 'html_tag', '#tag' => 'button',
        '#value' => \Drupal\Component\Utility\Html::escape($actor['name'] . ($actor['required'] ? ' (party)' : ' (escort)')),
        '#attributes' => ['type' => 'button', 'data-setup-actor' => $id, 'aria-pressed' => 'false'],
      ];
      $form['placements'][$id]['hex'] = [
        '#type' => 'select', '#options' => $options,
        '#title' => $this->t('Position for @actor', ['@actor' => $actor['name']]), '#title_display' => 'invisible',
        '#default_value' => $placement === NULL ? '' : $placement['q'] . ':' . $placement['r'],
        '#attributes' => ['data-setup-position' => $id],
      ];
      $form['placements'][$id]['facing'] = [
        '#type' => 'select',
        '#title' => $this->t('Facing for @actor', ['@actor' => $actor['name']]), '#title_display' => 'invisible',
        '#options' => [0 => 'North', 1 => 'Northeast', 2 => 'Southeast', 3 => 'South', 4 => 'Southwest', 5 => 'Northwest'],
        '#default_value' => $placement['facing'] ?? 0,
      ];
      $form['placements'][$id]['duty'] = [
        '#type' => 'select',
        '#title' => $this->t('Activity for @actor', ['@actor' => $actor['name']]), '#title_display' => 'invisible',
        '#options' => $mode === 'camping' ? ['sleep' => 'Sleeping', 'watch' => 'On watch'] : ['travel' => 'Marching'],
        '#default_value' => $placement['duty'] ?? ($mode === 'camping' ? 'sleep' : 'travel'),
      ];
    }
    $form['revision'] = ['#type' => 'hidden', '#default_value' => $dungeon['revision']];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save @room default', ['@room' => $room['name']]),
      '#disabled' => $dungeon['roster'] === [],
    ];
    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $placements = [];
    foreach ($form_state->getValue('placements', []) as $id => $values) {
      if (empty($values['include'])) {
        continue;
      }
      if (!preg_match('/^(-?\d+):(-?\d+)$/', (string) $values['hex'], $matches)) {
        $form_state->setErrorByName('placements][' . $id . '][hex', $this->t('Choose a position for each included actor.'));
        continue;
      }
      $placements[$id] = [
        'q' => (int) $matches[1], 'r' => (int) $matches[2],
        'facing' => (int) $values['facing'], 'duty' => $values['duty'],
      ];
    }
    if ($form_state->hasAnyErrors()) {
      return;
    }
    try {
      $this->playerSetup->validateLayout($form_state->get('mode'), $placements, $this->playerSetup->roster($form_state->get('campaign_id')));
      $form_state->set('validated_placements', $placements);
    }
    catch (\InvalidArgumentException $exception) {
      $form_state->setErrorByName('placements', $exception->getMessage());
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      $this->playerSetup->save(
        $form_state->get('campaign_id'), $form_state->get('mode'),
        $form_state->get('validated_placements'), (int) $form_state->getValue('revision'),
      );
    }
    catch (\DomainException | \InvalidArgumentException $exception) {
      $this->messenger()->addError($exception->getMessage());
      $form_state->setRebuild();
      return;
    }
    $this->messenger()->addStatus($this->t('Room default saved. Live actors have not moved.'));
    $form_state->setRedirect('dungeoncrawler_content.player_setup', ['campaign_id' => $form_state->get('campaign_id')], [
      'query' => ['room' => $form_state->get('mode')],
    ]);
  }

}
