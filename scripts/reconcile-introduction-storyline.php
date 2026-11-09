<?php

/**
 * Restore the introduction draft and attach unassigned authored quests.
 *
 * Run through site-local Drush php:script. Read-only unless
 * INTRO_STORYLINE_APPLY=1; INTRO_STORYLINE_ROLLBACK=1 verifies then rolls back.
 * INTRO_STORYLINE_UID must identify the authorized editor performing the repair.
 */

$db = \Drupal::database();
$account = \Drupal::currentUser()->getAccount();
$transaction = NULL;
$apply = getenv('INTRO_STORYLINE_APPLY') === '1';
$storyline_id = 'introduction-to-game-mechanics';
$chapter_id = 'intro-mechanics';
$scene_id = 'tavern-foundations';

try {
  $uid = (int) getenv('INTRO_STORYLINE_UID');
  $editor = \Drupal\user\Entity\User::load($uid);
  if (!$editor || !$editor->hasPermission('edit canonical dungeoncrawler storylines')) {
    throw new \RuntimeException('introduction_reconciliation_authorized_editor_required');
  }
  \Drupal::currentUser()->setAccount($editor);
  $manager = \Drupal::service('dungeoncrawler_content.storyline_manager');
  $drafts = \Drupal::service('dungeoncrawler_content.storyline_authoring_drafts');
  $memberships = [];
  foreach ($manager->listTemplates(TRUE) as $template) {
    foreach (array_keys($template['template_data']['linked_quests'] ?? []) as $quest_id) {
      $memberships[$quest_id][] = $template['template_id'];
    }
  }
  $target = NULL;
  foreach ($drafts->listDrafts() as $summary) {
    $draft = $drafts->getDraftForViewing($summary['draft_id']);
    $payload = $draft['payload'];
    foreach ($payload['process_flow']['tasks'] as $task) {
      $memberships[$task['task_id']][] = $payload['storyline_id'];
    }
    if ($payload['storyline_id'] === $storyline_id) {
      if ($target !== NULL) {
        throw new \RuntimeException('introduction_reconciliation_multiple_workspaces');
      }
      if (!$summary['can_edit']) {
        throw new \RuntimeException('introduction_reconciliation_workspace_not_editable');
      }
      $target = $draft;
    }
  }
  $orphans = [];
  foreach ($db->select('dc_canonical_quests', 'q')->fields('q')->execute()->fetchAll(\PDO::FETCH_ASSOC) as $quest) {
    if (!isset($memberships[$quest['template_id']])) {
      $orphans[$quest['template_id']] = $quest;
    }
  }
  $report = [
    'storyline_id' => $storyline_id,
    'existing_draft_id' => $target['draft_id'] ?? NULL,
    'orphan_quest_ids' => array_keys($orphans),
    'orphan_count' => count($orphans),
    'apply' => $apply,
    'campaign_progress_modified' => FALSE,
    'canonical_publication_modified' => FALSE,
  ];
  if (!$apply) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    return;
  }

  $transaction = $db->startTransaction();
  if ($target === NULL) {
    $target = $drafts->createDraft();
    $target['payload']['storyline_id'] = $storyline_id;
    $target['payload']['title'] = 'Introduction to Game Mechanics';
    $target['payload']['description'] = 'Learn dialogue, exploration, collection, delivery, investigation, and combat through introductory assignments.';
    $target['payload']['premise'] = 'Practice core game mechanics through self-contained starter quests.';
    $target['payload']['player_hook'] = 'Choose a starter assignment and learn its mechanics by completing it.';
    $target['payload']['ending'] = 'The player can confidently navigate the core game loop and choose a new adventure.';
    $target['payload']['level_range'] = '1';
    $target['payload']['tags'] = ['tutorial', 'starter', 'mechanics'];
    $target['payload']['process_flow']['journal_entries'][] = [
      'chapter_id' => $chapter_id,
      'scene_id' => $scene_id,
      'title' => 'Core Mechanics Assignments',
      'summary' => 'Independent practice assignments. Existing quest objectives and rewards remain unchanged.',
    ];
  }
  $payload = $target['payload'];
  foreach ($orphans as $quest_id => $quest) {
    $payload['process_flow']['tasks'][] = [
      'task_id' => $quest_id,
      'chapter_id' => $chapter_id,
      'scene_id' => $scene_id,
      'status' => 'available',
      'unlocks_after' => [],
      'unlocks_to' => [],
      'unlock_condition' => '',
    ];
  }
  if ($payload !== $target['payload'] || $report['existing_draft_id'] === NULL) {
    $target = $drafts->saveDraft($target['draft_id'], $target['revision'], $payload);
  }

  $saved = $drafts->getDraft($target['draft_id']);
  $task_ids = array_column($saved['payload']['process_flow']['tasks'], 'task_id');
  if (array_diff(array_keys($orphans), $task_ids) !== []) {
    throw new \RuntimeException('introduction_reconciliation_task_membership_incomplete');
  }
  $visible = array_filter($drafts->listStorylineOptions(), static fn(array $option): bool => $option['draft_id'] === $saved['draft_id']);
  if (count($visible) !== 1) {
    throw new \RuntimeException('introduction_reconciliation_workspace_not_visible');
  }
  $validation = $drafts->validateDraft($saved['draft_id'], 'editing');
  $report += [
    'draft_id' => $saved['draft_id'],
    'revision' => $saved['revision'],
    'task_count' => count($task_ids),
    'visible_in_editor' => TRUE,
    'publication_attempted' => FALSE,
    'validation_error_count' => $validation['error_count'],
    'validation_warning_count' => $validation['warning_count'],
    'validation_errors' => $validation['errors'],
    'url' => '/game-master/storyline-builder?draft_id=' . $saved['draft_id'],
  ];
  if (getenv('INTRO_STORYLINE_ROLLBACK') === '1') {
    $transaction->rollBack();
    $report['rolled_back'] = TRUE;
  }
  echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
catch (\Throwable $exception) {
  if ($transaction !== NULL) {
    $transaction->rollBack();
  }
  throw $exception;
}
finally {
  \Drupal::currentUser()->setAccount($account);
}
