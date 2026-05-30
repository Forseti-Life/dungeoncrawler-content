<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CampaignInstitutionBackfillService;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers live campaign institution backfill analysis.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\CampaignInstitutionBackfillService
 */
class CampaignInstitutionBackfillServiceTest extends UnitTestCase {

  /**
   * @covers ::analyzeRuntimeActorRow
   */
  public function testAnalyzeRuntimeActorRowMarksPcBackfillable(): void {
    $service = new CampaignInstitutionBackfillService(
      $this->createMock(Connection::class),
      $this->buildInstitutionMembershipStub()
    );

    $analysis = $service->analyzeRuntimeActorRow([
      'id' => 10,
      'campaign_id' => 42,
      'instance_id' => 'pc-campaign-aelra',
      'type' => 'pc',
      'name' => 'Aelra',
      'ancestry' => 'elf',
      'class' => 'fighter',
      'character_data' => json_encode([
        'ancestry' => 'elf',
        'class' => 'fighter',
      ]),
      'state_data' => '{}',
    ]);

    $this->assertSame('backfillable', $analysis['status']);
    $this->assertSame('campaign_character', $analysis['source_type']);
    $this->assertCount(2, $analysis['institution_inputs']);
  }

  /**
   * @covers ::analyzeRuntimeActorRow
   */
  public function testAnalyzeRuntimeActorRowFallsBackToTopLevelColumnsWhenJsonFieldsAreBlank(): void {
    $service = new CampaignInstitutionBackfillService(
      $this->createMock(Connection::class),
      $this->buildInstitutionMembershipStub()
    );

    $analysis = $service->analyzeRuntimeActorRow([
      'id' => 12,
      'campaign_id' => 42,
      'instance_id' => 'pc-campaign-fallback',
      'type' => 'pc',
      'name' => 'Fallback Hero',
      'ancestry' => 'dwarf',
      'class' => 'cleric',
      'character_data' => json_encode([
        'ancestry' => '',
        'class' => '',
      ]),
      'state_data' => '{}',
    ]);

    $this->assertSame('backfillable', $analysis['status']);
    $this->assertSame('Dwarf', $analysis['institution_inputs'][0]['display_name']);
    $this->assertSame('Cleric', $analysis['institution_inputs'][1]['display_name']);
  }

  /**
   * @covers ::analyzeRuntimeActorRow
   */
  public function testAnalyzeRuntimeActorRowFlagsAmbiguousNpc(): void {
    $service = new CampaignInstitutionBackfillService(
      $this->createMock(Connection::class),
      $this->buildInstitutionMembershipStub()
    );

    $analysis = $service->analyzeRuntimeActorRow([
      'id' => 11,
      'campaign_id' => 42,
      'instance_id' => '',
      'type' => 'npc',
      'name' => 'Unknown Mentor',
      'ancestry' => '',
      'class' => 'mentor',
      'character_data' => json_encode([
        'class' => 'mentor',
      ]),
      'state_data' => '{}',
    ]);

    $this->assertSame('review_required', $analysis['status']);
    $this->assertSame(['missing_ancestry', 'ambiguous_profession_label'], $analysis['review_reasons']);
    $this->assertSame('campaign_row_11', $analysis['source_id']);
  }

  /**
   * @covers ::analyzeRuntimeActorRow
   */
  public function testAnalyzeRuntimeActorRowHonorsPersistedResolutionOverride(): void {
    $service = new CampaignInstitutionBackfillService(
      $this->createMock(Connection::class),
      $this->buildInstitutionMembershipStub()
    );

    $analysis = $service->analyzeRuntimeActorRow([
      'id' => 13,
      'campaign_id' => 42,
      'instance_id' => '',
      'type' => 'npc',
      'name' => 'Resolved Mentor',
      'ancestry' => '',
      'class' => 'mentor',
      'character_data' => json_encode([
        'class' => 'mentor',
      ]),
      'state_data' => json_encode([
        'institution_review_overrides' => [
          'missing_ancestry' => [
            'action' => 'map_existing',
            'domain' => 'ancestry',
            'display_name' => 'Elf',
            'subject_id' => 'institution_ancestry_elf',
          ],
          'ambiguous_profession_label' => [
            'action' => 'create_institution',
            'domain' => 'profession',
            'display_name' => 'Mentor',
            'subject_id' => 'institution_profession_mentor',
          ],
        ],
      ]),
    ]);

    $this->assertSame('backfillable', $analysis['status']);
    $this->assertSame([], $analysis['review_reasons']);
    $this->assertSame('campaign_row_13', $analysis['source_id']);
    $this->assertCount(2, $analysis['institution_inputs']);
    $this->assertSame(['missing_ancestry', 'ambiguous_profession_label'], array_keys($analysis['details']['manual_overrides']));
  }

  /**
   * Builds a deterministic membership-extraction stub.
   */
  private function buildInstitutionMembershipStub(): InstitutionMembershipService {
    return new class(
      $this->createMock(Connection::class),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService::class),
      new \Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService(),
      $this->createMock(\Drupal\dungeoncrawler_content\Service\RelationshipManagerService::class)
    ) extends InstitutionMembershipService {
      public function buildCharacterInstitutionInputs(array $character_data, string $seed_source = 'character_creation'): array {
        $inputs = [];
        if (!empty($character_data['ancestry'])) {
          $inputs[] = ['domain' => 'ancestry', 'display_name' => ucfirst((string) $character_data['ancestry'])];
        }
        if (!empty($character_data['class'])) {
          $inputs[] = ['domain' => 'profession', 'display_name' => ucfirst((string) $character_data['class'])];
        }
        return $inputs;
      }

      public function buildNpcInstitutionInputs(array $npc_data, string $seed_source = 'npc_creation'): array {
        $inputs = [];
        if (!empty($npc_data['ancestry'])) {
          $inputs[] = ['domain' => 'ancestry', 'display_name' => ucfirst((string) $npc_data['ancestry'])];
        }
        $class = (string) ($npc_data['class'] ?? '');
        if ($class !== '' && !in_array($class, ['mentor', 'npc', 'creature', 'humanoid'], TRUE)) {
          $inputs[] = ['domain' => 'profession', 'display_name' => ucfirst($class)];
        }
        return $inputs;
      }
    };
  }

}
