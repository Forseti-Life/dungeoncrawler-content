<?php

namespace Drupal\dungeoncrawler_content\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Url;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the tabbed character setup shell.
 */
class CharacterSetupController extends ControllerBase {

  public function __construct(
    protected CharacterManager $characterManager,
    protected SchemaLoader $schemaLoader,
    protected CharacterCreationGmService $characterCreationGm,
    protected CsrfTokenGenerator $csrfToken,
    protected Connection $database,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.schema_loader'),
      $container->get('dungeoncrawler_content.character_creation_gm'),
      $container->get('csrf_token'),
      $container->get('database'),
    );
  }

  /**
   * Builds the tabbed character setup page.
   */
  public function page(Request $request): array {
    $campaign_id = $request->query->get('campaign_id');
    $requested_character_id = $request->query->get('character_id');

    $character = $requested_character_id !== NULL && $requested_character_id !== ''
      ? $this->loadOwnedCharacter((int) $requested_character_id)
      : $this->loadExistingDraft();

    if ($requested_character_id !== NULL && $requested_character_id !== '' && !$character) {
      throw new AccessDeniedHttpException('Access denied.');
    }

    if ($character && ($campaign_id === NULL || $campaign_id === '') && !empty($character->campaign_id)) {
      $campaign_id = (int) $character->campaign_id;
    }

    $character_id = $character ? (int) $character->id : NULL;
    $character_data = $character ? json_decode((string) $character->character_data, TRUE) : [];
    if (!is_array($character_data)) {
      $character_data = [];
    }

    $max_accessible_step = $character_id ? max(1, min(8, (int) ($character_data['step'] ?? 1))) : 1;
    $requested_step = max(1, min(8, (int) $request->query->get('step', $max_accessible_step)));
    $active_step = $character_id ? min($requested_step, $max_accessible_step) : 1;

    $steps = [];
    for ($step = 1; $step <= 8; $step++) {
      $schema = $this->schemaLoader->loadStepSchema($step) ?? [];
      $steps[] = [
        'number' => $step,
        'name' => $schema['properties']['step_name']['const']
          ?? $schema['properties']['step_name']['default']
          ?? $this->t('Step @step', ['@step' => $step]),
        'description' => $schema['properties']['step_description']['const']
          ?? $schema['properties']['step_description']['default']
          ?? '',
        'enabled' => $step <= $max_accessible_step,
        'url' => $this->buildSetupUrl($step, $character_id, $campaign_id),
      ];
    }

    $back_url = $campaign_id !== NULL && $campaign_id !== ''
      ? Url::fromRoute('dungeoncrawler_content.characters', ['campaign_id' => (int) $campaign_id])->toString()
      : Url::fromRoute('dungeoncrawler_content.characters_roster')->toString();

    $gm_settings = [
      'endpoint' => Url::fromRoute('dungeoncrawler_content.api.character_gm_chat')->toString(),
      'characterId' => $character_id,
      'campaignId' => $campaign_id !== NULL && $campaign_id !== '' ? (int) $campaign_id : NULL,
      'step' => $active_step,
      'csrfToken' => $this->csrfToken->get('rest'),
      'history' => $this->characterCreationGm->getChatHistory($character_data),
      'summary' => $this->characterCreationGm->buildSummary($character_data),
      'shellMode' => 'character_setup',
    ];

    return [
      '#theme' => 'character_setup_page',
      '#steps' => $steps,
      '#active_step' => $active_step,
      '#back_url' => $back_url,
      '#character_id' => $character_id,
      '#campaign_id' => $campaign_id !== NULL && $campaign_id !== '' ? (int) $campaign_id : NULL,
      '#summary' => $gm_settings['summary'],
      '#history' => $gm_settings['history'],
      '#step_form' => $this->formBuilder()->getForm(
        'Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm',
        $active_step,
        $character_id,
        $campaign_id
      ),
      '#attached' => [
        'library' => [
          'dungeoncrawler_content/character-setup-tabs',
          'dungeoncrawler_content/character-creation-gm-chat',
        ],
        'drupalSettings' => [
          'dungeoncrawlerCharacterSetup' => [
            'shellUrl' => Url::fromRoute('dungeoncrawler_content.character_setup')->toString(),
            'characterId' => $character_id,
            'campaignId' => $campaign_id !== NULL && $campaign_id !== '' ? (int) $campaign_id : NULL,
            'activeStep' => $active_step,
            'maxAccessibleStep' => $max_accessible_step,
          ],
          'dungeoncrawlerCharacterGm' => $gm_settings,
        ],
      ],
      '#cache' => [
        'contexts' => ['user', 'url.query_args:character_id', 'url.query_args:campaign_id', 'url.query_args:step'],
        'tags' => ['dc_campaign_characters'],
        'max-age' => 0,
      ],
    ];
  }

  /**
   * Loads a character owned by the current user or by an administrator.
   */
  private function loadOwnedCharacter(int $character_id): ?object {
    $character = $this->characterManager->loadCharacter($character_id);
    if (!$character) {
      return NULL;
    }

    $is_admin = $this->currentUser()->hasPermission('administer dungeoncrawler content');
    if ((int) $character->uid !== (int) $this->currentUser()->id() && !$is_admin) {
      return NULL;
    }

    return $character;
  }

  /**
   * Loads the current user's active draft, if one exists.
   */
  private function loadExistingDraft(): ?object {
    $draft_id = $this->database->select('dc_campaign_characters', 'c')
      ->fields('c', ['id'])
      ->condition('uid', (int) $this->currentUser()->id())
      ->condition('status', 0)
      ->range(0, 1)
      ->execute()
      ->fetchField();

    return $draft_id ? $this->loadOwnedCharacter((int) $draft_id) : NULL;
  }

  /**
   * Builds a setup-shell URL for a specific step.
   */
  private function buildSetupUrl(int $step, ?int $character_id, int|string|null $campaign_id): string {
    $query = [];
    $query['step'] = $step;
    if ($character_id) {
      $query['character_id'] = $character_id;
    }
    if ($campaign_id !== NULL && $campaign_id !== '') {
      $query['campaign_id'] = (int) $campaign_id;
    }
    return Url::fromRoute('dungeoncrawler_content.character_setup')
      ->setOption('query', $query)
      ->toString();
  }

}
