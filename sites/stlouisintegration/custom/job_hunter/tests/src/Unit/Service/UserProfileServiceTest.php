<?php

namespace Drupal\job_hunter\Tests\Service;

use Drupal\Tests\UnitTestCase;
use Drupal\job_hunter\Service\UserProfileService;
use Drupal\user\Entity\User;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Unit tests for UserProfileService.
 * 
 * @group job_hunter
 */
class UserProfileServiceTest extends UnitTestCase {

  /**
   * The user profile service under test.
   *
   * @var \Drupal\job_hunter\Service\UserProfileService
   */
  protected $userProfileService;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->userProfileService = new UserProfileService();
  }

  /**
   * Test profile completeness calculation with empty user.
   */
  public function testCalculateCompletenessEmpty() {
    $user = $this->createMockUser([]);
    $completeness = $this->userProfileService->calculateProfileCompleteness($user);
    $this->assertEquals(0, $completeness);
  }

  /**
   * Test profile completeness calculation with resume only.
   */
  public function testCalculateCompletenessWithResume() {
    $user = $this->createMockUser(['field_resume_file' => 'resume.pdf']);
    $completeness = $this->userProfileService->calculateProfileCompleteness($user);
    $this->assertEquals(20, $completeness); // Resume is worth 20%
  }

  /**
   * Test field completion detection.
   */
  public function testIsFieldCompleted() {
    $user = $this->createMockUser([
      'field_professional_summary' => 'Test summary',
      'field_linkedin_url' => 'https://linkedin.com/in/test',
    ]);

    $this->assertTrue($this->userProfileService->isFieldCompleted($user, 'field_professional_summary'));
    $this->assertTrue($this->userProfileService->isFieldCompleted($user, 'field_linkedin_url'));
    $this->assertFalse($this->userProfileService->isFieldCompleted($user, 'field_github_url'));
  }

  /**
   * Test missing field recommendations.
   */
  public function testGetMissingFieldRecommendations() {
    $user = $this->createMockUser(['field_professional_summary' => 'Test summary']);
    $missing = $this->userProfileService->getMissingFieldRecommendations($user, 3);
    
    $this->assertIsArray($missing);
    $this->assertLessThanOrEqual(3, count($missing));
    $this->assertContains('Upload your resume', $missing);
  }

  /**
   * Test completeness status detection.
   */
  public function testGetCompletenessStatus() {
    $status_low = $this->userProfileService->getCompletenessStatus(30);
    $this->assertEquals('incomplete', $status_low['class']);
    $this->assertEquals('low', $status_low['level']);

    $status_medium = $this->userProfileService->getCompletenessStatus(50);
    $this->assertEquals('partial', $status_medium['class']);
    $this->assertEquals('medium', $status_medium['level']);

    $status_high = $this->userProfileService->getCompletenessStatus(80);
    $this->assertEquals('complete', $status_high['class']);
    $this->assertEquals('high', $status_high['level']);
  }

  /**
   * Test job application validation.
   */
  public function testValidateForJobApplication() {
    // User without required fields
    $user_incomplete = $this->createMockUser([]);
    $validation = $this->userProfileService->validateForJobApplication($user_incomplete);
    $this->assertFalse($validation['ready']);
    $this->assertNotEmpty($validation['errors']);

    // User with minimum required fields
    $user_basic = $this->createMockUser([
      'field_resume_file' => 'resume.pdf',
      'field_work_authorization' => 'us_citizen',
    ]);
    $validation_basic = $this->userProfileService->validateForJobApplication($user_basic);
    $this->assertTrue($validation_basic['ready']);
    $this->assertEmpty($validation_basic['errors']);
  }

  /**
   * Creates a mock user entity with specified field values.
   *
   * @param array $field_values
   *   Array of field values.
   *
   * @return \Drupal\user\Entity\User|\PHPUnit\Framework\MockObject\MockObject
   *   Mock user entity.
   */
  protected function createMockUser(array $field_values) {
    $user = $this->createMock(User::class);

    // Mock hasField method
    $user->method('hasField')->willReturnCallback(function($field_name) use ($field_values) {
      return array_key_exists($field_name, $field_values) || 
             in_array($field_name, array_keys(UserProfileService::FIELD_WEIGHTS));
    });

    // Mock get method
    $user->method('get')->willReturnCallback(function($field_name) use ($field_values) {
      $field_item_list = $this->createMock(FieldItemListInterface::class);
      
      if (array_key_exists($field_name, $field_values)) {
        $field_item_list->method('isEmpty')->willReturn(false);
        
        if (in_array($field_name, ['field_portfolio_url', 'field_linkedin_url', 'field_github_url'])) {
          $field_item_list->uri = $field_values[$field_name];
        } else {
          $field_item_list->value = $field_values[$field_name];
        }
      } else {
        $field_item_list->method('isEmpty')->willReturn(true);
        $field_item_list->value = null;
        $field_item_list->uri = null;
      }
      
      return $field_item_list;
    });

    return $user;
  }

}