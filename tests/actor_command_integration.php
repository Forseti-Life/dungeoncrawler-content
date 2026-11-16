<?php

/**
 * @file
 * Actor command integration checks.
 *
 * Run with the site's Drush php:script command. Uses one original fixture
 * actor inside a transaction that is always rolled back; no player rows,
 * model calls or lasting writes.
 */

declare(strict_types=1);

use Drupal\Core\Session\UserSession;

$database = \Drupal::database();
$manager = \Drupal::service('dungeoncrawler_content.character_manager');
$uuid = \Drupal::service('uuid');
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new \RuntimeException('FAIL: ' . $message);
  }
  $checks++;
};

$transaction = $database->startTransaction();
try {
  $actor_uuid = $uuid->generate();
  $owner_uid = 987650001;
  $id = (int) $database->insert('dc_campaign_characters')->fields([
    'uuid' => $actor_uuid, 'campaign_id' => 0, 'character_id' => 0, 'uid' => $owner_uid, 'type' => 'npc', 'role' => 'contact',
    'name' => 'Odalys Fenwick', 'level' => 2, 'lifecycle_state' => 'ready_library', 'status' => 0, 'version' => 4,
    'character_data' => json_encode([
      'name' => 'Odalys Fenwick', 'display_name' => 'Odalys Fenwick', 'level' => 2, 'backstory' => 'Keeps the toll ledger.',
      'basicInfo' => ['name' => 'Odalys Fenwick', 'backstory' => 'Keeps the toll ledger.'],
      'stats' => ['ac' => 16, 'maxHp' => 22],
    ]),
    'state_data' => json_encode(['description' => 'A ferry warden in a waxed coat.']),
    'created' => 1, 'changed' => 1,
  ])->execute();
  $account = static fn(int $uid): UserSession => new class(['uid' => $uid]) extends UserSession {

    public function hasPermission(string $permission): bool {
      return $permission === 'edit own dungeoncrawler characters';
    }

  };
  $owner = $account($owner_uid);
  $stranger = $account(987650002);
  $command = static fn(array $operations, string $type = 'actor.narrative.patch', array $target = []): array => [
    'contract_version' => 'actor-command-v1',
    'command_id' => \Drupal::service('uuid')->generate(),
    'target' => $target + ['scope' => 'template', 'actor_uuid' => $actor_uuid],
    'type' => $type,
    'base_revision' => 2,
    'payload' => ['operations' => $operations],
  ];
  $load = static fn(): array|false => $database->select('dc_campaign_characters', 'c')->fields('c')->condition('id', $id)->execute()->fetchAssoc();
  $unchanged = static function () use ($load): bool {
    $row = $load();
    return (int) $row['version'] === 4 && str_contains($row['character_data'], 'Keeps the toll ledger.');
  };

  // Preview is read-only.
  $edit = $command([
    ['op' => 'set', 'path' => '/narrative/biography', 'value' => 'Keeps the toll ledger and a second, private one.'],
    ['op' => 'set', 'path' => '/narrative/description', 'value' => 'A ferry warden with ink-stained cuffs.'],
  ]);
  $preview = $manager->previewActorCommand($edit, $owner);
  $check(($preview['intent'] ?? NULL) === 'preview', 'Preview must plan: ' . json_encode($preview));
  $check(array_column($preview['changed'], 'path') === ['/narrative/biography', '/narrative/description'], 'Preview lists exact changed paths.');
  $check($unchanged(), 'Preview must not write.');
  $check((int) $database->select('dc_actor_command_receipts', 'r')->condition('actor_uuid', $actor_uuid)->countQuery()->execute()->fetchField() === 0, 'Preview stores no receipt.');

  // Execute: an older inspected revision still applies to the latest row.
  $receipt = $manager->executeActorCommand($edit, $owner);
  $check($receipt['committed'] === TRUE, 'Valid edit commits: ' . json_encode($receipt));
  $check($receipt['inspected_revision'] === 2 && $receipt['applied_from_revision'] === 4 && $receipt['new_revision'] === 5, 'Receipt reports inspected, applied-from and new revisions.');
  $check($receipt['next_steps'] !== [], 'Receipt always carries next steps.');
  $row = $load();
  $data = json_decode($row['character_data'], TRUE);
  $state = json_decode($row['state_data'], TRUE);
  $check((int) $row['version'] === 5, 'Aggregate revision advances once.');
  $check($data['backstory'] === 'Keeps the toll ledger and a second, private one.' && $data['basicInfo']['backstory'] === $data['backstory'], 'Every stored copy of the fact is updated.');
  $check($state['description'] === 'A ferry warden with ink-stained cuffs.' && !isset($data['description']), 'A fact stays in the store it lives in.');
  $check($data['stats'] === ['ac' => 16, 'maxHp' => 22] && $data['level'] === 2, 'Unspecified fields are preserved.');
  $history = $database->select('dc_actor_revisions', 'h')->fields('h')->condition('actor_uuid', $actor_uuid)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  $check(count($history) === 1 && (int) $history[0]['revision'] === 5 && (int) $history[0]['parent_revision'] === 4, 'One history record per changed revision.');
  $check(json_decode($history[0]['document'], TRUE)['narrative']['biography'] === $data['backstory'], 'History stores the validated canonical document.');

  // Idempotency.
  $replay = $manager->executeActorCommand($edit, $owner);
  $check($replay == $receipt && (int) $load()['version'] === 5, 'Replaying a command returns the same receipt without a second write.');
  $reused = $edit;
  $reused['payload']['operations'][0]['value'] = 'Different text under the same command id.';
  $check($manager->executeActorCommand($reused, $owner)['code'] === 'actor_command_id_reused', 'A reused command id with a different request is rejected.');

  // No-change command: receipt, no revision.
  $same = $manager->executeActorCommand($command([['op' => 'set', 'path' => '/narrative/description', 'value' => 'A ferry warden with ink-stained cuffs.']]), $owner);
  $check($same['committed'] === TRUE && $same['changed'] === [] && $same['new_revision'] === 5 && str_starts_with($same['next_steps'][0], 'Nothing changed'), 'A no-change command does not advance the revision.');

  // Rename keeps every copy and the hot column equal.
  $rename = $manager->executeActorCommand($command([['op' => 'set', 'path' => '/identity/name', 'value' => 'Odalys Fenwick-Marr']], 'actor.identity.patch'), $owner);
  $row = $load();
  $data = json_decode($row['character_data'], TRUE);
  $check($rename['new_revision'] === 6 && $row['name'] === 'Odalys Fenwick-Marr' && $data['name'] === $row['name'] && $data['display_name'] === $row['name'] && $data['basicInfo']['name'] === $row['name'], 'Rename updates the column and every stored copy.');

  // Failures change nothing.
  $baseline = $load();
  $failures = [
    'actor_contract_invalid path outside command' => [$command([['op' => 'set', 'path' => '/build/level', 'value' => 9]]), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid unknown path' => [$command([['op' => 'set', 'path' => '/narrative/secret_plan', 'value' => 'x']]), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid too long' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => str_repeat('a', 1001)]]), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid wrong type' => [$command([['op' => 'set', 'path' => '/identity/name', 'value' => ['x']]], 'actor.identity.patch'), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid name removal' => [$command([['op' => 'unset', 'path' => '/identity/name']], 'actor.identity.patch'), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid duplicate path' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a'], ['op' => 'unset', 'path' => '/narrative/fears']]), $owner, 'actor_contract_invalid'],
    'actor_contract_invalid client force flag' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a']]) + ['force' => TRUE], $owner, 'actor_contract_invalid'],
    'actor_scope_mismatch' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a']], 'actor.narrative.patch', ['scope' => 'draft']), $owner, 'actor_scope_mismatch'],
    'actor_not_found for another user' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a']]), $stranger, 'actor_not_found'],
    'actor_not_found unknown uuid' => [$command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'a']], 'actor.narrative.patch', ['actor_uuid' => \Drupal::service('uuid')->generate()]), $owner, 'actor_not_found'],
  ];
  foreach ($failures as $label => [$bad, $account, $code]) {
    $result = $manager->executeActorCommand($bad, $account);
    $check($result['committed'] === FALSE && $result['code'] === $code, $label . ' => ' . json_encode($result));
    $check($result['next_steps'] !== [], $label . ' explains the next step.');
    $check($load() == $baseline, $label . ' must change nothing.');
  }

  // Unset removes the stored fact everywhere.
  $removed = $manager->executeActorCommand($command([['op' => 'unset', 'path' => '/narrative/biography']]), $owner);
  $data = json_decode($load()['character_data'], TRUE);
  $check($removed['committed'] === TRUE && !isset($data['backstory']) && !isset($data['basicInfo']['backstory']), 'Unset removes every stored copy.');

  // Lifecycle: archive blocks edits, restore returns the previous status.
  $lifecycle = static fn(string $type): array => ['payload' => []] + $command([], $type);
  $archived = $manager->executeActorCommand($lifecycle('actor.archive'), $owner);
  $row = $load();
  $meta = json_decode($row['character_data'], TRUE)['_archive_meta'] ?? NULL;
  $check($archived['committed'] === TRUE && (int) $row['status'] === 2 && ($meta['previous_status'] ?? NULL) === 0, 'Archive sets the status and remembers the previous one: ' . json_encode($archived));
  $check($archived['changed'] === [['path' => '/lifecycle/archived', 'before' => FALSE, 'after' => TRUE]], 'Archive reports its change.');
  $again = $manager->executeActorCommand($lifecycle('actor.archive'), $owner);
  $check($again['committed'] === TRUE && $again['changed'] === [] && $again['new_revision'] === $archived['new_revision'], 'Archiving an archived actor changes nothing.');
  $blocked = $manager->executeActorCommand($command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'Deep water.']]), $owner);
  $check($blocked['code'] === 'actor_precondition_failed', 'Archived actors cannot be edited.');
  $check($manager->executeActorCommand($lifecycle('actor.archive') + ['x' => 1], $owner)['code'] === 'actor_contract_invalid', 'Lifecycle envelope stays strict.');
  $with_ops = $command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'x']], 'actor.restore');
  $check($manager->executeActorCommand($with_ops, $owner)['code'] === 'actor_contract_invalid', 'Lifecycle commands accept no operations.');
  $restored = $manager->executeActorCommand($lifecycle('actor.restore'), $owner);
  $row = $load();
  $check($restored['committed'] === TRUE && (int) $row['status'] === 0 && !isset(json_decode($row['character_data'], TRUE)['_archive_meta']), 'Restore returns the previous status and clears the marker.');
  $check($manager->executeActorCommand($command([['op' => 'set', 'path' => '/narrative/fears', 'value' => 'Deep water.']]), $owner)['committed'] === TRUE, 'A restored actor can be edited again.');

  // The archive confirmation form writes through the command authority.
  $switcher = \Drupal::service('account_switcher');
  $switcher->switchTo($owner);
  try {
    $revision_before = (int) $load()['version'];
    $form_state = new \Drupal\Core\Form\FormState();
    $form_state->addBuildInfo('args', [$id]);
    \Drupal::formBuilder()->submitForm(\Drupal\dungeoncrawler_content\Form\CharacterArchiveForm::class, $form_state);
    $check($form_state->getErrors() === [], 'Archive form submits without errors: ' . json_encode($form_state->getErrors()));
  }
  finally {
    $switcher->switchBack();
  }
  $row = $load();
  $check((int) $row['status'] === 2 && (int) $row['version'] === $revision_before + 1, 'Archive form archives and advances the revision.');
  $last = $database->select('dc_actor_revisions', 'h')->fields('h', ['command_type', 'principal_uid'])->condition('actor_uuid', $actor_uuid)->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
  $check($last === ['command_type' => 'actor.archive', 'principal_uid' => (string) $owner_uid], 'Archive form leaves a command history record: ' . json_encode($last));

  // Delete: only an unreferenced library actor; history keeps the final state.
  $manager->executeActorCommand($lifecycle('actor.restore'), $owner);
  $instance_uuid = \Drupal::service('uuid')->generate();
  $instance_id = (int) $database->insert('dc_campaign_characters')->fields([
    'uuid' => $instance_uuid, 'campaign_id' => 987650, 'character_id' => 0, 'uid' => $owner_uid, 'type' => 'npc', 'role' => 'contact',
    'name' => 'Odalys (campaign)', 'level' => 2, 'lifecycle_state' => 'campaign_npc', 'status' => 0, 'version' => 0,
    'source_character_id' => $id, 'instance_id' => 'odalys-fixture', 'character_data' => '{}', 'created' => 1, 'changed' => 1,
  ])->execute();
  $referenced = $manager->executeActorCommand($lifecycle('actor.delete'), $owner);
  $check($referenced['code'] === 'actor_precondition_failed' && str_contains($referenced['message'], '1 campaign actor'), 'A library actor with campaign instances cannot be deleted: ' . json_encode($referenced));
  $instance_delete = ['payload' => []] + $command([], 'actor.delete', ['scope' => 'campaign_instance', 'actor_uuid' => $instance_uuid, 'campaign_id' => 987650]);
  $check($manager->executeActorCommand($instance_delete, $owner)['code'] === 'actor_precondition_failed', 'A campaign actor cannot be deleted.');
  $check($manager->executeActorCommand($lifecycle('actor.delete'), $stranger)['code'] === 'actor_not_found', 'Another user cannot delete the actor.');
  $database->delete('dc_campaign_characters')->condition('id', $instance_id)->execute();
  $final_revision = (int) $load()['version'];
  // Through the real delete confirmation form.
  $switcher->switchTo($owner);
  try {
    $form_state = new \Drupal\Core\Form\FormState();
    $form_state->addBuildInfo('args', [$id]);
    \Drupal::formBuilder()->submitForm(\Drupal\dungeoncrawler_content\Form\CharacterDeleteForm::class, $form_state);
    $check($form_state->getErrors() === [], 'Delete form submits without errors.');
  }
  finally {
    $switcher->switchBack();
  }
  $check($load() === FALSE, 'The delete form deletes an unreferenced library actor.');
  $last = $database->select('dc_actor_revisions', 'h')->fields('h', ['command_type', 'revision', 'document'])->condition('actor_uuid', $actor_uuid)->orderBy('id', 'DESC')->range(0, 1)->execute()->fetchAssoc();
  $check($last['command_type'] === 'actor.delete' && (int) $last['revision'] === $final_revision + 1 && json_decode($last['document'], TRUE)['identity']['name'] === 'Odalys Fenwick-Marr', 'History keeps the deleted actor\'s final document.');

  // Shared actor tools on the character creation GM surface.
  $switcher->switchTo($owner);
  try {
    $draft_id = (int) $database->insert('dc_campaign_characters')->fields([
      'campaign_id' => 0, 'character_id' => 0, 'uid' => $owner_uid, 'type' => 'pc', 'role' => 'player', 'name' => 'Tamsin Oarwright',
      'level' => 1, 'lifecycle_state' => 'draft_library', 'status' => 0, 'version' => 0,
      'character_data' => json_encode(['name' => 'Tamsin Oarwright', 'step' => 1, 'level' => 1]), 'created' => 1, 'changed' => 1,
    ])->execute();
    $surface = \Drupal::service('dungeoncrawler_content.editor_gm_surface.character_creation');
    $tool_context = $surface->createContext((string) $draft_id, 'editing');
    $registry = $surface->registry();
    $view = $registry->get('inspect_actor')->execute([], $tool_context);
    $check($view['target']['scope'] === 'draft' && $view['document']['identity'] === ['name' => 'Tamsin Oarwright', 'kind' => 'pc'] && $view['revision'] === 0, 'inspect_actor returns the canonical view: ' . json_encode($view['target']));
    $check(in_array('/narrative/biography', $view['commands']['actor.narrative.patch'], TRUE), 'inspect_actor lists the paths each command accepts.');
    $operations = [['op' => 'set', 'path' => '/narrative/biography', 'value' => 'Raised on the ferry.']];
    $planned = $registry->get('plan_actor_changes')->execute(['type' => 'actor.narrative.patch', 'operations' => $operations], $tool_context);
    $check($planned['intent'] === 'preview' && (int) $database->query('SELECT version FROM {dc_campaign_characters} WHERE id = :id', [':id' => $draft_id])->fetchField() === 0, 'plan_actor_changes previews without writing.');
    $applied = $registry->get('apply_actor_changes')->execute(['type' => 'actor.narrative.patch', 'operations' => $operations], $tool_context);
    $check($applied['committed'] === TRUE && $applied['new_revision'] === 1 && $applied['next_steps'] !== [], 'apply_actor_changes commits with next steps: ' . json_encode($applied));
    $check($tool_context->draft()['revision'] === 1 && $tool_context->draft()['payload']['backstory'] === 'Raised on the ferry.', 'The wizard draft sees the committed actor change.');
    $after = $registry->get('inspect_actor')->execute([], $tool_context);
    $check($after['history'][0]['command_type'] === 'actor.narrative.patch' && $after['history'][0]['changed_paths'] === ['/narrative/biography'], 'inspect_actor shows the change history.');
    // Wizard choices are a typed, idempotent actor command.
    $draft_uuid = (string) $database->query('SELECT uuid FROM {dc_campaign_characters} WHERE id = :id', [':id' => $draft_id])->fetchField();
    $choices = [
      'contract_version' => 'actor-command-v1', 'command_id' => \Drupal::service('uuid')->generate(),
      'target' => ['scope' => 'draft', 'actor_uuid' => $draft_uuid], 'type' => 'actor.build.apply_choices',
      'base_revision' => 1, 'payload' => ['choices' => ['concept' => 'A ferry pilot turned river warden.']],
    ];
    $chosen = $manager->executeActorCommand($choices, $owner);
    $check($chosen['committed'] === TRUE && $chosen['command_type'] === 'actor.build.apply_choices' && $chosen['new_revision'] === 2 && in_array('concept', $chosen['applied_fields'], TRUE), 'Wizard choices commit as a typed command: ' . json_encode(array_diff_key($chosen, ['validation' => 1, 'changed' => 1])));
    $check($manager->executeActorCommand($choices, $owner) == $chosen && (int) $database->query('SELECT version FROM {dc_campaign_characters} WHERE id = :id', [':id' => $draft_id])->fetchField() === 2, 'Replaying the choice command does not save twice.');
    $history_types = $database->query('SELECT command_type FROM {dc_actor_revisions} WHERE actor_uuid = :u ORDER BY revision', [':u' => $draft_uuid])->fetchCol();
    $check($history_types === ['actor.narrative.patch', 'actor.build.apply_choices'], 'History attributes the wizard save to its command: ' . json_encode($history_types));
    $bad_choice = ['command_id' => \Drupal::service('uuid')->generate(), 'payload' => ['choices' => ['ancestry' => 'not-an-ancestry']]] + $choices;
    $refused = $manager->executeActorCommand($bad_choice, $owner);
    $check($refused['committed'] === FALSE && $refused['code'] === 'actor_choice_ineligible' && (int) $database->query('SELECT version FROM {dc_campaign_characters} WHERE id = :id', [':id' => $draft_id])->fetchField() === 2, 'An ineligible choice is refused and changes nothing: ' . json_encode($refused['code'] ?? NULL));

    // New actors are created by the authority, which allocates identity.
    $new = $manager->createPreparedActor('actor.draft.create', [
      'campaign_id' => 0, 'name' => 'Pell Marrow', 'level' => 1, 'status' => 0, 'lifecycle_state' => 'draft_library',
      'character_data' => json_encode(['name' => 'Pell Marrow', 'level' => 1, 'step' => 1]),
    ], $owner);
    $created_row = $database->query('SELECT uuid, instance_id, uid, version FROM {dc_campaign_characters} WHERE id = :id', [':id' => $new['actor_row_id']])->fetchAssoc();
    $check($created_row === ['uuid' => $new['target']['actor_uuid'], 'instance_id' => $new['target']['actor_uuid'], 'uid' => (string) $owner_uid, 'version' => '0'], 'The authority allocates identity, owner and revision: ' . json_encode($created_row));
    $check($database->query('SELECT command_type FROM {dc_actor_revisions} WHERE actor_uuid = :u', [':u' => $new['target']['actor_uuid']])->fetchCol() === ['actor.draft.create'], 'Creation is recorded in history.');
    try {
      $manager->createPreparedActor('actor.draft.create', ['campaign_id' => 0, 'name' => 'x', 'uuid' => 'forged'], $owner);
      $check(FALSE, 'A caller-supplied identity must be rejected.');
    }
    catch (\LogicException $exception) {
      $check(str_starts_with($exception->getMessage(), 'actor_prepared_create_invalid'), 'A caller cannot supply identity columns.');
    }

    $rejected = $registry->get('apply_actor_changes')->execute(['type' => 'actor.narrative.patch', 'operations' => [['op' => 'set', 'path' => '/build/level', 'value' => 20]]], $tool_context);
    $check($rejected['committed'] === FALSE && $rejected['code'] === 'actor_contract_invalid', 'The tool cannot reach paths outside its command.');
  }
  finally {
    $switcher->switchBack();
  }

  // Canonical actor definitions: one library actor row each, shared tools.
  $switcher->switchTo(\Drupal\user\Entity\User::load(1));
  try {
    $definitions = \Drupal::service('dungeoncrawler_content.canonical_definitions');
    $slug = 'zz-fixture-ferry-warden';
    $definitions->saveDefinition('actor', NULL, [
      'actor_id' => $slug, 'version' => '1.0.0', 'actor_type' => 'npc', 'display_name' => 'Ferry Warden',
      'state_data' => ['name' => 'Ferry Warden', 'class' => 'Warden', 'level' => -1, 'hp_current' => 6, 'max_hp' => 6, 'ac' => 13, 'conditions' => [], 'backstory' => 'Counts every crossing.'],
    ]);
    $library = $database->query("SELECT id, uuid, level, lifecycle_state, version FROM {dc_campaign_characters} WHERE campaign_id = 0 AND instance_id = :s", [':s' => $slug])->fetchAssoc();
    $check($library && (int) $library['level'] === -1 && $library['lifecycle_state'] === 'canonical_template', 'Saving an actor definition creates its library actor row without clamping the level: ' . json_encode($library));
    $def_surface = \Drupal::service('dungeoncrawler_content.editor_gm_surface.definition_editor');
    $def_context = $def_surface->createContext(NULL, 'editing', ['family' => 'actor', 'definition_id' => $slug]);
    $def_tools = $def_surface->registry();
    $seen = $def_tools->get('inspect_actor')->execute([], $def_context);
    $check($seen['definition_id'] === $slug && $seen['document']['build'] === ['mechanics_model' => 'authored_statblock', 'level' => -1, 'hit_points' => ['max' => 6], 'defenses' => ['ac' => 13]] && $seen['document']['identity']['role'] === 'Warden', 'inspect_actor shows the definition in the actor contract with no invented class or statistics: ' . json_encode($seen['document']));
    $ops = [['op' => 'set', 'path' => '/narrative/biography', 'value' => 'Counts every crossing twice.'], ['op' => 'set', 'path' => '/identity/name', 'value' => 'Ferry Warden Odalys']];
    $plan_result = $def_tools->get('plan_actor_changes')->execute(['type' => 'actor.narrative.patch', 'operations' => [$ops[0]]], $def_context);
    $check($plan_result['intent'] === 'preview' && $definitions->definitionPayload('actor', $slug)['state_data']['backstory'] === 'Counts every crossing.', 'plan_actor_changes on a definition is read-only.');
    $done = $def_tools->get('apply_actor_changes')->execute(['type' => 'actor.narrative.patch', 'operations' => [$ops[0]]], $def_context);
    $renamed = $def_tools->get('apply_actor_changes')->execute(['type' => 'actor.identity.patch', 'operations' => [$ops[1]]], $def_context);
    $stored_definition = $definitions->definitionPayload('actor', $slug);
    $check($done['committed'] === TRUE && $renamed['committed'] === TRUE && $stored_definition['state_data']['backstory'] === 'Counts every crossing twice.' && $stored_definition['display_name'] === 'Ferry Warden Odalys' && $stored_definition['state_data']['name'] === 'Ferry Warden Odalys', 'apply_actor_changes saves through the definition service: ' . json_encode([$done, $renamed]));
    $mirrored = $database->query("SELECT name, version, character_data FROM {dc_campaign_characters} WHERE id = :id", [':id' => $library['id']])->fetchAssoc();
    $check($mirrored['name'] === 'Ferry Warden Odalys' && (int) $mirrored['version'] === 2 && json_decode($mirrored['character_data'], TRUE)['backstory'] === 'Counts every crossing twice.', 'The library actor row follows the definition and its revision advances.');
    $check($database->query('SELECT command_type FROM {dc_actor_revisions} WHERE actor_uuid = :u ORDER BY revision', [':u' => $library['uuid']])->fetchCol() === ['actor.template.import', 'actor.template.import', 'actor.template.import'], 'Each definition save is recorded in the actor history.');
    $unsupported = $def_tools->get('apply_actor_changes')->execute(['type' => 'actor.identity.patch', 'operations' => [['op' => 'set', 'path' => '/identity/personal/age', 'value' => '40']]], $def_context);
    $check($unsupported['committed'] === FALSE && $unsupported['code'] === 'actor_contract_invalid', 'A path the definition cannot hold is refused.');
    $direct = $manager->executeActorCommand(['target' => ['scope' => 'template', 'actor_uuid' => $library['uuid']], 'base_revision' => 2] + $command([['op' => 'set', 'path' => '/narrative/biography', 'value' => 'Bypass.']]), \Drupal::currentUser());
    $check($direct['committed'] === FALSE && $direct['code'] === 'actor_definition_owned' && $definitions->definitionPayload('actor', $slug)['state_data']['backstory'] === 'Counts every crossing twice.', 'A definition row cannot be changed around the definition service: ' . json_encode($direct));
    $check((int) $database->query('SELECT COUNT(*) FROM {dc_canonical_actors} WHERE actor_id = :s', [':s' => $slug])->fetchField() === 0 && $definitions->catalogEntry('actor', $slug)['label'] === 'Ferry Warden Odalys', 'The library actor row is the only store of a pc/npc definition.');
    $story_tools = \Drupal::service('dungeoncrawler_content.editor_gm_surface.storyline_builder')->registry();
    $check($story_tools->get('inspect_actor')->definition()->mutating === FALSE && $story_tools->get('apply_actor_changes')->definition()->mutating === TRUE, 'Storyline Builder registers the same actor tools.');
    $non_actor = $definitions->saveDefinition('actor', NULL, [
      'actor_id' => 'zz-fixture-rope-trap', 'version' => '1.0.0', 'actor_type' => 'trap', 'display_name' => 'Rope Trap',
      'state_data' => ['name' => 'Rope Trap', 'class' => 'trap', 'level' => 1, 'hp_current' => 0, 'conditions' => []],
    ]);
    $check((int) $database->query("SELECT COUNT(*) FROM {dc_campaign_characters} WHERE instance_id = 'zz-fixture-rope-trap'")->fetchField() === 0, 'A trap definition does not become an actor.');
  }
  finally {
    $switcher->switchBack();
  }

  echo "PASS: {$checks} actor command integration checks; fixture rolled back.\n";
}
finally {
  $transaction->rollBack();
}
