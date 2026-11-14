<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Utility\Html;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService;
use Drupal\dungeoncrawler_content\Service\CampaignCharacterRuntimeResolverService;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterRulesCatalog;
use Drupal\dungeoncrawler_content\Service\CharacterRulesUtility;
use Drupal\dungeoncrawler_content\Service\CharacterWizardHardeningService;
use Drupal\dungeoncrawler_content\Service\FamiliarService;
use Drupal\dungeoncrawler_content\Service\FactionGenerationService;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form for character creation steps.
 */
class CharacterCreationStepForm extends FormBase {

  private const STRUCTURED_AFFILIATION_CREATE_SENTINEL = \Drupal\dungeoncrawler_content\Service\CharacterCreationValidationService::STRUCTURED_AFFILIATION_CREATE_SENTINEL;
  private const STRUCTURED_AFFILIATION_FIELDS = \Drupal\dungeoncrawler_content\Service\CharacterCreationValidationService::STRUCTURED_AFFILIATION_FIELDS;

  /**
   * Constructs a CharacterCreationStepForm object.
   */
  public function __construct(
    protected CharacterManager $characterManager,
    protected SchemaLoader $schemaLoader,
    protected Connection $database,
    protected UuidInterface $uuid,
    protected AccountProxyInterface $currentUser,
    protected DateFormatterInterface $dateFormatter,
    protected TimeInterface $time,
    protected CharacterPortraitGenerationService $portraitGenerator,
    protected AbilityScoreTracker $abilityScoreTracker,
    protected ImageGenerationIntegrationService $imageGenerationIntegration,
    protected CharacterCreationGmService $characterCreationGm,
    protected FeatLibraryService $featLibrary,
    protected CsrfTokenGenerator $csrfToken,
    protected CampaignSubjectRegistryService $campaignSubjectRegistry,
    protected InstitutionMembershipService $institutionMembership,
    protected InstitutionNormalizationService $institutionNormalization,
    protected FactionGenerationService $factionGeneration,
    protected CampaignCharacterRuntimeResolverService $runtimeResolver,
    protected CharacterWizardHardeningService $wizardHardening,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('dungeoncrawler_content.character_manager'),
      $container->get('dungeoncrawler_content.schema_loader'),
      $container->get('database'),
      $container->get('uuid'),
      $container->get('current_user'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('dungeoncrawler_content.character_portrait_generator'),
      $container->get('dungeoncrawler_content.ability_score_tracker'),
      $container->get('dungeoncrawler_content.image_generation_integration'),
      $container->get('dungeoncrawler_content.character_creation_gm'),
      $container->get('dungeoncrawler_content.feat_library'),
      $container->get('csrf_token'),
      $container->get('dungeoncrawler_content.campaign_subject_registry'),
      $container->get('dungeoncrawler_content.institution_membership'),
      $container->get('dungeoncrawler_content.institution_normalization'),
      $container->get('dungeoncrawler_content.faction_generation'),
      $container->get('dungeoncrawler_content.campaign_character_runtime_resolver'),
      $container->get('dungeoncrawler_content.character_wizard_hardening'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'character_creation_step_form';
  }

  private static function validationRules(): \Drupal\dungeoncrawler_content\Service\CharacterCreationValidationService {
    return \Drupal::service('dungeoncrawler_content.character_creation_validation');
  }

  public function recalculateDraft(array $character_data): array {
    return $this->storeCalculatedAbilityScores($character_data, $this->abilityScoreTracker->calculateAbilityScores($character_data));
  }

  public function creationEquipmentCatalog(): array {
    return $this->getEquipmentCatalog();
  }

  /**
   * Project GM choices with the same pure step transformations as form submits.
   */
  public function projectDraft(array $data, array $changed_fields): array {
    $steps = [];
    foreach ([
      2 => ['ancestry', 'heritage', 'ancestry_feat', 'ancestry_boosts', 'feat_selections'],
      3 => ['background', 'background_boosts'],
      4 => ['ancestry_feat', 'class', 'class_key_ability', 'class_feat', 'subclass', 'arcane_thesis', 'cantrips', 'spells_first', 'feat_selections'],
      5 => ['free_boosts'],
      6 => ['general_feat', 'trained_skills', 'feat_selections'],
      7 => ['equipment_ids'],
    ] as $step => $fields) {
      if (array_intersect($fields, $changed_fields) !== []) {
        $steps[] = $step;
      }
    }
    foreach ($steps as $step) {
      $values = $data;
      if ($step === 7) {
        $catalog = $this->getEquipmentCatalog();
        $ids = $data['gm_equipment_ids'] ?? [];
        $known_ids = array_merge(...array_values(array_map(static fn(array $items): array => array_column($items, 'id'), $catalog)));
        if (array_diff($ids, $known_ids) !== []) {
          throw new \InvalidArgumentException('Unknown starting equipment IDs: ' . implode(', ', array_diff($ids, $known_ids)));
        }
        foreach ($catalog as $group => $items) {
          $values[$group] = array_values(array_intersect($ids, array_column($items, 'id')));
        }
      }
      $state = (new \Drupal\Core\Form\FormState())->set('step', $step)->setValues($values);
      $data = $this->projectStepValues($data, $step, $state)['character_data'];
    }
    $data = $this->recalculateDraft($data);
    return $this->characterManager->canonicalizeCharacterData($this->wizardHardening->syncWizardDraftFromCharacterData($data));
  }

  /**
   * Persist a validated GM patch through the wizard's canonical save authority.
   */
  public function saveValidatedDraft(?int $character_id, array $data, int $expected_revision, ?int $campaign_id): int {
    return (int) $this->saveCharacter($character_id, $data, $character_id ? $expected_revision + 1 : 0, $campaign_id);
  }

  /**
   * Delegate draft inspection to the shared validation subsystem.
   */
  public function validateDraft(array $character_data, ?int $campaign_id = NULL, bool $include_option_details = FALSE): array {
    return $this->characterCreationGm->validateData($character_data, $campaign_id, $include_option_details);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $step = 1, int|string|null $character_id = NULL, int|string|null $campaign_id = NULL): array {
    $embedded = (bool) $this->getRequest()->query->get('embedded');
    $setup_shell = $this->getRequest()->getPathInfo() === '/charactersetup';
    $compact_layout = $embedded || $setup_shell;
    $character_record = $character_id ? $this->characterManager->loadCharacter((int) $character_id) : NULL;
    $effective_campaign_id = $this->resolveEffectiveCampaignId($character_record, $campaign_id);
    $character_data = $this->loadCharacterData($character_id);

    // Load character record for concurrent-edit version tracking.
    $form['character_version'] = [
      '#type' => 'hidden',
      '#default_value' => $character_record ? (int) ($character_record->version ?? 0) : 0,
    ];

    // Store metadata
    $form_state->set('step', $step);
    $form_state->set('character_id', $character_id);
    $form_state->set('campaign_id', $effective_campaign_id);

    if ($effective_campaign_id > 0) {
      $form['campaign_id'] = [
        '#type' => 'hidden',
        '#value' => $effective_campaign_id,
      ];
    }

    // Load schema for tips and descriptions
    $schema = $this->schemaLoader->loadStepSchema($step);
    $step_name = $schema['properties']['step_name']['const']
      ?? $schema['properties']['step_name']['default']
      ?? "Step {$step}";
    $step_description = $schema['properties']['step_description']['const']
      ?? $schema['properties']['step_description']['default']
      ?? '';

    $form['#attributes']['class'][] = 'character-creation-form';
    $form['#attributes']['class'][] = $compact_layout ? 'character-creation-form--embedded' : 'character-creation-form--standalone';
    // Disable browser-native HTML5 validation entirely: Drupal handles all
    // validation server-side, and the native :invalid CSS pseudo-class fires
    // on required-but-empty fields immediately on page load / after AJAX,
    // causing premature red styling before the user has interacted.
    $form['#attributes']['novalidate'] = 'novalidate';
    $form['#attached']['library'][] = 'dungeoncrawler_content/character-step-base';
    $form['#attached']['library'][] = 'dungeoncrawler_content/character-creation-style';
    $form['#attached']['library'][] = 'dungeoncrawler_content/ability-widget';
    $form['#attached']['library'][] = 'dungeoncrawler_content/character-step-' . $step;

    // Steps with interactive ability boost widgets need the selector JS.
    if (in_array($step, [2, 3, 5], TRUE)) {
      $form['#attached']['library'][] = 'dungeoncrawler_content/ability-boost-selector';
    }
    
    if (!$compact_layout) {
      $form['#attached']['library'][] = 'dungeoncrawler_content/character-creation-gm-chat';
      $form['#attached']['drupalSettings']['dungeoncrawlerCharacterGm'] = [
        'endpointTemplate' => Url::fromRoute('dungeoncrawler_content.api.character_creation_gm', ['draft_id' => '0'])->toString(),
        'createEndpoint' => Url::fromRoute('dungeoncrawler_content.api.character_creation_draft')->toString(),
        'characterId' => $character_id ? (int) $character_id : NULL,
        'campaignId' => $effective_campaign_id > 0 ? $effective_campaign_id : NULL,
        'step' => $step,
        'csrfToken' => $this->csrfToken->get(\Drupal\Core\Access\CsrfRequestHeaderAccessCheck::TOKEN_KEY),
        'history' => $this->characterCreationGm->getChatHistory($character_data),
        'summary' => $this->characterCreationGm->buildSummary($character_data),
      ];
    }

    $prefix = $compact_layout ? '' : $this->buildGmChatShell($step, $character_id, $effective_campaign_id, $character_data);
    $progress_markup = $compact_layout ? '' : '<div class="progress-bar"><div class="progress-indicator progress-step-' . $step . '"></div></div><div class="progress-text">' . $this->t('Step @step of @total', ['@step' => $step, '@total' => 8]) . '</div>';
    $form['#prefix'] = Markup::create($prefix
      . '<div class="character-creation-step' . ($compact_layout ? ' character-creation-step--embedded' : '') . '"><div class="creation-container">' . $progress_markup . '<div class="step-content">');
    $form['#suffix'] = Markup::create('</div></div></div>' . ($compact_layout ? '' : '</div>'));

    $show_quick_play = $effective_campaign_id > 0
      && (int) $step === 1
      && empty($character_id);
    if ($show_quick_play) {
      $form['quick_play_banner'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['character-creation-quick-play']],
        'button' => $this->buildQuickPlayButton(),
      ];
    }

    $form['header'] = [
      '#markup' => "<h2>{$step_name}</h2><p class=\"step-description\">{$step_description}</p>",
    ];

    $tips_items = $this->extractStepTips($schema);
    if (!empty($tips_items)) {
      $form['tips'] = [
        '#type' => 'details',
        '#title' => $this->t('Legacy Player Tips'),
        '#open' => FALSE,
        '#attributes' => ['class' => ['tips-section']],
      ];
      $form['tips']['list'] = [
        '#theme' => 'item_list',
        '#items' => $tips_items,
        '#attributes' => ['class' => ['tips-list']],
      ];
    }

    // Build step-specific fields
    $this->buildStepFields($form, $form_state, $step, $character_data);
    $this->applyInputStylingClasses($form);

    // Navigation buttons
    $form['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['button-group']],
    ];
    
    if ($step > 1 && !$compact_layout) {
      $back_query = ['character_id' => $character_id];
      if ($campaign_id) {
        $back_query['campaign_id'] = $campaign_id;
      }
      $back_query = $this->preserveShellQueryFlags($back_query);

      $form['actions']['back'] = [
        '#type' => 'link',
        '#title' => $this->t('← Back'),
        '#url' => Url::fromRoute('dungeoncrawler_content.character_step', [
          'step' => max(1, (int) $step - 1),
        ])->setOption('query', $back_query),
        '#attributes' => ['class' => ['btn', 'btn-secondary']],
      ];
    }

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $step < 8
        ? ($embedded ? $this->t('Save Changes') : $this->t('Next →'))
        : $this->t('Create Legacy Character'),
      '#name' => 'wizard_next',
      '#attributes' => [
        'class' => ['btn', 'btn-primary'],
        'data-character-setup-submit' => 'wizard_next',
      ],
    ];
    if ($setup_shell) {
      $form['actions']['submit']['#ajax'] = [
        'callback' => '::workspaceSubmit',
        'disable-refocus' => TRUE,
        'progress' => ['type' => 'throbber', 'message' => $this->t('Waiting for the server to validate and save this step...')],
      ];
      $query = ['step' => $step];
      if ($character_id) {
        $query['character_id'] = (int) $character_id;
      }
      if ($effective_campaign_id > 0) {
        $query['campaign_id'] = $effective_campaign_id;
      }
      $form['#action'] = Url::fromRoute('dungeoncrawler_content.character_setup', [], ['query' => $query])->toString();
      // Fragments render on a GET route, but every form callback posts to setup.
      $bind = function (array &$element) use (&$bind, $query): void {
        if (isset($element['#ajax']['callback'])) {
          $element['#ajax']['url'] = Url::fromRoute('dungeoncrawler_content.character_setup');
          $element['#ajax']['options']['query'] = $query + ['ajax_form' => 1];
        }
        foreach ($element as $key => &$child) {
          if (!str_starts_with((string) $key, '#') && is_array($child)) {
            $bind($child);
          }
        }
      };
      $bind($form);
    }

    return $form;
  }

  public function workspaceSubmit(array &$form, FormStateInterface $form_state): \Drupal\Core\Ajax\AjaxResponse {
    $response = new \Drupal\Core\Ajax\AjaxResponse();
    $redirect = $form_state->get('wizard_redirect');
    if (!$form_state->hasAnyErrors() && $redirect instanceof Url) {
      if ($redirect->getRouteName() === 'dungeoncrawler_content.character_setup') {
        $response->addCommand(new \Drupal\Core\Ajax\InvokeCommand('[data-character-setup-root]', 'trigger', [
          'characterWorkspaceSaved', $redirect->getOption('query'),
        ]));
      }
      else {
        $response->addCommand(new \Drupal\Core\Ajax\RedirectCommand($redirect->toString()));
      }
      return $response;
    }
    $step = (int) $form_state->get('step');
    $schema = $this->schemaLoader->loadStepSchema($step);
    $response->addCommand(new \Drupal\Core\Ajax\ReplaceCommand('[data-character-step-region]', [
      '#theme' => 'character_setup_step',
      '#active_step' => $step,
      '#step_name' => $schema['properties']['step_name']['const'] ?? 'Step ' . $step,
      '#step_form' => $form,
      '#messages' => ['#type' => 'status_messages'],
    ]));
    return $response;
  }

  private function redirectAfterSave(FormStateInterface $form_state, string $route, array $parameters = [], array $options = []): void {
    $url = Url::fromRoute($route, $parameters, $options);
    $form_state->set('wizard_redirect', $url);
    $form_state->setRedirectUrl($url);
  }

  /**
   * Builds the quick-play launch link.
   */
  private function buildQuickPlayButton(): array {
    $campaign_id = $this->getRequest()->query->get('campaign_id');
    return [
      '#type' => 'link',
      '#title' => $this->t('I Just Want to Play'),
      '#url' => Url::fromRoute('dungeoncrawler_content.campaign_quick_play_character', [
        'campaign_id' => (int) $campaign_id,
      ]),
      '#attributes' => [
        'class' => ['btn', 'btn-secondary'],
        'data-character-setup-quick-play' => '1',
      ],
    ];
  }

