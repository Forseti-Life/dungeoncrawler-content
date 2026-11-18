<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\dungeoncrawler_content\Service\CampaignSubjectRegistryService;
use Drupal\dungeoncrawler_content\Service\InstitutionDispositionMatrixService;
use Drupal\dungeoncrawler_content\Service\InstitutionMembershipService;
use Drupal\dungeoncrawler_content\Service\InstitutionNormalizationService;
use Drupal\dungeoncrawler_content\Service\StorylineInstitutionRealizationService;
use Drupal\Tests\UnitTestCase;

/**
 * Covers realization of a storyline's authored institutions in a campaign.
 *
 * @group dungeoncrawler_content
 * @group social
 * @coversDefaultClass \Drupal\dungeoncrawler_content\Service\StorylineInstitutionRealizationService
 */
class StorylineInstitutionRealizationServiceTest extends UnitTestCase {

  /**
   * @covers ::realizeStorylineInstitutions
   */
  public function testRealizesTiedInstitutionsStancesAndTiesThroughTheirAuthorities(): void {
    $subject_inputs = [];
    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('resolveOrCreateInstitutionSubject')
      ->willReturnCallback(static function (int $campaign_id, array $input) use (&$subject_inputs): array {
        $subject_id = 'institution_' . $input['domain'] . '_' . strtolower(str_replace(' ', '-', $input['display_name']));
        $subject_inputs[$subject_id] = $input;
        return ['subject_id' => $subject_id];
      });

    $storyline = $this->buildStoryline();
    $sentiments = [];
    $membership = $this->createMock(InstitutionMembershipService::class);
    $membership->expects($this->once())
      ->method('syncStorylineContactMemberships')
      ->with(42, $storyline)
      ->willReturn(3);
    $membership->method('seedAuthoredInstitutionSentiment')
      ->willReturnCallback(static function (...$arguments) use (&$sentiments): int {
        $sentiments[] = $arguments;
        return 1;
      });

    $edges = [];
    $matrix = $this->createMock(InstitutionDispositionMatrixService::class);
    $matrix->method('seedAuthoredInstitutionDispositionEdge')
      ->willReturnCallback(static function (...$arguments) use (&$edges): int {
        $edges[] = $arguments;
        return 1;
      });

    $service = new StorylineInstitutionRealizationService($registry, new InstitutionNormalizationService(), $membership, $matrix);
    $summary = $service->realizeStorylineInstitutions(42, $storyline);

    $this->assertSame(['subjects' => 3, 'memberships_synced' => 3, 'stances' => 2, 'ties' => 2], $summary);

    // The Old Kingdom has no contact tied to it: no subject, parent or stance.
    $this->assertSame(
      ['institution_family_blackroot-clan', 'institution_family_blackblack-clan', 'institution_security_ford-watch'],
      array_keys($subject_inputs)
    );
    $blackroot = $subject_inputs['institution_family_blackroot-clan'];
    $this->assertSame('storyline', $blackroot['source_asset_type']);
    $this->assertSame('clans-blight:family:blackroot-clan', $blackroot['source_asset_id']);
    $this->assertSame('', $blackroot['parent_subject_id']);
    $this->assertSame([
      'source_storyline_id' => 'clans-blight-c42',
      'description' => 'Root-farmers of the vale.',
      'purpose' => 'Hold the vale.',
      'seat_id' => 'room-longhouse',
      'leader' => ['entity_id' => 'actor-elder', 'runtime_entity_id' => 'campaign_42_npc_elder'],
    ], $blackroot['metadata']);
    $this->assertSame('institution_family_blackroot-clan', $subject_inputs['institution_family_blackblack-clan']['parent_subject_id']);
    $this->assertNull($subject_inputs['institution_family_blackblack-clan']['metadata']['leader']);

    $this->assertSame([
      [42, 'institution_family_blackblack-clan', 'institution_family_blackroot-clan', -100, 'storyline_authored', 'storyline-authored-stance', 'Blood-debt.'],
      [42, 'institution_security_ford-watch', 'institution_family_blackroot-clan', -50, 'storyline_authored', 'storyline-authored-stance', ''],
    ], $edges);

    $this->assertSame([
      [42, 'campaign_npc', 'campaign_42_npc_outcast', 'institution_family_blackroot-clan', -50, 'storyline_authored', 'storyline-authored-exile', 'Authored as exile of the institution in storyline clans-blight-c42.'],
      [42, 'campaign_npc', 'campaign_42_npc_friend', 'institution_family_blackblack-clan', 50, 'storyline_authored', 'storyline-authored-ally', 'Authored as ally of the institution in storyline clans-blight-c42.'],
    ], $sentiments);
  }

