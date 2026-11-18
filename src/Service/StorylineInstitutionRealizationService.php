<?php

namespace Drupal\dungeoncrawler_content\Service;

/**
 * Makes a storyline's authored institutions real in a campaign.
 *
 * Storyline authoring is a seed source for the institution framework. This
 * service writes nothing itself; it hands each authored fact to its owner:
 * memberships and actor-held sentiments to InstitutionMembershipService,
 * subjects and hierarchy to CampaignSubjectRegistryService, and
 * institution->institution stances to InstitutionDispositionMatrixService.
 *
 * Only institutions at least one contact is tied to (any standing) are
 * realized, and only stances and parents between realized institutions.
 */
class StorylineInstitutionRealizationService {

  public const SEED_SOURCE = 'storyline_authored';
  public const STANCE_SEED_PROFILE_KEY = 'storyline-authored-stance';

  /**
   * Attitude an actor holds toward an institution it is tied to but does not
   * belong to, by authored standing.
   *
   * An exile is estranged from the institution that cast it out, not at war
   * with it: `unfriendly` stays above the hostility threshold, so the tie
   * alone never starts a fight.
   */
  protected const TIE_ATTITUDES = [
    'ally' => DispositionAuthorityContract::LABEL_FRIENDLY,
    'exile' => DispositionAuthorityContract::LABEL_UNFRIENDLY,
  ];

  protected const MEMBER_STANDINGS = ['member', 'leader'];

  public function __construct(
    protected readonly CampaignSubjectRegistryService $campaignSubjectRegistry,
    protected readonly InstitutionNormalizationService $institutionNormalization,
    protected readonly InstitutionMembershipService $institutionMembership,
    protected readonly InstitutionDispositionMatrixService $institutionDispositionMatrix,
    protected readonly InstitutionLibraryService $institutionLibrary,
  ) {}