  /**
   * Extracts step tips from schema in either string or object format.
   *
   * @param array $schema
   *   Loaded step schema array.
   *
   * @return array
   *   Renderable tip strings.
   */
  private function extractStepTips(array $schema): array {
    $raw_tips = $schema['properties']['tips']['default'] ?? NULL;
    if (!is_array($raw_tips)) {
      return [];
    }

    $tips = [];
    foreach ($raw_tips as $tip) {
      if (is_string($tip) && trim($tip) !== '') {
        $tips[] = Html::escape($tip);
      }
      elseif (is_array($tip)) {
        $title = trim((string) ($tip['title'] ?? ''));
        $text = trim((string) ($tip['text'] ?? ''));
        if ($title !== '' && $text !== '') {
          $tips[] = Html::escape($title . ': ' . $text);
        }
        elseif ($text !== '') {
          $tips[] = Html::escape($text);
        }
      }
    }

    return $tips;
  }

  /**
   * Builds the GM chat shell that wraps the wizard UI.
   */
  private function buildGmChatShell(int $step, int|string|null $character_id, int|string|null $campaign_id, array $character_data): string {
    $summary = $this->characterCreationGm->buildSummary($character_data);
    $summary_bits = [];
    foreach ([
      'name' => 'Name',
      'ancestry' => 'Ancestry',
      'class' => 'Class',
      'background' => 'Background',
    ] as $key => $label) {
      if (!empty($summary[$key])) {
        $summary_bits[] = '<span class="character-creation-gm-chat__pill"><strong>' . Html::escape($label) . ':</strong> ' . Html::escape((string) $summary[$key]) . '</span>';
      }
    }

    $history_markup = '';
    foreach ($this->characterCreationGm->getChatHistory($character_data) as $entry) {
      $role = (string) ($entry['role'] ?? 'assistant');
      $content = trim((string) ($entry['content'] ?? ''));
      if ($content === '') {
        continue;
      }
      $history_markup .= '<div class="character-creation-gm-chat__message character-creation-gm-chat__message--' . Html::escape($role) . '">'
        . '<div class="character-creation-gm-chat__message-role">' . Html::escape($role === 'user' ? 'You' : 'GM') . '</div>'
        . '<div class="character-creation-gm-chat__message-body">' . nl2br(Html::escape($content)) . '</div>'
        . '</div>';
    }

    if ($history_markup === '') {
      $history_markup = '<div class="character-creation-gm-chat__message character-creation-gm-chat__message--assistant">'
        . '<div class="character-creation-gm-chat__message-role">' . Html::escape((string) $this->t('GM')) . '</div>'
        . '<div class="character-creation-gm-chat__message-body">'
        . Html::escape((string) $this->t('Welcome to Character Creation. Building a complete character is hard; describe the character you want, ask for recommendations, or ask me to update the draft while you remain in control of every choice.'))
        . '</div>'
        . '</div>';
    }

    $root_attributes = [
      'class' => 'character-creation-shell',
      'data-step' => (string) $step,
      'data-character-id' => $character_id ? (string) $character_id : '',
      'data-campaign-id' => $campaign_id ? (string) $campaign_id : '',
    ];

    return '<div ' . new \Drupal\Core\Template\Attribute($root_attributes) . '>'
      . '<section class="character-creation-gm-chat" aria-label="' . Html::escape((string) $this->t('GM character guide')) . '">'
      . '<div class="character-creation-gm-chat__header">'
      . '<div>'
      . '<div class="character-creation-gm-chat__eyebrow">' . Html::escape((string) $this->t('GM-assisted creation')) . '</div>'
      . '<h2 class="character-creation-gm-chat__title">' . Html::escape((string) $this->t('GM Character Guide')) . '</h2>'
      . '<p class="character-creation-gm-chat__intro">' . Html::escape((string) $this->t('Talk to the GM to get suggestions or have the draft updated for you. The normal wizard stays right below, so you can mix chat guidance with manual edits at any time.')) . '</p>'
      . '</div>'
      . '<div class="character-creation-gm-chat__summary">'
      . implode('', $summary_bits)
      . '<span class="character-creation-gm-chat__pill"><strong>' . Html::escape((string) $this->t('Step')) . ':</strong> ' . $step . '/8</span>'
      . '</div>'
      . '</div>'
      . '<div class="character-creation-gm-chat__history" data-gm-chat-history>'
      . $history_markup
      . '</div>'
      . '<div class="character-creation-gm-chat__composer">'
      . '<label class="character-creation-gm-chat__label" for="characterCreationGmInput">' . Html::escape((string) $this->t('Message the GM')) . '</label>'
      . '<textarea id="characterCreationGmInput" class="character-creation-gm-chat__input" rows="3" placeholder="' . Html::escape((string) $this->t('Example: build me a sturdy dwarf fighter with a simple backstory and heavy armor.')) . '"></textarea>'
      . '<div class="character-creation-gm-chat__actions">'
      . '<div class="character-creation-gm-chat__status" data-gm-chat-status></div>'
      . '<span class="character-creation-gm-chat__hint">' . Html::escape((string) $this->t('Enter to send · Shift + Enter for a new line')) . '</span>'
      . '<button type="button" class="btn btn-primary character-creation-gm-chat__send" data-gm-chat-send>' . Html::escape((string) $this->t('Send to GM')) . '</button>'
      . '</div>'
      . '</div>'
      . '</section>';
  }

  /**
   * Applies shared styling classes to standard form controls.
   *
   * @param array $elements
   *   Form elements array.
   */
  private function applyInputStylingClasses(array &$elements): void {
    $input_types = ['textfield', 'textarea', 'select', 'number'];

    foreach ($elements as &$element) {
      if (!is_array($element)) {
        continue;
      }

      if (isset($element['#type']) && in_array($element['#type'], $input_types, TRUE)) {
        $element['#wrapper_attributes']['class'][] = 'form-group';
        $element['#attributes']['class'][] = 'form-control';
      }

      $this->applyInputStylingClasses($element);
    }
  }

  /**
   * Apply HTML5 validation attributes from schema definitions.
   *
   * @param array $element
   *   The form element to update.
   * @param array $schema_fields
   *   Schema field definitions for the current step.
   * @param string $field_name
   *   Field name to look up in schema.
   */
  private function applySchemaValidationAttributes(array &$element, array $schema_fields, string $field_name): void {
    $this->validationRules()->applySchemaValidationAttributes($element, $schema_fields, $field_name);
  }

  /**
   * Read a constraint value from a schema node.
   *
   * @param array|null $constraint
   *   Schema node containing const/default values.
   *
   * @return mixed|null
   *   Constraint value, or NULL when absent.
   */
  private function getSchemaConstraintValue(?array $constraint): mixed {
    return $this->validationRules()->getSchemaConstraintValue($constraint);
  }

  /**
   * Builds step-specific form fields.
   *
   * @param array $form
   *   The form array to add fields to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param int $step
   *   The current step number (1-8).
   * @param array $character_data
   *   The character data for default values.
   */
  private function buildStepFields(array &$form, FormStateInterface $form_state, int $step, array $character_data): void {
    $schema_fields = $this->schemaLoader->getStepFields($step);
    $method = 'buildStep' . $step . 'Fields';
    if (method_exists($this, $method)) {
      $this->$method($form, $form_state, $character_data, $schema_fields);
    }
  }

  /**
   * Attaches the ability score preview widget to the form.
   */
  private function attachAbilityPreview(array &$form, array $character_data, string $help_text, bool $show_sources = TRUE, string $mode = 'compact'): void {
    $this->validationRules()->attachAbilityPreview($form, $character_data, $help_text, $show_sources, $mode);
  }

