<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\dungeoncrawler_content\Service\Actor\ActorDefinitionMirror;
use Drupal\dungeoncrawler_content\Service\CanonicalDefinitionService;
use Drupal\dungeoncrawler_content\Service\CharacterCreationGmService;
use Drupal\dungeoncrawler_content\Service\Generation\CanonicalGenerationService;
use Drupal\dungeoncrawler_content\Service\StorylineActorSheetService;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use PHPUnit\Framework\TestCase;

/**
 * @group dungeoncrawler_content
 */
class StorylineActorSheetServiceTest extends TestCase {

  private array $draft;
  private array $sheets = [];
  private array $links = [];
  private int $generations = 0;
  private int $builds = 0;
  private ?string $failActor = NULL;
  private bool $invalidGeneratedClass = FALSE;

  private function service(): StorylineActorSheetService {
    $this->draft = ['draft_id' => 'original-fixture-draft', 'revision' => 3, 'status' => 'editing', 'payload' => [
      'title' => 'Original border expedition',
      'objects' => ['actors' => ['generation_specs' => [
        ['contact_id' => 'contact-guard', 'actor_id' => 'original-guard', 'role' => 'guard', 'display_name' => 'Original Guard'],
        ['contact_id' => 'contact-scout', 'actor_id' => 'original-scout', 'role' => 'scout', 'display_name' => 'Original Scout'],
      ]]],
    ]];
    $drafts = $this->createMock(StorylineAuthoringDraftService::class);
    $drafts->method('getDraft')->willReturnCallback(fn(): array => $this->draft);
    $drafts->method('saveDraft')->willReturnCallback(function (string $id, int $revision, array $payload): array {
      $this->assertSame($this->draft['revision'], $revision);
      $this->draft['payload'] = $payload;
      $this->draft['revision']++;
      return $this->draft;
    });
    $characters = $this->createMock(CharacterCreationGmService::class);
    $reference = [
      'classes' => [['id' => 'guard-class', 'name' => 'Original Guardian'], ['id' => 'scout-class', 'name' => 'Original Trailkeeper']],
      'ancestries' => [['id' => 'fixture-folk', 'name' => 'Original Fixture Folk']],
      'backgrounds' => [['id' => 'watch-background', 'name' => 'Original Watcher']],
      'alignments' => ['LG', 'N', 'CE'],
    ];
    $characters->method('getPathfinderCharacterCreationReference')->willReturn($reference);
    $characters->method('getDraft')->willReturnCallback(fn(int $id): array => $this->sheets[$id]);
    $characters->method('createDraft')->willReturnCallback(function (): array {
      $id = 101 + count($this->sheets);
      return $this->sheets[$id] = [
        'character_id' => $id, 'revision' => 0, 'campaign_id' => 0, 'status' => 0, 'lifecycle_state' => 'draft_library',
        'payload' => ['class' => '', 'ancestry' => '', 'level' => 1],
      ];
    });
    $characters->method('validateData')->willReturnCallback(static fn(array $payload): array => ['ready' => !empty($payload['class'])]);
    $characters->method('autofillDraft')->willReturnCallback(function (int $id, array $preferences): array {
      $this->builds++;
      if ($preferences['name'] === $this->failActor) {
        throw new \RuntimeException('native_offered_choice_rejected');
      }
      $this->sheets[$id]['payload'] = $preferences + $this->sheets[$id]['payload'];
      $this->sheets[$id]['revision']++;
      return ['draft' => $this->sheets[$id], 'validation' => ['ready' => TRUE]];
    });
    $mirror = $this->createMock(ActorDefinitionMirror::class);
    $mirror->method('libraryRow')->willReturnCallback(fn(string $id): ?array => isset($this->links[$id]) ? ['id' => $this->links[$id]] : NULL);
    $definitions = $this->createMock(CanonicalDefinitionService::class);
    $definitions->method('attachCharacterSheet')->willReturnCallback(function (string $id, int $character_id): array {
      $this->links[$id] = $character_id;
      $this->sheets[$character_id]['revision']++;
      $this->sheets[$character_id]['lifecycle_state'] = 'canonical_template';
      return ['committed' => TRUE];
    });
    $generation = $this->createMock(CanonicalGenerationService::class);
    $generation->method('completeJson')->willReturnCallback(function ($tool, $operation, $seed, $tokens, callable $prompt, callable $validate): array {
      $this->generations++;
      $text = $prompt([]);
      $input = json_decode(substr($text, strpos($text, '{"storyline"')), TRUE, 512, JSON_THROW_ON_ERROR);
      $actors = [];
      foreach ($input['requested_actors'] as $row) {
        $actors[] = ['actor_id' => $row['actor']['actor_id'], 'preferences' => $row['fixed_preferences'] + [
          'class' => $this->invalidGeneratedClass ? 'invented-class' : ($row['actor']['role'] === 'scout' ? 'scout-class' : 'guard-class'),
          'ancestry' => 'fixture-folk', 'background' => 'watch-background', 'alignment' => 'LG',
        ]];
      }
      return $validate(['actors' => $actors], ['tool' => $tool]);
    });
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturn(TRUE);
    $database = $this->createMock(Connection::class);
    $transaction = new class extends Transaction {
      public function __construct() {}
      public function __destruct() {}
      public function rollBack() {}
    };
    $database->method('startTransaction')->willReturn($transaction);
    return new StorylineActorSheetService($database, $lock, $drafts, $characters, $definitions, $mirror, $generation);
  }

