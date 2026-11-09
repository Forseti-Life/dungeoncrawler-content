<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm;
use Drupal\dungeoncrawler_content\Service\AbilityScoreTracker;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\CharacterManager;
use Drupal\dungeoncrawler_content\Service\CharacterWizardHardeningService;
use Drupal\dungeoncrawler_content\Service\FactionGenerationService;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\FeatLibraryService;
use Drupal\dungeoncrawler_content\Service\SpellCatalogService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 */
class CharacterCreationGmServiceTest extends TestCase {

  private function service(?CharacterCreationStepForm $form = NULL, ?object $record = NULL, ?FactionGenerationService $factions = NULL, ?FeatLibraryService $feats = NULL): CharacterCreationGmService {
    $manager = $this->createMock(CharacterManager::class);
    $manager->method('loadCharacter')->willReturn($record ?? (object) [
      'id' => 77, 'uid' => 7, 'version' => 3, 'campaign_id' => 0, 'status' => 0,
      'lifecycle_state' => 'draft_library',
      'character_data' => json_encode(['name' => 'Original Hero', 'step' => 6, 'wizard_complete' => FALSE]),
    ]);
    $manager->method('canonicalizeCharacterData')->willReturnArgument(0);
    $account = $this->createMock(AccountProxyInterface::class);
    $account->method('id')->willReturn(7);
    $account->method('hasPermission')->willReturn(FALSE);
    $form ??= $this->createMock(CharacterCreationStepForm::class);
    $form->method('projectDraft')->willReturnArgument(0);
    $form->method('creationEquipmentCatalog')->willReturn([
      'weapons' => [['id' => 'fixture-tool', 'name' => 'Original Fixture Tool', 'type' => 'weapon', 'cost' => 20]],
    ]);
    $resolver = $this->createMock(ClassResolverInterface::class);
    $resolver->method('getInstanceFromDefinition')->with(CharacterCreationStepForm::class)->willReturn($form);
    return new CharacterCreationGmService(
      $this->createMock(Connection::class), $account,
      $this->createMock(TimeInterface::class), $this->createMock(UuidInterface::class),
      $manager, new AbilityScoreTracker($manager), $this->createMock(InstitutionMembershipService::class),
      $factions ?? $this->createMock(FactionGenerationService::class), $resolver,
      $this->createMock(CharacterWizardHardeningService::class),
      $feats ?? $this->createMock(FeatLibraryService::class),
      $this->createMock(SpellCatalogService::class),
    );
  }

  public function testPathfinderReferenceIncludesCoreCatalogs(): void {
    $reference = $this->service()->getPathfinderCharacterCreationReference();
    $this->assertSame('pathfinder-character-creation-reference.v1.json', $reference['file_name']);
    foreach (['ancestries', 'classes', 'backgrounds', 'equipment'] as $field) {
      $this->assertNotEmpty($reference[$field]);
    }
    $this->assertContains('feat_selections', $reference['allowed_update_fields']);
    $this->assertSame('fixture-tool', $reference['equipment']['weapons'][0]['id']);
  }

  public function testCanonicalSummaryDoesNotReadStaleWizardMirrors(): void {
    $service = $this->service();
    $payload = ['name' => 'Current Hero', 'basicInfo' => [], 'resources' => [], 'defenses' => [],
      'wizard' => ['name' => 'Stale Hero']];
    $this->assertSame('Current Hero', $service->buildSummary($payload)['name']);
    $this->assertSame('Stale Hero', $service->buildSummary(['wizard' => ['name' => 'Stale Hero']])['name']);
  }

  public function testPlanUsesNativeValidationWithoutSavingAndBindsRevision(): void {
    $form = $this->createMock(CharacterCreationStepForm::class);
    $form->expects($this->exactly(2))->method('validateDraft')->willReturn([
      'errors' => [], 'warnings' => [['message' => 'Choose remaining fields']], 'ready' => FALSE, 'next_step' => 2,
    ]);
    $form->expects($this->never())->method('saveValidatedDraft');
    $plan = $this->service($form)->planUpdates(77, ['name' => 'Revised Hero']);
    $this->assertSame('proposed_execution', $plan['intent']);
    $this->assertSame(3, $plan['proposed_execution']['arguments']['expected_revision']);
    $this->assertSame(['name' => 'Revised Hero'], $plan['proposed_execution']['arguments']['updates']);
  }

