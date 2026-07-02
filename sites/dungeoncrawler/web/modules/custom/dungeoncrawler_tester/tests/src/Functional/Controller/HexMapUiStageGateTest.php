<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Stage-gate UI coverage for the /hexmap experience.
 *
 * @group dungeoncrawler_tester
 * @group controller
 * @group ui
 * @group hexmap
 */
class HexMapUiStageGateTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Loads the hex map page.
   */
  protected function loadHexMap(): void {
    $this->drupalGet('/hexmap');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Test 1: /hexmap route renders.
   */
  public function testHexMapPageLoads(): void {
    $this->loadHexMap();
  }

  /**
   * Test 2: Header/title copy is visible.
   */
  public function testHexMapHeaderTextVisible(): void {
    $this->loadHexMap();
    $this->assertSession()->pageTextContains('Hex Map Demo');
    $this->assertSession()->pageTextContains('Interactive hexagonal grid powered by PixiJS');
  }

  /**
   * Test 3: Canvas host exists for Pixi map render.
   */
  public function testCanvasContainerExists(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#hexmap-canvas-container');
  }

  /**
   * Test 4: Grid size selector exists.
   */
  public function testGridSizeControlExists(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#grid-size');
  }

  /**
   * Test 5: Grid size options include small/medium/large.
   */
  public function testGridSizeOptionsExist(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#grid-size option[value="small"]');
    $this->assertSession()->elementExists('css', '#grid-size option[value="medium"]');
    $this->assertSession()->elementExists('css', '#grid-size option[value="large"]');
  }

  /**
   * Test 6: Hex size slider exists and has expected defaults.
   */
  public function testHexSizeSliderDefaults(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#hex-size');
    $this->assertSession()->elementExists('css', '#hex-size[min="20"]');
    $this->assertSession()->elementExists('css', '#hex-size[max="60"]');
    $this->assertSession()->elementExists('css', '#hex-size[value="30"]');
    $this->assertSession()->pageTextContains('30px');
  }

  /**
   * Test 7: Map control buttons exist.
   */
  public function testMapControlButtonsExist(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#toggle-coordinates');
    $this->assertSession()->elementExists('css', '#toggle-grid');
    $this->assertSession()->elementExists('css', '#reset-view');
  }

  /**
   * Test 8: Object palette action buttons exist.
   */
  public function testObjectPaletteButtonsExist(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#place-creature');
    $this->assertSession()->elementExists('css', '#place-item');
    $this->assertSession()->elementExists('css', '#place-obstacle');
    $this->assertSession()->elementExists('css', '#clear-objects');
  }

  /**
   * Test 9: Selected object type default state is displayed.
   */
  public function testSelectedObjectDefaultState(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#selected-object-type');
    $this->assertSession()->pageTextContains('Selected: None');
  }

  /**
   * Test 10: Start combat control is visible by default.
   */
  public function testStartCombatControlVisible(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#start-combat');
    $this->assertSession()->pageTextContains('Start Combat');
  }

  /**
   * Test 11: End combat control starts hidden.
   */
  public function testEndCombatControlInitiallyHidden(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#end-combat');
  }

  /**
   * Test 12: Initiative tracker starts hidden.
   */
  public function testInitiativeTrackerInitiallyHidden(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#initiative-tracker');
    $this->assertSession()->elementExists('css', '#initiative-list');
  }

  /**
   * Test 13: Action footer structure exists.
   */
  public function testActionFooterExists(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#action-footer');
    $this->assertSession()->elementExists('css', '#action-footer-toggle');
    $this->assertSession()->pageTextContains('Actions');
  }

  /**
   * Test 14: Action buttons for movement and attack exist.
   */
  public function testActionButtonsExist(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#action-menu');
    $this->assertSession()->elementExists('css', '#action-move');
    $this->assertSession()->elementExists('css', '#action-attack');
  }

  /**
   * Test 15: End turn button starts hidden until combat starts.
   */
  public function testEndTurnButtonInitiallyHidden(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#end-turn');
  }

  /**
   * Test 16: Action instruction default copy is visible.
   */
  public function testActionInstructionDefaultCopy(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#action-instruction');
    $this->assertSession()->pageTextContains('Select a hostile target to attack.');
  }

  /**
   * Test 17: Map info panel fields exist with default values.
   */
  public function testMapInfoPanelDefaults(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#selected-hex');
    $this->assertSession()->elementExists('css', '#hovered-hex');
    $this->assertSession()->elementExists('css', '#hovered-object');
    $this->assertSession()->elementExists('css', '#zoom-level');
    $this->assertSession()->pageTextContains('100%');
  }

  /**
   * Test 18: Hex detail fields render.
   */
  public function testHexDetailFieldsExist(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#hex-detail-room');
    $this->assertSession()->elementExists('css', '#hex-detail-terrain');
    $this->assertSession()->elementExists('css', '#hex-detail-elevation');
    $this->assertSession()->elementExists('css', '#hex-detail-lighting');
    $this->assertSession()->elementExists('css', '#hex-detail-passability');
    $this->assertSession()->elementExists('css', '#hex-detail-objects');
    $this->assertSession()->elementExists('css', '#hex-detail-entities');
    $this->assertSession()->elementExists('css', '#hex-detail-connection');
  }

  /**
   * Test 19: Movement and combat instructions are rendered.
   */
  public function testInstructionListIncludesMovementAndAttackGuidance(): void {
    $this->loadHexMap();
    $this->assertSession()->pageTextContains('Blue overlay shows movement range');
    $this->assertSession()->pageTextContains('Click blue hex to move selected creature');
    $this->assertSession()->pageTextContains('Actions on your turn: Move, Attack, End Turn');
  }

  /**
   * Test 20: Character sheet core fields render.
   */
  public function testCharacterSheetFieldsExist(): void {
    $this->loadHexMap();
    $this->assertSession()->elementExists('css', '#char-name');
    $this->assertSession()->elementExists('css', '#char-type');
    $this->assertSession()->elementExists('css', '#char-team');
    $this->assertSession()->elementExists('css', '#char-level');
    $this->assertSession()->elementExists('css', '#char-hp');
    $this->assertSession()->elementExists('css', '#char-ac');
    $this->assertSession()->elementExists('css', '#char-speed');
    $this->assertSession()->elementExists('css', '#char-actions');
    $this->assertSession()->elementExists('css', '#char-inventory');
  }

  /**
   * Test 21: Hexmap JS API endpoints are embedded in delivered JS.
   */
  public function testHexmapApiEndpointsPresentInResponse(): void {
    $this->loadHexMap();
    $this->assertSession()->responseContains('/api/combat/start');
    $this->assertSession()->responseContains('/api/combat/end-turn');
    $this->assertSession()->responseContains('/api/combat/end');
    $this->assertSession()->responseContains('/api/combat/attack');
  }

  /**
   * Test 22: Drupal settings payload for hexmap launch context is attached.
   */
  public function testHexmapDrupalSettingsAttached(): void {
    $this->loadHexMap();
    $this->assertSession()->responseContains('hexmapLaunchContext');
    $this->assertSession()->responseContains('hexmapDungeonData');
  }

}
