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
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Simple form for character creation steps.
 */
class CharacterCreationStepForm extends FormBase {

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
    protected CsrfTokenGenerator $csrfToken,
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
      $container->get('csrf_token'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'character_creation_step_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, int $step = 1, int|string|null $character_id = NULL, int|string|null $campaign_id = NULL): array {
    $embedded = (bool) $this->getRequest()->query->get('embedded');
    $setup_shell = $this->getRequest()->getPathInfo() === '/charactersetup';
    $compact_layout = $embedded || $setup_shell;
    $character_data = $this->loadCharacterData($character_id);

    // Load character record for concurrent-edit version tracking.
    $character_record = $character_id ? $this->characterManager->loadCharacter((int) $character_id) : NULL;
    $form['character_version'] = [
      '#type' => 'hidden',
      '#value' => $character_record ? (int) ($character_record->version ?? 0) : 0,
    ];

    // Store metadata
    $form_state->set('step', $step);
    $form_state->set('character_id', $character_id);
    $form_state->set('campaign_id', $campaign_id);

    if ($campaign_id) {
      $form['campaign_id'] = [
        '#type' => 'hidden',
        '#value' => $campaign_id,
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
        'endpoint' => Url::fromRoute('dungeoncrawler_content.api.character_gm_chat')->toString(),
        'characterId' => $character_id ? (int) $character_id : NULL,
        'campaignId' => $campaign_id ? (int) $campaign_id : NULL,
        'step' => $step,
        'csrfToken' => $this->csrfToken->get('rest'),
        'history' => $this->characterCreationGm->getChatHistory($character_data),
        'summary' => $this->characterCreationGm->buildSummary($character_data),
      ];
    }

    $prefix = $compact_layout ? '' : $this->buildGmChatShell($step, $character_id, $campaign_id, $character_data);
    $progress_markup = $compact_layout ? '' : '<div class="progress-bar"><div class="progress-indicator progress-step-' . $step . '"></div></div><div class="progress-text">' . $this->t('Step @step of @total', ['@step' => $step, '@total' => 8]) . '</div>';
    $form['#prefix'] = Markup::create($prefix
      . '<div class="character-creation-step' . ($compact_layout ? ' character-creation-step--embedded' : '') . '"><div class="creation-container">' . $progress_markup . '<div class="step-content">');
    $form['#suffix'] = Markup::create('</div></div></div></div>');

    $show_quick_play = $campaign_id !== NULL
      && $campaign_id !== ''
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

    return $form;
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
      $history_markup = '<div class="character-creation-gm-chat__empty">'
        . Html::escape((string) $this->t('Ask the GM to build your character, recommend options, or directly change the draft while you stay in the wizard.'))
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
    $field_schema = $schema_fields[$field_name]['properties'] ?? [];
    if ($field_schema === []) {
      return;
    }

    $validation = $field_schema['validation']['properties'] ?? [];
    $required = $field_schema['required']['const'] ?? NULL;

    if ($required !== NULL && !isset($element['#required'])) {
      $element['#required'] = (bool) $required;
    }

    if (!isset($element['#attributes'])) {
      $element['#attributes'] = [];
    }

    $min_length = $this->getSchemaConstraintValue($validation['min_length'] ?? NULL);
    if ($min_length !== NULL) {
      $element['#attributes']['minlength'] = (int) $min_length;
    }

    $max_length = $this->getSchemaConstraintValue($validation['max_length'] ?? NULL);
    if ($max_length !== NULL) {
      $element['#maxlength'] = $element['#maxlength'] ?? (int) $max_length;
      $element['#attributes']['maxlength'] = (int) $max_length;
    }

    $pattern = $this->getSchemaConstraintValue($validation['pattern'] ?? NULL);
    if ($pattern !== NULL) {
      $element['#attributes']['pattern'] = $pattern;
    }
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
    if (!is_array($constraint)) {
      return NULL;
    }

    return $constraint['const'] ?? $constraint['default'] ?? NULL;
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
    $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
    $abilities = [];
    foreach ($calculation['scores'] as $key => $score) {
      $abilities[$key] = [
        'score' => $score,
        'modifier' => $calculation['modifiers'][$key],
        'sources' => $calculation['sources'][$key] ?? [],
      ];
    }
    $form['ability_preview'] = [
      '#theme' => 'character_ability_widget',
      '#abilities' => $abilities,
      '#mode' => $mode,
      '#show_sources' => $show_sources,
      '#help_text' => $this->t($help_text),
    ];
  }

