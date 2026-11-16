<?php

/**
 * Plays one quest goal three ways against the live services.
 *
 * Run: drush php:script tests/quest_goal_integration.php
 * Uses archived campaign 1152 and removes everything it creates.
 */

use Drupal\dungeoncrawler_content\Service\QuestGoalContract;

$campaign_id = 1152;
$db = \Drupal::database();
$tracker = \Drupal::service('dungeoncrawler_content.quest_tracker');
$generator = \Drupal::service('dungeoncrawler_content.quest_generator');
$character_id = (int) $db->query("SELECT id FROM {dc_campaign_characters} WHERE campaign_id = :c AND type = 'pc' AND role = 'player' AND is_active = 1 ORDER BY id LIMIT 1", [':c' => $campaign_id])->fetchField();
$character_before = $db->query('SELECT * FROM {dc_campaign_characters} WHERE id = :id', [':id' => $character_id])->fetchAssoc();

$checks = 0;
$failures = [];
$check = function (string $label, bool $ok, mixed $detail = NULL) use (&$checks, &$failures): void {
  $checks++;
  if (!$ok) {
    $failures[] = $label . ($detail === NULL ? '' : ' => ' . json_encode($detail));
  }
};
$refused = function (callable $call): string {
  try {
    $call();
  }
  catch (\DomainException $e) {
    return strtok($e->getMessage(), ':');
  }
  return 'not_refused';
};

$phases = [[
  'phase' => 1,
  'objectives' => [
    ['objective_id' => 'find_camp', 'type' => 'interact', 'description' => 'Learn where the goblin camp is', 'completed' => FALSE, 'target' => 'npc_tavern_keeper', 'completion_criteria' => ['kind' => 'flag', 'metric' => 'completed', 'required_value' => TRUE, 'description' => 'Learn where the camp is.']],
    ['objective_id' => 'kill_goblins', 'type' => 'kill', 'description' => 'Kill the goblins', 'completed' => FALSE, 'target' => 'goblin', 'current' => 0, 'target_count' => 2, 'completion_criteria' => ['kind' => 'count', 'metric' => 'current', 'target_count' => 2, 'description' => 'Kill two goblins.']],
  ],
]];
$goal = [
  'goal_text' => 'End the goblin threat to the mill',
  'gm_notes' => 'Grubnak leaves if offered the old mine.',
  'open_resolution' => TRUE,
  'approaches' => [
    ['approach_id' => 'force', 'label' => 'Drive them out by force', 'visibility' => 'known', 'objective_ids' => ['kill_goblins'], 'outcome_label' => 'The goblins were killed', 'rewards' => ['xp' => 50, 'gold' => 0, 'items' => []]],
    ['approach_id' => 'negotiate', 'label' => 'Persuade Chief Grubnak to leave', 'visibility' => 'discoverable', 'reveal_when' => ['objective_id' => 'find_camp'], 'objective_ids' => [], 'outcome_label' => 'The goblins left peacefully', 'rewards' => ['xp' => 80, 'gold' => 0, 'items' => []]],
    ['approach_id' => 'collapse', 'label' => 'Collapse the mine tunnel', 'visibility' => 'secret', 'objective_ids' => [], 'outcome_label' => 'The goblins were sealed in'],
    ['approach_id' => 'bribe', 'label' => 'Pay them off', 'visibility' => 'discoverable', 'reveal_when' => ['touchpoint' => ['objective_type' => 'interact', 'ref' => 'captured_goblin']], 'objective_ids' => [], 'outcome_label' => 'The goblins took the gold and left'],
  ],
];

// Contract.
$check('goal valid', QuestGoalContract::validate($goal, $phases) === [], QuestGoalContract::validate($goal, $phases));
$bad = $goal;
$bad['approaches'][0]['objective_ids'] = ['nope'];
$bad['approaches'][1]['reveal_when'] = NULL;
$bad['approaches'][2]['visibility'] = 'whispered';
$check('goal defects reported', count(QuestGoalContract::validate($bad, $phases)) === 3, QuestGoalContract::validate($bad, $phases));
$only_secret = ['goal_text' => 'x', 'approaches' => [$goal['approaches'][2]]];
$check('needs a known approach', count(QuestGoalContract::validate($only_secret, $phases)) === 1);

