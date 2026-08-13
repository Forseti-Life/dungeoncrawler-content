<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\ConditionReferenceValidatorService;
use Drupal\dungeoncrawler_content\Service\SkillReferenceValidatorService;
use Drupal\Tests\UnitTestCase;

/**
 * @group dungeoncrawler_content
 */
class SkillAndConditionReferenceValidatorServiceTest extends UnitTestCase {

  public function testSkillReferenceValidatorAcceptsCanonicalSkill(): void {
    $validator = new SkillReferenceValidatorService();
    $result = $validator->validateSkillReference('athletics', 'feat.skill');

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  public function testSkillReferenceValidatorRejectsUnknownSkill(): void {
    $validator = new SkillReferenceValidatorService();
    $result = $validator->validateSkillReference('woodworking', 'feat.skill');

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('unknown canonical skill', implode('; ', $result['errors']));
  }

  public function testSkillGrantMapValidatorValidatesKeysAndProficiency(): void {
    $validator = new SkillReferenceValidatorService();
    $result = $validator->validateSkillGrantMap([
      'crafting' => 'trained',
      'religion' => 'expert',
    ], 'feat.special.skill_grants');

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  public function testConditionReferenceValidatorAcceptsAlias(): void {
    $validator = new ConditionReferenceValidatorService();
    $result = $validator->validateConditionReferences(['flat-footed', 'persistent bleed'], 'spell.conditions_caused');

    $this->assertTrue($result['valid']);
    $this->assertSame([], $result['errors']);
  }

  public function testConditionReferenceValidatorRejectsUnknownCondition(): void {
    $validator = new ConditionReferenceValidatorService();
    $result = $validator->validateConditionReferences(['mysterious_curse'], 'spell.conditions_caused');

    $this->assertFalse($result['valid']);
    $this->assertStringContainsString('unknown condition', implode('; ', $result['errors']));
  }

}

