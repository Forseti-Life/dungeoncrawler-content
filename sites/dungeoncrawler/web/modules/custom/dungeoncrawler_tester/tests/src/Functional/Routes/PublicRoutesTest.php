<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Routes;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests public routes in the dungeon crawler module.
 *
 * @group dungeoncrawler_content
 * @group routes
 */
class PublicRoutesTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests home route - positive case.
   */
  public function testHomeRoutePositive(): void {
    $this->drupalGet('/home');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests home route - negative case (invalid subpath).
   */
  public function testHomeRouteNegative(): void {
    $this->drupalGet('/home/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests world route - positive case.
   */
  public function testWorldRoutePositive(): void {
    $this->drupalGet('/world');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests world route - negative case (non-existent parameter).
   */
  public function testWorldRouteNegative(): void {
    $this->drupalGet('/world/invalid-parameter');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests how-to-play route - positive case.
   */
  public function testHowToPlayRoutePositive(): void {
    $this->drupalGet('/how-to-play');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests how-to-play route - negative case (missing required resource).
   */
  public function testHowToPlayRouteNegative(): void {
    $this->drupalGet('/how-to-play/nonexistent');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests about route - positive case.
   */
  public function testAboutRoutePositive(): void {
    $this->drupalGet('/about');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests about route - negative case.
   */
  public function testAboutRouteNegative(): void {
    $this->drupalGet('/about/extra/path');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests credits route - positive case.
   */
  public function testCreditsRoutePositive(): void {
    $this->drupalGet('/credits');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests credits route - negative case.
   */
  public function testCreditsRouteNegative(): void {
    $this->drupalGet('/credits/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Tests testing page route - positive case.
   *
   * Note: This test verifies the route exists and is accessible.
   * TestingPageControllerTest provides more detailed controller-specific tests.
   */
  public function testTestingPageRoutePositive(): void {
    $this->drupalGet('/testing');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests testing page route - negative case.
   */
  public function testTestingPageRouteNegative(): void {
    $this->drupalGet('/testing/invalid');
    $this->assertSession()->statusCodeEquals(404);
  }

}