$template_id = 'zz_goal_test_template';
$db->delete('dc_canonical_quests')->condition('template_id', $template_id)->execute();
$db->insert('dc_canonical_quests')->fields([
  'template_id' => $template_id,
  'version' => '1.0.0',
  'name' => 'Clear the mill',
  'quest_type' => 'side',
  'description' => 'Goal test quest.',
  'level_min' => 1,
  'level_max' => 20,
  'tags' => '[]',
  'prerequisites' => '{}',
  'objectives_schema' => json_encode($phases),
  'rewards_schema' => json_encode(['xp' => 30, 'gold' => 0, 'items' => []]),
  'story_impact' => json_encode(['goal' => $goal]),
  'created_at' => time(),
  'updated_at' => time(),
])->execute();

// A storyline quest takes its goal from the storyline node and its choices.
$node = [
  'quest_id' => 'clear-the-mill',
  'goal' => ['goal_text' => 'End the goblin threat to the mill', 'gm_notes' => '', 'open_resolution' => FALSE],
  'choices' => [
    ['decision_id' => 'mill-fate', 'choice_id' => 'mill-force', 'label' => 'Drive them out by force', 'approach' => ['visibility' => 'known', 'outcome_label' => 'The goblins were killed', 'objective_ids' => ['kill_goblins']]],
    ['decision_id' => 'mill-fate', 'choice_id' => 'mill-talk', 'label' => 'Talk them into leaving', 'approach' => ['visibility' => 'secret', 'outcome_label' => 'The goblins left', 'objective_ids' => []]],
    ['decision_id' => 'mill-fate', 'choice_id' => 'mill-ignore', 'label' => 'Walk away'],
  ],
];
$from_node = QuestGoalContract::fromStorylineNode($node);
$check('storyline node gives two approaches bound to their choices', array_column($from_node['approaches'], 'approach_id') === ['mill-force', 'mill-talk'] && $from_node['approaches'][1]['branch'] === ['decision_id' => 'mill-fate', 'choice_id' => 'mill-talk'], $from_node);
$check('storyline goal valid against the quest objectives', QuestGoalContract::validate($from_node, $phases) === []);
$check('approach without a goal refused', $refused(fn() => QuestGoalContract::fromStorylineNode(['goal' => NULL] + $node)) === 'quest_goal_approach_without_goal');
$generate = new \ReflectionMethod($generator, 'generateQuestGoal');
$generated = $generate->invoke($generator, [], $phases, [], $from_node);
$check('generated goal starts with known ways revealed', $generated['goal']['revealed'] === ['mill-force'] && $generated['goal']['resolved'] === NULL, $generated);
$check('goal on both template and storyline refused', $refused(fn() => $generate->invoke($generator, ['goal' => $goal], $phases, [], $from_node)) === 'quest_goal_declared_twice');
$check('template goal naming a missing objective refused at generation', $refused(fn() => $generate->invoke($generator, ['goal' => $bad], $phases, [])) === 'quest_goal_invalid');

$created = [];
$make = function (string $suffix) use ($db, $campaign_id, $phases, $goal, $template_id, &$created): string {
  $quest_id = 'zz_goal_test_' . $suffix . '_' . substr(md5(uniqid('', TRUE)), 0, 8);
  $db->insert('dc_campaign_quests')->fields([
    'campaign_id' => $campaign_id,
    'quest_id' => $quest_id,
    'source_template_id' => $template_id,
    'quest_name' => 'Clear the mill (' . $suffix . ')',
    'quest_description' => 'Goal test quest.',
    'quest_type' => 'side',
    'quest_data' => json_encode(['variables' => [], 'party_level' => 1, 'difficulty' => 'moderate', 'goal' => QuestGoalContract::start($goal)]),
    'generated_objectives' => json_encode($phases),
    'generated_rewards' => json_encode(['xp' => 30, 'gold' => 0, 'items' => []]),
    'status' => 'offered',
    'created_at' => time(),
    'available_at' => time(),
  ])->execute();
  $created[] = $quest_id;
  return $quest_id;
};
$row = function (string $quest_id) use ($db, $campaign_id): array {
  $quest = $db->query('SELECT * FROM {dc_campaign_quests} WHERE campaign_id = :c AND quest_id = :q', [':c' => $campaign_id, ':q' => $quest_id])->fetchAssoc();
  $progress = $db->query('SELECT objective_states, current_phase, completed_at, outcome FROM {dc_campaign_quest_progress} WHERE campaign_id = :c AND quest_id = :q', [':c' => $campaign_id, ':q' => $quest_id])->fetchAssoc() ?: [];
  return $progress + $quest;
};
$objective_ids = fn(array $entry): array => array_column($entry['objective_states'][0]['objectives'] ?? [], 'objective_id');
$xp = fn(): int => (int) $db->query('SELECT experience_points FROM {dc_campaign_characters} WHERE id = :id', [':id' => $character_id])->fetchField();

