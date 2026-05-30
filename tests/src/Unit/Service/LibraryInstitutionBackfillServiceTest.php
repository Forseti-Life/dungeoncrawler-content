<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\LibraryInstitutionBackfillService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers staged library institution backfill analysis.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\LibraryInstitutionBackfillService
 */
class LibraryInstitutionBackfillServiceTest extends UnitTestCase {

  /**
   * @covers ::analyzeCharacterTemplateRow
   */
  public function testAnalyzeCharacterTemplateRowFlagsAmbiguousNpcAndMissingAncestry(): void {
    $service = new LibraryInstitutionBackfillService(
      $this->createMock(Connection::class),
      $this->createMock(ModuleExtensionList::class),
      $this->buildInstitutionMembershipStub()
    );

    $analysis = $service->analyzeCharacterTemplateRow([
      'character_id' => 4102,
      'instance_id' => 'ltba-grandmother',
      'type' => 'npc',
      'role' => 'npc',
      'location_type' => 'room',
      'location_ref' => 'ltba-grandmas-house-parlor',
      'state_data' => [
        'name' => 'The Kind Old Lady',
        'class' => 'quest_giver',
      ],
    ], '/tmp/little_trouble_character_templates.json');

    $this->assertSame('social_actor_thin', $analysis['classification']);
    $this->assertSame('review_required', $analysis['status']);
    $this->assertSame(['missing_ancestry', 'ambiguous_profession_label', 'unresolved_location_ref'], $analysis['review_reasons']);
  }

  /**
   * @covers ::analyzeCharacterTemplateRow
   */
  public function testAnalyzeCharacterTemplateRowNormalizesDeterministicPcData(): void {
    $service = new LibraryInstitutionBackfillService(
      $this->createMock(Connection::class),
      $this->createMock(ModuleExtensionList::class),
      $this->buildInstitutionMembershipStub()
    );

    $analysis = $service->analyzeCharacterTemplateRow([
      'character_id' => 5001,
      'instance_id' => 'pc-elf-fighter',
      'type' => 'pc',
      'role' => 'player',
      'location_type' => 'global',
      'location_ref' => '',
      'state_data' => [
        'name' => 'Aelra',
        'ancestry' => 'elf',
        'class' => 'fighter',
      ],
    ], '/tmp/normalized_character_templates.json');

    $this->assertSame('normalized', $analysis['status']);
    $this->assertCount(2, $analysis['normalized_payload']['institution_inputs']);
    $this->assertSame([], $analysis['review_reasons']);
  }

  /**
   * @covers ::analyzeCharacterTemplateRow
   */
  public function testAnalyzeCharacterTemplateRowHonorsPersistedResolutionOverride(): void {
    $service = new LibraryInstitutionBackfillService(
      $this->createMock(Connection::class),
      $this->createMock(ModuleExtensionList::class),
      $this->buildInstitutionMembershipStub()
    );

    $analysis = $service->analyzeCharacterTemplateRow([
      'character_id' => 5002,
      'instance_id' => 'npc-review-resolved',
      'type' => 'npc',
      'role' => 'npc',
      'location_type' => 'room',
      'location_ref' => '',
      'state_data' => [
        'name' => 'Resolved Mentor',
        'class' => 'mentor',
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
      ],
    ], '/tmp/review_override_character_templates.json');

    $this->assertSame('normalized', $analysis['status']);
    $this->assertSame([], $analysis['review_reasons']);
    $this->assertCount(2, $analysis['normalized_payload']['institution_inputs']);
    $this->assertSame(['missing_ancestry', 'ambiguous_profession_label'], array_keys($analysis['normalized_payload']['manual_overrides']));
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
        if ($class !== '' && !in_array($class, ['quest_giver', 'mentor', 'creature'], TRUE)) {
          $inputs[] = ['domain' => 'profession', 'display_name' => ucfirst($class)];
        }
        return $inputs;
      }
    };
  }

}
