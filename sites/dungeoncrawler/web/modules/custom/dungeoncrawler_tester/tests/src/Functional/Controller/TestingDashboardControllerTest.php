<?php

namespace Drupal\Tests\dungeoncrawler_tester\Functional\Controller;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests TestingDashboardController functionality.
 *
 * @group dungeoncrawler_tester
 * @group controller
 */
class TestingDashboardControllerTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['dungeoncrawler_tester', 'dungeoncrawler_content'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user with permissions to access dashboard.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user with permission to access the testing dashboard.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
    ]);
  }

  /**
   * Tests testing dashboard display - positive case.
   */
  public function testTestingDashboardDisplayPositive(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/dungeoncrawler/testing');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Test Documentation');
    $this->assertSession()->pageTextContains('Quick Test Commands');
    $this->assertSession()->pageTextContains('Release Testing Stagegates');
  }

  /**
   * Tests testing dashboard contains documentation links.
   */
  public function testTestingDashboardDocumentationLinks(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/dungeoncrawler/testing');
    $this->assertSession()->statusCodeEquals(200);
    
    // Check for documentation links.
    $this->assertSession()->pageTextContains('Module README');
    $this->assertSession()->pageTextContains('Testing Module README');
    $this->assertSession()->pageTextContains('Tests README');
    $this->assertSession()->pageTextContains('Testing Strategy Design');
    $this->assertSession()->pageTextContains('Testing Quick Start Guide');
    $this->assertSession()->pageTextContains('Testing Issues Directory');
  }

  /**
   * Tests testing dashboard contains test commands.
   */
  public function testTestingDashboardTestCommands(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/dungeoncrawler/testing');
    $this->assertSession()->statusCodeEquals(200);
    
    // Check for test command sections.
    $this->assertSession()->pageTextContains('Run All Tests');
    $this->assertSession()->pageTextContains('Unit Tests Only');
    $this->assertSession()->pageTextContains('Functional Tests Only');
    $this->assertSession()->pageTextContains('Route Tests');
    $this->assertSession()->pageTextContains('Controller Tests');
    $this->assertSession()->pageTextContains('API Tests');
    $this->assertSession()->pageTextContains('Campaign/Entity Tests');
    
    // Check for actual commands.
    $this->assertSession()->responseContains('cd sites/dungeoncrawler');
    $this->assertSession()->responseContains('phpunit');
  }

  /**
   * Tests testing dashboard access control - negative case.
   */
  public function testTestingDashboardAccessNegative(): void {
    // Anonymous user should not access dashboard.
    $this->drupalGet('/dungeoncrawler/testing');
    $this->assertSession()->statusCodeEquals(403);
    
    // Regular authenticated user without permission should not access.
    $regularUser = $this->drupalCreateUser([]);
    $this->drupalLogin($regularUser);
    $this->drupalGet('/dungeoncrawler/testing');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests testing dashboard contains stagegate information.
   */
  public function testTestingDashboardStagegates(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/dungeoncrawler/testing');
    $this->assertSession()->statusCodeEquals(200);
    
    // Check for stagegate process items.
    $this->assertSession()->pageTextContains('Pre-commit');
    $this->assertSession()->pageTextContains('Functional routes/controllers');
    $this->assertSession()->pageTextContains('Character creation workflow');
    $this->assertSession()->pageTextContains('Entity/campaign APIs');
    $this->assertSession()->pageTextContains('CI gate');
  }

  /**
   * Tests issue-pr-report page display.
   */
  public function testIssuePrReportDisplay(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/dungeoncrawler/testing/import-open-issues/issue-pr-report');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Process & Decision Logic');
    $this->assertSession()->pageTextContains('Triage Steps');
    $this->assertSession()->pageTextContains('Decision Rules');
    $this->assertSession()->pageTextContains('Open Issues (with linked PRs)');
    $this->assertSession()->pageTextContains('Orphaned Open PRs');
  }

  /**
   * Tests issue-pr-report access control.
   */
  public function testIssuePrReportAccessNegative(): void {
    // Anonymous user should not access report.
    $this->drupalGet('/dungeoncrawler/testing/import-open-issues/issue-pr-report');
    $this->assertSession()->statusCodeEquals(403);
    
    // Regular authenticated user without permission should not access.
    $regularUser = $this->drupalCreateUser([]);
    $this->drupalLogin($regularUser);
    $this->drupalGet('/dungeoncrawler/testing/import-open-issues/issue-pr-report');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests import-open-issues page displays and links to issue-pr-report.
   */
  public function testImportOpenIssuesPageDisplay(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/dungeoncrawler/testing/import-open-issues');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Import Open Issues');
    $this->assertSession()->pageTextContains('Synchronize local Open rows from Issues.md to GitHub');

    // Verify link to issue-pr-report exists.
    $this->assertSession()->linkExists('View Issue/PR Report →');
    $this->assertSession()->linkByHrefExists('/dungeoncrawler/testing/import-open-issues/issue-pr-report');
  }

}