  /**
   * Realizes the institutions of an attached campaign storyline.
   *
   * Safe to repeat: subjects and seeded edges are normalized in place, and a
   * stance or sentiment play has mutated is left as it is.
   *
   * @param array<string, mixed> $storyline
   *   Campaign storyline whose contacts carry their runtime_entity_id.
   *
   * @return array{subjects: int, memberships_synced: int, stances: int, ties: int}
   *   Institutions realized, NPCs whose memberships were synced, and authored
   *   stances and exile/ally ties handed to their authorities.
   */
  public function realizeStorylineInstitutions(int $campaign_id, array $storyline): array {
    if ($campaign_id <= 0) {
      throw new \InvalidArgumentException('Campaign id must be greater than zero.');
    }
    $storyline_id = trim((string) ($storyline['storyline_id'] ?? ''));
    if ($storyline_id === '') {
      throw new \InvalidArgumentException('Storyline institutions require the campaign storyline_id.');
    }
    $metadata = (array) ($storyline['storyline_data']['metadata'] ?? []);
    $contacts = array_values(array_filter((array) ($storyline['storyline_data']['contacts'] ?? []), 'is_array'));

    $institutions = [];
    foreach (array_filter((array) ($metadata['storyline_builder']['institutions'] ?? []), 'is_array') as $institution) {
      $institutions[trim((string) ($institution['institution_id'] ?? ''))] = $institution;
    }

    $contacts_by_entity_id = [];
    $ties = [];
    $tied = [];
    foreach ($contacts as $contact) {
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      $contacts_by_entity_id[$entity_id] = $contact;
      foreach (array_filter((array) ($contact['relationship_state']['authored_affiliations'] ?? []), 'is_array') as $affiliation) {
        $institution_id = trim((string) ($affiliation['institution_id'] ?? ''));
        if (!isset($institutions[$institution_id])) {
          throw new \InvalidArgumentException(sprintf('Storyline "%s" contact "%s" is tied to institution "%s", which the storyline does not define.', $storyline_id, $entity_id, $institution_id));
        }
        $standing = (string) ($affiliation['standing'] ?? 'member');
        $tied[$institution_id] = TRUE;
        if (in_array($standing, self::MEMBER_STANDINGS, TRUE)) {
          continue;
        }
        if (!isset(self::TIE_ATTITUDES[$standing])) {
          throw new \InvalidArgumentException(sprintf('Storyline "%s" contact "%s" has unsupported standing "%s" in institution "%s".', $storyline_id, $entity_id, $standing, $institution_id));
        }
        $ties[] = ['contact' => $contact, 'institution_id' => $institution_id, 'standing' => $standing];
      }
    }

    // An institution a contact is tied to brings the institutions it sits
    // inside with it: membership is inherited up the hierarchy.
    foreach (array_keys($tied) as $institution_id) {
      $parent_id = trim((string) ($institutions[$institution_id]['parent_institution_id'] ?? ''));
      while ($parent_id !== '' && !isset($tied[$parent_id])) {
        if (!isset($institutions[$parent_id])) {
          throw new \InvalidArgumentException(sprintf('Storyline "%s" institution "%s" sits inside "%s", which the storyline does not define.', $storyline_id, $institution_id, $parent_id));
        }
        $tied[$parent_id] = TRUE;
        $parent_id = trim((string) ($institutions[$parent_id]['parent_institution_id'] ?? ''));
      }
    }
    // Parents first, so a member's inherited memberships resolve.
    $depth = static function (string $id) use ($institutions): int {
      $steps = 0;
      $seen = [];
      while (($id = trim((string) ($institutions[$id]['parent_institution_id'] ?? ''))) !== '' && isset($institutions[$id]) && !isset($seen[$id])) {
        $seen[$id] = TRUE;
        $steps++;
      }
      return $steps;
    };
    uksort($tied, static fn(string $a, string $b): int => $depth($a) <=> $depth($b));

    $summary = ['subjects' => 0, 'memberships_synced' => 0, 'stances' => 0, 'ties' => 0];

    foreach (array_keys($tied) as $institution_id) {
      $institution = $institutions[$institution_id];
      $normalized = $this->institutionNormalization->normalizeInstitutionInput([
        'domain' => (string) ($institution['domain'] ?? ''),
        'display_name' => (string) ($institution['name'] ?? ''),
      ]);
      if ($this->institutionLibrary->loadRow($normalized['normalized_label']) === NULL) {
        throw new \RuntimeException(sprintf('Storyline "%s" institution "%s" has no record in the canonical institution library; a campaign only instantiates library institutions.', $storyline_id, $institution_id));
      }
      $parent_id = trim((string) ($institution['parent_institution_id'] ?? ''));
      $subject = $this->campaignSubjectRegistry->resolveOrCreateInstitutionSubject($campaign_id, [
        'domain' => $normalized['domain'],
        'display_name' => $normalized['display_name'],
        'source_asset_type' => InstitutionLibraryService::CAMPAIGN_SOURCE_ASSET_TYPE,
        'source_asset_id' => $normalized['normalized_label'],
        'parent_subject_id' => isset($tied[$parent_id]) ? $parent_id : '',
        'metadata' => [
          'source_storyline_id' => $storyline_id,
          'description' => trim((string) ($institution['description'] ?? '')),
          'purpose' => trim((string) ($institution['purpose'] ?? '')),
          'seat_id' => trim((string) ($institution['seat_id'] ?? '')),
          'leader' => $this->buildLeader($storyline_id, $institution_id, trim((string) ($institution['leader_actor_id'] ?? '')), $contacts_by_entity_id),
        ],
      ]);
      if ((string) $subject['subject_id'] !== $institution_id) {
        throw new \RuntimeException(sprintf('Storyline "%s" institution "%s" resolved to campaign subject "%s"; an institution_id must equal the subject id of its domain and name.', $storyline_id, $institution_id, (string) $subject['subject_id']));
      }
      $summary['subjects']++;
    }

    // Subjects exist before members are synced, so each member's seeded
    // neutral sentiments already cover every institution of the storyline.
    $summary['memberships_synced'] = $this->institutionMembership->syncStorylineContactMemberships($campaign_id, $storyline);

    foreach (array_keys($tied) as $institution_id) {
      foreach (array_filter((array) ($institutions[$institution_id]['stances'] ?? []), 'is_array') as $stance) {
        $toward = trim((string) ($stance['toward_institution_id'] ?? ''));
        if (!isset($tied[$toward])) {
          continue;
        }
        $attitude = (string) ($stance['attitude'] ?? '');
        $score = DispositionAuthorityContract::attitudeToScore($attitude);
        if ($score === NULL) {
          throw new \InvalidArgumentException(sprintf('Storyline "%s" institution "%s" has unsupported stance attitude "%s" toward "%s".', $storyline_id, $institution_id, $attitude, $toward));
        }
        $this->institutionDispositionMatrix->seedAuthoredInstitutionDispositionEdge(
          $campaign_id,
          $institution_id,
          $toward,
          $score,
          self::SEED_SOURCE,
          self::STANCE_SEED_PROFILE_KEY,
          trim((string) ($stance['reason'] ?? ''))
        );
        $summary['stances']++;
      }
    }

    foreach ($ties as $tie) {
      $entity_id = trim((string) ($tie['contact']['entity_id'] ?? ''));
      $instance_id = trim((string) ($tie['contact']['runtime_entity_id'] ?? ''));
      if ($instance_id === '') {
        throw new \RuntimeException(sprintf('Storyline contact "%s" is tied to institution "%s" but has no runtime_entity_id; realize the storyline NPCs first.', $entity_id, $tie['institution_id']));
      }
      $this->institutionMembership->seedAuthoredInstitutionSentiment(
        $campaign_id,
        'campaign_npc',
        $instance_id,
        $tie['institution_id'],
        (int) DispositionAuthorityContract::attitudeToScore(self::TIE_ATTITUDES[$tie['standing']]),
        self::SEED_SOURCE,
        'storyline-authored-' . $tie['standing'],
        sprintf('Authored as %s of the institution in storyline %s.', $tie['standing'], $storyline_id)
      );
      $summary['ties']++;
    }

    return $summary;
  }

  /**
   * The institution's leader as subject metadata, or NULL when it has none.
   *
   * @param array<string, array<string, mixed>> $contacts_by_entity_id
   *
   * @return array{entity_id: string, runtime_entity_id?: string}|null
   */
  protected function buildLeader(string $storyline_id, string $institution_id, string $leader_actor_id, array $contacts_by_entity_id): ?array {
    if ($leader_actor_id === '') {
      return NULL;
    }
    if (!isset($contacts_by_entity_id[$leader_actor_id])) {
      throw new \InvalidArgumentException(sprintf('Storyline "%s" institution "%s" is led by "%s", who is not a contact of the storyline.', $storyline_id, $institution_id, $leader_actor_id));
    }
    $leader = ['entity_id' => $leader_actor_id];
    $runtime_entity_id = trim((string) ($contacts_by_entity_id[$leader_actor_id]['runtime_entity_id'] ?? ''));
    if ($runtime_entity_id !== '') {
      $leader['runtime_entity_id'] = $runtime_entity_id;
    }
    return $leader;
  }

}