try {
  // A: by force, through its objectives.
  $a = $make('force');
  $check('A started', $tracker->startQuest($campaign_id, $a, $character_id) === TRUE);
  $player = $generator->buildQuestSummaryEntry($row($a), 'player');
  $check('A player sees goal', $player['goal']['goal_text'] === $goal['goal_text']);
  $check('A player sees only the known way', array_column($player['goal']['ways'], 'approach_id') === ['force'], $player['goal']['ways']);
  $check('A player told other ways may exist', $player['goal']['other_ways'] === TRUE);
  $check('A player payload has no GM notes', !str_contains(json_encode($player), 'Grubnak leaves') && !str_contains(json_encode($player), 'Collapse the mine'));
  $gm = $generator->buildQuestSummaryEntry($row($a), 'gm');
  $check('A GM sees all four ways and notes', count($gm['goal']['ways']) === 4 && $gm['goal']['gm_notes'] !== '');
  $check('A direct completion refused', $refused(fn() => $tracker->completeQuest($campaign_id, $a, $character_id)) === 'quest_goal_unresolved');

  $r = $tracker->updateObjectiveProgress($campaign_id, $a, 'find_camp', 1, $character_id);
  $check('A find_camp completes', !empty($r['objective_completed']) && empty($r['quest_completed']), $r);
  $player = $generator->buildQuestSummaryEntry($row($a), 'player');
  $check('A negotiate revealed by objective', array_column($player['goal']['ways'], 'approach_id') === ['force', 'negotiate'], $player['goal']['ways']);
  $tracker->revealQuestApproachesForTouchpoint($campaign_id, ['objective_type' => 'interact', 'npc_ref' => 'captured_goblin'], $character_id);
  $player = $generator->buildQuestSummaryEntry($row($a), 'player');
  $check('A bribe revealed by event', in_array('bribe', array_column($player['goal']['ways'], 'approach_id'), TRUE));
  $xp_before = $xp();
  $r = $tracker->updateObjectiveProgress($campaign_id, $a, 'kill_goblins', 2, $character_id);
  $check('A kills complete the quest', !empty($r['quest_completed']), $r);
  $final = $row($a);
  $goal_after = $tracker->loadQuestGoal($final);
  $check('A resolved by criteria as force', $final['status'] === 'completed' && $goal_after['resolved']['approach_id'] === 'force' && $goal_after['resolved']['resolved_by'] === 'criteria', $goal_after['resolved']);
  $check('A reward is the approach reward', $xp() - $xp_before === 50, $xp() - $xp_before);

  // B: by negotiation, judged by the GM before the camp is even found.
  $b = $make('negotiate');
  $tracker->startQuest($campaign_id, $b, $character_id);
  $check('B empty evidence refused', $refused(fn() => $tracker->resolveQuestGoal($campaign_id, $b, 'negotiate', $character_id, ' ')) === 'quest_goal_evidence_required');
  $check('B unknown approach refused', $refused(fn() => $tracker->resolveQuestGoal($campaign_id, $b, 'bake_a_cake', $character_id, 'x')) === 'quest_goal_approach_unknown');
  $check('B reveal twice refused', $refused(fn() => $tracker->revealQuestApproach($campaign_id, $b, 'force', 'x')) === 'quest_goal_approach_already_revealed');
  $view = $tracker->revealQuestApproach($campaign_id, $b, 'collapse', 'The miner mentioned the weak tunnel.');
  $check('B GM reveals a secret way', in_array('collapse', array_column(array_filter($view['ways'], fn($w) => $w['revealed']), 'approach_id'), TRUE));
  $xp_before = $xp();
  $done = $tracker->resolveQuestGoal($campaign_id, $b, 'negotiate', $character_id, 'Burasco offered Grubnak the old mine and he accepted.');
  $check('B completed', $done['success'] === TRUE && $row($b)['status'] === 'completed');
  $check('B reward is the negotiate reward', $xp() - $xp_before === 80, $xp() - $xp_before);
  $player = $generator->buildQuestSummaryEntry($row($b), 'player');
  $note = (string) $db->query("SELECT message FROM {dc_chat_messages} WHERE message LIKE :m ORDER BY id DESC LIMIT 1", [':m' => 'Quest completed: Clear the mill (negotiate)%'])->fetchField();
  $check('B narrator says how it ended', str_contains($note, 'The goblins left peacefully'), $note);
  $check('B player sees how it ended', $player['goal']['resolved']['outcome_label'] === 'The goblins left peacefully' && $player['goal']['other_ways'] === FALSE);
  $check('B player no longer sees the kill objective', !in_array('kill_goblins', $objective_ids($player), TRUE), $objective_ids($player));
  $check('B resolve twice refused', $refused(fn() => $tracker->resolveQuestGoal($campaign_id, $b, 'force', $character_id, 'x')) === 'quest_not_active');

  // C: an unplanned way.
  $c = $make('unplanned');
  $tracker->startQuest($campaign_id, $c, $character_id);
  $xp_before = $xp();
  $tracker->resolveQuestGoal($campaign_id, $c, QuestGoalContract::UNPLANNED, $character_id, 'The party flooded the camp by breaking the mill dam.');
  $check('C completed with the quest reward', $row($c)['status'] === 'completed' && $xp() - $xp_before === 30, $xp() - $xp_before);
  $player = $generator->buildQuestSummaryEntry($row($c), 'player');
  $check('C player reads the unplanned outcome', $player['goal']['resolved']['outcome_label'] === 'The party flooded the camp by breaking the mill dam.');

  // D: unplanned refused when the goal does not allow it.
  $d = $make('closed');
  $closed = QuestGoalContract::start(['open_resolution' => FALSE] + $goal);
  $db->update('dc_campaign_quests')->fields(['quest_data' => json_encode(['goal' => $closed])])->condition('quest_id', $d)->execute();
  $tracker->startQuest($campaign_id, $d, $character_id);
  $check('D unplanned refused', $refused(fn() => $tracker->resolveQuestGoal($campaign_id, $d, QuestGoalContract::UNPLANNED, $character_id, 'x')) === 'quest_goal_unplanned_not_allowed');

  // The GM prompt lists the open goal with hidden ways marked.
  $prompt = $tracker->buildQuestGoalPromptContext($campaign_id);
  $check('GM prompt lists hidden ways', str_contains($prompt, $d) && str_contains($prompt, 'approach_id collapse [HIDDEN') && str_contains($prompt, 'resolve_quest_goal'), $prompt);
}
finally {
  foreach ($created as $quest_id) {
    foreach (['dc_campaign_quests', 'dc_campaign_quest_progress', 'dc_campaign_quest_log', 'dc_campaign_quest_rewards_claimed'] as $table) {
      $db->delete($table)->condition('campaign_id', $campaign_id)->condition('quest_id', $quest_id)->execute();
    }
  }
  $db->update('dc_campaign_characters')->fields(array_diff_key($character_before, ['id' => 1]))->condition('id', $character_id)->execute();
  $db->delete('dc_canonical_quests')->condition('template_id', $template_id)->execute();
  $db->delete('dc_chat_messages')->condition('message', '%Clear the mill (%', 'LIKE')->execute();
}

echo $failures === [] ? "quest goal checks: {$checks} passed\n" : "quest goal checks: " . count($failures) . " of {$checks} FAILED\n" . implode("\n", $failures) . "\n";