  public function testCatalogSearchPaginatesAndFullReadsPreserveTheOwningRecord(): void {
    $description = str_repeat('Original locally authored rules and effects. ', 20);
    $entries = [
      ['id' => 'fixture-b', 'name' => 'Original B', 'description' => $description, 'prerequisites' => 'Original prerequisite', 'effects' => ['original' => TRUE]],
      ['id' => 'fixture-a', 'name' => 'Original A', 'description' => 'Original short local description.'],
    ];
    $feats = $this->createMock(FeatLibraryService::class);
    $feats->expects($this->exactly(3))->method('getFeats')->willReturn($entries);
    $service = $this->service(NULL, NULL, NULL, $feats);
    $first = $service->searchCatalog('feat', 'locally authored', 1);
    $this->assertSame(1, $first['total']);
    $this->assertSame($description, $first['entries'][0]['description']);
    $page = $service->searchCatalog('feat', '', 1, 1);
    $this->assertSame('fixture-b', $page['entries'][0]['id']);
    $this->assertNull($page['next_offset']);
    $this->assertSame($entries[0], $service->getCatalogEntry('feat', 'Original B')['entry']);
  }

  public function testMissingLocalCatalogEntryDoesNotInventOrFetchContent(): void {
    $this->expectException(\OutOfBoundsException::class);
    $this->expectExceptionCode(404);
    $this->service()->getCatalogEntry('spell', 'original-absent-fixture');
  }

  public function testSharedHeritageIdsRequireAncestryInsteadOfReturningTheWrongRules(): void {
    $service = $this->service();
    $matches = array_values(array_filter($service->searchCatalog('heritage', 'cavern')['entries'], static fn(array $entry): bool => $entry['id'] === 'cavern'));
    $this->assertGreaterThan(1, count($matches));
    foreach ($matches as $entry) {
      $this->assertSame($entry, $service->getCatalogEntry('heritage', 'cavern', $entry['ancestry'])['entry']);
    }
    $this->expectException(\InvalidArgumentException::class);
    $service->getCatalogEntry('heritage', 'cavern');
  }
  #[\PHPUnit\Framework\Attributes\DataProvider('invalidPatches')]
  public function testInvalidUpdatesAreRejectedInsteadOfSilentlyDropped(array $updates): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->service()->planUpdates(77, $updates);
  }

  public static function invalidPatches(): array {
    return [
      'unsupported field' => [['wizard_complete' => TRUE]],
      'unknown ancestry' => [['ancestry' => 'unrecognized-fixture-ancestry']],
      'invalid roleplay style' => [['roleplay_style' => 'unrecognized-fixture-style']],
      'non-list boosts' => [['free_boosts' => 'str']],
      'unknown ability' => [['free_boosts' => ['unrecognized-fixture-ability']]],
      'duplicate ability aliases' => [['free_boosts' => ['str', 'strength']]],
      'duplicate abilities' => [['free_boosts' => ['str', 'str']]],
    ];
  }

  public function testRuntimeMutationIsRejectedBeforePlanning(): void {
    $record = (object) ['id' => 77, 'uid' => 7, 'version' => 3, 'campaign_id' => 9,
      'status' => 1, 'lifecycle_state' => 'campaign_runtime', 'character_data' => '{}'];
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionCode(403);
    $this->service(NULL, $record)->planUpdates(77, ['name' => 'Not Applied']);
  }

  public function testOtherOwnersCannotReadDrafts(): void {
    $record = (object) ['id' => 77, 'uid' => 8];
    $this->expectException(\UnexpectedValueException::class);
    $this->service(NULL, $record)->getDraft(77);
  }

  public function testFactionCreationPreservesExistingSubjectIds(): void {
    $factions = $this->createMock(FactionGenerationService::class);
    $factions->method('isGenerationStorageReady')->willReturn(TRUE);
    $factions->expects($this->once())->method('createOrReuseFactionForNeed')
      ->with(97, $this->callback(static fn(array $request): bool => $request['label'] === 'Original Fixture Guild'))
      ->willReturn(['campaignSubjectId' => 'original-fixture-guild']);
    $service = $this->service(NULL, NULL, $factions);
    $data = ['faction_refs_create_labels' => ['Original Fixture Guild'],
      'faction_refs_create_why' => 'An original fixture need.', 'faction_refs_create_public_face' => 'A fixture guild.',
      'faction_refs' => [['subject_id' => 'existing-fixture-guild']]];
    $method = new \ReflectionMethod($service, 'resolveFactionDraftCreations');
    $method->invokeArgs($service, [&$data, 97]);
    $this->assertSame(['existing-fixture-guild', 'original-fixture-guild'], $data['faction_refs']);
    $this->assertArrayNotHasKey('faction_refs_create_labels', $data);
  }

}
