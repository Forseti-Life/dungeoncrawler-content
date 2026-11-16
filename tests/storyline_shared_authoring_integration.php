<?php

/**
 * @file
 * Focused live-container integration; every original fixture is rolled back.
 *
 * Run with the site's Drush php:script command. No model or player writes.
 */

use Drupal\dungeoncrawler_content\Controller\StorylineManagementController;
use Drupal\dungeoncrawler_content\Service\StorylineAuthoringDraftService;
use Drupal\dungeoncrawler_content\Service\StorylineManagerService;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

$database = \Drupal::database();
$transaction = $database->startTransaction();
$original_account = \Drupal::currentUser()->getAccount();
$checks = 0;
$check = static function (bool $valid, string $message) use (&$checks): void {
  if (!$valid) {
    throw new RuntimeException($message);
  }
  $checks++;
};
$reject = static function (callable $call, string $message) use ($check): void {
  try {
    $call();
  }
  catch (DomainException | UnexpectedValueException | OutOfBoundsException $exception) {
    $check($exception->getMessage() === $message, 'Expected explicit rejection: ' . $message);
    return;
  }
  throw new RuntimeException('Request was not rejected: ' . $message);
};

try {
  \Drupal::currentUser()->setAccount(User::load(1));
  $drafts = \Drupal::service('dungeoncrawler_content.storyline_authoring_drafts');
  $source = $drafts->createDraft();
  $prefix = 'original-shared-' . substr($source['draft_id'], 0, 8);
  $role = Role::create(['id' => $prefix, 'label' => 'Original shared authoring fixture']);
  $role->grantPermission('edit canonical dungeoncrawler storylines')->save();
  $editor = User::create(['name' => $prefix . '-editor', 'status' => 1, 'roles' => [$role->id()]]);
  $editor->save();
  $viewer = User::create(['name' => $prefix . '-viewer', 'status' => 1]);
  $viewer->save();
  $payload = $source['payload'];
  $payload['title'] = 'Original Shared Village Watch';
  $payload['storyline_id'] = $prefix;
  $payload['description'] = 'An original shared authoring fixture.';
  $source = $drafts->saveDraft($source['draft_id'], $source['revision'], $payload);
  $source_revision = $source['revision'];
  $template_id = $prefix . '-template';
  $database->update('dungeoncrawler_content_storyline_authoring_drafts')
    ->fields(['source_template_id' => $template_id])
    ->condition('draft_id', $source['draft_id'])->execute();
  $count_before = (int) $database->select('dungeoncrawler_content_storyline_authoring_drafts', 'd')->countQuery()->execute()->fetchField();

  \Drupal::currentUser()->setAccount($editor);
  $loaded = $drafts->getDraft($source['draft_id']);
  $check($loaded['can_edit'] && !$loaded['is_owner'], 'A different authorized editor must edit the same draft.');
  $check($drafts->createDraft($template_id)['draft_id'] === $source['draft_id'], 'Opening a storyline must reuse its shared draft across editors.');
  $check($drafts->findLatestDraftForTemplate($template_id, TRUE)['draft_id'] === $source['draft_id'], 'Continue Draft must resolve the shared editing draft.');
  $payload['description'] = 'Updated by a second authorized editor.';
  $saved = $drafts->saveDraft($source['draft_id'], $source_revision, $payload);
  $check($saved['draft_id'] === $source['draft_id'] && $saved['revision'] === $source_revision + 1, 'Collaborative save must advance the same draft.');
  $check($saved['owner_uid'] === 1 && $saved['updated_by_uid'] === (int) $editor->id(), 'Creator and revision updater must remain distinct.');
  $revisions = $drafts->listDraftRevisions($source['draft_id']);
  $check($revisions[0]['updated_by_uid'] === (int) $editor->id(), 'Revision history must attribute the actual saving editor.');
  $historical = $drafts->getDraftRevisionForViewing($source['draft_id'], $source_revision);
  $check($historical['updated_by_uid'] === 1 && !$historical['can_edit'], 'Historical revisions remain immutable and retain their author.');
  $check($historical['payload']['description'] !== $saved['payload']['description'], 'History must preserve the prior payload.');
  $reject(static fn() => $drafts->saveDraft($source['draft_id'], $source_revision, $payload), 'storyline_draft_revision_conflict');
  $reject(static fn() => $drafts->deleteDraft($source['draft_id']), 'storyline_draft_not_owner');
  $reject(static fn() => $drafts->archiveDraft($source['draft_id']), 'storyline_draft_not_owner');
  $reject(static fn() => $drafts->publishDraft($source['draft_id'], $saved['revision']), 'storyline_draft_publish_access_denied');
  $restored = $drafts->saveDraft($source['draft_id'], $saved['revision'], $historical['payload']);
  $check($restored['revision'] === $saved['revision'] + 1, 'Restoring history must append a new revision to the same draft.');
  $check($drafts->getDraftRevisionForViewing($source['draft_id'], $saved['revision'])['payload'] === $saved['payload'], 'Restore must not rewrite the intervening revision.');
  $operations = [['type' => 'set', 'path' => '/description', 'value' => 'Shared operation receipt fixture.']];
  $operation_id = $prefix . '-operation';
  $applied = $drafts->applyDraftOperations($source['draft_id'], $restored['revision'], $operation_id, $operations);

  \Drupal::currentUser()->setAccount(User::load(1));
  $replayed = $drafts->applyDraftOperations($source['draft_id'], $restored['revision'], $operation_id, $operations);
  $check($replayed['idempotent_replay'] && $replayed['result_revision'] === $applied['result_revision'], 'Operation receipts must be shared, not duplicated per editor.');
  $check($replayed['saved']['is_owner'], 'Replayed response capabilities must be projected for the current editor.');
  $check((int) $database->select('dungeoncrawler_content_storyline_authoring_drafts', 'd')->countQuery()->execute()->fetchField() === $count_before, 'Collaborative edits must never create personal copies.');
  $entries = array_column($drafts->listDrafts(), NULL, 'draft_id');
  $check($entries[$source['draft_id']]['updated_by_name'] === $editor->getAccountName(), 'List attribution must reflect the latest updater.');
  $check($entries[$source['draft_id']]['revision_count'] === count($drafts->listDraftRevisions($source['draft_id'])), 'Revision counts must include all editors.');

  \Drupal::currentUser()->setAccount($editor);
  $build = StorylineManagementController::create(\Drupal::getContainer())->listStorylines();
  $cards = array_values(array_filter($build['#storylines'], static fn(array $card): bool => str_contains($card['edit_url'], $source['draft_id'])));
  $check(count($cards) === 1 && $cards[0]['status'] === 'draft', 'Shared draft must appear exactly once in the unified library.');
  $check($cards[0]['updated_by_name'] === $editor->getAccountName(), 'Draft card must display updater attribution.');
  $check($cards[0]['archive_url'] === '', 'Collaborative editing must not expose another creator archive action.');
  $html = (string) \Drupal::service('renderer')->renderRoot($build);
  $check(str_contains($html, 'Storyline Library') && str_contains($html, 'Original Shared Village Watch'), 'Unified library must render the shared draft.');
  $check(!str_contains($html, 'My Storylines') && !str_contains($html, "Other's Storylines"), 'Authorship must not split the library.');
  $options = array_values(array_filter($drafts->listStorylineOptions(), static fn(array $row): bool => ($row['draft_id'] ?? '') === $source['draft_id']));
  $check(count($options) === 1 && $options[0]['can_edit'], 'Builder selector must include the same shared editable draft.');

  \Drupal::currentUser()->setAccount($viewer);
  $reject(static fn() => $drafts->getDraft($source['draft_id']), 'storyline_draft_access_denied');
  $reject(static fn() => $drafts->listDraftRevisions($source['draft_id']), 'storyline_draft_access_denied');
  $reject(static fn() => $drafts->applyDraftOperations($source['draft_id'], $restored['revision'], $operation_id, $operations), 'storyline_draft_access_denied');
  $reject(static fn() => $drafts->saveDraft($source['draft_id'], $restored['revision'], $payload), 'storyline_draft_access_denied');

  \Drupal::currentUser()->setAccount($editor);
  $manager = new class() extends StorylineManagerService {

    /**
     * Canonical redirects supplied by the original fixture.
     *
     * @var array<string, string|null>
     */
    public array $redirects = [];

    /**
     * Original fixture identities that deliberately do not exist.
     *
     * @var string[]
     */
    public array $missing = [];

    public function __construct() {}

    /**
     * Supplies an original canonical source without importing content.
     */
    public function getTemplate(string $template_id): ?array {
      if (in_array($template_id, $this->missing, TRUE)) {
        return NULL;
      }
      $metadata = ['authorship' => ['author_uid' => 1]];
      if (array_key_exists($template_id, $this->redirects)) {
        $metadata['storyline_consolidation']['merged_into_template_id'] = $this->redirects[$template_id];
      }
      return [
        'template_id' => $template_id,
        'name' => 'Original Shared Source',
        'template_data' => [
          'template_id' => $template_id,
          'name' => 'Original Shared Source',
          'synopsis' => 'Original fixture; no third-party content.',
          'metadata' => $metadata,
        ],
      ];
    }

  };
  $isolated = new StorylineAuthoringDraftService($database, $manager, \Drupal::currentUser(), \Drupal::service('uuid'), \Drupal::time());
  $from_template = $isolated->createDraft($prefix . '-new-source');
  $check($from_template['payload']['storyline_id'] === $prefix . '-new-source', 'Editing a published source must keep its canonical identity, not fork it per user.');
  $check($from_template['payload']['title'] === 'Original Shared Source', 'Working drafts must not gain Copy of titles.');
  $manager->redirects[$prefix . '-retired-source'] = $prefix . '-new-source';
  $redirected = $isolated->createDraft($prefix . '-retired-source');
  $check($redirected['draft_id'] === $from_template['draft_id'], 'Retired canonical links must reuse the retained shared draft.');
  $check($redirected['payload']['storyline_id'] === $prefix . '-new-source', 'Retired canonical links must never recreate a copy identity.');
  $manager->redirects[$prefix . '-older-source'] = $prefix . '-retired-source';
  $check($isolated->createDraft($prefix . '-older-source')['draft_id'] === $from_template['draft_id'], 'Multiple consolidations must resolve to the final shared draft.');
  $manager->redirects[$prefix . '-cycle-a'] = $prefix . '-cycle-b';
  $manager->redirects[$prefix . '-cycle-b'] = $prefix . '-cycle-a';
  $reject(static fn() => $isolated->createDraft($prefix . '-cycle-a'), 'storyline_template_consolidation_redirect_invalid');
  $manager->redirects[$prefix . '-missing-target'] = $prefix . '-missing';
  $manager->missing[] = $prefix . '-missing';
  $reject(static fn() => $isolated->createDraft($prefix . '-missing-target'), 'storyline_template_consolidation_target_not_found');
  foreach (['', NULL, ' spaced-target '] as $invalid) {
    $manager->redirects[$prefix . '-invalid-target'] = $invalid;
    $reject(static fn() => $isolated->createDraft($prefix . '-invalid-target'), 'storyline_template_consolidation_redirect_invalid');
  }
  $check((int) $database->select('dungeoncrawler_content_storyline_authoring_drafts', 'd')->countQuery()->execute()->fetchField() === $count_before + 1, 'Alias lookup and failures must never insert redundant drafts.');
  $identity_operations = [
    [
      'type' => 'append',
      'path' => '/objects/actors/generation_specs',
      'key' => 'guide',
      'value' => ['role' => 'guide', 'display_name' => 'Original Watch Guide'],
    ],
    [
      'type' => 'append',
      'path' => '/objects/items/generation_specs',
      'key' => 'journal',
      'value' => [
        'purpose' => 'Original watch journal',
        'actor_id' => ['$draft_ref' => 'guide.actor_id'],
      ],
    ],
  ];
  $identity_draft = $drafts->createDraft();
  $check($identity_draft['payload']['storyline_id'] === 'storyline-' . $identity_draft['draft_id'], 'A new storyline must receive its ID without requesting a title or identifier.');
  $identity_plan = $drafts->planDraftOperations($identity_draft['draft_id'], 1, $identity_operations);
  $check(count($identity_plan['allocated_ids']) === 3, 'Actor, contact, and item IDs must be allocated by the authority.');
  $identity_saved = $drafts->applyDraftOperations($identity_draft['draft_id'], 1, $prefix . '-identities', $identity_operations);
  $identity_replay = $drafts->applyDraftOperations($identity_draft['draft_id'], 1, $prefix . '-identities', $identity_operations);
  $check($identity_saved['allocated_ids'] === $identity_plan['allocated_ids'], 'Planning and saving must use exactly the same allocated IDs.');
  $check($identity_replay['idempotent_replay'] && $identity_replay['allocated_ids'] === $identity_saved['allocated_ids'], 'Retrying the operation must replay the original identity receipt.');
  $identity_payload = $identity_saved['saved']['payload'];
  $actor_id = $identity_payload['objects']['actors']['generation_specs'][0]['actor_id'];
  $check($identity_payload['objects']['items']['generation_specs'][0]['actor_id'] === $actor_id, 'Persisted item links must resolve the newly allocated actor identity.');
  $check(!str_contains(json_encode($identity_payload), '$draft_ref'), 'Internal batch references must never be persisted.');
  $renamed = $drafts->applyDraftOperations($identity_draft['draft_id'], 2, $prefix . '-rename', [[
    'type' => 'patch',
    'path' => '/objects/actors/generation_specs',
    'match' => ['field' => 'actor_id', 'value' => $actor_id],
    'value' => ['display_name' => 'Original Renamed Guide'],
  ],
  ]);
  $check($renamed['saved']['payload']['objects']['actors']['generation_specs'][0]['actor_id'] === $actor_id && $renamed['allocated_ids'] === [], 'Renaming must keep the original actor ID without allocating another.');
  $check($drafts->getDraftRevisionForViewing($identity_draft['draft_id'], 2)['payload'] === $identity_payload, 'Later edits must preserve the identity allocation snapshot.');
  echo "PASS: $checks focused shared-authoring checks; all fixtures rolled back.\n";
}
finally {
  $transaction->rollBack();
  \Drupal::currentUser()->setAccount($original_account);
}