  /**
   * Builds Step 1 fields.
   */
  private function buildStep1Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->validationRules()->buildStep1Fields($form, $form_state, $character_data, $schema_fields);
  }

  /**
   * Builds Step 2 fields.
   */
  private function buildStep2Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->validationRules()->buildStep2Fields($form, $form_state, $character_data, $schema_fields);
  }

  /**
   * Builds Step 3 fields.
   */
  private function buildStep3Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->validationRules()->buildStep3Fields($form, $form_state, $character_data, $schema_fields);
  }

  /**
   * Builds Step 4 fields.
   */
  private function buildStep4Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->validationRules()->buildStep4Fields($form, $form_state, $character_data, $schema_fields);
  }

  /**
   * Applies a tabbed layout to the dynamic Step 4 class-selection sections.
   */
  private function applyStep4TabbedLayout(array &$container, FormStateInterface $form_state, string $selected_class): void {
    $this->validationRules()->applyStep4TabbedLayout($container, $form_state, $selected_class);
  }

  /**
   * Registers a top-level Step 4 panel by wrapping existing sibling elements.
   */
  private function registerStep4Panel(array &$container, array &$tabs, string $tab_id, string|\Stringable $label, array $start_candidates, array $end_candidates): void {
    $this->validationRules()->registerStep4Panel($container, $tabs, $tab_id, $label, $start_candidates, $end_candidates);
  }

  /**
   * Returns the most appropriate subclass tab label for Step 4.
   */
  private function getStep4SubclassTabLabel(string $selected_class): string {
    return $this->validationRules()->getStep4SubclassTabLabel($selected_class);
  }

  /**
   * Returns the tab label for a Step 4 feat selection panel.
   */
  private function getStep4FeatSelectionTabLabel(string $selection_key): string {
    return $this->validationRules()->getStep4FeatSelectionTabLabel($selection_key);
  }

  /**
   * Builds Step 5 fields.
   */
  private function buildStep5Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->validationRules()->buildStep5Fields($form, $form_state, $character_data, $schema_fields);
  }

  /**
   * Builds Step 6 fields.
   */
  private function buildStep6Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->validationRules()->buildStep6Fields($form, $form_state, $character_data, $schema_fields);
  }

  /**
   * Builds Step 7 fields.
   */
  private function buildStep7Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->validationRules()->buildStep7Fields($form, $form_state, $character_data, $schema_fields);
  }

  /**
   * Build the current class's deterministic step-7 default loadout preset.
   *
   * @param string $class_id
   *   Current character class machine name.
   * @param array<string,array<string,mixed>> $catalog_by_id
   *   Equipment catalog keyed by item id.
   *
   * @return array<string,mixed>|null
   *   Preset metadata for the active class, or NULL when unavailable.
   */
  private function buildStep7ClassLoadoutPreset(string $class_id, array $catalog_by_id): ?array {
    return $this->validationRules()->buildStep7ClassLoadoutPreset($class_id, $catalog_by_id);
  }

  /**
   * Render Step 7 loadout items as compact chips.
   *
   * @param array<int,array<string,mixed>> $items
   *   Preset item list.
   */
  private function buildStep7LoadoutItemMarkup(array $items): string {
    return $this->validationRules()->buildStep7LoadoutItemMarkup($items);
  }

  /**
   * Determine whether the current Step 7 selection matches a preset exactly.
   *
   * @param array<int,string> $selected_ids
   *   Current selected equipment ids.
   * @param array<int,string> $preset_ids
   *   Preset equipment ids.
   */
  private function step7SelectionMatchesPreset(array $selected_ids, array $preset_ids): bool {
    return $this->validationRules()->step7SelectionMatchesPreset($selected_ids, $preset_ids);
  }

  /**
   * Builds Step 8 fields.
   */
  private function buildStep8Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->validationRules()->buildStep8Fields($form, $form_state, $character_data, $schema_fields);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $character_id = (int) $form_state->get('character_id');
    if ($character_id) {
      $record = $this->characterManager->loadCharacter($character_id);
      if (!$record || ((int) $record->uid !== (int) $this->currentUser->id()
        && !$this->currentUser->hasPermission('administer dungeoncrawler content'))) {
        $form_state->setErrorByName('character_version', $this->t('Character draft access denied.'));
        return;
      }
      if ((int) $record->version !== (int) $form_state->getValue('character_version', -1)) {
        $form_state->setErrorByName('character_version', $this->t('The character changed in another editor. Reload the latest character before applying manual edits.'));
        return;
      }
    }
    $step = (int) $form_state->get('step');
    $data = array_replace($this->loadCharacterData($character_id), $form_state->getValues());
    $report = $this->characterCreationGm->validateData($data, (int) $form_state->get('campaign_id'));
    foreach (array_merge($report['errors'], $report['warnings']) as $issue) {
      if ($step === 8 || $issue['step'] === $step) {
        $form_state->setErrorByName($step === 8 ? 'wizard_readiness_' . $issue['step'] . '_' . $issue['field'] : $issue['field'], $issue['message']);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $trigger_name = (string) ($trigger['#name'] ?? '');
    $trigger_value = (string) ($trigger['#value'] ?? '');

    $step = $form_state->get('step');
    $character_id = $form_state->get('character_id');
    $campaign_id = $form_state->get('campaign_id');
    $setup_shell = $this->getRequest()->getPathInfo() === '/charactersetup';
    $character_data = $this->loadCharacterData($character_id);
    $existing_record = $character_id ? $this->characterManager->loadCharacter((int) $character_id) : NULL;
    $existing_payload = [];
    if ($existing_record) {
      $existing_payload = json_decode((string) ($existing_record->character_data ?? '{}'), TRUE);
      if (!is_array($existing_payload)) {
        $existing_payload = [];
      }
    }
    $this->getLogger('dungeoncrawler_content')->notice('Character setup submit: step=@step campaign_id=@campaign_id character_id=@character_id trigger=@trigger value=@value setup_shell=@setup_shell stored_status=@status stored_step=@stored_step request_path=@path', [
      '@step' => (int) $step,
      '@campaign_id' => (string) ($campaign_id ?? ''),
      '@character_id' => (string) ($character_id ?? ''),
      '@trigger' => $trigger_name !== '' ? $trigger_name : '(unnamed)',
      '@value' => $trigger_value,
      '@setup_shell' => $setup_shell ? '1' : '0',
      '@status' => $existing_record ? (int) ($existing_record->status ?? -1) : -1,
      '@stored_step' => (int) ($existing_payload['step'] ?? 0),
      '@path' => (string) $this->getRequest()->getPathInfo(),
    ]);

    // Concurrent-edit protection: reject if another session saved since form load.
    $submitted_version = (int) $form_state->getValue('character_version', 0);
    if ($character_id) {
      $current_record = $this->characterManager->loadCharacter((int) $character_id);
      if ($current_record && (int) $current_record->version !== $submitted_version) {
        $this->messenger()->addError($this->t('This character is being edited in another browser session. Please reload and try again.'));
        $query = ['character_id' => $character_id];
        if ($campaign_id) {
          $query['campaign_id'] = $campaign_id;
        }
        $query = $this->preserveShellQueryFlags($query);
        if ($setup_shell) {
          $query['step'] = $step;
          $this->redirectAfterSave($form_state, 'dungeoncrawler_content.character_setup', [], ['query' => $query]);
        }
        else {
          $this->redirectAfterSave($form_state, 'dungeoncrawler_content.character_step', ['step' => $step], ['query' => $query]);
        }
        return;
      }
    }
    $next_version = $submitted_version + 1;
    $projection = $this->projectStepValues($character_data, (int) $step, $form_state);
    $this->persistSubmittedDraft(
      $form_state, $projection['character_data'], (int) $step,
      $character_id, $campaign_id, $next_version, $setup_shell, $projection['selected_items'],
    );
  }

  private function projectStepValues(array $character_data, int $step, FormStateInterface $form_state): array {
    // Update character data with form values.
    // Exclude internal Drupal keys AND the step-7 checkbox groups (weapons,
    // armor, gear) which contain raw Drupal checkbox arrays ({id: id|0}).
    // Step 7 builds its own cleaned equipment/inventory structures below.
    $exclude_keys = [
      'form_build_id', 'form_token', 'form_id', 'op', 'character_version',
      'weapons', 'armor', 'gear',
    ];
    $exclude_keys = array_merge($exclude_keys, $this->getStructuredAffiliationAuxiliaryKeys());
    foreach ($form_state->getValues() as $key => $value) {
      if (!in_array($key, $exclude_keys, TRUE)) {
        // Handle JSON-encoded hidden fields
        if (in_array($key, ['ancestry_boosts', 'background_boosts', 'free_boosts'], TRUE) && is_string($value)) {
          $decoded = json_decode($value, TRUE);
          $character_data[$key] = is_array($decoded) ? $decoded : [];
        }
        else {
          $character_data[$key] = $value;
        }
      }
    }

    // After steps 2, 3, 4, or 5: Recalculate ability scores using tracker service.
    if (in_array($step, [2, 3, 4, 5], TRUE)) {
      $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
      $character_data = $this->storeCalculatedAbilityScores($character_data, $calculation);
    }

    // Step 3: derive and store background skill training, lore, and feat.
    // These are display-only in the form (markup) so they must be applied here.
    if ((int) $step === 3 && !empty($character_data['background'])) {
      $bg = CharacterRulesCatalog::BACKGROUNDS[$character_data['background']] ?? NULL;
      if ($bg) {
        $character_data['background_skill_training'] = $bg['skill'] ?? '';
        $character_data['background_lore_skill']     = $bg['lore'] ?? '';
        $character_data['background_skill_feat']     = $bg['feat'] ?? '';
      }
    }

    if ((int) $step === 2) {
      if (($character_data['ancestry_feat'] ?? '') !== 'first-world-magic') {
        unset($character_data['feat_selections']['first-world-magic']);
      }
      else {
        $selection = $character_data['feat_selections']['first-world-magic'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_cantrip'] = trim((string) $form_state->getValue(
          ['feat_selections', 'first-world-magic', 'selected_cantrip'],
          $selection['selected_cantrip'] ?? ''
        ));
        $character_data['feat_selections']['first-world-magic'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'otherworldly-magic') {
        unset($character_data['feat_selections']['otherworldly-magic']);
      }
      else {
        $selection = $character_data['feat_selections']['otherworldly-magic'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_cantrip'] = trim((string) $form_state->getValue(
          ['feat_selections', 'otherworldly-magic', 'selected_cantrip'],
          $selection['selected_cantrip'] ?? ''
        ));
        $character_data['feat_selections']['otherworldly-magic'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'general-training') {
        unset($character_data['feat_selections']['general-training']);
      }
      else {
        $selection = $character_data['feat_selections']['general-training'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['bonus_general_feat'] = trim((string) $form_state->getValue(
          ['feat_selections', 'general-training', 'bonus_general_feat'],
          $selection['bonus_general_feat'] ?? ''
        ));
        $character_data['feat_selections']['general-training'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'elf-atavism') {
        unset($character_data['feat_selections']['elf-atavism']);
      }
      else {
        $selection = $character_data['feat_selections']['elf-atavism'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_feat'] = trim((string) $form_state->getValue(
          ['feat_selections', 'elf-atavism', 'selected_feat'],
          $selection['selected_feat'] ?? ''
        ));
        $character_data['feat_selections']['elf-atavism'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'multitalented') {
        unset($character_data['feat_selections']['multitalented']);
      }
      else {
        $selection = $character_data['feat_selections']['multitalented'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_skill'] = trim((string) $form_state->getValue(
          ['feat_selections', 'multitalented', 'selected_skill'],
          $selection['selected_skill'] ?? ''
        ));
        $selection['selected_language'] = trim((string) $form_state->getValue(
          ['feat_selections', 'multitalented', 'selected_language'],
          $selection['selected_language'] ?? ''
        ));
        $character_data['feat_selections']['multitalented'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'mixed-heritage-adaptability') {
        unset($character_data['feat_selections']['mixed-heritage-adaptability']);
      }
      else {
        $selection = $character_data['feat_selections']['mixed-heritage-adaptability'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_skill'] = trim((string) $form_state->getValue(
          ['feat_selections', 'mixed-heritage-adaptability', 'selected_skill'],
          $selection['selected_skill'] ?? ''
        ));
        $character_data['feat_selections']['mixed-heritage-adaptability'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'orc-atavism') {
        unset($character_data['feat_selections']['orc-atavism']);
      }
      else {
        $selection = $character_data['feat_selections']['orc-atavism'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_feat'] = trim((string) $form_state->getValue(
          ['feat_selections', 'orc-atavism', 'selected_feat'],
          $selection['selected_feat'] ?? ''
        ));
        $character_data['feat_selections']['orc-atavism'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'draconic-ties') {
        unset($character_data['feat_selections']['draconic-ties']);
      }
      else {
        $selection = $character_data['feat_selections']['draconic-ties'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['damage_type'] = trim((string) $form_state->getValue(
          ['feat_selections', 'draconic-ties', 'damage_type'],
          $selection['damage_type'] ?? ''
        ));
        $character_data['feat_selections']['draconic-ties'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'natural-skill') {
        unset($character_data['feat_selections']['natural-skill']);
      }
      else {
        $selection = $character_data['feat_selections']['natural-skill'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['skills'] = self::normalizeList($form_state->getValue(
          ['feat_selections', 'natural-skill', 'skills'],
          $selection['skills'] ?? []
        ));
        $character_data['feat_selections']['natural-skill'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'unconventional-weaponry') {
        unset($character_data['feat_selections']['unconventional-weaponry']);
      }
      else {
        $selection = $character_data['feat_selections']['unconventional-weaponry'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_weapon_id'] = trim((string) $form_state->getValue(
          ['feat_selections', 'unconventional-weaponry', 'selected_weapon_id'],
          $selection['selected_weapon_id'] ?? ''
        ));
        $character_data['feat_selections']['unconventional-weaponry'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'vengeful-hatred') {
        unset($character_data['feat_selections']['vengeful-hatred']);
      }
      else {
        $selection = $character_data['feat_selections']['vengeful-hatred'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['target_type'] = trim((string) $form_state->getValue(
          ['feat_selections', 'vengeful-hatred', 'target_type'],
          $selection['target_type'] ?? ''
        ));
        $character_data['feat_selections']['vengeful-hatred'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'ancestral-longevity') {
        unset($character_data['feat_selections']['ancestral-longevity']);
      }
      else {
        $selection = $character_data['feat_selections']['ancestral-longevity'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_skills'] = self::normalizeList($form_state->getValue(
          ['feat_selections', 'ancestral-longevity', 'selected_skills'],
          $selection['selected_skills'] ?? []
        ));
        $character_data['feat_selections']['ancestral-longevity'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'gnome-obsession') {
        unset($character_data['feat_selections']['gnome-obsession']);
      }
      else {
        $selection = $character_data['feat_selections']['gnome-obsession'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_lore'] = $this->normalizeLoreSkillName((string) $form_state->getValue(
          ['feat_selections', 'gnome-obsession', 'selected_lore'],
          $selection['selected_lore'] ?? ''
        ));
        $character_data['feat_selections']['gnome-obsession'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'natural-performer') {
        unset($character_data['feat_selections']['natural-performer']);
      }
      else {
        $selection = $character_data['feat_selections']['natural-performer'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['specialty'] = trim((string) $form_state->getValue(
          ['feat_selections', 'natural-performer', 'specialty'],
          $selection['specialty'] ?? ''
        ));
        $character_data['feat_selections']['natural-performer'] = $selection;
      }
    }

    // Step 4: Build structured spellcasting data for caster classes.
    if ((int) $step === 4) {
      $selected_class = $character_data['class'] ?? '';

      // Store class proficiency levels from the CLASSES constant.
      if ($selected_class !== '' && isset(CharacterRulesCatalog::CLASSES[$selected_class]['proficiencies'])) {
        $character_data['class_proficiencies'] = CharacterRulesCatalog::CLASSES[$selected_class]['proficiencies'];
      }
      $tradition = $this->characterManager->resolveClassTradition($selected_class, $character_data);

      if ($tradition) {
        // Clean cantrips checkbox array into a flat list of selected IDs.
        $raw_cantrips = $form_state->getValue('cantrips', []);
        $cantrip_ids = is_array($raw_cantrips)
          ? array_values(array_filter($raw_cantrips, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL))
          : [];

        // Clean first-level spells checkbox array.
        $raw_spells = $form_state->getValue('spells_first', []);
        $spell_ids = is_array($raw_spells)
          ? array_values(array_filter($raw_spells, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL))
          : [];

        $spell_payload = CharacterManager::buildSpellSelectionPayload($selected_class, $tradition, $cantrip_ids, $spell_ids);
        $character_data['spells'] = $spell_payload['spells'];
      }
      else {
        $character_data['spells'] = [];
        $character_data['cantrips'] = [];
        $character_data['spells_first'] = [];
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'adapted-cantrip') {
        if (isset($character_data['feat_selections']['adapted-cantrip'])) {
          unset($character_data['feat_selections']['adapted-cantrip']);
        }
      }
      else {
        $selection = $character_data['feat_selections']['adapted-cantrip'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_tradition'] = trim((string) $form_state->getValue(
          ['feat_selections', 'adapted-cantrip', 'selected_tradition'],
          $selection['selected_tradition'] ?? ''
        ));
        $selection['selected_cantrip'] = trim((string) $form_state->getValue(
          ['feat_selections', 'adapted-cantrip', 'selected_cantrip'],
          $selection['selected_cantrip'] ?? ''
        ));
        $character_data['feat_selections']['adapted-cantrip'] = $selection;
      }

      if (($character_data['ancestry_feat'] ?? '') !== 'natural-ambition') {
        unset($character_data['feat_selections']['natural-ambition']);
      }
      else {
        $selection = $character_data['feat_selections']['natural-ambition'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['bonus_class_feat'] = trim((string) $form_state->getValue(
          ['feat_selections', 'natural-ambition', 'bonus_class_feat'],
          $selection['bonus_class_feat'] ?? ''
        ));
        $character_data['feat_selections']['natural-ambition'] = $selection;
      }

      $selected_class_feat = trim((string) ($character_data['class_feat'] ?? ''));
      $selected_bonus_feat = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
      if ($selected_class_feat !== 'monster-hunter' && $selected_bonus_feat !== 'monster-hunter') {
        unset($character_data['feat_selections']['monster-hunter']);
      }
      else {
        $selection = $character_data['feat_selections']['monster-hunter'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_monster_type'] = trim((string) $form_state->getValue(
          ['feat_selections', 'monster-hunter', 'selected_monster_type'],
          $selection['selected_monster_type'] ?? ''
        ));
        $character_data['feat_selections']['monster-hunter'] = $selection;
      }

      if ($selected_class_feat !== 'staff-nexus' && $selected_bonus_feat !== 'staff-nexus') {
        unset($character_data['feat_selections']['staff-nexus']);
      }
      else {
        $selection = $character_data['feat_selections']['staff-nexus'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_cantrip'] = trim((string) $form_state->getValue(
          ['feat_selections', 'staff-nexus', 'selected_cantrip'],
          $selection['selected_cantrip'] ?? ''
        ));
        $selection['selected_spell'] = trim((string) $form_state->getValue(
          ['feat_selections', 'staff-nexus', 'selected_spell'],
          $selection['selected_spell'] ?? ''
        ));
        $character_data['feat_selections']['staff-nexus'] = $selection;
      }

      if ($selected_class_feat !== 'eldritch-trickster-racket' && $selected_bonus_feat !== 'eldritch-trickster-racket') {
        unset($character_data['feat_selections']['eldritch-trickster-racket']);
      }
      else {
        $selection = $character_data['feat_selections']['eldritch-trickster-racket'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_dedication'] = trim((string) $form_state->getValue(
          ['feat_selections', 'eldritch-trickster-racket', 'selected_dedication'],
          $selection['selected_dedication'] ?? $selection['dedication'] ?? ''
        ));
        $character_data['feat_selections']['eldritch-trickster-racket'] = $selection;
      }

      if ($selected_class_feat !== 'mastermind-racket' && $selected_bonus_feat !== 'mastermind-racket') {
        unset($character_data['feat_selections']['mastermind-racket']);
      }
      else {
        $selection = $character_data['feat_selections']['mastermind-racket'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_skill'] = trim((string) $form_state->getValue(
          ['feat_selections', 'mastermind-racket', 'selected_skill'],
          $selection['selected_skill'] ?? $selection['knowledge_skill'] ?? ''
        ));
        $character_data['feat_selections']['mastermind-racket'] = $selection;
      }

      $animal_companion_source = $this->resolveAnimalCompanionSelectionSource($form_state, $character_data, $selected_class);
      foreach (['animal-companion', 'animal-companion-druid'] as $feat_id) {
        if ($animal_companion_source !== $feat_id) {
          unset($character_data['feat_selections'][$feat_id]);
        }
      }
      if ($animal_companion_source !== NULL) {
        $selection = $character_data['feat_selections'][$animal_companion_source] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_companion_species'] = strtolower(trim((string) $form_state->getValue(
          ['feat_selections', $animal_companion_source, 'selected_companion_species'],
          $selection['selected_companion_species'] ?? $selection['species_id'] ?? ''
        )));
        $selection['species_id'] = $selection['selected_companion_species'];
        $selection['name'] = trim((string) $form_state->getValue(
          ['feat_selections', $animal_companion_source, 'name'],
          $selection['name'] ?? $selection['display_name'] ?? ''
        ));
        $character_data['feat_selections'][$animal_companion_source] = $selection;
      }

      $familiar_source = $this->resolveFamiliarSelectionSource($form_state, $character_data, $selected_class);
      foreach ($this->getFamiliarSelectionSourceIds() as $source_id) {
        if ($familiar_source !== $source_id) {
          unset($character_data['feat_selections'][$source_id]);
        }
      }
      if ($familiar_source !== NULL) {
        $selection = $character_data['feat_selections'][$familiar_source] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_familiar_type'] = strtolower(trim((string) $form_state->getValue(
          ['feat_selections', $familiar_source, 'selected_familiar_type'],
          $selection['selected_familiar_type'] ?? $selection['familiar_type'] ?? 'standard'
        )));
        $selection['familiar_type'] = $selection['selected_familiar_type'] !== '' ? $selection['selected_familiar_type'] : 'standard';
        $selection['selected_familiar_abilities'] = self::normalizeList($form_state->getValue(
          ['feat_selections', $familiar_source, 'selected_familiar_abilities'],
          $selection['selected_familiar_abilities'] ?? []
        ));
        $selection['name'] = trim((string) $form_state->getValue(
          ['feat_selections', $familiar_source, 'name'],
          $selection['name'] ?? ''
        ));
        $character_data['feat_selections'][$familiar_source] = $selection;
        $character_data['familiar'] = $this->buildCreationFamiliarPayload($character_data, $selected_class, $selection);
      }
      else {
        unset($character_data['familiar']);
      }

      // Build feats summary array from all sources.
      $character_data['feats'] = $this->buildFeatsArray($character_data);
    }

    // Step 6: Clean trained_skills checkbox data and build feats summary.
    if ((int) $step === 6) {
      $raw_skills = $form_state->getValue('trained_skills', []);
      $character_data['trained_skills'] = is_array($raw_skills)
        ? array_values(array_filter($raw_skills, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL))
        : [];

      if (($character_data['general_feat'] ?? '') !== 'specialty-crafting') {
        unset($character_data['feat_selections']['specialty-crafting']);
      }
      else {
        $selection = $character_data['feat_selections']['specialty-crafting'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['specialty'] = trim((string) $form_state->getValue(
          ['feat_selections', 'specialty-crafting', 'specialty'],
          $selection['specialty'] ?? ''
        ));
        $character_data['feat_selections']['specialty-crafting'] = $selection;
      }

      if (($character_data['general_feat'] ?? '') !== 'virtuosic-performer') {
        unset($character_data['feat_selections']['virtuosic-performer']);
      }
      else {
        $selection = $character_data['feat_selections']['virtuosic-performer'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['specialty'] = trim((string) $form_state->getValue(
          ['feat_selections', 'virtuosic-performer', 'specialty'],
          $selection['specialty'] ?? ''
        ));
        $character_data['feat_selections']['virtuosic-performer'] = $selection;
      }

      if (($character_data['general_feat'] ?? '') !== 'canny-acumen') {
        unset($character_data['feat_selections']['canny-acumen']);
      }
      else {
        $selection = $character_data['feat_selections']['canny-acumen'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_proficiency'] = trim((string) $form_state->getValue(
          ['feat_selections', 'canny-acumen', 'selected_proficiency'],
          $selection['selected_proficiency'] ?? ''
        ));
        $character_data['feat_selections']['canny-acumen'] = $selection;
      }

      if (($character_data['general_feat'] ?? '') !== 'adopted-ancestry') {
        unset($character_data['feat_selections']['adopted-ancestry']);
      }
      else {
        $selection = $character_data['feat_selections']['adopted-ancestry'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_ancestry'] = trim((string) $form_state->getValue(
          ['feat_selections', 'adopted-ancestry', 'selected_ancestry'],
          $selection['selected_ancestry'] ?? ''
        ));
        $character_data['feat_selections']['adopted-ancestry'] = $selection;
      }

      if (($character_data['general_feat'] ?? '') !== 'weapon-proficiency') {
        unset($character_data['feat_selections']['weapon-proficiency']);
      }
      else {
        $selection = $character_data['feat_selections']['weapon-proficiency'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_weapon_id'] = trim((string) $form_state->getValue(
          ['feat_selections', 'weapon-proficiency', 'selected_weapon_id'],
          $selection['selected_weapon_id'] ?? ''
        ));
        $character_data['feat_selections']['weapon-proficiency'] = $selection;
      }

      $selected_class_feat = trim((string) ($character_data['class_feat'] ?? ''));
      $selected_bonus_feat = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
      if ($selected_class_feat !== 'domain-initiate' && $selected_bonus_feat !== 'domain-initiate') {
        unset($character_data['feat_selections']['domain-initiate']);
      }
      else {
        $selection = $character_data['feat_selections']['domain-initiate'] ?? [];
        if (!is_array($selection)) {
          $selection = [];
        }
        $selection['selected_domain'] = trim((string) $form_state->getValue(
          ['feat_selections', 'domain-initiate', 'selected_domain'],
          $selection['selected_domain'] ?? ''
        ));
        $character_data['feat_selections']['domain-initiate'] = $selection;
      }

      // Rebuild feats array with general feat included.
      $character_data['feats'] = $this->buildFeatsArray($character_data);
    }

    if ((int) $step === 7) {
      $catalog = $this->getEquipmentCatalog();
      $catalog_by_id = [];
      foreach ($catalog as $items) {
        foreach ($items as $item) {
          $catalog_by_id[$item['id']] = $item;
        }
      }

      // Collect selected IDs from the three checkbox groups (not the broken
      // hidden JSON field).
      $selected_ids = [];
      foreach (['weapons', 'armor', 'gear'] as $group) {
        $raw = $form_state->getValue($group, []);
        if (is_array($raw)) {
          foreach (array_filter($raw) as $id) {
            $selected_ids[] = $id;
          }
        }
      }

      $selected_items = [];
      $total_cost = 0.0;
      foreach ($selected_ids as $item_id) {
        if (isset($catalog_by_id[$item_id])) {
          $selected_items[] = $catalog_by_id[$item_id];
          $total_cost += (float) $catalog_by_id[$item_id]['cost'];
        }
      }

      $character_data['gm_equipment_ids'] = array_values($selected_ids);

      $remaining_gp = max(0, round(15 - $total_cost, 2));
      $starting_currency = CharacterManager::normalizeCurrencyDenominations(['gp' => $remaining_gp]);
      $character_data['gold'] = CharacterManager::currencyDenominationsToGoldValue($starting_currency);

      // Build proper inventory structure matching CharacterStateService format.
      $carried = [];
      foreach ($selected_items as $item) {
        $carried[] = [
          'id' => $item['id'],
          'name' => $item['name'],
          'type' => $item['type'],
          'bulk' => $item['bulk'] ?? 'L',
          'quantity' => 1,
          'traits' => $item['traits'] ?? [],
        ];
      }

      // Calculate total bulk.
      $total_bulk = 0;
      foreach ($carried as $ci) {
        $bulk_val = $ci['bulk'] ?? 'L';
        if ($bulk_val === 'L' || $bulk_val === 'light') {
          $total_bulk += 0.1;
        }
        elseif (is_numeric($bulk_val)) {
          $total_bulk += (float) $bulk_val * ($ci['quantity'] ?? 1);
        }
      }
      $total_bulk = round($total_bulk, 1);

      // Determine encumbrance (matches CharacterStateService::calculateBulk).
      $str_score = (int) ($character_data['strength'] ?? 10);
      $encumbered_at = 5 + $str_score;
      $overloaded_at = 10 + $str_score;
      if ($total_bulk >= $overloaded_at) {
        $encumbrance = 'overloaded';
      }
      elseif ($total_bulk >= $encumbered_at) {
        $encumbrance = 'encumbered';
      }
      else {
        $encumbrance = 'unencumbered';
      }

      $character_data['inventory'] = [
        'worn' => [
          'weapons' => [],
          'armor' => NULL,
          'shield' => NULL,
          'accessories' => [],
        ],
        'carried' => $carried,
        'currency' => $starting_currency,
        'totalBulk' => $total_bulk,
        'encumbrance' => $encumbrance,
      ];

    }

    return ['character_data' => $character_data, 'selected_items' => $selected_items ?? []];
  }

  private function persistSubmittedDraft(
    FormStateInterface $form_state, array $character_data, int $step,
    int|string|null $character_id, int|string|null $campaign_id,
    int $next_version, bool $setup_shell, array $selected_items,
  ): void {
    $character_data = $this->characterManager->canonicalizeCharacterData($this->wizardHardening->syncWizardDraftFromCharacterData($character_data));
    $next_step = min(8, (int) $step + 1);
    $pending_completion_grants = $this->collectPendingCreationSelectionGrants($character_data);
    $readiness = $this->validateDraft($character_data, (int) $campaign_id);
    $character_data = $this->wizardHardening->advanceProgress($character_data, (int) $step, $pending_completion_grants !== [] || !$readiness['ready']);

    // Save to database
    $character_id = $this->saveCharacter($character_id, $character_data, $next_version, $campaign_id, $form_state, (int) $step);
    $form_state->set('saved_character_id', (int) $character_id);

    $persisted_record = $character_id ? $this->characterManager->loadCharacter((int) $character_id) : NULL;
    $persisted_campaign_id = $persisted_record ? (int) ($persisted_record->campaign_id ?? 0) : 0;
    $this->getLogger('dungeoncrawler_content')->notice('Character setup save complete: current_step=@step next_step=@next_step campaign_id=@campaign_id character_id=@character_id persisted_status=@status persisted_campaign_id=@persisted_campaign_id setup_shell=@setup_shell', [
      '@step' => (int) $step,
      '@next_step' => (int) $next_step,
      '@campaign_id' => (string) ($campaign_id ?? ''),
      '@character_id' => (int) $character_id,
      '@status' => $persisted_record ? (int) ($persisted_record->status ?? -1) : -1,
      '@persisted_campaign_id' => $persisted_campaign_id,
      '@setup_shell' => $setup_shell ? '1' : '0',
    ]);

    // Create dc_campaign_item_instances rows when inside a campaign context.
    if ($step === 7) {
      $this->getLogger('dungeoncrawler_content')->notice('Character setup equipment sync: character_id=@character_id campaign_id=@campaign_id selected_ids=@selected_ids carried_ids=@carried_ids remaining_gp=@remaining_gp', [
        '@character_id' => (string) $character_id,
        '@campaign_id' => (string) ($campaign_id ?? ''),
        '@selected_ids' => implode(',', $character_data['gm_equipment_ids']),
        '@carried_ids' => implode(',', array_column($selected_items, 'id')),
        '@remaining_gp' => number_format((float) $character_data['gold'], 2, '.', ''),
      ]);
    }
    if ((int) $step === 7 && $campaign_id && !empty($selected_items)) {
      $this->createCampaignItemInstances((int) $campaign_id, (int) $character_id, $selected_items);
    }

    // Redirect to next step or character view
    if ($step >= 8) {
      if ($pending_completion_grants !== []) {
        $pending_selection_messages = array_values(array_map(static function (array $grant): string {
          $description = trim((string) ($grant['description'] ?? ''));
          if ($description !== '') {
            return $description;
          }
          $selection_id = trim((string) ($grant['id'] ?? ''));
          return $selection_id !== '' ? $selection_id : 'unresolved selection';
        }, $pending_completion_grants));
        $this->messenger()->addError($this->t('Character creation is not complete. Resolve pending selections: @pending', [
          '@pending' => implode('; ', $pending_selection_messages),
        ]));
        $redirect_query = ['character_id' => $character_id];
        if ($campaign_id) {
          $redirect_query['campaign_id'] = $campaign_id;
        }
        $redirect_query = $this->preserveShellQueryFlags($redirect_query);
        if ($setup_shell) {
          $redirect_query['step'] = 8;
          $this->redirectAfterSave($form_state, 'dungeoncrawler_content.character_setup', [], ['query' => $redirect_query]);
        }
        else {
          $this->redirectAfterSave($form_state, 'dungeoncrawler_content.character_step', ['step' => 8], ['query' => $redirect_query]);
        }
        return;
      }
      if ($campaign_id) {
        $this->ensureCampaignCharacterHasCanonicalSource((int) $character_id, (int) $campaign_id);
      }
      $portrait_result = $this->portraitGenerator->generatePortrait(
        $character_data,
        (int) $character_id,
        (int) $this->currentUser->id(),
        $persisted_campaign_id > 0 ? $persisted_campaign_id : NULL,
        [
          'generate' => $character_data['portrait_generate'] ?? NULL,
          'user_prompt' => $character_data['portrait_prompt'] ?? '',
        ]
      );
      $this->notifyPortraitResult($portrait_result);
      $final_options = [];
      if ($campaign_id) {
        $final_options['query'] = ['campaign_id' => $campaign_id];
      }
      $this->redirectAfterSave($form_state, 'dungeoncrawler_content.character_view', ['character_id' => $character_id], $final_options);
    } else {
      $redirect_step = $step;
      $next_query = ['character_id' => $character_id];
      if ($campaign_id) {
        $next_query['campaign_id'] = $campaign_id;
      }
      $next_query = $this->preserveShellQueryFlags($next_query);
      if (!empty($this->getRequest()->query->get('embedded'))) {
        $next_query['unlocked_step'] = $next_step;
      }
      elseif ($setup_shell) {
        $next_query['step'] = $next_step;
        $this->getLogger('dungeoncrawler_content')->notice('Character setup redirecting inside setup shell: character_id=@character_id campaign_id=@campaign_id redirect_step=@redirect_step', [
          '@character_id' => (int) $character_id,
          '@campaign_id' => (string) ($campaign_id ?? ''),
          '@redirect_step' => (int) $next_step,
        ]);
        $this->redirectAfterSave($form_state, 'dungeoncrawler_content.character_setup', [], ['query' => $next_query]);
        return;
      }
      else {
        $redirect_step = $next_step;
      }

      $this->redirectAfterSave($form_state, 'dungeoncrawler_content.character_step', [
        'step' => $redirect_step,
      ], ['query' => $next_query]);
    }
  }

  /**
   * Preserves setup-shell query flags when the step form is embedded.
   */
  private function preserveShellQueryFlags(array $query): array {
    $current_query = $this->getRequest()->query->all();
    if (!empty($current_query['embedded'])) {
      $query['embedded'] = 1;
    }

    return $query;
  }

  /**
   * Return unresolved character-creation selection grants.
   *
   * @return array<int, array<string, mixed>>
   *   Selection grant rows that still require explicit user choice.
   */
  private function collectPendingCreationSelectionGrants(array $character_data): array {
    return $this->validationRules()->collectPendingCreationSelectionGrants($character_data);
  }

  /**
   * Determine whether a selection grant is already satisfied by current data.
   */
  private function isSelectionGrantResolved(array $grant, array $character_data): bool {
    return $this->validationRules()->isSelectionGrantResolved($grant, $character_data);
  }

  /**
   * True when Natural Ambition bonus feat is either not active or selected.
   */
  private function isBonusClassFeatSelectionResolved(array $character_data): bool {
    return $this->validationRules()->isBonusClassFeatSelectionResolved($character_data);
  }

  /**
   * True when familiar creation is either not required or already selected.
   */
  private function isFamiliarCreationSelectionResolved(array $character_data): bool {
    return $this->validationRules()->isFamiliarCreationSelectionResolved($character_data);
  }

  /**
   * Determine whether the current build requires familiar-creation choices.
   */
  private function hasActiveFamiliarRequirement(array $character_data): bool {
    return $this->validationRules()->hasActiveFamiliarRequirement($character_data);
  }

  /**
   * Gets equipment catalog options for step 7.
   * 
   * Returns equipment data conforming to character_options_step7.json schema.
   * All items include: id, name, cost, bulk, and category-specific fields.
   */
  private function getEquipmentCatalog(): array {
    return $this->validationRules()->getEquipmentCatalog();
  }

  /**
   * Builds step-7 equipment catalog from template item tables.
   */
  private function buildEquipmentCatalogFromTemplates(): array {
    return $this->validationRules()->buildEquipmentCatalogFromTemplates();
  }

  /**
   * Normalizes stored registry tags into a plain string list.
   */
  private function normalizeTags(string $raw_tags): array {
    return $this->validationRules()->normalizeTags($raw_tags);
  }

  private function normalizeLoreSkillName(string $lore_name): string {
    return $this->validationRules()->normalizeLoreSkillName($lore_name);
  }

  /**
   * Maps template item metadata to step-7 equipment categories.
   */
  private function mapTemplateItemCategory(string $item_type, array $tags): string {
    return $this->validationRules()->mapTemplateItemCategory($item_type, $tags);
  }

  /**
   * Loads character data from database.
   *
   * @param int|null $character_id
   *   The character ID to load.
   *
   * @return array
   *   Character data array with defaults.
   */
  private function loadCharacterData(int|string|null $character_id): array {
    if ($character_id) {
      $character = $this->characterManager->loadCharacter($character_id);
      if ($character && $character->uid == $this->currentUser->id()) {
        $data = json_decode($character->character_data, TRUE);
        $has_canonical_payload = is_array($data['basicInfo'] ?? NULL)
          && is_array($data['resources'] ?? NULL)
          && is_array($data['defenses'] ?? NULL);
        $form_data = $has_canonical_payload
          ? $data
          : (is_array($data['wizard'] ?? NULL) ? $data['wizard'] : $data);
        $form_data = $this->characterManager->canonicalizeCharacterData(is_array($form_data) ? $form_data : []);
        if (!empty($form_data['abilities'])) {
          $form_data['strength'] = $form_data['abilities']['strength'] ?? $form_data['abilities']['str'] ?? 10;
          $form_data['dexterity'] = $form_data['abilities']['dexterity'] ?? $form_data['abilities']['dex'] ?? 10;
          $form_data['constitution'] = $form_data['abilities']['constitution'] ?? $form_data['abilities']['con'] ?? 10;
          $form_data['intelligence'] = $form_data['abilities']['intelligence'] ?? $form_data['abilities']['int'] ?? 10;
          $form_data['wisdom'] = $form_data['abilities']['wisdom'] ?? $form_data['abilities']['wis'] ?? 10;
          $form_data['charisma'] = $form_data['abilities']['charisma'] ?? $form_data['abilities']['cha'] ?? 10;
        }
        return $form_data;
      }
    }
    return [
      'step' => 1,
      'name' => '',
      'concept' => '',
      'level' => 1,
      'experience_points' => 0,
      'ancestry' => '',
      'heritage' => '',
      'background' => '',
      'class' => '',
      'strength' => 10,
      'dexterity' => 10,
      'constitution' => 10,
      'intelligence' => 10,
      'wisdom' => 10,
      'charisma' => 10,
      'alignment' => '',
      'deity' => '',
      'age' => '',
      'gender' => '',
      'appearance' => '',
      'personality' => '',
      'roleplay_style' => 'balanced',
      'backstory' => '',
      'portrait_generate' => 1,
      'portrait_prompt' => '',
      'gold' => 15,
      'hero_points' => 1,
    ];
  }

  /**
   * Returns portrait generation availability for step 8.
   *
   * @return array<string, mixed>
   *   Availability summary with description.
   */
  private function getPortraitGenerationAvailability(): array {
    return $this->validationRules()->getPortraitGenerationAvailability();
  }

  /**
   * Surfaces portrait-generation outcomes in the redirected form flow.
   */
  private function notifyPortraitResult(array $result): void {
    $reason = (string) ($result['reason'] ?? '');

    if ($reason === 'provider_unavailable') {
      $provider = ucfirst((string) ($result['provider'] ?? 'image generation'));
      $this->messenger()->addWarning($this->t('@provider portrait generation is currently unavailable because no live provider configuration is present.', [
        '@provider' => $provider,
      ]));
      return;
    }

    if ($reason === 'exception') {
      $this->messenger()->addWarning($this->t('Portrait generation failed before an image could be stored.'));
      return;
    }

    if (!empty($result['attempted']) && !empty($result['storage']) && empty($result['storage']['stored'])) {
      $storage_reason = (string) ($result['storage']['reason'] ?? 'storage_failed');
      $this->messenger()->addWarning($this->t('Portrait generation completed without a stored image (@reason).', [
        '@reason' => $storage_reason,
      ]));
    }
  }

  /**
   * Saves character data to database.
   *
   * @param int|string|null $character_id
   *   The character ID to update, or NULL to create new.
   * @param array $character_data
   *   Character data array to save.
   * @param int $next_version
   *   The next version number for optimistic locking.
   * @param int|string|null $campaign_id
   *   The campaign ID to associate this character with, or NULL for none.
   *
   * @return int|string
   *   The character ID.
   */
  private function saveCharacter(int|string|null $character_id, array $character_data, int $next_version = 0, int|string|null $campaign_id = NULL, ?FormStateInterface $form_state = NULL, int $step = 0): int|string {
    $now = $this->time->getRequestTime();

    // Character setup edits the record that already exists; new records always
    // start in the canonical library and are attached to campaigns separately.
    $save_context = $this->resolveSaveCharacterContext($character_id, $campaign_id);
    $existing_record = $save_context['existing_record'];
    $resolved_campaign_id = $save_context['resolved_campaign_id'];
    $instance_id = $save_context['instance_id'];

    $transaction = $this->database->startTransaction();

    try {
      if ($step === 6 && $form_state) {
        $this->resolveStructuredAffiliationCreations($form_state, $character_data, $resolved_campaign_id, (int) $character_id);
        $this->normalizeStructuredAffiliationCharacterData($character_data);
      }

      $character_data = $this->syncWizardDraftFromCharacterData($character_data);

      $schema_data = $this->characterManager->canonicalizeCharacterData($character_data);
      if (empty($schema_data['created_at'])) {
        $schema_data['created_at'] = date('c', $now);
      }
      $schema_data['updated_at'] = date('c', $now);
      $hot = $this->characterManager->extractHotColumnsFromData($schema_data);

      if ($character_id) {
        if (!$existing_record) {
          throw new \RuntimeException('revision_conflict', 409);
        }
        // The creation rules prepare the stored values; the actor authority
        // commits them: lock, revision check, contract validation, history.
        $this->characterManager->commitPreparedActorWrite(
          (string) $existing_record->uuid,
          $next_version - 1,
          'actor.creation.save',
          [
            'campaign_id' => $resolved_campaign_id,
            'name' => $schema_data['name'] ?: 'Unnamed Character',
            'level' => $schema_data['level'],
            'ancestry' => $schema_data['ancestry'] ?? '',
            'class' => $schema_data['class'] ?? '',
            'hp_current' => $hot['hp_current'],
            'hp_max' => $hot['hp_max'],
            'armor_class' => $hot['armor_class'],
            'experience_points' => (int) ($schema_data['experience_points'] ?? 0),
            'position_q' => (int) ($schema_data['position']['q'] ?? 0),
            'position_r' => (int) ($schema_data['position']['r'] ?? 0),
            'last_room_id' => (string) ($schema_data['position']['room_id'] ?? ''),
            'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
            ...$this->wizardHardening->getCompletionFields($schema_data, $resolved_campaign_id),
          ],
        );

        if ($resolved_campaign_id > 0 && $existing_record) {
          $this->syncCanonicalSourceRowFromCampaignRecord($existing_record, $schema_data, $hot, $now);
        }
      }
      else {
        // The actor authority allocates identity, owner and revision and
        // validates the new actor before it exists.
        $receipt = $this->characterManager->createPreparedActor('actor.draft.create', [
          'campaign_id' => $resolved_campaign_id,
          'name' => $schema_data['name'] ?: 'Unnamed Character',
          'level' => $schema_data['level'],
          'ancestry' => $schema_data['ancestry'] ?? '',
          'class' => $schema_data['class'] ?? '',
          'hp_current' => $hot['hp_current'],
          'hp_max' => $hot['hp_max'],
          'armor_class' => $hot['armor_class'],
          'experience_points' => (int) ($schema_data['experience_points'] ?? 0),
          'position_q' => (int) ($schema_data['position']['q'] ?? 0),
          'position_r' => (int) ($schema_data['position']['r'] ?? 0),
          'last_room_id' => (string) ($schema_data['position']['room_id'] ?? ''),
          'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
          'default_locations' => trim((string) ($schema_data['default_locations'] ?? '')) !== ''
            ? (string) $schema_data['default_locations']
            : NULL,
          'portrait' => trim((string) ($schema_data['portrait'] ?? '')) !== ''
            ? (string) $schema_data['portrait']
            : NULL,
          ...$this->wizardHardening->getCompletionFields($schema_data, $resolved_campaign_id),
        ]);
        $character_id = $receipt['actor_row_id'];
        $instance_id = $receipt['target']['actor_uuid'];
      }

      if ($resolved_campaign_id > 0 && $instance_id !== '') {
        $this->institutionMembership->syncCampaignCharacterMemberships(
          $resolved_campaign_id,
          $instance_id,
          $schema_data
        );
      }
      if ($resolved_campaign_id === 0 && (int) $character_id > 0) {
        $this->runtimeResolver->ensureCanonicalLibraryFollowerDataset((int) $character_id);
      }
    }
    catch (\Throwable $exception) {
      if (is_object($transaction) && method_exists($transaction, 'rollBack')) {
        $transaction->rollBack();
      }
      throw $exception;
    }

    return $character_id;
  }

  /**
   * Resolve save-time campaign and instance context for character persistence.
   *
   * @return array{existing_record:?object, resolved_campaign_id:int, instance_id:string}
   *   Canonical save context values.
   */
  private function resolveSaveCharacterContext(int|string|null $character_id, int|string|null $campaign_id): array {
    $existing_record = NULL;
    $resolved_campaign_id = (int) ($campaign_id ?? 0);
    $instance_id = '';

    if ($character_id) {
      $existing_record = $this->characterManager->loadCharacter((int) $character_id);
      $resolved_campaign_id = $this->resolveEffectiveCampaignId($existing_record, $campaign_id);
      $instance_id = $existing_record ? trim((string) ($existing_record->instance_id ?? '')) : '';
    }

    return [
      'existing_record' => $existing_record,
      'resolved_campaign_id' => $resolved_campaign_id,
      'instance_id' => $instance_id,
    ];
  }

  /**
   * Mirror campaign-scoped character setup saves back into the canonical source row.
   */
  private function syncCanonicalSourceRowFromCampaignRecord(object $campaign_record, array $schema_data, array $hot, int $now): void {
    $source_character_id = (int) ($campaign_record->character_id ?? 0);
    $campaign_record_id = (int) ($campaign_record->id ?? 0);
    if ($source_character_id <= 0 || $source_character_id === $campaign_record_id) {
      return;
    }

    $this->database->update('dc_campaign_characters')
      ->fields([
        'name' => $schema_data['name'] ?: 'Unnamed Character',
        'level' => $schema_data['level'],
        'ancestry' => $schema_data['ancestry'] ?? '',
        'class' => $schema_data['class'] ?? '',
        'hp_current' => $hot['hp_current'],
        'hp_max' => $hot['hp_max'],
        'armor_class' => $hot['armor_class'],
        'experience_points' => (int) ($schema_data['experience_points'] ?? 0),
        'character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
        'default_character_data' => json_encode($schema_data, JSON_PRETTY_PRINT),
        'default_locations' => trim((string) ($campaign_record->default_locations ?? '')) !== ''
          ? (string) $campaign_record->default_locations
          : NULL,
        'portrait' => trim((string) ($campaign_record->portrait ?? '')) !== ''
          ? (string) $campaign_record->portrait
          : NULL,
        'status' => max(1, (int) ($campaign_record->status ?? 1)),
        'changed' => $now,
        'updated' => $now,
      ])
      ->condition('id', $source_character_id)
      ->condition('campaign_id', 0)
      ->execute();
  }

  /**
   * Keep the nested wizard draft aligned with the latest top-level character data.
   */
  private function syncWizardDraftFromCharacterData(array $character_data): array {
    return $this->wizardHardening->syncWizardDraftFromCharacterData($character_data);
  }

  /**
   * Persist the latest calculated ability scores onto the draft payload.
   *
   * @param array<string,mixed> $character_data
   *   Current character payload.
   * @param array<string,mixed> $calculation
   *   AbilityScoreTracker calculation result.
   *
   * @return array<string,mixed>
   *   Character payload with canonical ability mirrors refreshed.
   */
  private function storeCalculatedAbilityScores(array $character_data, array $calculation): array {
    $short_map = [
      'strength' => 'str',
      'dexterity' => 'dex',
      'constitution' => 'con',
      'intelligence' => 'int',
      'wisdom' => 'wis',
      'charisma' => 'cha',
    ];

    $abilities = [];
    $ability_scores = [];
    foreach (($calculation['scores'] ?? []) as $ability => $score) {
      $score = (int) $score;
      $modifier = (int) (($calculation['modifiers'][$ability] ?? floor(($score - 10) / 2)));
      $short_key = $short_map[$ability] ?? NULL;

      $character_data[$ability] = $score;
      $abilities[$ability] = $score;
      if ($short_key !== NULL) {
        $abilities[$short_key] = $score;
      }
      $ability_scores[$ability] = [
        'score' => $score,
        'modifier' => $modifier,
      ];
    }

    if ($abilities !== []) {
      $character_data['abilities'] = $abilities;
      $character_data['ability_scores'] = $ability_scores;
    }

    $level = max(1, (int) ($character_data['level'] ?? 1));
    $canonical_ancestry = CharacterRulesUtility::resolveAncestryCanonicalName((string) ($character_data['ancestry'] ?? ''));
    $ancestry_data = $canonical_ancestry !== '' ? (CharacterRulesCatalog::ANCESTRIES[$canonical_ancestry] ?? []) : [];
    $class_hp = $this->characterManager->getClassHP((string) ($character_data['class'] ?? ''));
    $con_mod = (int) ($ability_scores['constitution']['modifier'] ?? 0);
    $dex_mod = (int) ($ability_scores['dexterity']['modifier'] ?? 0);
    $max_hp = (int) (($ancestry_data['hp'] ?? 0) + $class_hp + $con_mod + (($level - 1) * ($class_hp + $con_mod)));
    $character_data['hit_points'] = [
      'max' => $max_hp,
      'current' => $max_hp,
      'temp' => (int) ($character_data['hit_points']['temp'] ?? $character_data['hit_points']['temporary'] ?? 0),
    ];
    $character_data['armor_class'] = 10 + $dex_mod;
    $resources = is_array($character_data['resources'] ?? NULL) ? $character_data['resources'] : [];
    $resources['hitPoints'] = [
      'max' => $max_hp,
      'current' => $max_hp,
      'temporary' => (int) ($character_data['hit_points']['temp'] ?? $character_data['hit_points']['temporary'] ?? 0),
    ];
    $character_data['resources'] = $resources;
    $defenses = is_array($character_data['defenses'] ?? NULL) ? $character_data['defenses'] : [];
    $defenses['armorClass'] = 10 + $dex_mod;
    $character_data['defenses'] = $defenses;

    $character_data['ability_sources'] = is_array($calculation['sources'] ?? NULL)
      ? $calculation['sources']
      : [];

    return $character_data;
  }

  /**
   * Ensure a campaign-created character points back to a canonical library row.
   */
  private function ensureCampaignCharacterHasCanonicalSource(int $character_id, int $campaign_id): void {
    $this->wizardHardening->ensureCampaignCharacterHasCanonicalSource($character_id, $campaign_id);
  }

  /**
   * Resolves the authoritative campaign context for the form.
   */
  private function resolveEffectiveCampaignId(?object $character_record, int|string|null $campaign_id): int {
    if ($character_record && !empty($character_record->campaign_id)) {
      return (int) $character_record->campaign_id;
    }
    return (int) ($campaign_id ?? 0);
  }

  /**
   * Creates dc_campaign_item_instances rows for a character's starting gear.
   *
   * Each selected equipment item gets an instance row so the campaign runtime
   * can track location, quantity, and state independently of the template.
   *
   * @param int $campaign_id
   *   The campaign ID.
   * @param int $character_id
   *   The character row ID (dc_campaign_characters.id).
   * @param array $selected_items
   *   Array of catalog item arrays, each with 'id', 'name', 'type', 'cost',
   *   'bulk', 'traits' keys.
   */
  private function createCampaignItemInstances(int $campaign_id, int $character_id, array $selected_items): void {
    $now = $this->time->getRequestTime();

    // Remove any existing instances for this character in this campaign to
    // support re-submission (e.g. user goes back to step 7 and re-saves).
    $this->database->delete('dc_campaign_item_instances')
      ->condition('campaign_id', $campaign_id)
      ->condition('location_type', 'character_inventory')
      ->condition('location_ref', (string) $character_id)
      ->execute();

    foreach ($selected_items as $item) {
      $item_id = $item['id'];
      // Unique instance ID: "{character_id}_{item_id}" — deterministic and
      // human-readable. If the same item appears twice (shouldn't with
      // checkboxes) the DB unique constraint will prevent duplicates.
      $instance_id = $character_id . '_' . $item_id;

      $state_data = [
        'condition' => 'new',
        'source' => 'character_creation',
        'original_cost' => $item['cost'] ?? 0,
      ];

      $this->database->insert('dc_campaign_item_instances')
        ->fields([
          'campaign_id' => $campaign_id,
          'item_instance_id' => $instance_id,
          'item_id' => $item_id,
          'location_type' => 'character_inventory',
          'location_ref' => (string) $character_id,
          'quantity' => 1,
          'state_data' => json_encode($state_data),
          'created' => $now,
          'updated' => $now,
        ])
        ->execute();
    }

    $this->getLogger('dungeoncrawler_content')->notice('Created @count campaign item instances for character @cid in campaign @camp.', [
      '@count' => count($selected_items),
      '@cid' => $character_id,
      '@camp' => $campaign_id,
    ]);
  }

  /**
   * Gets ancestry dropdown options.
   *
   * @return array
   *   Associative array of ancestry options.
   */
  private function getAncestryOptions(): array {
    return $this->validationRules()->getAncestryOptions();
  }

  /**
   * Gets heritage options filtered by ancestry.
   *
   * @param string $ancestry
   *   The ancestry key to filter heritages by.
   *
   * @return array
   *   Associative array of heritage options.
   */
  private function getHeritageOptions(string $ancestry): array {
    return $this->validationRules()->getHeritageOptions($ancestry);
  }

  /**
   * Builds ability data array for the interactive boost widget.
   *
   * Used by Steps 3 and 5 where users select ability boosts.
   */
  private function buildInteractiveAbilityData(array $calculation, array $selected_boosts): array {
    return $this->validationRules()->buildInteractiveAbilityData($calculation, $selected_boosts);
  }

  /**
   * Normalizes a form value that may be JSON-encoded into a flat array.
   *
   * Used by validateForm() and submitForm() for boost fields.
   */
  private static function normalizeList(mixed $value): array {
    return self::validationRules()->normalizeList($value);
  }

  /**
   * Converts an ancestry display name to its machine ID.
   */
  private static function ancestryMachineId(string $name): string {
    return self::validationRules()->ancestryMachineId($name);
  }

  /**
   * Resolves ancestry machine id (e.g. "half-elf") to canonical ancestry name.
   */
  private function resolveAncestryName(string $ancestry_id): string {
    return $this->validationRules()->resolveAncestryName($ancestry_id);
  }

  /**
   * Return Adopted Ancestry selection options excluding the current ancestry.
   */
  private function getAdoptedAncestryOptions(array $character_data): array {
    return $this->validationRules()->getAdoptedAncestryOptions($character_data);
  }

  /**
   * Return selected cantrip and spell options for Staff Nexus.
   *
   * @return array{cantrips: array<string, string>, spells: array<string, string>}
   *   Staff-eligible spell options keyed by spell id.
   */
  private function getStaffNexusSpellOptions(FormStateInterface $form_state, array $character_data, string $tradition): array {
    return $this->validationRules()->getStaffNexusSpellOptions($form_state, $character_data, $tradition);
  }

  /**
   * Gets background dropdown options.
   *
   * @return array
   *   Associative array of background options.
   */
  private function getBackgroundOptions(): array {
    return $this->validationRules()->getBackgroundOptions();
  }

  /**
   * Gets class dropdown options.
   *
   * @return array
   *   Associative array of class options.
   */
  private function getClassOptions(): array {
    return $this->validationRules()->getClassOptions();
  }

  /**
   * Gets alignment dropdown options.
   *
   * @return array
   *   Associative array of alignment options.
   */
  private function getAlignmentOptions(): array {
    return $this->validationRules()->getAlignmentOptions();
  }

  /**
   * Builds structured campaign affiliation selectors on the live step-6 surface.
   */
  private function buildStructuredAffiliationFields(array &$form, array $character_data, int $campaign_id): void {
    $this->validationRules()->buildStructuredAffiliationFields($form, $character_data, $campaign_id);
  }

  /**
   * Validates structured campaign affiliation selections against the registry.
   */


  /**
   * Adds explicit create-institution controls for an affiliation field.
   */
  private function buildStructuredAffiliationCreationElements(array &$form, string $field, array $definition, array $parent_options): void {
    $this->validationRules()->buildStructuredAffiliationCreationElements($form, $field, $definition, $parent_options);
  }

  /**
   * Resolves and creates structured affiliation institutions before persistence.
   */
  private function resolveStructuredAffiliationCreations(FormStateInterface $form_state, array &$character_data, int $campaign_id, int $character_id): void {
    if ($campaign_id <= 0) {
      return;
    }

    foreach (self::STRUCTURED_AFFILIATION_FIELDS as $field => $definition) {
      $selected_subject_ids = !empty($definition['multiple'])
        ? self::normalizeList($form_state->getValue($field, []))
        : self::normalizeList($form_state->getValue($field, ''));
      $selected_subject_ids = array_values(array_filter(
        $selected_subject_ids,
        static fn(string $subject_id): bool => $subject_id !== self::STRUCTURED_AFFILIATION_CREATE_SENTINEL
      ));
      $create_labels = $this->parseStructuredAffiliationCreateLabels($form_state->getValue($this->getStructuredAffiliationCreateLabelsKey($field), ''));
      $parent_subject_id = trim((string) $form_state->getValue($this->getStructuredAffiliationCreateParentKey($field), ''));
      $provenance_note = trim((string) $form_state->getValue($this->getStructuredAffiliationCreateNoteKey($field), ''));

      foreach ($create_labels as $label) {
        if ($this->isFactionGenerationField($field)) {
          $resolved = $this->factionGeneration->createOrReuseFactionForNeed($campaign_id, $this->buildFactionGenerationRequestFromForm($form_state, $field, $label, $character_id));
          $resolved_subject_id = trim((string) ($resolved['campaignSubjectId'] ?? ''));
        }
        else {
          $resolved = $this->campaignSubjectRegistry->resolveOrCreateInstitutionSubject($campaign_id, [
            'domain' => $definition['domain'],
            'display_name' => $label,
            'parent_subject_id' => $parent_subject_id,
            'source_asset_type' => 'campaign_character_wizard',
            'source_asset_id' => $character_id > 0 ? (string) $character_id : NULL,
            'metadata' => [
              'created_via' => 'character_creation_step6',
              'affiliation_field' => $field,
              'provenance_note' => $provenance_note,
            ],
          ]);
          $resolved_subject_id = trim((string) ($resolved['subject_id'] ?? ''));
        }
        if ($resolved_subject_id !== '') {
          $selected_subject_ids[] = $resolved_subject_id;
        }
      }

      $selected_subject_ids = array_values(array_unique($selected_subject_ids));
      if (!empty($definition['multiple'])) {
        $character_data[$field] = $selected_subject_ids;
      }
      else {
        $character_data[$field] = $selected_subject_ids[0] ?? '';
      }
    }
  }

  /**
   * Returns domain-keyed institution registry rows.
   *
   * @return array<string, array<int, array<string, string>>>
   *   Institution rows keyed by canonical domain.
   */
  private function getCampaignInstitutionRowsByDomain(int $campaign_id): array {
    return $this->validationRules()->getCampaignInstitutionRowsByDomain($campaign_id);
  }

  /**
   * Loads campaign institution picker options grouped by canonical domain.
   *
   * @return array<string, array<string, string>>
   *   Domain-keyed [subject_id => display_name] option maps.
   */
  private function getCampaignInstitutionOptionsByDomain(int $campaign_id): array {
    return $this->validationRules()->getCampaignInstitutionOptionsByDomain($campaign_id);
  }

  /**
   * Loads parent-institution picker options across all campaign institutions.
   *
   * @return array<string, string>
   *   Subject-id keyed labels.
   */
  private function getCampaignInstitutionParentOptions(int $campaign_id): array {
    return $this->validationRules()->getCampaignInstitutionParentOptions($campaign_id);
  }

  /**
   * Parses canonical labels entered for new institution creation.
   *
   * @return array<int, string>
   *   Unique canonical labels.
   */
  private function parseStructuredAffiliationCreateLabels(mixed $value): array {
    return $this->validationRules()->parseStructuredAffiliationCreateLabels($value);
  }

  /**
   * Returns an exact normalized-label match when one already exists.
   *
   * @param array<int, array<string, string>> $rows
   *   Existing registry rows for the field domain.
   */
  private function findExactInstitutionMatch(string $normalized_label, array $rows): ?array {
    return $this->validationRules()->findExactInstitutionMatch($normalized_label, $rows);
  }

  /**
   * Finds likely duplicate institution labels within the same domain.
   *
   * @param array<int, array<string, string>> $rows
   *   Existing registry rows for the field domain.
   *
   * @return array<int, string>
   *   Display names of likely near matches.
   */
  private function findLikelyInstitutionNearMatches(string $normalized_label, array $rows): array {
    return $this->validationRules()->findLikelyInstitutionNearMatches($normalized_label, $rows);
  }

  /**
   * Returns all non-persistent helper field keys for structured affiliations.
   *
   * @return array<int, string>
   *   Auxiliary form field keys.
   */
  private function getStructuredAffiliationAuxiliaryKeys(): array {
    $keys = [];
    foreach (array_keys(self::STRUCTURED_AFFILIATION_FIELDS) as $field) {
      $keys[] = $field . '__create_details';
      $keys[] = $this->getStructuredAffiliationCreateLabelsKey($field);
      $keys[] = $this->getStructuredAffiliationCreateParentKey($field);
      $keys[] = $this->getStructuredAffiliationCreateNoteKey($field);
      $keys[] = $this->getStructuredAffiliationCreateConfirmKey($field);
      if ($this->isFactionGenerationField($field)) {
        $keys[] = $this->getStructuredAffiliationCreateRoleKey($field);
        $keys[] = $this->getStructuredAffiliationCreateWhyKey($field);
        $keys[] = $this->getStructuredAffiliationCreatePublicFaceKey($field);
        $keys[] = $this->getStructuredAffiliationCreateHiddenFaceKey($field);
        $keys[] = $this->getStructuredAffiliationCreateIdeologyTagsKey($field);
        $keys[] = $this->getStructuredAffiliationCreateMethodTagsKey($field);
        $keys[] = $this->getStructuredAffiliationCreateMembershipStyleKey($field);
      }
    }
    return $keys;
  }

  private function getStructuredAffiliationCreateLabelsKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateLabelsKey($field);
  }

  private function getStructuredAffiliationCreateParentKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateParentKey($field);
  }

  private function getStructuredAffiliationCreateNoteKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateNoteKey($field);
  }

  private function getStructuredAffiliationCreateConfirmKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateConfirmKey($field);
  }

  private function getStructuredAffiliationCreateRoleKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateRoleKey($field);
  }

  private function getStructuredAffiliationCreateWhyKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateWhyKey($field);
  }

  private function getStructuredAffiliationCreatePublicFaceKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreatePublicFaceKey($field);
  }

  private function getStructuredAffiliationCreateHiddenFaceKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateHiddenFaceKey($field);
  }

  private function getStructuredAffiliationCreateIdeologyTagsKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateIdeologyTagsKey($field);
  }

  private function getStructuredAffiliationCreateMethodTagsKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateMethodTagsKey($field);
  }

  private function getStructuredAffiliationCreateMembershipStyleKey(string $field): string {
    return $this->validationRules()->getStructuredAffiliationCreateMembershipStyleKey($field);
  }

  private function isFactionGenerationField(string $field): bool {
    return $this->validationRules()->isFactionGenerationField($field);
  }

  /**
   * Builds a faction-generation request from structured affiliation helper input.
   *
   * @return array<string, mixed>
   *   Request payload for canonical faction generation.
   */
  private function buildFactionGenerationRequestFromForm(FormStateInterface $form_state, string $field, string $label, int $character_id = 0): array {
    return $this->validationRules()->buildFactionGenerationRequestFromForm($form_state, $field, $label, $character_id);
  }

  /**
   * Normalizes structured affiliation character data into strict wizard storage.
   */
  private function normalizeStructuredAffiliationCharacterData(array &$character_data): void {
    foreach (self::STRUCTURED_AFFILIATION_FIELDS as $field => $definition) {
      if (!empty($definition['multiple'])) {
        $character_data[$field] = array_values(array_unique(self::normalizeList($character_data[$field] ?? [])));
      }
      else {
        $selected_values = self::normalizeList($character_data[$field] ?? '');
        $character_data[$field] = $selected_values[0] ?? '';
      }
    }
  }

  /**
   * AJAX callback to refresh ancestry-dependent Step 2 options.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The ancestry-dependent container.
   */
  public function updateHeritageOptions(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $current_ancestry = (string) ($trigger['#value'] ?? $form_state->getValue('ancestry') ?? '');
    $previous_ancestry = (string) ($form_state->get('previous_ancestry_selection') ?? '');

    if ($current_ancestry !== $previous_ancestry) {
      // Reset ancestry-dependent posted values when ancestry changes.
      $form_state->setValue('heritage', '');
      $form_state->setValue('ancestry_feat', '');
      $form_state->setValue('ancestry_boosts', json_encode([]));

      $user_input = $form_state->getUserInput();
      if (is_array($user_input)) {
        $user_input['heritage'] = '';
        $user_input['ancestry_feat'] = '';
        $user_input['ancestry_boosts'] = json_encode([]);
        $user_input['ancestry'] = $current_ancestry;
        $form_state->setUserInput($user_input);
      }
    }

    $form_state->set('previous_ancestry_selection', $current_ancestry);

    // Clear any validation errors and messenger messages that may have
    // accumulated during form processing. With #limit_validation_errors absent
    // on the ancestry select, Drupal validates nothing by default for non-button
    // AJAX, so these should be empty — but we clear defensively to ensure no
    // stale messages from a prior request appear in the AJAX response.
    if (method_exists($form_state, 'clearErrors')) {
      $form_state->clearErrors();
    }
    \Drupal::messenger()->deleteByType('error');

    $form_state->setRebuild(TRUE);

    return $form['heritage_dynamic'];
  }

  /**
   * AJAX callback to refresh ancestry-feat-dependent Step 2 fields.
   */
  public function updateAncestryFeatOptions(array &$form, FormStateInterface $form_state): array {
    if (method_exists($form_state, 'clearErrors')) {
      $form_state->clearErrors();
    }
    \Drupal::messenger()->deleteByType('error');
    $form_state->setRebuild(TRUE);

    return $form['heritage_dynamic'];
  }

  /**
   * AJAX callback: Rebuilds background-dependent fields when background changes.
   */
  public function updateBackgroundOptions(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $current_background = (string) ($trigger['#value'] ?? $form_state->getValue('background') ?? '');
    $previous_background = (string) ($form_state->get('previous_background_selection') ?? '');

    if ($current_background !== $previous_background) {
      $form_state->setValue('background_boosts', json_encode([]));
      $form_state->setValue('scholar_skill_choice', '');

      $user_input = $form_state->getUserInput();
      if (is_array($user_input)) {
        $user_input['background'] = $current_background;
        $user_input['background_boosts'] = json_encode([]);
        $user_input['scholar_skill_choice'] = '';
        $form_state->setUserInput($user_input);
      }
    }

    $form_state->set('previous_background_selection', $current_background);

    if (method_exists($form_state, 'clearErrors')) {
      $form_state->clearErrors();
    }
    \Drupal::messenger()->deleteByType('error');

    $form_state->setRebuild(TRUE);

    return $form['background_dynamic'];
  }

  /**
   * AJAX callback: Rebuilds class-dependent fields when class or subclass changes.
   */
  public function updateClassOptions(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $trigger_name = $trigger['#name'] ?? '';

    // When the class itself changes, clear class-dependent selections.
    if ($trigger_name === 'class') {
      $form_state->setValue('class_feat', '');
      $form_state->setValue('class_key_ability', '');
      $form_state->setValue('subclass', '');
      $form_state->setValue('arcane_thesis', '');
      $form_state->setValue('cantrips', []);
      $form_state->setValue('spells_first', []);

      $user_input = $form_state->getUserInput();
      if (is_array($user_input)) {
        unset(
          $user_input['class_feat'],
          $user_input['class_key_ability'],
          $user_input['subclass'],
          $user_input['arcane_thesis'],
          $user_input['cantrips'],
          $user_input['spells_first']
        );
        $form_state->setUserInput($user_input);
      }
    }

    if ($trigger_name === 'class_feat') {
      $form_state->setValue('class_key_ability', '');
      $user_input = $form_state->getUserInput();
      if (is_array($user_input)) {
        unset(
          $user_input['class_key_ability'],
          $user_input['feat_selections']['eldritch-trickster-racket'],
          $user_input['feat_selections']['mastermind-racket']
        );
        $form_state->setUserInput($user_input);
      }
    }

    // When subclass changes, clear spell selections.
    if ($trigger_name === 'subclass') {
      $form_state->setValue('cantrips', []);
      $form_state->setValue('spells_first', []);

      $user_input = $form_state->getUserInput();
      if (is_array($user_input)) {
        unset($user_input['cantrips'], $user_input['spells_first']);
        $form_state->setUserInput($user_input);
      }
    }

    if (method_exists($form_state, 'clearErrors')) {
      $form_state->clearErrors();
    }
    \Drupal::messenger()->deleteByType('error');

    $form_state->setRebuild(TRUE);

    return $form['class_dynamic'];
  }

  /**
   * AJAX callback: Rebuilds general-feat-dependent fields when general feat changes.
   */
  public function updateGeneralFeatOptions(array &$form, FormStateInterface $form_state): array {
    if (method_exists($form_state, 'clearErrors')) {
      $form_state->clearErrors();
    }
    \Drupal::messenger()->deleteByType('error');
    $form_state->setRebuild(TRUE);

    return $form['general_feat_dynamic'];
  }

  /**
   * Clears stale user input when a submitted option is no longer allowed.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state containing posted input.
   * @param string $input_key
   *   Input key to sanitize.
   * @param array $options
   *   Currently allowed options for this element.
   */
  private function clearStaleOptionInput(FormStateInterface $form_state, string $input_key, array $options): void {
    $this->validationRules()->clearStaleOptionInput($form_state, $input_key, $options);
  }

  /**
   * Sanitizes submitted option values against currently allowed element options.
   *
  * Prevents stale ancestry-dependent values from triggering "value not allowed"
  * errors during ancestry switches.
   *
   * @param array $element
   *   Form element definition.
   * @param mixed $input
   *   Submitted value.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state.
   *
   * @return mixed
   *   A safe value compatible with current options.
   */
  public function sanitizeOptionValue(array &$element, mixed $input, FormStateInterface $form_state): mixed {
    if ($input === FALSE) {
      return $element['#default_value'] ?? '';
    }

    $options = $element['#options'] ?? [];

    if ($input === NULL || $input === '') {
      return '';
    }

    if (is_array($input)) {
      return [];
    }

    $candidate = (string) $input;
    return array_key_exists($candidate, $options) ? $candidate : '';
  }

  /**
   * Builds a consistent detail card for a selected option.
   */
  private function buildSelectionDetailMarkup(string $title, string $description = '', array $tags = [], array $facts = []): string {
    return $this->validationRules()->buildSelectionDetailMarkup($title, $description, $tags, $facts);
  }

  /**
   * Builds a stack of detail cards from structured item definitions.
   */
  private function buildOptionDetailStackMarkup(array $items): string {
    $cards_markup = '';

    foreach ($items as $item) {
      $cards_markup .= $this->buildSelectionDetailMarkup(
        (string) ($item['title'] ?? ''),
        (string) ($item['description'] ?? ''),
        is_array($item['tags'] ?? NULL) ? $item['tags'] : [],
        is_array($item['facts'] ?? NULL) ? $item['facts'] : [],
      );
    }

    if ($cards_markup === '') {
      return '';
    }

    return '<div class="option-detail-stack">' . $cards_markup . '</div>';
  }

  /**
   * Builds metadata for inline selector cards.
   */
  private function buildOptionCardData(string $description = '', array $tags = [], array $facts = []): array {
    return $this->validationRules()->buildOptionCardData($description, $tags, $facts);
  }

  /**
   * Attaches selector-card metadata to a form wrapper.
   */
  private function attachOptionCardSettings(array &$element, string $group_name, array $options, string $selection_type, array $disabled_values = []): void {
    $this->validationRules()->attachOptionCardSettings($element, $group_name, $options, $selection_type, $disabled_values);
  }

  /**
   * Extracts prominent spell facts for selector cards.
   */
  private function extractSpellFacts(array $spell): array {
    return $this->validationRules()->extractSpellFacts($spell);
  }

  /**
   * Builds selector-card metadata for equipment.
   */
  private function buildEquipmentOptionCardData(array $item, string $category): array {
    return $this->validationRules()->buildEquipmentOptionCardData($item, $category);
  }

  /**
   * Builds a usable description for equipment selector cards.
   */
  private function buildEquipmentDescription(array $item, string $category): string {
    return $this->validationRules()->buildEquipmentDescription($item, $category);
  }

  /**
   * Extracts item description text from template registry data.
   */
  private function extractTemplateItemDescription(array $schema_data): string {
    return $this->validationRules()->extractTemplateItemDescription($schema_data);
  }

  /**
   * Adds Step 2 selection UI for First World Magic.
   */
  private function buildFirstWorldMagicSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildFirstWorldMagicSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Otherworldly Magic.
   */
  private function buildOtherworldlyMagicSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildOtherworldlyMagicSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Gnome Obsession.
   */
  private function buildGnomeObsessionSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildGnomeObsessionSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Natural Performer.
   */
  private function buildNaturalPerformerSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildNaturalPerformerSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for General Training.
   */
  private function buildGeneralTrainingSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildGeneralTrainingSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Elf Atavism.
   */
  private function buildElfAtavismSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildElfAtavismSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Multitalented.
   */
  private function buildMultitalentedSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildMultitalentedSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Mixed Heritage Adaptability.
   */
  private function buildMixedHeritageAdaptabilitySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildMixedHeritageAdaptabilitySelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Orc Atavism.
   */
  private function buildOrcAtavismSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildOrcAtavismSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Draconic Ties.
   */
  private function buildDraconicTiesSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildDraconicTiesSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Natural Skill.
   */
  private function buildNaturalSkillSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildNaturalSkillSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Vengeful Hatred.
   */
  private function buildVengefulHatredSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildVengefulHatredSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Ancestral Longevity.
   */
  private function buildAncestralLongevitySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildAncestralLongevitySelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 2 selection UI for Unconventional Weaponry.
   */
  private function buildUnconventionalWeaponrySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildUnconventionalWeaponrySelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 4 selection UI for Natural Ambition.
   */
  private function buildNaturalAmbitionSelectionSection(array &$form, FormStateInterface $form_state, array $character_data, string $selected_class, array $class_feats): void {
    $this->validationRules()->buildNaturalAmbitionSelectionSection($form, $form_state, $character_data, $selected_class, $class_feats);
  }

  /**
   * Adds Step 4 selection UI for Eldritch Trickster's free dedication.
   */
  private function buildEldritchTricksterSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildEldritchTricksterSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 4 selection UI for Mastermind's extra knowledge skill.
   */
  private function buildMastermindSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildMastermindSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 6 selection UI for Specialty Crafting.
   */
  private function buildSpecialtyCraftingSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildSpecialtyCraftingSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 6 selection UI for Virtuosic Performer.
   */
  private function buildVirtuosicPerformerSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildVirtuosicPerformerSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 6 selection UI for Canny Acumen.
   */
  private function buildCannyAcumenSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildCannyAcumenSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 6 selection UI for Adopted Ancestry.
   */
  private function buildAdoptedAncestrySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildAdoptedAncestrySelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 6 selection UI for Domain Initiate.
   */
  private function buildDomainInitiateSelectionSection(array &$container, FormStateInterface $form_state, array $character_data, string $selected_deity): void {
    $this->validationRules()->buildDomainInitiateSelectionSection($container, $form_state, $character_data, $selected_deity);
  }

  /**
   * Adds Step 6 selection UI for Weapon Proficiency.
   */
  private function buildWeaponProficiencySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildWeaponProficiencySelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 4 selection UI for Monster Hunter.
   */
  private function buildMonsterHunterSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $this->validationRules()->buildMonsterHunterSelectionSection($container, $form_state, $character_data);
  }

  /**
   * Adds Step 4 selection UI for Animal Companion species and name.
   */
  private function buildAnimalCompanionSelectionSection(array &$container, FormStateInterface $form_state, array $character_data, string $source_feat_id): void {
    $this->validationRules()->buildAnimalCompanionSelectionSection($container, $form_state, $character_data, $source_feat_id);
  }

  /**
   * Adds Step 4 selection UI for familiar-granting feats and class features.
   */
  private function buildFamiliarSelectionSection(array &$container, FormStateInterface $form_state, array $character_data, string $source_feat_id): void {
    $this->validationRules()->buildFamiliarSelectionSection($container, $form_state, $character_data, $source_feat_id);
  }

  /**
   * Adds Step 4 selection UI for Staff Nexus.
   */
  private function buildStaffNexusSelectionSection(array &$container, FormStateInterface $form_state, array $character_data, string $tradition): void {
    $this->validationRules()->buildStaffNexusSelectionSection($container, $form_state, $character_data, $tradition);
  }

  /**
   * Validates First World Magic cantrip selection.
   */


  /**
   * Validates Otherworldly Magic cantrip selection.
   */


  /**
   * Validates Natural Ambition bonus feat selection.
   */


  /**
   * Validates General Training bonus feat selection.
   */


  /**
   * Validates Elf Atavism feat selection.
   */


  /**
   * Validates Multitalented skill and language selections.
   */


  /**
   * Validates Mixed Heritage Adaptability skill selection.
   */


  /**
   * Validates Orc Atavism feat selection.
   */


  /**
   * Validates Draconic Ties damage type selection.
   */


  /**
   * Validates Natural Skill training selections.
   */


  /**
   * Validates Unconventional Weaponry selection.
   */


  /**
   * Validates Vengeful Hatred target selection.
   */


  /**
   * Validates Ancestral Longevity skill selections.
   */


  /**
   * Validates Gnome Obsession lore selection.
   */


  /**
   * Validates Natural Performer specialty selection.
   */


  /**
   * Validates Specialty Crafting selection.
   */


  /**
   * Validates Virtuosic Performer selection.
   */


  /**
   * Validates Canny Acumen selection.
   */


  /**
   * Validates Adopted Ancestry selection.
   */


  /**
   * Validates Staff Nexus spell selections.
   */


  /**
   * Validates Monster Hunter selection.
   */


  /**
   * Validates Eldritch Trickster dedication selection.
   */


  /**
   * Validates Mastermind knowledge skill selection.
   */


  /**
   * Validates Animal Companion species selection.
   */


  /**
   * Validates familiar creation selections.
   */


  /**
   * Validates Armor Proficiency against the selected class.
   */


  /**
   * Validates Weapon Proficiency against the selected class state.
   */


  /**
   * Validates Domain Initiate against the selected deity.
   */


  /**
   * AJAX callback for deity-dependent feat options.
   */
  public function updateDeityDependentOptions(array &$form, FormStateInterface $form_state): array {
    return $form['deity_dependent'];
  }

  /**
   * Returns canonical Specialty Crafting options.
   */
  private function getSpecialtyCraftingOptions(): array {
    return $this->validationRules()->getSpecialtyCraftingOptions();
  }

  /**
   * Returns common Virtuosic Performer specialty options.
   */
  private function getPerformanceSpecialtyOptions(): array {
    return $this->validationRules()->getPerformanceSpecialtyOptions();
  }

  /**
   * Returns valid Canny Acumen proficiency targets.
   */
  private function getCannyAcumenOptions(): array {
    return $this->validationRules()->getCannyAcumenOptions();
  }

  /**
   * Returns valid Monster Hunter creature types.
   */
  private function getMonsterHunterOptions(): array {
    return $this->validationRules()->getMonsterHunterOptions();
  }

  /**
   * Returns the valid rogue key ability options for the selected racket feat.
   */
  private function resolveRogueKeyAbilityOptions(string $selected_class_feat): array {
    return $this->validationRules()->resolveRogueKeyAbilityOptions($selected_class_feat);
  }

  /**
   * Returns valid Eldritch Trickster multiclass dedication options.
   */
  private function getEldritchTricksterDedicationOptions(): array {
    return $this->validationRules()->getEldritchTricksterDedicationOptions();
  }

  /**
   * Returns valid Mastermind knowledge skill options.
   */
  private function getMastermindKnowledgeSkillOptions(): array {
    return $this->validationRules()->getMastermindKnowledgeSkillOptions();
  }

  /**
   * Resolves the armor tier granted by Armor Proficiency for the current class.
   */
  private function resolveArmorProficiencyTarget(array $character_data): ?string {
    return $this->validationRules()->resolveArmorProficiencyTarget($character_data);
  }

  private function getNaturalPerformerOptions(): array {
    return $this->validationRules()->getNaturalPerformerOptions();
  }

  /**
   * Returns standard skill training options.
   */
  private function getSkillTrainingOptions(): array {
    return $this->validationRules()->getSkillTrainingOptions();
  }

  /**
   * Returns 1st-level elf ancestry feats available to Elf Atavism.
   *
   * @return array<int,array<string,mixed>>
   *   Canonical elf ancestry feat definitions.
   */
  private function getElfAtavismFeatOptions(): array {
    return $this->validationRules()->getElfAtavismFeatOptions();
  }

  /**
   * Returns 1st-level orc ancestry feats available to Orc Atavism.
   *
   * @return array<int,array<string,mixed>>
   *   Canonical orc ancestry feat definitions.
   */
  private function getOrcAtavismFeatOptions(): array {
    return $this->validationRules()->getOrcAtavismFeatOptions();
  }

  /**
   * Returns Draconic Ties damage type options.
   */
  private function getDraconicDamageTypeOptions(): array {
    return $this->validationRules()->getDraconicDamageTypeOptions();
  }

  /**
   * Adds Adapted Cantrip step-4 selection UI once class tradition is known.
   */
  private function buildAdaptedCantripSelectionSection(array &$form, FormStateInterface $form_state, array $character_data, string $native_tradition): void {
    $this->validationRules()->buildAdaptedCantripSelectionSection($form, $form_state, $character_data, $native_tradition);
  }

  /**
   * Validates Adapted Cantrip selection values on step 4.
   */


  /**
   * Builds a consolidated feats array from all feat sources.
   *
   * Collects ancestry feat, class feat, general feat, and background skill
   * feat into a single array for the character sheet display.
   *
   * @param array $character_data
   *   Current character data.
   *
   * @return array
   *   Array of feat entries with type, id, and name.
   */
  private function buildFeatsArray(array $character_data): array {
    $feats = [];
    $class_name = strtolower(trim((string) ($character_data['class'] ?? '')));
    $class_feats = $this->getCreationEligibleClassFeats($class_name, $character_data);

    // Ancestry feat.
    if (!empty($character_data['ancestry_feat'])) {
      $ancestry_name = $this->resolveAncestryName($character_data['ancestry'] ?? '');
      $ancestry_feats = $this->getCanonicalAncestryFeats($ancestry_name);
      foreach ($ancestry_feats as $f) {
        if ($f['id'] === $character_data['ancestry_feat']) {
          $feats[] = ['type' => 'ancestry', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }
    }

    // Class feat.
    if (!empty($character_data['class_feat'])) {
      foreach ($class_feats as $f) {
        if ($f['id'] === $character_data['class_feat']) {
          $feats[] = ['type' => 'class', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }

      if (($character_data['ancestry_feat'] ?? '') === 'natural-ambition') {
        $bonus_class_feat = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
        foreach ($class_feats as $f) {
          if ($f['id'] === $bonus_class_feat) {
            $already_listed = in_array($bonus_class_feat, array_column($feats, 'id'), TRUE);
            if (!$already_listed) {
              $feats[] = [
                'type' => 'class',
                'id' => $f['id'],
                'name' => $f['name'],
                'level' => 1,
                'source' => 'natural-ambition',
              ];
            }
            break;
          }
        }
      }
    }

    $bonus_class_feat = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
    if (($character_data['class_feat'] ?? '') === 'eldritch-trickster-racket' || $bonus_class_feat === 'eldritch-trickster-racket') {
      $selected_dedication = trim((string) ($character_data['feat_selections']['eldritch-trickster-racket']['selected_dedication'] ?? ''));
      foreach (CharacterManager::MULTICLASS_ARCHETYPES as $archetype) {
        $dedication = $archetype['dedication'] ?? [];
        if (($dedication['id'] ?? '') === $selected_dedication) {
          $already_listed = in_array($selected_dedication, array_column($feats, 'id'), TRUE);
          if (!$already_listed) {
            $feats[] = [
              'type' => 'class',
              'id' => $dedication['id'],
              'name' => $dedication['name'],
              'level' => 1,
              'source' => 'eldritch-trickster-racket',
            ];
          }
          break;
        }
      }
    }

    $this->appendSubclassGrantedFeats($feats, $character_data, $class_feats);

    // General feat.
    if (!empty($character_data['general_feat'])) {
      foreach ($this->getCreationEligibleGeneralFeats($character_data) as $f) {
        if ($f['id'] === $character_data['general_feat']) {
          $feats[] = ['type' => 'general', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }
    }

    if (($character_data['ancestry_feat'] ?? '') === 'general-training') {
      $bonus_general_feat = trim((string) ($character_data['feat_selections']['general-training']['bonus_general_feat'] ?? ''));
      foreach ($this->getCreationEligibleGeneralFeats($character_data) as $f) {
        if ($f['id'] === $bonus_general_feat) {
          $already_listed = in_array($bonus_general_feat, array_column($feats, 'id'), TRUE);
          if (!$already_listed) {
            $feats[] = [
              'type' => 'general',
              'id' => $f['id'],
              'name' => $f['name'],
              'level' => 1,
              'source' => 'general-training',
            ];
          }
          break;
        }
      }
    }

    if (($character_data['ancestry_feat'] ?? '') === 'elf-atavism') {
      $bonus_elf_feat = trim((string) ($character_data['feat_selections']['elf-atavism']['selected_feat'] ?? ''));
      foreach ($this->getElfAtavismFeatOptions() as $f) {
        if ($f['id'] === $bonus_elf_feat) {
          $already_listed = in_array($bonus_elf_feat, array_column($feats, 'id'), TRUE);
          if (!$already_listed) {
            $feats[] = [
              'type' => 'ancestry',
              'id' => $f['id'],
              'name' => $f['name'],
              'level' => (int) ($f['level'] ?? 1),
              'source' => 'elf-atavism',
            ];
          }
          break;
        }
      }
    }

    if (($character_data['ancestry_feat'] ?? '') === 'orc-atavism') {
      $bonus_orc_feat = trim((string) ($character_data['feat_selections']['orc-atavism']['selected_feat'] ?? ''));
      foreach ($this->getOrcAtavismFeatOptions() as $f) {
        if ($f['id'] === $bonus_orc_feat) {
          $already_listed = in_array($bonus_orc_feat, array_column($feats, 'id'), TRUE);
          if (!$already_listed) {
            $feats[] = [
              'type' => 'ancestry',
              'id' => $f['id'],
              'name' => $f['name'],
              'level' => (int) ($f['level'] ?? 1),
              'source' => 'orc-atavism',
            ];
          }
          break;
        }
      }
    }

    // Background skill feat.
    if (!empty($character_data['background_skill_feat'])) {
      $feats[] = [
        'type' => 'skill',
        'id' => strtolower(str_replace(' ', '-', $character_data['background_skill_feat'])),
        'name' => $character_data['background_skill_feat'],
        'level' => 1,
        'source' => 'background',
      ];
    }

    return $feats;
  }

  /**
   * Get canonical ancestry feat definitions for the selected ancestry.
   *
   * @return array<int, array<string, mixed>>
   *   Matching ancestry feats.
   */
  private function getCanonicalAncestryFeats(string $ancestry_name): array {
    return $this->validationRules()->getCanonicalAncestryFeats($ancestry_name);
  }

  /**
   * Get creation-eligible ancestry feats for the selected ancestry.
   *
   * @return array<int, array<string, mixed>>
   *   Filtered 1st-level ancestry feats whose prerequisites are met.
   */
  private function getCreationEligibleAncestryFeats(string $ancestry_name, array $character_data): array {
    return $this->validationRules()->getCreationEligibleAncestryFeats($ancestry_name, $character_data);
  }

  /**
   * Get canonical class feat definitions for the selected class.
   *
   * @return array<int, array<string, mixed>>
   *   Matching class feats.
   */
  private function getCanonicalClassFeats(string $class_name): array {
    return $this->validationRules()->getCanonicalClassFeats($class_name);
  }

  /**
   * Get creation-eligible class feats for the selected class.
   *
   * @return array<int, array<string, mixed>>
   *   Filtered 1st-level class feats whose prerequisites are met.
   */
  private function getCreationEligibleClassFeats(string $class_name, array $character_data): array {
    return $this->validationRules()->getCreationEligibleClassFeats($class_name, $character_data);
  }

  /**
   * Get canonical general feat definitions.
   *
   * @return array<int, array<string, mixed>>
   *   General feats.
   */
  private function getCanonicalGeneralFeats(): array {
    return $this->validationRules()->getCanonicalGeneralFeats();
  }

  /**
   * Get creation-eligible general feats.
   *
   * @return array<int, array<string, mixed>>
   *   Filtered 1st-level general feats whose prerequisites are met.
   */
  private function getCreationEligibleGeneralFeats(array $character_data): array {
    return $this->validationRules()->getCreationEligibleGeneralFeats($character_data);
  }

  /**
   * Determine whether a feat is eligible during level-1 character creation.
   */
  private function isCreationFeatEligible(array $feat, array $character_data): bool {
    return $this->validationRules()->isCreationFeatEligible($feat, $character_data);
  }

  /**
   * Evaluate one prerequisite clause, including simple OR chains.
   */
  private function doesCreationPrerequisiteClausePass(string $clause, array $character_data): bool {
    return $this->validationRules()->doesCreationPrerequisiteClausePass($clause, $character_data);
  }

  /**
   * Evaluate one prerequisite atom against current creation state.
   */
  private function doesCreationPrerequisiteAtomPass(string $atom, array $character_data): bool {
    return $this->validationRules()->doesCreationPrerequisiteAtomPass($atom, $character_data);
  }

  /**
   * Resolve the preferred rules text field for feat option cards.
   */
  private function resolveFeatDisplayDescription(array $feat): string {
    return $this->validationRules()->resolveFeatDisplayDescription($feat);
  }

  /**
   * Adds subclass-granted feats that should behave like normal class feats.
   */
  private function appendSubclassGrantedFeats(array &$feats, array $character_data, array $class_feats): void {
    $class_name = strtolower(trim((string) ($character_data['class'] ?? '')));
    $granted_feat_sources = [];
    if ($class_name === 'druid') {
      $selected_order = strtolower(trim((string) ($character_data['subclass'] ?? '')));
      $order_definition = CharacterRulesCatalog::CLASSES['druid']['order']['orders'][$selected_order] ?? NULL;
      if (is_array($order_definition)) {
        foreach (($order_definition['granted_feats'] ?? []) as $granted_feat_id) {
          $granted_feat_sources[] = [
            'id' => strtolower(str_replace('_', '-', trim((string) $granted_feat_id))),
            'source' => 'druid-order:' . $selected_order,
          ];
        }
      }
    }
    elseif ($class_name === 'wizard' && strtolower(trim((string) ($character_data['arcane_thesis'] ?? ''))) === 'improved-familiar-attunement') {
      $granted_feat_sources[] = [
        'id' => 'familiar',
        'source' => 'arcane-thesis:improved-familiar-attunement',
      ];
    }

    foreach ($granted_feat_sources as $granted_feat) {
      $normalized_id = (string) ($granted_feat['id'] ?? '');
      if ($normalized_id === '' || in_array($normalized_id, array_column($feats, 'id'), TRUE)) {
        continue;
      }

      foreach ($class_feats as $feat_definition) {
        if (($feat_definition['id'] ?? '') !== $normalized_id) {
          continue;
        }

        $feats[] = [
          'type' => 'class',
          'id' => $feat_definition['id'],
          'name' => $feat_definition['name'],
          'level' => (int) ($feat_definition['level'] ?? 1),
          'source' => (string) ($granted_feat['source'] ?? 'granted'),
        ];
        break;
      }
    }
  }

  /**
   * Resolves which Step 4 source currently grants an animal companion choice.
   */
  private function resolveAnimalCompanionSelectionSource(FormStateInterface $form_state, array $character_data, string $selected_class): ?string {
    return $this->validationRules()->resolveAnimalCompanionSelectionSource($form_state, $character_data, $selected_class);
  }

  /**
   * Resolves which Step 4 source currently grants a familiar choice.
   */
  private function resolveFamiliarSelectionSource(FormStateInterface $form_state, array $character_data, string $selected_class): ?string {
    return $this->validationRules()->resolveFamiliarSelectionSource($form_state, $character_data, $selected_class);
  }

  /**
   * Return familiar-granting feat or class feature identifiers used in Step 4.
   *
   * @return array<int,string>
   *   Familiar source identifiers.
   */
  private function getFamiliarSelectionSourceIds(): array {
    return $this->validationRules()->getFamiliarSelectionSourceIds();
  }

  /**
   * Determine the familiar ability count available at creation.
   */
  private function getFamiliarStartingAbilityCount(array $character_data, string $source_feat_id): int {
    return $this->validationRules()->getFamiliarStartingAbilityCount($character_data, $source_feat_id);
  }

  /**
   * Determine whether a familiar form supports wing-based abilities.
   */
  private function familiarTypeHasWings(string $familiar_type): bool {
    return $this->validationRules()->familiarTypeHasWings($familiar_type);
  }

  /**
   * Build a persisted familiar payload for character creation.
   *
   * @param array<string,mixed> $character_data
   *   Character payload.
   * @param string $selected_class
   *   Selected class id.
   * @param array<string,mixed> $selection
   *   Familiar selection payload.
   *
   * @return array<string,mixed>
   *   Persisted familiar record.
   */
  private function buildCreationFamiliarPayload(array $character_data, string $selected_class, array $selection): array {
    $level = max(1, (int) ($character_data['level'] ?? 1));
    $familiar_type = strtolower(trim((string) ($selection['selected_familiar_type'] ?? $selection['familiar_type'] ?? 'standard')));
    if ($familiar_type === '' || ($familiar_type !== 'standard' && !isset(FamiliarService::FAMILIAR_TYPES[$familiar_type]))) {
      $familiar_type = 'standard';
    }
    $familiar_species_name = $familiar_type !== 'standard' && isset(FamiliarService::FAMILIAR_TYPES[$familiar_type]['name'])
      ? (string) FamiliarService::FAMILIAR_TYPES[$familiar_type]['name']
      : 'Familiar';
    $familiar_description = sprintf('Bound %s familiar ally.', strtolower($familiar_species_name));

    return [
      'familiar_id' => ((string) ($character_data['name'] ?? 'character')) . '_familiar',
      'character_id' => (string) ($character_data['character_id'] ?? ''),
      'familiar_type' => $familiar_type,
      'familiar_species_name' => $familiar_species_name,
      'name' => trim((string) ($selection['name'] ?? '')),
      'description' => $familiar_description,
      'hp' => FamiliarService::HP_PER_LEVEL * $level,
      'max_hp' => FamiliarService::HP_PER_LEVEL * $level,
      'speed' => FamiliarService::DEFAULT_SPEED,
      'state' => 'alive',
      'abilities' => self::normalizeList($selection['selected_familiar_abilities'] ?? []),
      'has_wings' => $this->familiarTypeHasWings($familiar_type),
      'is_witch_required' => $selected_class === 'witch',
      'spell_storage' => 0,
      'stored_witch_spells' => [],
      'downtime_replacement' => NULL,
    ];
  }

}
