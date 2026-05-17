<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Form\CampaignDeleteForm;
use Drupal\Tests\UnitTestCase;

/**
 * Tests campaign deletion character-preservation helpers.
 *
 * @group dungeoncrawler_content
 * @group campaign
 */
class CampaignDeleteFormTest extends UnitTestCase {

  /**
   * @covers ::buildDetachedPlayerCharacterFields
   */
  public function testBuildDetachedPlayerCharacterFieldsUsesUuidForRosterInstance(): void {
    $form = new CampaignDeleteForm(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(AccountProxyInterface::class),
      NULL,
    );

    $method = new \ReflectionMethod($form, 'buildDetachedPlayerCharacterFields');
    $method->setAccessible(TRUE);

    $fields = $method->invoke($form, (object) [
      'id' => 42,
      'uuid' => 'library-uuid-42',
      'instance_id' => 'pc-12-42',
    ], 1234567890);

    $this->assertSame(0, $fields['campaign_id']);
    $this->assertSame(0, $fields['character_id']);
    $this->assertSame('library-uuid-42', $fields['instance_id']);
    $this->assertSame('roster', $fields['location_type']);
    $this->assertSame('', $fields['location_ref']);
    $this->assertSame(0, $fields['position_q']);
    $this->assertSame(0, $fields['position_r']);
    $this->assertSame('', $fields['last_room_id']);
    $this->assertSame(0, $fields['is_active']);
    $this->assertSame(1234567890, $fields['updated']);
    $this->assertSame(1234567890, $fields['changed']);
  }

  /**
   * @covers ::isPreservablePlayerCharacter
   */
  public function testOnlyPlayerPcRowsArePreserved(): void {
    $form = new CampaignDeleteForm(
      $this->createMock(Connection::class),
      $this->createMock(TimeInterface::class),
      $this->createMock(AccountProxyInterface::class),
      NULL,
    );

    $method = new \ReflectionMethod($form, 'isPreservablePlayerCharacter');
    $method->setAccessible(TRUE);

    $this->assertTrue($method->invoke($form, (object) [
      'uid' => 7,
      'type' => 'pc',
      'role' => 'player',
    ]));
    $this->assertFalse($method->invoke($form, (object) [
      'uid' => 7,
      'type' => 'npc',
      'role' => 'player',
    ]));
    $this->assertFalse($method->invoke($form, (object) [
      'uid' => 0,
      'type' => 'pc',
      'role' => 'player',
    ]));
  }

}
