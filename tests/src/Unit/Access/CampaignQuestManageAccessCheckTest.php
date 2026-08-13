<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Access;

use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignQuestManageAccessCheck;
use Drupal\dungeoncrawler_content\Service\CampaignAuthorizationService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Access\CampaignQuestManageAccessCheck
 *
 * @group dungeoncrawler_content
 * @group quest_access
 */
class CampaignQuestManageAccessCheckTest extends UnitTestCase {

  /**
   * @covers ::access
   */
  public function testCampaignOwnerHasQuestManageAccess(): void {
    $authz = $this->createMock(CampaignAuthorizationService::class);
    $account = $this->createMock(AccountInterface::class);

    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);
    $account->method('id')->willReturn(7);
    $authz->method('canAccessCampaign')->with(22, 7)->willReturn(TRUE);

    $check = new CampaignQuestManageAccessCheck($authz);
    $result = $check->access($account, 22);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testNonOwnerWithoutAdminPermissionIsDenied(): void {
    $authz = $this->createMock(CampaignAuthorizationService::class);
    $account = $this->createMock(AccountInterface::class);

    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);
    $account->method('id')->willReturn(9);
    $authz->method('canAccessCampaign')->with(22, 9)->willReturn(FALSE);

    $check = new CampaignQuestManageAccessCheck($authz);
    $result = $check->access($account, 22);

    $this->assertFalse($result->isAllowed());
  }
}
