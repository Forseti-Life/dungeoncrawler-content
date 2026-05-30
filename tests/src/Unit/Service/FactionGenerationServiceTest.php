<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService;
use Drupal\dungeoncrawler_content\Service\FactionGenerationService;
use Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers narrative-need-driven faction generation.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\FactionGenerationService
 */
class FactionGenerationServiceTest extends UnitTestCase {

  /**
   * @covers ::normalizeNarrativeNeedRequest
   */
  public function testNormalizeNarrativeNeedRequestRequiresReasonAndCharacteristics(): void {
    $service = new FactionGenerationService(
      $this->createMock(Connection::class),
      new InstitutionNormalizationService(),
      $this->createMock(CampaignSubjectRegistryService::class),
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Explain why an existing faction cannot satisfy this narrative need.');
    $service->normalizeNarrativeNeedRequest(42, [
      'label' => 'Wharf Consortium',
    ]);
  }

  /**
   * @covers ::generateFactionDraft
   */
  public function testGenerateFactionDraftBuildsCanonicalLibraryContract(): void {
    $service = new FactionGenerationService(
      $this->createMock(Connection::class),
      new InstitutionNormalizationService(),
      $this->createMock(CampaignSubjectRegistryService::class),
    );

    $normalized = $service->normalizeNarrativeNeedRequest(42, [
      'label' => 'Wharf Consortium',
      'whyExistingFactionIsInsufficient' => 'The scene needs a dockside union with its own leverage and agenda.',
      'publicFace' => 'Dock labor union',
      'hiddenFace' => 'Smuggling patronage network',
      'ideologyTags' => 'solidarity, mercantile leverage',
      'methodTags' => ['strikes', 'bribery'],
      'roleInStory' => 'Controls access to the harbor',
      'requestSource' => 'character_creation_step6',
      'provenanceNote' => 'Requested from faction_refs create flow.',
    ]);
    $draft = $service->generateFactionDraft($normalized);

    $this->assertSame('Wharf Consortium', $draft['canonicalLabel']);
    $this->assertSame('wharf-consortium', $draft['canonicalSlug']);
    $this->assertSame('institution_allegiance_wharf-consortium', $draft['librarySubjectId']);
    $this->assertSame('generated-wharf-consortium-seed', $draft['seedProfile']['profile_key']);
    $this->assertSame('mutable', $draft['membershipModel']['default_mutability']);
    $this->assertSame(['solidarity', 'mercantile leverage'], $draft['requestedCharacteristics']['ideology_tags']);
    $this->assertSame(['strikes', 'bribery'], $draft['requestedCharacteristics']['method_tags']);
  }

  /**
   * @covers ::createOrReuseFactionForNeed
   */
  public function testCreateOrReuseFactionForNeedReusesExistingCanonicalFaction(): void {
    $service = new class(
      $this->createMock(Connection::class),
      new InstitutionNormalizationService(),
      $this->createMock(CampaignSubjectRegistryService::class)
    ) extends FactionGenerationService {
      public array $instantiatedDrafts = [];

      public function isGenerationStorageReady(): bool {
        return TRUE;
      }

      protected function findExistingLibraryFactionBySlug(string $canonical_slug): ?array {
        return [
          'id' => 77,
          'source_asset_id' => $canonical_slug,
        ];
      }

      protected function upsertLibraryFactionManifest(array $draft): int {
        throw new \RuntimeException('Manifest insert should not run when reusing an existing faction.');
      }

      protected function instantiateCampaignFactionSubject(int $campaign_id, array $draft): array {
        $this->instantiatedDrafts[] = ['campaign_id' => $campaign_id, 'draft' => $draft];
        return ['subject_id' => 'institution_allegiance_wharf-consortium'];
      }
    };

    $result = $service->createOrReuseFactionForNeed(42, [
      'label' => 'Wharf Consortium',
      'whyExistingFactionIsInsufficient' => 'Need a union that owns the harbor-side labor economy.',
      'publicFace' => 'Dock labor union',
    ]);

    $this->assertFalse($result['created']);
    $this->assertSame('reused', $result['status']);
    $this->assertSame(77, $result['manifestId']);
    $this->assertSame('institution_allegiance_wharf-consortium', $result['campaignSubjectId']);
    $this->assertCount(1, $service->instantiatedDrafts);
  }

  /**
   * @covers ::createOrReuseFactionForNeed
   */
  public function testCreateOrReuseFactionForNeedCreatesManifestForNewFaction(): void {
    $service = new class(
      $this->createMock(Connection::class),
      new InstitutionNormalizationService(),
      $this->createMock(CampaignSubjectRegistryService::class)
    ) extends FactionGenerationService {
      public array $capturedDrafts = [];

      public function isGenerationStorageReady(): bool {
        return TRUE;
      }

      protected function findExistingLibraryFactionBySlug(string $canonical_slug): ?array {
        return NULL;
      }

      protected function upsertLibraryFactionManifest(array $draft): int {
        $this->capturedDrafts[] = $draft;
        return 81;
      }

      protected function instantiateCampaignFactionSubject(int $campaign_id, array $draft): array {
        return ['subject_id' => 'institution_allegiance_keepers-of-the-third-bell'];
      }
    };

    $result = $service->createOrReuseFactionForNeed(42, [
      'label' => 'Keepers of the Third Bell',
      'whyExistingFactionIsInsufficient' => 'The harbor district needs a bell-watch mutual aid order.',
      'roleInStory' => 'Signals curfew and protects late-shift workers',
      'methodTags' => 'patrols, alarm bells',
    ]);

    $this->assertTrue($result['created']);
    $this->assertSame('created', $result['status']);
    $this->assertSame(81, $result['manifestId']);
    $this->assertSame('institution_allegiance_keepers-of-the-third-bell', $result['campaignSubjectId']);
    $this->assertCount(1, $service->capturedDrafts);
    $this->assertSame('keepers-of-the-third-bell', $service->capturedDrafts[0]['canonicalSlug']);
  }

}
