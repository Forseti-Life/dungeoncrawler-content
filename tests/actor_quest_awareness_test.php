<?php

/**
 * @file
 * Isolated actor/quest prompt regression checks; every fixture is rolled back.
 *
 * Run with the site's Drush php:script against this file.
 */

$db = \Drupal::database();
$campaign_id = random_int(1900000000, 2000000000);
$transaction = $db->startTransaction();
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
  if (!$condition) {
    throw new \RuntimeException('FAIL: ' . $message);
  }
  $checks++;
  echo "PASS: $message\n";
};

try {
  $psychology = \Drupal::service('dungeoncrawler_content.npc_psychology_service');
  $narrative = \Drupal::service('dungeoncrawler_content.actor_narrative_context_service');
  $psychology->getOrCreateProfile($campaign_id, 'awareness-contact', [
    'display_name' => 'Courier Contact',
    'description' => 'A retired courier who needs her signal lantern recovered.',
    'backstory' => 'She sent a search party to the harbor and wants them found.',
    'role' => 'contact',
    'initial_attitude' => 'friendly',
  ]);
  $broker = $psychology->getOrCreateProfile($campaign_id, 'awareness-broker', [
    'display_name' => 'Harbor Broker',
    'role' => 'contact',
  ]);
  $stored = $db->select('dc_psychology', 'p')->fields('p')
    ->condition('campaign_id', $campaign_id)->condition('entity_ref', 'npc_awareness-contact')
    ->execute()->fetchAssoc();
  $legacy_sheet = json_decode($stored['character_sheet'], TRUE);
  $legacy_sheet['description'] = 'Outdated copied description.';
  $legacy_sheet['backstory'] = '';
  $db->update('dc_psychology')->fields(['character_sheet' => json_encode($legacy_sheet)])
    ->condition('id', $stored['id'])->execute();
  $sheet_before = $db->select('dc_psychology', 'p')->fields('p', ['character_sheet'])
    ->condition('id', $stored['id'])->execute()->fetchField();
  $prompt = $psychology->buildNpcContextForPrompt($campaign_id, 'awareness-contact');
  $check(str_contains($prompt, 'signal lantern'), 'canonical actor description replaces stale copied psychology facts');
  $check(str_contains($prompt, 'search party to the harbor'), 'canonical backstory reaches the actual shared prompt formatter');
  $check(!str_contains($prompt, 'Outdated copied'), 'stale psychology biography has no prompt authority');
  $check(str_contains($prompt, 'friendly'), 'psychology attitude remains available as roleplay state');
  $sheet_after = $db->select('dc_psychology', 'p')->fields('p', ['character_sheet'])
    ->condition('id', $stored['id'])->execute()->fetchField();
  $check($sheet_before === $sheet_after, 'canonical projection does not rewrite the psychology snapshot');

  $actor = $db->select('dc_campaign_characters', 'c')->fields('c')
    ->condition('campaign_id', $campaign_id)->condition('instance_id', 'npc_awareness-contact')
    ->execute()->fetchAssoc();
  $character = json_decode($actor['character_data'], TRUE);
  $character['backstory'] = 'The updated harbor briefing is authoritative.';
  $db->update('dc_campaign_characters')->fields(['character_data' => json_encode($character)])
    ->condition('id', $actor['id'])->execute();
  $check(str_contains($psychology->buildNpcContextForPrompt($campaign_id, 'awareness-contact'), 'updated harbor briefing'),
    'later canonical edits appear without recreating a psychology profile');

  $objective = [
    'objective_id' => 'courier_handoff',
    'type' => 'interact',
    'description' => 'Accept the courier request and meet the search party at the harbor.',
    'target' => 'awareness-contact',
    'next_step' => 'Use the current harbor route.',
    'completed' => TRUE,
    'revealed' => TRUE,
    'location_id' => 'contact-parlor',
  ];
  $completed_phases = [['phase' => 1, 'objectives' => [$objective]]];
  $active_objective = array_replace($objective, [
    'objective_id' => 'courier_status',
    'description' => 'Discuss the signal lantern recovery.',
    'completed' => FALSE,
  ]);
  $hidden = array_replace($active_objective, ['objective_id' => 'hidden', 'description' => 'HIDDEN SECRET', 'revealed' => FALSE]);
  $other = array_replace($active_objective, [
    'objective_id' => 'other',
    'target' => 'awareness-contact-extra',
    'description' => 'PROSE ONLY mentions awareness-contact, but targets somebody else.',
  ]);
  $future = array_replace($active_objective, ['objective_id' => 'future', 'description' => 'FUTURE SECRET']);
  $group = [
    'objective_id' => 'contact_group',
    'target' => 'awareness-broker',
    'children' => [array_replace($active_objective, [
      'objective_id' => 'nested_detail',
      'description' => 'Nested courier detail.',
      'target' => '',
      'npc_id' => (string) $actor['id'],
    ])],
  ];
  $active_phases = [
    ['phase' => 1, 'objectives' => [$active_objective, $hidden, $other, $group]],
    ['phase' => 2, 'objectives' => [$future]],
  ];
  foreach (['completed' => $completed_phases, 'active' => $active_phases] as $status => $phases) {
    $quest_id = 'awareness-' . $status;
    $db->insert('dc_campaign_quests')->fields([
      'campaign_id' => $campaign_id,
      'quest_id' => $quest_id,
      'quest_name' => 'Courier ' . $status . ' assignment',
      'quest_description' => 'Broker introduction to the courier.',
      'quest_type' => 'side',
      'quest_data' => '{}',
      'generated_objectives' => json_encode($phases),
      'generated_rewards' => '{}',
      'giver_npc_id' => $broker['campaign_character_id'],
      'location_id' => 'broker-office',
      'status' => $status,
      'created_at' => time(),
    ])->execute();
    $db->insert('dc_campaign_quest_progress')->fields([
      'campaign_id' => $campaign_id,
      'quest_id' => $quest_id,
      'current_phase' => 1,
      'objective_states' => json_encode($phases),
      'started_at' => time(),
      'last_updated' => time(),
    ])->execute();
  }
  $before = $db->select('dc_campaign_quest_progress', 'p')->fields('p')
    ->condition('campaign_id', $campaign_id)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  $context = $narrative->buildContextEnvelope($campaign_id, 'npc_awareness-contact', 'contact-parlor');
  $contacts = array_column($context['quest_links']['as_contact'], NULL, 'quest_id');
  $check(count($contacts) === 2, 'contact links survive broker ownership and different quest-row location');
  $check($context['quest_links']['as_giver'] === [], 'objective contact is not incorrectly promoted to quest owner');
  $check($context['knowledge_directives']['knows_linked_objectives'], 'actor knows its linked current and historical objectives');
  $check(!$context['knowledge_directives']['can_offer_linked_work'], 'completed/contact work is not a new quest offer');
  $check($contacts['awareness-completed']['matched_objectives'][0]['completed'], 'completed handoff survives in actor memory');
  $check(count($contacts['awareness-active']['matched_objectives']) === 2, 'nested phases and children match only this actor and visible current objectives');
  $check(str_contains($context['prompt_context'], 'Nested courier detail'), 'each relevant nested contact objective contributes prompt guidance');
  $check(!str_contains($context['prompt_context'], 'HIDDEN SECRET'), 'unrevealed objectives do not leak');
  $check(!str_contains($context['prompt_context'], 'FUTURE SECRET'), 'future phases do not leak');
  $check(!str_contains($context['prompt_context'], 'PROSE ONLY'), 'incidental prose and prefix-similar actor IDs cannot create contact links');
  $check(str_contains($context['prompt_context'], 'current harbor route'), 'current contact guidance reaches the role-scoped prompt');
  $check(str_contains($context['prompt_context'], 'not new offers'),
    'history explicitly cannot restart completed work');
  $after = $db->select('dc_campaign_quest_progress', 'p')->fields('p')
    ->condition('campaign_id', $campaign_id)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  $check($before === $after, 'actor awareness does not change progress or grant rewards');

  $db->delete('dc_campaign_quest_progress')->condition('campaign_id', $campaign_id)
    ->condition('quest_id', 'awareness-active')->execute();
  $db->delete('dc_campaign_quests')->condition('campaign_id', $campaign_id)
    ->condition('quest_id', 'awareness-active')->execute();
  $giver_context = $narrative->buildContextEnvelope($campaign_id, 'awareness-broker', 'broker-office');
  $check($giver_context['quest_links']['as_contact'] === [], 'completed giver history does not require an objective contact role');
  $check(str_contains($giver_context['prompt_context'], 'Courier completed assignment')
    && str_contains($giver_context['prompt_context'], 'history only'),
    'giver-only actors remember their completed assignments');

  $db->update('dc_campaign_characters')->fields(['character_data' => '{broken'])
    ->condition('id', $actor['id'])->execute();
  $failed_loudly = FALSE;
  try {
    $psychology->loadProfile($campaign_id, 'awareness-contact');
  }
  catch (\RuntimeException $error) {
    $failed_loudly = str_contains($error->getMessage(), 'dc_campaign_characters.character_data')
      && str_contains($error->getMessage(), 'awareness-contact');
  }
  $check($failed_loudly, 'malformed canonical actor data fails with producer and actor context instead of falling back');
}
finally {
  $transaction->rollBack();
}

echo "$checks checks passed; all fixtures rolled back.\n";
