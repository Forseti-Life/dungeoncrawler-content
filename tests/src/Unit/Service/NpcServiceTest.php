<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountInterface;
use Drupal\dungeoncrawler_content\Service\FactionGenerationService;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\NameGeneratorService;
use Drupal\dungeoncrawler_content\Service\NpcService;
use Drupal\dungeoncrawler_content\Service\NpcSheetGenerationService;
use PHPUnit\Framework\TestCase;

/**
 * Covers NPC-side faction-generation request resolution.
 *
 * @group dungeoncrawler_content
 * @group social
 */
class NpcServiceTest extends TestCase {

  public function testResolveNpcFactionCreateRequestsPromotesFactionRefs(): void {
    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->expects($this->once())
      ->method('createOrReuseFactionForNeed')
      ->with(88, $this->callback(function (array $request): bool {
        return ($request['label'] ?? '') === 'Keepers of the Third Bell'
          && ($request['requestSource'] ?? '') === 'npc_authoring_support';
      }))
      ->willReturn([
        'campaignSubjectId' => 'institution_allegiance_keepers-of-the-third-bell',
      ]);

    $service = new NpcService(
      $this->createMock(Connection::class),
      $this->createMock(AccountInterface::class),
      $this->createMock(NpcSheetGenerationService::class),
      $this->createMock(InstitutionMembershipService::class),
      $faction_generation,
      $this->createMock(NameGeneratorService::class),
    );

    $payload = [
      'faction_create_requests' => [[
        'label' => 'Keepers of the Third Bell',
        'whyExistingFactionIsInsufficient' => 'Need a bell-watch mutual aid order for this district.',
        'publicFace' => 'Neighborhood watch',
      ]],
    ];

    $method = new \ReflectionMethod(NpcService::class, 'resolveNpcFactionCreateRequests');
    $method->setAccessible(TRUE);
    $method->invokeArgs($service, [88, &$payload]);

    $this->assertSame([[
      'subject_id' => 'institution_allegiance_keepers-of-the-third-bell',
      'metadata' => [
        'created_via' => 'npc_service',
        'request_source' => 'npc_authoring_support',
      ],
    ]], $payload['faction_refs']);
    $this->assertArrayNotHasKey('faction_create_requests', $payload);
  }

  public function testCreateNpcValidatesBasePayloadBeforeFactionGeneration(): void {
    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->expects($this->never())
      ->method('createOrReuseFactionForNeed');

    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);

    $result = new class {
      public function fields($table, $fields) { return $this; }
      public function condition($field, $value, $operator = NULL) { return $this; }
      public function execute() { return $this; }
      public function fetchField() { return 7; }
    };

    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturn($result);

    $service = new NpcService(
      $database,
      $account,
      $this->createMock(NpcSheetGenerationService::class),
      $this->createMock(InstitutionMembershipService::class),
      $faction_generation,
      NULL,
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('name is required');
    $service->createNpc(88, [
      'role' => 'neutral',
      'attitude' => 'indifferent',
      'faction_create_requests' => [[
        'label' => 'Keepers of the Third Bell',
        'whyExistingFactionIsInsufficient' => 'Need a bell-watch mutual aid order for this district.',
        'publicFace' => 'Neighborhood watch',
      ]],
    ]);
  }

  public function testCreateNpcStartsTransactionBeforeFactionGeneration(): void {
    $order = [];
    $account = $this->createMock(AccountInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('hasPermission')->with('administer dungeoncrawler content')->willReturn(FALSE);

    $campaign_owner_result = new class {
      public function fields($table, $fields) { return $this; }
      public function condition($field, $value, $operator = NULL) { return $this; }
      public function range($start = 0, $length = NULL) { return $this; }
      public function execute() { return $this; }
      public function fetchField() { return 7; }
    };
    $actor_instance_result = new class {
      public function fields($table, $fields) { return $this; }
      public function condition($field, $value, $operator = NULL) { return $this; }
      public function range($start = 0, $length = NULL) { return $this; }
      public function execute() { return $this; }
      public function fetchField() { return FALSE; }
    };
    $insert_query = new class {
      public function fields(array $fields): self { return $this; }
      public function execute(): int { return 123; }
    };

    $database = $this->createMock(Connection::class);
    $database->method('select')->willReturnCallback(static function (string $table, ...$unused) use ($campaign_owner_result, $actor_instance_result) {
      return $table === 'dc_campaign_characters' ? $actor_instance_result : $campaign_owner_result;
    });
    $database->expects($this->once())
      ->method('startTransaction')
      ->willReturnCallback(static function () use (&$order) {
        $order[] = 'transaction';
        return new class() {
          public function rollBack(): void {}
        };
      });
    $database->method('insert')->with('dc_campaign_characters')->willReturn($insert_query);

    $faction_generation = $this->createMock(FactionGenerationService::class);
    $faction_generation->expects($this->once())
      ->method('createOrReuseFactionForNeed')
      ->willReturnCallback(static function (int $campaign_id, array $request) use (&$order): array {
        $order[] = 'generation';
        return ['campaignSubjectId' => 'institution_allegiance_keepers-of-the-third-bell'];
      });

    $membership = $this->createMock(InstitutionMembershipService::class);
    $membership->expects($this->once())
      ->method('syncCampaignNpcMemberships');

    $sheet_generation = $this->createMock(NpcSheetGenerationService::class);
    $sheet_generation->expects($this->once())
      ->method('enqueueNpcSheetGeneration');

    $service = new NpcService(
      $database,
      $account,
      $sheet_generation,
      $membership,
      $faction_generation,
      $this->createMock(NameGeneratorService::class),
    );

    $service->createNpc(88, [
      'name' => 'Bellkeeper Ruan',
      'role' => 'neutral',
      'attitude' => 'indifferent',
      'faction_create_requests' => [[
        'label' => 'Keepers of the Third Bell',
        'whyExistingFactionIsInsufficient' => 'Need a bell-watch mutual aid order for this district.',
        'publicFace' => 'Neighborhood watch',
      ]],
    ]);

    $this->assertSame(['transaction', 'generation'], array_slice($order, 0, 2));
  }

}
