<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfRequestHeaderAccessCheck;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Legacy wizard transport; all editing resolves through the canonical form.
 */
class CharacterCreationStepController extends ControllerBase {

  public function __construct(
    protected CharacterManager $characterManager,
    protected CsrfTokenGenerator $csrfToken,
    protected Connection $database,
  ) {}

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('csrf_token'),
      $container->get('database'),
    );
  }

  public function start(Request $request): RedirectResponse {
    $character_id = (int) $request->query->get('character_id', 0);
    $campaign_id = (int) $request->query->get('campaign_id', 0);
    if (!$character_id) {
      $existing_draft = $this->database->select('dc_campaign_characters', 'c')
        ->fields('c', ['id'])
        ->condition('uid', (int) $this->currentUser()->id())
        ->condition('campaign_id', 0)
        ->condition('lifecycle_state', 'draft_library')
        ->condition('status', 0)
        ->range(0, 1)
        ->execute()
        ->fetchField();
      if ($existing_draft) {
        $character_id = (int) $existing_draft;
        $this->messenger()->addWarning($this->t('Resuming your existing draft character.'));
      }
    }
    $query = [];
    if ($character_id) {
      $character = $this->characterManager->loadCharacter($character_id);
      if (!$character || ((int) $character->uid !== (int) $this->currentUser()->id()
        && !$this->currentUser()->hasPermission('administer dungeoncrawler content'))) {
        throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
      }
      $data = json_decode($character->character_data, TRUE, 512, JSON_THROW_ON_ERROR);
      $query = ['character_id' => $character_id, 'step' => max(1, min(8, (int) ($data['step'] ?? 1)))];
      $campaign_id = (int) ($character->campaign_id ?: $campaign_id);
    }
    if ($campaign_id) {
      $query['campaign_id'] = $campaign_id;
    }
    return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.character_setup', [], ['query' => $query])->toString());
  }

  public function step(int $step, Request $request): RedirectResponse {
    if ($step < 1 || $step > 8) {
      throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Invalid character creation step.');
    }
    $query = ['step' => $step];
    foreach (['character_id', 'campaign_id'] as $key) {
      if ($request->query->has($key)) {
        $query[$key] = (int) $request->query->get($key);
      }
    }
    return new RedirectResponse(Url::fromRoute('dungeoncrawler_content.character_setup', [], ['query' => $query])->toString());
  }

  /**
   * Accept native form values, retaining CSRF, ownership, and revision checks.
   */
  public function saveStep(int $step, Request $request): JsonResponse {
    $token = $request->headers->get('X-CSRF-Token');
    if (!$token || !$this->csrfToken->validate($token, CsrfRequestHeaderAccessCheck::TOKEN_KEY)) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Invalid or missing CSRF token.'], 403);
    }
    if ($step < 1 || $step > 8) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Invalid character creation step.'], 400);
    }
    $values = $request->request->all();
    $character_id = (int) ($values['character_id'] ?? $request->query->get('character_id', 0));
    $campaign_id = (int) ($values['campaign_id'] ?? $request->query->get('campaign_id', 0));
    $character = $character_id ? $this->characterManager->loadCharacter($character_id) : NULL;
    if ($character_id && !$character) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Character draft not found.'], 404);
    }
    if ($character && (int) $character->uid !== (int) $this->currentUser()->id()
      && !$this->currentUser()->hasPermission('administer dungeoncrawler content')) {
      return new JsonResponse(['success' => FALSE, 'message' => 'Access denied.'], 403);
    }
    if ($campaign_id || ($character && (int) $character->campaign_id > 0)) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Campaign-scoped character creation saves must use the campaign wizard form submission path.',
      ], 400);
    }
    if ($character && (!array_key_exists('character_version', $values)
      || (int) $values['character_version'] !== (int) $character->version)) {
      return new JsonResponse(['success' => FALSE, 'message' => 'The character changed. Reload the wizard before saving.'], 409);
    }
    $state = new FormState();
    $state->setValues($values)->setUserInput($values);
    $this->formBuilder()->submitForm(CharacterCreationStepForm::class, $state, $step, $character_id ?: NULL, NULL);
    if ($state->hasAnyErrors()) {
      $errors = array_map(static fn(mixed $message): string => (string) $message, $state->getErrors());
      $state->clearErrors();
      return new JsonResponse(['success' => FALSE, 'errors' => $errors], 422);
    }
    $redirect = $state->get('wizard_redirect');
    if (!$redirect instanceof Url || !(int) $state->get('saved_character_id')) {
      throw new \LogicException('Character wizard submission returned without a saved character and redirect.');
    }
    return new JsonResponse([
      'success' => TRUE,
      'character_id' => (int) $state->get('saved_character_id'),
      'redirect' => $redirect->toString(),
    ]);
  }

}
