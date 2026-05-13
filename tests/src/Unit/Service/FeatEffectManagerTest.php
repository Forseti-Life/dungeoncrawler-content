<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;

/**
 * Unit tests for FeatEffectManager — dc-cr-feats-ch05 acceptance criteria.
 *
 * Covers: battle-medicine, assurance, recognize-spell, trick-magic-item,
 *         specialty-crafting, virtuosic-performer.
 *
 * @group dungeoncrawler_content
 * @group feats
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\FeatEffectManager
 */
class FeatEffectManagerTest extends UnitTestCase {

  protected FeatEffectManager $manager;

  protected function setUp(): void {
    parent::setUp();
    $this->manager = new FeatEffectManager();
  }

  // ---------------------------------------------------------------------------
  // Helpers
  // ---------------------------------------------------------------------------

  /**
   * Build a minimal character payload with one feat selected via feats array.
   */
  private function buildCharacterWithFeat(string $feat_id, array $extra_feat_keys = [], array $extra_character = []): array {
    return array_merge([
      'feats' => [array_merge(['id' => $feat_id], $extra_feat_keys)],
      'level' => 3,
    ], $extra_character);
  }

  /**
   * Build a character payload using feat_selections shape.
   */
  private function buildCharacterWithFeatSelection(string $feat_id, array $selection_data, array $extra_character = []): array {
    return array_merge([
      'feats' => [['id' => $feat_id]],
      'level' => 3,
      'feat_selections' => [$feat_id => $selection_data],
    ], $extra_character);
  }

  // ---------------------------------------------------------------------------
  // TC-FEAT-01: Battle Medicine — at_will action registered with correct shape
  // ---------------------------------------------------------------------------