  /**
   * Builds Step 1 fields.
   */
  private function buildStep1Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->attachAbilityPreview($form, $character_data, 'Your ability scores (will update as you progress)', FALSE);

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Legacy Character Name'),
      '#required' => TRUE,
      '#default_value' => $character_data['name'] ?? '',
      '#maxlength' => 50,
      '#placeholder' => $this->t('The name your roster will remember'),
      '#description' => $this->t('Your character\'s name will appear in all campaign records and legacy logs.'),
    ];
    $this->applySchemaValidationAttributes($form['name'], $schema_fields, 'name');
    $form['concept'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Character Concept'),
      '#default_value' => $character_data['concept'] ?? '',
      '#rows' => 4,
      '#placeholder' => $this->t('e.g., "Fortune-favored rogue seeking redemption", "Dwarf paladin defending the old ways"'),
      '#description' => $this->t('Optional: Capture your character\'s long-term identity and campaign arc. Think in terms of a character you\'ll want to revisit across many expeditions.'),
    ];
    $this->applySchemaValidationAttributes($form['concept'], $schema_fields, 'concept');
  }

  /**
   * Builds Step 2 fields.
   */
  private function buildStep2Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    // Step 2: Ancestry → Heritage → Ancestry Feat.
    // AJAX on ancestry select refreshes #heritage-path-wrapper (heritage + feat).
    // Validation is in validateForm() case 2 (not #required, to avoid :invalid).
    $heritage_payload = [];
    foreach (CharacterManager::HERITAGES as $ancestry_name => $heritages) {
      $ancestry_id = self::ancestryMachineId($ancestry_name);
      $heritage_payload[$ancestry_id] = $heritages;
    }

    $heritage_json = Html::escape(json_encode(
      $heritage_payload,
      JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ));

    $user_input = $form_state->getUserInput();
    $selected_ancestry = (string) (
      $form_state->getValue('ancestry')
      ?: (is_array($user_input) ? ($user_input['ancestry'] ?? '') : '')
      ?: ($character_data['ancestry'] ?? '')
    );
    $heritage_options = $this->getHeritageOptions($selected_ancestry);
    $has_heritage_choices = count($heritage_options) > 1;
    $selected_heritage = (string) ($form_state->getValue('heritage') ?: ($character_data['heritage'] ?? ''));
    if ($selected_heritage !== '' && !array_key_exists($selected_heritage, $heritage_options)) {
      $selected_heritage = '';
    }

    $selected_ancestry_boosts = self::normalizeList($form_state->getValue('ancestry_boosts', $character_data['ancestry_boosts'] ?? []));
    $ancestry_boost_config = CharacterManager::getAncestryBoostConfig($selected_ancestry, $selected_heritage);
    $ancestry_free_boosts_total = (int) ($ancestry_boost_config['free_boosts'] ?? 0);
    $ancestry_fixed_boosts = array_values(array_filter(array_map(
      fn(string $boost): ?string => $this->abilityScoreTracker->normalizeAbilityKey($boost),
      $ancestry_boost_config['fixed_boosts'] ?? []
    )));

    $character_data_for_widget = $character_data;
    if ($selected_ancestry !== '') {
      $character_data_for_widget['ancestry'] = $selected_ancestry;
    }
    if ($selected_heritage !== '') {
      $character_data_for_widget['heritage'] = $selected_heritage;
    }
    $character_data_for_widget['ancestry_boosts'] = $selected_ancestry_boosts;

    $this->attachAbilityPreview($form, $character_data_for_widget, 'Current ability scores (from ancestry)');

    $ancestry_cards_markup = '<div class="ancestry-selection" data-heritages="' . $heritage_json . '">';
    $ancestry_cards_markup .= '<div class="ancestry-grid">';

    foreach (CharacterManager::ANCESTRIES as $ancestry_name => $ancestry_data) {
      $ancestry_id = self::ancestryMachineId($ancestry_name);
      $selected_class = $selected_ancestry === $ancestry_id ? ' selected' : '';
      $boosts = $ancestry_data['boosts'] ?? [];
      $boosts_label = $boosts ? implode(', ', $boosts) : 'None';
      $flaw = $ancestry_data['flaw'] ?? '';
      $vision = $ancestry_data['vision'] ?? 'normal';

      $ancestry_cards_markup .= '<div class="ancestry-card' . $selected_class . '" data-ancestry="' . Html::escape($ancestry_id) . '">';
      $ancestry_cards_markup .= '<h3>' . Html::escape($ancestry_name) . '</h3>';
      $ancestry_cards_markup .= '<div class="ancestry-stats">';
      $ancestry_cards_markup .= '<span class="stat"><strong>HP:</strong> ' . (int) ($ancestry_data['hp'] ?? 0) . '</span>';
      $ancestry_cards_markup .= '<span class="stat"><strong>Size:</strong> ' . Html::escape((string) ($ancestry_data['size'] ?? '')) . '</span>';
      $ancestry_cards_markup .= '<span class="stat"><strong>Speed:</strong> ' . (int) ($ancestry_data['speed'] ?? 0) . 'ft</span>';
      $ancestry_cards_markup .= '</div>';
      $ancestry_cards_markup .= '<div class="ancestry-traits">';
      $ancestry_cards_markup .= '<span><strong>Boosts:</strong> ' . Html::escape($boosts_label) . '</span>';
      if ($flaw !== '') {
        $ancestry_cards_markup .= '<span><strong>Flaw:</strong> ' . Html::escape($flaw) . '</span>';
      }
      $ancestry_cards_markup .= '<span><strong>Vision:</strong> ' . Html::escape($vision) . '</span>';
      $ancestry_cards_markup .= '</div>';
      $ancestry_cards_markup .= '</div>';
    }

    $ancestry_cards_markup .= '</div>';
    $ancestry_cards_markup .= '<div id="heritageSelection" class="heritage-section hidden">';
    $ancestry_cards_markup .= '<h3>' . $this->t('Choose a Heritage') . '</h3>';
    $ancestry_cards_markup .= '<div id="heritageOptions" class="heritage-grid"></div>';
    $ancestry_cards_markup .= '</div>';
    $ancestry_cards_markup .= '</div>';

    $form['ancestry_cards'] = [
      '#type' => 'markup',
      '#markup' => $ancestry_cards_markup,
    ];

    $form['ancestry'] = [
      '#type' => 'select',
      '#title' => $this->t('Legacy Ancestry'),
      '#required' => TRUE,
      '#options' => $this->getAncestryOptions(),
      '#default_value' => $selected_ancestry,
      '#description' => $this->t('Your character\'s ancestral blood will determine size, speed, special senses, and long-term physical identity across all campaigns.'),
      // Visually hidden: the ancestry card grid is the user-facing selector.
      // This <select> stays in the DOM for Form API AJAX, validation, and
      // submission; JS syncs it when a card is clicked.
      '#wrapper_attributes' => ['class' => ['dc-visually-hidden']],
      '#ajax' => [
        'callback' => '::updateHeritageOptions',
        'wrapper' => 'heritage-path-wrapper',
        'event' => 'change',
      ],
      // Do NOT set #limit_validation_errors here. For AJAX triggered by a
      // non-button element, Drupal's FormValidator defaults to validating
      // nothing (returns []) when #limit_validation_errors is absent and the
      // form is not explicitly submitted. Setting it explicitly would override
      // that safe default and cause partial validation to run, which surfaces
      // the ancestry_feat "submitted value not allowed" error.
    ];
    $this->applySchemaValidationAttributes($form['ancestry'], $schema_fields, 'ancestry');

    $form['heritage_dynamic'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'heritage-path-wrapper',
      ],
    ];

    $form['heritage_dynamic']['heritage'] = [
      '#type' => 'select',
      '#title' => $this->t('Heritage Path'),
      // Do NOT use #required here: the browser applies :invalid to a required
      // <select> with empty value immediately on AJAX response, causing red
      // styling before the user has touched the field.
      // Validation is enforced in validateForm() case 2 instead.
      '#required' => FALSE,
      '#options' => $heritage_options,
      '#default_value' => $selected_heritage,
      '#value_callback' => [$this, 'sanitizeOptionValue'],
      // Visually hidden: JS-rendered heritage cards are the user-facing selector.
      '#wrapper_attributes' => ['class' => ['dc-visually-hidden']],
      '#description' => $this->t('Select a heritage to specialize your ancestry with unique talents and abilities that define your legacy.'),
    ];
    $this->clearStaleOptionInput($form_state, 'heritage', $heritage_options);
    $this->applySchemaValidationAttributes($form['heritage_dynamic']['heritage'], $schema_fields, 'heritage');

    if (!empty($selected_ancestry) && !$has_heritage_choices) {
      $form['heritage_dynamic']['heritage_unavailable_notice'] = [
        '#type' => 'markup',
        '#markup' => '<div class="messages messages--warning">'
          . $this->t('No heritage options are currently configured for this ancestry. You can continue to the next step and set heritage later when available.')
          . '</div>',
      ];
    }

    if ($ancestry_free_boosts_total > 0) {
      $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data_for_widget);
      $abilities_data = $this->buildInteractiveAbilityData($calculation, $selected_ancestry_boosts);
      foreach ($ancestry_fixed_boosts as $ability_key) {
        if (isset($abilities_data[$ability_key])) {
          $abilities_data[$ability_key]['disabled'] = TRUE;
        }
      }

      $help_text = !empty($ancestry_fixed_boosts)
        ? $this->t('Choose @count free ancestry boost(s). You can’t choose an ability that already received a fixed ancestry boost, but you can offset an ancestry flaw.', ['@count' => $ancestry_free_boosts_total])
        : $this->t('Choose @count free ancestry boost(s). Each selection must be different.', ['@count' => $ancestry_free_boosts_total]);

      $form['heritage_dynamic']['ancestry_boosts_help'] = [
        '#markup' => '<div class="section-instructions ancestry-boosts-section">'
          . '<h3>' . $this->t('Ancestry Ability Boosts') . '</h3>'
          . '<p>' . $help_text . '</p>'
          . '</div>',
      ];

      $form['heritage_dynamic']['ancestry_boosts_selector'] = [
        '#theme' => 'character_ability_widget',
        '#abilities' => $abilities_data,
        '#mode' => 'interactive',
        '#show_sources' => TRUE,
        '#boosts_remaining' => max(0, $ancestry_free_boosts_total - count($selected_ancestry_boosts)),
        '#boosts_total' => $ancestry_free_boosts_total,
        '#attributes' => [
          'data-step' => 'ancestry',
          'data-max-boosts' => $ancestry_free_boosts_total,
          'data-character-data' => json_encode($character_data_for_widget),
        ],
      ];

      $form['heritage_dynamic']['ancestry_boosts'] = [
        '#type' => 'hidden',
        '#default_value' => json_encode($selected_ancestry_boosts),
        '#attributes' => ['id' => 'ancestry-boosts-field'],
      ];
    }

    // Ancestry Feat Selection — nested inside heritage_dynamic so the AJAX
    // callback (which returns $form['heritage_dynamic']) refreshes both the
    // heritage select and the ancestry feat radios in a single response.
    // This eliminates the stale-value "submitted value not allowed" error
    // that occurred when ancestry changed but ancestry_feat_dynamic was
    // rendered outside the AJAX wrapper.
    $form['heritage_dynamic']['ancestry_feat_dynamic'] = [
      '#type' => 'container',
    ];
    if (!empty($selected_ancestry)) {
      $ancestry_name = $this->resolveAncestryName($selected_ancestry);
      $ancestry_feats = CharacterManager::getAncestryFeats($ancestry_name);

      if (!empty($ancestry_feats)) {
        $form['heritage_dynamic']['ancestry_feat_dynamic']['ancestry_feat_section'] = [
          '#markup' => '<div class="section-instructions ancestry-feat-section">'
            . '<h3>' . $this->t('Ancestry Feat') . '</h3>'
            . '<p>' . $this->t('Choose one 1st-level ancestry feat. This represents a special ability or training unique to your ancestry.') . '</p>'
            . '</div>',
        ];

        $feat_options = [];
        $feat_cards = [];

        foreach ($ancestry_feats as $feat) {
          $feat_options[$feat['id']] = $feat['name'];
          $feat_cards[$feat['id']] = $this->buildOptionCardData(
            $feat['benefit'] ?? '',
            [],
            [
              (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
            ],
          );
        }

        $selected_feat = (string) ($form_state->getValue('ancestry_feat') ?: ($character_data['ancestry_feat'] ?? ''));
        if ($selected_feat !== '' && !array_key_exists($selected_feat, $feat_options)) {
          $selected_feat = '';
        }

        $form['heritage_dynamic']['ancestry_feat_dynamic']['ancestry_feat'] = [
          '#type' => 'radios',
          '#title' => $this->t('Select Ancestry Feat'),
          '#options' => $feat_options,
          '#default_value' => $selected_feat,
          '#value_callback' => [$this, 'sanitizeOptionValue'],
          '#ajax' => [
            'callback' => '::updateAncestryFeatOptions',
            'wrapper' => 'heritage-path-wrapper',
            'event' => 'change',
          ],
          // Do NOT use #required => TRUE on radio groups: the browser immediately
          // applies :invalid CSS to all unselected required radio inputs on page
          // load, making the group appear red before the user interacts at all.
          // Validation is enforced in validateForm() case 2 instead.
          '#required' => FALSE,
          // Skip Drupal's built-in allowed-values check: sanitizeOptionValue
          // already normalises submitted values to '' or a valid option. Without
          // '#validated', Drupal's FormValidator rejects '' (empty/unselected)
          // because '' is not in $feat_options, logging a spurious "submitted
          // value not allowed" watchdog error on every AJAX request to this step.
          '#validated' => TRUE,
          '#description' => $this->t('Each feat provides unique mechanical benefits that reflect your ancestry\'s culture and abilities.'),
        ];
        $this->clearStaleOptionInput($form_state, 'ancestry_feat', $feat_options);
        $this->attachOptionCardSettings($form['heritage_dynamic']['ancestry_feat_dynamic'], 'ancestry_feat', $feat_cards, 'single');

        if ($selected_feat === 'first-world-magic') {
          $this->buildFirstWorldMagicSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'otherworldly-magic') {
          $this->buildOtherworldlyMagicSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'general-training') {
          $this->buildGeneralTrainingSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'elf-atavism') {
          $this->buildElfAtavismSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'multitalented') {
          $this->buildMultitalentedSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'mixed-heritage-adaptability') {
          $this->buildMixedHeritageAdaptabilitySelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'orc-atavism') {
          $this->buildOrcAtavismSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'draconic-ties') {
          $this->buildDraconicTiesSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'natural-skill') {
          $this->buildNaturalSkillSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'unconventional-weaponry') {
          $this->buildUnconventionalWeaponrySelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'vengeful-hatred') {
          $this->buildVengefulHatredSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'ancestral-longevity') {
          $this->buildAncestralLongevitySelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'gnome-obsession') {
          $this->buildGnomeObsessionSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
        elseif ($selected_feat === 'natural-performer') {
          $this->buildNaturalPerformerSelectionSection(
            $form['heritage_dynamic']['ancestry_feat_dynamic'],
            $form_state,
            $character_data
          );
        }
      }
      else {
        $this->clearStaleOptionInput($form_state, 'ancestry_feat', []);
      }
    }
  }

  /**
   * Builds Step 3 fields.
   */
  private function buildStep3Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $form['background'] = [
      '#type' => 'select',
      '#title' => $this->t('Pre-Campaign Background'),
      '#required' => TRUE,
      '#options' => $this->getBackgroundOptions(),
      '#default_value' => $character_data['background'] ?? '',
      '#description' => $this->t('Your character\'s former life shaped who they are. This choice grants lasting skills and a foundation for long-term roleplay consistency.'),
      '#ajax' => [
        'callback' => '::updateBackgroundOptions',
        'wrapper' => 'background-dynamic-wrapper',
        'event' => 'change',
      ],
    ];

    // Background Ability Boosts: 1 fixed (auto) + 1 free (player choice).
    $selected_background_for_boosts = (string) ($form_state->getValue('background') ?: ($character_data['background'] ?? ''));
    $selected_background_boosts = self::normalizeList($form_state->getValue('background_boosts', $character_data['background_boosts'] ?? []));
    $bg_boost_data = !empty($selected_background_for_boosts) ? (CharacterManager::BACKGROUNDS[$selected_background_for_boosts] ?? NULL) : NULL;
    $has_fixed_boost = $bg_boost_data && isset($bg_boost_data['fixed_boost']);
    $boost_total = $has_fixed_boost ? 1 : 2;
    $boost_desc = $has_fixed_boost
      ? $this->t('Your background automatically applies a fixed boost to <strong>@ability</strong>. Choose one additional free ability boost (must differ from the fixed boost).', ['@ability' => strtoupper($bg_boost_data['fixed_boost'])])
      : $this->t('Your background grants 2 free ability boosts. Choose any two different abilities to boost.');

    $character_data_for_widget = $character_data;
    if ($selected_background_for_boosts !== '') {
      $character_data_for_widget['background'] = $selected_background_for_boosts;
    }
    $character_data_for_widget['background_boosts'] = $selected_background_boosts;

    $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data_for_widget);
    $abilities_data = $this->buildInteractiveAbilityData($calculation, $selected_background_boosts);

    $form['background_dynamic'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      '#attributes' => [
        'id' => 'background-dynamic-wrapper',
      ],
    ];

    $form['background_dynamic']['background_boosts_help'] = [
      '#markup' => '<div class="section-instructions background-boosts-section">'
        . '<h3>' . $this->t('Background Ability Boosts') . '</h3>'
        . '<p>' . $boost_desc . '</p>'
        . '</div>',
    ];

    $form['background_dynamic']['background_boosts_selector'] = [
      '#theme' => 'character_ability_widget',
      '#abilities' => $abilities_data,
      '#mode' => 'interactive',
      '#show_sources' => TRUE,
      '#boosts_remaining' => $boost_total - count($selected_background_boosts),
      '#boosts_total' => $boost_total,
      '#attributes' => [
        'data-step' => 'background',
        'data-max-boosts' => $boost_total,
        'data-character-data' => json_encode($character_data_for_widget),
      ],
    ];

    $form['background_dynamic']['background_boosts'] = [
      '#type' => 'hidden',
      '#default_value' => json_encode($selected_background_boosts),
      '#attributes' => ['id' => 'background-boosts-field'],
    ];

    // Background Skill Training
    $selected_background = $selected_background_for_boosts;
    if (!empty($selected_background)) {
      $background_data = CharacterManager::BACKGROUNDS[$selected_background] ?? NULL;
      
      if ($background_data) {
        $form['background_dynamic']['background_skills_section'] = [
          '#markup' => '<div class="section-instructions background-skills-section">'
            . '<h3>' . $this->t('Background Skills') . '</h3>'
            . '<p>' . $this->t('Your background grants training in a specific skill and lore, plus a skill feat.') . '</p>'
            . '</div>',
        ];

        $form['background_dynamic']['background_skill'] = [
          '#markup' => $this->buildSelectionDetailMarkup(
            $background_data['name'] ?? $selected_background,
            $background_data['description'] ?? '',
            [],
            [
              (string) $this->t('Skill Training') => $background_data['skill'] ?? 'Varies',
              (string) $this->t('Lore Skill') => $background_data['lore'] ?? 'Varies',
              (string) $this->t('Skill Feat') => $background_data['feat'] ?? 'Varies',
              (string) $this->t('Application') => (string) $this->t('These will be automatically applied to your character.'),
            ],
          ),
        ];

        // For backgrounds with skill choices (like Scholar), add selector
        if ($selected_background === 'scholar') {
          $form['background_dynamic']['scholar_skill_choice'] = [
            '#type' => 'radios',
            '#title' => $this->t('Choose Primary Skill'),
            '#options' => [
              'Arcana' => 'Arcana (magic and spells)',
              'Nature' => 'Nature (wilderness and animals)',
              'Occultism' => 'Occultism (mysteries and spirits)',
              'Religion' => 'Religion (gods and divine power)',
            ],
            '#default_value' => $character_data['scholar_skill_choice'] ?? 'Arcana',
            '#required' => FALSE,
            '#description' => $this->t('Scholars can specialize in one of these knowledge domains.'),
          ];
        }
      }
    }
  }

  /**
   * Builds Step 4 fields.
   */
  private function buildStep4Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $form['class'] = [
      '#type' => 'select',
      '#title' => $this->t('Class Role'),
      '#required' => TRUE,
      '#options' => $this->getClassOptions(),
      '#default_value' => $character_data['class'] ?? '',
      '#description' => $this->t('Choose how your character will contribute to the party across many campaigns. Consider what role you\'ll enjoy playing across dozens of sessions.'),
      '#ajax' => [
        'callback' => '::updateClassOptions',
        'wrapper' => 'class-dynamic-wrapper',
        'event' => 'change',
      ],
    ];

    // Dynamic container: rebuilt via AJAX when class changes.
    $form['class_dynamic'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      '#attributes' => [
        'id' => 'class-dynamic-wrapper',
        'class' => ['step4-class-tabs'],
        'data-step4-tab-root' => 'true',
      ],
    ];

    // Resolve selected class: form_state (AJAX) takes priority over DB data.
    $selected_class = (string) ($form_state->getValue('class') ?: ($character_data['class'] ?? ''));
    if ($selected_class === '') {
      return;
    }

    $class_data = CharacterManager::CLASSES[$selected_class] ?? NULL;
    if (!$class_data) {
      return;
    }

    $selected_class_feat = (string) ($form_state->getValue('class_feat') ?: ($character_data['class_feat'] ?? ''));

    // Key Ability Selection
    $key_ability_raw = $class_data['key_ability'] ?? '';
    $key_options = $selected_class === 'rogue'
      ? $this->resolveRogueKeyAbilityOptions($selected_class_feat)
      : $this->abilityScoreTracker->normalizeAbilityOptions($key_ability_raw);

    if (count($key_options) > 1) {
      $form['class_dynamic']['class_key_ability_help'] = [
        '#markup' => '<div class="section-instructions class-key-ability-section">'
          . '<h3>' . $this->t('Choose Key Ability') . '</h3>'
          . '<p>' . $this->t('Your class allows a choice of key ability. This determines which ability receives a boost from your class.') . '</p>'
          . '</div>',
      ];

      $key_ability_options = [];
      foreach ($key_options as $option) {
        $normalized = $this->abilityScoreTracker->normalizeAbilityKey($option);
        if ($normalized) {
          $key_ability_options[$normalized] = ucfirst($normalized);
        }
      }

      $form['class_dynamic']['class_key_ability'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Key Ability'),
        '#options' => $key_ability_options,
        '#default_value' => $this->abilityScoreTracker->normalizeAbilityKey($character_data['class_key_ability'] ?? NULL) ?? array_key_first($key_ability_options),
        '#required' => TRUE,
        '#description' => $this->t('This ability will receive a +2 boost and is the primary ability for your class features.'),
      ];
    }
    else {
      $key_ability = $this->abilityScoreTracker->normalizeAbilityKey($key_options[0]);
      $form['class_dynamic']['class_key_ability_readonly'] = [
        '#markup' => '<div class="class-info">'
          . '<p><strong>' . $this->t('Key Ability:') . '</strong> ' . ucfirst($key_ability ?? 'Unknown') . ' ' . $this->t('(automatically applied)') . '</p>'
          . '</div>',
      ];
    }

    // Class Feat Selection
    $class_feats = CharacterManager::getClassFeats($selected_class);

    if (!empty($class_feats)) {
      $form['class_dynamic']['class_feat_section'] = [
        '#markup' => '<div class="section-instructions class-feat-section">'
          . '<h3>' . $this->t('Class Feat') . '</h3>'
          . '<p>' . $this->t('Choose one 1st-level class feat. This represents specialized training or a unique technique for your class.') . '</p>'
          . '</div>',
      ];

      $feat_options = [];
      $feat_cards = [];

      foreach ($class_feats as $feat) {
        $feat_options[$feat['id']] = $feat['name'];
        $feat_cards[$feat['id']] = $this->buildOptionCardData(
          $feat['benefit'] ?? '',
          $feat['traits'] ?? [],
          [
            (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
          ],
        );
      }

      $form['class_dynamic']['class_feat'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Class Feat'),
        '#options' => $feat_options,
        '#default_value' => $character_data['class_feat'] ?? '',
        '#required' => TRUE,
        '#description' => $this->t('Each feat provides unique tactical options that define your combat style.'),
        '#ajax' => [
          'callback' => '::updateClassOptions',
          'wrapper' => 'class-dynamic-wrapper',
          'event' => 'change',
        ],
      ];
      $this->attachOptionCardSettings($form['class_dynamic'], 'class_feat', $feat_cards, 'single');

      if ($selected_class_feat === 'monster-hunter') {
        $this->buildMonsterHunterSelectionSection($form['class_dynamic'], $form_state, $character_data);
      }
      if ($selected_class === 'rogue' && $selected_class_feat === 'eldritch-trickster-racket') {
        $this->buildEldritchTricksterSelectionSection($form['class_dynamic'], $form_state, $character_data);
      }
      if ($selected_class === 'rogue' && $selected_class_feat === 'mastermind-racket') {
        $this->buildMastermindSelectionSection($form['class_dynamic'], $form_state, $character_data);
      }

      if (($character_data['ancestry_feat'] ?? '') === 'natural-ambition') {
        $this->buildNaturalAmbitionSelectionSection($form, $form_state, $character_data, $selected_class, $class_feats);
        $selected_bonus_feat = (string) $form_state->getValue(
          ['feat_selections', 'natural-ambition', 'bonus_class_feat'],
          $character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''
        );
        if ($selected_class_feat !== 'monster-hunter' && $selected_bonus_feat === 'monster-hunter') {
          $this->buildMonsterHunterSelectionSection($form['class_dynamic'], $form_state, $character_data);
        }
        if ($selected_class === 'rogue' && $selected_class_feat !== 'eldritch-trickster-racket' && $selected_bonus_feat === 'eldritch-trickster-racket') {
          $this->buildEldritchTricksterSelectionSection($form['class_dynamic'], $form_state, $character_data);
        }
        if ($selected_class === 'rogue' && $selected_class_feat !== 'mastermind-racket' && $selected_bonus_feat === 'mastermind-racket') {
          $this->buildMastermindSelectionSection($form['class_dynamic'], $form_state, $character_data);
        }
      }

      $animal_companion_source = $this->resolveAnimalCompanionSelectionSource($form_state, $character_data, $selected_class);
      if ($animal_companion_source !== NULL) {
        $this->buildAnimalCompanionSelectionSection($form['class_dynamic'], $form_state, $character_data, $animal_companion_source);
      }
    }

    // --- Subclass selection for flexible-tradition casters ---
    if ($selected_class === 'sorcerer') {
      $form['class_dynamic']['bloodline_section'] = [
        '#markup' => '<div class="section-instructions bloodline-section">'
          . '<h3>' . $this->t('Sorcerer Bloodline') . '</h3>'
          . '<p>' . $this->t('Your bloodline determines your spellcasting tradition. Choose the source of your innate magical power.') . '</p>'
          . '</div>',
      ];
      $bloodline_options = [];
      $bloodline_cards = [];
      foreach (CharacterManager::SORCERER_BLOODLINES as $bl_id => $bl) {
        $bloodline_options[$bl_id] = $bl['label'];
        $bloodline_cards[$bl_id] = $this->buildOptionCardData(
          $bl['description'] ?? '',
          [ucfirst((string) ($bl['tradition'] ?? '')) . ' tradition'],
        );
      }
      $form['class_dynamic']['subclass'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Bloodline'),
        '#options' => $bloodline_options,
        '#default_value' => $character_data['subclass'] ?? '',
        '#required' => TRUE,
        '#description' => $this->t('Your bloodline determines which spell tradition you cast from.'),
        '#ajax' => [
          'callback' => '::updateClassOptions',
          'wrapper' => 'class-dynamic-wrapper',
          'event' => 'change',
        ],
      ];
      $this->attachOptionCardSettings($form['class_dynamic'], 'subclass', $bloodline_cards, 'single');
    }
    elseif ($selected_class === 'witch') {
      $form['class_dynamic']['patron_section'] = [
        '#markup' => '<div class="section-instructions patron-section">'
          . '<h3>' . $this->t('Witch Patron') . '</h3>'
          . '<p>' . $this->t('Your patron is the mysterious entity that granted you magic and a familiar. Choose your patron theme to determine your spellcasting tradition.') . '</p>'
          . '</div>',
      ];
      $patron_options = [];
      $patron_cards = [];
      foreach (CharacterManager::WITCH_PATRONS as $p_id => $p) {
        $patron_options[$p_id] = $p['label'];
        $patron_cards[$p_id] = $this->buildOptionCardData(
          $p['description'] ?? '',
          [ucfirst((string) ($p['tradition'] ?? '')) . ' tradition'],
        );
      }
      $form['class_dynamic']['subclass'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Patron'),
        '#options' => $patron_options,
        '#default_value' => $character_data['subclass'] ?? '',
        '#required' => TRUE,
        '#description' => $this->t('Your patron determines which spell tradition you cast from.'),
        '#ajax' => [
          'callback' => '::updateClassOptions',
          'wrapper' => 'class-dynamic-wrapper',
          'event' => 'change',
        ],
      ];
      $this->attachOptionCardSettings($form['class_dynamic'], 'subclass', $patron_cards, 'single');
    }
    elseif ($selected_class === 'wizard') {
      $form['class_dynamic']['arcane_school_section'] = [
        '#markup' => '<div class="section-instructions arcane-school-section">'
          . '<h3>' . $this->t('Arcane School') . '</h3>'
          . '<p>' . $this->t('Choose your arcane school or become a universalist. This determines your school spell access and specialist prerequisites.') . '</p>'
          . '</div>',
      ];
      $school_options = [];
      $school_cards = [];
      foreach (CharacterManager::ARCANE_SCHOOLS as $school_id => $school) {
        $school_options[$school_id] = $school['name'];
        $school_cards[$school_id] = $this->buildOptionCardData(
          $school['description'] ?? '',
          [],
          [
            (string) $this->t('Benefit') => $school['benefit'] ?? '',
          ],
        );
      }
      $form['class_dynamic']['subclass'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Arcane School'),
        '#options' => $school_options,
        '#default_value' => $character_data['subclass'] ?? '',
        '#required' => TRUE,
        '#description' => $this->t('Your arcane school determines your school spell access and specialist feat prerequisites.'),
      ];
      $this->attachOptionCardSettings($form['class_dynamic'], 'subclass', $school_cards, 'single');

      $form['class_dynamic']['arcane_thesis_section'] = [
        '#markup' => '<div class="section-instructions arcane-thesis-section">'
          . '<h3>' . $this->t('Arcane Thesis') . '</h3>'
          . '<p>' . $this->t('Choose the thesis that defines your spellbook and preparation specialization.') . '</p>'
          . '</div>',
      ];
      $thesis_options = [];
      $thesis_cards = [];
      foreach ((CharacterManager::CLASSES['wizard']['arcane_thesis']['options'] ?? []) as $thesis_id => $thesis) {
        $thesis_options[$thesis_id] = $thesis['name'];
        $thesis_cards[$thesis_id] = $this->buildOptionCardData(
          $thesis['benefit'] ?? '',
        );
      }
      $form['class_dynamic']['arcane_thesis'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Arcane Thesis'),
        '#options' => $thesis_options,
        '#default_value' => $character_data['arcane_thesis'] ?? '',
        '#required' => TRUE,
        '#description' => $this->t('Your thesis determines wizard feat prerequisites such as Spell Combination.'),
      ];
      $this->attachOptionCardSettings($form['class_dynamic'], 'arcane_thesis', $thesis_cards, 'single');
    }

    // --- Spell Selection for ALL spellcasting classes ---
    // For flexible-tradition casters, resolve subclass from form_state
    // first, then fall back to character_data saved values.
    $subclass_value = (string) ($form_state->getValue('subclass') ?: ($character_data['subclass'] ?? ''));
    $resolve_data = array_merge($character_data, $subclass_value ? ['subclass' => $subclass_value] : []);
    $tradition = $this->characterManager->resolveClassTradition($selected_class, $resolve_data);
    $spell_slots = CharacterManager::CASTER_SPELL_SLOTS[$selected_class] ?? NULL;

    if ($tradition && $spell_slots) {
      $tradition_label = ucfirst($tradition);
      $class_label = ucfirst($selected_class);
      $num_cantrips = $spell_slots['cantrips'];
      $num_first = $spell_slots['first'];
      $spellbook_size = $spell_slots['spellbook'] ?? NULL;

      if ($selected_class === 'wizard') {
        $spells_intro = $this->t('As a Wizard, you begin with knowledge of arcane magic. Choose your starting cantrips and spells for your spellbook.');
        $first_label = $this->t('Choose up to @count First Level Spells (Spellbook)', ['@count' => $spellbook_size ?? $num_first]);
        $first_help = $this->t('These spells are added to your spellbook. You can prepare @slots spells per day at level 1. Choose versatile spells.', ['@slots' => $num_first]);
        $max_first = $spellbook_size ?? $num_first;
      }
      else {
        $spells_intro = $this->t('As a @class, you tap into the @tradition spell tradition. Choose your starting cantrips and 1st-level spells.', [
          '@class' => $class_label,
          '@tradition' => $tradition_label,
        ]);
        $first_label = $this->t('Choose @count First Level Spells', ['@count' => $num_first]);
        $first_help = $this->t('You can cast @count 1st-level @tradition spells per day at level 1.', [
          '@count' => $num_first,
          '@tradition' => $tradition_label,
        ]);
        $max_first = $num_first;
      }

      $form['class_dynamic']['spells_section'] = [
        '#markup' => '<div class="section-instructions spells-section">'
          . '<h3>' . $this->t('Spells (@tradition)', ['@tradition' => $tradition_label]) . '</h3>'
          . '<p>' . $spells_intro . '</p>'
          . '</div>',
      ];

      // Store tradition and limits for validation.
      $form_state->set('spell_tradition', $tradition);
      $form_state->set('cantrip_limit', $num_cantrips);
      $form_state->set('first_spell_limit', $max_first);

      // Expose limits to JS for live checkbox guardrails.
      // Attached to class_dynamic (not $form) so settings travel with AJAX.
      $form['class_dynamic']['#attached']['drupalSettings']['characterStep4'] = [
        'cantripLimit' => $num_cantrips,
        'firstSpellLimit' => $max_first,
      ];

      // --- Cantrip Selection ---
      $cantrips = $this->characterManager->getSpellsByTradition($tradition, 0);
      $cantrip_options = [];
      $cantrip_cards = [];
      foreach ($cantrips as $cantrip) {
        $cantrip_options[$cantrip['id']] = $cantrip['name'];
        $tags = ['Cantrip'];
        if (!empty($cantrip['school'])) {
          $tags[] = ucfirst((string) $cantrip['school']);
        }
        $facts = $this->extractSpellFacts($cantrip);
        $cantrip_cards[$cantrip['id']] = $this->buildOptionCardData(
          ($cantrip['description_source'] ?? '') === 'description' ? ($cantrip['description'] ?? '') : '',
          $tags,
          $facts,
        );
      }

      $form['class_dynamic']['cantrips_help'] = [
        '#markup' => '<div class="spell-help"><strong>' . $this->t('Cantrips (Select @count)', ['@count' => $num_cantrips]) . '</strong><br>'
          . $this->t('Cantrips are spells you can cast at will. They heighten automatically to half your level.')
          . '</div>',
      ];

      $form['class_dynamic']['cantrips'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Choose @count Cantrips', ['@count' => $num_cantrips]),
        '#options' => $cantrip_options,
        '#default_value' => $character_data['cantrips'] ?? [],
        '#required' => FALSE,
        '#description' => $this->t('Select exactly @count cantrips from the @tradition spell list.', ['@count' => $num_cantrips, '@tradition' => $tradition_label]),
      ];
      $this->attachOptionCardSettings($form['class_dynamic'], 'cantrips', $cantrip_cards, 'multiple');

      // --- 1st Level Spell Selection ---
      $first_level_spells = $this->characterManager->getSpellsByTradition($tradition, 1);
      $spell_options = [];
      $spell_cards = [];
      foreach ($first_level_spells as $spell) {
        $spell_options[$spell['id']] = $spell['name'];
        $tags = ['1st-level spell'];
        if (!empty($spell['school'])) {
          $tags[] = ucfirst((string) $spell['school']);
        }
        $facts = $this->extractSpellFacts($spell);
        $spell_cards[$spell['id']] = $this->buildOptionCardData(
          ($spell['description_source'] ?? '') === 'description' ? ($spell['description'] ?? '') : '',
          $tags,
          $facts,
        );
      }

      $form['class_dynamic']['spells_help'] = [
        '#markup' => '<div class="spell-help"><strong>' . $first_label . '</strong><br>'
          . $first_help
          . '</div>',
      ];

      $form['class_dynamic']['spells_first'] = [
        '#type' => 'checkboxes',
        '#title' => $first_label,
        '#options' => $spell_options,
        '#default_value' => $character_data['spells_first'] ?? [],
        '#required' => FALSE,
        '#description' => $this->t('Select your starting 1st-level @tradition spells.', ['@tradition' => $tradition_label]),
      ];
      $this->attachOptionCardSettings($form['class_dynamic'], 'spells_first', $spell_cards, 'multiple');

      if (($character_data['ancestry_feat'] ?? '') === 'adapted-cantrip') {
        $this->buildAdaptedCantripSelectionSection($form, $form_state, $character_data, $tradition);
      }

      $selected_class_feat = (string) ($form_state->getValue('class_feat') ?: ($character_data['class_feat'] ?? ''));
      $selected_bonus_feat = (string) $form_state->getValue(
        ['feat_selections', 'natural-ambition', 'bonus_class_feat'],
        $character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''
      );
      if ($selected_class_feat === 'staff-nexus' || $selected_bonus_feat === 'staff-nexus') {
        $this->buildStaffNexusSelectionSection($form['class_dynamic'], $form_state, $character_data, $tradition);
      }
    }
    elseif (array_key_exists($selected_class, CharacterManager::CLASS_TRADITIONS) && !$tradition) {
      // Caster class but tradition not yet resolved (sorcerer/witch without subclass)
      $form['class_dynamic']['spells_pending'] = [
        '#markup' => '<div class="section-instructions spells-pending">'
          . '<p><em>' . $this->t('Select your @thing above to unlock spell selection.', [
            '@thing' => $selected_class === 'sorcerer' ? 'bloodline' : 'patron',
          ]) . '</em></p>'
          . '</div>',
      ];
    }

    $this->applyStep4TabbedLayout($form['class_dynamic'], $form_state, $selected_class);
  }

  /**
   * Applies a tabbed layout to the dynamic Step 4 class-selection sections.
   */
  private function applyStep4TabbedLayout(array &$container, FormStateInterface $form_state, string $selected_class): void {
    $tabs = [];

    $this->registerStep4Panel($container, $tabs, 'key-ability', $this->t('Key Ability'), [
      'class_key_ability_help',
      'class_key_ability_readonly',
    ], [
      'class_key_ability',
      'class_key_ability_readonly',
    ]);

    $this->registerStep4Panel($container, $tabs, 'subclass', $this->getStep4SubclassTabLabel($selected_class), [
      'bloodline_section',
      'patron_section',
      'arcane_school_section',
    ], [
      'subclass',
    ]);

    $this->registerStep4Panel($container, $tabs, 'arcane-thesis', $this->t('Arcane Thesis'), [
      'arcane_thesis_section',
    ], [
      'arcane_thesis',
    ]);

    $this->registerStep4Panel($container, $tabs, 'class-feat', $this->t('Class Feat'), [
      'class_feat_section',
    ], [
      'class_feat',
    ]);

    $this->registerStep4Panel($container, $tabs, 'cantrips', $this->t('Cantrips'), [
      'spells_section',
    ], [
      'cantrips',
    ]);

    $this->registerStep4Panel($container, $tabs, 'first-level-spells', $this->t('1st-Level Spells'), [
      'spells_help',
    ], [
      'spells_first',
    ]);

    $this->registerStep4Panel($container, $tabs, 'spellcasting', $this->t('Spellcasting'), [
      'spells_pending',
    ], [
      'spells_pending',
    ]);

    if (isset($container['feat_selections']) && is_array($container['feat_selections'])) {
      foreach (array_keys($container['feat_selections']) as $selection_key) {
        if (str_starts_with((string) $selection_key, '#') || !is_array($container['feat_selections'][$selection_key])) {
          continue;
        }
        $tab_id = 'feat-' . str_replace('_', '-', Html::getId((string) $selection_key));
        $tabs[$tab_id] = [
          'label' => $this->getStep4FeatSelectionTabLabel((string) $selection_key),
          'panel_id' => 'step4-tab-panel-' . $tab_id,
          'selection_key' => (string) $selection_key,
        ];
      }
    }

    if (count($tabs) < 2) {
      return;
    }

    $active_tab = trim((string) $form_state->getValue('step4_active_tab', ''));
    if ($active_tab === '' || !isset($tabs[$active_tab])) {
      $active_tab = array_key_first($tabs);
    }

    foreach ($tabs as $tab_id => $tab) {
      if (!str_starts_with($tab_id, 'feat-')) {
        continue;
      }
      $selection_key = $tab['selection_key'] ?? NULL;
      if (!isset($container['feat_selections'][$selection_key])) {
        continue;
      }
      $container['feat_selections'][$selection_key]['#attributes']['id'] = $tab['panel_id'];
      $container['feat_selections'][$selection_key]['#attributes']['role'] = 'tabpanel';
      $container['feat_selections'][$selection_key]['#attributes']['aria-labelledby'] = 'step4-tab-' . $tab_id;
      $container['feat_selections'][$selection_key]['#attributes']['data-step4-tab-panel'] = $tab_id;
      $container['feat_selections'][$selection_key]['#attributes']['class'][] = 'step4-class-tabs__panel';
      if ($tab_id !== $active_tab) {
        $container['feat_selections'][$selection_key]['#attributes']['hidden'] = 'hidden';
      }
    }

    $container['step4_active_tab'] = [
      '#type' => 'hidden',
      '#default_value' => $active_tab,
      '#attributes' => ['data-step4-active-tab-input' => 'true'],
    ];

    $tab_navigation = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['step4-class-tabs__nav'],
        'role' => 'tablist',
        'aria-label' => (string) $this->t('Step 4 class selection sections'),
      ],
      '#weight' => -100,
    ];

    foreach ($tabs as $tab_id => $tab) {
      $tab_navigation[$tab_id] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => (string) $tab['label'],
        '#attributes' => [
          'id' => 'step4-tab-' . $tab_id,
          'class' => array_filter([
            'step4-class-tabs__tab',
            $tab_id === $active_tab ? 'is-active' : NULL,
          ]),
          'type' => 'button',
          'role' => 'tab',
          'aria-selected' => $tab_id === $active_tab ? 'true' : 'false',
          'aria-controls' => $tab['panel_id'],
          'data-step4-tab' => $tab_id,
        ],
      ];
    }

    $rebuilt = [];
    foreach ($container as $key => $value) {
      if (str_starts_with((string) $key, '#')) {
        $rebuilt[$key] = $value;
      }
    }
    $rebuilt['step4_active_tab'] = $container['step4_active_tab'];
    $rebuilt['step4_tabs_nav'] = $tab_navigation;
    foreach ($container as $key => $value) {
      if (str_starts_with((string) $key, '#') || in_array($key, ['step4_active_tab', 'step4_tabs_nav'], TRUE)) {
        continue;
      }
      $rebuilt[$key] = $value;
    }
    $container = $rebuilt;
  }

  /**
   * Registers a top-level Step 4 panel by wrapping existing sibling elements.
   */
  private function registerStep4Panel(array &$container, array &$tabs, string $tab_id, string|\Stringable $label, array $start_candidates, array $end_candidates): void {
    $start_key = NULL;
    foreach ($start_candidates as $candidate) {
      if (isset($container[$candidate])) {
        $start_key = $candidate;
        break;
      }
    }

    $end_key = NULL;
    foreach ($end_candidates as $candidate) {
      if (isset($container[$candidate])) {
        $end_key = $candidate;
        break;
      }
    }

    if ($start_key === NULL || $end_key === NULL) {
      return;
    }

    $panel_id = 'step4-tab-panel-' . $tab_id;
    $container[$start_key]['#prefix'] = ($container[$start_key]['#prefix'] ?? '') . '<div id="' . $panel_id . '" class="step4-class-tabs__panel" role="tabpanel" aria-labelledby="step4-tab-' . $tab_id . '" data-step4-tab-panel="' . $tab_id . '">';
    $container[$end_key]['#suffix'] = ($container[$end_key]['#suffix'] ?? '') . '</div>';
    $tabs[$tab_id] = [
      'label' => (string) $label,
      'panel_id' => $panel_id,
    ];
  }

  /**
   * Returns the most appropriate subclass tab label for Step 4.
   */
  private function getStep4SubclassTabLabel(string $selected_class): string {
    return match ($selected_class) {
      'sorcerer' => (string) $this->t('Bloodline'),
      'witch' => (string) $this->t('Patron'),
      'wizard' => (string) $this->t('Arcane School'),
      default => (string) $this->t('Subclass'),
    };
  }

  /**
   * Returns the tab label for a Step 4 feat selection panel.
   */
  private function getStep4FeatSelectionTabLabel(string $selection_key): string {
    return match ($selection_key) {
      'adapted-cantrip' => (string) $this->t('Adapted Cantrip'),
      'natural-ambition' => (string) $this->t('Natural Ambition'),
      'animal-companion', 'animal-companion-druid' => (string) $this->t('Animal Companion'),
      'monster-hunter' => (string) $this->t('Monster Hunter'),
      'eldritch-trickster-racket' => (string) $this->t('Eldritch Trickster'),
      'mastermind-racket' => (string) $this->t('Mastermind'),
      'staff-nexus' => (string) $this->t('Staff Nexus'),
      default => ucwords(str_replace(['-', '_'], ' ', $selection_key)),
    };
  }

  /**
   * Builds Step 5 fields.
   */
  private function buildStep5Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    // Step 5: Free Ability Boosts (Pathbuilder-style interactive selection)
    // Calculate current scores from ancestry + background + class
    $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
    
    $abilities_data = $this->buildInteractiveAbilityData($calculation, $character_data['free_boosts'] ?? []);

    $form['abilities_help'] = [
      '#markup' => '<div class="section-instructions">'
        . '<p><strong>' . $this->t('Choose 4 abilities to boost') . '</strong></p>'
        . '<p>' . $this->t('You have 4 free ability boosts to assign. Each boost adds +2 to an ability score (or +1 if the score is already 18 or higher). You cannot boost the same ability twice in this step.') . '</p>'
        . '<p class="tip">' . $this->t('💡 Tip: Consider boosting your class\'s key ability and abilities that complement your playstyle. Most characters benefit from having at least one high ability score (16-18).') . '</p>'
        . '</div>',
    ];

    // Render interactive ability widget using Twig template
    $form['ability_selector'] = [
      '#theme' => 'character_ability_widget',
      '#abilities' => $abilities_data,
      '#mode' => 'interactive',
      '#show_sources' => TRUE,
      '#boosts_remaining' => 4 - count($character_data['free_boosts'] ?? []),
      '#boosts_total' => 4,
      '#attributes' => [
        'data-step' => 'free',
        'data-max-boosts' => 4,
        'data-character-data' => json_encode($character_data),
      ],
    ];

    // Hidden field to store selected boosts
    $form['free_boosts'] = [
      '#type' => 'hidden',
      '#default_value' => json_encode($character_data['free_boosts'] ?? []),
      '#attributes' => ['id' => 'free-boosts-field'],
    ];
  }

  /**
   * Builds Step 6 fields.
   */
  private function buildStep6Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
    $this->attachAbilityPreview($form, $character_data, 'Final ability scores');

    // Skill Training Selection
    $selected_class = $character_data['class'] ?? '';
    if (!empty($selected_class)) {
      $class_data = CharacterManager::CLASSES[$selected_class] ?? NULL;
      if ($class_data) {
        $trained_skills = $class_data['trained_skills'] ?? 3;
        
        // Calculate Intelligence modifier for bonus skills
        $int_modifier = $calculation['modifiers']['intelligence'] ?? 0;
        $total_skill_picks = max(1, $trained_skills + $int_modifier);

        $form['skills_section'] = [
          '#markup' => '<div class="section-instructions skills-section">'
            . '<h3>' . $this->t('Skill Training') . '</h3>'
            . '<p>' . $this->t('Choose @count skills to be trained in.', ['@count' => $total_skill_picks])
            . ' <em>' . $this->t('(@base from class + @bonus from Intelligence)', ['@base' => $trained_skills, '@bonus' => $int_modifier]) . '</em></p>'
            . '<p class="help-text">' . $this->t('Being trained in a skill gives you a +2 proficiency bonus. Choose skills that complement your class and planned activities.') . '</p>'
            . '</div>',
        ];

        $all_skills = $this->getSkillTrainingOptions();

        $form['trained_skills'] = [
          '#type' => 'checkboxes',
          '#title' => $this->t('Select Skills'),
          '#options' => $all_skills,
          '#default_value' => $character_data['trained_skills'] ?? [],
          '#description' => $this->t('Select exactly @count skill(s). You can gain additional skills from feats and ancestry features.', ['@count' => $total_skill_picks]),
          '#required' => FALSE,
        ];

        // Stash limit for validateForm() and expose to JS.
        $form_state->set('total_skill_picks', $total_skill_picks);
        $form['#attached']['drupalSettings']['characterStep6'] = [
          'requiredSkills' => $total_skill_picks,
        ];
      }
    }

    $form['alignment'] = [
      '#type' => 'select',
      '#title' => $this->t('Legacy Alignment'),
      '#required' => TRUE,
      '#options' => $this->getAlignmentOptions(),
      '#default_value' => $character_data['alignment'] ?? '',
      '#description' => $this->t('This character\'s moral and ethical compass will guide roleplay decisions across the entire span of their campaign life.'),
    ];
    $this->applySchemaValidationAttributes($form['alignment'], $schema_fields, 'alignment');
    $form['deity'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Deity or Guiding Belief (Optional)'),
      '#default_value' => $character_data['deity'] ?? '',
      '#placeholder' => $this->t('e.g., Iomedae, The Old Gods, Ancestor Oath, Unaligned'),
      '#description' => $this->t('Optional: A spiritual patron or philosophy that will anchor your character\'s identity and roleplay flavor across all campaigns.'),
      '#ajax' => [
        'callback' => '::updateDeityDependentOptions',
        'wrapper' => 'deity-dependent-wrapper',
        'event' => 'change',
      ],
    ];
    $this->applySchemaValidationAttributes($form['deity'], $schema_fields, 'deity');

    $form['deity_dependent'] = [
      '#type' => 'container',
      '#tree' => TRUE,
      '#attributes' => ['id' => 'deity-dependent-wrapper'],
    ];

    $selected_class_feat = trim((string) ($character_data['class_feat'] ?? ''));
    $selected_bonus_feat = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
    if ($selected_class_feat === 'domain-initiate' || $selected_bonus_feat === 'domain-initiate') {
      $selected_deity = trim((string) $form_state->getValue('deity', $character_data['deity'] ?? ''));
      $this->buildDomainInitiateSelectionSection($form['deity_dependent'], $form_state, $character_data, $selected_deity);
    }

    // --- General Feat Selection ---
    // Every PF2e character gets one 1st-level general feat at character creation.
    $form['general_feat_dynamic'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'general-feat-dynamic-wrapper'],
    ];
    $form['general_feat_dynamic']['general_feat_section'] = [
      '#markup' => '<div class="section-instructions general-feat-section">'
        . '<h3>' . $this->t('General Feat') . '</h3>'
        . '<p>' . $this->t('Every character receives one 1st-level general feat. These represent broad talents not tied to your class or ancestry.') . '</p>'
        . '</div>',
    ];

    $general_feat_options = [];
    $general_feat_cards = [];
    foreach (CharacterManager::getGeneralFeats() as $feat) {
      $general_feat_options[$feat['id']] = $feat['name'];
      $general_feat_cards[$feat['id']] = $this->buildOptionCardData(
        $feat['benefit'] ?? '',
        $feat['traits'] ?? [],
        [
          (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
        ],
      );
    }

    $selected_general_feat = (string) ($form_state->getValue('general_feat') ?: ($character_data['general_feat'] ?? ''));
    $form['general_feat_dynamic']['general_feat'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select General Feat'),
      '#options' => $general_feat_options,
      '#default_value' => $selected_general_feat,
      '#required' => FALSE,
      '#description' => $this->t('Popular choices: Toughness (more HP), Fleet (faster movement), Incredible Initiative (+2 to initiative), Shield Block (damage reduction).'),
      '#ajax' => [
        'callback' => '::updateGeneralFeatOptions',
        'wrapper' => 'general-feat-dynamic-wrapper',
        'event' => 'change',
      ],
    ];
    $this->attachOptionCardSettings($form['general_feat_dynamic'], 'general_feat', $general_feat_cards, 'single');

    if ($selected_general_feat === 'specialty-crafting') {
      $this->buildSpecialtyCraftingSelectionSection($form['general_feat_dynamic'], $form_state, $character_data);
    }
    elseif ($selected_general_feat === 'virtuosic-performer') {
      $this->buildVirtuosicPerformerSelectionSection($form['general_feat_dynamic'], $form_state, $character_data);
    }
    elseif ($selected_general_feat === 'canny-acumen') {
      $this->buildCannyAcumenSelectionSection($form['general_feat_dynamic'], $form_state, $character_data);
    }
    elseif ($selected_general_feat === 'adopted-ancestry') {
      $this->buildAdoptedAncestrySelectionSection($form['general_feat_dynamic'], $form_state, $character_data);
    }
    elseif ($selected_general_feat === 'weapon-proficiency') {
      $this->buildWeaponProficiencySelectionSection($form['general_feat_dynamic'], $form_state, $character_data);
    }
  }

  /**
   * Builds Step 7 fields.
   */
  private function buildStep7Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->attachAbilityPreview($form, $character_data, "Your character's abilities", FALSE);

    $catalog = $this->getEquipmentCatalog();
    $catalog_by_id = [];
    $options = [];

    foreach ($catalog as $category => $items) {
      foreach ($items as $item) {
        $catalog_by_id[$item['id']] = $item;
        $options[$item['id']] = $item['name'] . ' (' . (float) $item['cost'] . ' gp)';
      }
    }

    $selected_ids = [];
    foreach (($character_data['inventory']['carried'] ?? []) as $carried_item) {
      if (!empty($carried_item['id'])) {
        $selected_ids[] = $carried_item['id'];
      }
    }
    foreach (($character_data['gm_equipment_ids'] ?? []) as $item_id) {
      $item_id = trim((string) $item_id);
      if ($item_id !== '') {
        $selected_ids[] = $item_id;
      }
    }
    $selected_ids = array_values(array_unique($selected_ids));
    $category_ids = [
      'weapons' => array_column($catalog['weapons'] ?? [], 'id'),
      'armor' => array_column($catalog['armor'] ?? [], 'id'),
      'gear' => array_column($catalog['gear'] ?? [], 'id'),
    ];

    $selected_cost = 0.0;
    foreach ($selected_ids as $item_id) {
      if (isset($catalog_by_id[$item_id])) {
        $selected_cost += (float) $catalog_by_id[$item_id]['cost'];
      }
    }

    $remaining_gold = max(0, 15 - $selected_cost);

    $form['equipment_intro'] = [
      '#markup' => '<div class="section-instructions equipment-intro">'
        . '<h3>' . $this->t('Starting Equipment') . '</h3>'
        . '<p>' . $this->t('Assemble your starting loadout with up to 15 gp. Choose wisely - these items will be your tools for survival in early adventures.') . '</p>'
        . '</div>',
    ];

    $form['starting_gold'] = [
      '#markup' => '<div class="gold-display" style="background: #fff3cd; border: 2px solid #ffc107; padding: 15px; border-radius: 8px; margin: 20px 0;">'
        . '<div style="font-size: 24px; font-weight: bold; color: #856404;">'
        . '<span class="gold-icon" style="font-size: 32px;">🪙</span> '
        . $this->t('Budget: @gold gp', ['@gold' => 15])
        . '</div>'
        . '<div style="font-size: 16px; margin-top: 10px; color: #856404;">'
        . $this->t('Spent: <strong>@cost gp</strong> • Remaining: <strong style="color: @color;">@remaining gp</strong>', [
          '@cost' => number_format($selected_cost, 1),
          '@remaining' => number_format($remaining_gold, 1),
          '@color' => $remaining_gold > 0 ? '#28a745' : '#dc3545',
        ])
        . '</div>'
        . '</div>',
    ];

    // Organize equipment by category
    $form['equipment_weapons'] = [
      '#type' => 'details',
      '#title' => $this->t('⚔️ Weapons'),
      '#open' => TRUE,
    ];

    $form['equipment_armor'] = [
      '#type' => 'details',
      '#title' => $this->t('🛡️ Armor & Shields'),
      '#open' => TRUE,
    ];

    $form['equipment_gear'] = [
      '#type' => 'details',
      '#title' => $this->t('🎒 Adventuring Gear'),
      '#open' => TRUE,
    ];

    // Build categorized options
    $weapons_options = [];
    $armor_options = [];
    $gear_options = [];
    $weapon_cards = [];
    $armor_cards = [];
    $gear_cards = [];

    foreach ($catalog as $category => $items) {
      foreach ($items as $item) {
        $catalog_by_id[$item['id']] = $item;
        $item_label = $item['name'];
        $item_card = $this->buildEquipmentOptionCardData($item, $category);

        if ($category === 'weapons') {
          $weapons_options[$item['id']] = $item_label;
          $weapon_cards[$item['id']] = $item_card;
        }
        elseif ($category === 'armor') {
          $armor_options[$item['id']] = $item_label;
          $armor_cards[$item['id']] = $item_card;
        }
        else {
          $gear_options[$item['id']] = $item_label;
          $gear_cards[$item['id']] = $item_card;
        }
      }
    }

    $form['equipment_weapons']['weapons'] = [
      '#type' => 'checkboxes',
      '#options' => $weapons_options,
      '#default_value' => array_values(array_filter($selected_ids, fn($id) => in_array($id, $category_ids['weapons'], TRUE))),
      '#description' => $this->t('Select weapons for combat. Consider your class proficiencies.'),
    ];

    $form['equipment_armor']['armor'] = [
      '#type' => 'checkboxes',
      '#options' => $armor_options,
      '#default_value' => array_values(array_filter($selected_ids, fn($id) => in_array($id, $category_ids['armor'], TRUE))),
      '#description' => $this->t('Choose armor and shields for protection. Heavy armor may slow you down.'),
    ];

    $form['equipment_gear']['gear'] = [
      '#type' => 'checkboxes',
      '#options' => $gear_options,
      '#default_value' => array_values(array_filter($selected_ids, fn($id) => in_array($id, $category_ids['gear'], TRUE))),
      '#description' => $this->t('Essential adventuring supplies: rope, torches, rations, and tools.'),
    ];
    $this->attachOptionCardSettings($form['equipment_weapons'], 'weapons', $weapon_cards, 'multiple');
    $this->attachOptionCardSettings($form['equipment_armor'], 'armor', $armor_cards, 'multiple');
    $this->attachOptionCardSettings($form['equipment_gear'], 'gear', $gear_cards, 'multiple');

    // Pass catalog costs to JS so it doesn't have to regex-parse label text.
    $js_catalog = [];
    foreach ($catalog_by_id as $id => $item) {
      $js_catalog[$id] = [
        'cost' => (float) $item['cost'],
        'name' => $item['name'],
      ];
    }
    $form['#attached']['drupalSettings']['characterStep7'] = [
      'budget' => 15,
      'catalog' => $js_catalog,
    ];

    $form['equipment_help'] = [
      '#markup' => '<div class="equipment-tips" style="background: #e7f3ff; border-left: 4px solid #2196F3; padding: 15px; margin: 20px 0;">'
        . '<h4 style="margin-top: 0; color: #1976D2;">💡 ' . $this->t('Equipment Tips') . '</h4>'
        . '<ul style="margin-bottom: 0;">'
        . '<li>' . $this->t('<strong>Weapons:</strong> Choose at least one weapon your class is proficient with.') . '</li>'
        . '<li>' . $this->t('<strong>Armor:</strong> Wizards and sorcerers typically wear no armor. Fighters can wear heavy armor.') . '</li>'
        . '<li>' . $this->t('<strong>Essentials:</strong> Don\'t forget rope, torches, and a backpack!') . '</li>'
        . '<li>' . $this->t('<strong>Gold:</strong> Unspent gold carries over to your starting funds.') . '</li>'
        . '</ul>'
        . '</div>',
    ];
  }

  /**
   * Builds Step 8 fields.
   */
  private function buildStep8Fields(array &$form, FormStateInterface $form_state, array $character_data, array $schema_fields): void {
    $this->attachAbilityPreview($form, $character_data, 'Final ability scores - Review your character');
    $portrait_availability = $this->getPortraitGenerationAvailability();
    $portrait_available = $portrait_availability['available'];

    $form['portrait_generation'] = [
      '#type' => 'details',
      '#title' => $this->t('Portrait Generation'),
      '#open' => TRUE,
    ];
    $form['portrait_generation']['portrait_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate a character portrait'),
      '#default_value' => $portrait_available ? (int) ($character_data['portrait_generate'] ?? 1) : 0,
      '#parents' => ['portrait_generate'],
      '#description' => $portrait_availability['description'],
      '#disabled' => !$portrait_available,
    ];
    $form['portrait_generation']['portrait_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Portrait prompt (optional)'),
      '#default_value' => $character_data['portrait_prompt'] ?? '',
      '#rows' => 3,
      '#maxlength' => 500,
      '#parents' => ['portrait_prompt'],
      '#description' => $portrait_available
        ? $this->t('Add extra visual direction. Character attributes will be injected automatically.')
        : $this->t('Portrait prompts are unavailable until a live image provider is configured.'),
      '#disabled' => !$portrait_available,
    ];
    $form['age'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Age / Life Stage'),
      '#default_value' => $character_data['age'] ?? '',
      '#maxlength' => 10,
      '#placeholder' => $this->t('e.g., 28, middle-aged, elderly'),
      '#description' => $this->t('Optional: Your character\'s age or life stage informs their experience and how they might view future growth and eventual retirement.'),
    ];
    $form['gender'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Gender / Pronouns'),
      '#default_value' => $character_data['gender'] ?? '',
      '#maxlength' => 50,
      '#placeholder' => $this->t('e.g., she/her, he/him, they/them'),
      '#description' => $this->t('Optional: How you present your character at the table. Respected by all players for long-term roleplay and respect.'),
    ];
    $form['appearance'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Appearance & Presence'),
      '#default_value' => $character_data['appearance'] ?? '',
      '#rows' => 3,
      '#placeholder' => $this->t('What distinguishing features, scars, or style will make this character memorable?'),
      '#description' => $this->t('Tell the table what they see: build, distinctive features, clothing style. This is what other players will picture across every campaign session.'),
    ];
    $form['personality'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Personality & Table Voice'),
      '#default_value' => $character_data['personality'] ?? '',
      '#rows' => 3,
      '#placeholder' => $this->t('How does this character speak and act? What are their quirks, habits, and mannerisms?'),
      '#description' => $this->t('Define the emotional tone and voice you\'ll bring to roleplay. Think about personality traits you can embody consistently over many sessions.'),
    ];
    $form['roleplay_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Roleplay Style'),
      '#default_value' => $character_data['roleplay_style'] ?? 'balanced',
      '#options' => [
        'talker' => $this->t('Talker — This character leads with words. They negotiate, interrogate, and narrate their actions aloud. Expect them to speak on most turns.'),
        'balanced' => $this->t('Balanced — This character mixes dialogue and action naturally, reading the room to decide when to speak and when to act.'),
        'doer' => $this->t('Doer — This character lets actions speak louder than words. They act first, talk later. Expect short, purposeful speech.'),
        'observer' => $this->t('Observer — This character watches and listens. They speak rarely but deliberately, and are more likely to act on gathered information.'),
      ],
      '#description' => $this->t('How does this character participate on their turn — with words or deeds? This guides how the GM narrates their actions and how often they speak vs. act in party play.'),
    ];
    $form['backstory'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Backstory & Legacy Goal'),
      '#default_value' => $character_data['backstory'] ?? '',
      '#rows' => 5,
      '#placeholder' => $this->t('Where did this character come from? What drives them? What is their ultimate goal (which could be a noble end like retirement or legendary status)?'),
      '#description' => $this->t('Frame your character\'s story with an arc in mind: how they begin, what motivates them, and an end goal they might work toward across years of campaigning. This becomes your character\'s lasting legacy.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $step = (int) $form_state->get('step');

    switch ($step) {
      case 1:
        if (trim((string) $form_state->getValue('name', '')) === '') {
          $form_state->setErrorByName('name', $this->t('Character name is required.'));
        }
        break;

      case 2:
        if (trim((string) $form_state->getValue('ancestry', '')) === '') {
          $form_state->setErrorByName('ancestry', $this->t('Ancestry selection is required.'));
        }
        // Validate heritage if options exist for the selected ancestry.
        $ancestry_val = trim((string) $form_state->getValue('ancestry', ''));
        if ($ancestry_val !== '') {
          $heritage_opts = $this->getHeritageOptions($ancestry_val);
          $submitted_heritage = trim((string) $form_state->getValue('heritage', ''));
          if (count($heritage_opts) > 1 && $submitted_heritage === '') {
            $form_state->setErrorByName('heritage', $this->t('Heritage selection is required.'));
          }
          elseif ($submitted_heritage !== '' && !array_key_exists($submitted_heritage, $heritage_opts)) {
            $form_state->setErrorByName('heritage', $this->t('Invalid heritage for selected ancestry.'));
          }
          // Validate ancestry feat (enforced here instead of #required on the
          // radios element to avoid browser :invalid pre-styling on page load).
          $ancestry_name_val = $this->resolveAncestryName($ancestry_val);
          $feats_for_ancestry = CharacterManager::getAncestryFeats($ancestry_name_val);
          if (!empty($feats_for_ancestry) && trim((string) $form_state->getValue('ancestry_feat', '')) === '') {
            $form_state->setErrorByName('ancestry_feat', $this->t('Ancestry feat selection is required.'));
          }

          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'first-world-magic') {
            $this->validateFirstWorldMagicSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'otherworldly-magic') {
            $this->validateOtherworldlyMagicSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'general-training') {
            $this->validateGeneralTrainingSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'elf-atavism') {
            $this->validateElfAtavismSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'multitalented') {
            $this->validateMultitalentedSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'mixed-heritage-adaptability') {
            $this->validateMixedHeritageAdaptabilitySelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'orc-atavism') {
            $this->validateOrcAtavismSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'draconic-ties') {
            $this->validateDraconicTiesSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'natural-skill') {
            $this->validateNaturalSkillSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'unconventional-weaponry') {
            $this->validateUnconventionalWeaponrySelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'vengeful-hatred') {
            $this->validateVengefulHatredSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'ancestral-longevity') {
            $this->validateAncestralLongevitySelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'gnome-obsession') {
            $this->validateGnomeObsessionSelection($form_state);
          }
          if (trim((string) $form_state->getValue('ancestry_feat', '')) === 'natural-performer') {
            $this->validateNaturalPerformerSelection($form_state);
          }

          $ancestry_boost_config = CharacterManager::getAncestryBoostConfig($ancestry_val, $submitted_heritage);
          $ancestry_boosts = self::normalizeList($form_state->getValue('ancestry_boosts', []));
          $free_boost_count = (int) ($ancestry_boost_config['free_boosts'] ?? 0);
          $fixed_boosts = array_values(array_filter(array_map(
            fn(string $boost): ?string => $this->abilityScoreTracker->normalizeAbilityKey($boost),
            $ancestry_boost_config['fixed_boosts'] ?? []
          )));

          if ($free_boost_count > 0) {
            if (count($ancestry_boosts) !== $free_boost_count) {
              $form_state->setErrorByName('ancestry_boosts', $this->t('Select exactly @count free ancestry boost(s).', ['@count' => $free_boost_count]));
            }
            elseif (count($ancestry_boosts) !== count(array_unique($ancestry_boosts))) {
              $form_state->setErrorByName('ancestry_boosts', $this->t('Ability boost selections must be unique.'));
            }
            else {
              foreach ($ancestry_boosts as $boost) {
                $normalized_boost = $this->abilityScoreTracker->normalizeAbilityKey((string) $boost);
                if ($normalized_boost !== NULL && in_array($normalized_boost, $fixed_boosts, TRUE)) {
                  $form_state->setErrorByName('ancestry_boosts', $this->t('Cannot apply a free ancestry boost to an ability that already receives an ancestry boost.'));
                  break;
                }
              }
            }
          }
        }
        break;

      case 3:
        $bg_val = trim((string) $form_state->getValue('background', ''));
        if ($bg_val === '') {
          $form_state->setErrorByName('background', $this->t('Background is required.'));
        }
        else {
          $bg_data = CharacterManager::BACKGROUNDS[$bg_val] ?? NULL;
          if ($bg_data === NULL) {
            $form_state->setErrorByName('background', $this->t('Invalid background selection.'));
          }
          else {
            $background_boosts = self::normalizeList($form_state->getValue('background_boosts', []));
            if (isset($bg_data['fixed_boost'])) {
              // New model: 1 free boost required (fixed is auto-applied).
              if (count($background_boosts) !== 1) {
                $form_state->setErrorByName('background_boosts', $this->t('Select exactly 1 free ability boost for your background.'));
              }
              elseif (strtolower(trim($background_boosts[0])) === strtolower(trim($bg_data['fixed_boost']))) {
                $form_state->setErrorByName('background_boosts', $this->t('Cannot apply two boosts to the same ability score from a single background.'));
              }
            }
            else {
              // Legacy model: 2 free boosts.
              if (count($background_boosts) !== 2) {
                $form_state->setErrorByName('background_boosts', $this->t('Select exactly 2 background boosts.'));
              }
              elseif (count(array_unique($background_boosts)) !== 2) {
                $form_state->setErrorByName('background_boosts', $this->t('Cannot apply two boosts to the same ability score from a single background.'));
              }
            }
          }
        }
        break;

      case 4:
        if (trim((string) $form_state->getValue('class', '')) === '') {
          $form_state->setErrorByName('class', $this->t('Class is required.'));
        }

        // Validate key ability choice for classes with multiple options.
        $class_val_for_ka = trim((string) $form_state->getValue('class', ''));
        if ($class_val_for_ka !== '') {
          $class_data_for_ka = CharacterManager::CLASSES[$class_val_for_ka] ?? NULL;
          if ($class_data_for_ka) {
            $selected_class_feat_for_ka = trim((string) $form_state->getValue('class_feat', ''));
            $ka_opts = $class_val_for_ka === 'rogue'
              ? $this->resolveRogueKeyAbilityOptions($selected_class_feat_for_ka)
              : $this->abilityScoreTracker->normalizeAbilityOptions($class_data_for_ka['key_ability'] ?? '');
            $selected_key_ability = $this->abilityScoreTracker->normalizeAbilityKey($form_state->getValue('class_key_ability', ''));
            if (count($ka_opts) > 1 && $selected_key_ability === NULL) {
              $form_state->setErrorByName('class_key_ability', $this->t('You must choose a key ability for this class.'));
            }
            elseif ($selected_key_ability !== NULL && !in_array($selected_key_ability, $ka_opts, TRUE)) {
              $form_state->setErrorByName('class_key_ability', $this->t('That key ability is not allowed for your selected class feat.'));
            }
          }
        }

        // Validate subclass / class-specialization selections.
        $class_val = trim((string) $form_state->getValue('class', ''));
        if (in_array($class_val, ['sorcerer', 'witch'], TRUE)) {
          if (trim((string) $form_state->getValue('subclass', '')) === '') {
            $label = $class_val === 'sorcerer' ? 'bloodline' : 'patron';
            $form_state->setErrorByName('subclass', $this->t('Select a @label for your @class.', [
              '@label' => $label,
              '@class' => ucfirst($class_val),
            ]));
          }
        }
        elseif ($class_val === 'wizard') {
          if (trim((string) $form_state->getValue('subclass', '')) === '') {
            $form_state->setErrorByName('subclass', $this->t('Select an arcane school for your Wizard.'));
          }
          if (trim((string) $form_state->getValue('arcane_thesis', '')) === '') {
            $form_state->setErrorByName('arcane_thesis', $this->t('Select an arcane thesis for your Wizard.'));
          }
        }

        // Validate cantrip and spell counts for caster classes.
        $cantrip_limit = (int) $form_state->get('cantrip_limit');
        if ($cantrip_limit > 0) {
          $raw_cantrips = $form_state->getValue('cantrips', []);
          $selected_cantrips = is_array($raw_cantrips)
            ? array_filter($raw_cantrips, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL)
            : [];
          $cantrip_count = count($selected_cantrips);
          if ($cantrip_count !== $cantrip_limit) {
            $form_state->setErrorByName('cantrips', $this->t('Select exactly @count cantrip(s). You have selected @selected.', [
              '@count' => $cantrip_limit,
              '@selected' => $cantrip_count,
            ]));
          }
        }

        $first_spell_limit = (int) $form_state->get('first_spell_limit');
        if ($first_spell_limit > 0) {
          $raw_spells = $form_state->getValue('spells_first', []);
          $selected_spells = is_array($raw_spells)
            ? array_filter($raw_spells, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL)
            : [];
          $spell_count = count($selected_spells);
          if ($spell_count > $first_spell_limit) {
            $form_state->setErrorByName('spells_first', $this->t('Select at most @count spell(s). You have selected @selected.', [
              '@count' => $first_spell_limit,
              '@selected' => $spell_count,
            ]));
          }
        }

        $stored_character_data = $this->loadCharacterData((int) $form_state->get('character_id'));
        if (($stored_character_data['ancestry_feat'] ?? '') === 'adapted-cantrip') {
          $resolve_data = $stored_character_data;
          $resolve_data['class'] = $class_val;
          $resolve_data['subclass'] = (string) $form_state->getValue('subclass', ($stored_character_data['subclass'] ?? ''));
          $resolve_data['bloodline'] = $resolve_data['subclass'];
          $resolve_data['patron'] = $resolve_data['subclass'];
          $this->validateAdaptedCantripSelection($form_state, $resolve_data);
        }
        if (($stored_character_data['ancestry_feat'] ?? '') === 'natural-ambition') {
          $this->validateNaturalAmbitionSelection($form_state, $class_val);
        }
        $selected_class_feat = trim((string) $form_state->getValue('class_feat', ''));
        $selected_bonus_feat = trim((string) $form_state->getValue(['feat_selections', 'natural-ambition', 'bonus_class_feat'], ''));
        if ($selected_class_feat === 'monster-hunter' || $selected_bonus_feat === 'monster-hunter') {
          $this->validateMonsterHunterSelection($form_state);
        }
        if ($selected_class_feat === 'staff-nexus' || $selected_bonus_feat === 'staff-nexus') {
          $this->validateStaffNexusSelection($form_state, $class_val);
        }
        if ($selected_class_feat === 'eldritch-trickster-racket' || $selected_bonus_feat === 'eldritch-trickster-racket') {
          $this->validateEldritchTricksterSelection($form_state, $class_val);
        }
        if ($selected_class_feat === 'mastermind-racket' || $selected_bonus_feat === 'mastermind-racket') {
          $this->validateMastermindSelection($form_state, $class_val);
        }
        $animal_companion_source = $this->resolveAnimalCompanionSelectionSource($form_state, $this->loadCharacterData((int) $form_state->get('character_id')), $class_val);
        if ($animal_companion_source !== NULL) {
          $this->validateAnimalCompanionSelection($form_state, $animal_companion_source);
        }
        break;

      case 5:
        $free_boosts = self::normalizeList($form_state->getValue('free_boosts', []));
        if (count($free_boosts) !== 4) {
          $form_state->setErrorByName('free_boosts', $this->t('Select exactly 4 free boosts.'));
        }
        elseif (count(array_unique($free_boosts)) !== 4) {
          $form_state->setErrorByName('free_boosts', $this->t('Free boosts must be unique.'));
        }
        break;

      case 6:
        if (trim((string) $form_state->getValue('alignment', '')) === '') {
          $form_state->setErrorByName('alignment', $this->t('Alignment selection is required.'));
        }

        // Validate general feat selection.
        if (trim((string) $form_state->getValue('general_feat', '')) === '') {
          $form_state->setErrorByName('general_feat', $this->t('General feat selection is required.'));
        }

        $general_feat_value = trim((string) $form_state->getValue('general_feat', ''));
        $stored_character_data = $this->loadCharacterData((int) $form_state->get('character_id'));
        $stored_class_feat = trim((string) ($stored_character_data['class_feat'] ?? ''));
        $stored_bonus_feat = trim((string) ($stored_character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
        if ($stored_class_feat === 'domain-initiate' || $stored_bonus_feat === 'domain-initiate') {
          $selected_deity = trim((string) $form_state->getValue('deity', $stored_character_data['deity'] ?? ''));
          $this->validateDomainInitiateSelection($form_state, $selected_deity);
        }

        if ($general_feat_value === 'specialty-crafting') {
          $this->validateSpecialtyCraftingSelection($form_state);
        }
        elseif ($general_feat_value === 'virtuosic-performer') {
          $this->validateVirtuosicPerformerSelection($form_state);
        }
        elseif ($general_feat_value === 'canny-acumen') {
          $this->validateCannyAcumenSelection($form_state);
        }
        elseif ($general_feat_value === 'adopted-ancestry') {
          $this->validateAdoptedAncestrySelection($form_state, $stored_character_data);
        }
        elseif ($general_feat_value === 'armor-proficiency') {
          $this->validateArmorProficiencySelection($form_state, $stored_character_data);
        }
        elseif ($general_feat_value === 'weapon-proficiency') {
          $this->validateWeaponProficiencySelection($form_state, $stored_character_data);
        }

        // Enforce exact skill count (class base + INT modifier).
        $required_skills = (int) $form_state->get('total_skill_picks');
        if ($required_skills > 0) {
          $raw_skills = $form_state->getValue('trained_skills', []);
          $selected_skills = is_array($raw_skills)
            ? array_filter($raw_skills, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL)
            : [];
          $count = count($selected_skills);
          if ($count !== $required_skills) {
            $form_state->setErrorByName('trained_skills', $this->t('Select exactly @count skill(s). You have selected @selected.', [
              '@count' => $required_skills,
              '@selected' => $count,
            ]));
          }
        }
        break;

      case 7:
        // Enforce 15 gp budget.
        $catalog = $this->getEquipmentCatalog();
        $catalog_by_id = [];
        foreach ($catalog as $items) {
          foreach ($items as $item) {
            $catalog_by_id[$item['id']] = $item;
          }
        }

        $equipment_cost = 0.0;
        foreach (['weapons', 'armor', 'gear'] as $group) {
          $raw = $form_state->getValue($group, []);
          if (is_array($raw)) {
            foreach (array_filter($raw) as $id) {
              if (isset($catalog_by_id[$id])) {
                $equipment_cost += (float) $catalog_by_id[$id]['cost'];
              }
            }
          }
        }

        if ($equipment_cost > 15) {
          $form_state->setErrorByName('weapons', $this->t('Total equipment cost (@cost gp) exceeds the 15 gp budget.', [
            '@cost' => number_format($equipment_cost, 1),
          ]));
        }
        break;
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
          $form_state->setRedirect('dungeoncrawler_content.character_setup', [], ['query' => $query]);
        }
        else {
          $form_state->setRedirect('dungeoncrawler_content.character_step', ['step' => $step], ['query' => $query]);
        }
        return;
      }
    }
    $next_version = $submitted_version + 1;

    // Update character data with form values.
    // Exclude internal Drupal keys AND the step-7 checkbox groups (weapons,
    // armor, gear) which contain raw Drupal checkbox arrays ({id: id|0}).
    // Step 7 builds its own cleaned equipment/inventory structures below.
    $exclude_keys = [
      'form_build_id', 'form_token', 'form_id', 'op', 'character_version',
      'weapons', 'armor', 'gear',
    ];
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
      
      // Store final scores and sources
      foreach ($calculation['scores'] as $ability => $score) {
        $character_data[$ability] = $score;
      }
      
      // Store source attribution for transparency
      $character_data['ability_sources'] = $calculation['sources'];
    }

    // Step 3: derive and store background skill training, lore, and feat.
    // These are display-only in the form (markup) so they must be applied here.
    if ((int) $step === 3 && !empty($character_data['background'])) {
      $bg = CharacterManager::BACKGROUNDS[$character_data['background']] ?? NULL;
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
        $selection['selected_cantrip'] = trim((string) ($selection['selected_cantrip'] ?? ''));
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
        $selection['selected_cantrip'] = trim((string) ($selection['selected_cantrip'] ?? ''));
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
        $selection['bonus_general_feat'] = trim((string) ($selection['bonus_general_feat'] ?? ''));
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
        $selection['selected_feat'] = trim((string) ($selection['selected_feat'] ?? ''));
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
        $selection['selected_skill'] = trim((string) ($selection['selected_skill'] ?? ''));
        $selection['selected_language'] = trim((string) ($selection['selected_language'] ?? ''));
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
        $selection['selected_skill'] = trim((string) ($selection['selected_skill'] ?? ''));
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
        $selection['selected_feat'] = trim((string) ($selection['selected_feat'] ?? ''));
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
        $selection['damage_type'] = trim((string) ($selection['damage_type'] ?? ''));
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
        $selection['skills'] = self::normalizeList($selection['skills'] ?? []);
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
        $selection['selected_weapon_id'] = trim((string) ($selection['selected_weapon_id'] ?? ''));
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
        $selection['target_type'] = trim((string) ($selection['target_type'] ?? ''));
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
        $selection['selected_skills'] = self::normalizeList($selection['selected_skills'] ?? []);
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
        $selection['selected_lore'] = $this->normalizeLoreSkillName((string) ($selection['selected_lore'] ?? ''));
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
        $selection['specialty'] = trim((string) ($selection['specialty'] ?? ''));
        $character_data['feat_selections']['natural-performer'] = $selection;
      }
    }

    // Step 4: Build structured spellcasting data for caster classes.
    if ((int) $step === 4) {
      $selected_class = $character_data['class'] ?? '';

      // Store class proficiency levels from the CLASSES constant.
      if ($selected_class !== '' && isset(CharacterManager::CLASSES[$selected_class]['proficiencies'])) {
        $character_data['class_proficiencies'] = CharacterManager::CLASSES[$selected_class]['proficiencies'];
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

        $spell_slots = CharacterManager::CASTER_SPELL_SLOTS[$selected_class] ?? [];

        // Build the structured spells block for character_data.
        $character_data['spells'] = [
          'tradition' => $tradition,
          'casting_ability' => $this->resolveSpellcastingAbility($selected_class),
          'cantrips' => $cantrip_ids,
          'first_level' => $spell_ids,
          'slots' => [
            'cantrips' => $spell_slots['cantrips'] ?? 5,
            'first' => $spell_slots['first'] ?? 2,
          ],
        ];

        // Wizard spellbook: track separately if applicable.
        if ($selected_class === 'wizard') {
          $character_data['spells']['spellbook_size'] = $spell_slots['spellbook'] ?? 10;
        }

        // Clean the raw checkbox data from the generic dump.
        $character_data['cantrips'] = $cantrip_ids;
        $character_data['spells_first'] = $spell_ids;
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
        $selection['selected_tradition'] = trim((string) ($selection['selected_tradition'] ?? ''));
        $selection['selected_cantrip'] = trim((string) ($selection['selected_cantrip'] ?? ''));
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
        $selection['bonus_class_feat'] = trim((string) ($selection['bonus_class_feat'] ?? ''));
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
        $selection['selected_monster_type'] = trim((string) ($selection['selected_monster_type'] ?? ''));
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
        $selection['selected_cantrip'] = trim((string) ($selection['selected_cantrip'] ?? ''));
        $selection['selected_spell'] = trim((string) ($selection['selected_spell'] ?? ''));
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
        $selection['selected_dedication'] = trim((string) ($selection['selected_dedication'] ?? $selection['dedication'] ?? ''));
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
        $selection['selected_skill'] = trim((string) ($selection['selected_skill'] ?? $selection['knowledge_skill'] ?? ''));
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
        $selection['selected_companion_species'] = strtolower(trim((string) ($selection['selected_companion_species'] ?? $selection['species_id'] ?? '')));
        $selection['species_id'] = $selection['selected_companion_species'];
        $selection['name'] = trim((string) ($selection['name'] ?? $selection['display_name'] ?? ''));
        $character_data['feat_selections'][$animal_companion_source] = $selection;
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
        $selection['specialty'] = trim((string) ($selection['specialty'] ?? ''));
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
        $selection['specialty'] = trim((string) ($selection['specialty'] ?? ''));
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
        $selection['selected_proficiency'] = trim((string) ($selection['selected_proficiency'] ?? ''));
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
        $selection['selected_ancestry'] = trim((string) ($selection['selected_ancestry'] ?? ''));
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
        $selection['selected_weapon_id'] = trim((string) ($selection['selected_weapon_id'] ?? ''));
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
        $selection['selected_domain'] = trim((string) ($selection['selected_domain'] ?? ''));
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
      $character_data['gold'] = $remaining_gp;

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
        'currency' => [
          'cp' => 0,
          'sp' => 0,
          'gp' => $remaining_gp,
          'pp' => 0,
        ],
        'totalBulk' => $total_bulk,
        'encumbrance' => $encumbrance,
      ];

      $this->getLogger('dungeoncrawler_content')->notice('Character setup equipment sync: character_id=@character_id campaign_id=@campaign_id selected_ids=@selected_ids carried_ids=@carried_ids remaining_gp=@remaining_gp', [
        '@character_id' => (string) ($character_id ?? ''),
        '@campaign_id' => (string) ($campaign_id ?? ''),
        '@selected_ids' => implode(',', $selected_ids),
        '@carried_ids' => implode(',', array_map(static fn(array $item): string => (string) ($item['id'] ?? ''), $carried)),
        '@remaining_gp' => number_format($remaining_gp, 2, '.', ''),
      ]);
    }

    $next_step = min(8, (int) $step + 1);
    $character_data['step'] = $next_step;

    // Save to database
    $character_id = $this->saveCharacter($character_id, $character_data, $next_version, $campaign_id);

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
    if ((int) $step === 7 && $campaign_id && !empty($selected_items)) {
      $this->createCampaignItemInstances((int) $campaign_id, (int) $character_id, $selected_items);
    }

    // Redirect to next step or character view
    if ($step >= 8) {
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
      $form_state->setRedirect('dungeoncrawler_content.character_view', ['character_id' => $character_id], $final_options);
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
        $form_state->setRedirect('dungeoncrawler_content.character_setup', [], ['query' => $next_query]);
        return;
      }
      else {
        $redirect_step = $next_step;
      }

      $form_state->setRedirect('dungeoncrawler_content.character_step', [
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
   * Gets equipment catalog options for step 7.
   * 
   * Returns equipment data conforming to character_options_step7.json schema.
   * All items include: id, name, cost, bulk, and category-specific fields.
   */
  private function getEquipmentCatalog(): array {
    $template_catalog = $this->buildEquipmentCatalogFromTemplates();
    if (!empty($template_catalog['weapons']) || !empty($template_catalog['armor']) || !empty($template_catalog['gear'])) {
      return $template_catalog;
    }

    return [
      'weapons' => [
        ['id' => 'longsword', 'name' => 'Longsword', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 S', 'hands' => 1, 'traits' => ['versatile P'], 'description' => 'A dependable martial blade that can slash or shift into piercing attacks.'],
        ['id' => 'shortsword', 'name' => 'Shortsword', 'type' => 'weapon', 'cost' => 0.9, 'bulk' => 'L', 'damage' => '1d6 P', 'hands' => 1, 'traits' => ['agile', 'finesse', 'versatile S'], 'description' => 'A light dueling weapon suited to agile and Dexterity-focused fighting styles.'],
        ['id' => 'dagger', 'name' => 'Dagger', 'type' => 'weapon', 'cost' => 0.2, 'bulk' => 'L', 'damage' => '1d4 P', 'hands' => 1, 'traits' => ['agile', 'finesse', 'thrown 10 ft.', 'versatile S'], 'description' => 'A compact backup weapon that works in melee or as a short-range thrown blade.'],
        ['id' => 'rapier', 'name' => 'Rapier', 'type' => 'weapon', 'cost' => 2.0, 'bulk' => 1, 'damage' => '1d6 P', 'hands' => 1, 'traits' => ['deadly d8', 'disarm', 'finesse'], 'description' => 'A precise dueling blade built for finesse attacks and high-accuracy strikes.'],
        ['id' => 'battleaxe', 'name' => 'Battle Axe', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 S', 'hands' => 1, 'traits' => ['sweep'], 'description' => 'A hard-hitting axe that rewards pressuring multiple foes in close quarters.'],
        ['id' => 'warhammer', 'name' => 'Warhammer', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 B', 'hands' => 1, 'traits' => ['shove'], 'description' => 'A crushing melee weapon that pairs well with shield users and battlefield control.'],
        ['id' => 'shortbow', 'name' => 'Shortbow', 'type' => 'weapon', 'cost' => 3.0, 'bulk' => 1, 'damage' => '1d6 P', 'hands' => 2, 'traits' => ['deadly d10', 'range 60 ft.'], 'description' => 'A compact ranged weapon for mobile archers who want steady attacks from safety.'],
        ['id' => 'longbow', 'name' => 'Longbow', 'type' => 'weapon', 'cost' => 6.0, 'bulk' => 2, 'damage' => '1d8 P', 'hands' => 2, 'traits' => ['deadly d10', 'range 100 ft.', 'volley 30 ft.'], 'description' => 'A powerful bow with exceptional reach, best used when you can keep enemies at range.'],
        ['id' => 'staff', 'name' => 'Staff', 'type' => 'weapon', 'cost' => 0.0, 'bulk' => 1, 'damage' => '1d4 B', 'hands' => 2, 'traits' => ['two-hand d8'], 'description' => 'A simple two-handed staff that doubles as an arcane focus and walking aid.'],
      ],
      'armor' => [
        ['id' => 'leather', 'name' => 'Leather Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 1, 'ac' => '+1', 'traits' => [], 'description' => 'Flexible light armor that offers a modest defense boost with minimal encumbrance.'],
        ['id' => 'studded_leather_armor', 'name' => 'Studded Leather Armor', 'type' => 'armor', 'cost' => 3.0, 'bulk' => 1, 'ac' => '+2', 'traits' => [], 'description' => 'Reinforced light armor for agile warriors who want stronger protection without heavy bulk.'],
        ['id' => 'chain_shirt', 'name' => 'Chain Shirt', 'type' => 'armor', 'cost' => 5.0, 'bulk' => 1, 'ac' => '+2', 'traits' => ['flexible', 'noisy'], 'description' => 'A light layer of chain that balances defense with mobility, though it is harder to keep quiet.'],
        ['id' => 'hide_armor', 'name' => 'Hide Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 2, 'ac' => '+3', 'traits' => [], 'description' => 'Sturdy hides favored by wilderness warriors who can handle extra weight for more protection.'],
        ['id' => 'scale_mail', 'name' => 'Scale Mail', 'type' => 'armor', 'cost' => 4.0, 'bulk' => 2, 'ac' => '+3', 'traits' => [], 'description' => 'Overlapping scales provide solid early-game defense for front-line adventurers.'],
        ['id' => 'chain_mail', 'name' => 'Chain Mail', 'type' => 'armor', 'cost' => 6.0, 'bulk' => 2, 'ac' => '+4', 'traits' => ['flexible', 'noisy'], 'description' => 'Heavy rings offer strong defense for martial builds that can tolerate the noise and weight.'],
        ['id' => 'breastplate', 'name' => 'Breastplate', 'type' => 'armor', 'cost' => 8.0, 'bulk' => 2, 'ac' => '+4', 'traits' => [], 'description' => 'A solid torso plate that brings sturdy protection without a full suit of heavy armor.'],
        ['id' => 'wooden_shield', 'name' => 'Wooden Shield', 'type' => 'armor', 'cost' => 1.0, 'bulk' => 1, 'ac' => '+2 circumstance', 'traits' => [], 'description' => 'A simple shield that improves survivability and enables shield-based defenses.'],
      ],
      'gear' => [
        ['id' => 'backpack', 'name' => 'Backpack', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => [], 'description' => 'A basic pack for carrying the rest of your kit into the field.'],
        ['id' => 'bedroll', 'name' => 'Bedroll', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => [], 'description' => 'A simple sleeping roll for overland travel, campouts, and dungeon rests.'],
        ['id' => 'rope', 'name' => 'Rope (50 ft.)', 'type' => 'adventuring_gear', 'cost' => 0.5, 'bulk' => 'L', 'traits' => [], 'description' => 'Useful for climbing, tying gear, and solving all kinds of traversal problems.'],
        ['id' => 'torches', 'name' => 'Torches (5)', 'type' => 'adventuring_gear', 'cost' => 0.05, 'bulk' => 'L', 'traits' => [], 'description' => 'Cheap, reliable light sources when the party cannot rely on magic.'],
        ['id' => 'rations', 'name' => 'Rations (1 week)', 'type' => 'adventuring_gear', 'cost' => 0.4, 'bulk' => 'L', 'traits' => [], 'description' => 'Trail food for long journeys when inns and settlements are far away.'],
        ['id' => 'waterskin', 'name' => 'Waterskin', 'type' => 'adventuring_gear', 'cost' => 0.05, 'bulk' => 'L', 'traits' => [], 'description' => 'A light container that keeps your character ready for travel and survival scenes.'],
        ['id' => 'healers_tools', 'name' => "Healer's Tools", 'type' => 'adventuring_gear', 'cost' => 5.0, 'bulk' => 1, 'traits' => [], 'description' => 'Required for many Medicine actions, including key battlefield and downtime care.'],
        ['id' => 'thieves_tools', 'name' => "Thieves' Tools", 'type' => 'adventuring_gear', 'cost' => 3.0, 'bulk' => 'L', 'traits' => [], 'description' => 'Essential picks and implements for locks, traps, and stealthy infiltration work.'],
        ['id' => 'grappling_hook', 'name' => 'Grappling Hook', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => [], 'description' => 'Pairs with rope to help the party scale walls, ledges, and ruined structures.'],
        ['id' => 'hooded_lantern', 'name' => 'Hooded Lantern', 'type' => 'adventuring_gear', 'cost' => 0.7, 'bulk' => 'L', 'traits' => [], 'description' => 'A controlled light source that is safer and more practical than loose flames.'],
        ['id' => 'oil_flask', 'name' => 'Oil (1 flask)', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => [], 'description' => 'Fuel for lanterns and a handy utility consumable for improvised adventuring solutions.'],
      ],
    ];
  }

  /**
   * Builds step-7 equipment catalog from template item tables.
   */
  private function buildEquipmentCatalogFromTemplates(): array {
    $catalog = [
      'weapons' => [],
      'armor' => [],
      'gear' => [],
    ];

    if (!$this->database->schema()->tableExists('dungeoncrawler_content_registry')) {
      return $catalog;
    }

    // Query curated starting equipment directly from the content registry.
    // Filter: content_type = 'item', source from our content/items/ directory,
    // level ≤ 1, and cost ≤ 15 gp (starter budget).
    $query = $this->database->select('dungeoncrawler_content_registry', 'r');
    $query->fields('r', ['content_id', 'name', 'tags', 'schema_data']);
    $query->condition('r.content_type', 'item');
    $query->condition('r.source_file', 'items/%', 'LIKE');

    $result = $query->execute();

    foreach ($result as $row) {
      $item_id = (string) ($row->content_id ?? '');
      if ($item_id === '') {
        continue;
      }

      $schema_data = json_decode((string) ($row->schema_data ?? '{}'), TRUE);
      if (!is_array($schema_data)) {
        $schema_data = [];
      }

      // Calculate cost in gp from the nested price object.
      $price = $schema_data['price'] ?? [];
      $cost_gp = (float) ($price['gp'] ?? 0)
        + (float) ($price['sp'] ?? 0) / 10
        + (float) ($price['cp'] ?? 0) / 100
        + (float) ($price['pp'] ?? 0) * 10;

      // Fallback to flat price_gp for legacy scraped data.
      if ($cost_gp == 0 && isset($schema_data['price_gp'])) {
        $cost_gp = (float) $schema_data['price_gp'];
      }

      // Skip items over budget.
      if ($cost_gp > 15) {
        continue;
      }

      $tags = $this->normalizeTags((string) ($row->tags ?? ''));
      $item_type = (string) ($schema_data['item_type'] ?? 'adventuring_gear');
      $category = $this->mapTemplateItemCategory($item_type, $tags);

      $name = (string) ($row->name ?? '');
      if ($name === '') {
        $name = ucwords(str_replace(['_', '-'], ' ', $item_id));
      }

      $item = [
        'id' => $item_id,
        'name' => $name,
        'type' => $item_type,
        'cost' => round($cost_gp, 2),
        'bulk' => $schema_data['bulk'] ?? 'L',
        'traits' => $schema_data['traits'] ?? $tags,
        'description' => $this->extractTemplateItemDescription($schema_data),
      ];

      // Extract weapon stats from nested weapon_stats object.
      if ($category === 'weapons') {
        $ws = $schema_data['weapon_stats'] ?? [];
        $dmg = $ws['damage'] ?? [];
        $dice = ($dmg['dice_count'] ?? 1) . ($dmg['die_size'] ?? '');
        $dmg_type = $dmg['damage_type'] ?? '';
        $dmg_abbrev = $dmg_type ? strtoupper($dmg_type[0]) : '';
        $item['damage'] = trim($dice . ' ' . $dmg_abbrev);
        $item['hands'] = (int) ($schema_data['hands'] ?? 1);
      }
      // Extract armor stats from nested armor_stats object.
      elseif ($category === 'armor') {
        $as = $schema_data['armor_stats'] ?? [];
        $ac_bonus = $as['ac_bonus'] ?? NULL;
        if ($ac_bonus !== NULL) {
          $item['ac'] = '+' . (int) $ac_bonus;
          if (($as['category'] ?? '') === 'shield') {
            $item['ac'] .= ' circumstance';
          }
        }
        else {
          $item['ac'] = (string) ($schema_data['ac'] ?? '');
        }
      }

      $catalog[$category][$item_id] = $item;
    }

    foreach (['weapons', 'armor', 'gear'] as $category) {
      uasort($catalog[$category], static function (array $a, array $b): int {
        return strnatcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
      });
      $catalog[$category] = array_values($catalog[$category]);
    }

    return $catalog;
  }

  /**
   * Normalizes stored registry tags into a plain string list.
   */
  private function normalizeTags(string $raw_tags): array {
    $decoded = json_decode($raw_tags, TRUE);
    if (is_array($decoded)) {
      return array_values(array_filter(array_map(static fn($tag): string => (string) $tag, $decoded)));
    }

    return [];
  }

  private function normalizeLoreSkillName(string $lore_name): string {
    $normalized = preg_replace('/\s+/', ' ', trim($lore_name)) ?? '';
    if ($normalized === '') {
      return '';
    }
    if (!preg_match('/\blore$/i', $normalized)) {
      $normalized .= ' Lore';
    }
    return $normalized;
  }

  /**
   * Maps template item metadata to step-7 equipment categories.
   */
  private function mapTemplateItemCategory(string $item_type, array $tags): string {
    $normalized_type = strtolower($item_type);
    $normalized_tags = array_map('strtolower', $tags);

    if ($normalized_type === 'weapon' || in_array('weapon', $normalized_tags, TRUE)) {
      return 'weapons';
    }

    if ($normalized_type === 'armor' || in_array('armor', $normalized_tags, TRUE) || in_array('shield', $normalized_tags, TRUE)) {
      return 'armor';
    }

    return 'gear';
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
        $form_data = is_array($data['wizard'] ?? NULL) ? $data['wizard'] : $data;
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
    $status = $this->imageGenerationIntegration->getIntegrationStatus();
    $configured_provider = strtolower(trim((string) ($status['configured_provider'] ?? $status['default_provider'] ?? 'gemini')));
    $effective_provider = strtolower(trim((string) ($status['effective_provider'] ?? '')));

    if ($effective_provider !== '') {
      $description = $effective_provider === $configured_provider
        ? $this->t('Creates a portrait using the configured AI image provider after character creation.')
        : $this->t('Creates a portrait using @provider after character creation. The configured default (@configured) is currently unavailable, so the live-ready provider will be used instead.', [
          '@provider' => ucfirst($effective_provider),
          '@configured' => ucfirst($configured_provider),
        ]);

      return [
        'available' => TRUE,
        'description' => $description,
      ];
    }

    $issues = [];
    foreach (($status['providers'] ?? []) as $provider => $provider_status) {
      $provider_issues = [];
      if (empty($provider_status['enabled'])) {
        $provider_issues[] = $this->t('disabled');
      }
      if (empty($provider_status['has_credentials']) && empty($provider_status['has_api_key'])) {
        $provider_issues[] = $this->t('missing credentials');
      }
      if (!empty($provider_issues)) {
        $issues[] = $this->t('@provider: @issues', [
          '@provider' => ucfirst((string) $provider),
          '@issues' => implode(', ', $provider_issues),
        ]);
      }
    }

    return [
      'available' => FALSE,
      'description' => $this->t('Portrait generation is currently unavailable because no live image provider is ready (@issues).', [
        '@issues' => implode('; ', $issues),
      ]),
    ];
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
  private function saveCharacter(int|string|null $character_id, array $character_data, int $next_version = 0, int|string|null $campaign_id = NULL): int|string {
    $now = $this->time->getRequestTime();
    $schema_data = $this->characterManager->canonicalizeCharacterData($character_data);
    if (empty($schema_data['created_at'])) {
      $schema_data['created_at'] = date('c', $now);
    }
    $schema_data['updated_at'] = date('c', $now);
    $hot = $this->characterManager->extractHotColumnsFromData($schema_data);

    // Character setup edits the record that already exists; new records always
    // start in the canonical library and are attached to campaigns separately.
    $resolved_campaign_id = 0;
    if ($character_id) {
      $existing_record = $this->characterManager->loadCharacter((int) $character_id);
      $resolved_campaign_id = $existing_record ? (int) ($existing_record->campaign_id ?? 0) : 0;
    }

    if ($character_id) {
      $this->database->update('dc_campaign_characters')
        ->fields([
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
          'status' => $schema_data['step'] >= 8 ? 1 : 0,
          'version' => $next_version,
          'changed' => $now,
        ])
        ->condition('id', $character_id)
        ->execute();
      return $character_id;
    }
    else {
      $instance_id = $this->uuid->generate();
      return $this->database->insert('dc_campaign_characters')
        ->fields([
          'uuid' => $instance_id,
          'campaign_id' => $resolved_campaign_id,
          'character_id' => 0,
          'instance_id' => $instance_id,
          'uid' => (int) $this->currentUser->id(),
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
          'status' => 0,
          'created' => $now,
          'changed' => $now,
        ])
        ->execute();
    }
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
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterManager::ANCESTRIES as $name => $data) {
      $options[self::ancestryMachineId($name)] = $name;
    }
    return $options;
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
    $options = ['' => $this->t('- Select -')];
    if ($ancestry) {
      $ancestry_name = $this->resolveAncestryName((string) $ancestry);
      $heritages = CharacterManager::HERITAGES[$ancestry_name] ?? [];
      foreach ($heritages as $heritage) {
        $options[$heritage['id']] = $heritage['name'];
      }
    }
    return $options;
  }

  /**
   * Builds ability data array for the interactive boost widget.
   *
   * Used by Steps 3 and 5 where users select ability boosts.
   */
  private function buildInteractiveAbilityData(array $calculation, array $selected_boosts): array {
    $abilities_data = [];
    foreach ($calculation['scores'] as $ability_key => $score) {
      $abilities_data[$ability_key] = [
        'score' => $score,
        'modifier' => $calculation['modifiers'][$ability_key],
        'sources' => $calculation['sources'][$ability_key] ?? [],
        'selected' => in_array($ability_key, $selected_boosts, TRUE),
        'disabled' => FALSE,
      ];
    }
    return $abilities_data;
  }

  /**
   * Normalizes a form value that may be JSON-encoded into a flat array.
   *
   * Used by validateForm() and submitForm() for boost fields.
   */
  private static function normalizeList(mixed $value): array {
    if (is_string($value)) {
      $decoded = json_decode($value, TRUE);
      if (is_array($decoded)) {
        $value = $decoded;
      }
      elseif (trim($value) === '') {
        $value = [];
      }
      else {
        $value = [$value];
      }
    }

    if (!is_array($value)) {
      return [];
    }

    return array_values(array_filter(array_map(static function ($item) {
      return is_string($item) ? trim($item) : $item;
    }, $value), static function ($item) {
      return $item !== NULL && $item !== '';
    }));
  }

  /**
   * Converts an ancestry display name to its machine ID.
   */
  private static function ancestryMachineId(string $name): string {
    return strtolower(str_replace(' ', '-', $name));
  }

  /**
   * Resolves ancestry machine id (e.g. "half-elf") to canonical ancestry name.
   */
  private function resolveAncestryName(string $ancestry_id): string {
    if ($ancestry_id === '') {
      return '';
    }

    foreach (array_keys(CharacterManager::ANCESTRIES) as $name) {
      if (self::ancestryMachineId($name) === strtolower($ancestry_id)) {
        return $name;
      }
    }

    return str_replace('-', ' ', ucwords($ancestry_id, '-'));
  }

  /**
   * Return Adopted Ancestry selection options excluding the current ancestry.
   */
  private function getAdoptedAncestryOptions(array $character_data): array {
    $current_ancestry = trim((string) ($character_data['ancestry'] ?? ''));
    $options = [];
    foreach (array_keys(CharacterManager::ANCESTRIES) as $ancestry_name) {
      $ancestry_id = self::ancestryMachineId($ancestry_name);
      if ($ancestry_id === $current_ancestry) {
        continue;
      }
      $options[$ancestry_id] = $ancestry_name;
    }

    return $options;
  }

  /**
   * Return selected cantrip and spell options for Staff Nexus.
   *
   * @return array{cantrips: array<string, string>, spells: array<string, string>}
   *   Staff-eligible spell options keyed by spell id.
   */
  private function getStaffNexusSpellOptions(FormStateInterface $form_state, array $character_data, string $tradition): array {
    $selected_cantrip_ids = self::normalizeList(
      $form_state->getValue('cantrips', $character_data['cantrips'] ?? [])
    );
    $selected_spell_ids = self::normalizeList(
      $form_state->getValue('spells_first', $character_data['spells_first'] ?? [])
    );

    $cantrip_labels = [];
    foreach ($this->characterManager->getSpellsByTradition($tradition, 0) as $spell) {
      $spell_id = (string) ($spell['id'] ?? '');
      if ($spell_id === '' || !in_array($spell_id, $selected_cantrip_ids, TRUE)) {
        continue;
      }
      $cantrip_labels[$spell_id] = (string) ($spell['name'] ?? $spell_id);
    }

    $spell_labels = [];
    foreach ($this->characterManager->getSpellsByTradition($tradition, 1) as $spell) {
      $spell_id = (string) ($spell['id'] ?? '');
      if ($spell_id === '' || !in_array($spell_id, $selected_spell_ids, TRUE)) {
        continue;
      }
      $spell_labels[$spell_id] = (string) ($spell['name'] ?? $spell_id);
    }

    return [
      'cantrips' => $cantrip_labels,
      'spells' => $spell_labels,
    ];
  }

  /**
   * Gets background dropdown options.
   *
   * @return array
   *   Associative array of background options.
   */
  private function getBackgroundOptions(): array {
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterManager::BACKGROUNDS as $bg) {
      $options[$bg['id']] = $bg['name'];
    }
    return $options;
  }

  /**
   * Gets class dropdown options.
   *
   * @return array
   *   Associative array of class options.
   */
  private function getClassOptions(): array {
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterManager::CLASSES as $class) {
      $options[$class['id']] = $class['name'];
    }
    return $options;
  }

  /**
   * Gets alignment dropdown options.
   *
   * @return array
   *   Associative array of alignment options.
   */
  private function getAlignmentOptions(): array {
    return [
      '' => $this->t('- Select -'),
      'LG' => $this->t('Lawful Good'),
      'NG' => $this->t('Neutral Good'),
      'CG' => $this->t('Chaotic Good'),
      'LN' => $this->t('Lawful Neutral'),
      'N' => $this->t('Neutral'),
      'CN' => $this->t('Chaotic Neutral'),
      'LE' => $this->t('Lawful Evil'),
      'NE' => $this->t('Neutral Evil'),
      'CE' => $this->t('Chaotic Evil'),
    ];
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
    $user_input = $form_state->getUserInput();
    if (!is_array($user_input) || !array_key_exists($input_key, $user_input)) {
      return;
    }

    $raw_value = $user_input[$input_key];
    if (!is_scalar($raw_value) && $raw_value !== NULL) {
      $user_input[$input_key] = '';
      $form_state->setUserInput($user_input);
      $form_state->setValue($input_key, '');
      return;
    }

    $candidate = trim((string) $raw_value);
    if ($candidate === '') {
      return;
    }

    if (!array_key_exists($candidate, $options)) {
      $user_input[$input_key] = '';
      $form_state->setUserInput($user_input);
      $form_state->setValue($input_key, '');
    }
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
   * Resolves the spellcasting ability for a class.
   *
   * @param string $class
   *   The class ID.
   *
   * @return string
   *   The ability name (e.g. 'intelligence', 'wisdom', 'charisma').
   */
  private function resolveSpellcastingAbility(string $class): string {
    $map = [
      'wizard'   => 'intelligence',
      'witch'    => 'intelligence',
      'cleric'   => 'wisdom',
      'druid'    => 'wisdom',
      'bard'     => 'charisma',
      'sorcerer' => 'charisma',
      'oracle'   => 'charisma',
    ];
    return $map[strtolower($class)] ?? 'charisma';
  }

  /**
   * Builds a consistent detail card for a selected option.
   */
  private function buildSelectionDetailMarkup(string $title, string $description = '', array $tags = [], array $facts = []): string {
    $tag_markup = '';
    foreach ($tags as $tag) {
      $tag = trim((string) $tag);
      if ($tag === '') {
        continue;
      }
      $tag_markup .= '<span class="option-detail-card__tag">' . Html::escape($tag) . '</span>';
    }

    $description_markup = '';
    if (trim($description) !== '') {
      $description_markup = '<p class="option-detail-card__description">' . nl2br(Html::escape($description)) . '</p>';
    }

    $facts_markup = '';
    foreach ($facts as $label => $value) {
      $value = trim((string) $value);
      if ($value === '') {
        continue;
      }
      $facts_markup .= '<div class="option-detail-card__fact">'
        . '<span class="option-detail-card__fact-label">' . Html::escape((string) $label) . '</span>'
        . '<span class="option-detail-card__fact-value">' . Html::escape($value) . '</span>'
        . '</div>';
    }

    return '<div class="option-detail-card">'
      . '<div class="option-detail-card__header">'
      . '<h4 class="option-detail-card__title">' . Html::escape($title) . '</h4>'
      . ($tag_markup !== '' ? '<div class="option-detail-card__tags">' . $tag_markup . '</div>' : '')
      . '</div>'
      . $description_markup
      . ($facts_markup !== '' ? '<div class="option-detail-card__facts">' . $facts_markup . '</div>' : '')
      . '</div>';
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
    $normalized_tags = array_values(array_filter(array_map(static fn($tag): string => trim((string) $tag), $tags)));
    $normalized_facts = [];
    foreach ($facts as $label => $value) {
      $value = trim((string) $value);
      if ($value === '') {
        continue;
      }
      $normalized_facts[(string) $label] = $value;
    }

    return [
      'description' => trim($description),
      'tags' => $normalized_tags,
      'facts' => $normalized_facts,
    ];
  }

  /**
   * Attaches selector-card metadata to a form wrapper.
   */
  private function attachOptionCardSettings(array &$element, string $group_name, array $options, string $selection_type): void {
    if (empty($options)) {
      return;
    }

    $element['#attached']['drupalSettings']['characterOptionCards'][$group_name] = [
      'selectionType' => $selection_type,
      'options' => $options,
    ];
  }

  /**
   * Extracts prominent spell facts for selector cards.
   */
  private function extractSpellFacts(array $spell): array {
    $facts = [];
    $fact_map = [
      (string) $this->t('Cast') => ['actions', 'cast', 'cast_time'],
      (string) $this->t('Range') => ['range'],
      (string) $this->t('Targets') => ['targets', 'target'],
      (string) $this->t('Area') => ['area'],
      (string) $this->t('Duration') => ['duration'],
      (string) $this->t('Save') => ['save', 'saving_throw'],
      (string) $this->t('Components') => ['components'],
      (string) $this->t('Rarity') => ['rarity'],
      (string) $this->t('Source') => ['source', 'source_display', 'source_book'],
    ];

    foreach ($fact_map as $label => $keys) {
      foreach ($keys as $key) {
        if (!array_key_exists($key, $spell)) {
          continue;
        }

        $value = $spell[$key];
        if (is_array($value)) {
          $value = implode(', ', array_filter(array_map(static fn($item): string => trim((string) $item), $value)));
        }
        else {
          $value = trim((string) $value);
        }

        if ($value !== '') {
          $facts[$label] = $value;
          break;
        }
      }
    }

    if (!empty($spell['heightenable'])) {
      $facts[(string) $this->t('Heightens')] = (string) $this->t('Yes');
    }

    return $facts;
  }

  /**
   * Builds selector-card metadata for equipment.
   */
  private function buildEquipmentOptionCardData(array $item, string $category): array {
    $facts = [
      (string) $this->t('Cost') => number_format((float) ($item['cost'] ?? 0), 1) . ' gp',
      (string) $this->t('Bulk') => (string) ($item['bulk'] ?? 'L'),
    ];

    if ($category === 'weapons') {
      $facts[(string) $this->t('Damage')] = (string) ($item['damage'] ?? '');
      $facts[(string) $this->t('Hands')] = !empty($item['hands']) ? (string) $item['hands'] : '';
    }
    elseif ($category === 'armor') {
      $facts[(string) $this->t('AC')] = (string) ($item['ac'] ?? '');
    }

    return $this->buildOptionCardData(
      $this->buildEquipmentDescription($item, $category),
      $item['traits'] ?? [],
      $facts,
    );
  }

  /**
   * Builds a usable description for equipment selector cards.
   */
  private function buildEquipmentDescription(array $item, string $category): string {
    $description = trim((string) ($item['description'] ?? ''));
    if ($description !== '') {
      return $description;
    }

    return match ($category) {
      'weapons' => (string) $this->t('A starter weapon for early adventures and your opening combat kit.'),
      'armor' => (string) $this->t('Protective gear that improves survivability during the first stretch of the campaign.'),
      default => (string) $this->t('A practical adventuring supply that solves common travel, exploration, or camp needs.'),
    };
  }

  /**
   * Extracts item description text from template registry data.
   */
  private function extractTemplateItemDescription(array $schema_data): string {
    foreach (['description', 'summary', 'item_description', 'usage', 'effect'] as $key) {
      if (!array_key_exists($key, $schema_data)) {
        continue;
      }

      $value = $schema_data[$key];
      if (is_string($value)) {
        $value = trim($value);
        if ($value !== '') {
          return $value;
        }
      }
      elseif (is_array($value)) {
        $parts = [];
        array_walk_recursive($value, static function ($item) use (&$parts): void {
          $text = trim((string) $item);
          if ($text !== '') {
            $parts[] = $text;
          }
        });
        if (!empty($parts)) {
          return implode(' ', $parts);
        }
      }
    }

    return '';
  }

  /**
   * Adds Step 2 selection UI for First World Magic.
   */
  private function buildFirstWorldMagicSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['first-world-magic'] ?? [];
    $selected_cantrip = (string) $form_state->getValue(
      ['feat_selections', 'first-world-magic', 'selected_cantrip'],
      $stored_selection['selected_cantrip'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $container['feat_selections']['first-world-magic'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section', 'feat-selection-section']],
    ];
    $container['feat_selections']['first-world-magic']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('First World Magic')
        . '</strong><br>'
        . $this->t('Choose one primal cantrip to gain as an innate at-will spell.')
        . '</div>',
    ];

    $cantrip_options = [];
    $cantrip_cards = [];
    $cantrip_reference_cards = [];
    foreach ($this->characterManager->getSpellsByTradition('primal', 0) as $cantrip) {
      $cantrip_options[$cantrip['id']] = $cantrip['name'];
      $tags = ['Cantrip', 'Primal'];
      if (!empty($cantrip['school'])) {
        $tags[] = ucfirst((string) $cantrip['school']);
      }
      $facts = $this->extractSpellFacts($cantrip);
      $cantrip_cards[$cantrip['id']] = $this->buildOptionCardData(
        $cantrip['description'] ?? '',
        $tags,
        $facts,
      );
      $cantrip_reference_cards[] = [
        'title' => $cantrip['name'],
        'description' => $cantrip['description'] ?? '',
        'tags' => $tags,
        'facts' => $facts,
      ];
    }

    if (!array_key_exists($selected_cantrip, $cantrip_options)) {
      $selected_cantrip = '';
    }

    $container['feat_selections']['first-world-magic']['selected_cantrip'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your First World cantrip'),
      '#options' => $cantrip_options,
      '#default_value' => $selected_cantrip,
      '#required' => FALSE,
      '#description' => $this->t('This cantrip is fixed when chosen and becomes an innate at-will spell.'),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections']['first-world-magic'],
      'selected_cantrip',
      $cantrip_cards,
      'single'
    );
    $container['feat_selections']['first-world-magic']['reference'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section']],
      'heading' => [
        '#markup' => '<h4>' . $this->t('Primal cantrip reference') . '</h4>',
      ],
      'cards' => [
        '#markup' => $this->buildOptionDetailStackMarkup($cantrip_reference_cards),
      ],
    ];
  }

  /**
   * Adds Step 2 selection UI for Otherworldly Magic.
   */
  private function buildOtherworldlyMagicSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['otherworldly-magic'] ?? [];
    $selected_cantrip = (string) $form_state->getValue(
      ['feat_selections', 'otherworldly-magic', 'selected_cantrip'],
      $stored_selection['selected_cantrip'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $container['feat_selections']['otherworldly-magic'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section', 'feat-selection-section']],
    ];
    $container['feat_selections']['otherworldly-magic']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Otherworldly Magic')
        . '</strong><br>'
        . $this->t('Choose one primal cantrip to gain as an innate at-will spell.')
        . '</div>',
    ];

    $cantrip_options = [];
    $cantrip_cards = [];
    $cantrip_reference_cards = [];
    foreach ($this->characterManager->getSpellsByTradition('primal', 0) as $cantrip) {
      $cantrip_options[$cantrip['id']] = $cantrip['name'];
      $tags = ['Cantrip', 'Primal'];
      if (!empty($cantrip['school'])) {
        $tags[] = ucfirst((string) $cantrip['school']);
      }
      $facts = $this->extractSpellFacts($cantrip);
      $cantrip_cards[$cantrip['id']] = $this->buildOptionCardData(
        $cantrip['description'] ?? '',
        $tags,
        $facts,
      );
      $cantrip_reference_cards[] = [
        'title' => $cantrip['name'],
        'description' => $cantrip['description'] ?? '',
        'tags' => $tags,
        'facts' => $facts,
      ];
    }

    if (!array_key_exists($selected_cantrip, $cantrip_options)) {
      $selected_cantrip = '';
    }

    $container['feat_selections']['otherworldly-magic']['selected_cantrip'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your Otherworldly cantrip'),
      '#options' => $cantrip_options,
      '#default_value' => $selected_cantrip,
      '#required' => FALSE,
      '#description' => $this->t('This cantrip is fixed when chosen and becomes an innate at-will spell.'),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections']['otherworldly-magic'],
      'selected_cantrip',
      $cantrip_cards,
      'single'
    );
    $container['feat_selections']['otherworldly-magic']['reference'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section']],
      'heading' => [
        '#markup' => '<h4>' . $this->t('Primal cantrip reference') . '</h4>',
      ],
      'cards' => [
        '#markup' => $this->buildOptionDetailStackMarkup($cantrip_reference_cards),
      ],
    ];
  }

  /**
   * Adds Step 2 selection UI for Gnome Obsession.
   */
  private function buildGnomeObsessionSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['gnome-obsession'] ?? [];
    $selected_lore = (string) $form_state->getValue(
      ['feat_selections', 'gnome-obsession', 'selected_lore'],
      $stored_selection['selected_lore'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $container['feat_selections']['gnome-obsession'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['gnome-obsession']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Gnome Obsession')
        . '</strong><br>'
        . $this->t('Enter the Lore skill tied to your obsession, such as Forest Lore or Circus Lore.')
        . '</div>',
    ];
    $container['feat_selections']['gnome-obsession']['selected_lore'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Choose your obsession Lore'),
      '#default_value' => $selected_lore,
      '#required' => FALSE,
      '#maxlength' => 80,
      '#description' => $this->t('Enter a Lore skill name. If you omit “Lore,” it will be added automatically.'),
    ];
  }

  /**
   * Adds Step 2 selection UI for Natural Performer.
   */
  private function buildNaturalPerformerSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['natural-performer'] ?? [];
    $selected_specialty = (string) $form_state->getValue(
      ['feat_selections', 'natural-performer', 'specialty'],
      $stored_selection['specialty'] ?? ''
    );
    $options = $this->getNaturalPerformerOptions();

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    if (!array_key_exists($selected_specialty, $options)) {
      $selected_specialty = '';
    }

    $container['feat_selections']['natural-performer'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['natural-performer']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Natural Performer')
        . '</strong><br>'
        . $this->t('Choose your performance specialty. You become trained in Performance and gain a +1 circumstance bonus when performing with that specialty.')
        . '</div>',
    ];
    $container['feat_selections']['natural-performer']['specialty'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your performance specialty'),
      '#options' => $options,
      '#default_value' => $selected_specialty,
      '#required' => FALSE,
    ];
  }

  /**
   * Adds Step 2 selection UI for General Training.
   */
  private function buildGeneralTrainingSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['general-training'] ?? [];
    $selected_bonus_feat = (string) $form_state->getValue(
      ['feat_selections', 'general-training', 'bonus_general_feat'],
      $stored_selection['bonus_general_feat'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $feat_options = [];
    $feat_cards = [];
    foreach (CharacterManager::getGeneralFeats() as $feat) {
      $feat_options[$feat['id']] = $feat['name'];
      $feat_cards[$feat['id']] = $this->buildOptionCardData(
        $feat['benefit'] ?? '',
        $feat['traits'] ?? [],
        [
          (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
        ],
      );
    }

    if (!array_key_exists($selected_bonus_feat, $feat_options)) {
      $selected_bonus_feat = '';
    }

    $container['feat_selections']['general-training'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section', 'feat-selection-section']],
    ];
    $container['feat_selections']['general-training']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('General Training')
        . '</strong><br>'
        . $this->t('Choose one additional 1st-level general feat.')
        . '</div>',
    ];
    $container['feat_selections']['general-training']['bonus_general_feat'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your bonus general feat'),
      '#options' => $feat_options,
      '#default_value' => $selected_bonus_feat,
      '#required' => FALSE,
      '#description' => $this->t('This extra feat is granted by your ancestry and is added to your general feat list.'),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections']['general-training'],
      'bonus_general_feat',
      $feat_cards,
      'single'
    );
  }

  /**
   * Adds Step 2 selection UI for Elf Atavism.
   */
  private function buildElfAtavismSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['elf-atavism'] ?? [];
    $selected_feat = (string) $form_state->getValue(
      ['feat_selections', 'elf-atavism', 'selected_feat'],
      $stored_selection['selected_feat'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $feat_options = [];
    $feat_cards = [];
    foreach ($this->getElfAtavismFeatOptions() as $feat) {
      $feat_options[$feat['id']] = $feat['name'];
      $feat_cards[$feat['id']] = $this->buildOptionCardData(
        $feat['benefit'] ?? '',
        $feat['traits'] ?? [],
        [
          (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
        ],
      );
    }

    if (!array_key_exists($selected_feat, $feat_options)) {
      $selected_feat = '';
    }

    $container['feat_selections']['elf-atavism'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section', 'feat-selection-section']],
    ];
    $container['feat_selections']['elf-atavism']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Elf Atavism')
        . '</strong><br>'
        . $this->t('Choose one 1st-level elf ancestry feat to gain through your elven lineage.')
        . '</div>',
    ];
    $container['feat_selections']['elf-atavism']['selected_feat'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your elf ancestry feat'),
      '#options' => $feat_options,
      '#default_value' => $selected_feat,
      '#required' => FALSE,
      '#description' => $this->t('This feat is granted by Elf Atavism and is added to your ancestry feat list.'),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections']['elf-atavism'],
      'selected_feat',
      $feat_cards,
      'single'
    );
  }

  /**
   * Adds Step 2 selection UI for Multitalented.
   */
  private function buildMultitalentedSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['multitalented'] ?? [];
    $selected_skill = (string) $form_state->getValue(
      ['feat_selections', 'multitalented', 'selected_skill'],
      $stored_selection['selected_skill'] ?? ''
    );
    $selected_language = (string) $form_state->getValue(
      ['feat_selections', 'multitalented', 'selected_language'],
      $stored_selection['selected_language'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $skill_options = $this->getSkillTrainingOptions();
    if (!array_key_exists($selected_skill, $skill_options)) {
      $selected_skill = '';
    }

    $container['feat_selections']['multitalented'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['multitalented']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Multitalented')
        . '</strong><br>'
        . $this->t('Choose one skill to become trained in and one additional language to learn.')
        . '</div>',
    ];
    $container['feat_selections']['multitalented']['selected_skill'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your trained skill'),
      '#options' => $skill_options,
      '#default_value' => $selected_skill,
      '#empty_option' => $this->t('- Select a skill -'),
      '#description' => $this->t('Multitalented grants trained proficiency in the selected skill.'),
    ];
    $container['feat_selections']['multitalented']['selected_language'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Choose your additional language'),
      '#default_value' => $selected_language,
      '#maxlength' => 64,
      '#description' => $this->t('Enter the additional language granted by Multitalented.'),
    ];
  }

  /**
   * Adds Step 2 selection UI for Mixed Heritage Adaptability.
   */
  private function buildMixedHeritageAdaptabilitySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['mixed-heritage-adaptability'] ?? [];
    $selected_skill = (string) $form_state->getValue(
      ['feat_selections', 'mixed-heritage-adaptability', 'selected_skill'],
      $stored_selection['selected_skill'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $skill_options = $this->getSkillTrainingOptions();
    if (!array_key_exists($selected_skill, $skill_options)) {
      $selected_skill = '';
    }

    $container['feat_selections']['mixed-heritage-adaptability'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['mixed-heritage-adaptability']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Mixed Heritage Adaptability')
        . '</strong><br>'
        . $this->t('Choose the skill that gains your +1 circumstance bonus while you are trained in it.')
        . '</div>',
    ];
    $container['feat_selections']['mixed-heritage-adaptability']['selected_skill'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your adaptable skill'),
      '#options' => $skill_options,
      '#default_value' => $selected_skill,
      '#empty_option' => $this->t('- Select a skill -'),
      '#description' => $this->t('You can change this skill after daily preparations.'),
    ];
  }

  /**
   * Adds Step 2 selection UI for Orc Atavism.
   */
  private function buildOrcAtavismSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['orc-atavism'] ?? [];
    $selected_feat = (string) $form_state->getValue(
      ['feat_selections', 'orc-atavism', 'selected_feat'],
      $stored_selection['selected_feat'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $feat_options = [];
    $feat_cards = [];
    foreach ($this->getOrcAtavismFeatOptions() as $feat) {
      $feat_options[$feat['id']] = $feat['name'];
      $feat_cards[$feat['id']] = $this->buildOptionCardData(
        $feat['benefit'] ?? '',
        $feat['traits'] ?? [],
        [
          (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
        ],
      );
    }

    if (!array_key_exists($selected_feat, $feat_options)) {
      $selected_feat = '';
    }

    $container['feat_selections']['orc-atavism'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section', 'feat-selection-section']],
    ];
    $container['feat_selections']['orc-atavism']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Orc Atavism')
        . '</strong><br>'
        . $this->t('Choose one 1st-level orc ancestry feat to gain through your orc lineage.')
        . '</div>',
    ];
    $container['feat_selections']['orc-atavism']['selected_feat'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your orc ancestry feat'),
      '#options' => $feat_options,
      '#default_value' => $selected_feat,
      '#required' => FALSE,
      '#description' => $this->t('This feat is granted by Orc Atavism and is added to your ancestry feat list.'),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections']['orc-atavism'],
      'selected_feat',
      $feat_cards,
      'single'
    );
  }

  /**
   * Adds Step 2 selection UI for Draconic Ties.
   */
  private function buildDraconicTiesSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['draconic-ties'] ?? [];
    $selected_damage_type = (string) $form_state->getValue(
      ['feat_selections', 'draconic-ties', 'damage_type'],
      $stored_selection['damage_type'] ?? ''
    );

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $damage_type_options = $this->getDraconicDamageTypeOptions();
    if (!array_key_exists($selected_damage_type, $damage_type_options)) {
      $selected_damage_type = '';
    }

    $container['feat_selections']['draconic-ties'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['draconic-ties']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Draconic Ties')
        . '</strong><br>'
        . $this->t('Choose the draconic damage type that grants your minor resistance.')
        . '</div>',
    ];
    $container['feat_selections']['draconic-ties']['damage_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your draconic damage type'),
      '#options' => $damage_type_options,
      '#default_value' => $selected_damage_type,
      '#required' => FALSE,
      '#description' => $this->t('This choice determines the resistance granted by Draconic Ties.'),
    ];
  }

  /**
   * Adds Step 2 selection UI for Natural Skill.
   */
  private function buildNaturalSkillSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['natural-skill'] ?? [];
    $selected_skills = self::normalizeList($form_state->getValue(
      ['feat_selections', 'natural-skill', 'skills'],
      $stored_selection['skills'] ?? []
    ));

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $container['feat_selections']['natural-skill'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['natural-skill']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Natural Skill')
        . '</strong><br>'
        . $this->t('Choose two additional skills to become trained in.')
        . '</div>',
    ];
    $container['feat_selections']['natural-skill']['skills'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Choose your bonus trained skills'),
      '#options' => $this->getSkillTrainingOptions(),
      '#default_value' => $selected_skills,
      '#required' => FALSE,
      '#description' => $this->t('Select exactly two skills granted by Natural Skill.'),
    ];
  }

  /**
   * Adds Step 2 selection UI for Vengeful Hatred.
   */
  private function buildVengefulHatredSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['vengeful-hatred'] ?? [];
    $selected_target_type = (string) $form_state->getValue(
      ['feat_selections', 'vengeful-hatred', 'target_type'],
      $stored_selection['target_type'] ?? ''
    );
    $options = [
      'drow' => $this->t('Drow'),
      'duergar' => $this->t('Duergar'),
      'giant' => $this->t('Giant'),
      'orc' => $this->t('Orc'),
    ];

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    if (!array_key_exists($selected_target_type, $options)) {
      $selected_target_type = '';
    }

    $container['feat_selections']['vengeful-hatred'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['vengeful-hatred']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Vengeful Hatred')
        . '</strong><br>'
        . $this->t('Choose the enemy trait you have sworn to hate.')
        . '</div>',
    ];
    $container['feat_selections']['vengeful-hatred']['target_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your hated foe'),
      '#options' => $options,
      '#default_value' => $selected_target_type,
      '#required' => FALSE,
      '#description' => $this->t('You gain +1 circumstance damage per weapon die against creatures with the chosen trait.'),
    ];
  }

  /**
   * Adds Step 2 selection UI for Ancestral Longevity.
   */
  private function buildAncestralLongevitySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['ancestral-longevity'] ?? [];
    $selected_skills = self::normalizeList($form_state->getValue(
      ['feat_selections', 'ancestral-longevity', 'selected_skills'],
      $stored_selection['selected_skills'] ?? []
    ));

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $container['feat_selections']['ancestral-longevity'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['ancestral-longevity']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Ancestral Longevity')
        . '</strong><br>'
        . $this->t('Choose two skills to gain trained proficiency in until your next daily preparations.')
        . '</div>',
    ];
    $container['feat_selections']['ancestral-longevity']['selected_skills'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Choose your ancestral skills'),
      '#options' => $this->getSkillTrainingOptions(),
      '#default_value' => $selected_skills,
      '#required' => FALSE,
      '#description' => $this->t('Select exactly two skills granted temporarily by Ancestral Longevity.'),
    ];
  }

  /**
   * Adds Step 2 selection UI for Unconventional Weaponry.
   */
  private function buildUnconventionalWeaponrySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['unconventional-weaponry'] ?? [];
    $selected_weapon_id = (string) $form_state->getValue(
      ['feat_selections', 'unconventional-weaponry', 'selected_weapon_id'],
      $stored_selection['selected_weapon_id'] ?? ''
    );
    $weapon_options = CharacterManager::getUnconventionalWeaponOptions();

    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    if (!array_key_exists($selected_weapon_id, $weapon_options)) {
      $selected_weapon_id = '';
    }

    $container['feat_selections']['unconventional-weaponry'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['unconventional-weaponry']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Unconventional Weaponry')
        . '</strong><br>'
        . $this->t('Choose one uncommon weapon to gain access to and trained proficiency with it.')
        . '</div>',
    ];
    $container['feat_selections']['unconventional-weaponry']['selected_weapon_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your uncommon weapon'),
      '#options' => $weapon_options,
      '#default_value' => $selected_weapon_id,
      '#empty_option' => $this->t('- Select an uncommon weapon -'),
      '#description' => $this->t('This selection determines the uncommon weapon granted by Unconventional Weaponry.'),
    ];
  }

  /**
   * Adds Step 4 selection UI for Natural Ambition.
   */
  private function buildNaturalAmbitionSelectionSection(array &$form, FormStateInterface $form_state, array $character_data, string $selected_class, array $class_feats): void {
    $stored_selection = $character_data['feat_selections']['natural-ambition'] ?? [];
    $selected_bonus_feat = (string) $form_state->getValue(
      ['feat_selections', 'natural-ambition', 'bonus_class_feat'],
      $stored_selection['bonus_class_feat'] ?? ''
    );

    if (!isset($form['class_dynamic']['feat_selections']) || !is_array($form['class_dynamic']['feat_selections'])) {
      $form['class_dynamic']['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $feat_options = [];
    $feat_cards = [];
    foreach ($class_feats as $feat) {
      $feat_options[$feat['id']] = $feat['name'];
      $feat_cards[$feat['id']] = $this->buildOptionCardData(
        $feat['benefit'] ?? '',
        $feat['traits'] ?? [],
        [
          (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
        ],
      );
    }

    if (!array_key_exists($selected_bonus_feat, $feat_options)) {
      $selected_bonus_feat = '';
    }

    $form['class_dynamic']['feat_selections']['natural-ambition'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section', 'feat-selection-section']],
    ];
    $form['class_dynamic']['feat_selections']['natural-ambition']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Natural Ambition')
        . '</strong><br>'
        . $this->t('Choose one additional 1st-level @class class feat.', ['@class' => ucfirst($selected_class)])
        . '</div>',
    ];
    $form['class_dynamic']['feat_selections']['natural-ambition']['bonus_class_feat'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your bonus class feat'),
      '#options' => $feat_options,
      '#default_value' => $selected_bonus_feat,
      '#required' => FALSE,
      '#description' => $this->t('This extra feat is granted by your ancestry and is added to your class feat list.'),
    ];
    $this->attachOptionCardSettings(
      $form['class_dynamic']['feat_selections']['natural-ambition'],
      'bonus_class_feat',
      $feat_cards,
      'single'
    );
  }

  /**
   * Adds Step 4 selection UI for Eldritch Trickster's free dedication.
   */
  private function buildEldritchTricksterSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $options = $this->getEldritchTricksterDedicationOptions();
    $selected_dedication = trim((string) $form_state->getValue(
      ['feat_selections', 'eldritch-trickster-racket', 'selected_dedication'],
      $character_data['feat_selections']['eldritch-trickster-racket']['selected_dedication'] ?? ''
    ));
    if (!array_key_exists($selected_dedication, $options)) {
      $selected_dedication = '';
    }

    $container['feat_selections']['eldritch-trickster-racket'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['eldritch-trickster-racket']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Eldritch Trickster Racket')
        . '</strong><br>'
        . $this->t('Choose the free multiclass spellcasting dedication granted by your racket.')
        . '</div>',
    ];
    $container['feat_selections']['eldritch-trickster-racket']['selected_dedication'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your spellcasting dedication'),
      '#options' => $options,
      '#default_value' => $selected_dedication,
      '#required' => FALSE,
      '#description' => $this->t('This dedication is granted for free at level 1 and is added to your character state.'),
    ];
  }

  /**
   * Adds Step 4 selection UI for Mastermind's extra knowledge skill.
   */
  private function buildMastermindSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $options = $this->getMastermindKnowledgeSkillOptions();
    $selected_skill = trim((string) $form_state->getValue(
      ['feat_selections', 'mastermind-racket', 'selected_skill'],
      $character_data['feat_selections']['mastermind-racket']['selected_skill'] ?? ''
    ));
    if (!array_key_exists($selected_skill, $options)) {
      $selected_skill = '';
    }

    $container['feat_selections']['mastermind-racket'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['mastermind-racket']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Mastermind Racket')
        . '</strong><br>'
        . $this->t('Choose the additional knowledge skill granted by your racket alongside Society.')
        . '</div>',
    ];
    $container['feat_selections']['mastermind-racket']['selected_skill'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your additional knowledge skill'),
      '#options' => $options,
      '#default_value' => $selected_skill,
      '#empty_option' => $this->t('- Select a knowledge skill -'),
      '#description' => $this->t('Mastermind grants Society automatically plus one additional Recall Knowledge skill.'),
    ];
  }

  /**
   * Adds Step 6 selection UI for Specialty Crafting.
   */
  private function buildSpecialtyCraftingSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }
    $container['feat_selections']['specialty-crafting'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['specialty-crafting']['specialty'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your crafting specialty'),
      '#options' => $this->getSpecialtyCraftingOptions(),
      '#default_value' => (string) $form_state->getValue(
        ['feat_selections', 'specialty-crafting', 'specialty'],
        $character_data['feat_selections']['specialty-crafting']['specialty'] ?? ''
      ),
      '#empty_option' => $this->t('- Select a specialty -'),
      '#description' => $this->t('Your bonus applies when crafting items in this specialty.'),
    ];
  }

  /**
   * Adds Step 6 selection UI for Virtuosic Performer.
   */
  private function buildVirtuosicPerformerSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }
    $container['feat_selections']['virtuosic-performer'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['virtuosic-performer']['specialty'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your performance specialty'),
      '#options' => $this->getPerformanceSpecialtyOptions(),
      '#default_value' => (string) $form_state->getValue(
        ['feat_selections', 'virtuosic-performer', 'specialty'],
        $character_data['feat_selections']['virtuosic-performer']['specialty'] ?? ''
      ),
      '#empty_option' => $this->t('- Select a specialty -'),
      '#description' => $this->t('Your bonus applies when performing with this specialty.'),
    ];
  }

  /**
   * Adds Step 6 selection UI for Canny Acumen.
   */
  private function buildCannyAcumenSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }
    $container['feat_selections']['canny-acumen'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['canny-acumen']['selected_proficiency'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the proficiency to improve'),
      '#options' => $this->getCannyAcumenOptions(),
      '#default_value' => (string) $form_state->getValue(
        ['feat_selections', 'canny-acumen', 'selected_proficiency'],
        $character_data['feat_selections']['canny-acumen']['selected_proficiency'] ?? ''
      ),
      '#empty_option' => $this->t('- Select a proficiency -'),
      '#description' => $this->t('Choose Perception, Fortitude, Reflex, or Will. Canny Acumen applies to the selected proficiency.'),
    ];
  }

  /**
   * Adds Step 6 selection UI for Adopted Ancestry.
   */
  private function buildAdoptedAncestrySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $options = $this->getAdoptedAncestryOptions($character_data);
    $selected_ancestry = (string) $form_state->getValue(
      ['feat_selections', 'adopted-ancestry', 'selected_ancestry'],
      $character_data['feat_selections']['adopted-ancestry']['selected_ancestry'] ?? ''
    );
    if (!array_key_exists($selected_ancestry, $options)) {
      $selected_ancestry = '';
    }

    $container['feat_selections']['adopted-ancestry'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['adopted-ancestry']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Adopted Ancestry')
        . '</strong><br>'
        . $this->t('Choose another ancestry whose ancestry feat pool you can access when you gain future ancestry feat slots.')
        . '</div>',
    ];
    $container['feat_selections']['adopted-ancestry']['selected_ancestry'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your adopted ancestry'),
      '#options' => $options,
      '#default_value' => $selected_ancestry,
      '#empty_option' => $this->t('- Select an ancestry -'),
      '#description' => $this->t('Future ancestry feat choices can use feats from the selected ancestry in addition to your own ancestry pool.'),
    ];
  }

  /**
   * Adds Step 6 selection UI for Domain Initiate.
   */
  private function buildDomainInitiateSelectionSection(array &$container, FormStateInterface $form_state, array $character_data, string $selected_deity): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $stored_selection = $character_data['feat_selections']['domain-initiate'] ?? [];
    $selected_domain = (string) $form_state->getValue(
      ['feat_selections', 'domain-initiate', 'selected_domain'],
      $stored_selection['selected_domain'] ?? ''
    );

    $domain_options = [];
    foreach ($this->characterManager->getDeityDomainsForInput($selected_deity) as $domain) {
      $domain_options[$domain] = ucwords(str_replace('-', ' ', $domain));
    }

    if (!array_key_exists($selected_domain, $domain_options)) {
      $selected_domain = '';
    }

    $container['feat_selections']['domain-initiate'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['domain-initiate']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Domain Initiate')
        . '</strong><br>'
        . (!empty($domain_options)
          ? $this->t('Choose one domain from your deity\'s domain list to gain its initial domain spell as a focus spell.')
          : $this->t('Enter a supported deity above to choose a domain for Domain Initiate.'))
        . '</div>',
    ];
    $container['feat_selections']['domain-initiate']['selected_domain'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose your initial domain'),
      '#options' => $domain_options,
      '#default_value' => $selected_domain,
      '#empty_option' => $this->t('- Select a domain -'),
      '#description' => !empty($domain_options)
        ? $this->t('The available domains come from your selected deity.')
        : $this->t('No deity domains are available until you enter a valid deity ID or name.'),
      '#disabled' => empty($domain_options),
    ];
  }

  /**
   * Adds Step 6 selection UI for Weapon Proficiency.
   */
  private function buildWeaponProficiencySelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $grant_state = CharacterManager::resolveWeaponProficiencyGrant($character_data, 0);
    $container['feat_selections']['weapon-proficiency'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];

    if (($grant_state['mode'] ?? '') === 'advanced_choice') {
      $weapon_options = CharacterManager::getAdvancedWeaponOptions();
      $selected_weapon_id = (string) $form_state->getValue(
        ['feat_selections', 'weapon-proficiency', 'selected_weapon_id'],
        $character_data['feat_selections']['weapon-proficiency']['selected_weapon_id'] ?? ''
      );
      if (!array_key_exists($selected_weapon_id, $weapon_options)) {
        $selected_weapon_id = '';
      }

      $container['feat_selections']['weapon-proficiency']['intro'] = [
        '#markup' => '<div class="spell-help"><strong>'
          . $this->t('Weapon Proficiency')
          . '</strong><br>'
          . $this->t('Your class is already trained in simple and martial weapons, so choose one advanced weapon to become trained in.')
          . '</div>',
      ];
      $container['feat_selections']['weapon-proficiency']['selected_weapon_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Choose your advanced weapon'),
        '#options' => $weapon_options,
        '#default_value' => $selected_weapon_id,
        '#empty_option' => $this->t('- Select an advanced weapon -'),
        '#description' => $this->t('Weapon Proficiency grants trained proficiency with the selected advanced weapon.'),
      ];
      return;
    }

    if (($grant_state['mode'] ?? '') === 'no_upgrade') {
      $container['feat_selections']['weapon-proficiency']['intro'] = [
        '#markup' => '<div class="spell-help"><strong>'
          . $this->t('Weapon Proficiency')
          . '</strong><br>'
          . $this->t('Your current class training already covers simple, martial, and broad advanced weapon access, so this feat would not grant an additional benefit here.')
          . '</div>',
      ];
      return;
    }

    $granted_target = (string) ($grant_state['granted_target'] ?? 'martial');
    $container['feat_selections']['weapon-proficiency']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Weapon Proficiency')
        . '</strong><br>'
        . $this->t('No extra selection is required. This feat will grant trained proficiency in @category weapons for your current class.', [
          '@category' => $granted_target,
        ])
        . '</div>',
    ];
  }

  /**
   * Adds Step 4 selection UI for Monster Hunter.
   */
  private function buildMonsterHunterSelectionSection(array &$container, FormStateInterface $form_state, array $character_data): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }
    $container['feat_selections']['monster-hunter'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['monster-hunter']['selected_monster_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the creature type you hunt'),
      '#options' => $this->getMonsterHunterOptions(),
      '#default_value' => (string) $form_state->getValue(
        ['feat_selections', 'monster-hunter', 'selected_monster_type'],
        $character_data['feat_selections']['monster-hunter']['selected_monster_type'] ?? ''
      ),
      '#empty_option' => $this->t('- Select a creature type -'),
      '#description' => $this->t('Monster Hunter applies to the chosen creature trait for Recall Knowledge and Investigation checks.'),
    ];
  }

  /**
   * Adds Step 4 selection UI for Animal Companion species and name.
   */
  private function buildAnimalCompanionSelectionSection(array &$container, FormStateInterface $form_state, array $character_data, string $source_feat_id): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $stored_selection = $character_data['feat_selections'][$source_feat_id] ?? [];
    $selected_species = strtolower(trim((string) $form_state->getValue(
      ['feat_selections', $source_feat_id, 'selected_companion_species'],
      $stored_selection['selected_companion_species'] ?? $stored_selection['species_id'] ?? ''
    )));
    $companion_name = (string) $form_state->getValue(
      ['feat_selections', $source_feat_id, 'name'],
      $stored_selection['name'] ?? ''
    );

    $container['feat_selections'][$source_feat_id] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections'][$source_feat_id]['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Animal Companion')
        . '</strong><br>'
        . $this->t('Choose the companion species granted by this feat or druid order. This becomes your allied NPC companion.')
        . '</div>',
    ];

    $species_options = [];
    $species_cards = [];
    foreach (CharacterManager::ANIMAL_COMPANIONS['species'] ?? [] as $species_id => $species) {
      $species_options[$species_id] = $species['name'] ?? ucfirst((string) $species_id);
      $species_cards[$species_id] = $this->buildOptionCardData(
        $species['description'] ?? '',
        $species['traits'] ?? [],
        [
          (string) $this->t('Size') => (string) ($species['size'] ?? 'Medium'),
          (string) $this->t('Speed') => (string) ($species['speed']['walk'] ?? 25) . ' ft.',
          (string) $this->t('Support') => (string) ($species['support_benefit'] ?? ''),
        ],
      );
    }

    if (!array_key_exists($selected_species, $species_options)) {
      $selected_species = '';
    }

    $container['feat_selections'][$source_feat_id]['selected_companion_species'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose companion species'),
      '#options' => $species_options,
      '#default_value' => $selected_species,
      '#required' => FALSE,
      '#description' => $this->t('Your choice determines the companion NPC that accompanies this character.'),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections'][$source_feat_id],
      'selected_companion_species',
      $species_cards,
      'single'
    );

    $container['feat_selections'][$source_feat_id]['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Companion name'),
      '#default_value' => $companion_name,
      '#required' => FALSE,
      '#maxlength' => 80,
      '#description' => $this->t('Optional. Leave blank to use the default species name.'),
    ];
  }

  /**
   * Adds Step 4 selection UI for Staff Nexus.
   */
  private function buildStaffNexusSelectionSection(array &$container, FormStateInterface $form_state, array $character_data, string $tradition): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $options = $this->getStaffNexusSpellOptions($form_state, $character_data, $tradition);
    $selected_cantrip = (string) $form_state->getValue(
      ['feat_selections', 'staff-nexus', 'selected_cantrip'],
      $character_data['feat_selections']['staff-nexus']['selected_cantrip'] ?? ''
    );
    $selected_spell = (string) $form_state->getValue(
      ['feat_selections', 'staff-nexus', 'selected_spell'],
      $character_data['feat_selections']['staff-nexus']['selected_spell'] ?? ''
    );
    if (!array_key_exists($selected_cantrip, $options['cantrips'])) {
      $selected_cantrip = '';
    }
    if (!array_key_exists($selected_spell, $options['spells'])) {
      $selected_spell = '';
    }

    $container['feat_selections']['staff-nexus'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections']['staff-nexus']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Staff Nexus')
        . '</strong><br>'
        . $this->t('Choose one of your selected cantrips and one of your selected 1st-rank spellbook spells to embed in your makeshift staff.')
        . '</div>',
    ];
    $container['feat_selections']['staff-nexus']['selected_cantrip'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the staff cantrip'),
      '#options' => $options['cantrips'],
      '#default_value' => $selected_cantrip,
      '#empty_option' => $this->t('- Select a cantrip -'),
      '#description' => $this->t('The makeshift staff contains one cantrip from your chosen arcane cantrips.'),
      '#disabled' => empty($options['cantrips']),
    ];
    $container['feat_selections']['staff-nexus']['selected_spell'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose the staff 1st-rank spell'),
      '#options' => $options['spells'],
      '#default_value' => $selected_spell,
      '#empty_option' => $this->t('- Select a 1st-rank spell -'),
      '#description' => $this->t('The makeshift staff contains one selected 1st-rank spell from your spellbook.'),
      '#disabled' => empty($options['spells']),
    ];
  }

  /**
   * Validates First World Magic cantrip selection.
   */
  private function validateFirstWorldMagicSelection(FormStateInterface $form_state): void {
    $selected_cantrip = trim((string) $form_state->getValue(['feat_selections', 'first-world-magic', 'selected_cantrip'], ''));
    if ($selected_cantrip === '') {
      $form_state->setErrorByName(
        'feat_selections][first-world-magic][selected_cantrip',
        $this->t('Choose a cantrip for First World Magic.')
      );
      return;
    }
    $valid_cantrip_ids = array_map(
      static fn(array $spell): string => (string) ($spell['id'] ?? ''),
      $this->characterManager->getSpellsByTradition('primal', 0)
    );
    if (!in_array($selected_cantrip, $valid_cantrip_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][first-world-magic][selected_cantrip',
        $this->t('Choose a valid primal cantrip for First World Magic.')
      );
    }
  }

  /**
   * Validates Otherworldly Magic cantrip selection.
   */
  private function validateOtherworldlyMagicSelection(FormStateInterface $form_state): void {
    $selected_cantrip = trim((string) $form_state->getValue(['feat_selections', 'otherworldly-magic', 'selected_cantrip'], ''));
    if ($selected_cantrip === '') {
      $form_state->setErrorByName(
        'feat_selections][otherworldly-magic][selected_cantrip',
        $this->t('Choose a cantrip for Otherworldly Magic.')
      );
      return;
    }
    $valid_cantrip_ids = array_map(
      static fn(array $spell): string => (string) ($spell['id'] ?? ''),
      $this->characterManager->getSpellsByTradition('primal', 0)
    );
    if (!in_array($selected_cantrip, $valid_cantrip_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][otherworldly-magic][selected_cantrip',
        $this->t('Choose a valid primal cantrip for Otherworldly Magic.')
      );
    }
  }

  /**
   * Validates Natural Ambition bonus feat selection.
   */
  private function validateNaturalAmbitionSelection(FormStateInterface $form_state, string $selected_class): void {
    $selected_bonus_feat = trim((string) $form_state->getValue(['feat_selections', 'natural-ambition', 'bonus_class_feat'], ''));
    if ($selected_bonus_feat === '') {
      $form_state->setErrorByName(
        'feat_selections][natural-ambition][bonus_class_feat',
        $this->t('Choose a bonus class feat for Natural Ambition.')
      );
      return;
    }
    $valid_feat_ids = array_map(
      static fn(array $feat): string => (string) ($feat['id'] ?? ''),
      CharacterManager::getClassFeats($selected_class)
    );
    if (!in_array($selected_bonus_feat, $valid_feat_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][natural-ambition][bonus_class_feat',
        $this->t('Choose a valid class feat for Natural Ambition.')
      );
    }
  }

  /**
   * Validates General Training bonus feat selection.
   */
  private function validateGeneralTrainingSelection(FormStateInterface $form_state): void {
    $selected_bonus_feat = trim((string) $form_state->getValue(['feat_selections', 'general-training', 'bonus_general_feat'], ''));
    if ($selected_bonus_feat === '') {
      $form_state->setErrorByName(
        'feat_selections][general-training][bonus_general_feat',
        $this->t('Choose a bonus general feat for General Training.')
      );
      return;
    }
    $valid_feat_ids = array_map(
      static fn(array $feat): string => (string) ($feat['id'] ?? ''),
      CharacterManager::getGeneralFeats()
    );
    if (!in_array($selected_bonus_feat, $valid_feat_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][general-training][bonus_general_feat',
        $this->t('Choose a valid general feat for General Training.')
      );
    }
  }

  /**
   * Validates Elf Atavism feat selection.
   */
  private function validateElfAtavismSelection(FormStateInterface $form_state): void {
    $selected_feat = trim((string) $form_state->getValue(['feat_selections', 'elf-atavism', 'selected_feat'], ''));
    if ($selected_feat === '') {
      $form_state->setErrorByName(
        'feat_selections][elf-atavism][selected_feat',
        $this->t('Choose an elf ancestry feat for Elf Atavism.')
      );
      return;
    }
    $valid_feat_ids = array_map(
      static fn(array $feat): string => (string) ($feat['id'] ?? ''),
      $this->getElfAtavismFeatOptions()
    );
    if (!in_array($selected_feat, $valid_feat_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][elf-atavism][selected_feat',
        $this->t('Choose a valid elf ancestry feat for Elf Atavism.')
      );
    }
  }

  /**
   * Validates Multitalented skill and language selections.
   */
  private function validateMultitalentedSelection(FormStateInterface $form_state): void {
    $selected_skill = trim((string) $form_state->getValue(['feat_selections', 'multitalented', 'selected_skill'], ''));
    $selected_language = trim((string) $form_state->getValue(['feat_selections', 'multitalented', 'selected_language'], ''));
    if ($selected_skill === '' || !array_key_exists($selected_skill, $this->getSkillTrainingOptions())) {
      $form_state->setErrorByName(
        'feat_selections][multitalented][selected_skill',
        $this->t('Choose a valid trained skill for Multitalented.')
      );
    }
    if ($selected_language === '') {
      $form_state->setErrorByName(
        'feat_selections][multitalented][selected_language',
        $this->t('Enter an additional language for Multitalented.')
      );
    }
  }

  /**
   * Validates Mixed Heritage Adaptability skill selection.
   */
  private function validateMixedHeritageAdaptabilitySelection(FormStateInterface $form_state): void {
    $selected_skill = trim((string) $form_state->getValue(['feat_selections', 'mixed-heritage-adaptability', 'selected_skill'], ''));
    if ($selected_skill === '' || !array_key_exists($selected_skill, $this->getSkillTrainingOptions())) {
      $form_state->setErrorByName(
        'feat_selections][mixed-heritage-adaptability][selected_skill',
        $this->t('Choose a valid skill for Mixed Heritage Adaptability.')
      );
    }
  }

  /**
   * Validates Orc Atavism feat selection.
   */
  private function validateOrcAtavismSelection(FormStateInterface $form_state): void {
    $selected_feat = trim((string) $form_state->getValue(['feat_selections', 'orc-atavism', 'selected_feat'], ''));
    if ($selected_feat === '') {
      $form_state->setErrorByName(
        'feat_selections][orc-atavism][selected_feat',
        $this->t('Choose an orc ancestry feat for Orc Atavism.')
      );
      return;
    }
    $valid_feat_ids = array_map(
      static fn(array $feat): string => (string) ($feat['id'] ?? ''),
      $this->getOrcAtavismFeatOptions()
    );
    if (!in_array($selected_feat, $valid_feat_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][orc-atavism][selected_feat',
        $this->t('Choose a valid orc ancestry feat for Orc Atavism.')
      );
    }
  }

  /**
   * Validates Draconic Ties damage type selection.
   */
  private function validateDraconicTiesSelection(FormStateInterface $form_state): void {
    $selected_damage_type = trim((string) $form_state->getValue(['feat_selections', 'draconic-ties', 'damage_type'], ''));
    if ($selected_damage_type === '' || !array_key_exists($selected_damage_type, $this->getDraconicDamageTypeOptions())) {
      $form_state->setErrorByName(
        'feat_selections][draconic-ties][damage_type',
        $this->t('Choose a draconic damage type for Draconic Ties.')
      );
    }
  }

  /**
   * Validates Natural Skill training selections.
   */
  private function validateNaturalSkillSelection(FormStateInterface $form_state): void {
    $selected_skills = self::normalizeList($form_state->getValue(['feat_selections', 'natural-skill', 'skills'], []));
    if (count($selected_skills) !== 2) {
      $form_state->setErrorByName(
        'feat_selections][natural-skill][skills',
        $this->t('Choose exactly two bonus skills for Natural Skill.')
      );
      return;
    }
    $valid_skill_ids = array_keys($this->getSkillTrainingOptions());
    foreach ($selected_skills as $skill) {
      if (!in_array($skill, $valid_skill_ids, TRUE)) {
        $form_state->setErrorByName(
          'feat_selections][natural-skill][skills',
          $this->t('Choose valid bonus skills for Natural Skill.')
        );
        return;
      }
    }
  }

  /**
   * Validates Unconventional Weaponry selection.
   */
  private function validateUnconventionalWeaponrySelection(FormStateInterface $form_state): void {
    $selected_weapon_id = trim((string) $form_state->getValue(['feat_selections', 'unconventional-weaponry', 'selected_weapon_id'], ''));
    $weapon_options = CharacterManager::getUnconventionalWeaponOptions();
    if ($selected_weapon_id === '' || !array_key_exists($selected_weapon_id, $weapon_options)) {
      $form_state->setErrorByName(
        'feat_selections][unconventional-weaponry][selected_weapon_id',
        $this->t('Choose a valid uncommon weapon for Unconventional Weaponry.')
      );
    }
  }

  /**
   * Validates Vengeful Hatred target selection.
   */
  private function validateVengefulHatredSelection(FormStateInterface $form_state): void {
    $selected_target_type = trim((string) $form_state->getValue(['feat_selections', 'vengeful-hatred', 'target_type'], ''));
    if ($selected_target_type === '' || !in_array($selected_target_type, ['drow', 'duergar', 'giant', 'orc'], TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][vengeful-hatred][target_type',
        $this->t('Choose a hated foe for Vengeful Hatred.')
      );
    }
  }

  /**
   * Validates Ancestral Longevity skill selections.
   */
  private function validateAncestralLongevitySelection(FormStateInterface $form_state): void {
    $selected_skills = self::normalizeList($form_state->getValue(['feat_selections', 'ancestral-longevity', 'selected_skills'], []));
    if (count($selected_skills) !== 2) {
      $form_state->setErrorByName(
        'feat_selections][ancestral-longevity][selected_skills',
        $this->t('Choose exactly two skills for Ancestral Longevity.')
      );
      return;
    }
    $valid_skill_ids = array_keys($this->getSkillTrainingOptions());
    foreach ($selected_skills as $skill) {
      if (!in_array($skill, $valid_skill_ids, TRUE)) {
        $form_state->setErrorByName(
          'feat_selections][ancestral-longevity][selected_skills',
          $this->t('Choose valid skills for Ancestral Longevity.')
        );
        return;
      }
    }
  }

  /**
   * Validates Gnome Obsession lore selection.
   */
  private function validateGnomeObsessionSelection(FormStateInterface $form_state): void {
    $selected_lore = $this->normalizeLoreSkillName((string) $form_state->getValue(['feat_selections', 'gnome-obsession', 'selected_lore'], ''));
    if ($selected_lore === '') {
      $form_state->setErrorByName(
        'feat_selections][gnome-obsession][selected_lore',
        $this->t('Choose a Lore skill for Gnome Obsession.')
      );
    }
  }

  /**
   * Validates Natural Performer specialty selection.
   */
  private function validateNaturalPerformerSelection(FormStateInterface $form_state): void {
    $selected_specialty = trim((string) $form_state->getValue(['feat_selections', 'natural-performer', 'specialty'], ''));
    if ($selected_specialty === '' || !array_key_exists($selected_specialty, $this->getNaturalPerformerOptions())) {
      $form_state->setErrorByName(
        'feat_selections][natural-performer][specialty',
        $this->t('Choose a performance specialty for Natural Performer.')
      );
    }
  }

  /**
   * Validates Specialty Crafting selection.
   */
  private function validateSpecialtyCraftingSelection(FormStateInterface $form_state): void {
    $selected_specialty = trim((string) $form_state->getValue(['feat_selections', 'specialty-crafting', 'specialty'], ''));
    if ($selected_specialty === '' || !array_key_exists($selected_specialty, $this->getSpecialtyCraftingOptions())) {
      $form_state->setErrorByName(
        'feat_selections][specialty-crafting][specialty',
        $this->t('Choose a crafting specialty for Specialty Crafting.')
      );
    }
  }

  /**
   * Validates Virtuosic Performer selection.
   */
  private function validateVirtuosicPerformerSelection(FormStateInterface $form_state): void {
    $selected_specialty = trim((string) $form_state->getValue(['feat_selections', 'virtuosic-performer', 'specialty'], ''));
    if ($selected_specialty === '' || !array_key_exists($selected_specialty, $this->getPerformanceSpecialtyOptions())) {
      $form_state->setErrorByName(
        'feat_selections][virtuosic-performer][specialty',
        $this->t('Choose a performance specialty for Virtuosic Performer.')
      );
    }
  }

  /**
   * Validates Canny Acumen selection.
   */
  private function validateCannyAcumenSelection(FormStateInterface $form_state): void {
    $selected_proficiency = trim((string) $form_state->getValue(['feat_selections', 'canny-acumen', 'selected_proficiency'], ''));
    if ($selected_proficiency === '' || !array_key_exists($selected_proficiency, $this->getCannyAcumenOptions())) {
      $form_state->setErrorByName(
        'feat_selections][canny-acumen][selected_proficiency',
        $this->t('Choose Perception or a save for Canny Acumen.')
      );
    }
  }

  /**
   * Validates Adopted Ancestry selection.
   */
  private function validateAdoptedAncestrySelection(FormStateInterface $form_state, array $character_data): void {
    $selected_ancestry = trim((string) $form_state->getValue(['feat_selections', 'adopted-ancestry', 'selected_ancestry'], ''));
    $options = $this->getAdoptedAncestryOptions($character_data);
    if ($selected_ancestry === '' || !array_key_exists($selected_ancestry, $options)) {
      $form_state->setErrorByName(
        'feat_selections][adopted-ancestry][selected_ancestry',
        $this->t('Choose a valid adopted ancestry for Adopted Ancestry.')
      );
    }
  }

  /**
   * Validates Staff Nexus spell selections.
   */
  private function validateStaffNexusSelection(FormStateInterface $form_state, string $selected_class): void {
    if ($selected_class !== 'wizard') {
      $form_state->setErrorByName('class_feat', $this->t('Staff Nexus is only available to wizards.'));
      return;
    }

    $options = $this->getStaffNexusSpellOptions($form_state, [], 'arcane');
    $selected_cantrip = trim((string) $form_state->getValue(['feat_selections', 'staff-nexus', 'selected_cantrip'], ''));
    $selected_spell = trim((string) $form_state->getValue(['feat_selections', 'staff-nexus', 'selected_spell'], ''));

    if ($selected_cantrip === '' || !array_key_exists($selected_cantrip, $options['cantrips'])) {
      $form_state->setErrorByName(
        'feat_selections][staff-nexus][selected_cantrip',
        $this->t('Choose one of your selected cantrips for Staff Nexus.')
      );
    }
    if ($selected_spell === '' || !array_key_exists($selected_spell, $options['spells'])) {
      $form_state->setErrorByName(
        'feat_selections][staff-nexus][selected_spell',
        $this->t('Choose one of your selected 1st-rank spellbook spells for Staff Nexus.')
      );
    }
  }

  /**
   * Validates Monster Hunter selection.
   */
  private function validateMonsterHunterSelection(FormStateInterface $form_state): void {
    $selected_monster_type = trim((string) $form_state->getValue(['feat_selections', 'monster-hunter', 'selected_monster_type'], ''));
    if ($selected_monster_type === '' || !array_key_exists($selected_monster_type, $this->getMonsterHunterOptions())) {
      $form_state->setErrorByName(
        'feat_selections][monster-hunter][selected_monster_type',
        $this->t('Choose a creature type for Monster Hunter.')
      );
    }
  }

  /**
   * Validates Eldritch Trickster dedication selection.
   */
  private function validateEldritchTricksterSelection(FormStateInterface $form_state, string $selected_class): void {
    if ($selected_class !== 'rogue') {
      $form_state->setErrorByName('class_feat', $this->t('Eldritch Trickster is only available to rogues.'));
      return;
    }

    $selected_dedication = trim((string) $form_state->getValue(['feat_selections', 'eldritch-trickster-racket', 'selected_dedication'], ''));
    if ($selected_dedication === '' || !array_key_exists($selected_dedication, $this->getEldritchTricksterDedicationOptions())) {
      $form_state->setErrorByName(
        'feat_selections][eldritch-trickster-racket][selected_dedication',
        $this->t('Choose a valid spellcasting dedication for Eldritch Trickster.')
      );
    }
  }

  /**
   * Validates Mastermind knowledge skill selection.
   */
  private function validateMastermindSelection(FormStateInterface $form_state, string $selected_class): void {
    if ($selected_class !== 'rogue') {
      $form_state->setErrorByName('class_feat', $this->t('Mastermind is only available to rogues.'));
      return;
    }

    $selected_skill = trim((string) $form_state->getValue(['feat_selections', 'mastermind-racket', 'selected_skill'], ''));
    if ($selected_skill === '' || !array_key_exists($selected_skill, $this->getMastermindKnowledgeSkillOptions())) {
      $form_state->setErrorByName(
        'feat_selections][mastermind-racket][selected_skill',
        $this->t('Choose a valid knowledge skill for Mastermind.')
      );
    }
  }

  /**
   * Validates Animal Companion species selection.
   */
  private function validateAnimalCompanionSelection(FormStateInterface $form_state, string $source_feat_id): void {
    $selected_species = strtolower(trim((string) $form_state->getValue(
      ['feat_selections', $source_feat_id, 'selected_companion_species'],
      ''
    )));
    if ($selected_species === '' || !isset(CharacterManager::ANIMAL_COMPANIONS['species'][$selected_species])) {
      $form_state->setErrorByName(
        'feat_selections][' . $source_feat_id . '][selected_companion_species',
        $this->t('Choose a valid animal companion species.')
      );
    }
  }

  /**
   * Validates Armor Proficiency against the selected class.
   */
  private function validateArmorProficiencySelection(FormStateInterface $form_state, array $character_data): void {
    if ($this->resolveArmorProficiencyTarget($character_data) === NULL) {
      $form_state->setErrorByName(
        'general_feat',
        $this->t('Armor Proficiency does not grant an additional armor tier for your current class.')
      );
    }
  }

  /**
   * Validates Weapon Proficiency against the selected class state.
   */
  private function validateWeaponProficiencySelection(FormStateInterface $form_state, array $character_data): void {
    $grant_state = CharacterManager::resolveWeaponProficiencyGrant($character_data, 0);
    if (($grant_state['mode'] ?? '') === 'no_upgrade') {
      $form_state->setErrorByName(
        'general_feat',
        $this->t('Weapon Proficiency does not grant an additional weapon training benefit for your current class.')
      );
      return;
    }

    if (($grant_state['mode'] ?? '') !== 'advanced_choice') {
      return;
    }

    $selected_weapon_id = trim((string) $form_state->getValue(['feat_selections', 'weapon-proficiency', 'selected_weapon_id'], ''));
    $weapon_options = CharacterManager::getAdvancedWeaponOptions();
    if ($selected_weapon_id === '' || !array_key_exists($selected_weapon_id, $weapon_options)) {
      $form_state->setErrorByName(
        'feat_selections][weapon-proficiency][selected_weapon_id',
        $this->t('Choose a valid advanced weapon for Weapon Proficiency.')
      );
    }
  }

  /**
   * Validates Domain Initiate against the selected deity.
   */
  private function validateDomainInitiateSelection(FormStateInterface $form_state, string $selected_deity): void {
    $valid_domains = $this->characterManager->getDeityDomainsForInput($selected_deity);
    if ($valid_domains === []) {
      $form_state->setErrorByName(
        'deity',
        $this->t('Enter a valid deity with domains before choosing a domain for Domain Initiate.')
      );
      return;
    }

    $selected_domain = trim((string) $form_state->getValue(['feat_selections', 'domain-initiate', 'selected_domain'], ''));
    if ($selected_domain === '' || !in_array($selected_domain, $valid_domains, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][domain-initiate][selected_domain',
        $this->t('Choose a valid domain from your deity\'s domain list for Domain Initiate.')
      );
    }
  }

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
    return [
      'alchemy' => $this->t('Alchemy'),
      'artistry' => $this->t('Artistry'),
      'bookmaking' => $this->t('Bookmaking'),
      'glassmaking' => $this->t('Glassmaking'),
      'leatherworking' => $this->t('Leatherworking'),
      'pottery' => $this->t('Pottery'),
      'shipbuilding' => $this->t('Shipbuilding'),
      'stonemasonry' => $this->t('Stonemasonry'),
      'tailoring' => $this->t('Tailoring'),
      'woodworking' => $this->t('Woodworking'),
    ];
  }

  /**
   * Returns common Virtuosic Performer specialty options.
   */
  private function getPerformanceSpecialtyOptions(): array {
    return [
      'acting' => $this->t('Acting'),
      'comedy' => $this->t('Comedy'),
      'dance' => $this->t('Dance'),
      'keyboard' => $this->t('Keyboard instruments'),
      'oratory' => $this->t('Oratory'),
      'percussion' => $this->t('Percussion'),
      'singing' => $this->t('Singing'),
      'strings' => $this->t('String instruments'),
      'winds' => $this->t('Wind instruments'),
    ];
  }

  /**
   * Returns valid Canny Acumen proficiency targets.
   */
  private function getCannyAcumenOptions(): array {
    return [
      'perception' => $this->t('Perception'),
      'fortitude' => $this->t('Fortitude'),
      'reflex' => $this->t('Reflex'),
      'will' => $this->t('Will'),
    ];
  }

  /**
   * Returns valid Monster Hunter creature types.
   */
  private function getMonsterHunterOptions(): array {
    return [
      'aberration' => $this->t('Aberration'),
      'animal' => $this->t('Animal'),
      'beast' => $this->t('Beast'),
      'construct' => $this->t('Construct'),
      'dragon' => $this->t('Dragon'),
      'elemental' => $this->t('Elemental'),
      'fey' => $this->t('Fey'),
      'fungus' => $this->t('Fungus'),
      'giant' => $this->t('Giant'),
      'humanoid' => $this->t('Humanoid'),
      'ooze' => $this->t('Ooze'),
      'undead' => $this->t('Undead'),
    ];
  }

  /**
   * Returns the valid rogue key ability options for the selected racket feat.
   */
  private function resolveRogueKeyAbilityOptions(string $selected_class_feat): array {
    return match ($selected_class_feat) {
      'ruffian' => ['dexterity', 'strength'],
      'scoundrel' => ['dexterity', 'charisma'],
      'eldritch-trickster-racket', 'mastermind-racket' => ['dexterity', 'intelligence'],
      default => ['dexterity'],
    };
  }

  /**
   * Returns valid Eldritch Trickster multiclass dedication options.
   */
  private function getEldritchTricksterDedicationOptions(): array {
    $options = [];
    foreach (CharacterManager::getSpellcastingMulticlassDedicationOptions() as $dedication) {
      $dedication_id = (string) ($dedication['id'] ?? '');
      $dedication_name = (string) ($dedication['name'] ?? '');
      if ($dedication_id !== '' && $dedication_name !== '') {
        $options[$dedication_id] = $dedication_name;
      }
    }
    return $options;
  }

  /**
   * Returns valid Mastermind knowledge skill options.
   */
  private function getMastermindKnowledgeSkillOptions(): array {
    return [
      'arcana' => $this->t('Arcana'),
      'nature' => $this->t('Nature'),
      'occultism' => $this->t('Occultism'),
      'religion' => $this->t('Religion'),
    ];
  }

  /**
   * Resolves the armor tier granted by Armor Proficiency for the current class.
   */
  private function resolveArmorProficiencyTarget(array $character_data): ?string {
    $selected_class = trim((string) ($character_data['class'] ?? ''));
    if ($selected_class === '' || !isset(CharacterManager::CLASSES[$selected_class])) {
      return NULL;
    }

    $armor_proficiencies = CharacterManager::CLASSES[$selected_class]['armor_proficiency'] ?? [];
    if (is_string($armor_proficiencies)) {
      $armor_proficiencies = $armor_proficiencies === 'unarmored_only' ? ['unarmored'] : [$armor_proficiencies];
    }

    $owned_tiers = array_map(static fn(string $tier): string => strtolower(trim($tier)), $armor_proficiencies);
    if (in_array('heavy', $owned_tiers, TRUE)) {
      return NULL;
    }
    if (in_array('medium', $owned_tiers, TRUE)) {
      return 'heavy';
    }
    if (in_array('light', $owned_tiers, TRUE)) {
      return 'medium';
    }

    return 'light';
  }

  private function getNaturalPerformerOptions(): array {
    return [
      'acting' => $this->t('Acting'),
      'dance' => $this->t('Dancing'),
      'singing' => $this->t('Singing'),
    ];
  }

  /**
   * Returns standard skill training options.
   */
  private function getSkillTrainingOptions(): array {
    return [
      'Acrobatics' => 'Acrobatics - Balance, tumble, maneuver while flying',
      'Arcana' => 'Arcana - Recall knowledge about arcane magic, traditions, creatures',
      'Athletics' => 'Athletics - Climb, force open, grapple, swim',
      'Crafting' => 'Crafting - Repair items, identify alchemical objects, craft goods',
      'Deception' => 'Deception - Create a diversion, feint, lie, impersonate',
      'Diplomacy' => 'Diplomacy - Gather information, make an impression, request',
      'Intimidation' => 'Intimidation - Coerce, demoralize',
      'Medicine' => 'Medicine - Administer first aid, treat disease, treat poison',
      'Nature' => 'Nature - Command an animal, recall knowledge about natural creatures',
      'Occultism' => 'Occultism - Recall knowledge about occult topics, creatures',
      'Performance' => 'Performance - Act, dance, play instrument, give speech',
      'Religion' => 'Religion - Recall knowledge about divine topics, creatures',
      'Society' => 'Society - Recall knowledge about society, civilization, history',
      'Stealth' => 'Stealth - Conceal an object, hide, sneak',
      'Survival' => 'Survival - Cover tracks, sense direction, subsist, track',
      'Thievery' => 'Thievery - Palm an object, disable a device, pick a lock',
    ];
  }

  /**
   * Returns 1st-level elf ancestry feats available to Elf Atavism.
   *
   * @return array<int,array<string,mixed>>
   *   Canonical elf ancestry feat definitions.
   */
  private function getElfAtavismFeatOptions(): array {
    return array_values(array_filter(
      CharacterManager::getAncestryFeats('Elf'),
      static function (array $feat): bool {
        return (int) ($feat['level'] ?? 0) <= 1;
      }
    ));
  }

  /**
   * Returns 1st-level orc ancestry feats available to Orc Atavism.
   *
   * @return array<int,array<string,mixed>>
   *   Canonical orc ancestry feat definitions.
   */
  private function getOrcAtavismFeatOptions(): array {
    return array_values(array_filter(
      CharacterManager::getAncestryFeats('Orc'),
      static function (array $feat): bool {
        return (int) ($feat['level'] ?? 0) <= 1;
      }
    ));
  }

  /**
   * Returns Draconic Ties damage type options.
   */
  private function getDraconicDamageTypeOptions(): array {
    return [
      'acid' => 'Acid',
      'cold' => 'Cold',
      'electricity' => 'Electricity',
      'fire' => 'Fire',
      'poison' => 'Poison',
    ];
  }

  /**
   * Adds Adapted Cantrip step-4 selection UI once class tradition is known.
   */
  private function buildAdaptedCantripSelectionSection(array &$form, FormStateInterface $form_state, array $character_data, string $native_tradition): void {
    $available_traditions = SpellCatalogService::TRADITIONS;
    $stored_selection = $character_data['feat_selections']['adapted-cantrip'] ?? [];
    $selected_tradition = (string) $form_state->getValue(
      ['feat_selections', 'adapted-cantrip', 'selected_tradition'],
      $stored_selection['selected_tradition'] ?? ''
    );
    if (!in_array($selected_tradition, $available_traditions, TRUE)) {
      $selected_tradition = '';
    }

    $form['class_dynamic']['feat_selections'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    $form['class_dynamic']['feat_selections']['adapted-cantrip'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section', 'feat-selection-section']],
    ];
    $form['class_dynamic']['feat_selections']['adapted-cantrip']['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
        . $this->t('Adapted Cantrip')
        . '</strong><br>'
        . $this->t('Choose one cantrip from any tradition, including your native @tradition tradition.', [
          '@tradition' => ucfirst($native_tradition),
        ])
        . '</div>',
    ];
    $form['class_dynamic']['feat_selections']['adapted-cantrip']['selected_tradition'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose a tradition'),
      '#options' => array_combine(
        $available_traditions,
        array_map(static fn(string $tradition): string => ucfirst($tradition), $available_traditions)
      ),
      '#default_value' => $selected_tradition,
      '#required' => FALSE,
      '#ajax' => [
        'callback' => '::updateClassOptions',
        'wrapper' => 'class-dynamic-wrapper',
        'event' => 'change',
      ],
    ];

    if ($selected_tradition === '') {
      return;
    }

    $cantrip_options = [];
    $cantrip_cards = [];
    $cantrip_reference_cards = [];
    foreach ($this->characterManager->getSpellsByTradition($selected_tradition, 0) as $cantrip) {
      $cantrip_options[$cantrip['id']] = $cantrip['name'];
      $tags = ['Cantrip', ucfirst($selected_tradition)];
      if (!empty($cantrip['school'])) {
        $tags[] = ucfirst((string) $cantrip['school']);
      }
      $facts = $this->extractSpellFacts($cantrip);
      $cantrip_cards[$cantrip['id']] = $this->buildOptionCardData(
        $cantrip['description'] ?? '',
        $tags,
        $facts,
      );
      $cantrip_reference_cards[] = [
        'title' => $cantrip['name'],
        'description' => $cantrip['description'] ?? '',
        'tags' => $tags,
        'facts' => $facts,
      ];
    }

    $form['class_dynamic']['feat_selections']['adapted-cantrip']['selected_cantrip'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose your adapted cantrip'),
      '#options' => $cantrip_options,
      '#default_value' => (string) $form_state->getValue(
        ['feat_selections', 'adapted-cantrip', 'selected_cantrip'],
        $stored_selection['selected_cantrip'] ?? ''
      ),
      '#required' => FALSE,
      '#description' => $this->t('This cantrip is added as an innate at-will spell from the @tradition tradition.', [
        '@tradition' => ucfirst($selected_tradition),
      ]),
    ];
    $this->attachOptionCardSettings(
      $form['class_dynamic']['feat_selections']['adapted-cantrip'],
      'selected_cantrip',
      $cantrip_cards,
      'single'
    );
    $form['class_dynamic']['feat_selections']['adapted-cantrip']['reference'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['spell-reference-section']],
      'heading' => [
        '#markup' => '<h4>' . $this->t('@tradition cantrip reference', ['@tradition' => ucfirst($selected_tradition)]) . '</h4>',
      ],
      'cards' => [
        '#markup' => $this->buildOptionDetailStackMarkup($cantrip_reference_cards),
      ],
    ];
  }

  /**
   * Validates Adapted Cantrip selection values on step 4.
   */
  private function validateAdaptedCantripSelection(FormStateInterface $form_state, array $character_data): void {
    $selected_class = trim((string) ($character_data['class'] ?? ''));
    $native_tradition = $this->characterManager->resolveClassTradition($selected_class, $character_data);
    if ($native_tradition === NULL) {
      $form_state->setErrorByName('class', $this->t('Adapted Cantrip requires a class with a spellcasting tradition.'));
      return;
    }

    $selected_tradition = trim((string) $form_state->getValue(['feat_selections', 'adapted-cantrip', 'selected_tradition'], ''));
    $selected_cantrip = trim((string) $form_state->getValue(['feat_selections', 'adapted-cantrip', 'selected_cantrip'], ''));
    $available_traditions = SpellCatalogService::TRADITIONS;

    if ($selected_tradition === '' || !in_array($selected_tradition, $available_traditions, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][adapted-cantrip][selected_tradition',
        $this->t('Choose a tradition for Adapted Cantrip.')
      );
      return;
    }

    if ($selected_cantrip === '') {
      $form_state->setErrorByName(
        'feat_selections][adapted-cantrip][selected_cantrip',
        $this->t('Choose a cantrip for Adapted Cantrip.')
      );
      return;
    }

    $valid_cantrip_ids = array_map(
      static fn(array $spell): string => (string) ($spell['id'] ?? ''),
      $this->characterManager->getSpellsByTradition($selected_tradition, 0)
    );
    if (!in_array($selected_cantrip, $valid_cantrip_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][adapted-cantrip][selected_cantrip',
        $this->t('Choose a valid cantrip from the selected tradition.')
      );
    }
  }

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
    $class_feats = $class_name !== '' ? CharacterManager::getClassFeats($class_name) : [];

    // Ancestry feat.
    if (!empty($character_data['ancestry_feat'])) {
      $ancestry_name = $this->resolveAncestryName($character_data['ancestry'] ?? '');
      $ancestry_feats = CharacterManager::getAncestryFeats($ancestry_name);
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
      foreach (CharacterManager::getGeneralFeats() as $f) {
        if ($f['id'] === $character_data['general_feat']) {
          $feats[] = ['type' => 'general', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }
    }

    if (($character_data['ancestry_feat'] ?? '') === 'general-training') {
      $bonus_general_feat = trim((string) ($character_data['feat_selections']['general-training']['bonus_general_feat'] ?? ''));
      foreach (CharacterManager::getGeneralFeats() as $f) {
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
   * Adds subclass-granted feats that should behave like normal class feats.
   */
  private function appendSubclassGrantedFeats(array &$feats, array $character_data, array $class_feats): void {
    $class_name = strtolower(trim((string) ($character_data['class'] ?? '')));
    if ($class_name !== 'druid') {
      return;
    }

    $selected_order = strtolower(trim((string) ($character_data['subclass'] ?? '')));
    $order_definition = CharacterManager::CLASSES['druid']['order']['orders'][$selected_order] ?? NULL;
    if (!is_array($order_definition)) {
      return;
    }

    foreach (($order_definition['granted_feats'] ?? []) as $granted_feat_id) {
      $normalized_id = strtolower(str_replace('_', '-', trim((string) $granted_feat_id)));
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
          'source' => 'druid-order:' . $selected_order,
        ];
        break;
      }
    }
  }

  /**
   * Resolves which Step 4 source currently grants an animal companion choice.
   */
  private function resolveAnimalCompanionSelectionSource(FormStateInterface $form_state, array $character_data, string $selected_class): ?string {
    $selected_class_feat = trim((string) $form_state->getValue('class_feat', $character_data['class_feat'] ?? ''));
    if ($selected_class_feat === 'animal-companion' || $selected_class_feat === 'animal-companion-druid') {
      return $selected_class_feat;
    }

    $selected_bonus_feat = trim((string) $form_state->getValue(
      ['feat_selections', 'natural-ambition', 'bonus_class_feat'],
      $character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''
    ));
    if ($selected_bonus_feat === 'animal-companion') {
      return 'animal-companion';
    }

    $selected_subclass = strtolower(trim((string) $form_state->getValue('subclass', $character_data['subclass'] ?? '')));
    if ($selected_class === 'druid' && $selected_subclass === 'animal') {
      return 'animal-companion-druid';
    }

    return NULL;
  }

}
