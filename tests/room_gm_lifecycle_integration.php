<?php

/**
 * @file
 * Room GM lifecycle checks using original fixtures and rollback-only writes.
 *
 * Run with the owning site's Drush php:script command.
 */

declare(strict_types=1);

use Drupal\Core\Session\UserSession;

$database = \Drupal::database();
$rooms = \Drupal::service('dungeoncrawler_content.room_editor');
$definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
$harness = \Drupal::service('dungeoncrawler_content.editor_gm_harness');
$switcher = \Drupal::service('account_switcher');
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new \RuntimeException('FAIL: ' . $message);
  }
  $checks++;
};
$expect = static function (callable $operation, string $error) use ($check): void {
  try {
    $operation();
  }
  catch (\Throwable $exception) {
    $check(str_contains($exception->getMessage(), $error), 'Expected ' . $error . ', got ' . $exception->getMessage());
    return;
  }
  throw new \RuntimeException('FAIL: expected ' . $error);
};
$account = static fn(int $uid, bool $admin = FALSE): UserSession => new class(['uid' => $uid], $admin) extends UserSession {

  public function __construct(array $values, private readonly bool $admin) {
    parent::__construct($values);
  }

  public function hasPermission(string $permission): bool {
    return $this->admin && $permission === 'administer dungeoncrawler content';
  }

};
$owner = $account(987651001);
$stranger = $account(987651002);
$admin = $account(987651003, TRUE);
$run = static function (?string $draft_id, string $tool, array $args = [], bool $dry_run = FALSE) use ($harness): array {
  $context = ['tool_id' => 'room_editor'];
  if ($draft_id !== NULL) {
    $context['draft_id'] = $draft_id;
  }
  return $harness->handle('room_editor', $draft_id, [
    'schema_version' => 'editor-gm-request-v1',
    'tool_context' => $context,
    'intent' => ['type' => 'tool_call', 'tool_name' => $tool, 'arguments' => $args],
    'options' => ['dry_run' => $dry_run],
  ]);
};
$transaction = $database->startTransaction();
$switcher->switchTo($owner);
try {
  $bootstrap = $harness->describe('room_editor', NULL);
  $check($bootstrap['context_snapshot']['draft'] === NULL, 'Bootstrap requires no draft.');
  $check($bootstrap['context_snapshot']['tools']['tool_count'] === 32, 'Live Room GM has all nine new tools.');
  $check(isset($bootstrap['context_snapshot']['drafts'], $bootstrap['context_snapshot']['rooms']), 'Bootstrap grounds accessible identities.');
  $before = count($rooms->listAccessibleDrafts());
  $preview = $run(NULL, 'create_room_draft', ['name' => 'Lantern Ledger'], TRUE);
  $check($preview['tool_result']['would_mutate'] === TRUE && count($rooms->listAccessibleDrafts()) === $before, 'Create dry run writes nothing.');
  $created = $run(NULL, 'create_room_draft', ['name' => 'Lantern Ledger']);
  $draft = $created['tool_result']['draft'];
  $id = $draft['draft_id'];
  $check($draft['room']['name'] === 'Lantern Ledger' && $draft['room']['room_id'] !== '', 'New named room generates its own identity.');
  $check($created['context_snapshot']['draft']['draft_id'] === $id && $created['tool_result']['active_draft_id'] === $id, 'Create re-grounds the handoff.');
  $edited = $run($id, 'apply_room_command', ['expected_revision' => 0, 'type' => 'set_room_metadata', 'payload' => ['changes' => ['name' => 'Lantern Archive']]]);
  $check($edited['tool_result']['final_revision'] === 1 && $rooms->getDraft($id)['room']['name'] === 'Lantern Archive', 'Single command uses the native revision executor.');
  $expect(static fn() => $run($id, 'apply_room_command', ['expected_revision' => 0, 'type' => 'set_room_metadata', 'payload' => ['changes' => ['name' => 'Stale']]]), 'revision_conflict');
  $history = $run($id, 'list_room_command_history', ['limit' => 1])['tool_result'];
  $check(count($history['commands']) === 1 && $history['commands'][0]['result_revision'] === 1, 'History exposes genuine command IDs.');
  $check(!isset($history['commands'][0]['inverse_payload']), 'History does not leak inverse snapshots.');
  $run($id, 'apply_room_command', ['expected_revision' => 1, 'type' => 'undo', 'payload' => ['target_command_id' => $history['commands'][0]['command_id']]]);
  $check($rooms->getDraft($id)['room']['name'] === 'Lantern Ledger', 'Undo uses the inspected command ID.');
  $older = $rooms->commandHistory($id, 1, 2);
  $check($older['commands'][0]['result_revision'] === 1, 'History cursor is exclusive.');
  $expect(static fn() => $rooms->commandHistory($id, 101), 'history_bounds_invalid');
  $expect(static fn() => $run(NULL, 'apply_room_command', ['expected_revision' => 0, 'type' => 'undo', 'payload' => []]), 'editor_gm_draft_required');

  $second = $rooms->createDraft(NULL, 'Cobalt Workshop');
  $loaded = $run($id, 'load_room_draft', ['draft_id' => $second['draft_id']]);
  $check($loaded['context_snapshot']['draft']['draft_id'] === $second['draft_id'], 'Load switches grounding without revision changes.');
  $queue = new \ReflectionMethod($harness, 'executeTaskQueue');
  $proposal = $queue->invoke($harness, $harness->surface('room_editor'), $id, 'editing', [
    ['tool_name' => 'load_room_draft', 'arguments' => ['draft_id' => $second['draft_id']]],
    ['tool_name' => 'apply_room_command', 'arguments' => ['expected_revision' => 0, 'type' => 'set_room_metadata', 'payload' => ['changes' => ['name' => 'Not Approved']]]],
    ['tool_name' => 'abandon_room_draft', 'arguments' => ['expected_revision' => 1]],
  ], '', [], [], '');
  $check($proposal['tool_result']['requires_approval'] === TRUE && count($proposal['tool_result']['remaining_tasks']) === 1, 'Queue stops at mutation approval.');
  $check($rooms->getDraft($second['draft_id'])['revision'] === 0, 'Queue cannot bypass mutation approval.');
  $check($proposal['tool_result']['active_draft_id'] === $second['draft_id'], 'Queue retains a preceding draft handoff.');
  $queue_preview = $queue->invoke($harness, $harness->surface('room_editor'), NULL, 'editing', [
    ['tool_name' => 'create_room_draft', 'arguments' => ['name' => 'Preview Only']],
    ['tool_name' => 'apply_room_command', 'arguments' => ['expected_revision' => 0, 'type' => 'undo', 'payload' => []]],
  ], '', [], [], '', TRUE);
  $check($queue_preview['tool_result']['would_mutate'] === TRUE && count($queue_preview['tool_result']['remaining_tasks']) === 1, 'Dry-run queue does not simulate an unavailable new draft.');

  $controller = \Drupal\dungeoncrawler_content\Controller\EditorGmController::create(\Drupal::getContainer());
  $chat = \Drupal::service('dungeoncrawler_content.chat_session_manager');
  $token = \Drupal::service('csrf_token')->get(\Drupal\Core\Access\CsrfRequestHeaderAccessCheck::TOKEN_KEY);
  $http = static function (?string $draft_id, string $tool, array $arguments, string $message) use ($controller, $token): \Symfony\Component\HttpFoundation\JsonResponse {
    $context = ['tool_id' => 'room_editor'];
    if ($draft_id !== NULL) {
      $context['draft_id'] = $draft_id;
    }
    $request = \Symfony\Component\HttpFoundation\Request::create('/api/room-editor/gm', 'POST', [], [], [], [
      'CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $token,
    ], json_encode([
      'schema_version' => 'editor-gm-request-v1', 'tool_context' => $context,
      'intent' => ['type' => 'tool_call', 'tool_name' => $tool, 'arguments' => $arguments, 'author_message' => $message],
    ], JSON_THROW_ON_ERROR));
    return $controller->execute('room_editor', $request, $draft_id);
  };
  $http_created = $http(NULL, 'create_room_draft', ['name' => 'Slate Gallery'], 'Start Slate Gallery.');
  $check($http_created->getStatusCode() === 200, 'Bootstrap controller accepts a CSRF-protected create.');
  $http_id = json_decode($http_created->getContent(), TRUE, 512, JSON_THROW_ON_ERROR)['data']['tool_result']['active_draft_id'];
  $target_session = $chat->loadSession($chat->editorGmSessionKey('room_editor', $http_id));
  $turns = $chat->getRecentMessagesChronological((int) $target_session['id'], 10);
  $check(count($turns) === 2 && $turns[0]['message'] === 'Start Slate Gallery.', 'Bootstrap create retains one author turn and one response in the new draft.');
  $http_loaded = $http($http_id, 'load_room_draft', ['draft_id' => $second['draft_id']], 'Switch to Cobalt Workshop.');
  $check($http_loaded->getStatusCode() === 200, 'Controller loads another accessible draft.');
  $loaded_session = $chat->loadSession($chat->editorGmSessionKey('room_editor', $second['draft_id']));
  $check(count($chat->getRecentMessagesChronological((int) $loaded_session['id'], 10)) === 2, 'Draft switch carries one handoff pair into the destination conversation.');
  $no_csrf = $controller->execute('room_editor', \Symfony\Component\HttpFoundation\Request::create('/api/room-editor/gm', 'POST'));
  $check($no_csrf->getStatusCode() === 403, 'Bootstrap mutation still requires CSRF.');

  $switcher->switchBack();
  $switcher->switchTo($stranger);
  $expect(static fn() => $rooms->getDraft($id), 'draft_access_denied');
  $check(!in_array($id, array_column($rooms->listAccessibleDrafts(), 'draft_id'), TRUE), 'Other authors cannot list the fixture draft.');
  $check($controller->describe('room_editor', \Symfony\Component\HttpFoundation\Request::create('/'), $id)->getStatusCode() === 403, 'Controller refuses inaccessible conversations.');
  $switcher->switchBack();
  $switcher->switchTo($owner);

  $fixture_id = 'gm-lifecycle-' . substr(str_replace('-', '', \Drupal::service('uuid')->generate()), 0, 16);
  $payload = [
    'object_id' => $fixture_id, 'label' => 'Copper Ledger Stand', 'category' => 'custom',
    'movable' => TRUE, 'stackable' => FALSE,
    'movement' => ['passable' => FALSE, 'blocks_movement' => TRUE, 'cost_multiplier' => 1],
  ];
  $receipt = $definitions->saveDefinition('obstacle', NULL, $payload);
  $version = $receipt['version'];
  $reference = ['family' => 'obstacle', 'definition_id' => $fixture_id, 'version' => $version];
  $placement = $run($second['draft_id'], 'apply_room_command', ['expected_revision' => 0, 'type' => 'place_object', 'payload' => ['definition_ref' => $reference, 'anchor_hex' => ['q' => 0, 'r' => 0]]]);
  $instance = $placement['tool_result']['draft']['room']['placements'][0]['instance_id'];
  $stamp = $definitions->catalogVersion();
  $expect(static fn() => $definitions->setDefinitionLifecycle('obstacle', $fixture_id, 'retired', $version, 0, 'Replace stand design', $owner), 'definition_lifecycle_access_denied');
  $expect(static fn() => $definitions->setDefinitionLifecycle('obstacle', $fixture_id, 'retired', '999.0.0', 0, 'Replace stand design', $admin), 'definition_lifecycle_conflict');
  $retired = $definitions->setDefinitionLifecycle('obstacle', $fixture_id, 'retired', $version, 0, 'Replace stand design', $admin);
  $check($retired['status'] === 'retired' && $retired['revision'] === 1 && $definitions->catalogVersion() !== $stamp, 'Retirement increments lifecycle revision and invalidates catalog identity.');
  $check($definitions->definitionPayload('obstacle', $fixture_id) === $payload && $definitions->definitionExists('obstacle', $fixture_id, $version), 'Retirement preserves stored and pinned references.');
  $check($definitions->catalog('obstacle', $fixture_id, 10, 0)['definitions'] === [], 'Retired definition is excluded from new selection.');
  $check($definitions->requireCanonicalDefinitionRef('obstacle', $fixture_id)['definition_id'] === $fixture_id, 'Existing runtime references still resolve.');
  $expect(static fn() => $definitions->assertDefinitionSelectable('obstacle', $fixture_id), 'definition_retired');
  $expect(static fn() => $run($second['draft_id'], 'apply_room_command', ['expected_revision' => 1, 'type' => 'place_object', 'payload' => ['definition_ref' => $reference, 'anchor_hex' => ['q' => 1, 'r' => 0]]]), 'definition_retired');
  $expect(static fn() => $run($second['draft_id'], 'apply_room_command', ['expected_revision' => 1, 'type' => 'duplicate_object', 'payload' => ['instance_id' => $instance, 'anchor_hex' => ['q' => 1, 'r' => 0]]]), 'definition_retired');
  $moved = $run($second['draft_id'], 'apply_room_command', ['expected_revision' => 1, 'type' => 'move_object', 'payload' => ['instance_id' => $instance, 'anchor_hex' => ['q' => 1, 'r' => 0]]]);
  $check($moved['tool_result']['final_revision'] === 2, 'An existing retired reference can still be edited.');
  $expect(static fn() => $definitions->setDefinitionLifecycle('obstacle', $fixture_id, 'active', $version, 0, 'Restore design', $admin), 'definition_lifecycle_conflict');
  $restored = $definitions->setDefinitionLifecycle('obstacle', $fixture_id, 'active', $version, 1, 'Restore design', $admin);
  $check($restored['status'] === 'active' && $restored['revision'] === 2, 'Restore is revision-checked and reversible.');
  $check(count($definitions->catalog('obstacle', $fixture_id, 10, 0)['definitions']) === 1, 'Restored definition is selectable again.');

  $item_payload = [
    'schema_version' => '1.0.0', 'item_id' => $fixture_id . '-item', 'name' => 'Ledger Sun Lens',
    'item_type' => 'adventuring_gear', 'level' => 25, 'rarity' => 'epic',
  ];
  $item_receipt = $definitions->saveDefinition('item', NULL, $item_payload);
  $criteria = ['level_min' => 25, 'level_max' => 25, 'rarity' => 'epic', 'limit' => 250];
  $check(in_array($item_payload['item_id'], array_column($definitions->publishedDefinitions('item', $criteria), 'definition_id'), TRUE), 'Original item is eligible for generation selection.');
  $switcher->switchBack();
  $switcher->switchTo($admin);
  $inspected = $run(NULL, 'inspect_definition_lifecycle', ['family' => 'item', 'definition_id' => $item_payload['item_id']])['tool_result'];
  $item_retired = $run(NULL, 'retire_canonical_definition', [
    'family' => 'item', 'definition_id' => $item_payload['item_id'], 'expected_version' => $inspected['definition_version'],
    'expected_revision' => $inspected['revision'], 'reason' => 'Replace original fixture lens.',
  ])['tool_result'];
  $check($item_retired['status'] === 'retired', 'Registered retirement tool works without a room draft.');
  $check(!in_array($item_payload['item_id'], array_column($definitions->publishedDefinitions('item', $criteria), 'definition_id'), TRUE), 'Retired item is excluded from generation selection.');
  $check($definitions->requireCanonicalDefinitionRef('item', $item_payload['item_id'])['definition_id'] === $item_payload['item_id'], 'Retirement does not break existing item-state reads.');
  $conflict_response = $http(NULL, 'restore_canonical_definition', [
    'family' => 'item', 'definition_id' => $item_payload['item_id'], 'expected_version' => $item_receipt['version'],
    'expected_revision' => 0, 'reason' => 'Stale restore.',
  ], 'Restore the lens.');
  $check($conflict_response->getStatusCode() === 409, 'Lifecycle conflicts are explicit API conflicts, not internal errors.');
  $item_restored = $run(NULL, 'restore_canonical_definition', [
    'family' => 'item', 'definition_id' => $item_payload['item_id'], 'expected_version' => $item_receipt['version'],
    'expected_revision' => 1, 'reason' => 'Restore original fixture lens.',
  ])['tool_result'];
  $check($item_restored['status'] === 'active' && $item_restored['revision'] === 2, 'Registered restore tool preserves lifecycle checks.');
  $check(in_array($id, array_column($rooms->listAccessibleDrafts(), 'draft_id'), TRUE), 'Administrator can discover another author draft.');
  $switcher->switchBack();
  $switcher->switchTo($owner);

  $expect(static fn() => $rooms->abandonDraft($id, 0), 'revision_conflict');
  $abandoned = $run($id, 'abandon_room_draft', ['expected_revision' => 2]);
  $check($abandoned['tool_result']['active_draft_id'] === NULL && $abandoned['context_snapshot']['draft'] === NULL, 'Abandon clears active grounding.');
  $check($rooms->getDraft($id)['room']['name'] === 'Lantern Ledger' && count($rooms->commandHistory($id)['commands']) === 2, 'Abandon preserves payload and audit history.');
  $expect(static fn() => $run(NULL, 'load_room_draft', ['draft_id' => $id]), 'draft_not_active');
  $expect(static fn() => $rooms->abandonDraft($id, 3), 'draft_not_active');
  $database->update('dungeoncrawler_content_room_editor_drafts')->fields(['status' => 'published'])->condition('draft_id', $second['draft_id'])->execute();
  $expect(static fn() => $rooms->abandonDraft($second['draft_id'], 2), 'draft_not_active');
  echo 'PASS: ', $checks, " isolated Room GM lifecycle checks.\n";
}
finally {
  $switcher->switchBack();
  $transaction->rollBack();
}
