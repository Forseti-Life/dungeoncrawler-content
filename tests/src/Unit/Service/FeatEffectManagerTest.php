<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CharacterManager;
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

  /**
   * Build a leveled-character payload using features.feats + feat_params shape.
   */
  private function buildLeveledCharacterWithFeatParams(string $feat_id, array $feat_params, array $extra_character = []): array {
    return array_merge([
      'level' => 3,
      'features' => [
        'feats' => [[
          'id' => $feat_id,
          'feat_params' => $feat_params,
        ]],
      ],
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
  public function testMonsterHunterAddsChosenCreatureBonuses(): void {
    $character = $this->buildCharacterWithFeatSelection('monster-hunter', [
      'selected_monster_type' => 'undead',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['monster-hunter'];
    $this->assertSame('undead', $override['chosen_creature_trait']);
    $this->assertSame(2, $override['recall_knowledge_bonus']);
    $this->assertSame(2, $override['investigation_bonus']);
    $this->assertSame('recall_knowledge', $effects['modifiers']['skills'][0]['skill']);
    $this->assertSame('investigation', $effects['modifiers']['skills'][1]['skill']);
    $this->assertContains('monster-hunter', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMonsterHunterAddsPendingSelectionWhenCreatureTypeMissing(): void {
    $character = $this->buildCharacterWithFeat('monster-hunter');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('monster-hunter', $grant['source_feat']);
    $this->assertSame('monster_type_choice', $grant['selection_type']);
    $this->assertContains('monster-hunter', $effects['applied_feats']);
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

    $modifiers = [];
    foreach ($effects['conditional_modifiers'] as $modifier) {
      if (is_array($modifier) && isset($modifier['type'], $modifier['target'])) {
        $modifiers[] = $modifier;
      }
    }

    $this->assertCount(3, $modifiers);
    $this->assertSame('perception', $modifiers[0]['type']);
    $this->assertSame('find_traps', $modifiers[0]['target']);
    $this->assertSame('ac', $modifiers[1]['type']);
    $this->assertSame('attacks_by_traps', $modifiers[1]['target']);
    $this->assertSame('saving_throw', $modifiers[2]['type']);
    $this->assertSame('effects_from_traps', $modifiers[2]['target']);
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
  public function testEldritchTricksterRequestsDedicationSelectionWhenMissing(): void {
    $character = $this->buildCharacterWithFeat('eldritch-trickster-racket');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants']['eldritch-trickster-racket'];
    $this->assertSame('single', $grant['type']);
    $this->assertTrue($grant['required']);
    $this->assertArrayHasKey('wizard-dedication', $grant['options']);
    $this->assertTrue($effects['feat_overrides']['eldritch-trickster-racket']['selection_pending']);
    $this->assertContains('eldritch-trickster-racket', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEldritchTricksterPersistsSelectedDedicationMetadata(): void {
    $character = $this->buildCharacterWithFeatSelection('eldritch-trickster-racket', [
      'selected_dedication' => 'wizard-dedication',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['eldritch-trickster-racket'];
    $this->assertSame('wizard-dedication', $override['selected_dedication']);
    $this->assertSame('Wizard Dedication', $override['selected_dedication_name']);
    $this->assertSame('wizard', $override['selected_dedication_source_class']);
    $this->assertSame('arcane', $override['selected_dedication_tradition']);
    $this->assertTrue($override['grants_free_dedication']);
    $this->assertContains('eldritch-trickster-racket', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMastermindRequestsKnowledgeSkillSelectionWhenMissing(): void {
    $character = $this->buildCharacterWithFeat('mastermind-racket');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants']['mastermind-racket'];
    $this->assertSame('single', $grant['type']);
    $this->assertTrue($grant['required']);
    $this->assertArrayHasKey('arcana', $grant['options']);
    $this->assertSame(['Society'], $effects['training_grants']['skills']);
    $this->assertTrue($effects['feat_overrides']['mastermind-racket']['selection_pending']);
    $this->assertContains('mastermind-racket', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMastermindPersistsKnowledgeSkillAndRecallKnowledgeMetadata(): void {
    $character = $this->buildCharacterWithFeatSelection('mastermind-racket', [
      'selected_skill' => 'arcana',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['mastermind-racket'];
    $this->assertSame('Society', $override['granted_skill']);
    $this->assertSame('arcana', $override['selected_knowledge_skill']);
    $this->assertSame('Arcana', $override['selected_knowledge_skill_name']);
    $this->assertTrue($override['recall_knowledge_flat_footed_on_success']);
    $this->assertSame(['Society', 'Arcana'], $effects['training_grants']['skills']);
    $combat_advantage = NULL;
    foreach ($effects['conditional_modifiers'] as $modifier) {
      if (is_array($modifier) && (($modifier['type'] ?? NULL) === 'combat_advantage')) {
        $combat_advantage = $modifier;
        break;
      }
    }
    $this->assertNotNull($combat_advantage);
    $this->assertSame('recalled_knowledge_target', $combat_advantage['target']);
    $this->assertContains('mastermind-racket', $effects['applied_feats']);
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
  public function testAnimalCompanionAddsCreationSelectionGrant(): void {
    $character = $this->buildCharacterWithFeat('animal-companion');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('animal-companion', $grant['source_feat']);
    $this->assertSame('animal_companion_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('animal-companion', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAnimalCompanionDoesNotAddSelectionGrantWhenResolved(): void {
    $character = $this->buildCharacterWithFeat('animal-companion');
    $character['feat_selections']['animal-companion'] = [
      'selected_companion_species' => 'wolf',
    ];

    $effects = $this->manager->buildEffectState($character);

    $this->assertSame([], $effects['selection_grants']);
    $this->assertSame('wolf', $effects['feat_overrides']['animal-companion']['selected_companion_species']);
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
  public function testStaffNexusRequestsEmbeddedSpellSelectionWhenMissing(): void {
    $character = $this->buildCharacterWithFeat('staff-nexus');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('staff-nexus', $grant['source_feat']);
    $this->assertSame('staff_nexus_spell_selection', $grant['selection_type']);
    $this->assertSame(2, $grant['count']);
    $this->assertContains('staff-nexus', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testStaffNexusAppliesSelectedEmbeddedSpells(): void {
    $character = $this->buildCharacterWithFeatSelection('staff-nexus', [
      'selected_cantrip' => 'detect-magic',
      'selected_spell' => 'magic-missile',
    ], [
      'class' => 'wizard',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['staff-nexus'];
    $this->assertSame('makeshift_staff', $override['type']);
    $this->assertSame('detect-magic', $override['selected_cantrip']);
    $this->assertSame('magic-missile', $override['selected_spell']);
    $this->assertSame('slot_rank', $override['charges_gained_per_slot']);
    $this->assertSame([], $effects['selection_grants']);
    $this->assertContains('staff-nexus', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testStaffNexusResolvesSelectionsFromCanonicalWizardPayload(): void {
    $character = [
      'features' => [
        'feats' => [
          ['id' => 'staff-nexus'],
        ],
      ],
      'wizard' => [
        'feat_selections' => [
          'staff-nexus' => [
            'selected_cantrip' => 'detect-magic',
            'selected_spell' => 'magic-missile',
          ],
        ],
      ],
    ];
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['staff-nexus'];
    $this->assertSame('detect-magic', $override['selected_cantrip']);
    $this->assertSame('magic-missile', $override['selected_spell']);
    $this->assertSame([], $effects['selection_grants']);
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
  public function testCantripExpansionWizardAddsSpellbookCantripExpansion(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('cantrip-expansion-wizard', [
      'selected_cantrips' => ['detect-magic', 'shield'],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['cantrip-expansion-wizard'];
    $this->assertSame('spellbook_cantrip_expansion', $override['type']);
    $this->assertSame('arcane', $override['tradition']);
    $this->assertSame(['detect-magic', 'shield'], $override['added_cantrips']);
    $this->assertTrue($override['prepared_cantrips_do_not_count_against_limit']);
    $this->assertContains('cantrip-expansion-wizard', $effects['applied_feats']);
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
  public function testEsotericPolymathRequestsCrossTraditionSpellSelectionWhenMissing(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('esoteric-polymath', [], [
      'class' => 'bard',
      'subclass' => 'polymath',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('esoteric-polymath', $grant['source_feat']);
    $this->assertSame('esoteric_polymath_spell_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('esoteric-polymath', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEsotericPolymathPersistsSelectedSpellAndDailySwap(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('esoteric-polymath', [
      'selected_spell' => 'magic-missile',
    ], [
      'class' => 'bard',
      'subclass' => 'polymath',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['esoteric-polymath'];
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('cross_tradition_signature_repertoire_spell', $override['type']);
    $this->assertSame('magic-missile', $override['selected_spell']);
    $this->assertSame('esoteric-polymath', $override['selected_spell_source']);
    $this->assertTrue($override['selected_spell_must_be_common']);
    $this->assertTrue($override['selected_spell_must_be_cross_tradition']);
    $this->assertTrue($override['selected_spell_is_signature_spell']);
    $this->assertTrue($override['add_selected_spell_to_repertoire']);
    $this->assertSame(1, $override['signature_spell_swap_uses_per_long_rest']);
    $this->assertSame('daily_preparations', $override['swap_timing']);
    $this->assertSame('esoteric_polymath_spell', $override['special_repertoire_entry_key']);
    $this->assertSame('Esoteric Polymath Spell Swap', $action['name']);
    $this->assertContains('esoteric-polymath', $effects['applied_feats']);
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
  public function testCantripExpansionBardAddsRepertoireCantrips(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('cantrip-expansion', [
      'selected_cantrips' => ['daze', 'read-aura'],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['cantrip-expansion'];
    $this->assertSame('repertoire_cantrip_expansion', $override['type']);
    $this->assertSame('occult', $override['tradition']);
    $this->assertSame(['daze', 'read-aura'], $override['added_cantrips']);
    $this->assertSame(2, $override['extra_repertoire_cantrips']);
    $this->assertContains('cantrip-expansion', $effects['applied_feats']);
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
  public function testEclecticPolymathUsesPersistedEsotericSpellSelection(): void {
    $character = [
      'level' => 10,
      'class' => 'bard',
      'subclass' => 'polymath',
      'features' => [
        'feats' => [
          [
            'id' => 'esoteric-polymath',
            'feat_params' => [
              'selected_spell' => 'magic-missile',
            ],
          ],
          [
            'id' => 'eclectic-polymath',
            'feat_params' => [],
          ],
        ],
      ],
    ];
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['eclectic-polymath'];
    $this->assertSame('cross_tradition_signature_spellcasting_upgrade', $override['type']);
    $this->assertSame('magic-missile', $override['selected_spell']);
    $this->assertSame('esoteric-polymath', $override['selected_spell_source']);
    $this->assertTrue($override['selected_spell_is_signature_spell']);
    $this->assertTrue($override['selected_spell_treated_as_prepared_at_any_available_rank']);
    $this->assertTrue($override['selected_spell_ignores_spontaneous_rank_repertoire_requirement']);
    $this->assertContains('eclectic-polymath', $effects['applied_feats']);
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
  public function testAlchemicalFamiliarAddsCreationGrantAndOverrides(): void {
    $character = $this->buildCharacterWithFeat('alchemical-familiar');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $override = $effects['feat_overrides']['alchemical-familiar'];
    $this->assertSame('alchemical-familiar', $grant['source']);
    $this->assertSame('familiar_creation', $grant['id']);
    $this->assertTrue($override['familiar_uses_int_for_perception_acrobatics_stealth']);
    $this->assertTrue($override['counts_as_alchemical_item_for_infused_reagents']);
    $this->assertContains('alchemical-familiar', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAlchemicalSavantAddsIdentifyAction(): void {
    $character = $this->buildCharacterWithFeat('alchemical-savant');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Alchemical Savant', $action['name']);
    $this->assertSame('identify_alchemical_item', $action['activity']);
    $this->assertTrue($action['requirements']['held_alchemical_item']);
    $this->assertSame(['Concentrate', 'Manipulate'], $action['traits']);
    $this->assertContains('alchemical-savant', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFarLobberAddsBombRangeOverride(): void {
    $character = $this->buildCharacterWithFeat('far-lobber');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['far-lobber'];
    $this->assertSame(30, $override['alchemical_bomb_range_increment_feet']);
    $this->assertContains('far-lobber', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testQuickBomberAddsDrawAndStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('quick-bomber');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Quick Bomber', $action['name']);
    $this->assertSame(1, $action['action_cost']);
    $this->assertSame('draw_bomb_and_strike', $action['activity']);
    $this->assertContains('quick-bomber', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPoisonResistanceAddsResistanceAndSaveBonus(): void {
    $character = $this->buildCharacterWithFeat('poison-resistance');
    $effects = $this->manager->buildEffectState($character, ['level' => 6]);

    $override = $effects['feat_overrides']['poison-resistance'];
    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame('poison', $override['damage_type']);
    $this->assertSame(3, $override['resistance']);
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('status', $modifier['bonus_type']);
    $this->assertSame('against poison', $modifier['context']);
    $this->assertContains('poison-resistance', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRevivifyingMutagenAddsEndMutagenHealingAction(): void {
    $character = $this->buildCharacterWithFeat('revivifying-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Revivifying Mutagen', $action['name']);
    $this->assertSame('end_mutagen_to_heal', $action['activity']);
    $this->assertTrue($action['requirements']['under_mutagen']);
    $this->assertSame('1d6 per 2 mutagen item levels (minimum 1d6)', $action['healing_formula']);
    $this->assertContains('revivifying-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSmokeBombAddsQuickAlchemySmokeAdditive(): void {
    $character = $this->buildCharacterWithFeat('smoke-bomb');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Smoke Bomb', $action['name']);
    $this->assertSame('free', $action['action_cost']);
    $this->assertSame('quick_alchemy_additive', $action['activity']);
    $this->assertSame('quick_alchemy_creates_qualifying_bomb', $action['trigger']);
    $this->assertSame(10, $action['smoke_burst_feet']);
    $this->assertContains('smoke-bomb', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCalculatedSplashAddsIntelligenceSplashOverride(): void {
    $character = $this->buildCharacterWithFeat('calculated-splash');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['calculated-splash'];
    $this->assertSame('max(0, intelligence_modifier)', $override['bomb_splash_damage_formula']);
    $this->assertContains('calculated-splash', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEfficientAlchemyAddsCraftingOutputOverride(): void {
    $character = $this->buildCharacterWithFeat('efficient-alchemy');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['efficient-alchemy'];
    $this->assertSame(2, $override['craft_alchemical_batch_output_multiplier']);
    $this->assertTrue($override['no_extra_time_required']);
    $this->assertContains('efficient-alchemy', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEnduringAlchemyExtendsQuickAlchemyDuration(): void {
    $character = $this->buildCharacterWithFeat('enduring-alchemy');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['enduring-alchemy'];
    $this->assertSame('end_of_your_next_turn', $override['quick_alchemy_tools_and_elixirs_expire']);
    $this->assertContains('enduring-alchemy', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCombineElixirsAddsQuickAlchemyElixirAdditive(): void {
    $character = $this->buildCharacterWithFeat('combine-elixirs');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Combine Elixirs', $action['name']);
    $this->assertSame('quick_alchemy_additive', $action['activity']);
    $this->assertSame('quick_alchemy_creates_qualifying_elixir', $action['trigger']);
    $this->assertSame(2, $action['advanced_alchemy_gap']);
    $this->assertSame('same_or_lower', $action['secondary_elixir_level']);
    $this->assertContains('combine-elixirs', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDebilitatingBombAddsOnHitConditionChoice(): void {
    $character = $this->buildCharacterWithFeat('debilitating-bomb');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Debilitating Bomb', $action['name']);
    $this->assertSame('quick_alchemy_additive', $action['activity']);
    $this->assertSame(['dazzled', 'deafened', 'flat-footed', 'speed_penalty_5'], $action['on_hit_choose_one']);
    $this->assertSame('until_start_of_your_next_turn', $action['duration']);
    $this->assertContains('debilitating-bomb', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDirectionalBombsAddsConeSplashOverride(): void {
    $character = $this->buildCharacterWithFeat('directional-bombs');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['directional-bombs'];
    $this->assertTrue($override['bomb_splash_can_be_directed_as_cone']);
    $this->assertSame(15, $override['cone_length_feet']);
    $this->assertSame('away_from_you', $override['cone_direction']);
    $this->assertContains('directional-bombs', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFeralMutagenAddsBestialCombatOverrides(): void {
    $character = $this->buildCharacterWithFeat('feral-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['feral-mutagen'];
    $this->assertSame('bestial', $override['requires_mutagen']);
    $this->assertTrue($override['bestial_mutagen_item_bonus_applies_to_intimidation']);
    $this->assertSame('deadly_d10', $override['claws_gain_trait']);
    $this->assertSame('deadly_d10', $override['jaws_gain_trait']);
    $this->assertContains('feral-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testStickyBombAddsPersistentDamageAdditive(): void {
    $character = $this->buildCharacterWithFeat('sticky-bomb');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Sticky Bomb', $action['name']);
    $this->assertSame('quick_alchemy_additive', $action['activity']);
    $this->assertSame('quick_alchemy_creates_qualifying_bomb', $action['trigger']);
    $this->assertSame('bomb_item_level', $action['on_direct_hit_persistent_damage']);
    $this->assertSame('bomb_main_damage_type', $action['persistent_damage_type']);
    $this->assertContains('sticky-bomb', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testElasticMutagenAddsMobilityOverrides(): void {
    $character = $this->buildCharacterWithFeat('elastic-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['elastic-mutagen'];
    $this->assertSame('quicksilver', $override['requires_mutagen']);
    $this->assertSame(10, $override['step_distance_feet']);
    $this->assertTrue($override['squeeze_as_size_smaller']);
    $this->assertContains('elastic-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testExpandedSplashAddsDamageAndRadiusOverrides(): void {
    $character = $this->buildCharacterWithFeat('expanded-splash');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['expanded-splash'];
    $this->assertSame('normal_splash + intelligence_modifier', $override['bomb_splash_damage_formula']);
    $this->assertSame(10, $override['bomb_splash_radius_feet']);
    $this->assertContains('expanded-splash', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGreaterDebilitatingBombAddsMoreConditionChoices(): void {
    $character = $this->buildCharacterWithFeat('greater-debilitating-bomb');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['greater-debilitating-bomb'];
    $this->assertSame('debilitating-bomb', $override['modifies_feat']);
    $this->assertSame(['clumsy_1', 'enfeebled_1', 'stupefied_1', 'speed_penalty_10'], $override['additional_on_hit_options']);
    $this->assertContains('greater-debilitating-bomb', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMercifulElixirAddsCounteractAdditive(): void {
    $character = $this->buildCharacterWithFeat('merciful-elixir');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Merciful Elixir', $action['name']);
    $this->assertSame('quick_alchemy_additive', $action['activity']);
    $this->assertSame('quick_alchemy_creates_elixir_of_life', $action['trigger']);
    $this->assertSame(['fear', 'poison'], $action['counteract_options']);
    $this->assertContains('merciful-elixir', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPotentPoisonerAddsPoisonDcCraftingOverride(): void {
    $character = $this->buildCharacterWithFeat('potent-poisoner');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['potent-poisoner'];
    $this->assertSame(4, $override['crafted_poison_dc_bonus_max']);
    $this->assertTrue($override['crafted_poison_dc_capped_by_class_dc']);
    $this->assertContains('potent-poisoner', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testExtendElixirAddsDurationMultiplierOverride(): void {
    $character = $this->buildCharacterWithFeat('extend-elixir');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['extend-elixir'];
    $this->assertSame('drink_own_infused_elixir_with_duration_at_least_1_minute', $override['trigger']);
    $this->assertSame(2, $override['duration_multiplier']);
    $this->assertContains('extend-elixir', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInvincibleMutagenAddsPhysicalResistanceFormula(): void {
    $character = $this->buildCharacterWithFeat('invincible-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['invincible-mutagen'];
    $this->assertSame('juggernaut', $override['requires_mutagen']);
    $this->assertSame('intelligence_modifier', $override['physical_resistance_formula']);
    $this->assertContains('invincible-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUncannyBombsAddsRangeCoverAndConcealmentOverrides(): void {
    $character = $this->buildCharacterWithFeat('uncanny-bombs');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['uncanny-bombs'];
    $this->assertSame(60, $override['alchemical_bomb_range_increment_feet']);
    $this->assertSame(1, $override['reduce_cover_ac_bonus_against_bombs_by']);
    $this->assertTrue($override['automatically_succeed_concealed_flat_check_with_bombs']);
    $this->assertContains('uncanny-bombs', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGlibMutagenAddsSocialPenaltyBypassOverrides(): void {
    $character = $this->buildCharacterWithFeat('glib-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['glib-mutagen'];
    $this->assertSame('silvertongue', $override['requires_mutagen']);
    $this->assertSame(['Deception', 'Diplomacy', 'Intimidation', 'Performance'], $override['ignore_circumstance_penalties_to']);
    $this->assertTrue($override['lies_become_more_convincing']);
    $this->assertContains('glib-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGreaterMercifulElixirAddsMoreCounteractOptions(): void {
    $character = $this->buildCharacterWithFeat('greater-merciful-elixir');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['greater-merciful-elixir'];
    $this->assertSame('merciful-elixir', $override['modifies_feat']);
    $this->assertSame(['blinded', 'deafened', 'sickened', 'slowed'], $override['additional_counteract_options']);
    $this->assertContains('greater-merciful-elixir', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrueDebilitatingBombAddsStrongerConditionChoices(): void {
    $character = $this->buildCharacterWithFeat('true-debilitating-bomb');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['true-debilitating-bomb'];
    $this->assertSame('debilitating-bomb', $override['modifies_feat']);
    $this->assertSame(['enfeebled_2', 'stupefied_2', 'speed_penalty_15'], $override['additional_on_hit_options']);
    $this->assertSame('until_end_of_targets_next_turn', $override['duration']);
    $this->assertContains('true-debilitating-bomb', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEternalElixirAddsDailyExtendedElixirAction(): void {
    $character = $this->buildCharacterWithFeat('eternal-elixir');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Eternal Elixir', $action['name']);
    $this->assertSame('consume_elixir_with_extended_duration', $action['activity']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('until_next_daily_preparations', $action['duration']);
    $this->assertContains('eternal-elixir', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testExploitiveBombAddsResistanceReductionAdditive(): void {
    $character = $this->buildCharacterWithFeat('exploitive-bomb');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Exploitive Bomb', $action['name']);
    $this->assertSame('quick_alchemy_additive', $action['activity']);
    $this->assertSame('bomb_item_level', $action['on_hit_reduce_resistance_by']);
    $this->assertSame('bomb_damage_type', $action['affected_resistance']);
    $this->assertContains('exploitive-bomb', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGeniusMutagenAddsCognitiveSkillExpansion(): void {
    $character = $this->buildCharacterWithFeat('genius-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['genius-mutagen'];
    $this->assertSame('cognitive', $override['requires_mutagen']);
    $this->assertSame(['Deception', 'Diplomacy', 'Intimidation', 'Medicine', 'Nature', 'Performance', 'Religion', 'Survival'], $override['cognitive_mutagen_item_bonus_applies_to']);
    $this->assertContains('genius-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPersistentMutagenAddsDailyExtendedMutagenAction(): void {
    $character = $this->buildCharacterWithFeat('persistent-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Persistent Mutagen', $action['name']);
    $this->assertSame('consume_mutagen_with_extended_duration', $action['activity']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('until_next_daily_preparations', $action['duration']);
    $this->assertContains('persistent-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMindblankMutagenAddsDetectionBlockers(): void {
    $character = $this->buildCharacterWithFeat('mindblank-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['mindblank-mutagen'];
    $this->assertSame('serene', $override['requires_mutagen']);
    $this->assertTrue($override['blocks_detection_revelation_and_scrying']);
    $this->assertSame(9, $override['effect_level_cap']);
    $this->assertTrue($override['as_if_under_mind_blank']);
    $this->assertContains('mindblank-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMiracleWorkerAddsResurrectionAction(): void {
    $character = $this->buildCharacterWithFeat('miracle-worker');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Miracle Worker', $action['name']);
    $this->assertSame('once_per_10_minutes', $action['frequency']);
    $this->assertSame('creature_dead_for_2_rounds_or_fewer', $action['target_requirement']);
    $this->assertSame('returns_to_life_at_1_hp', $action['result']);
    $this->assertTrue($action['consumes_item']);
    $this->assertContains('miracle-worker', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPerfectDebilitationAddsCriticalSuccessAvoidanceGate(): void {
    $character = $this->buildCharacterWithFeat('perfect-debilitation');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['perfect-debilitation'];
    $this->assertSame('debilitating-bomb', $override['modifies_feat']);
    $this->assertTrue($override['conditions_avoided_only_on_critical_save_success']);
    $this->assertContains('perfect-debilitation', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCraftPhilosophersStoneAddsFormulaGrant(): void {
    $character = $this->buildCharacterWithFeat('craft-philosophers-stone');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['craft-philosophers-stone'];
    $this->assertSame(["philosopher's stone"], $override['formula_grants']);
    $this->assertTrue($override['add_to_formula_book']);
    $this->assertContains('craft-philosophers-stone', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testImprobableElixirsRequestsFormulaSelectionWhenMissing(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('improbable-elixirs', [], [
      'level' => 16,
      'abilities' => [
        'intelligence' => 12,
      ],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('improbable-elixirs', $grant['source_feat']);
    $this->assertSame('improbable_elixirs_formula_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('improbable-elixirs', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testImprobableElixirsIgnoresInvalidSelectionsAndKeepsChoiceOpen(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('improbable-elixirs', [
      'selected_formulas' => ['scroll-1st-level'],
    ], [
      'level' => 16,
      'abilities' => [
        'intelligence' => 12,
      ],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertArrayNotHasKey('improbable-elixirs', $effects['feat_overrides']);
    $this->assertSame('improbable_elixirs_formula_choice', $effects['selection_grants'][0]['selection_type']);
    $this->assertContains('improbable-elixirs', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testImprobableElixirsPersistsConvertedPotionFormulaMetadata(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('improbable-elixirs', [
      'selected_formulas' => ['potion-of-healing-lesser'],
    ], [
      'level' => 16,
      'abilities' => [
        'intelligence' => 12,
      ],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['improbable-elixirs'];
    $this->assertSame('improbable_elixirs_formula_conversion', $override['type']);
    $this->assertSame(['potion-of-healing-lesser'], $override['formula_grants']);
    $this->assertTrue($override['add_to_formula_book']);
    $this->assertTrue($override['treat_selected_potion_formulas_as_alchemical_elixirs']);
    $this->assertSame('improbable-elixirs', $override['formula_source']);
    $this->assertSame('alchemical_elixir_via_potion_formula', $override['display_in_formula_book_as']);
    $this->assertFalse($override['selection_pending']);
    $this->assertSame('potion-of-healing-lesser', $override['converted_formulas'][0]['formula_id']);
    $this->assertSame('Potion of Healing (Lesser)', $override['converted_formulas'][0]['formula_name']);
    $this->assertSame(1, $override['converted_formulas'][0]['item_level']);
    $this->assertSame('potion', $override['converted_formulas'][0]['original_item_type']);
    $this->assertSame('elixir', $override['converted_formulas'][0]['converted_item_type']);
    $this->assertContains('improbable-elixirs', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInfiniteEyeAddsAuraSenseAndTruesightUses(): void {
    $character = $this->buildCharacterWithFeat('infinite-eye');
    $effects = $this->manager->buildEffectState($character);

    $sense = $effects['senses'][0];
    $this->assertSame('detect_magic_auras', $sense['type']);
    $this->assertSame('at_will', $sense['mode']);
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Infinite Eye', $action['name']);
    $this->assertSame('3_per_long_rest', $action['frequency']);
    $this->assertSame('gain_truesight', $action['activity']);
    $this->assertSame(30, $action['range_feet']);
    $this->assertContains('infinite-eye', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testReprepareSpellAddsDailyPreparationAction(): void {
    $character = $this->buildCharacterWithFeat('reprepare-spell');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Reprepare Spell', $action['name']);
    $this->assertSame('10_minutes', $action['action_cost']);
    $this->assertSame('3_per_long_rest', $action['frequency']);
    $this->assertSame('prepare_spellbook_spell_into_slot', $action['activity']);
    $this->assertSame('spellbook', $action['source']);
    $this->assertContains('reprepare-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMetamagicMasteryAddsMetamagicActionOverride(): void {
    $character = $this->buildCharacterWithFeat('metamagic-mastery');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['metamagic-mastery'];
    $this->assertTrue($override['metamagic_does_not_increase_spell_action_cost']);
    $this->assertTrue($override['can_apply_two_metamagic_feats_to_same_spell']);
    $this->assertContains('metamagic-mastery', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEternalCompositionAddsCompositionCapacityOverride(): void {
    $character = $this->buildCharacterWithFeat('eternal-composition');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['eternal-composition'];
    $this->assertSame(3, $override['max_simultaneous_compositions']);
    $this->assertTrue($override['single_sustain_can_maintain_all_active_compositions']);
    $this->assertContains('eternal-composition', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMelodicCastingAddsTwoSpellMelodiousAugment(): void {
    $character = $this->buildCharacterWithFeat('melodic-casting');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $this->assertSame('Melodic Casting', $augment['name']);
    $this->assertSame(2, $augment['applies_melodious_spell_to_next_spells_this_turn']);
    $this->assertTrue($augment['replaces_separate_metamagic_actions']);
    $this->assertContains('melodic-casting', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFatalAriaAddsDailyFocusSpell(): void {
    $character = $this->buildCharacterWithFeat('fatal-aria');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Fatal Aria', $action['name']);
    $this->assertSame(2, $action['action_cost']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('will', $action['save']);
    $this->assertSame('dies', $action['outcomes']['critical_failure']);
    $this->assertContains('fatal-aria', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPerfectEncoreAddsFocusBoostOverride(): void {
    $character = $this->buildCharacterWithFeat('perfect-encore');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['perfect-encore'];
    $this->assertSame('cast_non_cantrip_composition_spell', $override['trigger']);
    $this->assertSame(1, $override['focus_point_cost']);
    $this->assertSame(2, $override['treat_focus_points_spent_as']);
    $this->assertTrue($override['spell_slot_cost_unchanged']);
    $this->assertContains('perfect-encore', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPiedPiperAddsCompulsionFocusSpell(): void {
    $character = $this->buildCharacterWithFeat('pied-piper');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Pied Piper', $action['name']);
    $this->assertSame(2, $action['action_cost']);
    $this->assertSame('all_creatures_in_range', $action['target']);
    $this->assertTrue($action['repeat_save_each_turn']);
    $this->assertTrue($action['critical_success_ends_effect']);
    $this->assertContains('pied-piper', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPolymathApexAddsExpertMinimumToVersatilePerformance(): void {
    $character = $this->buildCharacterWithFeat('polymath-apex');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['polymath-apex'];
    $this->assertTrue($override['when_using_versatile_performance']);
    $this->assertSame('expert', $override['minimum_substitute_skill_proficiency']);
    $this->assertTrue($override['use_higher_of_performance_or_expert']);
    $this->assertContains('polymath-apex', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSymphonyOfTheMuseAddsFreeCompositionAugment(): void {
    $character = $this->buildCharacterWithFeat('symphony-of-the-muse');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $this->assertSame('Symphony of the Muse', $augment['name']);
    $this->assertSame('free', $augment['next_composition_action_cost']);
    $this->assertTrue($augment['does_not_count_against_one_composition_per_turn_limit']);
    $this->assertContains('symphony-of-the-muse', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrueFacetsRequestsSecondMuseSelectionWhenMissing(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('true-facets', [], [
      'level' => 20,
      'class' => 'bard',
      'subclass' => 'enigma',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('true-facets', $grant['source_feat']);
    $this->assertSame('bard_second_muse_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('true-facets', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrueFacetsRequestsSelectionWhenPrimaryMuseIsMissing(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('true-facets', [
      'selected_muse' => 'maestro',
    ], [
      'level' => 20,
      'class' => 'bard',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('true-facets', $grant['source_feat']);
    $this->assertSame('bard_second_muse_choice', $grant['selection_type']);
    $this->assertArrayNotHasKey('true-facets', $effects['feat_overrides']);
    $this->assertContains('true-facets', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrueFacetsPersistsSecondMuseBenefitsAndUnlocks(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('true-facets', [
      'selected_muse' => 'maestro',
    ], [
      'level' => 20,
      'class' => 'bard',
      'subclass' => 'enigma',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['true-facets'];
    $this->assertSame('bard_second_muse_unlock', $override['type']);
    $this->assertSame('enigma', $override['primary_muse']);
    $this->assertSame('maestro', $override['selected_second_muse']);
    $this->assertSame('Maestro', $override['selected_second_muse_label']);
    $this->assertSame(['lingering-composition'], $override['bonus_feat_grants']);
    $this->assertSame('soothe', $override['bonus_spell_grants'][0]['spell_id']);
    $this->assertSame('soothe', $override['bonus_spell_grants'][0]['spell_name']);
    $this->assertSame(['maestro'], $override['unlocked_muse_prerequisites']);
    $this->assertTrue($override['grants_second_muse_feat_graph_access']);
    $this->assertTrue($override['selection_must_differ_from_primary_muse']);
    $this->assertSame([], $effects['selection_grants']);
    $this->assertContains('true-facets', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMegaBombAddsLargeAreaBombAction(): void {
    $character = $this->buildCharacterWithFeat('mega-bomb');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Mega Bomb', $action['name']);
    $this->assertSame('mega_bomb_throw', $action['activity']);
    $this->assertSame(3, $action['advanced_alchemy_gap']);
    $this->assertSame(30, $action['detonation_radius_feet']);
    $this->assertTrue($action['all_creatures_take_full_damage_and_splash']);
    $this->assertContains('mega-bomb', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPerfectMutagenRemovesOwnMutagenDrawbacks(): void {
    $character = $this->buildCharacterWithFeat('perfect-mutagen');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['perfect-mutagen'];
    $this->assertTrue($override['ignores_drawbacks_of_own_crafted_mutagens']);
    $this->assertContains('perfect-mutagen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAcuteVisionAddsConditionalDarkvision(): void {
    $character = $this->buildCharacterWithFeat('acute-vision');
    $effects = $this->manager->buildEffectState($character);

    $sense = $effects['senses'][0];
    $this->assertSame('darkvision', $sense['type']);
    $this->assertSame('while_raging', $sense['condition']);
    $this->assertContains('acute-vision', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMomentOfClarityAddsConcentrateWindowAction(): void {
    $character = $this->buildCharacterWithFeat('moment-of-clarity');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Moment of Clarity', $action['name']);
    $this->assertSame('allow_concentrate_action_while_raging', $action['activity']);
    $this->assertContains('moment-of-clarity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRagingIntimidationAddsRageTraitAndBonusFeat(): void {
    $character = $this->buildCharacterWithFeat('raging-intimidation');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['raging-intimidation'];
    $this->assertSame(['demoralize', 'scare_to_death'], $override['actions_gain_rage_trait']);
    $this->assertSame(['demoralize', 'scare_to_death'], $override['actions_usable_while_raging']);
    $this->assertSame(['intimidating-glare'], $override['bonus_feat_grants']);
    $this->assertContains('raging-intimidation', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRagingThrowerAddsThrownRageDamageOverrides(): void {
    $character = $this->buildCharacterWithFeat('raging-thrower');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['raging-thrower'];
    $this->assertTrue($override['thrown_attacks_gain_rage_melee_damage_bonus']);
    $this->assertSame(6, $override['giant_instinct_oversized_thrown_bonus_damage']);
    $this->assertContains('raging-thrower', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAcuteScentAddsConditionalImpreciseScent(): void {
    $character = $this->buildCharacterWithFeat('acute-scent');
    $effects = $this->manager->buildEffectState($character);

    $sense = $effects['senses'][0];
    $this->assertSame('imprecise_scent', $sense['type']);
    $this->assertSame(30, $sense['range_feet']);
    $this->assertSame('while_raging', $sense['condition']);
    $this->assertContains('acute-scent', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFuriousFinishAddsMaximumDamageStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('furious-finish');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Furious Finish', $action['name']);
    $this->assertSame('strike_with_maximum_weapon_damage_dice', $action['activity']);
    $this->assertTrue($action['spends_remaining_rage_rounds']);
    $this->assertTrue($action['rage_ends_after_action']);
    $this->assertContains('furious-finish', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testNoEscapeAddsReactionStride(): void {
    $character = $this->buildCharacterWithFeat('no-escape');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('No Escape', $action['name']);
    $this->assertSame('reaction_stride', $action['activity']);
    $this->assertSame('adjacent_enemy_moves_away', $action['trigger']);
    $this->assertSame('stride_to_remain_adjacent', $action['result']);
    $this->assertContains('no-escape', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSecondWindAddsDailyRecoveryAction(): void {
    $character = $this->buildCharacterWithFeat('second-wind');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Second Wind', $action['name']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('self_heal', $action['activity']);
    $this->assertSame('barbarian_level', $action['healing_formula']);
    $this->assertSame(1, $action['dying_override']['hp_after_stabilizing']);
    $this->assertContains('second-wind', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testShakeItOffAddsConditionReductionAction(): void {
    $character = $this->buildCharacterWithFeat('shake-it-off');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Shake It Off', $action['name']);
    $this->assertSame('reduce_condition', $action['activity']);
    $this->assertSame(['persistent_damage', 'frightened', 'sickened', 'slowed'], $action['condition_options']);
    $this->assertSame(1, $action['juggernaut_persistent_damage_bonus_reduction']);
    $this->assertContains('shake-it-off', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFastMovementAddsWhileRagingSpeedBonus(): void {
    $character = $this->buildCharacterWithFeat('fast-movement');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['fast-movement'];
    $this->assertSame(10, $override['while_raging_speed_bonus']);
    $this->assertContains('fast-movement', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRagingAthleteAddsMobilityOverrides(): void {
    $character = $this->buildCharacterWithFeat('raging-athlete');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['raging-athlete'];
    $this->assertTrue($override['athletics_uses_rage_proficiency']);
    $this->assertTrue($override['climb_speed_equals_land_speed']);
    $this->assertTrue($override['jumps_treat_athletics_roll_as_10']);
    $this->assertTrue($override['difficult_terrain_does_not_reduce_jump_distance']);
    $this->assertContains('raging-athlete', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSwipeAddsTwoTargetStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('swipe');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Swipe', $action['name']);
    $this->assertSame('melee_strike_two_adjacent_foes', $action['activity']);
    $this->assertTrue($action['same_damage_roll_applies_to_each_target']);
    $this->assertTrue($action['each_target_counts_as_own_strike_for_map']);
    $this->assertContains('swipe', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWoundedRageAddsDailyReactionRage(): void {
    $character = $this->buildCharacterWithFeat('wounded-rage');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Wounded Rage', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('enter_rage', $action['activity']);
    $this->assertSame('you_take_damage', $action['trigger']);
    $this->assertContains('wounded-rage', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAnimalSkinAddsRagingAcBonuses(): void {
    $character = $this->buildCharacterWithFeat('animal-skin');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['animal-skin'];
    $this->assertSame('while_raging', $override['condition']);
    $this->assertSame(2, $override['unarmored_ac_item_bonus']);
    $this->assertSame(1, $override['light_armor_ac_item_bonus']);
    $this->assertContains('animal-skin', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAttackOfOpportunityBarbarianAddsReactionStrike(): void {
    $character = $this->buildCharacterWithFeat('attack-of-opportunity-barbarian');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Attack of Opportunity', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertTrue($action['disrupts_manipulate_on_hit']);
    $this->assertContains('attack-of-opportunity-barbarian', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBrutalBullyAddsCombatManeuverDamage(): void {
    $character = $this->buildCharacterWithFeat('brutal-bully');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['brutal-bully'];
    $this->assertSame(['grapple_success', 'shove_success', 'trip_success'], $override['triggers']);
    $this->assertSame('rage_melee_damage_bonus', $override['extra_damage']);
    $this->assertSame('bludgeoning', $override['extra_damage_type']);
    $this->assertContains('brutal-bully', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCleaveAddsReactionFollowupStrike(): void {
    $character = $this->buildCharacterWithFeat('cleave');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Cleave', $action['name']);
    $this->assertSame('you_kill_or_critically_hit_a_foe', $action['trigger']);
    $this->assertSame('adjacent_enemy', $action['target_requirement']);
    $this->assertContains('cleave', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDragonsRageBreathAddsOncePerRageCone(): void {
    $character = $this->buildCharacterWithFeat('dragons-rage-breath');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame("Dragon's Rage Breath", $action['name']);
    $this->assertSame('once_per_rage', $action['usage_limit']);
    $this->assertSame('30_foot_cone', $action['area']);
    $this->assertSame('1d6_per_level', $action['damage_formula']);
    $this->assertContains('dragons-rage-breath', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSpiritsInterferenceAddsPhysicalDamageReductionRoll(): void {
    $character = $this->buildCharacterWithFeat('spirits-interference');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['spirits-interference'];
    $this->assertSame('would_take_physical_damage', $override['trigger']);
    $this->assertSame('1d4', $override['roll']);
    $this->assertSame(1, $override['failure_on']);
    $this->assertSame('rolled_amount', $override['reduction_on_other_results']);
    $this->assertContains('spirits-interference', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAnimalRageAddsTransformationAction(): void {
    $character = $this->buildCharacterWithFeat('animal-rage');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Animal Rage', $action['name']);
    $this->assertSame('transform_into_instinct_animal', $action['activity']);
    $this->assertSame('animal_form_rank_4', $action['form_reference']);
    $this->assertTrue($action['retains_rage_effects']);
    $this->assertContains('animal-rage', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSpiritsWrathAddsOncePerRageRangedBurst(): void {
    $character = $this->buildCharacterWithFeat('spirits-wrath');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame("Spirit's Wrath", $action['name']);
    $this->assertSame('once_per_rage', $action['usage_limit']);
    $this->assertSame(['negative', 'positive'], $action['damage_type_options']);
    $this->assertSame('fortitude', $action['save']);
    $this->assertContains('spirits-wrath', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGiantFootprintAddsReachOverrides(): void {
    $character = $this->buildCharacterWithFeat('giant-footprint');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['giant-footprint'];
    $this->assertSame(5, $override['reach_bonus_feet']);
    $this->assertSame(10, $override['medium_reach_becomes_feet']);
    $this->assertSame(15, $override['medium_reach_weapon_reach_becomes_feet']);
    $this->assertContains('giant-footprint', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRenewedVigorAddsTemporaryHitPointAction(): void {
    $character = $this->buildCharacterWithFeat('renewed-vigor');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Renewed Vigor', $action['name']);
    $this->assertSame('gain_temporary_hit_points', $action['activity']);
    $this->assertSame('floor(level/2) + constitution_modifier', $action['temporary_hp_formula']);
    $this->assertTrue($action['replaces_existing_rage_temp_hp']);
    $this->assertContains('renewed-vigor', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testShareThePainAddsRetaliationReaction(): void {
    $character = $this->buildCharacterWithFeat('share-the-pain');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Share the Pain', $action['name']);
    $this->assertSame('hit_by_enemy_melee_strike', $action['trigger']);
    $this->assertSame('rage_melee_damage_bonus', $action['retaliation_damage']);
    $this->assertSame('bludgeoning', $action['retaliation_damage_type']);
    $this->assertContains('share-the-pain', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSuddenLeapAddsJumpStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('sudden-leap');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Sudden Leap', $action['name']);
    $this->assertSame('jump_then_strike', $action['activity']);
    $this->assertTrue($action['can_target_enemy_jumped_over']);
    $this->assertTrue($action['jump_does_not_provoke_reactions']);
    $this->assertContains('sudden-leap', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAwesomeBlowAddsPushAndProneReaction(): void {
    $character = $this->buildCharacterWithFeat('awesome-blow');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Awesome Blow', $action['name']);
    $this->assertSame('critically_hit_enemy_with_melee_strike_while_raging', $action['trigger']);
    $this->assertSame(20, $action['outcomes']['critical_failure']['push_feet']);
    $this->assertTrue($action['outcomes']['failure']['prone']);
    $this->assertContains('awesome-blow', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGiantStatureAddsLargeSizeOverride(): void {
    $character = $this->buildCharacterWithFeat('giant-stature');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['giant-stature'];
    $this->assertSame('large', $override['size_becomes']);
    $this->assertTrue($override['oversized_weapon_grows_with_you']);
    $this->assertTrue($override['space_and_reach_increase']);
    $this->assertContains('giant-stature', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testKnockbackAddsFreeShoveAction(): void {
    $character = $this->buildCharacterWithFeat('knockback');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Knockback', $action['name']);
    $this->assertSame('free_shove_after_melee_strike', $action['activity']);
    $this->assertFalse($action['multiple_attack_penalty_applies']);
    $this->assertContains('knockback', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTerrifyingHowlAddsAreaDemoralizeAction(): void {
    $character = $this->buildCharacterWithFeat('terrifying-howl');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Terrifying Howl', $action['name']);
    $this->assertSame('demoralize_area', $action['activity']);
    $this->assertSame('all_enemies_in_range', $action['targets']);
    $this->assertSame('frightened_2', $action['critical_success_effect']);
    $this->assertContains('terrifying-howl', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDragonsRageWingsAddsConditionalFlight(): void {
    $character = $this->buildCharacterWithFeat('dragons-rage-wings');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['dragons-rage-wings'];
    $this->assertTrue($override['gain_fly_speed_equal_to_land_speed']);
    $this->assertTrue($override['wings_retract_when_rage_ends']);
    $this->assertContains('dragons-rage-wings', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInvulnerableJuggernautAddsPhysicalResistanceBonus(): void {
    $character = $this->buildCharacterWithFeat('invulnerable-juggernaut');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['invulnerable-juggernaut'];
    $this->assertSame(2, $override['physical_resistance_bonus']);
    $this->assertTrue($override['stacks_with_raging_resistance']);
    $this->assertContains('invulnerable-juggernaut', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPredatorInstinctAddsAnimalAttackUpgrades(): void {
    $character = $this->buildCharacterWithFeat('predator-instinct');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['predator-instinct'];
    $this->assertSame('d10', $override['animal_instinct_attack_damage_die']);
    $this->assertSame('deadly_d8', $override['animal_instinct_attack_gains_trait']);
    $this->assertContains('predator-instinct', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRavagerAddsCriticalSpecializationOverride(): void {
    $character = $this->buildCharacterWithFeat('ravager');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['ravager'];
    $this->assertTrue($override['critical_hits_gain_weapon_critical_specialization_without_mastery']);
    $this->assertTrue($override['existing_critical_specialization_can_add_additional_effect']);
    $this->assertContains('ravager', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testComeAndGetMeAddsChallengeStanceAction(): void {
    $character = $this->buildCharacterWithFeat('come-and-get-me');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Come and Get Me', $action['name']);
    $this->assertSame('raging_challenge_stance', $action['activity']);
    $this->assertSame(-2, $action['ac_penalty']);
    $this->assertTrue($action['enemies_that_hit_you_become_flat_footed_to_your_next_strike']);
    $this->assertContains('come-and-get-me', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAuraOfFuryAddsAlliedDamageAura(): void {
    $character = $this->buildCharacterWithFeat('aura-of-fury');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['aura-of-fury'];
    $this->assertSame(10, $override['aura_radius_feet']);
    $this->assertSame(1, $override['allies_gain_status_bonus_to_damage']);
    $this->assertContains('aura-of-fury', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSpiritsRageRemovesSpiritsWrathLimit(): void {
    $character = $this->buildCharacterWithFeat('spirits-rage');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['spirits-rage'];
    $this->assertSame('spirits-wrath', $override['modifies_feat']);
    $this->assertTrue($override['removes_once_per_rage_limit']);
    $this->assertContains('spirits-rage', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVengefulStrikeAddsTriggeredReactionStrike(): void {
    $character = $this->buildCharacterWithFeat('vengeful-strike');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Vengeful Strike', $action['name']);
    $this->assertSame('ally_within_60_feet_is_critically_hit', $action['trigger']);
    $this->assertSame('triggering_enemy_within_reach', $action['target_requirement']);
    $this->assertContains('vengeful-strike', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWhirlwindStrikeAddsAllAdjacentStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('whirlwind-strike');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Whirlwind Strike', $action['name']);
    $this->assertSame(3, $action['action_cost']);
    $this->assertSame('melee_strike_all_adjacent_creatures', $action['activity']);
    $this->assertTrue($action['same_damage_roll_applies_to_all_targets']);
    $this->assertContains('whirlwind-strike', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCollateralDamageAddsAdjacentSplashDamageOverride(): void {
    $character = $this->buildCharacterWithFeat('collateral-damage');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['collateral-damage'];
    $this->assertSame('deal_damage_with_melee_strike', $override['trigger']);
    $this->assertSame('rage_melee_damage_bonus', $override['adjacent_secondary_target_damage']);
    $this->assertSame('bludgeoning', $override['adjacent_secondary_target_damage_type']);
    $this->assertContains('collateral-damage', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGreatCleaveAddsChainOverride(): void {
    $character = $this->buildCharacterWithFeat('great-cleave');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['great-cleave'];
    $this->assertSame('cleave', $override['modifies_feat']);
    $this->assertTrue($override['cleave_can_chain_repeatedly']);
    $this->assertSame(['miss', 'no_new_adjacent_foe'], $override['chain_stops_when']);
    $this->assertContains('great-cleave', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAccurateSwingAddsSwipeAccuracyOverrides(): void {
    $character = $this->buildCharacterWithFeat('accurate-swing');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['accurate-swing'];
    $this->assertSame('swipe', $override['modifies_feat']);
    $this->assertSame('sweep', $override['swipe_gains_trait']);
    $this->assertSame(1, $override['swipe_attack_bonus']);
    $this->assertContains('accurate-swing', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testImpalingStrikeAddsImmobilizingBleedAction(): void {
    $character = $this->buildCharacterWithFeat('impaling-strike');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Impaling Strike', $action['name']);
    $this->assertSame(2, $action['multiple_attack_penalty_counts_as']);
    $this->assertTrue($action['on_hit_effects']['immobilized']);
    $this->assertSame('1d8', $action['on_hit_effects']['persistent_bleed_damage']);
    $this->assertContains('impaling-strike', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAwakenTheInnerMonolithAddsHugeSizeOverride(): void {
    $character = $this->buildCharacterWithFeat('awaken-the-inner-monolith');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['awaken-the-inner-monolith'];
    $this->assertSame('while_raging_with_giant_stature', $override['condition']);
    $this->assertSame('huge', $override['size_becomes']);
    $this->assertTrue($override['oversized_weapon_grows_with_you']);
    $this->assertContains('awaken-the-inner-monolith', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testApexOfFuryAddsUnlimitedRageOverride(): void {
    $character = $this->buildCharacterWithFeat('apex-of-fury');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['apex-of-fury'];
    $this->assertSame('unlimited', $override['rage_uses_per_day']);
    $this->assertTrue($override['removes_rage_cooldown']);
    $this->assertContains('apex-of-fury', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrueBeastAddsFinalAnimalInstinctOverrides(): void {
    $character = $this->buildCharacterWithFeat('true-beast');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['true-beast'];
    $this->assertTrue($override['can_enter_true_beast_form']);
    $this->assertSame(['medium', 'large'], $override['true_beast_form_size_options']);
    $this->assertSame('2d6', $override['animal_instinct_attack_base_damage']);
    $this->assertSame('deadly_d10', $override['animal_instinct_attack_gains_trait']);
    $this->assertContains('true-beast', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHarmingHandsAddsBonusHarmDamageOverride(): void {
    $character = $this->buildCharacterWithFeat('harming-hands');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['harming-hands'];
    $this->assertTrue($override['harm_bonus_damage_equals_level']);
    $this->assertTrue($override['applies_to_font_and_regular_slots']);
    $this->assertContains('harming-hands', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDeadlySimplicityAddsFavoredWeaponDeadlyUpgrade(): void {
    $character = $this->buildCharacterWithFeat('deadly-simplicity');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['deadly-simplicity'];
    $this->assertTrue($override['deity_favored_simple_weapon_gains_deadly_d6']);
    $this->assertTrue($override['existing_deadly_trait_increases_one_step']);
    $this->assertContains('deadly-simplicity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEmblazonArmamentAddsExplorationAction(): void {
    $character = $this->buildCharacterWithFeat('emblazon-armament');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Emblazon Armament', $action['name']);
    $this->assertSame('10_minutes', $action['action_cost']);
    $this->assertSame(['weapon', 'shield'], $action['target_options']);
    $this->assertTrue($action['only_one_item_can_be_emblazoned_at_a_time']);
    $this->assertContains('emblazon-armament', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testChannelSmiteAddsStructuredStrikeAction(): void {
    $character = $this->buildCharacterWithFeat('channel-smite');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['channel_smite_available']);
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Channel Smite', $action['name']);
    $this->assertSame('melee_strike_plus_divine_font', $action['activity']);
    $this->assertSame('divine_font_slot', $action['expends_resource']);
    $this->assertTrue($action['use_higher_of_weapon_or_spell_dc']);
    $this->assertContains('channel-smite', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRaiseSymbolAddsShortBuffAction(): void {
    $character = $this->buildCharacterWithFeat('raise-symbol');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Raise Symbol', $action['name']);
    $this->assertSame(2, $action['spell_attack_roll_bonus']);
    $this->assertSame(2, $action['save_bonus_against_opposed_alignment_spells']);
    $this->assertContains('raise-symbol', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCantripExpansionClericAddsPreparedCantripCapacity(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('cantrip-expansion-cleric', []);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['cantrip-expansion-cleric'];
    $this->assertSame('prepared_cantrip_capacity_increase', $override['type']);
    $this->assertSame('divine', $override['tradition']);
    $this->assertSame(2, $override['extra_prepared_cantrips']);
    $this->assertContains('cantrip-expansion-cleric', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCantripExpansionSorcererAddsBloodlineTraditionCantrips(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('cantrip-expansion-sorcerer', [
      'selected_cantrips' => ['electric-arc', 'mage-hand'],
    ], [
      'subclass' => 'draconic',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['cantrip-expansion-sorcerer'];
    $this->assertSame('repertoire_cantrip_expansion', $override['type']);
    $this->assertSame('arcane', $override['tradition']);
    $this->assertSame('draconic', $override['bloodline']);
    $this->assertSame(['electric-arc', 'mage-hand'], $override['added_cantrips']);
    $this->assertSame(2, $override['extra_repertoire_cantrips']);
    $this->assertContains('cantrip-expansion-sorcerer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testStudiousCapacityAddsMixedRepertoireExpansion(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('studious-capacity', [
      'selected_cantrips' => ['daze', 'read-aura'],
      'selected_spell' => 'spirit-blast',
    ], [
      'level' => 12,
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['studious-capacity'];
    $this->assertSame('mixed_repertoire_expansion', $override['type']);
    $this->assertSame('occult', $override['tradition']);
    $this->assertSame(['daze', 'read-aura'], $override['added_cantrips']);
    $this->assertSame(6, $override['highest_available_rank']);
    $this->assertSame('spirit-blast', $override['added_highest_rank_spell']);
    $this->assertContains('studious-capacity', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGreaterVitalEvolutionAddsSpellbookSpellsAndInitiativeBonus(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('greater-vital-evolution', [
      'selected_spells' => ['fireball', 'haste'],
    ], [
      'level' => 8,
      'abilities' => [
        'intelligence' => 18,
      ],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['greater-vital-evolution'];
    $this->assertSame('spellbook_expansion', $override['type']);
    $this->assertSame('arcane', $override['tradition']);
    $this->assertSame(['fireball', 'haste'], $override['added_spells']);
    $this->assertSame('intelligence_modifier', $override['initiative_ability_bonus']);
    $this->assertSame(4, $effects['derived_adjustments']['initiative_bonus']);
    $this->assertContains('greater-vital-evolution', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSpellMasteryAddsMasteredFreeCastSpells(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('spell-mastery', [
      'selected_spells' => ['fireball', 'haste', 'disintegrate', 'teleport'],
    ], [
      'level' => 20,
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['spell-mastery'];
    $this->assertSame('mastered_spell_preparation', $override['type']);
    $this->assertSame(['fireball', 'haste', 'disintegrate', 'teleport'], $override['mastered_spells']);
    $this->assertSame(1, $override['free_casts_per_spell_per_day']);
    $this->assertTrue($override['does_not_count_against_prepared_slots']);
    $this->assertContains('spell-mastery', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInfinitePossibilitiesAddsTemporarySpellbookEntries(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('infinite-possibilities', [
      'selected_spells' => ['heal', 'synesthesia', 'wall-of-stone'],
    ], [
      'level' => 18,
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['infinite-possibilities'];
    $this->assertSame('temporary_spellbook_entries', $override['type']);
    $this->assertSame(['heal', 'synesthesia', 'wall-of-stone'], $override['added_spells']);
    $this->assertSame('next_daily_preparations', $override['temporary_until']);
    $this->assertTrue($override['prepared_from_entries_count_as_arcane']);
    $this->assertContains('infinite-possibilities', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testScrollSavantAddsDailyTemporaryScrollCreation(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('scroll-savant', [
      'selected_spells' => ['fireball', 'teleport'],
    ], [
      'level' => 10,
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['scroll-savant'];
    $this->assertSame('daily_temporary_scrolls', $override['type']);
    $this->assertSame(['fireball', 'teleport'], $override['created_scroll_spells']);
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('create_temporary_arcane_scrolls', $action['activity']);
    $this->assertSame(2, $action['scroll_count']);
    $this->assertContains('scroll-savant', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBondConservationAddsDrainBondedMetamagic(): void {
    $character = $this->buildCharacterWithFeat('bond-conservation');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $this->assertSame('bond-conservation', $augment['id']);
    $this->assertTrue($augment['drain_bonded_item_can_be_part_of_same_activity']);
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('metamagic', $action['activity']);
    $override = $effects['feat_overrides']['bond-conservation'];
    $this->assertTrue($override['combined_activity_with_next_arcane_spell']);
    $this->assertContains('bond-conservation', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUniversalVersatilityAddsBorrowedSchoolSpellAction(): void {
    $character = $this->buildCharacterWithFeat('universal-versatility');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['universal-versatility'];
    $this->assertSame('borrow_school_spell', $override['type']);
    $this->assertContains('abjuration', $override['available_schools']);
    $this->assertNotContains('universalist', $override['available_schools']);
    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('borrow_arcane_school_spell', $action['activity']);
    $this->assertContains('universal-versatility', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAdvancedSchoolSpellAddsSelectedSchoolFocusSpell(): void {
    $character = $this->buildCharacterWithFeat('advanced-school-spell', [], [
      'class' => 'wizard',
      'subclass' => 'evocation',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['advanced-school-spell'];
    $this->assertSame('advanced_school_focus_spell', $override['type']);
    $this->assertSame('evocation', $override['school_id']);
    $this->assertSame('thunderburst', $override['advanced_focus_spell']);
    $this->assertSame(1, $override['focus_pool_bonus']);
    $this->assertContains('advanced-school-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAlterRealityAddsWishLikeDailyAction(): void {
    $character = $this->buildCharacterWithFeat('alter-reality');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('alter-reality', $action['id']);
    $override = $effects['feat_overrides']['alter-reality'];
    $this->assertSame('wish_like_arcane_duplication', $override['type']);
    $this->assertSame(7, $override['spell_rank_cap']);
    $this->assertContains('alter-reality', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSpellCombinationAddsDailyPreparationCombinationAction(): void {
    $character = $this->buildCharacterWithFeat('spell-combination');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('combine_prepared_spells_into_dual_slot', $action['activity']);
    $override = $effects['feat_overrides']['spell-combination'];
    $this->assertSame('dual_prepared_slot', $override['type']);
    $this->assertTrue($override['casts_both_effects_simultaneously']);
    $this->assertContains('spell-combination', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMiracleAddsWishLikeDivineDailyAction(): void {
    $character = $this->buildCharacterWithFeat('miracle');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('miracle', $action['id']);
    $override = $effects['feat_overrides']['miracle'];
    $this->assertSame('wish_like_divine_duplication', $override['type']);
    $this->assertSame(9, $override['spell_rank_cap']);
    $this->assertSame('divine', $override['spell_tradition']);
    $this->assertContains('miracle', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSteadySpellcastingClericAddsDisruptionCheckOverride(): void {
    $character = $this->buildCharacterWithFeat('steady-spellcasting-cleric');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['steady-spellcasting-cleric'];
    $this->assertSame(15, $override['flat_check_to_avoid_spell_disruption']);
    $this->assertContains('steady-spellcasting-cleric', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDivineWeaponAddsPostFontDamageOverride(): void {
    $character = $this->buildCharacterWithFeat('divine-weapon');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['divine-weapon'];
    $this->assertSame('cast_divine_font_spell', $override['trigger']);
    $this->assertSame('1d4', $override['next_strike_with_favored_weapon_extra_damage']);
    $this->assertSame(['fire', 'radiant'], $override['damage_type_mapping']['positive']);
    $this->assertContains('divine-weapon', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSelectiveEnergyAddsBurstExclusionOverride(): void {
    $character = $this->buildCharacterWithFeat('selective-energy');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['selective-energy'];
    $this->assertSame(['heal_burst', 'harm_burst'], $override['applies_to']);
    $this->assertSame('max(1, wisdom_modifier)', $override['excluded_targets_formula']);
    $this->assertContains('selective-energy', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testVersatileFontAddsMixedFontPreparationOverride(): void {
    $character = $this->buildCharacterWithFeat('versatile-font');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['versatile-font'];
    $this->assertTrue($override['can_prepare_heal_and_harm_in_divine_font_slots']);
    $this->assertSame('half_rounded_up', $override['minimum_default_font_share']);
    $this->assertContains('versatile-font', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAlignArmamentAddsAlignmentWeaponAction(): void {
    $character = $this->buildCharacterWithFeat('align-armament');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Align Armament', $action['name']);
    $this->assertSame('imbue_weapon_alignment', $action['activity']);
    $this->assertSame('1_minute', $action['duration']);
    $this->assertSame('1d6', $action['extra_damage']);
    $this->assertContains('align-armament', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCastigatingWeaponAddsUndeadDamageOverride(): void {
    $character = $this->buildCharacterWithFeat('castigating-weapon');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['castigating-weapon'];
    $this->assertSame('hit_undead_with_deity_favored_weapon', $override['trigger']);
    $this->assertSame('max(1, wisdom_modifier)', $override['extra_positive_damage_formula']);
    $this->assertContains('castigating-weapon', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHeroicRecoveryAddsFreeRecoveryCheckOverride(): void {
    $character = $this->buildCharacterWithFeat('heroic-recovery');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['heroic-recovery'];
    $this->assertSame('cast_heal_rank_3_or_higher_on_creature_at_0_hp', $override['trigger']);
    $this->assertTrue($override['target_gets_free_recovery_check']);
    $this->assertContains('heroic-recovery', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testReplenishingStrikeAddsDailyFontRecoveryTrigger(): void {
    $character = $this->buildCharacterWithFeat('replenishing-strike');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Replenishing Strike', $action['name']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('restore_divine_font_slot', $action['activity']);
    $this->assertContains('replenishing-strike', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSharedReplenishmentRedirectsCommunalHealing(): void {
    $character = $this->buildCharacterWithFeat('shared-replenishment');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['shared-replenishment'];
    $this->assertSame('communal-healing', $override['modifies_feat']);
    $this->assertTrue($override['bonus_healing_goes_to_healed_ally_instead_of_self']);
    $this->assertContains('shared-replenishment', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDivineRebuttalAddsCounteractReaction(): void {
    $character = $this->buildCharacterWithFeat('divine-rebuttal');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Divine Rebuttal', $action['name']);
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('once_per_10_minutes', $action['frequency']);
    $this->assertSame('counteract_triggering_spell', $action['activity']);
    $this->assertContains('divine-rebuttal', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEchoingChannelAddsChannelSmiteBurstOverride(): void {
    $character = $this->buildCharacterWithFeat('echoing-channel');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['echoing-channel'];
    $this->assertSame('channel-smite', $override['modifies_feat']);
    $this->assertSame(5, $override['secondary_burst_radius_feet']);
    $this->assertSame('half', $override['secondary_burst_damage_fraction']);
    $this->assertContains('echoing-channel', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEmblazonEnergyAddsPersistentCritDamageOverride(): void {
    $character = $this->buildCharacterWithFeat('emblazon-energy');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['emblazon-energy'];
    $this->assertTrue($override['requires_emblazoned_weapon']);
    $this->assertSame('1d4', $override['persistent_damage']);
    $this->assertSame('fire', $override['damage_type_mapping']['holy']);
    $this->assertContains('emblazon-energy', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUseElixirAddsOneActionAdministration(): void {
    $character = $this->buildCharacterWithFeat('use-elixir');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Use Elixir', $action['name']);
    $this->assertSame('administer_held_potion_or_elixir', $action['activity']);
    $this->assertSame('willing_adjacent_creature', $action['target']);
    $this->assertContains('use-elixir', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAvatarsAudienceAddsDailyVisionAction(): void {
    $character = $this->buildCharacterWithFeat('avatar-s-audience');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame("Avatar's Audience", $action['name']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('receive_divine_vision', $action['activity']);
    $this->assertTrue($action['automatic_success']);
    $this->assertSame(6, $action['max_yes_no_questions']);
    $this->assertContains('avatar-s-audience', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testExtendedChannelAddsMetamagicBurstIncrease(): void {
    $character = $this->buildCharacterWithFeat('extended-channel');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $this->assertSame('Extended Channel', $augment['name']);
    $this->assertSame(['heal_burst', 'harm_burst'], $augment['applies_to']);
    $this->assertSame(60, $augment['three_action_burst_radius_feet']);
    $this->assertContains('extended-channel', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSwiftBanishmentAddsTriggeredFreeSpellAction(): void {
    $character = $this->buildCharacterWithFeat('swift-banishment');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Swift Banishment', $action['name']);
    $this->assertSame('free', $action['action_cost']);
    $this->assertSame('critically_hit_creature_with_strike', $action['trigger']);
    $this->assertSame('prepared_banishment_spell_slot', $action['expends_resource']);
    $this->assertContains('swift-banishment', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAvatarAddsDailyTransformationAction(): void {
    $character = $this->buildCharacterWithFeat('avatar');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Avatar', $action['name']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('transform_into_deific_avatar', $action['activity']);
    $this->assertSame('large', $action['size_becomes']);
    $this->assertSame(60, $action['fly_speed_feet']);
    $this->assertContains('avatar', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLeshyFamiliarDruidAddsLeshyCreationGrant(): void {
    $character = $this->buildCharacterWithFeat('leshy-familiar-druid');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('familiar_creation', $grant['selection_type']);
    $override = $effects['feat_overrides']['leshy-familiar-druid'];
    $this->assertSame('leshy', $override['familiar_type']);
    $this->assertTrue($override['can_regain_plant_trait']);
    $this->assertContains('leshy-familiar-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testReachSpellDruidAddsMetamagicAugment(): void {
    $character = $this->buildCharacterWithFeat('reach-spell-druid');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $this->assertSame('Reach Spell', $augment['name']);
    $this->assertSame(30, $augment['range_bonus_feet']);
    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('metamagic', $action['activity']);
    $this->assertContains('reach-spell-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWidenSpellDruidAddsAreaAugment(): void {
    $character = $this->buildCharacterWithFeat('widen-spell-druid');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $this->assertSame(['burst', 'cone', 'line'], $augment['eligible_shapes']);
    $this->assertSame(5, $augment['burst_radius_bonus_feet']);
    $this->assertContains('widen-spell-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrderExplorerRequestsSecondOrderSelectionWhenMissing(): void {
    $character = $this->buildCharacterWithFeat('order-explorer', [], [
      'class' => 'druid',
      'subclass' => 'animal',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('order-explorer', $grant['source_feat']);
    $this->assertSame('druid_second_order_choice', $grant['selection_type']);
    $this->assertContains('order-explorer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrderExplorerRequestsSelectionWhenPrimaryOrderIsMissing(): void {
    $character = $this->buildCharacterWithFeatSelection('order-explorer', [
      'selected_order' => 'leaf',
    ], [
      'class' => 'druid',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertArrayNotHasKey('order-explorer', $effects['feat_overrides']);
    $this->assertSame('druid_second_order_choice', $effects['selection_grants'][0]['selection_type']);
    $this->assertContains('order-explorer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOrderExplorerPersistsSecondOrderBenefitsAndAnathemaScope(): void {
    $character = $this->buildCharacterWithFeatSelection('order-explorer', [
      'selected_order' => 'leaf',
    ], [
      'class' => 'druid',
      'subclass' => 'animal',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['order-explorer'];
    $this->assertSame('druid_second_order_unlock', $override['type']);
    $this->assertSame('animal', $override['primary_order']);
    $this->assertSame('leaf', $override['selected_second_order']);
    $this->assertSame('Order of the Leaf', $override['selected_second_order_label']);
    $this->assertSame(2, $override['focus_pool_bonus']);
    $this->assertTrue($override['order_spell_not_granted']);
    $this->assertSame('goodberry', $override['suppressed_order_spell']);
    $this->assertSame(['leshy-familiar-druid'], $override['secondary_order_granted_feat_access']);
    $this->assertSame('Allowing wanton destruction of plants, using fire recklessly in natural settings, harvesting plants without replanting or giving back.', $override['secondary_order_anathema']);
    $this->assertTrue($override['secondary_order_runtime_flags']['leaf_order_access']);
    $this->assertTrue($override['secondary_order_runtime_flags']['leaf_order_feat_access']);
    $this->assertSame(['leaf'], $override['unlocked_order_prerequisites']);
    $this->assertSame('remove_secondary_order_feats_only', $override['secondary_order_anathema_scope']);
    $this->assertContains('order-explorer', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testStormBornAddsWeatherOverrides(): void {
    $character = $this->buildCharacterWithFeat('storm-born');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['storm-born'];
    $this->assertTrue($override['ignore_natural_weather_penalties']);
    $this->assertTrue($override['not_buffeted_or_blinded_by_wind']);
    $this->assertContains('storm-born', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWildShapeDruidAddsFocusSpellAction(): void {
    $character = $this->buildCharacterWithFeat('wild-shape-druid');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Wild Shape', $action['name']);
    $this->assertSame('focus_spell', $action['activity']);
    $this->assertSame('once_per_hour', $action['wild_order_free_cast_frequency']);
    $override = $effects['feat_overrides']['wild-shape-druid'];
    $this->assertSame(1, $override['grants_focus_point']);
    $this->assertContains('wild-shape-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFamiliarDruidAddsFamiliarCreationGrant(): void {
    $character = $this->buildCharacterWithFeat('familiar-druid');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('familiar_creation', $grant['selection_type']);
    $this->assertContains('familiar-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAnimalCompanionDruidAddsCompanionCreationGrant(): void {
    $character = $this->buildCharacterWithFeat('animal-companion-druid');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('animal-companion-druid', $grant['source_feat']);
    $this->assertSame('animal_companion_choice', $grant['selection_type']);
    $this->assertContains('animal-companion-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSpecializedCompanionDruidAddsSpecializationGrantWhenMissing(): void {
    $character = $this->buildCharacterWithFeat('specialized-companion-druid');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('animal_companion_specialization_choice', $grant['selection_type']);
    $this->assertSame('mature', $effects['feat_overrides']['specialized-companion-druid']['animal_companion_stage']);
    $this->assertContains('specialized-companion-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGoodberryAddsFocusSpellAction(): void {
    $character = $this->buildCharacterWithFeat('goodberry');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('goodberry', $action['id']);
    $this->assertSame('focus_spell', $action['activity']);
    $this->assertTrue($action['creates_healing_and_sustaining_berry']);
    $this->assertContains('goodberry', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHealAnimalAddsAnimalHealingFocusSpell(): void {
    $character = $this->buildCharacterWithFeat('heal-animal');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('heal_animal', $action['spell_reference']);
    $this->assertSame(['animal_companion', 'animal'], $action['preferred_targets']);
    $this->assertContains('heal-animal', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTempestSurgeAddsStormFocusSpell(): void {
    $character = $this->buildCharacterWithFeat('tempest-surge');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('tempest_surge', $action['spell_reference']);
    $this->assertSame(['electricity', 'bludgeoning'], $action['damage_types']);
    $this->assertContains('tempest-surge', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSteadySpellcastingDruidAddsDisruptionCheckOverride(): void {
    $character = $this->buildCharacterWithFeat('steady-spellcasting-druid');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['steady-spellcasting-druid'];
    $this->assertSame(15, $override['flat_check_to_avoid_spell_disruption']);
    $this->assertContains('steady-spellcasting-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCallOfTheWildAddsSummoningAction(): void {
    $character = $this->buildCharacterWithFeat('call-of-the-wild');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('summon_bound_natural_servant', $action['activity']);
    $this->assertSame(['animal', 'elemental', 'plant'], $action['eligible_traits']);
    $this->assertSame('24_hours', $action['duration']);
    $this->assertContains('call-of-the-wild', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testEnhancedFamiliarDruidAddsMoreAbilities(): void {
    $character = $this->buildCharacterWithFeat('enhanced-familiar-druid');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['enhanced-familiar-druid'];
    $this->assertSame(2, $override['additional_familiar_abilities_per_day']);
    $this->assertContains('enhanced-familiar-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFerociousShapeAddsLargeAnimalForms(): void {
    $character = $this->buildCharacterWithFeat('ferocious-shape');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['ferocious-shape'];
    $this->assertSame('wild-shape-druid', $override['modifies_feat']);
    $this->assertTrue($override['wild_shape_unlocks_large_animal_forms']);
    $this->assertContains('ferocious-shape', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSoaringShapeAddsWingedWildForms(): void {
    $character = $this->buildCharacterWithFeat('soaring-shape');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['soaring-shape'];
    $this->assertTrue($override['wild_shape_unlocks_winged_forms']);
    $this->assertTrue($override['wild_shape_forms_gain_fly_speed']);
    $this->assertContains('soaring-shape', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWindCallerAddsStormwindFlightSpell(): void {
    $character = $this->buildCharacterWithFeat('wind-caller');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('stormwind_flight', $action['spell_reference']);
    $this->assertContains('wind-caller', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCurrentSpellAddsColdAndElectricityRangeAugment(): void {
    $character = $this->buildCharacterWithFeat('current-spell');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $this->assertSame(['electricity', 'cold'], $augment['requires_traits']);
    $this->assertSame(30, $augment['range_bonus_feet']);
    $this->assertContains('current-spell', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGreenEmpathyAddsPlantWildEmpathyOverride(): void {
    $character = $this->buildCharacterWithFeat('green-empathy');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['green-empathy'];
    $this->assertTrue($override['wild_empathy_applies_to_plants']);
    $this->assertTrue($override['mindless_plants_are_immune']);
    $this->assertContains('green-empathy', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInsectShapeAddsTinyWildForms(): void {
    $character = $this->buildCharacterWithFeat('insect-shape');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['insect-shape'];
    $this->assertSame('wild-shape-druid', $override['modifies_feat']);
    $this->assertTrue($override['wild_shape_unlocks_tiny_insect_forms']);
    $this->assertContains('insect-shape', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testStormRetributionAddsReactionTempestSurge(): void {
    $character = $this->buildCharacterWithFeat('storm-retribution');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('reaction', $action['action_cost']);
    $this->assertSame('cast_tempest_surge', $action['activity']);
    $this->assertSame('creature_deals_damage_to_you_with_melee_attack', $action['trigger']);
    $this->assertContains('storm-retribution', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAerialFormImprovesSoaringShape(): void {
    $character = $this->buildCharacterWithFeat('aerial-form');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['aerial-form'];
    $this->assertSame('soaring-shape', $override['modifies_feat']);
    $this->assertTrue($override['wild_shape_aerial_forms_improved']);
    $this->assertContains('aerial-form', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTimelessNatureAddsAgingImmunityOverride(): void {
    $character = $this->buildCharacterWithFeat('deadly-simplicity-druid');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['deadly-simplicity-druid'];
    $this->assertTrue($override['ignore_aging_ability_penalties']);
    $this->assertTrue($override['cannot_die_of_old_age']);
    $this->assertContains('deadly-simplicity-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testThousandFacesAddsHumanoidForms(): void {
    $character = $this->buildCharacterWithFeat('thousand-faces');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['thousand-faces'];
    $this->assertSame('wild-shape-druid', $override['modifies_feat']);
    $this->assertTrue($override['wild_shape_unlocks_small_and_medium_humanoids']);
    $this->assertContains('thousand-faces', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWoodlandStrideAddsNaturalTerrainFlags(): void {
    $character = $this->buildCharacterWithFeat('woodland-stride');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['ignore_difficult_terrain_natural_undergrowth']);
    $this->assertTrue($effects['derived_adjustments']['flags']['ignore_natural_plant_hazards']);
    $this->assertContains('woodland-stride', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testOverwhelmingEnergyDruidAddsResistanceBypassAugment(): void {
    $character = $this->buildCharacterWithFeat('overwhelming-energy-druid');
    $effects = $this->manager->buildEffectState($character);

    $augment = $effects['spell_augments']['metamagic'][0];
    $this->assertSame(['acid', 'cold', 'electricity', 'fire', 'sonic'], $augment['eligible_damage_types']);
    $this->assertSame(10, $augment['ignore_resistance_up_to']);
    $this->assertContains('overwhelming-energy-druid', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPlantShapeAddsPlantForms(): void {
    $character = $this->buildCharacterWithFeat('plant-shape');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['plant-shape'];
    $this->assertTrue($override['wild_shape_unlocks_small_and_medium_plant_forms']);
    $this->assertContains('plant-shape', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPrimalFocusAddsSecondDailyRefocus(): void {
    $character = $this->buildCharacterWithFeat('primal-focus');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['primal-focus'];
    $this->assertSame(2, $override['max_refocuses_per_day']);
    $this->assertSame(1, $override['focus_points_restored_per_refocus']);
    $this->assertContains('primal-focus', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testElementalShapeAddsElementalForms(): void {
    $character = $this->buildCharacterWithFeat('elemental-shape');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['elemental-shape'];
    $this->assertSame(['air', 'earth', 'fire', 'water'], $override['wild_shape_unlocks_elemental_forms']);
    $this->assertSame(['small', 'medium', 'large'], $override['wild_shape_elemental_size_options']);
    $this->assertContains('elemental-shape', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPristineWeaponAddsMaterialOverrides(): void {
    $character = $this->buildCharacterWithFeat('pristine-weapon');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['pristine-weapon'];
    $this->assertSame(['cold_iron', 'silver'], $override['weapons_count_as_materials']);
    $this->assertContains('pristine-weapon', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testStormOrderResilienceAddsResistanceAndSwimSpeed(): void {
    $character = $this->buildCharacterWithFeat('storm-order-resilience');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['storm-order-resilience'];
    $this->assertSame('electricity', $override['resistance']['damage_type']);
    $this->assertSame(10, $override['resistance']['value']);
    $this->assertSame(30, $override['grant_swim_speed_feet']);
    $this->assertContains('storm-order-resilience', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDragonShapeAddsDragonWildForms(): void {
    $character = $this->buildCharacterWithFeat('dragon-shape');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['dragon-shape'];
    $this->assertTrue($override['wild_shape_unlocks_large_dragon_form']);
    $this->assertTrue($override['dragon_form_includes_breath_weapon']);
    $this->assertContains('dragon-shape', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrueShapeshifterAddsDailyReshapeAction(): void {
    $character = $this->buildCharacterWithFeat('true-shapeshifter');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('True Shapeshifter', $action['name']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('change_wild_shape_form', $action['activity']);
    $this->assertContains('true-shapeshifter', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testMonstrosityShapeAddsGargantuanForms(): void {
    $character = $this->buildCharacterWithFeat('monstrosity-shape');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['monstrosity-shape'];
    $this->assertTrue($override['wild_shape_unlocks_gargantuan_monstrosity_forms']);
    $this->assertContains('monstrosity-shape', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPrimalWellspringAddsThirdDailyRefocus(): void {
    $character = $this->buildCharacterWithFeat('primal-wellspring');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['primal-wellspring'];
    $this->assertSame(3, $override['max_refocuses_per_day']);
    $this->assertSame(1, $override['focus_points_restored_per_refocus']);
    $this->assertContains('primal-wellspring', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testInvokeDisasterAddsOrderSpellAction(): void {
    $character = $this->buildCharacterWithFeat('invoke-disaster');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('invoke_disaster', $action['spell_reference']);
    $this->assertSame('focus_spell', $action['activity']);
    $this->assertContains('invoke-disaster', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPerfectFormControlAddsCastingAndPenaltyOverride(): void {
    $character = $this->buildCharacterWithFeat('perfect-form-control');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['perfect-form-control'];
    $this->assertTrue($override['can_cast_spells_while_wild_shaped_if_form_allows']);
    $this->assertSame(2, $override['ignore_wild_shape_metamagic_spell_level_penalty']);
    $this->assertContains('perfect-form-control', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testNaturesAegisAddsRegenerationAndResistance(): void {
    $character = $this->buildCharacterWithFeat('natures-aegis');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['natures-aegis'];
    $this->assertSame(5, $override['regeneration']);
    $this->assertSame(['fire', 'acid'], $override['regeneration_deactivated_by']);
    $this->assertSame(10, $override['physical_resistance_bonus_against_natural_sources']);
    $this->assertContains('natures-aegis', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLeylineConduitAddsDailySpellSlotBuff(): void {
    $character = $this->buildCharacterWithFeat('leyline-conduit');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Leyline Conduit', $action['name']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('10_minutes', $action['duration']);
    $this->assertTrue($action['grants_extra_highest_rank_primal_slot']);
    $this->assertContains('leyline-conduit', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBreathControlAddsHoldBreathAndInhaledThreatBenefits(): void {
    $character = $this->buildCharacterWithFeat('breath-control');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['breath-control'];
    $this->assertSame(25, $override['hold_breath_multiplier']);
    $modifier = $effects['conditional_modifiers']['saving_throws'][0];
    $this->assertSame(1, $modifier['bonus']);
    $this->assertSame('against inhaled threats', $modifier['context']);
    $this->assertContains('breath-control', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDiehardRaisesDyingDeathThreshold(): void {
    $character = $this->buildCharacterWithFeat('diehard');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['diehard'];
    $this->assertSame(5, $override['die_from_dying_value']);
    $this->assertContains('diehard', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFastRecoveryAddsRestAndMaladyRecoveryOverrides(): void {
    $character = $this->buildCharacterWithFeat('fast-recovery');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['fast-recovery'];
    $this->assertSame(2, $override['rest_healing_multiplier']);
    $this->assertSame(2, $override['fortitude_success_reduces_disease_or_poison_stage_by']);
    $this->assertContains('fast-recovery', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFeatherStepAddsLightTerrainMovementFlag(): void {
    $character = $this->buildCharacterWithFeat('feather-step');
    $effects = $this->manager->buildEffectState($character);

    $this->assertTrue($effects['derived_adjustments']['flags']['ignore_difficult_terrain_light']);
    $this->assertContains('feather-step', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testFleetAddsFiveFootSpeedBonus(): void {
    $character = $this->buildCharacterWithFeat('fleet');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame(5, $effects['derived_adjustments']['speed_bonus']);
    $this->assertContains('fleet', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testIncredibleInitiativeAddsInitiativeBonus(): void {
    $character = $this->buildCharacterWithFeat('incredible-initiative');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame(2, $effects['derived_adjustments']['initiative_bonus']);
    $this->assertContains('incredible-initiative', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testRideRemovesMountedCheckAndAttackPenalty(): void {
    $character = $this->buildCharacterWithFeat('ride');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['ride'];
    $this->assertTrue($override['command_an_animal_mount_auto_succeeds']);
    $this->assertTrue($override['ignore_mounted_attack_penalty']);
    $this->assertContains('ride', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testShieldBlockAddsDamageReductionReaction(): void {
    $character = $this->buildCharacterWithFeat('shield-block');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('Shield Block', $action['name']);
    $this->assertSame('reduce_damage_with_shield', $action['activity']);
    $this->assertSame('shield_hardness', $action['prevent_damage_up_to']);
    $this->assertContains('shield-block', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testToughnessAddsHpAndRecoveryBenefit(): void {
    $character = $this->buildCharacterWithFeat('toughness');
    $effects = $this->manager->buildEffectState($character, ['level' => 7]);

    $this->assertSame(7, $effects['derived_adjustments']['hp_max_bonus']);
    $this->assertSame('9 + dying_value', $effects['feat_overrides']['toughness']['recovery_check_dc_formula']);
    $this->assertContains('toughness', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testHirelingManagerAddsHirelingBonus(): void {
    $character = $this->buildCharacterWithFeat('hireling-manager');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['hireling-manager'];
    $this->assertSame(2, $override['hireling_skill_check_bonus']);
    $this->assertSame('circumstance', $override['bonus_type']);
    $this->assertContains('hireling-manager', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testImprovisedRepairAddsTemporaryPatchAction(): void {
    $character = $this->buildCharacterWithFeat('improvised-repair');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame(3, $action['action_cost']);
    $this->assertSame('temporary_item_patch', $action['activity']);
    $this->assertSame('broken_nonmagical_item', $action['target_requirement']);
    $this->assertContains('improvised-repair', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testKeenFollowerImprovesFollowTheExpertBonuses(): void {
    $character = $this->buildCharacterWithFeat('keen-follower');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['keen-follower'];
    $this->assertSame('follow_the_expert', $override['modifies_activity']);
    $this->assertSame(3, $override['expert_leader_bonus']);
    $this->assertSame(4, $override['master_leader_bonus']);
    $this->assertContains('keen-follower', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPickUpThePaceAddsAdditionalHustleTime(): void {
    $character = $this->buildCharacterWithFeat('pick-up-the-pace');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['pick-up-the-pace'];
    $this->assertSame(20, $override['additional_hustle_minutes']);
    $this->assertTrue($override['group_hustle_cap_uses_highest_constitution_member']);
    $this->assertContains('pick-up-the-pace', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPrescientPlannerAddsRetroactivePurchaseOverride(): void {
    $character = $this->buildCharacterWithFeat('prescient-planner');
    $effects = $this->manager->buildEffectState($character, ['level' => 8]);

    $override = $effects['feat_overrides']['prescient-planner'];
    $this->assertSame(1, $override['uses_per_shopping_opportunity']);
    $this->assertTrue($override['retroactive_purchase_allowed']);
    $this->assertSame('floor(level/2)', $override['item_requirements']['level_max_formula']);
    $this->assertContains('prescient-planner', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSkitterAddsHalfSpeedCrawlOverride(): void {
    $character = $this->buildCharacterWithFeat('skitter');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['skitter'];
    $this->assertSame('half_speed', $override['crawl_speed_formula']);
    $this->assertContains('skitter', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testThoroughSearchAddsCarefulSearchBonus(): void {
    $character = $this->buildCharacterWithFeat('thorough-search');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['thorough-search'];
    $this->assertSame(2, $override['search_time_multiplier']);
    $this->assertSame(2, $override['seek_bonus_when_searching_carefully']);
    $this->assertContains('thorough-search', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testPrescientConsumableExtendsPrescientPlanner(): void {
    $character = $this->buildCharacterWithFeat('prescient-consumable');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['prescient-consumable'];
    $this->assertSame('prescient-planner', $override['modifies_feat']);
    $this->assertTrue($override['retroactive_purchase_allows_consumables']);
    $this->assertContains('prescient-consumable', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testSupertasterAddsPoisonDetectionAndRecallBonus(): void {
    $character = $this->buildCharacterWithFeat('supertaster');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['supertaster'];
    $this->assertTrue($override['secret_perception_check_when_eating_or_drinking_near_poison']);
    $this->assertSame(2, $override['recall_knowledge_bonus_when_taste_relevant']);
    $this->assertContains('supertaster', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAHomeInEveryPortAddsDowntimeLodgingAction(): void {
    $character = $this->buildCharacterWithFeat('a-home-in-every-port');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('secure_lodging', $action['activity']);
    $this->assertSame(7, $action['max_total_occupants']);
    $this->assertSame('24_hours', $action['duration']);
    $this->assertContains('a-home-in-every-port', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCaravanLeaderImprovesGroupHustle(): void {
    $character = $this->buildCharacterWithFeat('caravan-leader');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['caravan-leader'];
    $this->assertSame('hustle', $override['modifies_activity']);
    $this->assertTrue($override['group_uses_longest_solo_hustle_limit']);
    $this->assertSame(20, $override['additional_group_hustle_minutes']);
    $this->assertContains('caravan-leader', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testIncredibleScoutImprovesScoutBonus(): void {
    $character = $this->buildCharacterWithFeat('incredible-scout');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['incredible-scout'];
    $this->assertSame('scout', $override['modifies_activity']);
    $this->assertSame(2, $override['allies_initiative_bonus']);
    $this->assertContains('incredible-scout', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTruePerceptionAddsAlwaysOnTrueSeeing(): void {
    $character = $this->buildCharacterWithFeat('true-perception');
    $effects = $this->manager->buildEffectState($character);

    $sense = $effects['senses'][0];
    $this->assertSame('true_seeing', $sense['type']);
    $this->assertTrue($sense['always_on']);
    $this->assertSame('perception', $sense['counteract_modifier']);
    $this->assertContains('true-perception', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testTrueBloodAddsDualBloodMagicOverride(): void {
    $character = $this->buildCharacterWithFeat('true-blood');
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['true-blood'];
    $this->assertTrue($override['blood_magic_automatically_triggers_on_bloodline_spell']);
    $this->assertTrue($override['blood_magic_can_apply_to_caster_and_target_simultaneously']);
    $this->assertContains('true-blood', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBloodlineConduitAddsDailyTenthRankSlot(): void {
    $character = $this->buildCharacterWithFeat('bloodline-conduit');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['per_long_rest'][0];
    $this->assertSame('Bloodline Conduit', $action['name']);
    $this->assertSame('once_per_long_rest', $action['frequency']);
    $this->assertSame('gain_extra_10th_level_spell_slot', $action['activity']);
    $this->assertTrue($action['heighten_any_repertoire_spell_to_10th']);
    $this->assertContains('bloodline-conduit', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfDreamsAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-dreams');
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('veil-of-dreams', $action['id']);
    $override = $effects['feat_overrides']['lesson-of-dreams'];
    $this->assertSame('basic', $override['lesson_tier']);
    $this->assertSame('sleep', $override['familiar_learns_spell']);
    $this->assertContains('lesson-of-dreams', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfLifeAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-life');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('life-boost', $effects['available_actions']['at_will'][0]['id']);
    $this->assertSame('spirit-link', $effects['feat_overrides']['lesson-of-life']['familiar_learns_spell']);
    $this->assertContains('lesson-of-life', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfElementsReadsLeveledFeatParamsAndAddsFamiliarSpell(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('lesson-of-elements', [
      'selected_spell' => 'hydraulic-push',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $action = $effects['available_actions']['at_will'][0];
    $this->assertSame('elemental-betrayal', $action['id']);
    $override = $effects['feat_overrides']['lesson-of-elements'];
    $this->assertSame('basic', $override['lesson_tier']);
    $this->assertSame('hydraulic-push', $override['familiar_learns_spell']);
    $this->assertContains('lesson-of-elements', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfElementsAddsPendingChoiceWithoutSpellSelection(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-elements');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('lesson-of-elements', $grant['source_feat']);
    $this->assertSame('lesson_of_elements_spell_choice', $grant['selection_type']);
    $this->assertContains('lesson-of-elements', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfProtectionAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-protection');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('blood-ward', $effects['available_actions']['at_will'][0]['id']);
    $this->assertSame('mage-armor', $effects['feat_overrides']['lesson-of-protection']['familiar_learns_spell']);
    $this->assertContains('lesson-of-protection', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfVengeanceAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-vengeance');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('needle-of-vengeance', $effects['available_actions']['at_will'][0]['id']);
    $this->assertSame('phantom-pain', $effects['feat_overrides']['lesson-of-vengeance']['familiar_learns_spell']);
    $this->assertContains('lesson-of-vengeance', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfMischiefAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-mischief');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('deceivers-cloak', $effects['available_actions']['at_will'][0]['id']);
    $this->assertSame('mad-monkeys', $effects['feat_overrides']['lesson-of-mischief']['familiar_learns_spell']);
    $this->assertContains('lesson-of-mischief', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfShadowAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-shadow');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('malicious-shadow', $effects['available_actions']['at_will'][0]['id']);
    $this->assertSame('chilling-darkness', $effects['feat_overrides']['lesson-of-shadow']['familiar_learns_spell']);
    $this->assertContains('lesson-of-shadow', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfSnowAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-snow');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('personal-blizzard', $effects['available_actions']['at_will'][0]['id']);
    $this->assertSame('wall-of-wind', $effects['feat_overrides']['lesson-of-snow']['familiar_learns_spell']);
    $this->assertContains('lesson-of-snow', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfDeathAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-death');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('curse-of-death', $effects['available_actions']['at_will'][0]['id']);
    $this->assertSame('raise-dead', $effects['feat_overrides']['lesson-of-death']['familiar_learns_spell']);
    $this->assertContains('lesson-of-death', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testLessonOfRenewalAddsHexAndFamiliarSpell(): void {
    $character = $this->buildCharacterWithFeat('lesson-of-renewal');
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('restorative-moment', $effects['available_actions']['at_will'][0]['id']);
    $this->assertSame('field-of-life', $effects['feat_overrides']['lesson-of-renewal']['familiar_learns_spell']);
    $this->assertContains('lesson-of-renewal', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCannyAcumenAddsPendingChoiceWithoutSelection(): void {
    $character = $this->buildCharacterWithFeat('canny-acumen');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('canny-acumen', $grant['source_feat']);
    $this->assertSame('proficiency_upgrade_choice', $grant['selection_type']);
    $this->assertContains('canny-acumen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCannyAcumenUpgradesSelectedTrainedProficiency(): void {
    $character = $this->buildCharacterWithFeatSelection('canny-acumen', [
      'selected_proficiency' => 'fortitude',
    ], [
      'class' => 'wizard',
      'class_proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['proficiencies'][0];
    $this->assertSame('saving_throw', $grant['category']);
    $this->assertSame('fortitude', $grant['target']);
    $this->assertSame('expert', $grant['rank']);
    $override = $effects['feat_overrides']['canny-acumen'];
    $this->assertSame('fortitude', $override['selected_proficiency']);
    $this->assertSame('trained', $override['current_rank']);
    $this->assertSame('expert', $override['granted_rank']);
    $this->assertSame(1, $override['active_at_level']);
    $this->assertContains('canny-acumen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCannyAcumenDefersMasterUpgradeUntilLevelSeventeen(): void {
    $character = $this->buildCharacterWithFeatSelection('canny-acumen', [
      'selected_proficiency' => 'will',
    ], [
      'class' => 'wizard',
      'level' => 3,
      'class_proficiencies' => [
        'perception' => 'Trained',
        'fortitude' => 'Trained',
        'reflex' => 'Trained',
        'will' => 'Expert',
      ],
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame([], $effects['training_grants']['proficiencies']);
    $override = $effects['feat_overrides']['canny-acumen'];
    $this->assertSame('will', $override['selected_proficiency']);
    $this->assertSame('expert', $override['current_rank']);
    $this->assertSame('master', $override['granted_rank']);
    $this->assertSame(17, $override['active_at_level']);
    $this->assertContains('canny-acumen', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testArmorProficiencyGrantsNextArmorTier(): void {
    $character = $this->buildCharacterWithFeat('armor-proficiency', [], [
      'class' => 'wizard',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['proficiencies'][0];
    $this->assertSame('armor', $grant['category']);
    $this->assertSame('light', $grant['target']);
    $this->assertSame('trained', $grant['rank']);
    $override = $effects['feat_overrides']['armor-proficiency'];
    $this->assertSame('armor_tier_upgrade', $override['type']);
    $this->assertSame('light', $override['granted_tier']);
    $this->assertContains('armor-proficiency', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testArmorProficiencyLeavesHeavyArmorClassesUnchanged(): void {
    $character = $this->buildCharacterWithFeat('armor-proficiency', [], [
      'class' => 'fighter',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame([], $effects['training_grants']['proficiencies']);
    $this->assertArrayNotHasKey('armor-proficiency', $effects['feat_overrides']);
    $this->assertContains('armor-proficiency', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWeaponProficiencyGrantsSimpleWeaponsForClassesWithoutSimpleTraining(): void {
    $character = $this->buildCharacterWithFeat('weapon-proficiency', [], [
      'class' => 'wizard',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['proficiencies'][0];
    $this->assertSame('weapon', $grant['category']);
    $this->assertSame('simple', $grant['target']);
    $this->assertSame('trained', $grant['rank']);
    $override = $effects['feat_overrides']['weapon-proficiency'];
    $this->assertSame('weapon_category_upgrade', $override['type']);
    $this->assertSame('simple', $override['granted_target']);
    $this->assertContains('weapon-proficiency', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWeaponProficiencyGrantsMartialWeaponsForSimpleWeaponClasses(): void {
    $character = $this->buildCharacterWithFeat('weapon-proficiency', [], [
      'class' => 'rogue',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['proficiencies'][0];
    $this->assertSame('weapon', $grant['category']);
    $this->assertSame('martial', $grant['target']);
    $this->assertSame('trained', $grant['rank']);
    $override = $effects['feat_overrides']['weapon-proficiency'];
    $this->assertSame('weapon_category_upgrade', $override['type']);
    $this->assertSame('martial', $override['granted_target']);
    $this->assertContains('weapon-proficiency', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWeaponProficiencyRequestsAdvancedWeaponChoiceWhenClassAlreadyHasMartialTraining(): void {
    $character = $this->buildCharacterWithFeat('weapon-proficiency', [], [
      'class' => 'ranger',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('weapon-proficiency', $grant['source_feat']);
    $this->assertSame('advanced_weapon_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('weapon-proficiency', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWeaponProficiencyAppliesSelectedAdvancedWeaponFromCharacterCreationSelection(): void {
    $character = $this->buildCharacterWithFeatSelection('weapon-proficiency', [
      'selected_weapon_id' => 'dwarven-waraxe',
    ], [
      'class' => 'ranger',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['proficiencies'][0];
    $this->assertSame('weapon', $grant['category']);
    $this->assertSame('dwarven-waraxe', $grant['target']);
    $this->assertSame('trained', $grant['rank']);
    $override = $effects['feat_overrides']['weapon-proficiency'];
    $this->assertSame('advanced_weapon_training', $override['type']);
    $this->assertSame('dwarven-waraxe', $override['selected_weapon_id']);
    $this->assertSame('Dwarven Waraxe', $override['selected_weapon_name']);
    $this->assertContains('weapon-proficiency', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testWeaponProficiencyAppliesSelectedAdvancedWeaponFromLeveledFeatParams(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('weapon-proficiency', [
      'selected_weapon_id' => 'flickmace',
    ], [
      'class' => 'ranger',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['proficiencies'][0];
    $this->assertSame('flickmace', $grant['target']);
    $override = $effects['feat_overrides']['weapon-proficiency'];
    $this->assertSame('flickmace', $override['selected_weapon_id']);
    $this->assertSame('Flickmace', $override['selected_weapon_name']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUnconventionalWeaponryRequestsWeaponSelectionWhenMissing(): void {
    $character = $this->buildCharacterWithFeat('unconventional-weaponry');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('unconventional-weaponry', $grant['source_feat']);
    $this->assertSame('unconventional_weapon_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('unconventional-weaponry', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUnconventionalWeaponryAppliesSelectedWeaponFromCharacterCreationSelection(): void {
    $character = $this->buildCharacterWithFeatSelection('unconventional-weaponry', [
      'selected_weapon_id' => 'gnome-hooked-hammer',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['proficiencies'][0];
    $this->assertSame('weapon', $grant['category']);
    $this->assertSame('gnome-hooked-hammer', $grant['target']);
    $this->assertSame('trained', $grant['rank']);
    $override = $effects['feat_overrides']['unconventional-weaponry'];
    $this->assertSame('uncommon_weapon_training', $override['type']);
    $this->assertSame('gnome-hooked-hammer', $override['selected_weapon_id']);
    $this->assertSame('Gnome Hooked Hammer', $override['selected_weapon_name']);
    $this->assertTrue($override['grants_access']);
    $this->assertContains('unconventional-weaponry', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testUnconventionalWeaponryAppliesSelectedWeaponFromLeveledFeatParams(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('unconventional-weaponry', [
      'selected_weapon_id' => 'flickmace',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['training_grants']['proficiencies'][0];
    $this->assertSame('flickmace', $grant['target']);
    $override = $effects['feat_overrides']['unconventional-weaponry'];
    $this->assertSame('flickmace', $override['selected_weapon_id']);
    $this->assertSame('Flickmace', $override['selected_weapon_name']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAdoptedAncestryRequestsAncestrySelectionWhenMissing(): void {
    $character = $this->buildCharacterWithFeat('adopted-ancestry');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('adopted-ancestry', $grant['source_feat']);
    $this->assertSame('adopted_ancestry_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('adopted-ancestry', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAdoptedAncestryAppliesSelectedAncestryPoolOverride(): void {
    $character = $this->buildCharacterWithFeatSelection('adopted-ancestry', [
      'selected_ancestry' => 'elf',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['adopted-ancestry'];
    $this->assertSame('adopted_ancestry_pool_unlock', $override['type']);
    $this->assertSame('elf', $override['selected_ancestry']);
    $this->assertSame([], $effects['selection_grants']);
    $this->assertContains('adopted-ancestry', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testArcaneEvolutionRequestsSpellSelectionWhenMissing(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('arcane-evolution', [], [
      'class' => 'sorcerer',
      'subclass' => 'imperial',
      'bloodline' => 'imperial',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('arcane-evolution', $grant['source_feat']);
    $this->assertSame('arcane_evolution_spell_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('arcane-evolution', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testArcaneEvolutionPersistsSelectedSpellFromLeveledFeat(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('arcane-evolution', [
      'selected_spell' => 'magic-missile',
    ], [
      'class' => 'sorcerer',
      'subclass' => 'imperial',
      'bloodline' => 'imperial',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['arcane-evolution'];
    $this->assertSame('repertoire_spell_expansion', $override['type']);
    $this->assertSame('arcane', $override['tradition']);
    $this->assertSame('magic-missile', $override['selected_spell']);
    $this->assertTrue($override['add_one_arcane_spell_each_new_rank']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCrossbloodedEvolutionRequestsSelectionWhenMissing(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('crossblooded-evolution', [], [
      'class' => 'sorcerer',
      'subclass' => 'imperial',
      'bloodline' => 'imperial',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('crossblooded-evolution', $grant['source_feat']);
    $this->assertSame('crossblooded_evolution_spell_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('crossblooded-evolution', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testCrossbloodedEvolutionPersistsSelectedSpellAndBloodline(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('crossblooded-evolution', [
      'selected_bloodline' => 'draconic',
      'selected_spell' => 'magic-missile',
    ], [
      'class' => 'sorcerer',
      'subclass' => 'imperial',
      'bloodline' => 'imperial',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['crossblooded-evolution'];
    $this->assertSame('crossblooded_repertoire_expansion', $override['type']);
    $this->assertSame('draconic', $override['selected_bloodline']);
    $this->assertSame('Draconic', $override['selected_bloodline_label']);
    $this->assertSame('magic-missile', $override['selected_spell']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGreaterMentalEvolutionRequestsSpellSelectionWhenMissing(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('greater-mental-evolution', [], [
      'class' => 'sorcerer',
      'subclass' => 'imperial',
      'bloodline' => 'imperial',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('greater-mental-evolution', $grant['source_feat']);
    $this->assertSame('greater_mental_evolution_spell_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertContains('greater-mental-evolution', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGreaterMentalEvolutionPersistsSelectedSpellFromLeveledFeat(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('greater-mental-evolution', [
      'selected_spell' => 'charm',
    ], [
      'class' => 'sorcerer',
      'subclass' => 'imperial',
      'bloodline' => 'imperial',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['greater-mental-evolution'];
    $this->assertSame('cross_tradition_mental_repertoire_spell', $override['type']);
    $this->assertSame('charm', $override['selected_spell']);
    $this->assertTrue($override['cast_using_bloodline_tradition']);
    $this->assertSame('arcane', $override['bloodline_tradition']);
    $this->assertSame(1, $override['uses_per_long_rest']);
    $this->assertSame(6, $override['max_selected_spell_rank']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBloodlineBreadthExpandsImperialGrantedSpellsByRank(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('bloodline-breadth', [], [
      'level' => 8,
      'class' => 'sorcerer',
      'subclass' => 'imperial',
      'bloodline' => 'imperial',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['bloodline-breadth'];
    $this->assertSame('bloodline_granted_spell_expansion', $override['type']);
    $this->assertSame('imperial', $override['bloodline']);
    $this->assertSame('Imperial', $override['bloodline_label']);
    $this->assertSame(4, $override['highest_available_rank']);
    $this->assertTrue($override['adds_one_granted_spell_per_rank']);
    $this->assertSame('magic_missile', $override['granted_spells_up_to_highest_rank'][1]['spell_id']);
    $this->assertSame('dispel_magic', $override['granted_spells_up_to_highest_rank'][2]['spell_id']);
    $this->assertSame('haste', $override['granted_spells_up_to_highest_rank'][3]['spell_id']);
    $this->assertSame('dimension_door', $override['granted_spells_up_to_highest_rank'][4]['spell_id']);
    $this->assertContains('bloodline-breadth', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBloodlineBreadthUsesPersistedGenieSubtypeForVariableRanks(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('bloodline-breadth', [], [
      'level' => 10,
      'class' => 'sorcerer',
      'subclass' => 'genie',
      'bloodline' => 'genie',
      'genie_type' => 'janni',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['bloodline-breadth'];
    $this->assertSame('genie', $override['bloodline']);
    $this->assertSame('janni', $override['bloodline_subtype']);
    $this->assertSame('create_food', $override['granted_spells_up_to_highest_rank'][2]['spell_id']);
    $this->assertSame('banishment', $override['granted_spells_up_to_highest_rank'][5]['spell_id']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testGreaterBloodlineResolvesHighestImperialGrantedSpell(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('greater-bloodline', [], [
      'level' => 12,
      'class' => 'sorcerer',
      'subclass' => 'imperial',
      'bloodline' => 'imperial',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['greater-bloodline'];
    $this->assertSame('bloodline_highest_rank_spell_expansion', $override['type']);
    $this->assertSame('imperial', $override['bloodline']);
    $this->assertSame(6, $override['highest_available_rank']);
    $this->assertSame('disintegrate', $override['selected_spell']);
    $this->assertSame('disintegrate', $override['selected_spell_label']);
    $this->assertSame(6, $override['selected_spell_rank']);
    $this->assertTrue($override['selected_spell_gains_blood_magic']);
    $this->assertContains('greater-bloodline', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBloodlineResistanceResolvesDamageTypeForEverySupportedBloodline(): void {
    $expected_damage_types = [
      'aberrant' => ['damage_type' => 'mental'],
      'angelic' => ['damage_type' => 'positive'],
      'demonic' => ['damage_type' => 'acid'],
      'draconic' => ['damage_type' => 'electricity', 'extra_character' => ['dragon_type' => 'blue'], 'expected_subtype' => 'blue'],
      'elemental' => ['damage_type' => 'bludgeoning', 'extra_character' => ['elemental_type' => 'water'], 'expected_subtype' => 'water'],
      'fey' => ['damage_type' => 'mental'],
      'hag' => ['damage_type' => 'mental'],
      'imperial' => ['damage_type' => 'force'],
      'undead' => ['damage_type' => 'negative'],
      'genie' => ['damage_type' => 'mental', 'extra_character' => ['genie_type' => 'janni'], 'expected_subtype' => 'janni'],
      'nymph' => ['damage_type' => 'mental'],
    ];

    $this->assertSame(array_keys(CharacterManager::SORCERER_BLOODLINES), array_keys($expected_damage_types));

    foreach ($expected_damage_types as $bloodline => $expectation) {
      $character = $this->buildLeveledCharacterWithFeatParams('bloodline-resistance', [], array_merge([
        'level' => 16,
        'class' => 'sorcerer',
        'subclass' => $bloodline,
        'bloodline' => $bloodline,
      ], $expectation['extra_character'] ?? []));
      $effects = $this->manager->buildEffectState($character);

      $override = $effects['feat_overrides']['bloodline-resistance'];
      $this->assertSame($bloodline, $override['bloodline']);
      $this->assertSame($expectation['damage_type'], $override['blood_magic_damage_type']);
      $this->assertSame($expectation['damage_type'], $override['resistance']['damage_type']);
      $this->assertSame(10, $override['resistance']['value']);
      $this->assertSame($expectation['expected_subtype'] ?? NULL, $override['bloodline_subtype']);
      $this->assertContains('bloodline-resistance', $effects['applied_feats']);
    }
  }

  /**
   * @covers ::buildEffectState
   */
  public function testBloodlineResistanceKeepsFireElementalSubtypeOnFireDamage(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('bloodline-resistance', [], [
      'level' => 16,
      'class' => 'sorcerer',
      'subclass' => 'elemental',
      'bloodline' => 'elemental',
      'elemental_type' => 'fire',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $override = $effects['feat_overrides']['bloodline-resistance'];
    $this->assertSame('elemental', $override['bloodline']);
    $this->assertSame('fire', $override['bloodline_subtype']);
    $this->assertSame('fire', $override['blood_magic_damage_type']);
    $this->assertSame('fire', $override['resistance']['damage_type']);
    $this->assertSame(10, $override['resistance']['value']);
    $this->assertContains('bloodline-resistance', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDomainInitiateRequestsDomainSelectionWhenMissing(): void {
    $character = $this->buildCharacterWithFeat('domain-initiate');
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('domain-initiate', $grant['source_feat']);
    $this->assertSame('domain_initiate_domain_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertTrue($effects['derived_adjustments']['flags']['domain_initiate'] ?? FALSE);
    $this->assertContains('domain-initiate', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testDomainInitiatePersistsSelectedDomainFlag(): void {
    $character = $this->buildCharacterWithFeatSelection('domain-initiate', [
      'selected_domain' => 'travel',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('travel', $effects['derived_adjustments']['flags']['domain_initiate_domain'] ?? NULL);
    $this->assertSame([], $effects['selection_grants']);
    $this->assertContains('domain-initiate', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAdvancedDomainRequestsDomainSelectionWhenMissing(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('advanced-domain', []);
    $effects = $this->manager->buildEffectState($character);

    $grant = $effects['selection_grants'][0];
    $this->assertSame('advanced-domain', $grant['source_feat']);
    $this->assertSame('advanced_domain_domain_choice', $grant['selection_type']);
    $this->assertSame(1, $grant['count']);
    $this->assertTrue($effects['derived_adjustments']['flags']['advanced_domain'] ?? FALSE);
    $this->assertContains('advanced-domain', $effects['applied_feats']);
  }

  /**
   * @covers ::buildEffectState
   */
  public function testAdvancedDomainPersistsSelectedDomainFlagFromLeveledFeat(): void {
    $character = $this->buildLeveledCharacterWithFeatParams('advanced-domain', [
      'selected_domain' => 'travel',
    ]);
    $effects = $this->manager->buildEffectState($character);

    $this->assertSame('travel', $effects['derived_adjustments']['flags']['advanced_domain_domain'] ?? NULL);
    $this->assertSame([], $effects['selection_grants']);
    $this->assertContains('advanced-domain', $effects['applied_feats']);
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
