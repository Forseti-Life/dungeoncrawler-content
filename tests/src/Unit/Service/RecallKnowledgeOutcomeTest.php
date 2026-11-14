<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\RecallKnowledgeService;
use PHPUnit\Framework\TestCase;

/**
 * One Recall Knowledge outcome authority for exploration and encounter play.
 *
 * @group dungeoncrawler_content
 * @group feats
 */
class RecallKnowledgeOutcomeTest extends TestCase {

  private const PARAMS = ['known_info' => 'It fears lantern light.', 'bonus_detail' => 'It nests under piers.', 'false_info' => 'It is harmless when fed.'];

  public function testDegreesWithoutTheFeat(): void {
    $this->assertSame(['It fears lantern light.', 'It nests under piers.'], array_slice(array_values(RecallKnowledgeService::resolveOutcome('critical_success', self::PARAMS)), 1, 2));
    $success = RecallKnowledgeService::resolveOutcome('success', self::PARAMS);
    $this->assertSame(['It fears lantern light.', NULL], [$success['info'], $success['bonus_detail']]);
    $failure = RecallKnowledgeService::resolveOutcome('failure', self::PARAMS);
    $this->assertNull($failure['info']);
    $this->assertFalse($failure['dubious']);
    // A critical failure presents the false answer as if it were true.
    $critical = RecallKnowledgeService::resolveOutcome('critical_failure', self::PARAMS);
    $this->assertSame('It is harmless when fed.', $critical['info']);
    $this->assertSame('You recall information about the subject.', $critical['player_facing_message']);
  }

  public function testDubiousKnowledgeOnlyChangesAPlainFailure(): void {
    $failure = RecallKnowledgeService::resolveOutcome('failure', self::PARAMS, TRUE);
    $this->assertTrue($failure['dubious']);
    // Both answers, in an order that does not reveal which is true.
    $this->assertSame(['It fears lantern light.', 'It is harmless when fed.'], $failure['info']);
    $swapped = RecallKnowledgeService::resolveOutcome('failure', ['known_info' => 'It is harmless when fed.', 'false_info' => 'It fears lantern light.'], TRUE);
    $this->assertSame($failure['info'], $swapped['info']);

    foreach (['critical_success', 'success', 'critical_failure'] as $degree) {
      $this->assertSame(RecallKnowledgeService::resolveOutcome($degree, self::PARAMS), RecallKnowledgeService::resolveOutcome($degree, self::PARAMS, TRUE), $degree);
    }
  }

  public function testUnknownDegreeHardFails(): void {
    $this->expectExceptionMessage('recall_knowledge_degree_invalid:partial');
    RecallKnowledgeService::resolveOutcome('partial', self::PARAMS);
  }

}
