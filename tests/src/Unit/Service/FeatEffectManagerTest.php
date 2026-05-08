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
