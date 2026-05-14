<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Access;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Select;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Access\CampaignQuestManageAccessCheck;
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
    $database = $this->createMock(Connection::class);
    $account = $this->createMock(AccountInterface::class);

    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);
    $account->method('id')->willReturn(7);

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn(['uid' => 7]);

    $select = $this->createMock(Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    $database->method('select')->with('dc_campaigns', 'c')->willReturn($select);

    $check = new CampaignQuestManageAccessCheck($database);
    $result = $check->access($account, 22);

    $this->assertTrue($result->isAllowed());
  }

  /**
   * @covers ::access
   */
  public function testNonOwnerWithoutAdminPermissionIsDenied(): void {
    $database = $this->createMock(Connection::class);
    $account = $this->createMock(AccountInterface::class);

    $account->method('isAnonymous')->willReturn(FALSE);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);
    $account->method('id')->willReturn(9);

    $statement = $this->createMock(StatementInterface::class);
    $statement->method('fetchAssoc')->willReturn(['uid' => 7]);

    $select = $this->createMock(Select::class);
    $select->method('fields')->willReturnSelf();
    $select->method('condition')->willReturnSelf();
    $select->method('execute')->willReturn($statement);

    $database->method('select')->with('dc_campaigns', 'c')->willReturn($select);

    $check = new CampaignQuestManageAccessCheck($database);
    $result = $check->access($account, 22);

    $this->assertFalse($result->isAllowed());
  }
}
