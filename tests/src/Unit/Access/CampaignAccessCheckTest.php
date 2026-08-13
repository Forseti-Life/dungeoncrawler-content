<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignAccessCheck;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Access\CampaignAccessCheck
 *
 * @group dungeoncrawler_content
 * @group campaign_access
 */
class CampaignAccessCheckTest extends UnitTestCase {

  /**
   * @covers ::access
   */
  public function testCampaignMemberIsAllowed(): void {
    $authz = $this->createMock(CampaignAuthorizationService::class);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);
    $account->method('id')->willReturn(11);

    $authz->method('canAccessCampaign')->with(42, 11)->willReturn(TRUE);

    $check = new CampaignAccessCheck($authz);
    $result = $check->access($account, 42);
    $this->assertTrue($result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testAnonymousIsDenied(): void {
    $authz = $this->createMock(CampaignAuthorizationService::class);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(TRUE);

    $check = new CampaignAccessCheck($authz);
    $result = $check->access($account, 42);
    $this->assertFalse($result->isAllowed());
  }

}
