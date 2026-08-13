<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignGmAccessCheck;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Access\CampaignGmAccessCheck
 *
 * @group dungeoncrawler_content
 * @group campaign_access
 */
class CampaignGmAccessCheckTest extends UnitTestCase {

  /**
   * @covers ::access
   */
  public function testCampaignGmIsAllowed(): void {
    $authz = $this->createMock(CampaignAuthorizationService::class);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);
    $account->method('id')->willReturn(17);

    $authz->method('canManageCampaign')->with(88, 17)->willReturn(TRUE);

    $check = new CampaignGmAccessCheck($authz);
    $result = $check->access($account, 88);
    $this->assertTrue($result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testNonGmDenied(): void {
    $authz = $this->createMock(CampaignAuthorizationService::class);
    $account = $this->createMock(AccountInterface::class);
    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);
    $account->method('id')->willReturn(18);

    $authz->method('canManageCampaign')->with(88, 18)->willReturn(FALSE);

    $check = new CampaignGmAccessCheck($authz);
    $result = $check->access($account, 88);
    $this->assertFalse($result->isAllowed());
  }

}
