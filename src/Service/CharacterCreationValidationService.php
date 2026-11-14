<?php

namespace Drupal\dungeoncrawler_content\Service;

use Drupal\Core\Form\OptGroup;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Html;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\dungeoncrawler_content\Service\Validation\CharacterCreationInput;

/**
 * Shared character rules and field definitions, independent of form execution.
 *
 * Native editors render these definitions; validation reads their constraints
 * directly and never builds, processes, or submits a Drupal form.
 */
class CharacterCreationValidationService {

  use StringTranslationTrait;

  public const STRUCTURED_AFFILIATION_CREATE_SENTINEL = '__create__';

  /**
   * Structured campaign-only affiliation fields exposed on the live wizard.
   */
  public const STRUCTURED_AFFILIATION_FIELDS = [
    'home_settlement_ref' => [
      'domain' => 'settlement',
      'title' => 'Home Settlement',
      'description' => 'Select the canonical campaign settlement this character calls home.',
      'multiple' => FALSE,
    ],
    'government_ref' => [
      'domain' => 'government',
      'title' => 'Government',
      'description' => 'Select the governing body or polity this character is tied to.',
      'multiple' => FALSE,
    ],
    'security_affiliation_refs' => [
      'domain' => 'security',
      'title' => 'Security Affiliations',
      'description' => 'Select law, military, or watch institutions this character serves or belongs to.',
      'multiple' => TRUE,
    ],
    'family_refs' => [
      'domain' => 'family',
      'title' => 'Family Affiliations',
      'description' => 'Select the canonical family, house, or clan institutions represented for this character.',
      'multiple' => TRUE,
    ],
    'religion_refs' => [
      'domain' => 'religion',
      'title' => 'Religious Affiliations',
      'description' => 'Select the canonical faith institutions represented for this character.',
      'multiple' => TRUE,
    ],
    'employer_refs' => [
      'domain' => 'employer',
      'title' => 'Employer Affiliations',
      'description' => 'Select the guild, employer, or trade institutions represented for this character.',
      'multiple' => TRUE,
    ],
    'order_refs' => [
      'domain' => 'education',
      'title' => 'Order Affiliations',
      'description' => 'Select canonical orders, schools, or training institutions represented for this character.',
      'multiple' => TRUE,
    ],
    'noble_refs' => [
      'domain' => 'noble',
      'title' => 'Noble Affiliations',
      'description' => 'Select canonical noble or patron institutions represented for this character.',
      'multiple' => TRUE,
    ],
    'criminal_refs' => [
      'domain' => 'criminal',
      'title' => 'Criminal Affiliations',
      'description' => 'Select canonical covert or criminal institutions represented for this character.',
      'multiple' => TRUE,
    ],
    'faction_refs' => [
      'domain' => 'allegiance',
      'title' => 'Faction Affiliations',
      'description' => 'Select canonical factions represented for this character. New faction creation goes through the canonical library generation flow.',
      'multiple' => TRUE,
    ],
    'culture_refs' => [
      'domain' => 'culture',
      'title' => 'Cultural Affiliations',
      'description' => 'Select canonical cultural or tribal institutions represented for this character.',
      'multiple' => TRUE,
    ],
  ];

  public function __construct(
    protected CharacterManager $characterManager,
    protected SchemaLoader $schemaLoader,
    protected Connection $database,
    protected AbilityScoreTracker $abilityScoreTracker,
    protected ImageGenerationIntegrationService $imageGenerationIntegration,
    protected FeatLibraryService $featLibrary,
    protected CampaignSubjectRegistryService $campaignSubjectRegistry,
    protected InstitutionNormalizationService $institutionNormalization,
    protected FactionGenerationService $factionGeneration,
  ) {}

