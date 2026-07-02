<?php

namespace Drupal\dungeoncrawler_content\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Component\Utility\Html;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\ImageGenerationIntegrationService;
use Drupal\dungeoncrawler_content\Service\CharacterPortraitGenerationService;
use Drupal\dungeoncrawler_content\Service\SchemaLoader;
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
    
    $form['#prefix'] = '<div class="character-creation-step"><div class="creation-container"><div class="progress-bar"><div class="progress-indicator progress-step-' . $step . '"></div></div><div class="progress-text">' . $this->t('Step @step of @total', ['@step' => $step, '@total' => 8]) . '</div><div class="step-content">';
    $form['#suffix'] = '</div></div></div>';

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
    
    if ($step > 1) {
      $back_query = ['character_id' => $character_id];
      if ($campaign_id) {
        $back_query['campaign_id'] = $campaign_id;
      }

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
      '#value' => $step < 8 ? $this->t('Next →') : $this->t('Create Legacy Character'),
      '#attributes' => ['class' => ['btn', 'btn-primary']],
    ];

    return $form;
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
      $ancestry_feats = CharacterManager::ANCESTRY_FEATS[$ancestry_name] ?? [];

      if (!empty($ancestry_feats)) {
        $form['heritage_dynamic']['ancestry_feat_dynamic']['ancestry_feat_section'] = [
          '#markup' => '<div class="section-instructions ancestry-feat-section">'
            . '<h3>' . $this->t('Ancestry Feat') . '</h3>'
            . '<p>' . $this->t('Choose one 1st-level ancestry feat. This represents a special ability or training unique to your ancestry.') . '</p>'
            . '</div>',
        ];

        $feat_options = [];
        $feat_descriptions = [];

        foreach ($ancestry_feats as $feat) {
          $feat_options[$feat['id']] = $feat['name'];
          $prereq_text = !empty($feat['prerequisites']) ? ' <em>(Requires: ' . $feat['prerequisites'] . ')</em>' : '';
          $feat_descriptions[$feat['id']] = [
            '#markup' => '<div class="feat-description">'
              . '<strong>' . $feat['name'] . '</strong>' . $prereq_text . '<br>'
              . $feat['benefit']
              . '</div>',
          ];
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

        // Add detailed descriptions for each feat option via #states.
        foreach ($feat_descriptions as $feat_id => $description_markup) {
          $form['heritage_dynamic']['ancestry_feat_dynamic']['ancestry_feat_desc_' . $feat_id] = $description_markup;
          $form['heritage_dynamic']['ancestry_feat_dynamic']['ancestry_feat_desc_' . $feat_id]['#states'] = [
            'visible' => [
              ':input[name="ancestry_feat"]' => ['value' => $feat_id],
            ],
          ];
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
          '#markup' => '<div class="background-benefit">'
            . '<p><strong>' . $this->t('Skill Training:') . '</strong> ' . ($background_data['skill'] ?? 'Varies') . '</p>'
            . '<p><strong>' . $this->t('Lore Skill:') . '</strong> ' . ($background_data['lore'] ?? 'Varies') . '</p>'
            . '<p><strong>' . $this->t('Skill Feat:') . '</strong> ' . ($background_data['feat'] ?? 'Varies') . '</p>'
            . '<p class="help-text">' . $this->t('These will be automatically applied to your character.') . '</p>'
            . '</div>',
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
      '#attributes' => ['id' => 'class-dynamic-wrapper'],
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

    // Key Ability Selection
    $key_ability_raw = $class_data['key_ability'] ?? '';
    $key_options = array_map('trim', explode(' or ', strtolower($key_ability_raw)));

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
        '#default_value' => $character_data['class_key_ability'] ?? '',
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
    $class_feats = CharacterManager::CLASS_FEATS[$selected_class] ?? [];

    if (!empty($class_feats)) {
      $form['class_dynamic']['class_feat_section'] = [
        '#markup' => '<div class="section-instructions class-feat-section">'
          . '<h3>' . $this->t('Class Feat') . '</h3>'
          . '<p>' . $this->t('Choose one 1st-level class feat. This represents specialized training or a unique technique for your class.') . '</p>'
          . '</div>',
      ];

      $feat_options = [];
      $feat_descriptions = [];

      foreach ($class_feats as $feat) {
        $feat_options[$feat['id']] = $feat['name'];
        $prereq_text = !empty($feat['prerequisites']) ? ' <em>(Requires: ' . $feat['prerequisites'] . ')</em>' : '';
        $traits_text = !empty($feat['traits']) ? ' [' . implode(', ', $feat['traits']) . ']' : '';
        $feat_descriptions[$feat['id']] = [
          '#markup' => '<div class="feat-description">'
            . '<strong>' . $feat['name'] . '</strong>' . $traits_text . $prereq_text . '<br>'
            . $feat['benefit']
            . '</div>',
        ];
      }

      $form['class_dynamic']['class_feat'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Class Feat'),
        '#options' => $feat_options,
        '#default_value' => $character_data['class_feat'] ?? '',
        '#required' => TRUE,
        '#description' => $this->t('Each feat provides unique tactical options that define your combat style.'),
      ];

      foreach ($feat_descriptions as $feat_id => $description_markup) {
        $form['class_dynamic']['class_feat_desc_' . $feat_id] = $description_markup;
        $form['class_dynamic']['class_feat_desc_' . $feat_id]['#states'] = [
          'visible' => [
            ':input[name="class_feat"]' => ['value' => $feat_id],
          ],
        ];
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
      foreach (CharacterManager::SORCERER_BLOODLINES as $bl_id => $bl) {
        $bloodline_options[$bl_id] = $bl['label'] . ' (' . ucfirst($bl['tradition']) . ') — ' . $bl['description'];
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
    }
    elseif ($selected_class === 'witch') {
      $form['class_dynamic']['patron_section'] = [
        '#markup' => '<div class="section-instructions patron-section">'
          . '<h3>' . $this->t('Witch Patron') . '</h3>'
          . '<p>' . $this->t('Your patron is the mysterious entity that granted you magic and a familiar. Choose your patron theme to determine your spellcasting tradition.') . '</p>'
          . '</div>',
      ];
      $patron_options = [];
      foreach (CharacterManager::WITCH_PATRONS as $p_id => $p) {
        $patron_options[$p_id] = $p['label'] . ' (' . ucfirst($p['tradition']) . ') — ' . $p['description'];
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
      foreach ($cantrips as $cantrip) {
        $school_tag = !empty($cantrip['school']) ? ' [' . ucfirst($cantrip['school']) . ']' : '';
        $cantrip_options[$cantrip['id']] = $cantrip['name'] . $school_tag . ' — ' . $cantrip['description'];
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

      // --- 1st Level Spell Selection ---
      $first_level_spells = $this->characterManager->getSpellsByTradition($tradition, 1);
      $spell_options = [];
      foreach ($first_level_spells as $spell) {
        $school_tag = !empty($spell['school']) ? ' [' . ucfirst($spell['school']) . ']' : '';
        $spell_options[$spell['id']] = $spell['name'] . $school_tag . ' — ' . $spell['description'];
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

        $all_skills = [
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
    ];
    $this->applySchemaValidationAttributes($form['deity'], $schema_fields, 'deity');

    // --- General Feat Selection ---
    // Every PF2e character gets one 1st-level general feat at character creation.
    $form['general_feat_section'] = [
      '#markup' => '<div class="section-instructions general-feat-section">'
        . '<h3>' . $this->t('General Feat') . '</h3>'
        . '<p>' . $this->t('Every character receives one 1st-level general feat. These represent broad talents not tied to your class or ancestry.') . '</p>'
        . '</div>',
    ];

    $general_feat_options = [];
    $general_feat_descriptions = [];
    foreach (CharacterManager::GENERAL_FEATS as $feat) {
      $general_feat_options[$feat['id']] = $feat['name'];
      $prereq_text = !empty($feat['prerequisites']) ? ' <em>(Requires: ' . $feat['prerequisites'] . ')</em>' : '';
      $general_feat_descriptions[$feat['id']] = [
        '#markup' => '<div class="feat-description">'
          . '<strong>' . $feat['name'] . '</strong>' . $prereq_text . '<br>'
          . $feat['benefit']
          . '</div>',
      ];
    }

    $form['general_feat'] = [
      '#type' => 'radios',
      '#title' => $this->t('Select General Feat'),
      '#options' => $general_feat_options,
      '#default_value' => $character_data['general_feat'] ?? '',
      '#required' => TRUE,
      '#description' => $this->t('Popular choices: Toughness (more HP), Fleet (faster movement), Incredible Initiative (+2 to initiative), Shield Block (damage reduction).'),
    ];

    // Add detailed descriptions with show/hide via #states.
    foreach ($general_feat_descriptions as $feat_id => $description_markup) {
      $form['general_feat_desc_' . $feat_id] = $description_markup;
      $form['general_feat_desc_' . $feat_id]['#states'] = [
        'visible' => [
          ':input[name="general_feat"]' => ['value' => $feat_id],
        ],
      ];
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

    foreach ($catalog as $category => $items) {
      foreach ($items as $item) {
        $catalog_by_id[$item['id']] = $item;
        $item_label = $item['name'] . ' (' . (float) $item['cost'] . ' gp)';
        
        // Add extra info for weapons and armor
        if ($category === 'weapons' && !empty($item['damage'])) {
          $item_label .= ' - ' . $item['damage'] . ' damage';
        }
        elseif ($category === 'armor' && !empty($item['ac'])) {
          $item_label .= ' - AC ' . $item['ac'];
        }

        if ($category === 'weapons') {
          $weapons_options[$item['id']] = $item_label;
        }
        elseif ($category === 'armor') {
          $armor_options[$item['id']] = $item_label;
        }
        else {
          $gear_options[$item['id']] = $item_label;
        }
      }
    }

    $form['equipment_weapons']['weapons'] = [
      '#type' => 'checkboxes',
      '#options' => $weapons_options,
      '#default_value' => array_filter($selected_ids, fn($id) => isset($catalog['weapons']) && in_array($id, array_column($catalog['weapons'], 'id'))),
      '#description' => $this->t('Select weapons for combat. Consider your class proficiencies.'),
    ];

    $form['equipment_armor']['armor'] = [
      '#type' => 'checkboxes',
      '#options' => $armor_options,
      '#default_value' => array_filter($selected_ids, fn($id) => isset($catalog['armor']) && in_array($id, array_column($catalog['armor'], 'id'))),
      '#description' => $this->t('Choose armor and shields for protection. Heavy armor may slow you down.'),
    ];

    $form['equipment_gear']['gear'] = [
      '#type' => 'checkboxes',
      '#options' => $gear_options,
      '#default_value' => array_filter($selected_ids, fn($id) => isset($catalog['gear']) && in_array($id, array_column($catalog['gear'], 'id'))),
      '#description' => $this->t('Essential adventuring supplies: rope, torches, rations, and tools.'),
    ];

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
          $feats_for_ancestry = CharacterManager::ANCESTRY_FEATS[$ancestry_name_val] ?? [];
          if (!empty($feats_for_ancestry) && trim((string) $form_state->getValue('ancestry_feat', '')) === '') {
            $form_state->setErrorByName('ancestry_feat', $this->t('Ancestry feat selection is required.'));
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
            $ka_raw = $class_data_for_ka['key_ability'] ?? '';
            $ka_opts = array_map('trim', explode(' or ', strtolower($ka_raw)));
            if (count($ka_opts) > 1 && trim((string) $form_state->getValue('class_key_ability', '')) === '') {
              $form_state->setErrorByName('class_key_ability', $this->t('You must choose a key ability for this class.'));
            }
          }
        }

        // Validate subclass (bloodline/patron) for flexible-tradition casters.
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
    $step = $form_state->get('step');
    $character_id = $form_state->get('character_id');
    $campaign_id = $form_state->get('campaign_id');
    $character_data = $this->loadCharacterData($character_id);

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
        $form_state->setRedirect('dungeoncrawler_content.character_step', ['step' => $step], ['query' => $query]);
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

      // Build feats summary array from all sources.
      $character_data['feats'] = $this->buildFeatsArray($character_data);
    }

    // Step 6: Clean trained_skills checkbox data and build feats summary.
    if ((int) $step === 6) {
      $raw_skills = $form_state->getValue('trained_skills', []);
      $character_data['trained_skills'] = is_array($raw_skills)
        ? array_values(array_filter($raw_skills, static fn($v) => $v !== 0 && $v !== '' && $v !== NULL))
        : [];

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
    }

    $next_step = min(8, (int) $step + 1);
    $character_data['step'] = $next_step;

    // Save to database
    $character_id = $this->saveCharacter($character_id, $character_data, $next_version, $campaign_id);

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
        $campaign_id !== NULL && $campaign_id !== '' ? (int) $campaign_id : NULL,
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
      $next_query = ['character_id' => $character_id];
      if ($campaign_id) {
        $next_query['campaign_id'] = $campaign_id;
      }

      $form_state->setRedirect('dungeoncrawler_content.character_step', [
        'step' => $next_step,
      ], ['query' => $next_query]);
    }
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
        ['id' => 'longsword', 'name' => 'Longsword', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 S', 'hands' => 1, 'traits' => ['versatile P']],
        ['id' => 'shortsword', 'name' => 'Shortsword', 'type' => 'weapon', 'cost' => 0.9, 'bulk' => 'L', 'damage' => '1d6 P', 'hands' => 1, 'traits' => ['agile', 'finesse', 'versatile S']],
        ['id' => 'dagger', 'name' => 'Dagger', 'type' => 'weapon', 'cost' => 0.2, 'bulk' => 'L', 'damage' => '1d4 P', 'hands' => 1, 'traits' => ['agile', 'finesse', 'thrown 10 ft.', 'versatile S']],
        ['id' => 'rapier', 'name' => 'Rapier', 'type' => 'weapon', 'cost' => 2.0, 'bulk' => 1, 'damage' => '1d6 P', 'hands' => 1, 'traits' => ['deadly d8', 'disarm', 'finesse']],
        ['id' => 'battleaxe', 'name' => 'Battle Axe', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 S', 'hands' => 1, 'traits' => ['sweep']],
        ['id' => 'warhammer', 'name' => 'Warhammer', 'type' => 'weapon', 'cost' => 1.0, 'bulk' => 1, 'damage' => '1d8 B', 'hands' => 1, 'traits' => ['shove']],
        ['id' => 'shortbow', 'name' => 'Shortbow', 'type' => 'weapon', 'cost' => 3.0, 'bulk' => 1, 'damage' => '1d6 P', 'hands' => 2, 'traits' => ['deadly d10', 'range 60 ft.']],
        ['id' => 'longbow', 'name' => 'Longbow', 'type' => 'weapon', 'cost' => 6.0, 'bulk' => 2, 'damage' => '1d8 P', 'hands' => 2, 'traits' => ['deadly d10', 'range 100 ft.', 'volley 30 ft.']],
        ['id' => 'staff', 'name' => 'Staff', 'type' => 'weapon', 'cost' => 0.0, 'bulk' => 1, 'damage' => '1d4 B', 'hands' => 2, 'traits' => ['two-hand d8']],
      ],
      'armor' => [
        ['id' => 'leather', 'name' => 'Leather Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 1, 'ac' => '+1', 'traits' => []],
        ['id' => 'studded_leather_armor', 'name' => 'Studded Leather Armor', 'type' => 'armor', 'cost' => 3.0, 'bulk' => 1, 'ac' => '+2', 'traits' => []],
        ['id' => 'chain_shirt', 'name' => 'Chain Shirt', 'type' => 'armor', 'cost' => 5.0, 'bulk' => 1, 'ac' => '+2', 'traits' => ['flexible', 'noisy']],
        ['id' => 'hide_armor', 'name' => 'Hide Armor', 'type' => 'armor', 'cost' => 2.0, 'bulk' => 2, 'ac' => '+3', 'traits' => []],
        ['id' => 'scale_mail', 'name' => 'Scale Mail', 'type' => 'armor', 'cost' => 4.0, 'bulk' => 2, 'ac' => '+3', 'traits' => []],
        ['id' => 'chain_mail', 'name' => 'Chain Mail', 'type' => 'armor', 'cost' => 6.0, 'bulk' => 2, 'ac' => '+4', 'traits' => ['flexible', 'noisy']],
        ['id' => 'breastplate', 'name' => 'Breastplate', 'type' => 'armor', 'cost' => 8.0, 'bulk' => 2, 'ac' => '+4', 'traits' => []],
        ['id' => 'wooden_shield', 'name' => 'Wooden Shield', 'type' => 'armor', 'cost' => 1.0, 'bulk' => 1, 'ac' => '+2 circumstance', 'traits' => []],
      ],
      'gear' => [
        ['id' => 'backpack', 'name' => 'Backpack', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'bedroll', 'name' => 'Bedroll', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'rope', 'name' => 'Rope (50 ft.)', 'type' => 'adventuring_gear', 'cost' => 0.5, 'bulk' => 'L', 'traits' => []],
        ['id' => 'torches', 'name' => 'Torches (5)', 'type' => 'adventuring_gear', 'cost' => 0.05, 'bulk' => 'L', 'traits' => []],
        ['id' => 'rations', 'name' => 'Rations (1 week)', 'type' => 'adventuring_gear', 'cost' => 0.4, 'bulk' => 'L', 'traits' => []],
        ['id' => 'waterskin', 'name' => 'Waterskin', 'type' => 'adventuring_gear', 'cost' => 0.05, 'bulk' => 'L', 'traits' => []],
        ['id' => 'healers_tools', 'name' => "Healer's Tools", 'type' => 'adventuring_gear', 'cost' => 5.0, 'bulk' => 1, 'traits' => []],
        ['id' => 'thieves_tools', 'name' => "Thieves' Tools", 'type' => 'adventuring_gear', 'cost' => 3.0, 'bulk' => 'L', 'traits' => []],
        ['id' => 'grappling_hook', 'name' => 'Grappling Hook', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
        ['id' => 'hooded_lantern', 'name' => 'Hooded Lantern', 'type' => 'adventuring_gear', 'cost' => 0.7, 'bulk' => 'L', 'traits' => []],
        ['id' => 'oil_flask', 'name' => 'Oil (1 flask)', 'type' => 'adventuring_gear', 'cost' => 0.1, 'bulk' => 'L', 'traits' => []],
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
        // Support both old flat structure and new nested abilities structure
        if (!empty($data['abilities'])) {
          // New format - flatten for form
          $data['strength'] = $data['abilities']['str'] ?? 10;
          $data['dexterity'] = $data['abilities']['dex'] ?? 10;
          $data['constitution'] = $data['abilities']['con'] ?? 10;
          $data['intelligence'] = $data['abilities']['int'] ?? 10;
          $data['wisdom'] = $data['abilities']['wis'] ?? 10;
          $data['charisma'] = $data['abilities']['cha'] ?? 10;
        }
        return $data;
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
    $provider = strtolower(trim((string) ($status['default_provider'] ?? 'gemini')));
    $provider_status = is_array($status['providers'][$provider] ?? NULL)
      ? $status['providers'][$provider]
      : [];
    $enabled = !empty($provider_status['enabled']);
    $has_api_key = !empty($provider_status['has_api_key']);

    if ($enabled && $has_api_key) {
      return [
        'available' => TRUE,
        'description' => $this->t('Creates a portrait using the configured AI image provider after character creation.'),
      ];
    }

    $issues = [];
    if (!$enabled) {
      $issues[] = $this->t('the provider is disabled');
    }
    if (!$has_api_key) {
      $issues[] = $this->t('no API key is configured');
    }

    return [
      'available' => FALSE,
      'description' => $this->t('Portrait generation is currently unavailable because @provider is not fully configured (@issues).', [
        '@provider' => ucfirst($provider),
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

    // Restructure data to match schema
    $schema_data = $character_data;

    // Convert flat ability scores to nested structure
    $schema_data['abilities'] = [
      'str' => (int) ($character_data['strength'] ?? 10),
      'dex' => (int) ($character_data['dexterity'] ?? 10),
      'con' => (int) ($character_data['constitution'] ?? 10),
      'int' => (int) ($character_data['intelligence'] ?? 10),
      'wis' => (int) ($character_data['wisdom'] ?? 10),
      'cha' => (int) ($character_data['charisma'] ?? 10),
    ];

    // Remove flat ability scores from root
    unset($schema_data['strength'], $schema_data['dexterity'], $schema_data['constitution']);
    unset($schema_data['intelligence'], $schema_data['wisdom'], $schema_data['charisma']);

    // Auto-populate ancestry-derived fields
    if (!empty($schema_data['ancestry'])) {
      $canonical_ancestry = CharacterManager::resolveAncestryCanonicalName($schema_data['ancestry']);
      $ancestry_data = $canonical_ancestry !== '' ? (CharacterManager::ANCESTRIES[$canonical_ancestry] ?? NULL) : NULL;
      if ($ancestry_data) {
        $schema_data['size'] = $ancestry_data['size'];
        $schema_data['speed'] = $ancestry_data['speed'];
        if (empty($schema_data['languages'])) {
          $schema_data['languages'] = $ancestry_data['languages'];
        }
        // Auto-assign ancestry creature traits (idempotent — no duplicates).
        $schema_data['traits'] = CharacterManager::mergeTraits(
          $schema_data['traits'] ?? [],
          $ancestry_data['traits'] ?? []
        );
      }
    }

    // Calculate max HP
    $level = $schema_data['level'] ?? 1;
    $con_mod = floor(($schema_data['abilities']['con'] - 10) / 2);
    $ancestry_hp = 0;
    if (!empty($schema_data['ancestry'])) {
      $canonical_ancestry_hp = CharacterManager::resolveAncestryCanonicalName($schema_data['ancestry']);
      $ancestry_data = $canonical_ancestry_hp !== '' ? (CharacterManager::ANCESTRIES[$canonical_ancestry_hp] ?? NULL) : NULL;
      if ($ancestry_data) {
        $ancestry_hp = $ancestry_data['hp'];
      }
    }
    $class_hp = 8; // Default fallback
    if (!empty($schema_data['class'])) {
      $class_hp = $this->characterManager->getClassHP((string) $schema_data['class']);
    }
    $max_hp = $ancestry_hp + $class_hp + $con_mod + (($level - 1) * ($class_hp + $con_mod));

    // Structure hit_points
    $schema_data['hit_points'] = [
      'max' => $max_hp,
      'current' => $schema_data['hit_points']['current'] ?? $max_hp,
      'temp' => $schema_data['hit_points']['temp'] ?? 0,
    ];

    $dex_mod = (int) floor(((int) $schema_data['abilities']['dex'] - 10) / 2);
    $wis_mod = (int) floor(((int) $schema_data['abilities']['wis'] - 10) / 2);
    $armor_class = 10 + $dex_mod;

    // Compute PF2E derived saves and perception (trained at level 1 = level + 2 + ability mod).
    $schema_data['saves'] = [
      'fortitude' => (int) ($level + 2 + $con_mod),
      'reflex'    => (int) ($level + 2 + $dex_mod),
      'will'      => (int) ($level + 2 + $wis_mod),
    ];
    $schema_data['perception'] = (int) ($level + 2 + $wis_mod);

    // Ensure required schema fields exist
    $schema_data['level'] = $level;
    $schema_data['experience_points'] = (int) ($schema_data['experience_points'] ?? 0);
    $schema_data['gold'] = (float) ($schema_data['gold'] ?? 15);
    $schema_data['hero_points'] = (int) ($schema_data['hero_points'] ?? 1);
    $schema_data['inventory'] = $schema_data['inventory'] ?? [];
    $schema_data['skills'] = $schema_data['skills'] ?? [];
    $schema_data['feats'] = $schema_data['feats'] ?? [];
    $schema_data['conditions'] = $schema_data['conditions'] ?? [];

    // Timestamps
    if (!isset($schema_data['created_at'])) {
      $schema_data['created_at'] = date('c', $now);
    }
    $schema_data['updated_at'] = date('c', $now);

    // Resolve campaign_id: use passed value, fall back to 0.
    $resolved_campaign_id = ($campaign_id !== NULL && $campaign_id !== '') ? (int) $campaign_id : 0;

    if ($character_id) {
      $this->database->update('dc_campaign_characters')
        ->fields([
          'campaign_id' => $resolved_campaign_id,
          'name' => $schema_data['name'] ?: 'Unnamed Character',
          'level' => $schema_data['level'],
          'ancestry' => $schema_data['ancestry'] ?? '',
          'class' => $schema_data['class'] ?? '',
          'hp_current' => (int) ($schema_data['hit_points']['current'] ?? $max_hp),
          'hp_max' => (int) $max_hp,
          'armor_class' => (int) $armor_class,
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
          'hp_current' => (int) ($schema_data['hit_points']['current'] ?? $max_hp),
          'hp_max' => (int) $max_hp,
          'armor_class' => (int) $armor_class,
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
      $form_state->setValue('cantrips', []);
      $form_state->setValue('spells_first', []);

      $user_input = $form_state->getUserInput();
      if (is_array($user_input)) {
        unset(
          $user_input['class_feat'],
          $user_input['class_key_ability'],
          $user_input['subclass'],
          $user_input['cantrips'],
          $user_input['spells_first']
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

    // Ancestry feat.
    if (!empty($character_data['ancestry_feat'])) {
      $ancestry_name = $this->resolveAncestryName($character_data['ancestry'] ?? '');
      $ancestry_feats = CharacterManager::ANCESTRY_FEATS[$ancestry_name] ?? [];
      foreach ($ancestry_feats as $f) {
        if ($f['id'] === $character_data['ancestry_feat']) {
          $feats[] = ['type' => 'ancestry', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }
    }

    // Class feat.
    if (!empty($character_data['class_feat'])) {
      $class_feats = CharacterManager::CLASS_FEATS[$character_data['class'] ?? ''] ?? [];
      foreach ($class_feats as $f) {
        if ($f['id'] === $character_data['class_feat']) {
          $feats[] = ['type' => 'class', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
          break;
        }
      }
    }

    // General feat.
    if (!empty($character_data['general_feat'])) {
      foreach (CharacterManager::GENERAL_FEATS as $f) {
        if ($f['id'] === $character_data['general_feat']) {
          $feats[] = ['type' => 'general', 'id' => $f['id'], 'name' => $f['name'], 'level' => 1];
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

}
