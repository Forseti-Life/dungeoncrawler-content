<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Controller\CharacterViewController;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterStateService;
use Drupal\dungeoncrawler_content\Service\FeatEffectManager;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\dungeoncrawler_content\Service\FollowerSubsystemService;
use Drupal\dungeoncrawler_content\Service\GeneratedImageRepository;
use Drupal\dungeoncrawler_content\Service\NpcPsychologyService;
use Drupal\dungeoncrawler_content\Service\RelationshipManagerService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the sheet-facing effect summary for the character sheet.
 *
 * @group dungeoncrawler_content
 * @group character
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Controller\CharacterViewController
 */
class CharacterViewControllerEffectSummaryTest extends UnitTestCase {

  protected function buildController(): CharacterViewController {
    return new CharacterViewController(
      $this->createMock(CharacterManager::class),
      $this->createMock(CharacterStateService::class),
      $this->createMock(FeatEffectManager::class),
      $this->createMock(FeatLibraryService::class),
      $this->createMock(RelationshipManagerService::class),
      $this->createMock(NpcPsychologyService::class),
      $this->createMock(GeneratedImageRepository::class),
      $this->createMock(FollowerSubsystemService::class),
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class),
    );
  }

  /**
   * @covers ::buildSheetEffectSummary
   */
  public function testBuildSheetEffectSummaryProjectsAppliedAndConditionalEffects(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'buildSheetEffectSummary');
    $method->setAccessible(TRUE);

    $summary = $method->invoke($controller, [
      'conditions' => [
        ['name' => 'Frightened', 'condition_type' => 'frightened', 'value' => 2],
        ['name' => 'Speed Penalty', 'condition_type' => 'speed_penalty_10'],
      ],
    ], [
      'applied_feats' => ['toughness'],
      'derived_adjustments' => [
        'hp_max_bonus' => 1,
        'perception_bonus' => 1,
        'initiative_bonus' => 2,
      ],
      'conditional_modifiers' => [
        'skills' => [
          ['bonus' => 1, 'skill' => 'Perception', 'context' => 'Seek'],
        ],
      ],
    ], [
      'hp_max_base' => 16,
      'hp_max_final' => 17,
      'ac_base' => 18,
      'speed_base' => 25,
      'speed_feat_final' => 25,
      'speed_final' => 15,
      'perception_base' => 6,
      'perception_final' => 7,
      'ac_final' => 16,
      'initiative_base' => 6,
      'initiative_final' => 9,
      'initiative_bonus_total' => 3,
    ]);

    $this->assertTrue($summary['stats']['hp_max']['modified']);
    $this->assertSame(16, $summary['stats']['hp_max']['base']);
    $this->assertSame(17, $summary['stats']['hp_max']['final']);
    $this->assertSame('positive', $summary['stats']['hp_max']['direction']);
    $this->assertSame('+1', $summary['stats']['hp_max']['formatted_delta']);
    $this->assertSame('Toughness', $summary['stats']['hp_max']['contributions'][0]['label']);

    $this->assertTrue($summary['stats']['armor_class']['modified']);
    $this->assertSame(18, $summary['stats']['armor_class']['base']);
    $this->assertSame(16, $summary['stats']['armor_class']['final']);
    $this->assertSame('negative', $summary['stats']['armor_class']['direction']);
    $this->assertSame(-2, $summary['stats']['armor_class']['contributions'][0]['value']);

    $this->assertTrue($summary['stats']['speed']['modified']);
    $this->assertSame(25, $summary['stats']['speed']['base']);
    $this->assertSame(15, $summary['stats']['speed']['final']);
    $this->assertSame('-10 ft', $summary['stats']['speed']['formatted_delta']);
    $this->assertCount(1, $summary['stats']['speed']['contributions']);

    $this->assertTrue($summary['stats']['initiative']['modified']);
    $this->assertSame(6, $summary['stats']['initiative']['base']);
    $this->assertSame(9, $summary['stats']['initiative']['final']);
    $this->assertSame('+3', $summary['stats']['initiative']['formatted_delta']);
    $this->assertSame(3, $summary['stats']['initiative']['contributions'][0]['value']);

    $this->assertCount(1, $summary['conditional']);
    $this->assertSame('+1 Perception', $summary['conditional'][0]['label']);
    $this->assertSame('Seek', $summary['conditional'][0]['context']);
  }

  /**
   * @covers ::buildSheetEffectSummary
   */
  public function testBuildSheetEffectSummaryKeepsUnchangedStatsQuiet(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'buildSheetEffectSummary');
    $method->setAccessible(TRUE);

    $summary = $method->invoke($controller, ['conditions' => []], [
      'derived_adjustments' => [],
      'conditional_modifiers' => [],
    ], [
      'hp_max_base' => 16,
      'hp_max_final' => 16,
      'ac_base' => 18,
      'speed_base' => 25,
      'speed_feat_final' => 25,
      'speed_final' => 25,
      'perception_base' => 6,
      'perception_final' => 6,
      'ac_final' => 18,
      'initiative_base' => 6,
      'initiative_final' => 6,
      'initiative_bonus_total' => 0,
    ]);

    $this->assertFalse($summary['stats']['hp_max']['modified']);
    $this->assertFalse($summary['stats']['speed']['modified']);
    $this->assertFalse($summary['stats']['perception']['modified']);
    $this->assertFalse($summary['stats']['armor_class']['modified']);
    $this->assertFalse($summary['stats']['initiative']['modified']);
    $this->assertSame([], $summary['conditional']);
  }

  /**
   * @covers ::buildSheetEffectSummary
   */
  public function testBuildSheetEffectSummaryPreservesFeatAndConditionSpeedBreakdown(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'buildSheetEffectSummary');
    $method->setAccessible(TRUE);

    $summary = $method->invoke($controller, [
      'conditions' => [
        ['name' => 'Hampered', 'condition_type' => 'speed_penalty_10'],
      ],
    ], [
      'applied_feats' => ['fleet'],
      'derived_adjustments' => [
        'computed_speed' => 30,
      ],
      'conditional_modifiers' => [],
    ], [
      'condition_effects' => [
        'armor_class' => ['total' => 0, 'contributions' => []],
        'speed' => [
          'total' => -10,
          'contributions' => [
            ['source' => 'condition', 'label' => 'Hampered', 'value' => -10],
          ],
        ],
      ],
      'hp_max_base' => 16,
      'hp_max_final' => 16,
      'ac_base' => 18,
      'speed_base' => 25,
      'speed_feat_final' => 30,
      'speed_final' => 20,
      'perception_base' => 6,
      'perception_final' => 6,
      'ac_final' => 18,
      'initiative_base' => 6,
      'initiative_final' => 6,
      'initiative_bonus_total' => 0,
    ]);

    $this->assertTrue($summary['stats']['speed']['modified']);
    $this->assertSame(25, $summary['stats']['speed']['base']);
    $this->assertSame(20, $summary['stats']['speed']['final']);
    $this->assertSame('negative', $summary['stats']['speed']['direction']);
    $this->assertSame('-5 ft', $summary['stats']['speed']['formatted_delta']);
    $this->assertCount(2, $summary['stats']['speed']['contributions']);
    $this->assertSame('Fleet', $summary['stats']['speed']['contributions'][0]['label']);
    $this->assertSame('+5 ft', $summary['stats']['speed']['contributions'][0]['formatted_value']);
    $this->assertSame('Hampered', $summary['stats']['speed']['contributions'][1]['label']);
    $this->assertSame('-10 ft', $summary['stats']['speed']['contributions'][1]['formatted_value']);
  }

  /**
   * @covers ::buildSheetEffectSummary
   */
  public function testBuildSheetEffectSummaryKeepsNegativeFeatSpeedContributions(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'buildSheetEffectSummary');
    $method->setAccessible(TRUE);

    $summary = $method->invoke($controller, [
      'conditions' => [
        ['name' => 'Hampered', 'condition_type' => 'speed_penalty_10'],
      ],
    ], [
      'applied_feats' => ['heavy_stride'],
      'derived_adjustments' => [
        'computed_speed' => 20,
      ],
      'conditional_modifiers' => [],
    ], [
      'condition_effects' => [
        'armor_class' => ['total' => 0, 'contributions' => []],
        'speed' => [
          'total' => -10,
          'contributions' => [
            ['source' => 'condition', 'label' => 'Hampered', 'value' => -10],
          ],
        ],
      ],
      'hp_max_base' => 16,
      'hp_max_final' => 16,
      'ac_base' => 18,
      'speed_base' => 25,
      'speed_feat_final' => 20,
      'speed_final' => 10,
      'perception_base' => 6,
      'perception_final' => 6,
      'ac_final' => 18,
      'initiative_base' => 6,
      'initiative_final' => 6,
      'initiative_bonus_total' => 0,
    ]);

    $this->assertTrue($summary['stats']['speed']['modified']);
    $this->assertSame('negative', $summary['stats']['speed']['direction']);
    $this->assertSame('-15 ft', $summary['stats']['speed']['formatted_delta']);
    $this->assertCount(2, $summary['stats']['speed']['contributions']);
    $this->assertSame('Heavy Stride', $summary['stats']['speed']['contributions'][0]['label']);
    $this->assertSame('-5 ft', $summary['stats']['speed']['contributions'][0]['formatted_value']);
    $this->assertSame('Hampered', $summary['stats']['speed']['contributions'][1]['label']);
    $this->assertSame('-10 ft', $summary['stats']['speed']['contributions'][1]['formatted_value']);
  }

  /**
   * @covers ::buildSheetEffectSummary
   */
  public function testBuildSheetEffectSummaryRequiresExplicitContext(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'buildSheetEffectSummary');
    $method->setAccessible(TRUE);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('initiative_bonus_total');

    $method->invoke($controller, ['conditions' => []], [
      'derived_adjustments' => [],
      'conditional_modifiers' => [],
    ], [
      'hp_max_base' => 16,
      'hp_max_final' => 16,
      'ac_base' => 18,
      'speed_base' => 25,
      'speed_feat_final' => 25,
      'speed_final' => 25,
      'perception_base' => 6,
      'perception_final' => 6,
      'ac_final' => 18,
      'initiative_base' => 6,
      'initiative_final' => 6,
    ]);
  }

  /**
   * @covers ::buildSheetEffectSummary
   */
  public function testBuildSheetEffectSummaryAccountsForSpeedClamp(): void {
    $controller = $this->buildController();
    $method = new \ReflectionMethod($controller, 'buildSheetEffectSummary');
    $method->setAccessible(TRUE);

    $summary = $method->invoke($controller, [
      'conditions' => [
        ['name' => 'Hampered', 'condition_type' => 'speed_penalty_40'],
      ],
    ], [
      'applied_feats' => ['fleet'],
      'derived_adjustments' => [
        'computed_speed' => 30,
      ],
      'conditional_modifiers' => [],
    ], [
      'condition_effects' => [
        'armor_class' => ['total' => 0, 'contributions' => []],
        'speed' => [
          'total' => -40,
          'contributions' => [
            ['source' => 'condition', 'label' => 'Hampered', 'value' => -40],
          ],
        ],
      ],
      'hp_max_base' => 16,
      'hp_max_final' => 16,
      'ac_base' => 18,
      'speed_base' => 25,
      'speed_feat_final' => 30,
      'speed_final' => 0,
      'perception_base' => 6,
      'perception_final' => 6,
      'ac_final' => 18,
      'initiative_base' => 6,
      'initiative_final' => 6,
      'initiative_bonus_total' => 0,
    ]);

    $this->assertTrue($summary['stats']['speed']['modified']);
    $this->assertTrue($summary['stats']['speed']['clamped']);
    $this->assertSame(-10, $summary['stats']['speed']['raw_final']);
    $this->assertSame('-25 ft', $summary['stats']['speed']['formatted_delta']);
    $this->assertCount(3, $summary['stats']['speed']['contributions']);
    $this->assertSame('+5 ft', $summary['stats']['speed']['contributions'][0]['formatted_value']);
    $this->assertSame('-40 ft', $summary['stats']['speed']['contributions'][1]['formatted_value']);
    $this->assertSame('+10 ft', $summary['stats']['speed']['contributions'][2]['formatted_value']);
    $this->assertSame('Minimum speed 0 ft', $summary['stats']['speed']['contributions'][2]['label']);
  }

}