  /**
   * Apply the moved step rules to inspected values, never a loaded form.
   */
  private function validateStepRules(int $step, CharacterCreationInput $form_state): void {
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
          $preview_character_data = $form_state->characterData();
          $preview_character_data['ancestry'] = $ancestry_val;
          $preview_character_data['heritage'] = $submitted_heritage;
          $feats_for_ancestry = $this->getCreationEligibleAncestryFeats($ancestry_name_val, $preview_character_data);
          $selected_ancestry_feat = trim((string) $form_state->getValue('ancestry_feat', ''));
          if (!empty($feats_for_ancestry) && $selected_ancestry_feat === '') {
            $form_state->setErrorByName('ancestry_feat', $this->t('Ancestry feat selection is required.'));
          }
          elseif ($selected_ancestry_feat !== '' && !in_array($selected_ancestry_feat, array_column($feats_for_ancestry, 'id'), TRUE)) {
            $form_state->setErrorByName('ancestry_feat', $this->t('Choose a valid ancestry feat for the current character build.'));
          }

          if ($selected_ancestry_feat === 'first-world-magic') {
            $this->validateFirstWorldMagicSelection($form_state);
          }
          if ($selected_ancestry_feat === 'otherworldly-magic') {
            $this->validateOtherworldlyMagicSelection($form_state);
          }
          if ($selected_ancestry_feat === 'general-training') {
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
          $bg_data = CharacterRulesCatalog::BACKGROUNDS[$bg_val] ?? NULL;
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
          $class_data_for_ka = CharacterRulesCatalog::CLASSES[$class_val_for_ka] ?? NULL;
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

            $preview_character_data = $form_state->characterData();
            $preview_character_data['class'] = $class_val_for_ka;
            $preview_character_data['class_feat'] = $selected_class_feat_for_ka;
            $preview_character_data['subclass'] = trim((string) $form_state->getValue('subclass', $preview_character_data['subclass'] ?? ''));
            $preview_character_data['arcane_thesis'] = trim((string) $form_state->getValue('arcane_thesis', $preview_character_data['arcane_thesis'] ?? ''));
            $eligible_class_feats = $this->getCreationEligibleClassFeats($class_val_for_ka, $preview_character_data);
            $selected_class_feat = trim((string) $form_state->getValue('class_feat', ''));
            if ($selected_class_feat === '') {
              $form_state->setErrorByName('class_feat', $this->t('Class feat selection is required.'));
            }
            elseif (
              $class_val_for_ka === 'wizard'
              && trim((string) $form_state->getValue('arcane_thesis', '')) === 'improved-familiar-attunement'
              && $selected_class_feat === 'familiar'
            ) {
              $form_state->setErrorByName('class_feat', $this->t('Improved Familiar Attunement already grants Familiar for free.'));
            }
            elseif (!in_array($selected_class_feat, array_column($eligible_class_feats, 'id'), TRUE)) {
              $form_state->setErrorByName('class_feat', $this->t('Choose a valid class feat for the current character build.'));
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

        $stored_character_data = $form_state->characterData();
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
        $animal_companion_source = $this->resolveAnimalCompanionSelectionSource($form_state, $form_state->characterData(), $class_val);
        if ($animal_companion_source !== NULL) {
          $this->validateAnimalCompanionSelection($form_state, $animal_companion_source);
        }
        $familiar_source = $this->resolveFamiliarSelectionSource($form_state, $form_state->characterData(), $class_val);
        if ($familiar_source !== NULL) {
          $this->validateFamiliarSelection($form_state, $familiar_source, $class_val);
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
        $stored_character_data = $form_state->characterData();
        if ($general_feat_value !== '' && !in_array($general_feat_value, array_column($this->getCreationEligibleGeneralFeats($stored_character_data), 'id'), TRUE)) {
          $form_state->setErrorByName('general_feat', $this->t('Choose a valid general feat for the current character build.'));
        }
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

        $campaign_id = (int) ($form_state->get('campaign_id') ?? 0);
        if ($campaign_id > 0) {
          $this->validateStructuredAffiliationSelections($form_state, $campaign_id);
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

  public function applySchemaValidationAttributes(array &$element, array $schema_fields, string $field_name): void {
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

  public function getSchemaConstraintValue(?array $constraint): mixed {
    if (!is_array($constraint)) {
      return NULL;
    }

    return $constraint['const'] ?? $constraint['default'] ?? NULL;
  }

  public function attachAbilityPreview(array &$form, array $character_data, string $help_text, bool $show_sources = TRUE, string $mode = 'compact'): void {
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

  public function buildStep1Fields(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, array $schema_fields): void {
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

  public function buildStep2Fields(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, array $schema_fields): void {
    // Step 2: Ancestry → Heritage → Ancestry Feat.
    // AJAX on ancestry select refreshes #heritage-path-wrapper (heritage + feat).
    // Validation is in validateForm() case 2 (not #required, to avoid :invalid).
    $heritage_payload = [];
    foreach (CharacterRulesCatalog::HERITAGES as $ancestry_name => $heritages) {
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

    foreach (CharacterRulesCatalog::ANCESTRIES as $ancestry_name => $ancestry_data) {
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
      $ancestry_feats = $this->getCreationEligibleAncestryFeats($ancestry_name, $character_data);

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
            $this->resolveFeatDisplayDescription($feat),
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

  public function buildStep3Fields(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, array $schema_fields): void {
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
    $bg_boost_data = !empty($selected_background_for_boosts) ? (CharacterRulesCatalog::BACKGROUNDS[$selected_background_for_boosts] ?? NULL) : NULL;
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
    if ($has_fixed_boost) {
      $fixed_ability = $this->abilityScoreTracker->normalizeAbilityKey($bg_boost_data['fixed_boost']);
      if (isset($abilities_data[$fixed_ability])) {
        $abilities_data[$fixed_ability]['disabled'] = TRUE;
      }
    }

    $form['background_dynamic'] = [
      '#type' => 'container',
      '#tree' => FALSE,
      '#attributes' => [
        'id' => 'background-dynamic-wrapper',
      ],
    ];

    if ($selected_background_for_boosts === '') {
      $form['background_dynamic']['background_boosts_pending'] = [
        '#markup' => '<div class="section-instructions background-boosts-section">'
        . '<h3>' . $this->t('Background Ability Boosts') . '</h3>'
        . '<p>' . $this->t('Choose a pre-campaign background first. Background ability boosts are determined only after that selection.') . '</p>'
        . '</div>',
      ];
      $form['background_dynamic']['background_boosts'] = [
        '#type' => 'hidden',
        '#default_value' => json_encode([]),
        '#attributes' => ['id' => 'background-boosts-field'],
      ];
      return;
    }

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

    // Background Skill Training.
    $selected_background = $selected_background_for_boosts;
    if (!empty($selected_background)) {
      $background_data = CharacterRulesCatalog::BACKGROUNDS[$selected_background] ?? NULL;

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

        // For backgrounds with skill choices (like Scholar), add selector.
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

  public function buildStep4Fields(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, array $schema_fields): void {
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

    $class_data = CharacterRulesCatalog::CLASSES[$selected_class] ?? NULL;
    if (!$class_data) {
      return;
    }

    $selected_class_feat = (string) ($form_state->getValue('class_feat') ?: ($character_data['class_feat'] ?? ''));

    // Key Ability Selection.
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

    // Class Feat Selection.
    $class_feats = $this->getCreationEligibleClassFeats($selected_class, $character_data);

    if (!empty($class_feats)) {
      $form['class_dynamic']['class_feat_section'] = [
        '#markup' => '<div class="section-instructions class-feat-section">'
        . '<h3>' . $this->t('Class Feat') . '</h3>'
        . '<p>' . $this->t('Choose one 1st-level class feat. This represents specialized training or a unique technique for your class.') . '</p>'
        . '</div>',
      ];

      $selected_bonus_feat = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
      $feat_options = [];
      $feat_cards = [];

      foreach ($class_feats as $feat) {
        $duplicate_tags = [];
        if ($selected_bonus_feat !== '' && $selected_bonus_feat === (string) ($feat['id'] ?? '') && $selected_bonus_feat !== $selected_class_feat) {
          $duplicate_tags[] = (string) $this->t('Already selected by Natural Ambition');
        }
        if (
          $selected_class === 'wizard'
          && trim((string) ($character_data['arcane_thesis'] ?? '')) === 'improved-familiar-attunement'
          && (string) ($feat['id'] ?? '') === 'familiar'
          && $selected_class_feat !== 'familiar'
        ) {
          $duplicate_tags[] = (string) $this->t('Already granted by arcane thesis');
        }
        $feat_options[$feat['id']] = $feat['name'];
        $feat_cards[$feat['id']] = $this->buildOptionCardData(
          $this->resolveFeatDisplayDescription($feat),
          array_values(array_merge($feat['traits'] ?? [], $duplicate_tags)),
          [
            (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
          ],
        );
      }

      $form['class_dynamic']['class_feat'] = [
        '#type' => 'radios',
        '#title' => $this->t('Select Class Feat'),
        '#options' => $feat_options,
        '#default_value' => ($character_data['class_feat'] ?? '') ?: NULL,
        '#required' => TRUE,
        '#description' => $this->t('Each feat provides unique tactical options that define your combat style.'),
        '#ajax' => [
          'callback' => '::updateClassOptions',
          'wrapper' => 'class-dynamic-wrapper',
          'event' => 'change',
        ],
      ];
      $class_feat_disabled_ids = [];
      $selected_bonus_feat = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
      if ($selected_bonus_feat !== '' && $selected_bonus_feat !== $selected_class_feat) {
        $class_feat_disabled_ids[] = $selected_bonus_feat;
      }
      if (
        $selected_class === 'wizard'
        && trim((string) ($character_data['arcane_thesis'] ?? '')) === 'improved-familiar-attunement'
        && $selected_class_feat !== 'familiar'
      ) {
        $class_feat_disabled_ids[] = 'familiar';
      }
      $this->attachOptionCardSettings($form['class_dynamic'], 'class_feat', $feat_cards, 'single', $class_feat_disabled_ids);

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
      $familiar_source = $this->resolveFamiliarSelectionSource($form_state, $character_data, $selected_class);
      if ($familiar_source !== NULL) {
        $this->buildFamiliarSelectionSection($form['class_dynamic'], $form_state, $character_data, $familiar_source);
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
        '#default_value' => ($character_data['subclass'] ?? '') ?: NULL,
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
      foreach ((CharacterRulesCatalog::CLASSES['wizard']['arcane_thesis']['options'] ?? []) as $thesis_id => $thesis) {
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
        '#default_value' => CharacterManager::getSelectedCantripIds($character_data),
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
        '#default_value' => CharacterManager::getSelectedFirstLevelSpellIds($character_data),
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
      $tradition_source = match ($selected_class) {
        'sorcerer' => 'bloodline',
        'witch' => 'patron',
        'summoner' => 'eidolon type',
        default => 'class option',
      };
      $form['class_dynamic']['spells_pending'] = [
        '#markup' => '<div class="section-instructions spells-pending">'
        . '<p><em>' . $this->t('Select your @thing above to unlock spell selection.', [
          '@thing' => $tradition_source,
        ]) . '</em></p>'
        . '</div>',
      ];
    }

    $this->applyStep4TabbedLayout($form['class_dynamic'], $form_state, $selected_class);
  }

  public function applyStep4TabbedLayout(array &$container, FormStateInterface|CharacterCreationInput $form_state, string $selected_class): void {
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

  public function registerStep4Panel(array &$container, array &$tabs, string $tab_id, string|\Stringable $label, array $start_candidates, array $end_candidates): void {
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

  public function getStep4SubclassTabLabel(string $selected_class): string {
    return match ($selected_class) {
      'sorcerer' => (string) $this->t('Bloodline'),
      'witch' => (string) $this->t('Patron'),
      'wizard' => (string) $this->t('Arcane School'),
      default => (string) $this->t('Subclass'),
    };
  }

  public function getStep4FeatSelectionTabLabel(string $selection_key): string {
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

  public function buildStep5Fields(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, array $schema_fields): void {
    // Step 5: Free Ability Boosts (Pathbuilder-style interactive selection)
    // Calculate current scores from ancestry + background + class.
    $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);

    $abilities_data = $this->buildInteractiveAbilityData($calculation, $character_data['free_boosts'] ?? []);

    $form['abilities_help'] = [
      '#markup' => '<div class="section-instructions">'
      . '<p><strong>' . $this->t('Choose 4 abilities to boost') . '</strong></p>'
      . '<p>' . $this->t('You have 4 free ability boosts to assign. Each boost adds +2 to an ability score (or +1 if the score is already 18 or higher). You cannot boost the same ability twice in this step.') . '</p>'
      . '<p class="tip">' . $this->t('💡 Tip: Consider boosting your class\'s key ability and abilities that complement your playstyle. Most characters benefit from having at least one high ability score (16-18).') . '</p>'
      . '</div>',
    ];

    // Render interactive ability widget using Twig template.
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

    // Hidden field to store selected boosts.
    $form['free_boosts'] = [
      '#type' => 'hidden',
      '#default_value' => json_encode($character_data['free_boosts'] ?? []),
      '#attributes' => ['id' => 'free-boosts-field'],
    ];
  }

  public function buildStep6Fields(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, array $schema_fields): void {
    $calculation = $this->abilityScoreTracker->calculateAbilityScores($character_data);
    $this->attachAbilityPreview($form, $character_data, 'Final ability scores');

    // Skill Training Selection.
    $selected_class = $character_data['class'] ?? '';
    if (!empty($selected_class)) {
      $class_data = CharacterRulesCatalog::CLASSES[$selected_class] ?? NULL;
      if ($class_data) {
        $trained_skills = $class_data['trained_skills'] ?? 3;

        // Calculate Intelligence modifier for bonus skills.
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

    $campaign_id = (int) ($form_state->get('campaign_id') ?? 0);
    if ($campaign_id > 0) {
      $this->buildStructuredAffiliationFields($form, $character_data, $campaign_id);
    }

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
    $selected_general_feat = (string) ($form_state->getValue('general_feat') ?: ($character_data['general_feat'] ?? ''));
    $selected_bonus_general_feat = trim((string) ($character_data['feat_selections']['general-training']['bonus_general_feat'] ?? ''));
    foreach ($this->getCreationEligibleGeneralFeats($character_data) as $feat) {
      $duplicate_tags = [];
      if ($selected_bonus_general_feat !== '' && $selected_bonus_general_feat === (string) ($feat['id'] ?? '') && $selected_bonus_general_feat !== $selected_general_feat) {
        $duplicate_tags[] = (string) $this->t('Already selected by General Training');
      }
      $general_feat_options[$feat['id']] = $feat['name'];
      $general_feat_cards[$feat['id']] = $this->buildOptionCardData(
        $this->resolveFeatDisplayDescription($feat),
        array_values(array_merge($feat['traits'] ?? [], $duplicate_tags)),
        [
          (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
        ],
      );
    }

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
    $general_feat_disabled_ids = [];
    $selected_bonus_general_feat = trim((string) ($character_data['feat_selections']['general-training']['bonus_general_feat'] ?? ''));
    if ($selected_bonus_general_feat !== '' && $selected_bonus_general_feat !== $selected_general_feat) {
      $general_feat_disabled_ids[] = $selected_bonus_general_feat;
    }
    $this->attachOptionCardSettings($form['general_feat_dynamic'], 'general_feat', $general_feat_cards, 'single', $general_feat_disabled_ids);

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

  public function buildStep7Fields(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, array $schema_fields): void {
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
    $class_loadout_preset = $this->buildStep7ClassLoadoutPreset((string) ($character_data['class'] ?? ''), $catalog_by_id);

    if ($class_loadout_preset !== NULL) {
      $preset_is_selected = $this->step7SelectionMatchesPreset($selected_ids, $class_loadout_preset['ids']);
      $form['class_default_loadout'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => array_values(array_filter([
            'step7-loadout-preset',
            $preset_is_selected ? 'step7-loadout-preset--active' : '',
          ])),
          'data-step7-preset-card' => $class_loadout_preset['id'],
        ],
      ];
      $form['class_default_loadout']['intro'] = [
        '#markup' => '<div class="step7-loadout-preset__header">'
        . '<div class="step7-loadout-preset__heading">'
        . '<h3>' . $this->t('@class Default Loadout', ['@class' => $class_loadout_preset['class_label']]) . '</h3>'
        . '<p>' . $this->t('Apply the recommended @class kit, then fine-tune individual items below if you want to customize it for this campaign.', [
          '@class' => $class_loadout_preset['class_label'],
        ]) . '</p>'
        . '</div>'
        . '<div class="step7-loadout-preset__facts">'
        . '<div class="step7-loadout-preset__fact-card"><span class="step7-loadout-preset__fact-label">' . $this->t('Items') . '</span><span class="step7-loadout-preset__fact-value">' . count($class_loadout_preset['items']) . '</span></div>'
        . '<div class="step7-loadout-preset__fact-card"><span class="step7-loadout-preset__fact-label">' . $this->t('Cost') . '</span><span class="step7-loadout-preset__fact-value">' . number_format((float) $class_loadout_preset['cost'], 1) . ' gp</span></div>'
        . '<div class="step7-loadout-preset__fact-card"><span class="step7-loadout-preset__fact-label">' . $this->t('Gold Left') . '</span><span class="step7-loadout-preset__fact-value">' . number_format(max(0, 15 - (float) $class_loadout_preset['cost']), 1) . ' gp</span></div>'
        . '</div>'
        . '</div>',
      ];
      $form['class_default_loadout']['status'] = [
        '#markup' => '<p class="step7-loadout-preset__status" data-step7-preset-status="' . Html::escape($class_loadout_preset['id']) . '">'
        . ($preset_is_selected
            ? $this->t('This loadout is currently applied.')
            : $this->t('This loadout is ready to apply.'))
        . '</p>',
      ];
      $form['class_default_loadout']['items'] = [
        '#markup' => $this->buildStep7LoadoutItemMarkup($class_loadout_preset['items']),
      ];
      $form['class_default_loadout']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['step7-loadout-preset__actions']],
      ];
      $form['class_default_loadout']['actions']['apply'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Apply Loadout'),
        '#attributes' => [
          'type' => 'button',
          'data-step7-loadout-apply' => $class_loadout_preset['id'],
          'class' => ['btn', 'btn-primary', 'step7-loadout-preset__button'],
        ],
      ];
      $form['class_default_loadout']['actions']['clear'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => $this->t('Clear Equipment'),
        '#attributes' => [
          'type' => 'button',
          'data-step7-loadout-clear' => '1',
          'class' => ['btn', 'btn-secondary', 'step7-loadout-preset__button'],
        ],
      ];
    }

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

    // Organize equipment by category.
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

    // Build categorized options.
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
      'presets' => $class_loadout_preset !== NULL
        ? [
          $class_loadout_preset['id'] => [
            'label' => $class_loadout_preset['class_label'],
            'cost' => (float) $class_loadout_preset['cost'],
            'ids' => $class_loadout_preset['ids'],
          ],
        ]
        : [],
      'activePresetId' => ($class_loadout_preset !== NULL && $this->step7SelectionMatchesPreset($selected_ids, $class_loadout_preset['ids']))
        ? $class_loadout_preset['id']
        : '',
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

  public function buildStep7ClassLoadoutPreset(string $class_id, array $catalog_by_id): ?array {
    $class_id = strtolower(trim($class_id));
    if ($class_id === '' || $catalog_by_id === []) {
      return NULL;
    }

    $ids = CharacterManager::getDefaultEquipmentLoadoutIdsForClass($class_id, array_keys($catalog_by_id));
    if ($ids === []) {
      return NULL;
    }

    $items = [];
    $cost = 0.0;
    foreach ($ids as $item_id) {
      if (!isset($catalog_by_id[$item_id])) {
        continue;
      }

      $items[] = $catalog_by_id[$item_id];
      $cost += (float) ($catalog_by_id[$item_id]['cost'] ?? 0);
    }

    if ($items === []) {
      return NULL;
    }

    return [
      'id' => sprintf('%s_default', $class_id),
      'class_id' => $class_id,
      'class_label' => ucwords(str_replace(['-', '_'], ' ', $class_id)),
      'ids' => array_values(array_unique(array_column($items, 'id'))),
      'items' => $items,
      'cost' => round($cost, 2),
    ];
  }

  public function buildStep7LoadoutItemMarkup(array $items): string {
    $list_items = array_map(static function (array $item): string {
      return '<li class="step7-loadout-preset__list-item">' . Html::escape((string) ($item['name'] ?? 'Unknown item')) . '</li>';
    }, $items);

    return '<div class="step7-loadout-preset__items-block">'
      . '<p class="step7-loadout-preset__items-title">' . $this->t('Included equipment') . '</p>'
      . '<ul class="step7-loadout-preset__list">' . implode('', $list_items) . '</ul>'
      . '</div>';
  }

  public function step7SelectionMatchesPreset(array $selected_ids, array $preset_ids): bool {
    sort($selected_ids);
    sort($preset_ids);
    return $selected_ids === $preset_ids;
  }

  public function buildStep8Fields(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, array $schema_fields): void {
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

  public function collectPendingCreationSelectionGrants(array $character_data): array {
    $selection_grants = $character_data['features']['featEffects']['selection_grants'] ?? [];
    if (!is_array($selection_grants)) {
      return [];
    }

    $pending = [];
    foreach ($selection_grants as $grant) {
      if (!is_array($grant)) {
        continue;
      }

      $status = strtolower(trim((string) ($grant['status'] ?? '')));
      if ($status === 'pending_choice' && !$this->isSelectionGrantResolved($grant, $character_data)) {
        $pending[] = $grant;
      }
    }

    return $pending;
  }

  public function isSelectionGrantResolved(array $grant, array $character_data): bool {
    $selection_type = trim((string) ($grant['selection_type'] ?? ''));
    return match ($selection_type) {
      'bonus_class_feat' => $this->isBonusClassFeatSelectionResolved($character_data),
      'familiar_creation' => $this->isFamiliarCreationSelectionResolved($character_data),
      default => FALSE,
    };
  }

  public function isBonusClassFeatSelectionResolved(array $character_data): bool {
    if (trim((string) ($character_data['ancestry_feat'] ?? '')) !== 'natural-ambition') {
      return TRUE;
    }

    $selected = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
    return $selected !== '';
  }

  public function isFamiliarCreationSelectionResolved(array $character_data): bool {
    if (!$this->hasActiveFamiliarRequirement($character_data)) {
      return TRUE;
    }

    $expected_count = (($class_id = strtolower(trim((string) ($character_data['class'] ?? '')))) === 'wizard'
      && strtolower(trim((string) ($character_data['arcane_thesis'] ?? ''))) === 'improved-familiar-attunement')
      ? 3
      : 2;
    $feat_selections = is_array($character_data['feat_selections'] ?? NULL) ? $character_data['feat_selections'] : [];
    foreach ($feat_selections as $selection) {
      if (!is_array($selection)) {
        continue;
      }

      $abilities = self::normalizeList($selection['selected_familiar_abilities'] ?? []);
      if (count($abilities) === $expected_count) {
        return TRUE;
      }
    }

    $familiar_payload = is_array($character_data['familiar'] ?? NULL) ? $character_data['familiar'] : [];
    $familiar_abilities = self::normalizeList($familiar_payload['abilities'] ?? []);
    return count($familiar_abilities) === $expected_count;
  }

  public function hasActiveFamiliarRequirement(array $character_data): bool {
    $class_feat = trim((string) ($character_data['class_feat'] ?? ''));
    $bonus_feat = trim((string) ($character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''));
    $class_id = strtolower(trim((string) ($character_data['class'] ?? '')));
    $subclass = strtolower(trim((string) ($character_data['subclass'] ?? '')));
    $arcane_thesis = strtolower(trim((string) ($character_data['arcane_thesis'] ?? '')));
    $familiar_sources = $this->getFamiliarSelectionSourceIds();

    return in_array($class_feat, $familiar_sources, TRUE)
      || in_array($bonus_feat, $familiar_sources, TRUE)
      || ($class_id === 'druid' && $subclass === 'leaf')
      || ($class_id === 'wizard' && $arcane_thesis === 'improved-familiar-attunement')
      || $class_id === 'witch';
  }

  public function getEquipmentCatalog(): array {
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

  public function buildEquipmentCatalogFromTemplates(): array {
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

  public function normalizeTags(string $raw_tags): array {
    $decoded = json_decode($raw_tags, TRUE);
    if (is_array($decoded)) {
      return array_values(array_filter(array_map(static fn($tag): string => (string) $tag, $decoded)));
    }

    return [];
  }

  public function normalizeLoreSkillName(string $lore_name): string {
    $normalized = preg_replace('/\s+/', ' ', trim($lore_name)) ?? '';
    if ($normalized === '') {
      return '';
    }
    if (!preg_match('/\blore$/i', $normalized)) {
      $normalized .= ' Lore';
    }
    return $normalized;
  }

  public function mapTemplateItemCategory(string $item_type, array $tags): string {
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

  public function getPortraitGenerationAvailability(): array {
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

  public function getAncestryOptions(): array {
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterRulesCatalog::ANCESTRIES as $name => $data) {
      $options[self::ancestryMachineId($name)] = $name;
    }
    return $options;
  }

  public function getHeritageOptions(string $ancestry): array {
    $options = ['' => $this->t('- Select -')];
    if ($ancestry) {
      $ancestry_name = $this->resolveAncestryName((string) $ancestry);
      $heritages = CharacterRulesCatalog::HERITAGES[$ancestry_name] ?? [];
      foreach ($heritages as $heritage) {
        $options[$heritage['id']] = $heritage['name'];
      }
    }
    return $options;
  }

  public function buildInteractiveAbilityData(array $calculation, array $selected_boosts): array {
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

  public static function normalizeList(mixed $value): array {
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
      return $item !== NULL
      && $item !== ''
      && $item !== FALSE
      && $item !== 0
      && $item !== '0';
    }));
  }

  public static function ancestryMachineId(string $name): string {
    return strtolower(str_replace(' ', '-', $name));
  }

  public function resolveAncestryName(string $ancestry_id): string {
    if ($ancestry_id === '') {
      return '';
    }

    foreach (array_keys(CharacterRulesCatalog::ANCESTRIES) as $name) {
      if (self::ancestryMachineId($name) === strtolower($ancestry_id)) {
        return $name;
      }
    }

    return str_replace('-', ' ', ucwords($ancestry_id, '-'));
  }

  public function getAdoptedAncestryOptions(array $character_data): array {
    $current_ancestry = trim((string) ($character_data['ancestry'] ?? ''));
    $options = [];
    foreach (array_keys(CharacterRulesCatalog::ANCESTRIES) as $ancestry_name) {
      $ancestry_id = self::ancestryMachineId($ancestry_name);
      if ($ancestry_id === $current_ancestry) {
        continue;
      }
      $options[$ancestry_id] = $ancestry_name;
    }

    return $options;
  }

  public function getStaffNexusSpellOptions(FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $tradition): array {
    $selected_cantrip_ids = self::normalizeList(
      $form_state->getValue('cantrips', CharacterManager::getSelectedCantripIds($character_data))
    );
    $selected_spell_ids = self::normalizeList(
      $form_state->getValue('spells_first', CharacterManager::getSelectedFirstLevelSpellIds($character_data))
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

  public function getBackgroundOptions(): array {
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterRulesCatalog::BACKGROUNDS as $bg) {
      $options[$bg['id']] = $bg['name'];
    }
    return $options;
  }

  public function getClassOptions(): array {
    $options = ['' => $this->t('- Select -')];
    foreach (CharacterRulesCatalog::CLASSES as $class) {
      $options[$class['id']] = $class['name'];
    }
    return $options;
  }

  public function getAlignmentOptions(): array {
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

  public function buildStructuredAffiliationFields(array &$form, array $character_data, int $campaign_id): void {
    $options_by_domain = $this->getCampaignInstitutionOptionsByDomain($campaign_id);
    $parent_options = $this->getCampaignInstitutionParentOptions($campaign_id);
    $has_any_options = FALSE;
    foreach ($options_by_domain as $options) {
      if ($options !== []) {
        $has_any_options = TRUE;
        break;
      }
    }

    $form['structured_affiliations'] = [
      '#type' => 'details',
      '#title' => $this->t('Campaign Affiliations'),
      '#open' => TRUE,
      '#tree' => FALSE,
      '#attributes' => ['class' => ['campaign-affiliations-section']],
    ];
    $form['structured_affiliations']['intro'] = [
      '#markup' => '<p>' . $this->t('Choose canonical campaign institutions for represented affiliations. If the correct institution does not exist yet, create it here with a canonical label, optional parent institution, and provenance note. This wizard stores subject ids directly; freeform affiliation prose is not supported as the system of record.') . '</p>',
    ];

    if (!$has_any_options) {
      $form['structured_affiliations']['empty_state'] = [
        '#markup' => '<div class="messages messages--warning">'
        . $this->t('No campaign institution subjects are currently available for these affiliation pickers yet.')
        . '</div>',
      ];
    }

    foreach (self::STRUCTURED_AFFILIATION_FIELDS as $field => $definition) {
      $domain_options = $options_by_domain[$definition['domain']] ?? [];
      $single_default = self::normalizeList($character_data[$field] ?? '');
      $default_value = !empty($definition['multiple'])
        ? self::normalizeList($character_data[$field] ?? [])
        : ($single_default[0] ?? '');

      $element = [
        '#type' => 'select',
        '#title' => $this->t($definition['title']),
        '#description' => $this->t($definition['description']),
        '#options' => !empty($definition['multiple'])
          ? ($domain_options + [self::STRUCTURED_AFFILIATION_CREATE_SENTINEL => (string) $this->t('Create new below')])
          : ['' => $this->t('- None -')] + $domain_options + [self::STRUCTURED_AFFILIATION_CREATE_SENTINEL => (string) $this->t('Create new below')],
        '#default_value' => $default_value,
        '#required' => FALSE,
      ];

      if (!empty($definition['multiple'])) {
        $element['#multiple'] = TRUE;
        $element['#size'] = max(4, min(8, count($domain_options) ?: 4));
      }

      $form['structured_affiliations'][$field] = $element;
      $this->buildStructuredAffiliationCreationElements($form['structured_affiliations'], $field, $definition, $parent_options);
    }
  }

  public function validateStructuredAffiliationSelections(CharacterCreationInput $form_state, int $campaign_id): void {
    $options_by_domain = $this->getCampaignInstitutionOptionsByDomain($campaign_id);
    $rows_by_domain = $this->getCampaignInstitutionRowsByDomain($campaign_id);
    $parent_options = $this->getCampaignInstitutionParentOptions($campaign_id);

    foreach (self::STRUCTURED_AFFILIATION_FIELDS as $field => $definition) {
      $allowed_subject_ids = array_keys($options_by_domain[$definition['domain']] ?? []);
      $selected_subject_ids = !empty($definition['multiple'])
        ? self::normalizeList($form_state->getValue($field, []))
        : self::normalizeList($form_state->getValue($field, ''));
      $create_labels = $this->parseStructuredAffiliationCreateLabels($form_state->getValue($this->getStructuredAffiliationCreateLabelsKey($field), ''));
      $parent_subject_id = trim((string) $form_state->getValue($this->getStructuredAffiliationCreateParentKey($field), ''));
      $provenance_note = trim((string) $form_state->getValue($this->getStructuredAffiliationCreateNoteKey($field), ''));
      $confirmed = (bool) $form_state->getValue($this->getStructuredAffiliationCreateConfirmKey($field), FALSE);
      $creating_new = $create_labels !== []
        || $parent_subject_id !== ''
        || $provenance_note !== ''
        || $confirmed;
      $selected_subject_ids = array_values(array_filter(
        $selected_subject_ids,
        static fn(string $subject_id): bool => $subject_id !== self::STRUCTURED_AFFILIATION_CREATE_SENTINEL
      ));

      if (!empty($definition['multiple']) === FALSE && (count($selected_subject_ids) + count($create_labels)) > 1) {
        $form_state->setErrorByName($field, $this->t('Select one existing institution or create one new institution for @label, not both.', [
          '@label' => $definition['title'],
        ]));
      }

      foreach ($selected_subject_ids as $subject_id) {
        if (!in_array($subject_id, $allowed_subject_ids, TRUE)) {
          $form_state->setErrorByName($field, $this->t('Select valid campaign subject ids for @label.', [
            '@label' => $definition['title'],
          ]));
          break;
        }
      }

      if (!$creating_new) {
        continue;
      }

      if (!$this->campaignSubjectRegistry->isSubjectRegistryReady()) {
        $form_state->setErrorByName($field, $this->t('Campaign institution creation requires the campaign subject registry to be installed.'));
        continue;
      }

      if ($this->isFactionGenerationField($field) && !$this->factionGeneration->isGenerationStorageReady()) {
        $form_state->setErrorByName($field, $this->t('Faction generation requires the library manifest and campaign subject registry storage to be installed.'));
        continue;
      }

      if ($create_labels === []) {
        $form_state->setErrorByName($this->getStructuredAffiliationCreateLabelsKey($field), $this->t('Enter the canonical institution label to create for @label.', [
          '@label' => $definition['title'],
        ]));
        continue;
      }

      if ($parent_subject_id !== '' && !array_key_exists($parent_subject_id, $parent_options)) {
        $form_state->setErrorByName($this->getStructuredAffiliationCreateParentKey($field), $this->t('Select a valid parent institution.'));
      }

      foreach ($create_labels as $label) {
        if ($this->isFactionGenerationField($field)) {
          try {
            $this->factionGeneration->normalizeNarrativeNeedRequest($campaign_id, $this->buildFactionGenerationRequestFromForm($form_state, $field, $label));
          }
          catch (\InvalidArgumentException $exception) {
            $form_state->setErrorByName($this->getStructuredAffiliationCreateLabelsKey($field), $this->t($exception->getMessage()));
            continue 2;
          }
        }

        try {
          $normalized = $this->institutionNormalization->normalizeInstitutionInput([
            'domain' => $definition['domain'],
            'display_name' => $label,
          ]);
        }
        catch (\InvalidArgumentException) {
          $form_state->setErrorByName($this->getStructuredAffiliationCreateLabelsKey($field), $this->t('Enter a valid canonical label for @label.', [
            '@label' => $definition['title'],
          ]));
          continue 2;
        }

        $exact_match = $this->findExactInstitutionMatch($normalized['normalized_label'], $rows_by_domain[$definition['domain']] ?? []);
        if ($exact_match !== NULL) {
          $form_state->setErrorByName($this->getStructuredAffiliationCreateLabelsKey($field), $this->t('The institution %label already exists. Select the existing canonical institution instead of creating a duplicate.', [
            '%label' => $exact_match['display_name'],
          ]));
          continue 2;
        }

        $near_matches = $this->findLikelyInstitutionNearMatches($normalized['normalized_label'], $rows_by_domain[$definition['domain']] ?? []);
        if ($near_matches !== [] && !$confirmed) {
          $form_state->setErrorByName($this->getStructuredAffiliationCreateConfirmKey($field), $this->t('Possible duplicate institutions found for %label: %matches. Confirm that you want to create a new canonical institution anyway.', [
            '%label' => $label,
            '%matches' => implode(', ', $near_matches),
          ]));
          continue 2;
        }
      }
    }
  }

  public function buildStructuredAffiliationCreationElements(array &$form, string $field, array $definition, array $parent_options): void {
    $details_key = $field . '__create_details';
    $labels_key = $this->getStructuredAffiliationCreateLabelsKey($field);
    $parent_key = $this->getStructuredAffiliationCreateParentKey($field);
    $note_key = $this->getStructuredAffiliationCreateNoteKey($field);
    $confirm_key = $this->getStructuredAffiliationCreateConfirmKey($field);

    $form[$details_key] = [
      '#type' => 'details',
      '#title' => $this->t('Create new @label', ['@label' => strtolower($definition['title'])]),
      '#open' => FALSE,
      '#attributes' => ['class' => ['campaign-affiliations-create']],
    ];
    $form[$details_key]['intro'] = [
      '#markup' => '<p>' . $this->t('Search/select existing institutions first. Use this only when the canonical institution does not already exist in the campaign registry.') . '</p>',
    ];
    $form[$details_key][$labels_key] = [
      '#type' => !empty($definition['multiple']) ? 'textarea' : 'textfield',
      '#title' => $this->t('Canonical label'),
      '#description' => !empty($definition['multiple'])
        ? $this->t('Enter one canonical label per line to create additional institutions in the @domain domain.', ['@domain' => $definition['domain']])
        : $this->t('Enter the canonical label to create in the @domain domain.', ['@domain' => $definition['domain']]),
      '#rows' => !empty($definition['multiple']) ? 3 : NULL,
      '#required' => FALSE,
    ];
    $form[$details_key][$parent_key] = [
      '#type' => 'select',
      '#title' => $this->t('Parent institution (optional)'),
      '#options' => ['' => $this->t('- None -')] + $parent_options,
      '#required' => FALSE,
      '#description' => $this->t('If this institution belongs under an existing canonical institution, select the parent here.'),
    ];
    $form[$details_key][$note_key] = [
      '#type' => 'textfield',
      '#title' => $this->t('Provenance note'),
      '#maxlength' => 255,
      '#required' => FALSE,
      '#description' => $this->t('Describe where this institution label came from so the creation decision remains auditable.'),
    ];
    if ($this->isFactionGenerationField($field)) {
      $role_key = $this->getStructuredAffiliationCreateRoleKey($field);
      $why_key = $this->getStructuredAffiliationCreateWhyKey($field);
      $public_face_key = $this->getStructuredAffiliationCreatePublicFaceKey($field);
      $hidden_face_key = $this->getStructuredAffiliationCreateHiddenFaceKey($field);
      $ideology_key = $this->getStructuredAffiliationCreateIdeologyTagsKey($field);
      $method_key = $this->getStructuredAffiliationCreateMethodTagsKey($field);
      $membership_style_key = $this->getStructuredAffiliationCreateMembershipStyleKey($field);

      $form[$details_key]['faction_generation_intro'] = [
        '#markup' => '<p>' . $this->t('New factions are generated into the canonical library first, then instantiated into this campaign. Capture the narrative need and core characteristics so the created faction stays reusable and auditable.') . '</p>',
      ];
      $form[$details_key][$role_key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Narrative role'),
        '#maxlength' => 255,
        '#required' => FALSE,
        '#description' => $this->t('Describe what role this faction needs to play in the story or scene.'),
      ];
      $form[$details_key][$why_key] = [
        '#type' => 'textarea',
        '#title' => $this->t('Why existing factions are insufficient'),
        '#rows' => 3,
        '#required' => FALSE,
        '#description' => $this->t('Required. Explain why no existing canonical faction satisfies this narrative need.'),
      ];
      $form[$details_key][$public_face_key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Public face'),
        '#maxlength' => 255,
        '#required' => FALSE,
        '#description' => $this->t('How the faction presents itself to outsiders.'),
      ];
      $form[$details_key][$hidden_face_key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Hidden face'),
        '#maxlength' => 255,
        '#required' => FALSE,
        '#description' => $this->t('Optional covert purpose, secret agenda, or internal truth.'),
      ];
      $form[$details_key][$ideology_key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Ideology tags'),
        '#maxlength' => 255,
        '#required' => FALSE,
        '#description' => $this->t('Comma-separated values describing the faction worldview or values.'),
      ];
      $form[$details_key][$method_key] = [
        '#type' => 'textfield',
        '#title' => $this->t('Method tags'),
        '#maxlength' => 255,
        '#required' => FALSE,
        '#description' => $this->t('Comma-separated values describing how the faction tends to operate.'),
      ];
      $form[$details_key][$membership_style_key] = [
        '#type' => 'select',
        '#title' => $this->t('Membership style'),
        '#options' => [
          'invite_only' => $this->t('Invite only'),
          'open' => $this->t('Open'),
          'cell_based' => $this->t('Cell based'),
          'secret' => $this->t('Secret'),
        ],
        '#default_value' => 'invite_only',
        '#required' => FALSE,
      ];
    }
    $form[$details_key][$confirm_key] = [
      '#type' => 'checkbox',
      '#title' => $this->t('I have reviewed possible near matches and still want to create a new canonical institution.'),
      '#required' => FALSE,
    ];
  }

  public function getCampaignInstitutionRowsByDomain(int $campaign_id): array {
    $rows_by_domain = [];
    foreach (self::STRUCTURED_AFFILIATION_FIELDS as $definition) {
      $rows_by_domain[$definition['domain']] ??= [];
    }

    if ($campaign_id <= 0 || !$this->database->schema()->tableExists('dc_campaign_subject_registry')) {
      return $rows_by_domain;
    }

    $domains = array_keys($rows_by_domain);
    $rows = $this->database->select('dc_campaign_subject_registry', 'r')
      ->fields('r', ['subject_id', 'subject_domain', 'display_name', 'normalized_label'])
      ->condition('campaign_id', $campaign_id)
      ->condition('subject_kind', 'institution')
      ->condition('subject_domain', $domains, 'IN')
      ->orderBy('subject_domain')
      ->orderBy('display_name')
      ->execute()
      ->fetchAll(\PDO::FETCH_ASSOC);

    if (!is_array($rows)) {
      return $rows_by_domain;
    }

    foreach ($rows as $row) {
      $domain = trim((string) ($row['subject_domain'] ?? ''));
      $subject_id = trim((string) ($row['subject_id'] ?? ''));
      $display_name = trim((string) ($row['display_name'] ?? ''));
      $normalized_label = trim((string) ($row['normalized_label'] ?? ''));
      if ($domain === '' || $subject_id === '' || $display_name === '' || !isset($rows_by_domain[$domain])) {
        continue;
      }
      if ($normalized_label === '') {
        $normalized_label = $this->institutionNormalization->normalizeToken($display_name);
      }
      $rows_by_domain[$domain][] = [
        'subject_id' => $subject_id,
        'display_name' => $display_name,
        'normalized_label' => $normalized_label,
      ];
    }

    return $rows_by_domain;
  }

  public function getCampaignInstitutionOptionsByDomain(int $campaign_id): array {
    $options_by_domain = [];
    foreach (self::STRUCTURED_AFFILIATION_FIELDS as $definition) {
      $options_by_domain[$definition['domain']] ??= [];
    }

    foreach ($this->getCampaignInstitutionRowsByDomain($campaign_id) as $domain => $rows) {
      foreach ($rows as $row) {
        if (isset($options_by_domain[$domain])) {
          $options_by_domain[$domain][$row['subject_id']] = $row['display_name'];
        }
      }
    }

    return $options_by_domain;
  }

  public function getCampaignInstitutionParentOptions(int $campaign_id): array {
    $options = [];
    foreach ($this->getCampaignInstitutionRowsByDomain($campaign_id) as $domain => $rows) {
      foreach ($rows as $row) {
        $options[$row['subject_id']] = sprintf('%s (%s)', $row['display_name'], $domain);
      }
    }

    asort($options);
    return $options;
  }

  public function parseStructuredAffiliationCreateLabels(mixed $value): array {
    $raw = is_string($value) ? $value : '';
    $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
    $labels = [];
    foreach ($parts as $part) {
      $label = trim($part);
      if ($label !== '') {
        $labels[$label] = $label;
      }
    }
    return array_values($labels);
  }

  public function findExactInstitutionMatch(string $normalized_label, array $rows): ?array {
    foreach ($rows as $row) {
      if (($row['normalized_label'] ?? '') === $normalized_label) {
        return $row;
      }
    }
    return NULL;
  }

  public function findLikelyInstitutionNearMatches(string $normalized_label, array $rows): array {
    $matches = [];
    foreach ($rows as $row) {
      $candidate = (string) ($row['normalized_label'] ?? '');
      if ($candidate === '') {
        continue;
      }
      if (str_contains($candidate, $normalized_label) || str_contains($normalized_label, $candidate) || levenshtein($candidate, $normalized_label) <= 2) {
        $matches[] = (string) ($row['display_name'] ?? $candidate);
      }
    }
    return array_values(array_unique($matches));
  }

  public function getStructuredAffiliationCreateLabelsKey(string $field): string {
    return $field . '__create_labels';
  }

  public function getStructuredAffiliationCreateParentKey(string $field): string {
    return $field . '__create_parent_subject_id';
  }

  public function getStructuredAffiliationCreateNoteKey(string $field): string {
    return $field . '__create_note';
  }

  public function getStructuredAffiliationCreateConfirmKey(string $field): string {
    return $field . '__create_confirm';
  }

  public function getStructuredAffiliationCreateRoleKey(string $field): string {
    return $field . '__create_role';
  }

  public function getStructuredAffiliationCreateWhyKey(string $field): string {
    return $field . '__create_why';
  }

  public function getStructuredAffiliationCreatePublicFaceKey(string $field): string {
    return $field . '__create_public_face';
  }

  public function getStructuredAffiliationCreateHiddenFaceKey(string $field): string {
    return $field . '__create_hidden_face';
  }

  public function getStructuredAffiliationCreateIdeologyTagsKey(string $field): string {
    return $field . '__create_ideology_tags';
  }

  public function getStructuredAffiliationCreateMethodTagsKey(string $field): string {
    return $field . '__create_method_tags';
  }

  public function getStructuredAffiliationCreateMembershipStyleKey(string $field): string {
    return $field . '__create_membership_style';
  }

  public function isFactionGenerationField(string $field): bool {
    return $field === 'faction_refs';
  }

  public function buildFactionGenerationRequestFromForm(FormStateInterface|CharacterCreationInput $form_state, string $field, string $label, int $character_id = 0): array {
    return [
      'label' => $label,
      'domain' => self::STRUCTURED_AFFILIATION_FIELDS[$field]['domain'] ?? 'faction',
      'parentSubjectId' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreateParentKey($field), '')),
      'provenanceNote' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreateNoteKey($field), '')),
      'roleInStory' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreateRoleKey($field), '')),
      'whyExistingFactionIsInsufficient' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreateWhyKey($field), '')),
      'publicFace' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreatePublicFaceKey($field), '')),
      'hiddenFace' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreateHiddenFaceKey($field), '')),
      'ideologyTags' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreateIdeologyTagsKey($field), '')),
      'methodTags' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreateMethodTagsKey($field), '')),
      'membershipStyle' => trim((string) $form_state->getValue($this->getStructuredAffiliationCreateMembershipStyleKey($field), '')),
      'requestSource' => 'character_creation_step6',
      'requesterCharacterId' => $character_id,
    ];
  }

  public function clearStaleOptionInput(FormStateInterface|CharacterCreationInput $form_state, string $input_key, array $options): void {
    if ($form_state instanceof CharacterCreationInput) {
      return;
    }
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

  public function buildSelectionDetailMarkup(string $title, string $description = '', array $tags = [], array $facts = []): string {
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

  public function buildOptionCardData(string $description = '', array $tags = [], array $facts = []): array {
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

  public function attachOptionCardSettings(array &$element, string $group_name, array $options, string $selection_type, array $disabled_values = []): void {
    if (empty($options)) {
      return;
    }

    $element['#attached']['drupalSettings']['characterOptionCards'][$group_name] = [
      'selectionType' => $selection_type,
      'options' => $options,
      'disabledValues' => array_values(array_unique(array_filter(array_map(static fn($value): string => trim((string) $value), $disabled_values), static fn(string $value): bool => $value !== ''))),
    ];
  }

  public function extractSpellFacts(array $spell): array {
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

  public function buildEquipmentOptionCardData(array $item, string $category): array {
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

  public function buildEquipmentDescription(array $item, string $category): string {
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

  public function extractTemplateItemDescription(array $schema_data): string {
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

  public function buildFirstWorldMagicSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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
  }

  public function buildOtherworldlyMagicSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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
  }

  public function buildGnomeObsessionSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildNaturalPerformerSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildGeneralTrainingSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

    $selected_general_feat = trim((string) ($character_data['general_feat'] ?? ''));
    $feat_options = [];
    $feat_cards = [];
    foreach ($this->getCreationEligibleGeneralFeats($character_data) as $feat) {
      $duplicate_tags = [];
      if ($selected_general_feat !== '' && $selected_general_feat === (string) ($feat['id'] ?? '') && $selected_general_feat !== $selected_bonus_feat) {
        $duplicate_tags[] = (string) $this->t('Already selected as general feat');
      }
      $feat_options[$feat['id']] = $feat['name'];
      $feat_cards[$feat['id']] = $this->buildOptionCardData(
        $this->resolveFeatDisplayDescription($feat),
        array_values(array_merge($feat['traits'] ?? [], $duplicate_tags)),
        [
          (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
        ],
      );
    }

    if (!array_key_exists($selected_bonus_feat, $feat_options)) {
      $selected_bonus_feat = '';
    }
    $disabled_bonus_general_feat_ids = [];
    if ($selected_general_feat !== '' && $selected_general_feat !== $selected_bonus_feat) {
      $disabled_bonus_general_feat_ids[] = $selected_general_feat;
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
      'single',
      $disabled_bonus_general_feat_ids
    );
  }

  public function buildElfAtavismSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildMultitalentedSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildMixedHeritageAdaptabilitySelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildOrcAtavismSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildDraconicTiesSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildNaturalSkillSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildVengefulHatredSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildAncestralLongevitySelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildUnconventionalWeaponrySelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
    $stored_selection = $character_data['feat_selections']['unconventional-weaponry'] ?? [];
    $selected_weapon_id = (string) $form_state->getValue(
      ['feat_selections', 'unconventional-weaponry', 'selected_weapon_id'],
      $stored_selection['selected_weapon_id'] ?? ''
    );
    $weapon_options = CharacterRulesCatalog::UNCONVENTIONAL_WEAPON_OPTIONS;

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

  public function buildNaturalAmbitionSelectionSection(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $selected_class, array $class_feats): void {
    $stored_selection = $character_data['feat_selections']['natural-ambition'] ?? [];
    $selected_class_feat = trim((string) ($character_data['class_feat'] ?? ''));
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
      $duplicate_tags = [];
      if ($selected_class_feat !== '' && $selected_class_feat === (string) ($feat['id'] ?? '') && $selected_class_feat !== $selected_bonus_feat) {
        $duplicate_tags[] = (string) $this->t('Already selected as class feat');
      }
      $feat_options[$feat['id']] = $feat['name'];
      $feat_cards[$feat['id']] = $this->buildOptionCardData(
        $feat['benefit'] ?? '',
        array_values(array_merge($feat['traits'] ?? [], $duplicate_tags)),
        [
          (string) $this->t('Prerequisites') => $feat['prerequisites'] ?? '',
        ],
      );
    }
    $disabled_bonus_feat_ids = [];
    if ($selected_class_feat !== '' && $selected_class_feat !== $selected_bonus_feat) {
      $disabled_bonus_feat_ids[] = $selected_class_feat;
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
      'single',
      $disabled_bonus_feat_ids
    );
  }

  public function buildEldritchTricksterSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildMastermindSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildSpecialtyCraftingSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildVirtuosicPerformerSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildCannyAcumenSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildAdoptedAncestrySelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildDomainInitiateSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $selected_deity): void {
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

  public function buildWeaponProficiencySelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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
      $weapon_options = CharacterRulesCatalog::ADVANCED_WEAPON_OPTIONS;
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

  public function buildMonsterHunterSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data): void {
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

  public function buildAnimalCompanionSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $source_feat_id): void {
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

  public function buildFamiliarSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $source_feat_id): void {
    if (!isset($container['feat_selections']) || !is_array($container['feat_selections'])) {
      $container['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }

    $stored_selection = $character_data['feat_selections'][$source_feat_id] ?? [];
    $selected_type = strtolower(trim((string) $form_state->getValue(
      ['feat_selections', $source_feat_id, 'selected_familiar_type'],
      $stored_selection['selected_familiar_type'] ?? $stored_selection['familiar_type'] ?? 'standard'
    )));
    $selected_abilities = self::normalizeList($form_state->getValue(
      ['feat_selections', $source_feat_id, 'selected_familiar_abilities'],
      $stored_selection['selected_familiar_abilities'] ?? []
    ));
    $familiar_name = (string) $form_state->getValue(
      ['feat_selections', $source_feat_id, 'name'],
      $stored_selection['name'] ?? ''
    );

    $container['feat_selections'][$source_feat_id] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['feat-selection-section']],
    ];
    $container['feat_selections'][$source_feat_id]['intro'] = [
      '#markup' => '<div class="spell-help"><strong>'
      . $this->t('Familiar')
      . '</strong><br>'
      . $this->t('Choose the familiar form and starting familiar abilities that this feat or class feature grants.')
      . '</div>',
    ];

    $type_options = ['standard' => (string) $this->t('Standard Familiar')];
    $type_cards = [
      'standard' => $this->buildOptionCardData(
        (string) $this->t('A general magical familiar form that uses the standard familiar rules.'),
        [],
        [
          (string) $this->t('Speed') => FamiliarService::DEFAULT_SPEED . ' ft.',
          (string) $this->t('Abilities') => (string) $this->getFamiliarStartingAbilityCount($character_data, $source_feat_id),
        ]
      ),
    ];
    foreach (FamiliarService::FAMILIAR_TYPES as $type_id => $type) {
      $type_options[$type_id] = (string) ($type['name'] ?? ucfirst((string) $type_id));
      $facts = [
        (string) $this->t('Speed') => FamiliarService::DEFAULT_SPEED . ' ft.',
      ];
      if (!empty($type['burrow_speed'])) {
        $facts[(string) $this->t('Special')] = (string) $this->t('Burrow-capable form');
      }
      $type_cards[$type_id] = $this->buildOptionCardData(
        (string) $this->t('A @type familiar form using the standard familiar rules.', ['@type' => $type_options[$type_id]]),
        !empty($type['burrow_speed']) ? [(string) $this->t('Burrow form')] : [],
        $facts
      );
    }

    if (!array_key_exists($selected_type, $type_options)) {
      $selected_type = 'standard';
    }

    $container['feat_selections'][$source_feat_id]['selected_familiar_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose familiar form'),
      '#options' => $type_options,
      '#default_value' => $selected_type,
      '#required' => FALSE,
      '#description' => $this->t('This determines the familiar’s base creature form and whether wing-based abilities are available.'),
      '#ajax' => [
        'callback' => '::updateClassOptions',
        'wrapper' => 'class-dynamic-wrapper',
        'event' => 'change',
      ],
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections'][$source_feat_id],
      'selected_familiar_type',
      $type_cards,
      'single'
    );

    $ability_options = [];
    $ability_cards = [];
    $has_wings = $this->familiarTypeHasWings($selected_type);
    foreach (FamiliarService::ABILITY_CATALOG as $ability_id => $ability) {
      $prerequisites = is_array($ability['prerequisites'] ?? NULL) ? $ability['prerequisites'] : [];
      if (($prerequisites['has_wings'] ?? FALSE) && !$has_wings) {
        continue;
      }
      $ability_options[$ability_id] = $ability['name'];
      $tags = [];
      if (($prerequisites['has_wings'] ?? FALSE) === TRUE) {
        $tags[] = (string) $this->t('Requires wings');
      }
      $ability_cards[$ability_id] = $this->buildOptionCardData(
        (string) ($ability['description'] ?? ''),
        $tags
      );
    }

    $container['feat_selections'][$source_feat_id]['selected_familiar_abilities'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Choose starting familiar abilities'),
      '#options' => $ability_options,
      '#default_value' => array_values(array_filter($selected_abilities, static fn($value): bool => is_string($value) && $value !== '')),
      '#description' => $this->t('Select exactly @count familiar abilities for this familiar.', [
        '@count' => $this->getFamiliarStartingAbilityCount($character_data, $source_feat_id),
      ]),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections'][$source_feat_id],
      'selected_familiar_abilities',
      $ability_cards,
      'multiple'
    );

    $container['feat_selections'][$source_feat_id]['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Familiar name'),
      '#default_value' => $familiar_name,
      '#required' => FALSE,
      '#maxlength' => 80,
      '#description' => $this->t('Optional. Leave blank to use the familiar form as the default display name.'),
    ];
  }

  public function buildStaffNexusSelectionSection(array &$container, FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $tradition): void {
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

    if (empty($options['cantrips']) || empty($options['spells'])) {
      $container['feat_selections']['staff-nexus']['pending'] = [
        '#markup' => '<div class="spell-help">'
        . $this->t('Select your cantrips and 1st-rank spellbook spells above first. Staff Nexus choices will unlock as soon as those spell selections are present.')
        . '</div>',
      ];
      return;
    }

    $selected_cantrip_cards = [];
    foreach ($this->characterManager->getSpellsByTradition($tradition, 0) as $spell) {
      $spell_id = (string) ($spell['id'] ?? '');
      if (!array_key_exists($spell_id, $options['cantrips'])) {
        continue;
      }
      $tags = ['Cantrip', ucfirst($tradition)];
      if (!empty($spell['school'])) {
        $tags[] = ucfirst((string) $spell['school']);
      }
      $selected_cantrip_cards[$spell_id] = $this->buildOptionCardData(
        $spell['description'] ?? '',
        $tags,
        $this->extractSpellFacts($spell)
      );
    }
    $selected_spell_cards = [];
    foreach ($this->characterManager->getSpellsByTradition($tradition, 1) as $spell) {
      $spell_id = (string) ($spell['id'] ?? '');
      if (!array_key_exists($spell_id, $options['spells'])) {
        continue;
      }
      $tags = ['1st-rank spell', ucfirst($tradition)];
      if (!empty($spell['school'])) {
        $tags[] = ucfirst((string) $spell['school']);
      }
      $selected_spell_cards[$spell_id] = $this->buildOptionCardData(
        $spell['description'] ?? '',
        $tags,
        $this->extractSpellFacts($spell)
      );
    }

    $container['feat_selections']['staff-nexus']['selected_cantrip'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose the staff cantrip'),
      '#options' => $options['cantrips'],
      '#default_value' => $selected_cantrip,
      '#required' => FALSE,
      '#description' => $this->t('The makeshift staff contains one cantrip from your selected arcane cantrips.'),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections']['staff-nexus'],
      'selected_cantrip',
      $selected_cantrip_cards,
      'single'
    );

    $container['feat_selections']['staff-nexus']['selected_spell'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose the staff 1st-rank spell'),
      '#options' => $options['spells'],
      '#default_value' => $selected_spell,
      '#required' => FALSE,
      '#description' => $this->t('The makeshift staff contains one selected 1st-rank spell from your spellbook.'),
    ];
    $this->attachOptionCardSettings(
      $container['feat_selections']['staff-nexus'],
      'selected_spell',
      $selected_spell_cards,
      'single'
    );
  }

  public function validateFirstWorldMagicSelection(CharacterCreationInput $form_state): void {
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

  public function validateOtherworldlyMagicSelection(CharacterCreationInput $form_state): void {
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

  public function validateNaturalAmbitionSelection(CharacterCreationInput $form_state, string $selected_class): void {
    $selected_bonus_feat = trim((string) $form_state->getValue(['feat_selections', 'natural-ambition', 'bonus_class_feat'], ''));
    $selected_class_feat = trim((string) $form_state->getValue('class_feat', ''));
    if ($selected_bonus_feat === '') {
      $form_state->setErrorByName(
        'feat_selections][natural-ambition][bonus_class_feat',
        $this->t('Choose a bonus class feat for Natural Ambition.')
      );
      return;
    }
    if ($selected_bonus_feat === $selected_class_feat) {
      $form_state->setErrorByName(
        'feat_selections][natural-ambition][bonus_class_feat',
        $this->t('Natural Ambition cannot grant the same class feat you already selected.')
      );
      return;
    }
    $valid_feat_ids = array_map(
      static fn(array $feat): string => (string) ($feat['id'] ?? ''),
      $this->getCreationEligibleClassFeats($selected_class, $form_state->characterData())
    );
    if (!in_array($selected_bonus_feat, $valid_feat_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][natural-ambition][bonus_class_feat',
        $this->t('Choose a valid class feat for Natural Ambition.')
      );
    }
  }

  public function validateGeneralTrainingSelection(CharacterCreationInput $form_state): void {
    $selected_bonus_feat = trim((string) $form_state->getValue(['feat_selections', 'general-training', 'bonus_general_feat'], ''));
    $selected_general_feat = trim((string) $form_state->getValue('ancestry_feat', '')) === 'general-training'
      ? trim((string) $form_state->getValue('general_feat', ''))
      : '';
    if ($selected_bonus_feat === '') {
      $form_state->setErrorByName(
        'feat_selections][general-training][bonus_general_feat',
        $this->t('Choose a bonus general feat for General Training.')
      );
      return;
    }
    if ($selected_general_feat !== '' && $selected_bonus_feat === $selected_general_feat) {
      $form_state->setErrorByName(
        'feat_selections][general-training][bonus_general_feat',
        $this->t('General Training cannot grant the same general feat you already selected.')
      );
      return;
    }
    $valid_feat_ids = array_map(
      static fn(array $feat): string => (string) ($feat['id'] ?? ''),
      $this->getCreationEligibleGeneralFeats($form_state->characterData())
    );
    if (!in_array($selected_bonus_feat, $valid_feat_ids, TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][general-training][bonus_general_feat',
        $this->t('Choose a valid general feat for General Training.')
      );
    }
  }

  public function validateElfAtavismSelection(CharacterCreationInput $form_state): void {
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

  public function validateMultitalentedSelection(CharacterCreationInput $form_state): void {
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

  public function validateMixedHeritageAdaptabilitySelection(CharacterCreationInput $form_state): void {
    $selected_skill = trim((string) $form_state->getValue(['feat_selections', 'mixed-heritage-adaptability', 'selected_skill'], ''));
    if ($selected_skill === '' || !array_key_exists($selected_skill, $this->getSkillTrainingOptions())) {
      $form_state->setErrorByName(
        'feat_selections][mixed-heritage-adaptability][selected_skill',
        $this->t('Choose a valid skill for Mixed Heritage Adaptability.')
      );
    }
  }

  public function validateOrcAtavismSelection(CharacterCreationInput $form_state): void {
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

  public function validateDraconicTiesSelection(CharacterCreationInput $form_state): void {
    $selected_damage_type = trim((string) $form_state->getValue(['feat_selections', 'draconic-ties', 'damage_type'], ''));
    if ($selected_damage_type === '' || !array_key_exists($selected_damage_type, $this->getDraconicDamageTypeOptions())) {
      $form_state->setErrorByName(
        'feat_selections][draconic-ties][damage_type',
        $this->t('Choose a draconic damage type for Draconic Ties.')
      );
    }
  }

  public function validateNaturalSkillSelection(CharacterCreationInput $form_state): void {
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

  public function validateUnconventionalWeaponrySelection(CharacterCreationInput $form_state): void {
    $selected_weapon_id = trim((string) $form_state->getValue(['feat_selections', 'unconventional-weaponry', 'selected_weapon_id'], ''));
    $weapon_options = CharacterRulesCatalog::UNCONVENTIONAL_WEAPON_OPTIONS;
    if ($selected_weapon_id === '' || !array_key_exists($selected_weapon_id, $weapon_options)) {
      $form_state->setErrorByName(
        'feat_selections][unconventional-weaponry][selected_weapon_id',
        $this->t('Choose a valid uncommon weapon for Unconventional Weaponry.')
      );
    }
  }

  public function validateVengefulHatredSelection(CharacterCreationInput $form_state): void {
    $selected_target_type = trim((string) $form_state->getValue(['feat_selections', 'vengeful-hatred', 'target_type'], ''));
    if ($selected_target_type === '' || !in_array($selected_target_type, ['drow', 'duergar', 'giant', 'orc'], TRUE)) {
      $form_state->setErrorByName(
        'feat_selections][vengeful-hatred][target_type',
        $this->t('Choose a hated foe for Vengeful Hatred.')
      );
    }
  }

  public function validateAncestralLongevitySelection(CharacterCreationInput $form_state): void {
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

  public function validateGnomeObsessionSelection(CharacterCreationInput $form_state): void {
    $selected_lore = $this->normalizeLoreSkillName((string) $form_state->getValue(['feat_selections', 'gnome-obsession', 'selected_lore'], ''));
    if ($selected_lore === '') {
      $form_state->setErrorByName(
        'feat_selections][gnome-obsession][selected_lore',
        $this->t('Choose a Lore skill for Gnome Obsession.')
      );
    }
  }

  public function validateNaturalPerformerSelection(CharacterCreationInput $form_state): void {
    $selected_specialty = trim((string) $form_state->getValue(['feat_selections', 'natural-performer', 'specialty'], ''));
    if ($selected_specialty === '' || !array_key_exists($selected_specialty, $this->getNaturalPerformerOptions())) {
      $form_state->setErrorByName(
        'feat_selections][natural-performer][specialty',
        $this->t('Choose a performance specialty for Natural Performer.')
      );
    }
  }

  public function validateSpecialtyCraftingSelection(CharacterCreationInput $form_state): void {
    $selected_specialty = trim((string) $form_state->getValue(['feat_selections', 'specialty-crafting', 'specialty'], ''));
    if ($selected_specialty === '' || !array_key_exists($selected_specialty, $this->getSpecialtyCraftingOptions())) {
      $form_state->setErrorByName(
        'feat_selections][specialty-crafting][specialty',
        $this->t('Choose a crafting specialty for Specialty Crafting.')
      );
    }
  }

  public function validateVirtuosicPerformerSelection(CharacterCreationInput $form_state): void {
    $selected_specialty = trim((string) $form_state->getValue(['feat_selections', 'virtuosic-performer', 'specialty'], ''));
    if ($selected_specialty === '' || !array_key_exists($selected_specialty, $this->getPerformanceSpecialtyOptions())) {
      $form_state->setErrorByName(
        'feat_selections][virtuosic-performer][specialty',
        $this->t('Choose a performance specialty for Virtuosic Performer.')
      );
    }
  }

  public function validateCannyAcumenSelection(CharacterCreationInput $form_state): void {
    $selected_proficiency = trim((string) $form_state->getValue(['feat_selections', 'canny-acumen', 'selected_proficiency'], ''));
    if ($selected_proficiency === '' || !array_key_exists($selected_proficiency, $this->getCannyAcumenOptions())) {
      $form_state->setErrorByName(
        'feat_selections][canny-acumen][selected_proficiency',
        $this->t('Choose Perception or a save for Canny Acumen.')
      );
    }
  }

  public function validateAdoptedAncestrySelection(CharacterCreationInput $form_state, array $character_data): void {
    $selected_ancestry = trim((string) $form_state->getValue(['feat_selections', 'adopted-ancestry', 'selected_ancestry'], ''));
    $options = $this->getAdoptedAncestryOptions($character_data);
    if ($selected_ancestry === '' || !array_key_exists($selected_ancestry, $options)) {
      $form_state->setErrorByName(
        'feat_selections][adopted-ancestry][selected_ancestry',
        $this->t('Choose a valid adopted ancestry for Adopted Ancestry.')
      );
    }
  }

  public function validateStaffNexusSelection(CharacterCreationInput $form_state, string $selected_class): void {
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

  public function validateMonsterHunterSelection(CharacterCreationInput $form_state): void {
    $selected_monster_type = trim((string) $form_state->getValue(['feat_selections', 'monster-hunter', 'selected_monster_type'], ''));
    if ($selected_monster_type === '' || !array_key_exists($selected_monster_type, $this->getMonsterHunterOptions())) {
      $form_state->setErrorByName(
        'feat_selections][monster-hunter][selected_monster_type',
        $this->t('Choose a creature type for Monster Hunter.')
      );
    }
  }

  public function validateEldritchTricksterSelection(CharacterCreationInput $form_state, string $selected_class): void {
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

  public function validateMastermindSelection(CharacterCreationInput $form_state, string $selected_class): void {
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

  public function validateAnimalCompanionSelection(CharacterCreationInput $form_state, string $source_feat_id): void {
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

  public function validateFamiliarSelection(CharacterCreationInput $form_state, string $source_feat_id, string $selected_class): void {
    $selected_type = strtolower(trim((string) $form_state->getValue(
      ['feat_selections', $source_feat_id, 'selected_familiar_type'],
      'standard'
    )));
    if ($selected_type !== 'standard' && !isset(FamiliarService::FAMILIAR_TYPES[$selected_type])) {
      $form_state->setErrorByName(
        'feat_selections][' . $source_feat_id . '][selected_familiar_type',
        $this->t('Choose a valid familiar form.')
      );
    }

    $selected_abilities = self::normalizeList($form_state->getValue(
      ['feat_selections', $source_feat_id, 'selected_familiar_abilities'],
      []
    ));
    $expected_count = $this->getFamiliarStartingAbilityCount(['class' => $selected_class], $source_feat_id);
    if (count($selected_abilities) !== $expected_count
      || count(array_unique($selected_abilities, SORT_REGULAR)) !== $expected_count) {
      $form_state->setErrorByName(
        'feat_selections][' . $source_feat_id . '][selected_familiar_abilities',
        $this->t('Choose exactly @count distinct familiar abilities.', ['@count' => $expected_count])
      );
      return;
    }

    foreach ($selected_abilities as $ability_id) {
      if (!isset(FamiliarService::ABILITY_CATALOG[$ability_id])) {
        $form_state->setErrorByName(
          'feat_selections][' . $source_feat_id . '][selected_familiar_abilities',
          $this->t('Choose only valid familiar abilities.')
        );
        return;
      }
      if ($ability_id === 'flier' && !$this->familiarTypeHasWings($selected_type)) {
        $form_state->setErrorByName(
          'feat_selections][' . $source_feat_id . '][selected_familiar_abilities',
          $this->t('The selected familiar form does not have wings, so Flier is not available.')
        );
        return;
      }
    }
  }

  public function validateArmorProficiencySelection(CharacterCreationInput $form_state, array $character_data): void {
    if ($this->resolveArmorProficiencyTarget($character_data) === NULL) {
      $form_state->setErrorByName(
        'general_feat',
        $this->t('Armor Proficiency does not grant an additional armor tier for your current class.')
      );
    }
  }

  public function validateWeaponProficiencySelection(CharacterCreationInput $form_state, array $character_data): void {
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
    $weapon_options = CharacterRulesCatalog::ADVANCED_WEAPON_OPTIONS;
    if ($selected_weapon_id === '' || !array_key_exists($selected_weapon_id, $weapon_options)) {
      $form_state->setErrorByName(
        'feat_selections][weapon-proficiency][selected_weapon_id',
        $this->t('Choose a valid advanced weapon for Weapon Proficiency.')
      );
    }
  }

  public function validateDomainInitiateSelection(CharacterCreationInput $form_state, string $selected_deity): void {
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

  public function getSpecialtyCraftingOptions(): array {
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

  public function getPerformanceSpecialtyOptions(): array {
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

  public function getCannyAcumenOptions(): array {
    return [
      'perception' => $this->t('Perception'),
      'fortitude' => $this->t('Fortitude'),
      'reflex' => $this->t('Reflex'),
      'will' => $this->t('Will'),
    ];
  }

  public function getMonsterHunterOptions(): array {
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

  public function resolveRogueKeyAbilityOptions(string $selected_class_feat): array {
    return match ($selected_class_feat) {
      'ruffian' => ['dexterity', 'strength'],
      'scoundrel' => ['dexterity', 'charisma'],
      'eldritch-trickster-racket', 'mastermind-racket' => ['dexterity', 'intelligence'],
      default => ['dexterity'],
    };
  }

  public function getEldritchTricksterDedicationOptions(): array {
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

  public function getMastermindKnowledgeSkillOptions(): array {
    return [
      'arcana' => $this->t('Arcana'),
      'nature' => $this->t('Nature'),
      'occultism' => $this->t('Occultism'),
      'religion' => $this->t('Religion'),
    ];
  }

  public function resolveArmorProficiencyTarget(array $character_data): ?string {
    $selected_class = trim((string) ($character_data['class'] ?? ''));
    if ($selected_class === '' || !isset(CharacterRulesCatalog::CLASSES[$selected_class])) {
      return NULL;
    }

    $armor_proficiencies = CharacterRulesCatalog::CLASSES[$selected_class]['armor_proficiency'] ?? [];
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

  public function getNaturalPerformerOptions(): array {
    return [
      'acting' => $this->t('Acting'),
      'dance' => $this->t('Dancing'),
      'singing' => $this->t('Singing'),
    ];
  }

  public function getSkillTrainingOptions(): array {
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

  public function getElfAtavismFeatOptions(): array {
    return array_values(array_filter(
      $this->getCanonicalAncestryFeats('Elf'),
      static function (array $feat): bool {
        return (int) ($feat['level'] ?? 0) <= 1;
      }
    ));
  }

  public function getOrcAtavismFeatOptions(): array {
    return array_values(array_filter(
      $this->getCanonicalAncestryFeats('Orc'),
      static function (array $feat): bool {
        return (int) ($feat['level'] ?? 0) <= 1;
      }
    ));
  }

  public function getDraconicDamageTypeOptions(): array {
    return [
      'acid' => 'Acid',
      'cold' => 'Cold',
      'electricity' => 'Electricity',
      'fire' => 'Fire',
      'poison' => 'Poison',
    ];
  }

  public function buildAdaptedCantripSelectionSection(array &$form, FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $native_tradition): void {
    $available_traditions = SpellCatalogService::TRADITIONS;
    $stored_selection = $character_data['feat_selections']['adapted-cantrip'] ?? [];
    $selected_tradition = (string) $form_state->getValue(
      ['feat_selections', 'adapted-cantrip', 'selected_tradition'],
      $stored_selection['selected_tradition'] ?? ''
    );
    if (!in_array($selected_tradition, $available_traditions, TRUE)) {
      $selected_tradition = '';
    }

    if (!isset($form['class_dynamic']['feat_selections']) || !is_array($form['class_dynamic']['feat_selections'])) {
      $form['class_dynamic']['feat_selections'] = [
        '#type' => 'container',
        '#tree' => TRUE,
      ];
    }
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
  }

  public function validateAdaptedCantripSelection(CharacterCreationInput $form_state, array $character_data): void {
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

  public function getCanonicalAncestryFeats(string $ancestry_name): array {
    return $this->featLibrary->getAncestryFeats($ancestry_name);
  }

  public function getCreationEligibleAncestryFeats(string $ancestry_name, array $character_data): array {
    return array_values(array_filter(
      $this->getCanonicalAncestryFeats($ancestry_name),
      fn(array $feat): bool => $this->isCreationFeatEligible($feat, $character_data)
    ));
  }

  public function getCanonicalClassFeats(string $class_name): array {
    $class_name = trim($class_name);
    return $class_name === '' ? [] : $this->featLibrary->getClassFeats($class_name);
  }

  public function getCreationEligibleClassFeats(string $class_name, array $character_data): array {
    return array_values(array_filter(
      $this->getCanonicalClassFeats($class_name),
      fn(array $feat): bool => $this->isCreationFeatEligible($feat, $character_data)
    ));
  }

  public function getCanonicalGeneralFeats(): array {
    return $this->featLibrary->getGeneralFeats();
  }

  public function getCreationEligibleGeneralFeats(array $character_data): array {
    return array_values(array_filter(
      $this->getCanonicalGeneralFeats(),
      fn(array $feat): bool => $this->isCreationFeatEligible($feat, $character_data)
    ));
  }

  public function isCreationFeatEligible(array $feat, array $character_data): bool {
    if ((int) ($feat['level'] ?? 1) !== 1) {
      return FALSE;
    }

    $prerequisites = trim((string) ($feat['prerequisites'] ?? ''));
    if ($prerequisites === '' || strtolower($prerequisites) === 'none') {
      return TRUE;
    }

    foreach (preg_split('/\s*,\s*/', $prerequisites) ?: [] as $clause) {
      $clause = trim($clause);
      if ($clause === '') {
        continue;
      }
      if (!$this->doesCreationPrerequisiteClausePass($clause, $character_data)) {
        return FALSE;
      }
    }

    return TRUE;
  }

  public function doesCreationPrerequisiteClausePass(string $clause, array $character_data): bool {
    $alternatives = preg_split('/\s+or\s+/i', $clause) ?: [$clause];
    foreach ($alternatives as $alternative) {
      if ($this->doesCreationPrerequisiteAtomPass(trim($alternative), $character_data)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function doesCreationPrerequisiteAtomPass(string $atom, array $character_data): bool {
    $normalized = strtolower(trim($atom));
    if ($normalized === '' || $normalized === 'none') {
      return TRUE;
    }

    $class_id = strtolower(trim((string) ($character_data['class'] ?? '')));
    $subclass = strtolower(trim((string) ($character_data['subclass'] ?? '')));
    $arcane_thesis = strtolower(trim((string) ($character_data['arcane_thesis'] ?? '')));
    $class_feat = strtolower(trim((string) ($character_data['class_feat'] ?? '')));
    $ancestry_feat = strtolower(trim((string) ($character_data['ancestry_feat'] ?? '')));
    $general_feat = strtolower(trim((string) ($character_data['general_feat'] ?? '')));
    $trained_skills = array_map(
      static fn(string $skill): string => strtolower(trim($skill)),
      is_array($character_data['trained_skills'] ?? NULL) ? $character_data['trained_skills'] : []
    );
    $background_skill = strtolower(trim((string) ($character_data['background_skill_training'] ?? '')));
    if ($background_skill !== '') {
      $trained_skills[] = $background_skill;
    }
    $trained_skills = array_values(array_unique(array_filter($trained_skills)));

    if ($normalized === 'spellcasting class feature') {
      return $class_id !== '' && $this->characterManager->resolveClassTradition($class_id, $character_data) !== NULL;
    }

    if (preg_match('/^trained in (.+)$/i', $atom, $matches) === 1) {
      $skill = strtolower(trim((string) $matches[1]));
      return in_array($skill, $trained_skills, TRUE);
    }

    if (preg_match('/^([a-z ]+)\s*\+(\d+)\s*modifier$/i', $atom, $matches) === 1) {
      $ability_key = $this->abilityScoreTracker->normalizeAbilityKey($matches[1]);
      if ($ability_key === NULL) {
        return FALSE;
      }
      $score = (int) (
        $character_data[$ability_key]
        ?? ($character_data['abilities'][$ability_key] ?? $character_data['abilities'][substr($ability_key, 0, 3)] ?? 10)
      );
      return (int) floor(($score - 10) / 2) >= (int) $matches[2];
    }

    if ($normalized === 'universalist wizard') {
      return $class_id === 'wizard' && $subclass === 'universalist';
    }

    if (str_ends_with($normalized, ' order')) {
      $order = strtolower(trim(substr($normalized, 0, -strlen(' order'))));
      return $class_id === 'druid' && $subclass === $order;
    }

    if (str_ends_with($normalized, ' bloodline')) {
      $bloodline = strtolower(trim(substr($normalized, 0, -strlen(' bloodline'))));
      return $class_id === 'sorcerer' && $subclass === $bloodline;
    }

    if (str_starts_with($normalized, 'research field:')) {
      $field = strtolower(trim(substr($normalized, strlen('research field:'))));
      return $class_id === 'alchemist' && $subclass === $field;
    }

    if (str_starts_with($normalized, 'access to ')) {
      $target = strtolower(trim(substr($normalized, strlen('access to '))));
      return str_contains($subclass, $target) || str_contains($class_feat, $target) || str_contains($general_feat, $target);
    }

    if ($normalized === 'a familiar' || $normalized === 'familiar') {
      return in_array($class_feat, ['familiar', 'familiar-druid', 'familiar-sorcerer', 'alchemical-familiar', 'leshy-familiar-druid'], TRUE)
        || ($class_id === 'wizard' && $arcane_thesis === 'improved-familiar-attunement')
        || $class_id === 'witch';
    }

    $owned_feat_ids = array_filter([
      $ancestry_feat,
      $class_feat,
      $general_feat,
    ]);
    $normalized_atom_id = strtolower(str_replace([' ', '_'], ['-', '-'], $normalized));
    return in_array($normalized_atom_id, $owned_feat_ids, TRUE);
  }

  public function resolveFeatDisplayDescription(array $feat): string {
    return (string) ($feat['description'] ?? $feat['benefit'] ?? $feat['description_snippet'] ?? '');
  }

  public function resolveAnimalCompanionSelectionSource(FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $selected_class): ?string {
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

  public function resolveFamiliarSelectionSource(FormStateInterface|CharacterCreationInput $form_state, array $character_data, string $selected_class): ?string {
    $selected_class_feat = trim((string) $form_state->getValue('class_feat', $character_data['class_feat'] ?? ''));
    if (in_array($selected_class_feat, $this->getFamiliarSelectionSourceIds(), TRUE)) {
      return $selected_class_feat;
    }

    $selected_bonus_feat = trim((string) $form_state->getValue(
      ['feat_selections', 'natural-ambition', 'bonus_class_feat'],
      $character_data['feat_selections']['natural-ambition']['bonus_class_feat'] ?? ''
    ));
    if (in_array($selected_bonus_feat, $this->getFamiliarSelectionSourceIds(), TRUE)) {
      return $selected_bonus_feat;
    }

    $selected_subclass = strtolower(trim((string) $form_state->getValue('subclass', $character_data['subclass'] ?? '')));
    if ($selected_class === 'druid' && $selected_subclass === 'leaf') {
      return 'leshy-familiar-druid';
    }

    $selected_arcane_thesis = strtolower(trim((string) $form_state->getValue('arcane_thesis', $character_data['arcane_thesis'] ?? '')));
    if ($selected_class === 'wizard' && $selected_arcane_thesis === 'improved-familiar-attunement') {
      return 'improved-familiar-attunement';
    }

    if ($selected_class === 'witch') {
      return 'familiar-witch-class';
    }

    return NULL;
  }

  public function getFamiliarSelectionSourceIds(): array {
    return [
      'familiar',
      'familiar-druid',
      'familiar-sorcerer',
      'alchemical-familiar',
      'leshy-familiar-druid',
      'improved-familiar-attunement',
      'familiar-witch-class',
    ];
  }

  public function getFamiliarStartingAbilityCount(array $character_data, string $source_feat_id): int {
    $count = FamiliarService::BASE_ABILITY_COUNT;
    if (($character_data['class'] ?? '') === 'wizard' && $source_feat_id === 'improved-familiar-attunement') {
      $count += 1;
    }
    return $count;
  }

  public function familiarTypeHasWings(string $familiar_type): bool {
    return in_array($familiar_type, ['bat', 'owl', 'raven'], TRUE);
  }

  /**
   * Inspect the complete character with shared rules, without Form API execution.
   */
  public function validate(array $character_data, ?int $campaign_id = NULL, bool $include_option_details = FALSE): array {
    foreach (['ancestry_boosts', 'background_boosts', 'free_boosts', 'trained_skills'] as $field) {
      $character_data[$field] = self::normalizeList($character_data[$field] ?? []);
    }
    $character_data['cantrips'] = array_key_exists('cantrips', $character_data)
      ? self::normalizeList($character_data['cantrips'])
      : CharacterManager::getSelectedCantripIds($character_data);
    $character_data['spells_first'] = array_key_exists('spells_first', $character_data)
      ? self::normalizeList($character_data['spells_first'])
      : CharacterManager::getSelectedFirstLevelSpellIds($character_data);
    $equipment_ids = $character_data['gm_equipment_ids']
      ?? array_column($character_data['inventory']['carried'] ?? [], 'id');
    foreach ($this->getEquipmentCatalog() as $group => $items) {
      if (!array_key_exists($group, $character_data)) {
        $character_data[$group] = array_values(array_intersect($equipment_ids, array_column($items, 'id')));
      }
    }
    $report = ['valid' => TRUE, 'ready' => TRUE, 'errors' => [], 'warnings' => [], 'steps' => []];
    for ($step = 1; $step <= 8; $step++) {
      $input = (new CharacterCreationInput($character_data))->set('step', $step)->set('campaign_id', $campaign_id ?? 0);
      $definition = [];
      $method = 'buildStep' . $step . 'Fields';
      $this->$method($definition, $input, $character_data, $this->schemaLoader->getStepFields($step));
      $choices = [];
      $cards = [];
      $this->validateStepRules($step, $input);
      $this->validateFieldDefinitions($definition, $input, [], FALSE, $choices, $cards);
      $issues = [];
      foreach ($input->getErrors() as $field => $message) {
        $value = $input->getValue(explode('][', $field));
        if (in_array($field, ['ancestry_boosts', 'background_boosts', 'free_boosts'], TRUE)
          || !empty($choices[$field]['multiple'])) {
          $value = self::normalizeList($value);
        }
        $missing = $step !== 7 && ($value === NULL || $value === '' || $value === []);
        $issue = [
          'code' => $missing ? 'character_choice_required' : 'character_choice_invalid',
          'severity' => $missing ? 'warning' : 'error',
          'step' => $step,
          'field' => $field,
          'path' => 'steps.' . $step . '.' . str_replace('][', '.', $field),
          'message' => $message,
        ];
        $issues[] = $issue;
        $report[$missing ? 'warnings' : 'errors'][] = $issue;
      }
      if ($step === 8) {
        foreach ($this->collectPendingCreationSelectionGrants($character_data) as $grant) {
          $issue = [
            'code' => 'character_feature_choice_required',
            'severity' => 'warning',
            'step' => 8,
            'field' => 'feat_selections',
            'path' => 'features.featEffects.selection_grants',
            'message' => (string) ($grant['description'] ?? $grant['selection_type'] ?? 'Unresolved feature selection.'),
          ];
          $issues[] = $issue;
          $report['warnings'][] = $issue;
        }
      }
      $schema = $this->schemaLoader->loadStepSchema($step);
      $report['steps'][] = [
        'number' => $step,
        'name' => $schema['properties']['step_name']['const'] ?? 'Step ' . $step,
        'complete' => $issues === [],
        'issues' => $issues,
        'choices' => $choices,
        ...($include_option_details ? ['option_cards' => $cards] : []),
      ];
    }
    $report['valid'] = $report['errors'] === [];
    $report['ready'] = $report['valid'] && $report['warnings'] === [];
    $report['completed_steps'] = count(array_filter($report['steps'], static fn(array $step): bool => $step['complete']));
    $report['percent_complete'] = (int) round($report['completed_steps'] / 8 * 100);
    $incomplete = array_values(array_filter($report['steps'], static fn(array $step): bool => !$step['complete']));
    $report['next_step'] = $incomplete[0]['number'] ?? 8;
    return $report;
  }

  /**
   * Read common constraints from the same definitions used by the manual editor.
   */
  private function validateFieldDefinitions(array $elements, CharacterCreationInput $input, array $parents, bool $tree, array &$choices, array &$cards): void {
    foreach ($elements['#attached']['drupalSettings']['characterOptionCards'] ?? [] as $group => $entries) {
      $cards[$group] = $entries;
    }
    if (($elements['#theme'] ?? '') === 'character_ability_widget' && ($elements['#mode'] ?? '') === 'interactive') {
      $field = $elements['#attributes']['data-step'] . '_boosts';
      $abilities = array_filter($elements['#abilities'], static fn(array $ability): bool => empty($ability['disabled']));
      $choices[$field] = [
        'required' => TRUE,
        'multiple' => TRUE,
        'selection_count' => (int) $elements['#boosts_total'],
        'unique_items' => TRUE,
        'options' => array_combine(array_keys($abilities), array_keys($abilities)),
      ];
      $selected = self::normalizeList($input->getValue($field, []));
      if (array_diff($selected, array_keys($abilities)) !== []) {
        $input->setErrorByName($field, $this->t('Choose only available abilities for this boost selection.'));
      }
    }
    foreach ($elements as $key => $element) {
      if (str_starts_with((string) $key, '#') || !is_array($element)) {
        continue;
      }
      $element_tree = $element['#tree'] ?? $tree;
      $path = $element['#parents'] ?? ($element_tree && $tree ? [...$parents, (string) $key] : [(string) $key]);
      $field = implode('][', $path);
      $type = $element['#type'] ?? '';
      $value = $input->getValue($path);
      $multiple = $type === 'checkboxes' || !empty($element['#multiple']);
      if (isset($element['#options']) && in_array($type, ['select', 'radios', 'checkboxes'], TRUE)) {
        $options = OptGroup::flattenOptions($element['#options']);
        $choices[$field] = [
          'required' => !empty($element['#required']),
          'multiple' => $multiple,
          'description' => strip_tags((string) ($element['#description'] ?? '')),
          'options' => array_map(static fn(mixed $label): string => (string) $label, $options),
          ...($multiple ? ['unique_items' => TRUE] : []),
        ];
        $selected = $multiple ? self::normalizeList($value) : ($value === NULL || $value === '' ? [] : [$value]);
        foreach ($selected as $selection) {
          if (!is_scalar($selection) || !array_key_exists((string) $selection, $options)) {
            $input->setErrorByName($field, $this->t('Choose a valid option for @field.', ['@field' => $element['#title'] ?? $field]));
            break;
          }
        }
        if ($multiple && count(array_unique($selected, SORT_REGULAR)) !== count($selected)) {
          $input->setErrorByName($field, $this->t('Choose distinct options for @field.', ['@field' => $element['#title'] ?? $field]));
        }
      }
      $empty = $value === NULL || $value === '' || ($multiple && self::normalizeList($value) === []);
      if (!empty($element['#required']) && $empty) {
        $input->setErrorByName($field, $this->t('@field field is required.', ['@field' => $element['#title'] ?? $field]));
      }
      if (!$empty && in_array($type, ['textfield', 'textarea', 'select', 'radios'], TRUE) && !is_scalar($value)) {
        $input->setErrorByName($field, $this->t('@field must be a scalar value.', ['@field' => $element['#title'] ?? $field]));
      }
      if (is_string($value)) {
        $max = $element['#maxlength'] ?? ($type === 'textfield' ? 128 : NULL);
        if ($max !== NULL && mb_strlen($value) > $max) {
          $input->setErrorByName($field, $this->t('@field cannot be longer than @count characters.', [
            '@field' => $element['#title'] ?? $field,
            '@count' => $max,
          ]));
        }
        $min = $element['#attributes']['minlength'] ?? NULL;
        if (!$empty && $min !== NULL && mb_strlen($value) < $min) {
          $input->setErrorByName($field, $this->t('@field must be at least @count characters.', [
            '@field' => $element['#title'] ?? $field,
            '@count' => $min,
          ]));
        }
        if (!$empty && isset($element['#attributes']['pattern'])
          && preg_match('~' . str_replace('~', '\~', $element['#attributes']['pattern']) . '~u', $value) !== 1) {
          $input->setErrorByName($field, $this->t('@field has an invalid format.', ['@field' => $element['#title'] ?? $field]));
        }
      }
      $this->validateFieldDefinitions($element, $input, $path, (bool) $element_tree, $choices, $cards);
    }
  }

}
