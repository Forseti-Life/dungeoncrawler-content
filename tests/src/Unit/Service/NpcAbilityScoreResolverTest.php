<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\NpcAbilityScoreResolver;
use PHPUnit\Framework\TestCase;

/**
 * Tests NpcAbilityScoreResolver.
 *
 * @covers \Drupal\dungeoncrawler_content\Service\NpcAbilityScoreResolver
 */
class NpcAbilityScoreResolverTest extends TestCase {

  public function testResolveAbilityScoreUsesDefaultWhenMissing(): void {
    $this->assertSame(10, NpcAbilityScoreResolver::resolveAbilityScore([
      'entity_ref' => 'npc:unknown',
    ], 'charisma', 10));
  }

  public function testResolveCharismaScoreUsesSharedDefault(): void {
    $this->assertSame(NpcAbilityScoreResolver::DEFAULT_CHARISMA_SCORE, NpcAbilityScoreResolver::resolveCharismaScore([
      'entity_ref' => 'npc:unknown',
    ]));
  }

  public function testResolveCharismaScoreClampsNegativeValuesToMinimumThree(): void {
    $this->assertSame(3, NpcAbilityScoreResolver::resolveCharismaScore([
      'entity' => ['state' => ['abilities' => ['charisma' => -7]]],
    ]));
  }

  public function testResolveAbilityScoreClampsDefaultBelowMinimum(): void {
    $this->assertSame(3, NpcAbilityScoreResolver::resolveAbilityScore([
      'entity_ref' => 'npc:unknown',
    ], 'charisma', -9));
  }

  public function testFindAbilityScoreSupportsCanonicalCharismaShapes(): void {
    $this->assertSame(12, NpcAbilityScoreResolver::findAbilityScore([
      'entity' => ['state' => ['abilities' => ['charisma' => 12]]],
    ], 'charisma'));

    $this->assertSame(15, NpcAbilityScoreResolver::findAbilityScore([
      'entity' => ['state' => ['pf2e_stats' => ['ability_scores' => ['charisma' => ['score' => 15]]]]],
    ], 'charisma'));

    $this->assertSame(17, NpcAbilityScoreResolver::findAbilityScore([
      'ability_scores' => ['cha' => ['value' => 17]],
    ], 'charisma'));

    $this->assertSame(11, NpcAbilityScoreResolver::findAbilityScore([
      'profile' => ['ability_scores' => ['charisma' => ['base' => 11]]],
    ], 'charisma'));
  }

  public function testFindAbilityScoreUsesSourcePriorityOrder(): void {
    $npc = [
      'entity' => [
        'state' => ['abilities' => ['charisma' => 18]],
      ],
      'profile' => [
        'ability_scores' => ['charisma' => 8],
      ],
    ];

    $this->assertSame(18, NpcAbilityScoreResolver::findCharismaScore($npc));
  }

  public function testFindCharismaScoreClampsNegativeValuesToMinimumThree(): void {
    $this->assertSame(3, NpcAbilityScoreResolver::findCharismaScore([
      'entity' => ['state' => ['abilities' => ['charisma' => -42]]],
    ]));
  }

}