  /**
   * @covers ::buildEffectState
   */
  public function testBattleMedicineRegistersAtWillAction(): void {
    $character = $this->buildCharacterWithFeat('battle-medicine');
    $effects = $this->manager->buildEffectState($character);

    $actions = $effects['available_actions']['at_will'];
    $found = NULL;
    foreach ($actions as $action) {
      if (($action['id'] ?? '') === 'battle-medicine') {
        $found = $action;
        break;
      }
    }

    $this->assertNotNull($found, 'battle-medicine at_will action should be registered');
    $this->assertSame(1, $found['action_cost']);
    $this->assertFalse($found['removes_wounded'], 'Battle Medicine must not remove wounded condition');
    $this->assertSame('battle_medicine_immune', $found['immunity_key']);
    $this->assertSame('1_day', $found['immunity_duration']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBattleMedicineDcHpTableMatchesTreatWounds(): void {
    $character = $this->buildCharacterWithFeat('battle-medicine');
    $effects = $this->manager->buildEffectState($character);

    $action = NULL;
    foreach ($effects['available_actions']['at_will'] as $a) {
      if (($a['id'] ?? '') === 'battle-medicine') {
        $action = $a;
        break;
      }
    }

    $this->assertNotNull($action);
    $this->assertSame([1 => 15, 2 => 20, 3 => 30, 4 => 40], $action['dc_table']);
    $this->assertSame([1 => 0, 2 => 10, 3 => 30, 4 => 50], $action['hp_bonus_table']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBattleMedicineRequiresHealersToolsAndTrainedMedicine(): void {
    $character = $this->buildCharacterWithFeat('battle-medicine');
    $effects = $this->manager->buildEffectState($character);

    $action = NULL;
    foreach ($effects['available_actions']['at_will'] as $a) {
      if (($a['id'] ?? '') === 'battle-medicine') {
        $action = $a;
        break;
      }
    }

    $this->assertNotNull($action);
    $this->assertTrue($action['requires_healers_tools']);
    $this->assertTrue($action['requires_trained_medicine']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBattleMedicineAppliedToFeatsList(): void {
    $character = $this->buildCharacterWithFeat('battle-medicine');
    $effects = $this->manager->buildEffectState($character);

    $this->assertContains('battle-medicine', $effects['applied_feats']);
  }

  // ---------------------------------------------------------------------------
  // TC-FEAT-02: Assurance — fixed result override stored per skill
  // ---------------------------------------------------------------------------

  /**
   * @covers ::buildEffectState
   */
  public function testAssuranceRegistersFixedResultOverrideForSkill(): void {
    $character = $this->buildCharacterWithFeat('assurance', ['skill' => 'Athletics']);
    $effects = $this->manager->buildEffectState($character);

    $this->assertArrayHasKey('assurance', $effects['feat_overrides']);
    $override = $effects['feat_overrides']['assurance'][0];
    $this->assertSame('fixed_result', $override['type']);
    $this->assertSame('athletics', $override['skill']);
    $this->assertSame('10_plus_proficiency', $override['formula']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAssuranceFallsBackToUnknownWhenNoSkillSelected(): void {
    $character = $this->buildCharacterWithFeat('assurance');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['assurance'][0];
    $this->assertSame('unknown', $override['skill']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAssuranceSkillResolvableViaFeatSelectionsShape(): void {
    $character = $this->buildCharacterWithFeatSelection('assurance', ['skill' => 'Stealth']);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['assurance'][0];
    $this->assertSame('stealth', $override['skill'], 'Skill from feat_selections must be resolved correctly');
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAssuranceAppliedToFeatsList(): void {
    $character = $this->buildCharacterWithFeat('assurance', ['skill' => 'Acrobatics']);
    $effects = $this->manager->buildEffectState($character);

    $this->assertContains('assurance', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAdaptedCantripEmitsSelectionGrantWithoutChosenCantrip(): void {
    $character = $this->buildCharacterWithFeat('adapted-cantrip');
    $effects = $this->manager->buildEffectState($character);

    $this->assertNotEmpty($effects['selection_grants']);
    $this->assertSame('adapted-cantrip', $effects['selection_grants'][0]['feat_id']);
    $this->assertSame('adapted_cantrip_choice', $effects['selection_grants'][0]['selection_type']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAdaptedCantripBuildsInnateSpellFromFeatSelections(): void {
    $character = $this->buildCharacterWithFeatSelection('adapted-cantrip', [
      'selected_tradition' => 'occult',
      'selected_cantrip' => 'daze',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('daze', $effects['spell_augments']['innate_spells'][0]['spell_id']);
    $this->assertSame('Daze', $effects['spell_augments']['innate_spells'][0]['spell_name']);
    $this->assertSame('occult', $effects['spell_augments']['innate_spells'][0]['tradition']);
    $this->assertSame('Cast Adapted Cantrip', $effects['available_actions']['at_will'][0]['name']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCooperativeNatureRegistersAidOverride(): void {
    $character = $this->buildCharacterWithFeat('cooperative-nature');
    $effects = $this->manager->buildEffectState($character);

    $this->assertArrayHasKey('cooperative-nature', $effects['feat_overrides']);
    $override = $effects['feat_overrides']['cooperative-nature'][0];
    $this->assertSame('aid_bonus', $override['type']);
    $this->assertSame(5, $override['skill_check_bonus']);
    $this->assertSame(2, $override['attack_roll_bonus']);
    $this->assertSame(2, $override['ac_bonus']);
    $this->assertTrue($override['replaces_default_aid_values']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCooperativeNatureDoesNotAddSaveModifier(): void {
    $character = $this->buildCharacterWithFeat('cooperative-nature');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame([], $effects['conditional_modifiers']['saving_throws']);
    $this->assertContains('cooperative-nature', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGeneralTrainingPromotesSelectedBonusGeneralFeat(): void {
    $character = $this->buildCharacterWithFeatSelection('general-training', [
      'bonus_general_feat' => 'fleet',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertContains('general-training', $effects['applied_feats']);
    $this->assertContains('fleet', $effects['applied_feats']);
    $this->assertSame(5, $effects['derived_adjustments']['speed_bonus']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHaughtyObstinacyAddsMentalWillBonusAndSuccessImmunity(): void {
    $character = $this->buildCharacterWithFeat('haughty-obstinacy');
    $effects = $this->manager->buildEffectState($character);

    $this->assertCount(1, $effects['conditional_modifiers']['saving_throws']);
    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame('Will', $modifier['save']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('mental effects', $modifier['context']);

    $this->assertArrayHasKey('haughty-obstinacy', $effects['feat_overrides']);
    $override = $effects['feat_overrides']['haughty-obstinacy'][0];
    $this->assertSame('success_immunity', $override['type']);
    $this->assertSame('successful_will_save', $override['trigger']);
    $this->assertSame('mental', $override['effect_category']);
    $this->assertSame('effect_source', $override['immunity_target']);
    $this->assertSame('10_minutes', $override['duration']);
    $this->assertContains('haughty-obstinacy', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testNaturalSkillAppliesSelectedSkillTraining(): void {
    $character = $this->buildCharacterWithFeatSelection('natural-skill', [
      'skills' => ['Athletics', 'Stealth'],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $skills = $effects['training_grants']['skills'];
    $this->assertSame('trained', $skills['Athletics'] ?? NULL);
    $this->assertSame('trained', $skills['Stealth'] ?? NULL);
    $this->assertContains('natural-skill', $effects['applied_feats']);
    $this->assertSame([], $effects['selection_grants']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDwarvenLoreGrantsSkillsAndLore(): void {
    $character = $this->buildCharacterWithFeat('dwarven-lore');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Crafting'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['skills']['Religion'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['lore']['Crafting Lore'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['lore']['Dwarven Lore'] ?? NULL);
    $this->assertContains('dwarven-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDwarvenWeaponFamiliarityGrantsWeaponTraining(): void {
    $character = $this->buildCharacterWithFeat('dwarven-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $this->assertCount(1, $effects['training_grants']['weapons']);
    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Dwarven Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['battle axe', 'pick', 'warhammer'], $grant['examples']);
    $this->assertContains('dwarven-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRockRunnerGrantsStoneMovementAndBalanceBenefits(): void {
    $character = $this->buildCharacterWithFeat('rock-runner');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['ignore_difficult_terrain_rubble_stone'] ?? FALSE);
    $this->assertTrue($effects['derived_adjustments']['flags']['ignore_flat_footed_balance_stone'] ?? FALSE);
    $this->assertCount(1, $effects['conditional_modifiers']['skills']);
    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Acrobatics', $modifier['skill']);
    $this->assertSame(2, $modifier['bonus']);
    $this->assertContains('rock-runner', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testStonecunningAddsStoneworkPerceptionOverride(): void {
    $character = $this->buildCharacterWithFeat('stonecunning');
    $effects = $this->manager->buildEffectState($character);

    $this->assertArrayHasKey('stonecunning', $effects['feat_overrides']);
    $override = $effects['feat_overrides']['stonecunning'][0];
    $this->assertSame('conditional_perception_bonus', $override['type']);
    $this->assertSame(2, $override['bonus']);
    $this->assertSame('notice unusual stonework', $override['context']);
    $this->assertSame('within_10ft_stonework', $override['auto_check_trigger']);
    $this->assertContains('stonecunning', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUnburdenedIronRemovesArmorAndEncumberedSpeedPenalties(): void {
    $character = $this->buildCharacterWithFeat('unburdened-iron');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['ignore_armor_speed_penalty'] ?? FALSE);
    $this->assertTrue($effects['derived_adjustments']['flags']['ignore_encumbered_speed_penalty'] ?? FALSE);
    $this->assertContains('unburdened-iron', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVengefulHatredAddsChosenTargetDamageOverride(): void {
    $character = $this->buildCharacterWithFeatSelection('vengeful-hatred', [
      'target_type' => 'giant',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertArrayHasKey('vengeful-hatred', $effects['feat_overrides']);
    $override = $effects['feat_overrides']['vengeful-hatred'][0];
    $this->assertSame('conditional_damage_bonus', $override['type']);
    $this->assertSame(1, $override['bonus']);
    $this->assertSame('weapon_die', $override['per']);
    $this->assertSame('giant', $override['target_trait']);
    $this->assertSame('circumstance', $override['bonus_type']);
    $this->assertContains('vengeful-hatred', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAncestralLongevityAppliesSelectedSkillTraining(): void {
    $character = $this->buildCharacterWithFeatSelection('ancestral-longevity', [
      'selected_skills' => ['Arcana', 'Society'],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $skills = $effects['training_grants']['skills'];
    $this->assertSame('trained', $skills['Arcana'] ?? NULL);
    $this->assertSame('trained', $skills['Society'] ?? NULL);
    $this->assertContains('ancestral-longevity', $effects['applied_feats']);
    $this->assertSame([], $effects['selection_grants']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testElvenLoreAddsSkillAndLoreTraining(): void {
    $character = $this->buildCharacterWithFeat('elven-lore');
    $effects = $this->manager->buildEffectState($character);

    $skills = $effects['training_grants']['skills'];
    $lores = $effects['training_grants']['lores'];
    $this->assertSame('trained', $skills['Arcana'] ?? NULL);
    $this->assertSame('trained', $skills['Nature'] ?? NULL);
    $this->assertSame('trained', $lores['Elven Lore'] ?? NULL);
    $this->assertContains('elven-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testElvenWeaponFamiliarityAddsWeaponTraining(): void {
    $character = $this->buildCharacterWithFeat('elven-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Elven Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(
      ['longbow', 'composite longbow', 'longsword', 'rapier', 'shortbow', 'composite shortbow'],
      $grant['examples']
    );
    $this->assertContains('elven-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFirstWorldMagicBuildsInnateSpellFromFeatSelections(): void {
    $character = $this->buildCharacterWithFeatSelection('first-world-magic', [
      'selected_cantrip' => 'detect-magic',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('detect-magic', $effects['spell_augments']['innate_spells'][0]['spell_id']);
    $this->assertSame('Detect Magic', $effects['spell_augments']['innate_spells'][0]['spell_name']);
    $this->assertSame('primal', $effects['spell_augments']['innate_spells'][0]['tradition']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOtherworldlyMagicBuildsInnateSpellFromFeatSelections(): void {
    $character = $this->buildCharacterWithFeatSelection('otherworldly-magic', [
      'selected_cantrip' => 'detect-magic',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('detect-magic', $effects['spell_augments']['innate_spells'][0]['spell_id']);
    $this->assertSame('Detect Magic', $effects['spell_augments']['innate_spells'][0]['spell_name']);
    $this->assertSame('primal', $effects['spell_augments']['innate_spells'][0]['tradition']);
    $this->assertSame('Cast Otherworldly Cantrip', $effects['available_actions']['at_will'][0]['name']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testForlornAddsEmotionSaveBonusAndUpgrade(): void {
    $character = $this->buildCharacterWithFeat('forlorn');
    $effects = $this->manager->buildEffectState($character);

    $this->assertCount(1, $effects['conditional_modifiers']['saving_throws']);
    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame('All', $modifier['save']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('emotion effects', $modifier['context']);
    $upgrade = $effects['conditional_modifiers']['outcome_upgrades'][0];
    $this->assertSame('forlorn', $upgrade['id']);
    $this->assertSame('emotion effects', $upgrade['context']);
    $this->assertContains('forlorn', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testNimbleElfSetsSpeedFloor(): void {
    $character = $this->buildCharacterWithFeat('nimble-elf');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame(35, $effects['derived_adjustments']['speed_override']);
    $this->assertContains('nimble-elf', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAnimalAccompliceAddsFamiliarCreationGrant(): void {
    $character = $this->buildCharacterWithFeat('animal-accomplice');
    $effects = $this->manager->buildEffectState($character);

    $this->assertNotEmpty($effects['selection_grants']);
    $this->assertSame('animal-accomplice', $effects['selection_grants'][0]['feat_id']);
    $this->assertSame('familiar_creation', $effects['selection_grants'][0]['selection_type']);
    $this->assertContains('animal-accomplice', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBurrowElocutionistAddsBurrowingSpeechSupport(): void {
    $character = $this->buildCharacterWithFeat('burrow-elocutionist');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['speak_with_burrowing_creatures'] ?? FALSE);
    $this->assertSame('Burrow Elocutionist', $effects['available_actions']['at_will'][0]['name']);
    $this->assertContains('burrow-elocutionist', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFeyFellowshipAddsFeyBonusesAndImpressionAction(): void {
    $character = $this->buildCharacterWithFeat('fey-fellowship');
    $effects = $this->manager->buildEffectState($character);

    $this->assertCount(1, $effects['conditional_modifiers']['skills']);
    $this->assertSame('Perception', $effects['conditional_modifiers']['skills'][0]['skill']);
    $this->assertSame(2, $effects['conditional_modifiers']['skills'][0]['bonus']);
    $this->assertSame('against fey creatures', $effects['conditional_modifiers']['skills'][0]['context']);
    $this->assertCount(1, $effects['conditional_modifiers']['saving_throws']);
    $this->assertSame('All', $effects['conditional_modifiers']['saving_throws'][0]['save']);
    $this->assertSame(2, $effects['conditional_modifiers']['saving_throws'][0]['bonus']);
    $this->assertSame('against fey creatures', $effects['conditional_modifiers']['saving_throws'][0]['context']);
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Fey Fellowship: Make an Impression', $action['name']);
    $this->assertSame('Diplomacy', $action['skill']);
    $this->assertSame(-5, $action['penalty']);
    $this->assertSame('glad-hand', $action['penalty_waived_by_feat']);
    $this->assertContains('fey-fellowship', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGnomeObsessionAppliesLoreSelectionAndDowntimeOverride(): void {
    $character = $this->buildCharacterWithFeatSelection('gnome-obsession', [
      'selected_lore' => 'Forest Lore',
    ], [
      'background_lore_skill' => 'Farming Lore',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertContains('Forest Lore', $effects['training_grants']['lore']);
    $this->assertContains('Farming Lore', $effects['training_grants']['lore']);
    $this->assertSame('Forest Lore', $effects['derived_adjustments']['flags']['gnome_obsession_lore']);
    $this->assertSame('expert', $effects['derived_adjustments']['flags']['gnome_obsession_lore_rank']);
    $this->assertSame('Farming Lore', $effects['derived_adjustments']['flags']['gnome_obsession_background_lore']);
    $override = $effects['feat_overrides']['gnome-obsession'][0];
    $this->assertSame('conditional_related_skill_bonus', $override['type']);
    $this->assertSame(1, $override['bonus']);
    $this->assertSame('Forest Lore', $override['related_lore']);
    $this->assertContains('gnome-obsession', $effects['applied_feats']);
    $this->assertSame([], $effects['selection_grants']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGnomeWeaponFamiliarityAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('gnome-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Gnome Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['glaive', 'kukri'], $grant['examples']);
    $this->assertTrue($grant['uncommon_access']);
    $this->assertSame(['martial' => 'simple', 'advanced' => 'martial'], $grant['proficiency_remap']);
    $this->assertContains('gnome-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testIllusionSenseAddsIllusionBonusesAndAutoCheck(): void {
    $character = $this->buildCharacterWithFeat('illusion-sense');
    $effects = $this->manager->buildEffectState($character);

    $this->assertCount(1, $effects['conditional_modifiers']['saving_throws']);
    $this->assertSame('Will', $effects['conditional_modifiers']['saving_throws'][0]['save']);
    $this->assertSame(1, $effects['conditional_modifiers']['saving_throws'][0]['bonus']);
    $this->assertSame('illusions', $effects['conditional_modifiers']['saving_throws'][0]['context']);
    $override = $effects['feat_overrides']['illusion-sense'][0];
    $this->assertSame('conditional_perception_bonus', $override['type']);
    $this->assertSame(1, $override['bonus']);
    $this->assertSame('disbelieve illusions', $override['context']);
    $this->assertSame('enter_visible_illusion_area', $override['auto_check_trigger']);
    $this->assertContains('illusion-sense', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testNaturalPerformerAddsPerformanceTrainingAndSpecialtyBonus(): void {
    $character = $this->buildCharacterWithFeatSelection('natural-performer', [
      'specialty' => 'singing',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Performance'] ?? NULL);
    $override = $effects['feat_overrides']['natural-performer'][0];
    $this->assertSame('conditional_performance_bonus', $override['type']);
    $this->assertSame(1, $override['bonus']);
    $this->assertSame('singing', $override['specialty']);
    $this->assertContains('natural-performer', $effects['applied_feats']);
    $this->assertSame([], $effects['selection_grants']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVibrantDisplayAddsSaveActionAndImmunityMetadata(): void {
    $character = $this->buildCharacterWithFeat('vibrant-display');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Vibrant Display', $action['name']);
    $this->assertSame(2, $action['action_cost']);
    $this->assertSame('Will', $action['save']);
    $this->assertSame('10_plus_cha_mod_plus_level', $action['dc_formula']);
    $this->assertSame('fascinated_until_end_of_next_turn', $action['on_failure']);
    $this->assertSame('1_minute', $action['immunity_duration']);
    $this->assertContains('vibrant-display', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFirstWorldAdeptAddsInnateSpellsAndDailyActions(): void {
    $character = $this->buildCharacterWithFeat('first-world-adept');
    $effects = $this->manager->buildEffectState($character);

    $this->assertCount(2, $effects['spell_augments']['innate_spells']);
    $this->assertSame('faerie-fire', $effects['spell_augments']['innate_spells'][0]['spell_id']);
    $this->assertSame('invisibility', $effects['spell_augments']['innate_spells'][1]['spell_id']);
    $this->assertCount(2, $effects['available_actions']['per_long_rest']);
    $this->assertSame('Cast Faerie Fire (innate, 1/day)', $effects['available_actions']['per_long_rest'][0]['name']);
    $this->assertSame('Cast Invisibility (innate, 1/day)', $effects['available_actions']['per_long_rest'][1]['name']);
    $this->assertContains('first-world-adept', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVivaciousConduitAddsRestHealingAction(): void {
    $character = $this->buildCharacterWithFeat('vivacious-conduit');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['vivacious_conduit_short_rest_heal'] ?? FALSE);
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Vivacious Conduit', $action['name']);
    $this->assertSame('10_minutes_rest', $action['action_cost']);
    $this->assertSame('constitution_modifier_x_half_level', $action['healing_formula']);
    $this->assertTrue($action['stacks_with_treat_wounds']);
    $this->assertContains('vivacious-conduit', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGnomeWeaponSpecialistAddsCritSpecializationFlag(): void {
    $character = $this->buildCharacterWithFeat('gnome-weapon-specialist');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['gnome_weapon_specialist_crit_spec'] ?? FALSE);
    $this->assertContains('gnome-weapon-specialist', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGnomeWeaponExpertiseCascadesClassWeaponRank(): void {
    $character = [
      'feats' => [
        ['id' => 'gnome-weapon-familiarity'],
        ['id' => 'gnome-weapon-expertise'],
      ],
      'level' => 13,
      'class_features' => [
        ['id' => 'wizard-weapon-expertise'],
      ],
    ];
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Gnome Weapons', $grant['group']);
    $this->assertSame('expert', $grant['proficiency']);
    $this->assertSame('expert', $effects['derived_adjustments']['flags']['gnome_weapon_expertise_cascade_rank']);
    $this->assertContains('gnome-weapon-expertise', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBurnItAddsFireDamageAndResistanceOverrides(): void {
    $character = $this->buildCharacterWithFeat('burn-it');
    $effects = $this->manager->buildEffectState($character);

    $this->assertArrayHasKey('burn-it', $effects['feat_overrides']);
    $this->assertSame('conditional_fire_damage_bonus', $effects['feat_overrides']['burn-it'][0]['type']);
    $this->assertSame(1, $effects['feat_overrides']['burn-it'][0]['bonus']);
    $this->assertSame('status', $effects['feat_overrides']['burn-it'][0]['bonus_type']);
    $this->assertSame('fire_resistance_reduction', $effects['feat_overrides']['burn-it'][1]['type']);
    $this->assertSame('max(1, floor(level/2))', $effects['feat_overrides']['burn-it'][1]['reduction_formula']);
    $this->assertContains('burn-it', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCityScavengerAddsUrbanSkillSubstitutions(): void {
    $character = $this->buildCharacterWithFeat('city-scavenger');
    $effects = $this->manager->buildEffectState($character);

    $this->assertArrayHasKey('city-scavenger', $effects['feat_overrides']);
    $subsist = $effects['feat_overrides']['city-scavenger'][0];
    $this->assertSame('subsist_skill_substitution', $subsist['type']);
    $this->assertSame(['Society', 'Survival'], $subsist['allowed_skills']);
    $this->assertSame('settlement', $subsist['environment']);
    $tracking = $effects['feat_overrides']['city-scavenger'][1];
    $this->assertSame('skill_substitution', $tracking['type']);
    $this->assertSame('Society', $tracking['substitute_skill']);
    $this->assertSame('Survival', $tracking['replaces_skill']);
    $this->assertSame(['Track', 'Seek'], $tracking['actions']);
    $this->assertSame('urban', $tracking['environment']);
    $this->assertContains('city-scavenger', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGoblinLoreAddsSkillAndLoreTraining(): void {
    $character = $this->buildCharacterWithFeat('goblin-lore');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Nature'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['skills']['Stealth'] ?? NULL);
    $this->assertContains('Goblin Lore', $effects['training_grants']['lore']);
    $this->assertContains('goblin-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGoblinScuttleAddsReactionStep(): void {
    $character = $this->buildCharacterWithFeat('goblin-scuttle');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Goblin Scuttle', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('ally_ends_move_adjacent', $action['trigger']);
    $this->assertSame('step', $action['effect']);
    $this->assertContains('goblin-scuttle', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGoblinSongAddsPerformanceActionAndImmunity(): void {
    $character = $this->buildCharacterWithFeat('goblin-song');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Goblin Song', $action['name']);
    $this->assertSame(1, $action['action_cost']);
    $this->assertSame('Performance', $action['skill']);
    $this->assertSame('target_will_dc', $action['check_against']);
    $this->assertSame('frightened_1', $action['on_success']);
    $this->assertSame('frightened_2', $action['on_critical_success']);
    $this->assertSame('1_hour', $action['immunity_duration']);
    $this->assertContains('goblin-song', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGoblinWeaponFamiliarityAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('goblin-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Goblin Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['dogslicer', 'horsechopper'], $grant['examples']);
    $this->assertTrue($grant['uncommon_access']);
    $this->assertSame(['martial' => 'simple', 'advanced' => 'martial'], $grant['proficiency_remap']);
    $this->assertContains('goblin-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGoblinWeaponFrenzyAddsCritSpecializationFlag(): void {
    $character = $this->buildCharacterWithFeat('goblin-weapon-frenzy');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['goblin_weapon_frenzy_crit_spec'] ?? FALSE);
    $this->assertContains('goblin-weapon-frenzy', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testJunkTinkerAddsCraftingTrainingAndJunkCraftingOverride(): void {
    $character = $this->buildCharacterWithFeat('junk-tinker');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Crafting'] ?? NULL);
    $override = $effects['feat_overrides']['junk-tinker'][0];
    $this->assertSame('junk_crafting', $override['type']);
    $this->assertSame(-5, $override['dc_adjustment']);
    $this->assertSame('shoddy', $override['item_quality']);
    $this->assertSame('nonmagical_items_from_junk', $override['scope']);
    $this->assertContains('junk-tinker', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVerySneakyAddsSneakMovementFlags(): void {
    $character = $this->buildCharacterWithFeat('very-sneaky');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame(5, $effects['derived_adjustments']['flags']['very_sneaky_sneak_distance_bonus'] ?? NULL);
    $this->assertTrue($effects['derived_adjustments']['flags']['very_sneaky_eot_visibility_delay'] ?? FALSE);
    $this->assertContains('very-sneaky', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDistractingShadowsAddsLargeCreatureCoverRule(): void {
    $character = $this->buildCharacterWithFeat('distracting-shadows');
    $effects = $this->manager->buildEffectState($character);

    $rule = $effects['conditional_modifiers']['movement'][0];
    $this->assertSame('distracting-shadows', $rule['id']);
    $this->assertSame('can_use_larger_creatures_as_cover', $rule['rule']);
    $this->assertSame('Hide and Sneak', $rule['context']);
    $this->assertContains('distracting-shadows', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHalflingLoreAddsSkillAndLoreTraining(): void {
    $character = $this->buildCharacterWithFeat('halfling-lore');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Acrobatics'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['skills']['Stealth'] ?? NULL);
    $this->assertContains('Halfling Lore', $effects['training_grants']['lore']);
    $this->assertContains('halfling-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHalflingWeaponFamiliarityAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('halfling-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Halfling Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['sling', 'halfling sling staff'], $grant['examples']);
    $this->assertSame(['martial' => 'simple', 'advanced' => 'martial'], $grant['proficiency_remap']);
    $this->assertContains('halfling-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSureFeetAddsBalanceOutcomeUpgrade(): void {
    $character = $this->buildCharacterWithFeat('sure-feet');
    $effects = $this->manager->buildEffectState($character);

    $upgrade = $effects['conditional_modifiers']['outcome_upgrades'][0];
    $this->assertSame('sure-feet', $upgrade['id']);
    $this->assertSame('Acrobatics:Balance', $upgrade['target']);
    $this->assertSame('critical_failure', $upgrade['from']);
    $this->assertSame('success', $upgrade['to']);
    $this->assertContains('sure-feet', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTitanSlingerAddsRangeIncrementScaling(): void {
    $character = $this->buildCharacterWithFeat('titan-slinger');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['titan-slinger'][0];
    $this->assertSame(['thrown', 'sling'], $override['weapon_types']);
    $this->assertSame(10, $override['range_increment_bonus']);
    $this->assertSame(13, $override['scales_at_level']);
    $this->assertSame(20, $override['scaled_range_increment_bonus']);
    $this->assertContains('titan-slinger', $effects['applied_feats']);

    $high_level_effects = $this->manager->buildEffectState([
      'feats' => [['id' => 'titan-slinger']],
      'level' => 13,
    ]);
    $this->assertSame(20, $high_level_effects['feat_overrides']['titan-slinger'][0]['range_increment_bonus']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUnfetteredHalflingAddsEscapeBonusAndUpgrade(): void {
    $character = $this->buildCharacterWithFeat('unfettered-halfling');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Escape', $modifier['skill']);
    $this->assertSame(2, $modifier['bonus']);
    $this->assertSame('circumstance', $modifier['bonus_type']);

    $upgrade = $effects['conditional_modifiers']['outcome_upgrades'][0];
    $this->assertSame('unfettered-halfling', $upgrade['id']);
    $this->assertSame('Escape', $upgrade['target']);
    $this->assertSame('success', $upgrade['from']);
    $this->assertSame('critical_success', $upgrade['to']);
    $this->assertContains('unfettered-halfling', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHalflingResolveAddsEmotionSaveUpgradeAndGutsyRider(): void {
    $character = $this->buildCharacterWithFeat('halfling-resolve');
    $effects = $this->manager->buildEffectState($character);

    $upgrade = $effects['conditional_modifiers']['outcome_upgrades'][0];
    $this->assertSame('halfling-resolve', $upgrade['id']);
    $this->assertSame('saving_throw', $upgrade['target']);
    $this->assertSame('success', $upgrade['from']);
    $this->assertSame('critical_success', $upgrade['to']);
    $this->assertSame('emotion effects', $upgrade['context']);
    $this->assertTrue($effects['derived_adjustments']['flags']['halfling_resolve_active']);

    $gutsy_effects = $this->manager->buildEffectState([
      'feats' => [['id' => 'halfling-resolve']],
      'heritage' => 'gutsy',
      'level' => 9,
    ]);
    $gutsy_upgrade = $gutsy_effects['conditional_modifiers']['outcome_upgrades'][1];
    $this->assertSame('halfling-resolve-gutsy', $gutsy_upgrade['id']);
    $this->assertSame('critical_failure', $gutsy_upgrade['from']);
    $this->assertSame('failure', $gutsy_upgrade['to']);
    $this->assertSame('emotion effects', $gutsy_upgrade['context']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCeaselessShadowsAddsHideAndCoverFlags(): void {
    $character = $this->buildCharacterWithFeat('ceaseless-shadows');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['ceaseless_shadows_hide_sneak_no_cover']);
    $this->assertTrue($effects['derived_adjustments']['flags']['ceaseless_shadows_creature_cover_upgrade']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testElfAtavismAddsSelectionGrantUntilFeatChosen(): void {
    $character = $this->buildCharacterWithFeat('elf-atavism');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('elf-atavism', $grant['source_feat']);
    $this->assertSame('elf_ancestry_feat', $grant['selection_type']);
    $this->assertContains('elf-atavism', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testForlornHalfElfAddsSaveBonusAndLimitedUpgrade(): void {
    $character = $this->buildCharacterWithFeat('forlorn-half-elf');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame('Will', $modifier['save']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('emotion effects', $modifier['context']);

    $override = $effects['feat_overrides']['forlorn-half-elf'][0];
    $this->assertSame('limited_success_upgrade', $override['type']);
    $this->assertSame('success', $override['from']);
    $this->assertSame('critical_success', $override['to']);
    $this->assertSame(1, $override['uses_per_long_rest']);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('forlorn-half-elf-emotion-save-upgrade', $action['id']);
    $this->assertContains('forlorn-half-elf', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMultitalentedGrantsSkillTrainingAndLanguage(): void {
    $character = $this->buildCharacterWithFeatSelection('multitalented', [
      'selected_skill' => 'Society',
      'selected_language' => 'Elven',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Society'] ?? NULL);
    $override = $effects['feat_overrides']['multitalented'][0];
    $this->assertSame('additional_language', $override['type']);
    $this->assertSame('Elven', $override['language']);
    $this->assertContains('multitalented', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMixedHeritageAdaptabilityAddsSelectedSkillBonus(): void {
    $character = $this->buildCharacterWithFeatSelection('mixed-heritage-adaptability', [
      'selected_skill' => 'Diplomacy',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Diplomacy', $modifier['skill']);
    $this->assertSame(1, $modifier['bonus']);
    $override = $effects['feat_overrides']['mixed-heritage-adaptability'][0];
    $this->assertSame('daily_reassignable_skill_bonus', $override['type']);
    $this->assertSame('Diplomacy', $override['skill']);
    $this->assertContains('mixed-heritage-adaptability', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testElvenInstinctsAddsInitiativeAndSeekBonus(): void {
    $character = $this->buildCharacterWithFeat('elven-instincts');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame(1, $effects['derived_adjustments']['initiative_bonus']);
    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Perception', $modifier['skill']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('Seek', $modifier['context']);
    $this->assertContains('elven-instincts', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCrossCulturalUpbringingAddsSocietyTrainingAndRecallKnowledgeOverride(): void {
    $character = $this->buildCharacterWithFeat('cross-cultural-upbringing');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Society'] ?? NULL);
    $override = $effects['feat_overrides']['cross-cultural-upbringing'][0];
    $this->assertSame('recall_knowledge_expansion', $override['type']);
    $this->assertSame('Society', $override['skill']);
    $this->assertSame(['human', 'elven'], $override['communities']);
    $this->assertContains('cross-cultural-upbringing', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrcAtavismAddsSelectionGrantUntilFeatChosen(): void {
    $character = $this->buildCharacterWithFeat('orc-atavism');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('orc-atavism', $grant['source_feat']);
    $this->assertSame('orc_ancestry_feat', $grant['selection_type']);
    $this->assertContains('orc-atavism', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFeralEnduranceAddsSurviveZeroHpOverride(): void {
    $character = $this->buildCharacterWithFeat('feral-endurance');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('feral-endurance', $action['id']);
    $override = $effects['feat_overrides']['feral-endurance'][0];
    $this->assertSame('survive_zero_hp', $override['type']);
    $this->assertSame(1, $override['hp_floor']);
    $this->assertSame(1, $override['wounded_value']);
    $this->assertContains('feral-endurance', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testIntimidatingGlareHalfOrcAddsDemoralizeOverride(): void {
    $character = $this->buildCharacterWithFeat('intimidating-glare-half-orc');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Intimidating Glare', $action['name']);
    $this->assertSame(1, $action['action_cost']);
    $this->assertSame('Intimidation', $action['skill']);
    $this->assertSame('Demoralize', $action['activity']);
    $this->assertFalse($action['shared_language_required']);
    $this->assertContains('intimidating-glare-half-orc', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrcWeaponFamiliarityHalfOrcAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('orc-weapon-familiarity-half-orc');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Orc Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['martial' => 'simple'], $grant['proficiency_remap']);
    $this->assertContains('orc-weapon-familiarity-half-orc', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testScarThickenedAddsFortitudeBonusAgainstBleedAndPoison(): void {
    $character = $this->buildCharacterWithFeat('scar-thickened');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame('Fortitude', $modifier['save']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('persistent bleed and poison effects', $modifier['context']);
    $this->assertContains('scar-thickened', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUnyieldingWillAddsFearSaveBonus(): void {
    $character = $this->buildCharacterWithFeat('unyielding-will');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame('Will', $modifier['save']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('fear effects', $modifier['context']);
    $this->assertContains('unyielding-will', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testKoboldLoreAddsSkillAndLoreTraining(): void {
    $character = $this->buildCharacterWithFeat('kobold-lore');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Crafting'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['skills']['Stealth'] ?? NULL);
    $this->assertContains('Kobold Lore', $effects['training_grants']['lore']);
    $this->assertContains('kobold-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSnareSetterAddsSnareEfficiencyOverride(): void {
    $character = $this->buildCharacterWithFeat('snare-setter');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['snare-setter'][0];
    $this->assertSame('snare_setup_efficiency', $override['type']);
    $this->assertSame('faster_simple_snares', $override['crafting_speed']);
    $this->assertSame('reduced_setup_time', $override['deployment_speed']);
    $this->assertContains('snare-setter', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDraconicTiesAddsSelectedResistance(): void {
    $character = $this->buildCharacterWithFeatSelection('draconic-ties', [
      'damage_type' => 'fire',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['draconic-ties'][0];
    $this->assertSame('energy_resistance', $override['type']);
    $this->assertSame('fire', $override['damage_type']);
    $this->assertSame(1, $override['resistance']);
    $this->assertContains('draconic-ties', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTunnelRunnerAddsMovementRuleAndSqueezeBonus(): void {
    $character = $this->buildCharacterWithFeat('tunnel-runner');
    $effects = $this->manager->buildEffectState($character);

    $movement = $effects['conditional_modifiers']['movement'][0];
    $this->assertSame('tunnel-runner', $movement['id']);
    $this->assertSame('ignore_cramped_underground_movement_penalties', $movement['rule']);
    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Acrobatics', $modifier['skill']);
    $this->assertSame(2, $modifier['bonus']);
    $this->assertSame('Squeeze in cramped underground passages', $modifier['context']);
    $this->assertContains('tunnel-runner', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDraconicScoutAddsUndergroundBonuses(): void {
    $character = $this->buildCharacterWithFeat('draconic-scout');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['draconic-scout'][0];
    $this->assertSame('conditional_initiative_bonus', $override['type']);
    $this->assertSame(1, $override['bonus']);
    $this->assertSame('underground', $override['environment']);
    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Survival', $modifier['skill']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('when underground', $modifier['context']);
    $this->assertContains('draconic-scout', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testKoboldWeaponFamiliarityAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('kobold-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Kobold Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['martial' => 'simple'], $grant['proficiency_remap']);
    $this->assertContains('kobold-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLeshyLoreAddsSkillAndLoreTraining(): void {
    $character = $this->buildCharacterWithFeat('leshy-lore');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Nature'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['skills']['Diplomacy'] ?? NULL);
    $this->assertContains('Leshy Lore', $effects['training_grants']['lore']);
    $this->assertContains('leshy-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSeedpodAddsNaturalRangedAttack(): void {
    $character = $this->buildCharacterWithFeat('seedpod');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Seedpod', $action['name']);
    $this->assertSame('ranged_natural_attack', $action['attack_type']);
    $override = $effects['feat_overrides']['seedpod'][0];
    $this->assertSame('natural_attack_grant', $override['type']);
    $this->assertSame('seedpod', $override['attack_form']);
    $this->assertContains('seedpod', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPhotosyntheticRecoveryAddsSunlightRestOverride(): void {
    $character = $this->buildCharacterWithFeat('photosynthetic-recovery');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['photosynthetic-recovery'][0];
    $this->assertSame('sunlight_rest_healing', $override['type']);
    $this->assertSame('rest_in_natural_sunlight', $override['rest_type']);
    $this->assertContains('photosynthetic-recovery', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRootedResilienceAddsForcedMovementResistance(): void {
    $character = $this->buildCharacterWithFeat('rooted-resilience');
    $effects = $this->manager->buildEffectState($character);

    $movement = $effects['conditional_modifiers']['movement'][0];
    $this->assertSame('rooted-resilience', $movement['id']);
    $this->assertSame('forced_movement_resistance', $movement['rule']);
    $this->assertSame(1, $movement['bonus']);
    $this->assertSame('against forced movement and prone effects', $movement['context']);
    $this->assertContains('rooted-resilience', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVerdantVoiceAddsPlantCommunicationAndNatureBonus(): void {
    $character = $this->buildCharacterWithFeat('verdant-voice');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Nature', $modifier['skill']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('to influence plant creatures', $modifier['context']);
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('communicate_simple_intent', $action['activity']);
    $this->assertSame('plant', $action['target_trait']);
    $this->assertContains('verdant-voice', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testForestStepIgnoresNaturalUndergrowth(): void {
    $character = $this->buildCharacterWithFeat('forest-step');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['ignore_difficult_terrain_natural_undergrowth']);
    $this->assertContains('forest-step', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRatfolkLoreAddsSkillAndLoreTraining(): void {
    $character = $this->buildCharacterWithFeat('ratfolk-lore');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Society'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['skills']['Thievery'] ?? NULL);
    $this->assertContains('Ratfolk Lore', $effects['training_grants']['lore']);
    $this->assertContains('ratfolk-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCheekPouchesAddsQuickStowAction(): void {
    $character = $this->buildCharacterWithFeat('cheek-pouches');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Cheek Pouches', $action['name']);
    $this->assertSame(1, $action['action_cost']);
    $this->assertContains('cheek-pouches', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTunnelVisionAddsConditionalPerceptionBonus(): void {
    $character = $this->buildCharacterWithFeat('tunnel-vision');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Perception', $modifier['skill']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('to detect movement in narrow corridors and tunnels', $modifier['context']);
    $this->assertContains('tunnel-vision', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testScroungerAddsRepairAndSubsistBonuses(): void {
    $character = $this->buildCharacterWithFeat('scrounger');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Crafting', $modifier['skill']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('Repair', $modifier['context']);
    $override = $effects['feat_overrides']['scrounger'][0];
    $this->assertSame('subsist_bonus', $override['type']);
    $this->assertSame('settlement', $override['environment']);
    $this->assertContains('scrounger', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCommunalInstinctAddsFearSaveBonusWhenAdjacent(): void {
    $character = $this->buildCharacterWithFeat('communal-instinct');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame('Will', $modifier['save']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('against fear while adjacent to an ally', $modifier['context']);
    $this->assertContains('communal-instinct', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRatfolkWeaponFamiliarityAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('ratfolk-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Ratfolk Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['martial' => 'simple'], $grant['proficiency_remap']);
    $this->assertContains('ratfolk-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTenguLoreAddsSkillAndLoreTraining(): void {
    $character = $this->buildCharacterWithFeat('tengu-lore');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Acrobatics'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['skills']['Deception'] ?? NULL);
    $this->assertContains('Tengu Lore', $effects['training_grants']['lore']);
    $this->assertContains('tengu-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOneToedHopAddsBalanceAndLeapBonus(): void {
    $character = $this->buildCharacterWithFeat('one-toed-hop');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['one-toed-hop'][0];
    $this->assertSame('conditional_check_bonus', $override['type']);
    $this->assertSame(2, $override['bonus']);
    $this->assertSame(['Balance', 'Leap'], $override['checks']);
    $this->assertContains('one-toed-hop', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSquawkAddsDemoralizeAction(): void {
    $character = $this->buildCharacterWithFeat('squawk');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Squawk', $action['name']);
    $this->assertSame('Intimidation', $action['skill']);
    $this->assertSame('Demoralize', $action['activity']);
    $this->assertSame('1_hour', $action['immunity_duration']);
    $this->assertContains('squawk', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSkyBridgeRunnerAddsAcrobaticsBonus(): void {
    $character = $this->buildCharacterWithFeat('sky-bridge-runner');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Acrobatics', $modifier['skill']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('while traversing narrow or elevated surfaces', $modifier['context']);
    $this->assertContains('sky-bridge-runner', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBeakAdeptAddsNaturalAttackAndDisarmBonus(): void {
    $character = $this->buildCharacterWithFeat('beak-adept');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Beak Adept', $action['name']);
    $this->assertSame('natural_attack', $action['attack_type']);
    $override = $effects['feat_overrides']['beak-adept'][0];
    $this->assertSame('natural_attack_enhancement', $override['type']);
    $this->assertSame('beak', $override['attack_form']);
    $this->assertSame(1, $override['disarm_bonus']);
    $this->assertContains('beak-adept', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTenguWeaponFamiliarityAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('tengu-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Tengu Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['martial' => 'simple'], $grant['proficiency_remap']);
    $this->assertContains('tengu-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHoldScarredAddsStealthTrainingAndTerrainStalker(): void {
    $character = $this->buildCharacterWithFeat('hold-scarred');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Stealth'] ?? NULL);
    $override = $effects['feat_overrides']['hold-scarred'][0];
    $this->assertSame('terrain_stalker_grant', $override['type']);
    $this->assertSame('underground', $override['terrain']);
    $this->assertContains('hold-scarred', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrcFerocityAddsSurviveZeroHpOverride(): void {
    $character = $this->buildCharacterWithFeat('orc-ferocity');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('orc-ferocity', $action['id']);
    $override = $effects['feat_overrides']['orc-ferocity'][0];
    $this->assertSame('survive_zero_hp', $override['type']);
    $this->assertSame(1, $override['hp_floor']);
    $this->assertSame(1, $override['wounded_increase']);
    $this->assertContains('orc-ferocity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrcSightAddsDarkvision(): void {
    $character = $this->buildCharacterWithFeat('orc-sight');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('darkvision', $effects['senses'][0]['id']);
    $this->assertContains('orc-sight', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrcSuperstitionAddsMagicSaveBonusAndLimitedUpgrade(): void {
    $character = $this->buildCharacterWithFeat('orc-superstition');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame('Will', $modifier['save']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('spells and magical effects', $modifier['context']);
    $override = $effects['feat_overrides']['orc-superstition'][0];
    $this->assertSame('limited_success_upgrade', $override['type']);
    $this->assertSame('critical_success', $override['to']);
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('orc-superstition-save-upgrade', $action['id']);
    $this->assertContains('orc-superstition', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrcWeaponFamiliarityAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('orc-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Orc Weapons', $grant['group']);
    $this->assertSame(['falchion', 'greataxe'], $grant['examples']);
    $this->assertSame(['martial' => 'simple', 'advanced' => 'martial'], $grant['proficiency_remap']);
    $this->assertContains('orc-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrcWeaponCarnageAddsCritSpecFlag(): void {
    $character = $this->buildCharacterWithFeat('orc-weapon-carnage');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['orc_weapon_carnage_crit_spec']);
    $this->assertContains('orc-weapon-carnage', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDoubleSliceAddsTwoStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('double-slice');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Double Slice', $action['name']);
    $this->assertSame(2, $action['action_cost']);
    $this->assertSame('two_melee_strikes', $action['activity']);
    $this->assertTrue($action['same_target_required']);
    $this->assertTrue($action['combine_damage_for_resistance_and_weakness']);
    $this->assertContains('double-slice', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testExactingStrikeAddsMapControlAction(): void {
    $character = $this->buildCharacterWithFeat('exacting-strike');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Exacting Strike', $action['name']);
    $this->assertSame(1, $action['action_cost']);
    $this->assertSame('melee_strike', $action['activity']);
    $this->assertSame(2, $action['map_attack_count']);
    $this->assertSame('do_not_increase_map', $action['on_failure']);
    $this->assertContains('exacting-strike', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPointBlankShotAddsStanceAction(): void {
    $character = $this->buildCharacterWithFeat('point-blank-shot');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Point-Blank Shot', $action['name']);
    $this->assertTrue($action['stance']);
    $this->assertTrue($action['effects']['ignore_volley_penalty_within_volley_range']);
    $this->assertSame(2, $action['effects']['non_volley_ranged_damage_bonus']);
    $this->assertContains('point-blank-shot', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPowerAttackAddsHeavyStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('power-attack');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Power Attack', $action['name']);
    $this->assertSame(2, $action['action_cost']);
    $this->assertSame(2, $action['map_attack_count']);
    $this->assertSame(1, $action['on_hit_extra_weapon_damage_dice']);
    $this->assertContains('power-attack', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testReactiveShieldAddsReactionTrigger(): void {
    $character = $this->buildCharacterWithFeat('reactive-shield');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Reactive Shield', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('enemy_hits_with_melee_strike', $action['trigger']);
    $this->assertTrue($action['applies_to_triggering_attack']);
    $this->assertContains('reactive-shield', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSnaggingStrikeAddsFlatFootedRider(): void {
    $character = $this->buildCharacterWithFeat('snagging-strike');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Snagging Strike', $action['name']);
    $this->assertSame('melee_strike', $action['activity']);
    $this->assertSame('two_hand_weapon_wielded_in_one_hand', $action['weapon_requirement']);
    $this->assertSame('target_flat_footed_until_start_of_next_turn', $action['on_hit_and_damage']);
    $this->assertContains('snagging-strike', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSuddenChargeAddsMoveThenStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('sudden-charge');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Sudden Charge', $action['name']);
    $this->assertSame(2, $action['action_cost']);
    $this->assertSame('stride_stride_strike', $action['activity']);
    $this->assertSame(2, $action['movement_count']);
    $this->assertSame('melee', $action['followup_strike']);
    $this->assertContains('sudden-charge', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testNimbleDodgeAddsReactionAcBonus(): void {
    $character = $this->buildCharacterWithFeat('nimble-dodge');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Nimble Dodge', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('creature_targets_you_with_attack', $action['trigger']);
    $this->assertTrue($action['requirements']['can_see_attacker']);
    $this->assertSame(2, $action['ac_bonus']);
    $this->assertSame('triggering_attack_only', $action['duration']);
    $this->assertContains('nimble-dodge', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrapFinderAddsTrapBonusesAndOverrides(): void {
    $character = $this->buildCharacterWithFeat('trap-finder');
    $effects = $this->manager->buildEffectState($character);

    $this->assertCount(3, $effects['conditional_modifiers']);
    $this->assertSame('perception', $effects['conditional_modifiers'][0]['type']);
    $this->assertSame('find_traps', $effects['conditional_modifiers'][0]['target']);
    $this->assertSame('ac', $effects['conditional_modifiers'][1]['type']);
    $this->assertSame('attacks_by_traps', $effects['conditional_modifiers'][1]['target']);
    $this->assertSame('saving_throw', $effects['conditional_modifiers'][2]['type']);
    $this->assertSame('effects_from_traps', $effects['conditional_modifiers'][2]['target']);
    $this->assertTrue($effects['feat_overrides']['trap-finder']['can_find_legendary_traps']);
    $this->assertTrue($effects['feat_overrides']['trap-finder']['disable_device_critical_failure_does_not_trigger_trap']);
    $this->assertContains('trap-finder', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTwinFeintAddsSecondStrikeFlatFootedRider(): void {
    $character = $this->buildCharacterWithFeat('twin-feint');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Twin Feint', $action['name']);
    $this->assertSame(1, $action['action_cost']);
    $this->assertSame('two_melee_strikes', $action['activity']);
    $this->assertTrue($action['same_target_required']);
    $this->assertTrue($action['second_attack_target_flat_footed']);
    $this->assertContains('twin-feint', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testYoureNextAddsDemoralizeReaction(): void {
    $character = $this->buildCharacterWithFeat('you-re-next');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('You\'re Next', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('you_reduce_enemy_to_zero_hp', $action['trigger']);
    $this->assertSame('demoralize', $action['activity']);
    $this->assertSame(2, $action['check_bonus']);
    $this->assertTrue($action['target_requirements']['must_perceive_defeated_creature']);
    $this->assertTrue($action['ignore_normal_demoralize_range_limit']);
    $this->assertContains('you-re-next', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCounterspellAddsCounteractReaction(): void {
    $character = $this->buildCharacterWithFeat('counterspell');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Counterspell', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('creature_casts_spell_you_have_prepared', $action['trigger']);
    $this->assertTrue($action['requirements']['can_see_spell_manifestations']);
    $this->assertTrue($action['requirements']['prepared_same_spell']);
    $this->assertTrue($action['expends_prepared_spell_slot']);
    $this->assertSame('attempt_counteract', $action['effect']);
    $this->assertContains('counterspell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEschewMaterialsAddsSpellComponentOverride(): void {
    $character = $this->buildCharacterWithFeat('eschew-materials');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['eschew-materials'];
    $this->assertTrue($override['can_replace_material_components_without_pouch']);
    $this->assertTrue($override['requires_free_hand']);
    $this->assertTrue($override['cost_materials_still_required']);
    $this->assertContains('eschew-materials', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFamiliarAddsCreationSelectionGrant(): void {
    $character = $this->buildCharacterWithFeat('familiar');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('familiar', $grant['source']);
    $this->assertSame('familiar_creation', $grant['id']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('familiar', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHandOfTheApprenticeAddsFocusSpellAction(): void {
    $character = $this->buildCharacterWithFeat('hand-of-the-apprentice');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $override = $effects['feat_overrides']['hand-of-the-apprentice'];
    $this->assertSame('Hand of the Apprentice', $action['name']);
    $this->assertTrue($action['focus_spell']);
    $this->assertSame(1, $action['focus_cost']);
    $this->assertSame('intelligence', $action['uses_attack_ability']);
    $this->assertTrue($action['weapon_returns_after_strike']);
    $this->assertSame(1, $override['grants_focus_pool_if_none']);
    $this->assertSame('study_spellbook', $override['refocus_requirement']);
    $this->assertContains('hand-of-the-apprentice', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testReachSpellAddsNextSpellMetamagicAugment(): void {
    $character = $this->buildCharacterWithFeat('reach-spell');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Reach Spell', $augment['name']);
    $this->assertSame(30, $augment['range_bonus_feet']);
    $this->assertSame(30, $augment['touch_range_to_feet']);
    $this->assertTrue($augment['applies_to_next_spell_only']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertTrue($action['applies_to_next_spell_only']);
    $this->assertContains('reach-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWidenSpellAddsShapeSpecificMetamagicAugment(): void {
    $character = $this->buildCharacterWithFeat('widen-spell');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Widen Spell', $augment['name']);
    $this->assertSame(['burst', 'cone', 'line'], $augment['eligible_shapes']);
    $this->assertTrue($augment['applies_to_next_spell_only']);
    $this->assertSame(5, $augment['burst_radius_bonus_feet']);
    $this->assertSame(10, $augment['long_cone_or_line_bonus_feet']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('widen-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testConcealSpellAddsSubtleMetamagicAugment(): void {
    $character = $this->buildCharacterWithFeat('conceal-spell');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Conceal Spell', $augment['name']);
    $this->assertTrue($augment['grants_subtle_trait']);
    $this->assertSame('perception_vs_arcana_dc', $augment['observers_notice_via']);
    $this->assertTrue($action['applies_to_next_spell_only']);
    $this->assertContains('conceal-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEnhancedFamiliarAddsAbilityAndHpOverrides(): void {
    $character = $this->buildCharacterWithFeat('enhanced-familiar');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['enhanced-familiar'];
    $this->assertSame(2, $override['additional_familiar_abilities_per_day']);
    $this->assertSame('intelligence_modifier', $override['familiar_hp_bonus']);
    $this->assertContains('enhanced-familiar', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testNonlethalSpellAddsDamageConversionMetamagicAugment(): void {
    $character = $this->buildCharacterWithFeat('nonlethal-spell');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Nonlethal Spell', $augment['name']);
    $this->assertTrue($augment['converts_damage_to_nonlethal']);
    $this->assertTrue($augment['requires_damage_spell']);
    $this->assertTrue($augment['does_not_apply_to_already_nonlethal_spells']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('nonlethal-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBespellWeaponAddsNextStrikeDamageOverride(): void {
    $character = $this->buildCharacterWithFeat('bespell-weapon');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['bespell-weapon'][0];
    $this->assertSame('spell_strike_damage_bonus', $override['type']);
    $this->assertSame('cast_non_cantrip_arcane_spell', $override['trigger']);
    $this->assertSame('until_end_of_current_turn', $override['duration']);
    $this->assertSame('next_strike_with_held_weapon', $override['applies_to']);
    $this->assertSame('1d6', $override['bonus_dice']);
    $this->assertSame('spell_trait_or_force', $override['damage_type_source']);
    $this->assertContains('bespell-weapon', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLinkedFocusAddsSpellSlotFocusRecoveryOverride(): void {
    $character = $this->buildCharacterWithFeat('linked-focus');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['linked-focus'];
    $this->assertTrue($override['recover_focus_point_on_arcane_spell_slot_cast']);
    $this->assertSame(1, $override['focus_recovery_limit_per_round']);
    $this->assertTrue($override['cannot_exceed_focus_pool_max']);
    $this->assertContains('linked-focus', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSpellPenetrationAddsSaveAndCounteractPenalties(): void {
    $character = $this->buildCharacterWithFeat('spell-penetration-feat');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['spell-penetration-feat'];
    $this->assertSame(-2, $override['saving_throw_penalty_against_your_spells']);
    $this->assertSame(-2, $override['counteract_penalty_against_your_spells']);
    $this->assertSame('circumstance', $override['penalty_type']);
    $this->assertContains('spell-penetration-feat', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSteadySpellcastingAddsAntiDisruptionFlatCheck(): void {
    $character = $this->buildCharacterWithFeat('steady-spellcasting-wizard');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['steady-spellcasting-wizard'];
    $this->assertSame('reaction_or_free_action_would_disrupt_spellcasting', $override['trigger']);
    $this->assertSame(15, $override['flat_check_dc']);
    $this->assertTrue($override['success_prevents_disruption']);
    $this->assertContains('steady-spellcasting-wizard', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOverwhelmingEnergyAddsResistanceBypassMetamagic(): void {
    $character = $this->buildCharacterWithFeat('overwhelming-energy-wizard');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Overwhelming Energy', $augment['name']);
    $this->assertTrue($augment['applies_to_next_spell_only']);
    $this->assertTrue($augment['requires_energy_damage_spell']);
    $this->assertSame(-5, $augment['resistance_penalty']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('overwhelming-energy-wizard', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testQuickenedCastingAddsDailyActionReduction(): void {
    $character = $this->buildCharacterWithFeat('quickened-casting-wizard');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['quickened-casting-wizard'];
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertTrue($override['applies_to_next_spell_only']);
    $this->assertSame('arcane', $override['spell_tradition']);
    $this->assertSame(2, $override['required_normal_action_cost']);
    $this->assertSame(1, $override['reduced_action_cost']);
    $this->assertSame('Quickened Casting', $action['name']);
    $this->assertContains('quickened-casting-wizard', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCleverCounterspellRelaxesPreparedSpellRequirement(): void {
    $character = $this->buildCharacterWithFeat('clever-counterspell');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['clever-counterspell'];
    $this->assertSame('counterspell', $override['modifies_feat']);
    $this->assertSame('same_or_higher_rank_from_spellbook', $override['prepared_spell_requirement']);
    $this->assertTrue($override['expended_spell_must_be_prepared']);
    $this->assertContains('clever-counterspell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMagicSenseAddsConstantDetectMagicSense(): void {
    $character = $this->buildCharacterWithFeat('magic-sense');
    $effects = $this->manager->buildEffectState($character);

    $sense = $effects['senses'][0];
    $this->assertSame('detect_magic', $sense['type']);
    $this->assertSame(30, $sense['radius_feet']);
    $this->assertTrue($sense['always_on']);
    $this->assertTrue($sense['details_limited']);
    $this->assertContains('magic-sense', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testReflectSpellAddsCounterspellReflectionOverride(): void {
    $character = $this->buildCharacterWithFeat('reflect-spell');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['reflect-spell'];
    $this->assertSame('counterspell', $override['modifies_feat']);
    $this->assertSame('successful_counterspell', $override['trigger']);
    $this->assertSame('redirect_spell_to_original_caster', $override['effect']);
    $this->assertContains('reflect-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEffortlessConcentrationAddsFreeSustainMetamagic(): void {
    $character = $this->buildCharacterWithFeat('effortless-concentration');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Effortless Concentration', $augment['name']);
    $this->assertTrue($augment['grants_sustain_duration']);
    $this->assertSame('free', $augment['sustain_action_cost']);
    $this->assertTrue($augment['applies_once_per_spell']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('effortless-concentration', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCrossbowAceAddsReloadOverrides(): void {
    $character = $this->buildCharacterWithFeat('crossbow-ace');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['crossbow-ace'];
    $this->assertTrue($override['quick_draw_also_reloads_crossbow']);
    $this->assertFalse($override['loaded_crossbow_reload_requires_free_hand_draw']);
    $this->assertContains('crossbow-ace', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHuntedShotAddsTwoStrikePreyAction(): void {
    $character = $this->buildCharacterWithFeat('hunted-shot');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Hunted Shot', $action['name']);
    $this->assertSame('two_ranged_strikes', $action['activity']);
    $this->assertSame('hunted_prey', $action['target_requirement']);
    $this->assertTrue($action['volley_weapons_reduce_to_one_strike']);
    $this->assertTrue($action['combine_damage_for_resistance_and_weakness_on_two_hits']);
    $this->assertSame(2, $action['map_attack_count']);
    $this->assertContains('hunted-shot', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTwinTakedownAddsDifferentWeaponDifferentTargetAction(): void {
    $character = $this->buildCharacterWithFeat('twin-takedown');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Twin Takedown', $action['name']);
    $this->assertSame('two_melee_strikes', $action['activity']);
    $this->assertTrue($action['different_targets_required']);
    $this->assertTrue($action['second_strike_uses_normal_map']);
    $this->assertTrue($action['double_slice_damage_rule']);
    $this->assertContains('twin-takedown', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBardicLoreAddsUniversalLoreOverrides(): void {
    $character = $this->buildCharacterWithFeat('bardic-lore');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['bardic-lore'];
    $this->assertTrue($override['can_attempt_lore_on_any_topic']);
    $this->assertTrue($override['uses_occultism_proficiency_for_bardic_lore_dc']);
    $this->assertTrue($override['roll_twice_take_better_on_lore_checks']);
    $this->assertContains('bardic-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLingeringCompositionAddsPerformanceExtensionAction(): void {
    $character = $this->buildCharacterWithFeat('lingering-composition');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Lingering Composition', $action['name']);
    $this->assertSame('performance_check_composition_extension', $action['activity']);
    $this->assertSame('extend_to_4_rounds', $action['outcomes']['critical_success']);
    $this->assertSame('immediately_ends', $action['outcomes']['critical_failure']);
    $this->assertContains('lingering-composition', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVersatilePerformanceAddsSkillSubstitutionsAndDailySwap(): void {
    $character = $this->buildCharacterWithFeat('versatile-performance');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['versatile-performance'];
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Performance', $override['skill_substitutions']['make_an_impression']);
    $this->assertSame('Performance', $override['skill_substitutions']['lie']);
    $this->assertSame('Performance', $override['skill_substitutions']['demoralize']);
    $this->assertSame(1, $override['signature_spell_swap_uses_per_long_rest']);
    $this->assertSame('Versatile Performance Signature Swap', $action['name']);
    $this->assertContains('versatile-performance', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInspireCompetenceAddsCompositionCantripAction(): void {
    $character = $this->buildCharacterWithFeat('inspire-competence');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Inspire Competence', $action['name']);
    $this->assertSame('free', $action['action_cost']);
    $this->assertSame('composition_cantrip', $action['activity']);
    $this->assertSame(60, $action['range_feet']);
    $this->assertSame(2, $action['skill_check_status_bonus']);
    $this->assertSame('up_to_1_minute', $action['sustain_duration']);
    $this->assertContains('inspire-competence', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMelodiousSpellAddsAuditoryMetamagicAugment(): void {
    $character = $this->buildCharacterWithFeat('melodious-spell');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Melodious Spell', $augment['name']);
    $this->assertSame('manipulate', $augment['remove_trait']);
    $this->assertSame('auditory', $augment['add_trait']);
    $this->assertTrue($augment['somatic_components_do_not_require_free_hand']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('melodious-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTripleTimeAddsSpeedCompositionAction(): void {
    $character = $this->buildCharacterWithFeat('triple-time');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Triple Time', $action['name']);
    $this->assertSame('free', $action['action_cost']);
    $this->assertSame(10, $action['speed_status_bonus']);
    $this->assertSame('while_sustained_up_to_1_minute', $action['sustain_duration']);
    $this->assertContains('triple-time', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVersatileSignatureAddsDailySwapAction(): void {
    $character = $this->buildCharacterWithFeat('versatile-signature');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['versatile-signature'];
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame(1, $override['signature_spell_swap_uses_per_long_rest']);
    $this->assertSame('daily_preparations', $override['swap_timing']);
    $this->assertSame('Versatile Signature', $action['name']);
    $this->assertContains('versatile-signature', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDirgeOfDoomAddsFearAuraComposition(): void {
    $character = $this->buildCharacterWithFeat('dirge-of-doom');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Dirge of Doom', $action['name']);
    $this->assertSame(30, $action['range_feet']);
    $this->assertSame('frightened_1', $action['condition']);
    $this->assertTrue($action['reapplies_each_turn_in_aura']);
    $this->assertContains('dirge-of-doom', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHarmonizeAddsCompositionCoexistenceMetamagic(): void {
    $character = $this->buildCharacterWithFeat('harmonize');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertTrue($augment['requires_composition_spell']);
    $this->assertTrue($augment['next_composition_does_not_end_existing_composition']);
    $this->assertTrue($augment['allows_two_active_compositions']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('harmonize', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSteadySpellcastingAddsDisruptionFlatCheck(): void {
    $character = $this->buildCharacterWithFeat('steady-spellcasting');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['steady-spellcasting'];
    $this->assertSame('reaction_would_disrupt_spellcasting', $override['trigger']);
    $this->assertSame(15, $override['flat_check_dc']);
    $this->assertTrue($override['success_prevents_disruption']);
    $this->assertContains('steady-spellcasting', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInspireDefenseAddsDefensiveCompositionAction(): void {
    $character = $this->buildCharacterWithFeat('inspire-defense');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Inspire Defense', $action['name']);
    $this->assertSame(60, $action['range_feet']);
    $this->assertSame(1, $action['ac_status_bonus']);
    $this->assertSame(1, $action['saving_throw_status_bonus']);
    $this->assertSame('while_sustained', $action['sustain_duration']);
    $this->assertContains('inspire-defense', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInspireHeroicsAddsCompositionBoostMetamagic(): void {
    $character = $this->buildCharacterWithFeat('inspire-heroics');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame(['inspire-courage', 'inspire-competence'], $augment['eligible_spells']);
    $this->assertSame('performance_vs_composition_dc', $augment['check']);
    $this->assertSame(1, $augment['success_bonus_increase']);
    $this->assertSame(2, $augment['critical_success_bonus_increase']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('inspire-heroics', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHouseOfImaginaryWallsAddsIllusoryWallAction(): void {
    $character = $this->buildCharacterWithFeat('house-of-imaginary-walls');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('House of Imaginary Walls', $action['name']);
    $this->assertSame(10, $action['wall_length_feet']);
    $this->assertSame('adjacent', $action['placement']);
    $this->assertSame('will', $action['save_type']);
    $this->assertSame('treat_wall_as_solid_barrier_for_1_round', $action['on_failed_save']);
    $this->assertContains('house-of-imaginary-walls', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testQuickenedCastingBardAddsDailyOccultSpeedup(): void {
    $character = $this->buildCharacterWithFeat('quickened-casting-bard');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['quickened-casting-bard'];
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('occult', $override['spell_tradition']);
    $this->assertSame([1, 2], $override['eligible_normal_action_costs']);
    $this->assertSame(1, $override['action_cost_reduction']);
    $this->assertTrue($override['excludes_10th_rank_slots']);
    $this->assertSame('Quickened Casting', $action['name']);
    $this->assertContains('quickened-casting-bard', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEclecticSkillAddsUniversalTrainingOverrides(): void {
    $character = $this->buildCharacterWithFeat('eclectic-skill');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['eclectic-skill'];
    $this->assertTrue($override['treat_all_skills_as_trained']);
    $this->assertTrue($override['use_versatile_performance_when_untrained']);
    $this->assertTrue($override['untrained_improvisation_reduces_non_lore_skill_dcs']);
    $this->assertContains('eclectic-skill', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSoothingBalladAddsHealingFocusSpell(): void {
    $character = $this->buildCharacterWithFeat('soothing-ballad');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $override = $effects['feat_overrides']['soothing-ballad'];
    $this->assertSame('Soothing Ballad', $action['name']);
    $this->assertSame('focus_spell', $action['activity']);
    $this->assertSame(30, $action['range_feet']);
    $this->assertSame('1d8 + charisma_modifier', $action['healing_formula']);
    $this->assertTrue($action['fear_counteract_effect']);
    $this->assertSame(1, $override['focus_cost']);
    $this->assertContains('soothing-ballad', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUnusualCompositionAddsTriggerSwapMetamagic(): void {
    $character = $this->buildCharacterWithFeat('unusual-composition');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertTrue($augment['requires_composition_spell']);
    $this->assertTrue($augment['can_swap_visual_and_auditory_triggers']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('unusual-composition', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInspireMagnificenceAddsMagicResistantCompositionAura(): void {
    $character = $this->buildCharacterWithFeat('inspire-magnificence');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Inspire Magnificence', $action['name']);
    $this->assertSame(60, $action['range_feet']);
    $this->assertSame(2, $action['skill_check_status_bonus']);
    $this->assertSame(2, $action['saves_against_magic_status_bonus']);
    $this->assertSame(3, $action['critical_sustain_bonus']);
    $this->assertContains('inspire-magnificence', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPolymathGreaterExtendsVersatilePerformanceToAllSkills(): void {
    $character = $this->buildCharacterWithFeat('polymath-greater');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['polymath-greater'];
    $this->assertTrue($override['versatile_performance_applies_to_any_skill_check']);
    $this->assertContains('polymath-greater', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAllegroAddsReflexAndFreeStepComposition(): void {
    $character = $this->buildCharacterWithFeat('allegro');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Allegro', $action['name']);
    $this->assertSame('one_ally', $action['targets']);
    $this->assertSame(1, $action['reflex_status_bonus']);
    $this->assertTrue($action['free_step_once_per_turn']);
    $this->assertContains('allegro', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSharedAssaultAddsFollowupFlatFootedOverride(): void {
    $character = $this->buildCharacterWithFeat('shared-assault');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['shared-assault'];
    $this->assertSame(['inspire-courage', 'inspire-defense'], $override['requires_active_composition']);
    $this->assertSame('critical_success_occult_spell_attack', $override['trigger']);
    $this->assertSame('target_flat_footed_to_next_strike_from_benefiting_ally', $override['effect']);
    $this->assertContains('shared-assault', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDeepLoreAddsOccultismAndLoreBonuses(): void {
    $character = $this->buildCharacterWithFeat('deep-lore');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['deep-lore'];
    $skill_modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertTrue($override['bardic_lore_identify_spells_via_occultism']);
    $this->assertTrue($override['bardic_lore_identify_creatures_via_occultism']);
    $this->assertTrue($override['bardic_lore_identify_magic_items_via_occultism']);
    $this->assertSame('Lore', $skill_modifier['skill']);
    $this->assertSame(2, $skill_modifier['bonus']);
    $this->assertContains('deep-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHealingHandsAddsHealBonusOverride(): void {
    $character = $this->buildCharacterWithFeat('healing-hands');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['healing-hands'];
    $this->assertTrue($override['heal_spell_bonus_healing_equals_level']);
    $this->assertTrue($override['applies_to_divine_font_and_regular_slots']);
    $this->assertTrue($override['three_action_heal_applies_bonus_to_each_target']);
    $this->assertContains('healing-hands', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHolyCastigationAddsUndeadHealDamageOverride(): void {
    $character = $this->buildCharacterWithFeat('holy-castigation');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['holy-castigation'];
    $this->assertSame('1d6', $override['heal_also_damages_undead']);
    $this->assertTrue($override['ignores_undead_harm_resistance']);
    $this->assertContains('holy-castigation', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testReachSpellClericAddsNextSpellRangeAugment(): void {
    $character = $this->buildCharacterWithFeat('reach-spell-cleric');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame(30, $augment['range_bonus_feet']);
    $this->assertSame(30, $augment['touch_range_to_feet']);
    $this->assertTrue($augment['applies_to_next_spell_only']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('reach-spell-cleric', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWidenSpellClericAddsShapeSpecificAreaAugment(): void {
    $character = $this->buildCharacterWithFeat('widen-spell-cleric');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame(['burst', 'cone', 'line'], $augment['eligible_shapes']);
    $this->assertTrue($augment['applies_to_next_spell_only']);
    $this->assertSame(5, $augment['burst_radius_bonus_feet']);
    $this->assertSame(10, $augment['long_cone_or_line_bonus_feet']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('widen-spell-cleric', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCommunalHealingAddsSelfRecoveryOverride(): void {
    $character = $this->buildCharacterWithFeat('communal-healing');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['communal-healing'];
    $this->assertSame('single_target_heal_on_living_creature_not_self', $override['trigger']);
    $this->assertTrue($override['regain_hp_equal_to_spell_lowest_damage_die']);
    $this->assertContains('communal-healing', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSapLifeAddsHarmRecoveryOverride(): void {
    $character = $this->buildCharacterWithFeat('sap-life');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['sap-life'];
    $this->assertSame('harm_spell_damages_at_least_one_creature', $override['trigger']);
    $this->assertTrue($override['regain_hp_equal_to_spell_level']);
    $this->assertContains('sap-life', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDangerousSorceryAddsSpellRankDamageBonusOverride(): void {
    $character = $this->buildCharacterWithFeat('dangerous-sorcery');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['dangerous-sorcery'];
    $this->assertSame('cast_damaging_spell_from_spell_slot_without_duration', $override['trigger']);
    $this->assertTrue($override['damage_bonus_equals_spell_rank']);
    $this->assertContains('dangerous-sorcery', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFamiliarSorcererAddsCreationSelectionGrant(): void {
    $character = $this->buildCharacterWithFeat('familiar-sorcerer');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('familiar-sorcerer', $grant['source']);
    $this->assertSame('familiar_creation', $grant['id']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('familiar-sorcerer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testReachSpellSorcererAddsNextSpellRangeAugment(): void {
    $character = $this->buildCharacterWithFeat('reach-spell-sorcerer');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame(30, $augment['range_bonus_feet']);
    $this->assertSame(30, $augment['touch_range_to_feet']);
    $this->assertTrue($augment['applies_to_next_spell_only']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('reach-spell-sorcerer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWidenSpellSorcererAddsShapeSpecificAreaAugment(): void {
    $character = $this->buildCharacterWithFeat('widen-spell-sorcerer');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame(['burst', 'cone', 'line'], $augment['eligible_shapes']);
    $this->assertTrue($augment['applies_to_next_spell_only']);
    $this->assertSame(5, $augment['burst_radius_bonus_feet']);
    $this->assertSame(10, $augment['long_cone_or_line_bonus_feet']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('widen-spell-sorcerer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEnhancedFamiliarSorcererAddsExtraAbilitiesOverride(): void {
    $character = $this->buildCharacterWithFeat('enhanced-familiar-sorcerer');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['enhanced-familiar-sorcerer'];
    $this->assertSame(2, $override['additional_familiar_abilities_per_day']);
    $this->assertContains('enhanced-familiar-sorcerer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSteadySpellcastingSorcererAddsDisruptionFlatCheck(): void {
    $character = $this->buildCharacterWithFeat('steady-spellcasting-sorcerer');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['steady-spellcasting-sorcerer'];
    $this->assertSame('reaction_would_disrupt_spellcasting', $override['trigger']);
    $this->assertSame(15, $override['flat_check_dc']);
    $this->assertTrue($override['success_prevents_disruption']);
    $this->assertContains('steady-spellcasting-sorcerer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInstinctiveObfuscationAddsSpellMisdirectionReaction(): void {
    $character = $this->buildCharacterWithFeat('instinctive-obfuscation');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Instinctive Obfuscation', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('creature_targets_you_with_spell', $action['trigger']);
    $this->assertSame('misdirect_spell', $action['activity']);
    $this->assertSame('deception_vs_caster_perception_dc', $action['check']);
    $this->assertTrue($action['requires_alternate_target_in_range']);
    $this->assertContains('instinctive-obfuscation', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOverwhelmingEnergySorcererAddsResistanceIgnoreMetamagic(): void {
    $character = $this->buildCharacterWithFeat('overwhelming-energy');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $action = $effects['available_actions']['at_will'][0];
    $this->assertTrue($augment['requires_energy_damage_spell']);
    $this->assertSame(10, $augment['ignore_resistance_up_to']);
    $this->assertSame(['acid', 'cold', 'electricity', 'fire', 'sonic'], $augment['eligible_damage_types']);
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('overwhelming-energy', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testQuickenedCastingSorcererAddsDailyLowRankSpeedup(): void {
    $character = $this->buildCharacterWithFeat('quickened-casting-sorcerer');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['quickened-casting-sorcerer'];
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame(3, $override['spell_level_max']);
    $this->assertSame(1, $override['action_cost_reduction']);
    $this->assertSame(1, $override['minimum_action_cost']);
    $this->assertTrue($override['cannot_apply_to_already_reduced_casting_time']);
    $this->assertSame('Quickened Casting', $action['name']);
    $this->assertContains('quickened-casting-sorcerer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCatfolkLoreAddsSkillAndLoreTraining(): void {
    $character = $this->buildCharacterWithFeat('catfolk-lore');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('trained', $effects['training_grants']['skills']['Acrobatics'] ?? NULL);
    $this->assertSame('trained', $effects['training_grants']['skills']['Stealth'] ?? NULL);
    $this->assertContains('Catfolk Lore', $effects['training_grants']['lore']);
    $this->assertContains('catfolk-lore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCatfolkWeaponFamiliarityAddsWeaponTrainingAndRemap(): void {
    $character = $this->buildCharacterWithFeat('catfolk-weapon-familiarity');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Catfolk Weapons', $grant['group']);
    $this->assertSame('trained', $grant['proficiency']);
    $this->assertSame(['martial' => 'simple'], $grant['proficiency_remap']);
    $this->assertContains('catfolk-weapon-familiarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGracefulStepAddsAcrobaticsModifier(): void {
    $character = $this->buildCharacterWithFeat('graceful-step');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Acrobatics', $modifier['skill']);
    $this->assertSame(2, $modifier['bonus']);
    $this->assertSame('circumstance', $modifier['bonus_type']);
    $this->assertSame('Balance and Tumble Through', $modifier['context']);
    $this->assertContains('graceful-step', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFelineEyesAddsVisionSenseAndDimLightBonus(): void {
    $character = $this->buildCharacterWithFeat('feline-eyes');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('low-light-vision', $effects['senses'][0]['id']);
    $override = $effects['feat_overrides']['feline-eyes'][0];
    $this->assertSame('conditional_check_bonus', $override['type']);
    $this->assertSame(1, $override['bonus']);
    $this->assertSame('checks_relying_on_sight', $override['check_scope']);
    $this->assertSame('dim', $override['lighting']);
    $this->assertContains('feline-eyes', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWellGroomedAddsDiplomacyMakeImpressionBonus(): void {
    $character = $this->buildCharacterWithFeat('well-groomed');
    $effects = $this->manager->buildEffectState($character);

    $modifier = $effects['conditional_modifiers']['skills'][0];
    $this->assertSame('Diplomacy', $modifier['skill']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('Make an Impression where appearance matters', $modifier['context']);
    $override = $effects['feat_overrides']['well-groomed'][0];
    $this->assertSame('activity_bonus', $override['type']);
    $this->assertSame('Make an Impression', $override['activity']);
    $this->assertSame('social settings where appearance matters', $override['context']);
    $this->assertContains('well-groomed', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCatNapAddsRestEfficiencyOverrides(): void {
    $character = $this->buildCharacterWithFeat('cat-nap');
    $effects = $this->manager->buildEffectState($character);

    $light_rest = $effects['feat_overrides']['cat-nap'][0];
    $this->assertSame('rest_efficiency', $light_rest['type']);
    $this->assertSame('light_rest', $light_rest['rest_type']);
    $this->assertTrue($light_rest['reduced_downtime']);

    $short_rest = $effects['feat_overrides']['cat-nap'][1];
    $this->assertSame('rest_efficiency', $short_rest['type']);
    $this->assertSame('short_rest', $short_rest['rest_type']);
    $this->assertTrue($short_rest['improved_recovery']);
    $this->assertContains('cat-nap', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHalflingWeaponExpertiseCascadesClassWeaponRank(): void {
    $character = [
      'feats' => [
        ['id' => 'halfling-weapon-familiarity'],
        ['id' => 'halfling-weapon-expertise'],
      ],
      'level' => 13,
      'class_features' => [
        ['id' => 'wizard-weapon-expertise'],
      ],
    ];
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['weapons'][0];
    $this->assertSame('Halfling Weapons', $grant['group']);
    $this->assertSame('expert', $grant['proficiency']);
    $this->assertSame(['sling', 'halfling sling staff', 'shortsword'], $grant['specific_weapons']);
    $this->assertSame('expert', $effects['derived_adjustments']['flags']['halfling_weapon_expertise_cascade_rank']);
    $this->assertContains('halfling-weapon-expertise', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUnwaveringMienAddsMentalSaveUpgrade(): void {
    $character = $this->buildCharacterWithFeat('unwavering-mien');
    $effects = $this->manager->buildEffectState($character);

    $upgrade = $effects['conditional_modifiers']['outcome_upgrades'][0];
    $this->assertSame('unwavering-mien', $upgrade['id']);
    $this->assertSame('saving_throw', $upgrade['target']);
    $this->assertSame('success', $upgrade['from']);
    $this->assertSame('critical_success', $upgrade['to']);
    $this->assertSame('mental effects', $upgrade['context']);
    $this->assertContains('unwavering-mien', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testNaturalAmbitionPromotesSelectedBonusClassFeat(): void {
    $character = $this->buildCharacterWithFeatSelection('natural-ambition', [
      'bonus_class_feat' => 'power-attack',
    ], [
      'class_feat' => 'reactive-shield',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertContains('natural-ambition', $effects['applied_feats']);
    $this->assertContains('power-attack', $effects['applied_feats']);
  }

  // ---------------------------------------------------------------------------
  // TC-FEAT-03: Recognize Spell — auto-identify thresholds and crit descriptors
  // ---------------------------------------------------------------------------

  /**
   * @covers ::buildEffectState
   */
  public function testRecognizeSpellRegistersAutoIdentifyThresholds(): void {
    $character = $this->buildCharacterWithFeat('recognize-spell');
    $effects = $this->manager->buildEffectState($character);

    $action = NULL;
    foreach ($effects['available_actions']['at_will'] as $a) {
      if (($a['id'] ?? '') === 'recognize-spell') {
        $action = $a;
        break;
      }
    }

    $this->assertNotNull($action, 'recognize-spell at_will action should be registered');
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertIsArray($action['auto_identify_thresholds']);
    $this->assertCount(4, $action['auto_identify_thresholds']);
    $this->assertSame(2, $action['auto_identify_thresholds'][1]);
    $this->assertSame(4, $action['auto_identify_thresholds'][2]);
    $this->assertSame(6, $action['auto_identify_thresholds'][3]);
    $this->assertSame(10, $action['auto_identify_thresholds'][4]);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRecognizeSpellHasCritOutcomeDescriptors(): void {
    $character = $this->buildCharacterWithFeat('recognize-spell');
    $effects = $this->manager->buildEffectState($character);

    $action = NULL;
    foreach ($effects['available_actions']['at_will'] as $a) {
      if (($a['id'] ?? '') === 'recognize-spell') {
        $action = $a;
        break;
      }
    }

    $this->assertNotNull($action);
    $this->assertNotEmpty($action['crit_success_effect']);
    $this->assertNotEmpty($action['crit_failure_effect']);
  }

  // ---------------------------------------------------------------------------
  // TC-FEAT-04: Trick Magic Item — tradition-skill map, fallback DC, crit-fail lockout
  // ---------------------------------------------------------------------------

  /**
   * @covers ::buildEffectState
   */
  public function testTrickMagicItemRegistersAtWillActionWithTraditionSkillMap(): void {
    $character = $this->buildCharacterWithFeat('trick-magic-item');
    $effects = $this->manager->buildEffectState($character);

    $action = NULL;
    foreach ($effects['available_actions']['at_will'] as $a) {
      if (($a['id'] ?? '') === 'trick-magic-item') {
        $action = $a;
        break;
      }
    }

    $this->assertNotNull($action, 'trick-magic-item at_will action should be registered');
    $this->assertSame(1, $action['action_cost']);

    $map = $action['tradition_skill_required'];
    $this->assertSame('Arcana', $map['arcane']);
    $this->assertSame('Religion', $map['divine']);
    $this->assertSame('Occultism', $map['occult']);
    $this->assertSame('Nature', $map['primal']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrickMagicItemHasFallbackDcAndCritFailLockout(): void {
    $character = $this->buildCharacterWithFeat('trick-magic-item');
    $effects = $this->manager->buildEffectState($character);

    $action = NULL;
    foreach ($effects['available_actions']['at_will'] as $a) {
      if (($a['id'] ?? '') === 'trick-magic-item') {
        $action = $a;
        break;
      }
    }

    $this->assertNotNull($action);
    $this->assertNotEmpty($action['fallback_dc_formula']);
    $this->assertNotEmpty($action['crit_fail_lockout']);
  }

  // ---------------------------------------------------------------------------
  // TC-FEAT-05: Specialty Crafting — rank-scaled bonus (+1 trained, +2 master)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::buildEffectState
   */
  public function testSpecialtyCraftingAppliesPlusOneWhenTrained(): void {
    $character = array_merge(
      $this->buildCharacterWithFeat('specialty-crafting'),
      ['skills' => ['Crafting' => 'trained']]
    );
    $effects = $this->manager->buildEffectState($character);

    $bonus = 0;
    foreach ($effects['conditional_modifiers']['skills'] as $mod) {
      if (($mod['skill'] ?? '') === 'Crafting') {
        $bonus = $mod['bonus'];
        break;
      }
    }

    $this->assertSame(1, $bonus, 'Specialty Crafting with trained rank should give +1');
    $this->assertTrue($effects['feat_overrides']['specialty-crafting_master_tier_pending'] ?? FALSE);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSpecialtyCraftingAppliesPlusTwoWhenMaster(): void {
    $character = array_merge(
      $this->buildCharacterWithFeat('specialty-crafting'),
      ['skills' => ['Crafting' => 'master']]
    );
    $effects = $this->manager->buildEffectState($character);

    $bonus = 0;
    foreach ($effects['conditional_modifiers']['skills'] as $mod) {
      if (($mod['skill'] ?? '') === 'Crafting') {
        $bonus = $mod['bonus'];
        break;
      }
    }

    $this->assertSame(2, $bonus, 'Specialty Crafting with master rank should give +2');
    $this->assertArrayNotHasKey('specialty-crafting_master_tier_pending', $effects['feat_overrides'] ?? []);
  }

  // ---------------------------------------------------------------------------
  // TC-FEAT-06: Virtuosic Performer — rank-scaled bonus (+1 trained, +2 master)
  // ---------------------------------------------------------------------------

  /**
   * @covers ::buildEffectState
   */
  public function testVirtuosicPerformerAppliesPlusOneWhenTrained(): void {
    $character = array_merge(
      $this->buildCharacterWithFeat('virtuosic-performer'),
      ['skills' => ['Performance' => 'trained']]
    );
    $effects = $this->manager->buildEffectState($character);

    $bonus = 0;
    foreach ($effects['conditional_modifiers']['skills'] as $mod) {
      if (($mod['skill'] ?? '') === 'Performance') {
        $bonus = $mod['bonus'];
        break;
      }
    }

    $this->assertSame(1, $bonus, 'Virtuosic Performer with trained rank should give +1');
    $this->assertTrue($effects['feat_overrides']['virtuosic-performer_master_tier_pending'] ?? FALSE);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVirtuosicPerformerAppliesPlusTwoWhenMaster(): void {
    $character = array_merge(
      $this->buildCharacterWithFeat('virtuosic-performer'),
      ['skills' => ['Performance' => 'master']]
    );
    $effects = $this->manager->buildEffectState($character);

    $bonus = 0;
    foreach ($effects['conditional_modifiers']['skills'] as $mod) {
      if (($mod['skill'] ?? '') === 'Performance') {
        $bonus = $mod['bonus'];
        break;
      }
    }

    $this->assertSame(2, $bonus, 'Virtuosic Performer with master rank should give +2');
    $this->assertArrayNotHasKey('virtuosic-performer_master_tier_pending', $effects['feat_overrides'] ?? []);
  }

}
