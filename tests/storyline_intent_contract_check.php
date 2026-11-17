<?php

/**
 * @file
 * Read-only check of the Storyline GM's reply contract against a live draft.
 *
 * Replays a fixed list of author messages through the intent parser and
 * reports which replies were accepted. Nothing is executed or saved: the
 * parser only classifies a request. Then it asks the provider for three
 * replies that would break a strict function schema, to record whether the
 * provider prevents them or they are only caught on receipt.
 *
 * Usage, from the site root:
 *   vendor/bin/drush php:script <module>/tests/storyline_intent_contract_check.php -- <draft_id>
 */

use Drupal\dungeoncrawler_content\Service\Definition\DefinitionSchemaValidator;
use Drupal\user\Entity\User;

$draft_id = (string) ($extra[0] ?? '');
if ($draft_id === '') {
  throw new \InvalidArgumentException('Usage: drush php:script storyline_intent_contract_check.php -- <draft_id>');
}

$messages = [
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
];

$switcher = \Drupal::service('account_switcher');
$switcher->switchTo(User::load(1));
try {
  $surface = \Drupal::service('dungeoncrawler_content.editor_gm_surface.storyline_builder');
  $parser = \Drupal::service('dungeoncrawler_content.editor_gm_intent_parser');
  $drafts = \Drupal::service('dungeoncrawler_content.storyline_authoring_drafts');
  $revision_before = (int) $drafts->getDraft($draft_id)['revision'];
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

  $revision_after = (int) $drafts->getDraft($draft_id)['revision'];
  printf("\nDraft revision before %d, after %d (%s).\n", $revision_before, $revision_after, $revision_before === $revision_after ? 'unchanged' : 'CHANGED');
}
finally {
  $switcher->switchBack();
}
