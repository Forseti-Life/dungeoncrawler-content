<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ConditionReferenceValidatorService;
use Drupal\dungeoncrawler_content\Service\SkillReferenceValidatorService;
use Drupal\dungeoncrawler_content\Service\SpellFeatActionDataValidatorService;
use Drupal\dungeoncrawler_content\Service\ValidationProfileResolverService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\SpellFeatActionDataValidatorService
 *
 * @group dungeoncrawler_content
 */
class SpellFeatActionDataValidatorServiceTest extends UnitTestCase {

  private function buildIntegratedValidator(): SpellFeatActionDataValidatorService {
    return new SpellFeatActionDataValidatorService(
      new SkillReferenceValidatorService(),
      new ConditionReferenceValidatorService(),
      new ValidationProfileResolverService()
    );
  }

  /**
   * @covers ::validateSpellDefinition
   */
  public function testValidateSpellDefinitionAcceptsCanonicalPayload(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateSpellDefinition([
      'id' => 'mage_armor',
      'name' => 'Mage Armor',
      'rank' => 1,
      'traditions' => ['arcane'],
      'description' => 'You ward yourself with magical armor.',
      'cast_actions' => '2_actions',
      'save_type' => 'none',
      'traits' => ['abjuration'],
      'conditions_caused' => ['none'],
    ]);

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  /**
   * @covers ::validateSpellDefinition
   */
  public function testValidateSpellDefinitionRejectsUnsafeOrIncompletePayload(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateSpellDefinition([
      'id' => '',
      'name' => '',
      'rank' => 99,
      'traditions' => ['invalid'],
      'description' => '<script>alert(1)</script>',
      'cast_actions' => '4_actions',
    ]);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
    $this->assertStringContainsString('Spell id is required.', implode('; ', $result['errors']));
    $this->assertStringContainsString('unsafe text content', implode('; ', $result['errors']));
  }

  /**
   * @covers ::validateFeatDefinition
   */
  public function testValidateFeatDefinitionAcceptsCanonicalPayload(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateFeatDefinition([
      'id' => 'toughness',
      'name' => 'Toughness',
      'level' => 1,
      'type' => 'general',
      'benefit' => 'Increase your maximum Hit Points.',
      'skill' => 'athletics',
      'traits' => ['general'],
      'effects' => [['kind' => 'hp_bonus', 'value' => 1]],
    ]);

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  /**
   * @covers ::validateFeatDefinition
   */
  public function testValidateFeatDefinitionRejectsIncompletePayload(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateFeatDefinition([
      'id' => '',
      'name' => '',
      'level' => 0,
      'type' => 'unknown',
      'benefit' => '<script>bad</script>',
    ]);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
    $this->assertStringContainsString('Feat type is invalid.', implode('; ', $result['errors']));
  }

  /**
   * @covers ::validateActionDefinition
   */
  public function testValidateActionDefinitionAcceptsCanonicalPayload(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateActionDefinition('cast_spell', [
      'label' => 'Cast spell',
      'validator' => 'GameplayActionProcessor::validateCharacterActionResources',
      'executor' => 'GameplayActionProcessor::applyCharacterStateChanges',
      'scope' => 'character',
      'status' => 'active',
    ]);

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  /**
   * @covers ::validateActionDefinition
   */
  public function testValidateActionDefinitionRejectsUnsafeCallable(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateActionDefinition('bad action', [
      'label' => 'Bad',
      'validator' => 'GameplayActionProcessor->validate',
      'executor' => 'GameplayActionProcessor::run;rm -rf /',
      'scope' => 'mixed scope',
      'status' => 'mystery',
    ]);

    $this->assertFalse($result['valid']);
    $this->assertNotEmpty($result['errors']);
    $this->assertStringContainsString('safe Service::method reference', implode('; ', $result['errors']));
  }

  /**
   * @covers ::validateSpellDefinition
   */
  public function testValidateSpellDefinitionRejectsUnknownConditionReference(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateSpellDefinition([
      'id' => 'test_spell',
      'name' => 'Test Spell',
      'rank' => 1,
      'traditions' => ['arcane'],
      'description' => 'Test.',
      'cast_actions' => '2_actions',
      'conditions_caused' => ['unknown_condition'],
    ]);

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('unknown condition', implode('; ', $result['errors']));
  }

  /**
   * @covers ::validateFeatDefinition
   */
  public function testValidateFeatDefinitionRejectsUnknownSkillReference(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateFeatDefinition([
      'id' => 'test_feat',
      'name' => 'Test Feat',
      'level' => 1,
      'type' => 'general',
      'benefit' => 'Benefit.',
      'skill' => 'woodworking',
    ]);

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('unknown canonical skill', implode('; ', $result['errors']));
  }

  /**
   * @covers ::validateSpellDefinition
   */
  public function testValidateSpellDefinitionRejectsIntermediaryOnlyFieldInCanonicalProfile(): void {
    $validator = $this->buildIntegratedValidator();
    $result = $validator->validateSpellDefinition([
      'id' => 'test_spell',
      'name' => 'Test Spell',
      'rank' => 1,
      'traditions' => ['arcane'],
      'description' => 'Test.',
      'cast_actions' => '2_actions',
      'source_book' => 'crb',
    ], 'canonical_registry');

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('intermediary-only', implode('; ', $result['errors']));
  }

}
