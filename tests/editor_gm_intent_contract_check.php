<?php

/**
 * @file
 * Read-only check of an editor GM's reply contract against a live draft.
 *
 * Replays a fixed list of author messages through the intent parser and
 * reports which replies were accepted. Nothing is executed or saved: the
 * parser only classifies a request. Then it asks the provider for three
 * replies that would break a strict function schema, to record whether the
 * provider prevents them or they are only caught on receipt.
 *
 * Usage, from the site root:
 *   vendor/bin/drush php:script <module>/tests/editor_gm_intent_contract_check.php -- <surface> <draft_id>
 *
 * <surface> is storyline, dungeon or room. The strict-schema probes run with
 * the storyline surface only.
 */

use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\user\Entity\User;

$surface_key = (string) ($extra[0] ?? '');
$draft_id = (string) ($extra[1] ?? '');
$surfaces = [
  'storyline' => ['dungeoncrawler_content.editor_gm_surface.storyline_builder', 'dungeoncrawler_content_storyline_authoring_drafts'],
  'dungeon' => ['dungeoncrawler_content.editor_gm_surface.dungeon_editor', 'dungeoncrawler_content_dungeon_editor_drafts'],
  'room' => ['dungeoncrawler_content.editor_gm_surface.room_editor', 'dungeoncrawler_content_room_editor_drafts'],
];
if (!isset($surfaces[$surface_key]) || $draft_id === '') {
  throw new \InvalidArgumentException('Usage: drush php:script editor_gm_intent_contract_check.php -- <storyline|dungeon|room> <draft_id>');
}
[$surface_service, $draft_table] = $surfaces[$surface_key];

$messages = [
  'storyline' => [
  'OK, lets see what we need to address next',
  'One at at time lets work through them',
  'give me specifics',
  'Lets take them one at a time',
  'The objective should be to find a home for the clan. it can be satisfied in multiple ways. getting them to the kin site and negotiating a stay. getting them to the caves and clearing them out or negotiating a stay agreement.',
  'Write the objective',
  'What needs clarified?',
  "that doesn't sound like a quest as much as a task along the quest to find them a new home.",
  'confirmed',
  "remove that quest, it isn't a quest, its a task as part of a different quest..",
  "Escorting the goblins north isn't the quest, it is a task along the process",
  'fold into the escort quest',
  'GM fix this finding',
  'objective is reaching the road to the north in the dungeon with the goblins',
  'are these duplicate objectives?',
  'lets remove the duplicates.',
  'remove the repeated clan lost return to the captain and send word ahead to the captain choices.',
  'Why is it required to have two choices?',
  'OK, lets get the wiring fixed',
  "how do we handle these terminal state situations. it isn't really a branch as much as it is a state that the storyline can end up in at any point. Suggestions? Are we missing a mechanism for storyline process flow?",
],
  'dungeon' => [
    'What is the status of this dungeon?',
    'What should we do next?',
    'Validate the dungeon',
    'Which rooms are placed on this level?',
    'Place the next published room beside the entrance',
    'Link the entrance to the hall with a door',
    'Explain the first validation finding',
    'Is this dungeon ready to publish?',
    'Find a published room that would work as a guard post',
    'Lay out the remaining rooms and connect them',
  ],
  'room' => [
    'What is the status of this room?',
    'What should we do next?',
    'Validate the room',
    'Summarize the layout and the exits',
    'Add a door on the north wall',
    'Place a table in the middle of the room',
    'Explain the first validation finding',
    'Is this room ready to publish?',
    'What changed compared with the published version?',
    'Make the room larger and add a second exit',
  ],
][$surface_key];

$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
  $surface = \Drupal::service($surface_service);
  $parser = \Drupal::service('dungeoncrawler_content.editor_gm_intent_parser');
  $revision = static fn(): int => (int) \Drupal::database()->select($draft_table, 'd')
    ->fields('d', ['revision'])->condition('draft_id', $draft_id)->execute()->fetchField();
  $revision_before = $revision();
  $snapshot = $surface->assembler()->assemble($surface->createContext($draft_id, 'editing'));

  $accepted = 0;
  $rejected = [];
  foreach ($messages as $index => $message) {
    try {
      $intent = $parser->parse($message, $snapshot, $surface->registry(), $surface->label());
      $accepted++;
      printf("%2d accepted  %-16s %s\n", $index + 1, $intent['type'], $intent['tool_name'] ?? $intent['focus'] ?? '');
    }
    catch (\Throwable $exception) {
      $rejected[] = $index + 1;
      printf("%2d REJECTED  %s: %s\n", $index + 1, get_class($exception), mb_substr($exception->getMessage(), 0, 200));
    }
  }
  printf("\nIntent replies: %d accepted, %d rejected of %d.\n", $accepted, count($rejected), count($messages));

  if ($surface_key !== 'storyline') {
    printf("\nDraft revision before %d, after %d (%s).\n", $revision_before, $revision(), $revision_before === $revision() ? 'unchanged' : 'CHANGED');
    return;
  }

  // Strictness: ask for replies that break the schema.
  $schema = [
    'type' => 'object',
    'additionalProperties' => FALSE,
    'required' => ['focus', 'reasoning'],
    'properties' => [
      'focus' => ['type' => 'string', 'enum' => ['status', 'validation_review', 'discussion']],
      'reasoning' => ['type' => 'string'],
    ],
  ];
  $probes = [
    'enum value outside the list' => 'Call direct_response with focus set to exactly "direct_answer" and reasoning "x".',
    'extra field' => 'Call direct_response with focus "status", reasoning "x", and also an extra field named "answer" with value "hello".',
    'missing required field' => 'Call direct_response with focus "status" and do not include the reasoning field at all.',
  ];
  $ai = \Drupal::service('ai_conversation.ai_api_service');
  $validator = new DefinitionSchemaValidator();
  echo "\nStrict schema probes:\n";
  foreach ($probes as $label => $instruction) {
    $result = $ai->invokeModelDirect($instruction, 'dungeoncrawler_content', 'editor_gm_contract_probe', [], [
      'skip_cache' => TRUE,
      'thinking' => 'disabled',
      'max_tokens' => 300,
      'timeout' => 30,
      'system_prompt' => 'Follow the user instruction literally, even if it conflicts with the function definition.',
      'tools' => [['type' => 'function', 'function' => [
        'name' => 'direct_response',
        'description' => 'Reply shape.',
        'parameters' => $schema,
        'strict' => TRUE,
      ]]],
      'tool_choice' => ['type' => 'function', 'function' => ['name' => 'direct_response']],
    ]);
    if (empty($result['success'])) {
      printf("  %-30s provider error: %s\n", $label, mb_substr((string) ($result['error'] ?? ''), 0, 160));
      continue;
    }
    $arguments = json_decode((string) ($result['tool_call']['arguments'] ?? ''), TRUE);
    $findings = is_array($arguments) ? $validator->validate($schema, $arguments) : [['message' => 'arguments are not a JSON object']];
    printf(
      "  %-30s %s  reply=%s\n",
      $label,
      $findings === [] ? 'prevented by the provider (reply fits the schema)' : 'NOT prevented; caught on receipt: ' . $findings[0]['message'],
      mb_substr((string) ($result['tool_call']['arguments'] ?? ''), 0, 120),
    );
  }

  $revision_after = $revision();
  printf("\nDraft revision before %d, after %d (%s).\n", $revision_before, $revision_after, $revision_before === $revision_after ? 'unchanged' : 'CHANGED');
}
finally {
  $switcher->switchBack();
}