  public function testWholeRosterUsesNativeChoicesAndRetryReusesEachSheet(): void {
    $service = $this->service();
    $built = $service->build($this->draft['draft_id'], 3);
    $this->assertSame(2, $built['completed_count']);
    $this->assertSame(['guard-class', 'scout-class'], array_column($built['actors'], 'class'));
    $this->assertSame([101, 102], array_column($built['actors'], 'character_id'));
    $this->assertSame(1, $this->generations);
    $this->assertSame(2, $this->builds);
    $retry = $service->build($this->draft['draft_id'], $this->draft['revision']);
    $this->assertSame([101, 102], array_column($retry['actors'], 'character_id'));
    $this->assertSame([TRUE, TRUE], array_column($retry['actors'], 'reused'));
    $this->assertSame(2, count($this->sheets));
    $this->assertSame(2, $this->builds);
    $this->assertSame(1, $this->generations);
  }

  public function testSelectedActorAndExplicitPreferencesAreRespected(): void {
    $service = $this->service();
    $built = $service->build($this->draft['draft_id'], 3, ['original-scout'], [
      'original-scout' => ['ancestry' => 'fixture-folk', 'class' => 'guard-class'],
    ]);
    $this->assertSame(['original-scout'], array_column($built['actors'], 'actor_id'));
    $this->assertSame(['guard-class'], array_column($built['actors'], 'class'));
    $this->assertArrayNotHasKey('character_id', $this->draft['payload']['objects']['actors']['generation_specs'][0]);
  }

  public function testRetryPreservesLaterNativeSheetEdits(): void {
    $service = $this->service();
    $service->build($this->draft['draft_id'], 3);
    $this->sheets[101]['payload']['class'] = 'scout-class';
    $this->sheets[101]['payload']['name'] = 'Revised Native Name';
    $this->sheets[101]['revision']++;
    $retry = $service->build($this->draft['draft_id'], $this->draft['revision']);
    $this->assertSame('scout-class', $retry['actors'][0]['class']);
    $this->assertSame('Revised Native Name', $this->sheets[101]['payload']['name']);
    $this->assertSame('scout-class', $this->draft['payload']['objects']['actors']['generation_specs'][0]['class']);
    $this->assertSame(2, $this->builds);
    $this->assertSame(1, $this->generations);
  }

  public function testArchivedLibraryActorCannotBeReused(): void {
    $service = $this->service();
    $service->build($this->draft['draft_id'], 3);
    $this->sheets[101]['status'] = 2;
    $this->expectExceptionMessage('storyline_actor_sheet_archived:original-guard');
    $service->build($this->draft['draft_id'], $this->draft['revision']);
  }

  public function testCampaignActorCannotBeUsedAsLibrarySheet(): void {
    $service = $this->service();
    $service->build($this->draft['draft_id'], 3);
    $this->sheets[101]['campaign_id'] = 17;
    $this->expectExceptionMessage('storyline_actor_sheet_campaign_runtime_forbidden:original-guard');
    $service->build($this->draft['draft_id'], $this->draft['revision']);
  }

  public function testInvalidNativePlanFailsBeforeCreatingAnySheet(): void {
    $service = $this->service();
    $this->invalidGeneratedClass = TRUE;
    try {
      $service->build($this->draft['draft_id'], 3);
      $this->fail('Invalid native choices must not be saved.');
    }

    catch (\RuntimeException $exception) {
      $this->assertSame('generation_nonconforming', $exception->getMessage());
      $this->assertSame([], $this->sheets);
      $this->assertSame(3, $this->draft['revision']);
    }
  }

  public function testExplicitNullClassIsNotTreatedAsAutofillPermission(): void {
    $service = $this->service();
    $this->expectExceptionMessage('storyline_actor_sheet_native_choice_invalid:class:null');
    $service->build($this->draft['draft_id'], 3, ['original-guard'], ['original-guard' => ['class' => NULL]]);
  }

  public function testPartialFailureIdentifiesPersistedActorsAndTheNativeFailure(): void {
    $service = $this->service();
    $this->failActor = 'Original Scout';
    $this->expectExceptionMessage('original-scout; completed=original-guard; failed actor changes rolled back; native_offered_choice_rejected');
    $service->build($this->draft['draft_id'], 3);
  }

  public function testStaleRevisionDoesNotCreateSheets(): void {
    $service = $this->service();
    $this->expectExceptionMessage('storyline_actor_sheet_revision_conflict');
    $service->build($this->draft['draft_id'], 2);
  }

  public function testLinkToAnotherActorIsRejected(): void {
    $service = $this->service();
    $this->draft['payload']['objects']['actors']['generation_specs'][0]['character_id'] = 999;
    $this->expectExceptionMessage('storyline_actor_sheet_link_conflict:original-guard');
    $service->build($this->draft['draft_id'], 3);
  }

}