  /**
   * @covers ::realizeStorylineInstitutions
   */
  public function testTieWithoutRealizedNpcFailsLoudly(): void {
    $storyline = $this->buildStoryline();
    unset($storyline['storyline_data']['contacts'][2]['runtime_entity_id']);

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->method('resolveOrCreateInstitutionSubject')
      ->willReturnCallback(static fn (int $campaign_id, array $input): array => [
        'subject_id' => 'institution_' . $input['domain'] . '_' . strtolower(str_replace(' ', '-', $input['display_name'])),
      ]);
    $membership = $this->createMock(InstitutionMembershipService::class);
    $membership->expects($this->never())->method('seedAuthoredInstitutionSentiment');

    $service = new StorylineInstitutionRealizationService(
      $registry,
      new InstitutionNormalizationService(),
      $membership,
      $this->createMock(InstitutionDispositionMatrixService::class)
    );

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Storyline contact "actor-outcast" is tied to institution "institution_family_blackroot-clan" but has no runtime_entity_id');
    $service->realizeStorylineInstitutions(42, $storyline);
  }

  /**
   * @covers ::realizeStorylineInstitutions
   */
  public function testUnsupportedStandingFailsBeforeAnythingIsWritten(): void {
    $storyline = $this->buildStoryline();
    $storyline['storyline_data']['contacts'][3]['relationship_state']['authored_affiliations'][0]['standing'] = 'rival';

    $registry = $this->createMock(CampaignSubjectRegistryService::class);
    $registry->expects($this->never())->method('resolveOrCreateInstitutionSubject');
    $membership = $this->createMock(InstitutionMembershipService::class);
    $membership->expects($this->never())->method('syncStorylineContactMemberships');
    $membership->expects($this->never())->method('seedAuthoredInstitutionSentiment');

    $service = new StorylineInstitutionRealizationService(
      $registry,
      new InstitutionNormalizationService(),
      $membership,
      $this->createMock(InstitutionDispositionMatrixService::class)
    );

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('unsupported standing "rival"');
    $service->realizeStorylineInstitutions(42, $storyline);
  }

  /**
   * A campaign storyline with two clans, a watch and an untied kingdom.
   */
  private function buildStoryline(): array {
    $contact = static fn (string $actor, array $affiliations): array => [
      'entity_type' => 'campaign_npc',
      'entity_id' => 'actor-' . $actor,
      'runtime_entity_id' => 'campaign_42_npc_' . $actor,
      'relationship_state' => ['authored_affiliations' => $affiliations],
    ];

    return [
      'storyline_id' => 'clans-blight-c42',
      'storyline_data' => [
        'metadata' => [
          'template_id' => 'clans-blight',
          'storyline_builder' => [
            'institutions' => [
              [
                'institution_id' => 'institution_family_blackroot-clan',
                'domain' => 'family',
                'name' => 'Blackroot Clan',
                'description' => 'Root-farmers of the vale.',
                'purpose' => 'Hold the vale.',
                'seat_id' => 'room-longhouse',
                'leader_actor_id' => 'actor-elder',
                'parent_institution_id' => 'institution_government_old-kingdom',
                'stances' => [],
              ],
              [
                'institution_id' => 'institution_family_blackblack-clan',
                'domain' => 'family',
                'name' => 'Blackblack Clan',
                'parent_institution_id' => 'institution_family_blackroot-clan',
                'stances' => [
                  ['toward_institution_id' => 'institution_family_blackroot-clan', 'attitude' => 'hostile', 'reason' => 'Blood-debt.'],
                  ['toward_institution_id' => 'institution_government_old-kingdom', 'attitude' => 'friendly', 'reason' => 'Old oaths.'],
                ],
              ],
              [
                'institution_id' => 'institution_security_ford-watch',
                'domain' => 'security',
                'name' => 'Ford Watch',
                'stances' => [
                  ['toward_institution_id' => 'institution_family_blackroot-clan', 'attitude' => 'unfriendly'],
                ],
              ],
              [
                'institution_id' => 'institution_government_old-kingdom',
                'domain' => 'government',
                'name' => 'Old Kingdom',
                'stances' => [
                  ['toward_institution_id' => 'institution_family_blackroot-clan', 'attitude' => 'helpful', 'reason' => 'Fealty.'],
                ],
              ],
            ],
          ],
        ],
        'contacts' => [
          $contact('elder', [['institution_id' => 'institution_family_blackroot-clan', 'standing' => 'leader']]),
          $contact('rival', [['institution_id' => 'institution_family_blackblack-clan']]),
          $contact('outcast', [['institution_id' => 'institution_family_blackroot-clan', 'standing' => 'exile']]),
          $contact('friend', [
            ['institution_id' => 'institution_security_ford-watch', 'standing' => 'member'],
            ['institution_id' => 'institution_family_blackblack-clan', 'standing' => 'ally'],
          ]),
        ],
      ],
    ];
  }

}
