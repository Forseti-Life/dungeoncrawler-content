<?php

/**
 * @file
 * Original transactional fixtures for linked quest reference repair.
 */

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService;
use Drupal\user\Entity\User;

$db = \Drupal::database();
$transaction = $db->startTransaction();
$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
$checks = 0;
$check = static function (bool $valid, string $message) use (&$checks): void {
  if (!$valid) {
    throw new RuntimeException($message);
  }
  $checks++;
};
try {
  $drafts = \Drupal::service('dungeoncrawler_content.storyline_authoring_drafts');
  $quests = \Drupal::service('dungeoncrawler_content.canonical_quest_template');
  $harness = \Drupal::service('dungeoncrawler_content.editor_gm_harness');
  $draft = $drafts->createDraft();
  $prefix = 'original-quest-repair-' . substr($draft['draft_id'], 0, 8);
  $quest_id = $prefix . '-watch-task';
  $payload = $draft['payload'];
  $payload['title'] = 'Original Village Watch';
  $payload['description'] = 'Help the village watch recover a lost lantern.';
  $payload['premise'] = 'A guide requests help finding the watch lantern.';
  $payload['process_flow']['journal_entries'] = [
    [
      'chapter_id' => 'village',
      'scene_id' => 'watch-house',
      'title' => 'The Watch House',
      'summary' => 'The watch asks for help.',
    ],
  ];
  $payload['process_flow']['tasks'] = [
    [
      'task_id' => $quest_id,
      'chapter_id' => 'village',
      'scene_id' => 'watch-house',
    ],
  ];
  $draft = $drafts->saveDraft($draft['draft_id'], 1, $payload);
  $db->insert('dc_canonical_quests')->fields([
    'template_id' => $quest_id,
    'version' => '1.0.0',
    'name' => 'Original Recover The Watch Lantern',
    'description' => 'Keep this authored description unchanged.',
    'quest_type' => 'module_story',
    'storyline_template_id' => $prefix . '-old-copy',
    'entry_point_actor_id' => 'original-watch-guide',
    'entry_point_location_id' => 'original-watch-house',
    'contract_hash' => hash('sha256', $prefix),
    'objectives_schema' => json_encode([
      ['phase' => 1, 'objectives' => []],
    ], JSON_THROW_ON_ERROR),
    'rewards_schema' => '{"xp":40,"gold":3,"items":["original-watch-badge"]}',
    'story_impact' => json_encode([
      'storyline_id' => $prefix . '-old-copy',
      'chapter_id' => 'village',
      'scene_id' => 'watch-house',
    ], JSON_THROW_ON_ERROR),
    'tags' => '["original","keep-this-tag"]',
    'prerequisites' => '[]',
    'level_min' => 1,
    'level_max' => 2,
    'estimated_duration_minutes' => 20,
    'created_at' => time(),
    'updated_at' => time(),
  ])->execute();
  $load = static fn(): array => $db->select('dc_canonical_quests', 'q')->fields('q')->condition('template_id', $quest_id)->execute()->fetchAssoc();
  $before = $load();
  $snapshot = $harness->describe('storyline_builder', $draft['draft_id'])['context_snapshot'];
  $repairs = $snapshot['authoring_workflow']['linked_quest_reference_repairs'];
  $check(count($repairs) === 1 && $repairs[0]['template_id'] === $quest_id, 'The GM must see the exact current reference correction.');
  $context = $harness->surface('storyline_builder')->createContext($draft['draft_id'], 'editing');
  $plan = $harness->surface('storyline_builder')->registry()->get('plan_storyline_remediation')->execute([
    'expected_revision' => 2,
    'scope' => 'quest_storyline_references',
  ], $context);
  $arguments = $plan['proposed_execution']['arguments'];
  $check($arguments['definitions'] === [] && $arguments['rooms'] === [] && $arguments['dungeons'] === [] && $arguments['operations'] === [], 'Reference-only repair must never generate assets or edit the draft.');
  $check($arguments['quests'] === $repairs, 'The proposed execution must carry the exact grounded CAS-protected repair.');
  $response = $harness->handle('storyline_builder', $draft['draft_id'], [
    'schema_version' => EditorGmHarnessService::REQUEST_CONTRACT_VERSION,
    'tool_context' => [
      'tool_id' => 'storyline_builder',
      'draft_id' => $draft['draft_id'],
      'validation_profile' => 'editing',
    ],
    'intent' => ['type' => 'natural_language', 'utterance' => 'Apply it'],
  ], [
    'conversation_context' => "NEXT STEPS:\n1. Align the linked quest's storyline references with the current storyline.",
  ]);
  $check(($response['tool_result']['tool'] ?? '') === 'apply_storyline_remediation', 'Apply it must execute the grounded correction, not return another review.');
  $after = $load();
  $impact = json_decode($before['story_impact'], TRUE, 512, JSON_THROW_ON_ERROR);
  $impact['storyline_id'] = $payload['storyline_id'];
  $check($after['storyline_template_id'] === $payload['storyline_id'] && json_decode($after['story_impact'], TRUE) === $impact, 'Both storyline references must be repaired without losing other impact data.');
  foreach (array_diff(array_keys($before), ['storyline_template_id', 'story_impact', 'contract_hash', 'updated_at']) as $field) {
    $check($after[$field] === $before[$field], 'Quest content must be preserved: ' . $field);
  }
  $check($after['contract_hash'] !== $before['contract_hash'], 'Changed quest references must update the contract fingerprint.');
  $check($drafts->getDraft($draft['draft_id'])['payload_hash'] === $draft['payload_hash'], 'The draft must not be rewritten for a quest-reference correction.');
  $check($quests->planStorylineReferenceRepairs([$quest_id], $payload['storyline_id']) === [], 'Aligned references must not be planned repeatedly.');
  $check($quests->applyStorylineReferenceRepair($repairs[0])['references_already_aligned'], 'Replaying a completed repair must be a no-op.');
  $stale = $quests->planStorylineReferenceRepairs([$quest_id], $prefix . '-other-story')[0];
  $db->update('dc_canonical_quests')->fields(['description' => 'A concurrent author changed the quest.'])->condition('template_id', $quest_id)->execute();
  try {
    $quests->applyStorylineReferenceRepair($stale);
    throw new RuntimeException('A stale repair must not overwrite concurrent quest edits.');
  }
  catch (DomainException $exception) {
    $check($exception->getMessage() === 'quest_storyline_reference_revision_conflict:' . $quest_id, 'Concurrent quest changes must fail explicitly.');
  }
  echo "PASS: $checks focused quest-reference repair checks; all original fixtures rolled back.\n";
}
finally {
  $transaction->rollBack();
  $switcher->switchBack();
}
