<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\TreasureByLevelService;
use Drupal\Tests\UnitTestCase;

/**
 * Tests treasure-by-level tables and economy helpers.
 *
 * @group dungeoncrawler_content
 * @group treasure
 * @group pf2e-rules
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\TreasureByLevelService
 */
class TreasureByLevelServiceTest extends UnitTestCase {

  /**
   * Tests every level has a complete treasure row.
   *
   * @covers ::getLevelBudget
   * @covers ::getCurrencyComposition
   */
  public function testTreasureTableCoversAllLevelsAndCurrencyComposition(): void {
    $service = new TreasureByLevelService();

    for ($level = 1; $level <= 20; $level++) {
      $budget = $service->getLevelBudget($level);
      $this->assertNotEmpty($budget['currency_gp']);
      $this->assertNotEmpty($budget['permanent_items']);
      $this->assertNotEmpty($budget['consumable_items']);
    }

    $this->assertSame(
      ['coins', 'gems', 'art_objects', 'half_price_items'],
      $service->getCurrencyComposition()
    );
    $this->assertSame(
      ['coins', 'gems', 'art_objects', 'half_price_items'],
      $service->getLevelBudget(5)['currency_includes']
    );
  }

  /**
   * Tests party-size adjustments apply above and below the 4-PC baseline.
   *
   * @covers ::getLevelBudget
   * @covers ::getPartySizeAdjustment
   */
  public function testPartySizeAdjustmentChangesCurrencyBudget(): void {
    $service = new TreasureByLevelService();

    $baseline = $service->getLevelBudget(5, 4);
    $larger = $service->getLevelBudget(5, 5);
    $smaller = $service->getLevelBudget(5, 3);

    $this->assertSame(320.0, $service->getPartySizeAdjustment(5, 5));
    $this->assertSame(-320.0, $service->getPartySizeAdjustment(5, 3));
    $this->assertSame($baseline['currency_gp'] + 320.0, $larger['currency_gp']);
    $this->assertSame($baseline['currency_gp'] - 320.0, $smaller['currency_gp']);
  }

  /**
   * Tests standard items sell at half price.
   *
   * @covers ::sellItem
   */
  public function testStandardItemsSellForHalfPrice(): void {
    $service = new TreasureByLevelService();

    $sale = $service->sellItem('standard', 100.0);

    $this->assertTrue($sale['success']);
    $this->assertSame(50.0, $sale['sale_value']);
    $this->assertFalse($sale['full_price_sale']);
  }

  /**
   * Tests gems, art objects, and raw materials sell at full price.
   *
   * @covers ::sellItem
   */
  public function testFullPriceSaleTypesSellAtFullPrice(): void {
    $service = new TreasureByLevelService();

    $this->assertSame(100.0, $service->sellItem('gem', 100.0)['sale_value']);
    $this->assertSame(100.0, $service->sellItem('art_object', 100.0)['sale_value']);
    $this->assertSame(100.0, $service->sellItem('raw_material', 100.0)['sale_value']);
  }

  /**
   * Tests selling standard items at full price is blocked with correction.
   *
   * @covers ::sellItem
   */
  public function testStandardItemCannotBeSoldAtFullPrice(): void {
    $service = new TreasureByLevelService();

    $sale = $service->sellItem('standard', 100.0, 100.0);

    $this->assertFalse($sale['success']);
    $this->assertTrue($sale['blocked']);
    $this->assertSame('must_sell_at_half_price', $sale['reason']);
    $this->assertSame(50.0, $sale['corrected_value']);
  }

  /**
   * Tests trading outside downtime is soft-flagged.
   *
   * @covers ::validateTradePhase
   * @covers ::sellItem
   */
  public function testTradingOutsideDowntimeIsFlagged(): void {
    $service = new TreasureByLevelService();

    $sale = $service->sellItem('standard', 100.0, NULL, 'exploration');

    $this->assertFalse($sale['success']);
    $this->assertFalse($sale['blocked']);
    $this->assertTrue($sale['flagged']);
    $this->assertTrue($sale['gm_override_available']);
    $this->assertSame('not_downtime', $sale['reason']);
  }

  /**
   * Tests starting wealth uses the requested character level row.
   *
   * @covers ::getStartingWealth
   */
  public function testStartingWealthUsesRequestedLevel(): void {
    $service = new TreasureByLevelService();

    $this->assertSame(15.0, $service->getStartingWealth(1));
    $this->assertSame(270.0, $service->getStartingWealth(5));
  }

}
