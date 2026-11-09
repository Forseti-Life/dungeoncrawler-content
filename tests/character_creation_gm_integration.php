<?php

/**
 * Focused live-container integration; every fixture write is rolled back.
 *
 * Run with the site's Drush php:script command. No model calls or player edits.
 */

use Drupal\Core\Form\FormState;
use Drupal\dungeoncrawler_content\Form\CharacterCreationStepForm;

$database = \Drupal::database();
$transaction = $database->startTransaction();
$original_account = \Drupal::currentUser()->getAccount();
$checks = 0;
$check = static function (bool $valid, string $message) use (&$checks): void {
  if (!$valid) {
    throw new \RuntimeException($message);
  }
  $checks++;
};
try {
  \Drupal::currentUser()->setAccount(\Drupal\user\Entity\User::load(1));
  $drafts = \Drupal::service('dungeoncrawler_content.character_creation_gm');
  $harness = \Drupal::service('dungeoncrawler_content.editor_gm_harness');
  $manager = \Drupal::service('dungeoncrawler_content.character_manager');
  $draft = $drafts->createDraft();
  $id = $draft['character_id'];
  $before = $manager->loadCharacter($id)->character_data;
  $report = $drafts->validateData($draft['payload']);
  $check($report['valid'] && !$report['ready'], 'Empty drafts must be editable but not ready.');
  $check(count($report['steps']) === 8, 'Validation must inspect all eight steps.');
  $check(isset($report['steps'][5]['choices']['general_feat']), 'Reference must include real conditional form choices.');
  $check($before === $manager->loadCharacter($id)->character_data, 'Inspection must not persist.');
  $call = static function (string $tool, array $arguments = [], bool $dry_run = FALSE) use ($harness, $id): array {
    return $harness->handle('character_creation', (string) $id, [
      'schema_version' => 'editor-gm-request-v1',
      'tool_context' => ['tool_id' => 'character_creation', 'draft_id' => (string) $id, 'validation_profile' => 'editing'],
      'intent' => ['type' => 'tool_call', 'tool_name' => $tool, 'arguments' => $arguments],
      'options' => ['dry_run' => $dry_run],
    ]);
  };
  $review = $call('validate_draft')['tool_result'];
  $check(count($review['player_review']) === count($report['errors']) + count($report['warnings']), 'Player review must reuse all authoritative findings, not a second completeness model.');
  $boost_choices = $review['steps'][4]['choices']['free_boosts'];
  $check($boost_choices['selection_count'] === 4 && $boost_choices['unique_items'], 'Boost contracts must expose the native exact count and uniqueness.');
  $preview = $call('get_character_creation_reference', ['updates' => [
    'ancestry' => 'orc', 'background' => 'warrior', 'class' => 'champion',
  ]])['tool_result'];
  $check(!empty($preview['candidate_step_choices'][1]['choices']['heritage']['options']), 'Candidate reference must expose actual ancestry-dependent choices.');
  $check(isset($preview['candidate_step_choices'][2]['choices']['background_boosts']['selection_count']), 'Candidate reference must expose actual background boost limits.');
  $fixed_background_ability = \Drupal::service('dungeoncrawler_content.ability_score_tracker')->normalizeAbilityKey(\Drupal\dungeoncrawler_content\Service\CharacterRulesCatalog::BACKGROUNDS['warrior']['fixed_boost']);
  $check(!array_key_exists($fixed_background_ability, $preview['candidate_step_choices'][2]['choices']['background_boosts']['options']), 'Background choices must exclude the already applied fixed boost.');
  $check(!isset($preview['proposed_execution']) && $before === $manager->loadCharacter($id)->character_data, 'Candidate inspection must neither save nor expose approval.');
  $chat = \Drupal::service('dungeoncrawler_content.chat_session_manager');
  $preview_session = $chat->ensureEditorGmSession('character_creation', (string) $id, 'Original Preview Fixture');
  $chat->postMessage((int) $preview_session['id'], 0, 'Game Master Agent', 'gm', '', 'Inspected candidate choices.', 'dialogue', 'private',
    ['editor_gm_response' => ['tool_result' => $preview]], FALSE);
  $controller = \Drupal\dungeoncrawler_content\Controller\EditorGmController::create(\Drupal::getContainer());
  $reference_context = new \ReflectionMethod($controller, 'characterReferenceContext');
  $check(str_contains($reference_context->invoke($controller, $preview_session, (string) $id), 'VERIFIED CHARACTER CHOICES'), 'Native candidate choices must survive into the next conversational turn.');
  $parser = \Drupal::service('dungeoncrawler_content.editor_gm_intent_parser');
  $surface = $harness->surface('character_creation');
  $flow = $call('get_character_draft')['tool_result']['process_flow'];
  $check($flow['current_step'] === $report['next_step'] && count($flow['steps']) === 8, 'The GM must follow the actual eight-step wizard, not a second cursor.');
  $check(array_unique(array_column($flow['decision_points'], 'step')) === [$report['next_step']], 'Current decisions must belong to the earliest unresolved native step.');
  $intent = $parser->parse('what needs review', $surface->assembler()->assemble($surface->createContext((string) $id, 'editing')), $surface->registry(), $surface->label());
  $check($intent['type'] === 'direct_response', 'Review requests must stay read-only rather than propose a build mutation.');
  $plan = $call('plan_character_draft_updates', ['updates' => ['name' => 'Original Workspace Fixture']]);
  $check(!$plan['tool_result']['requires_approval'], 'Character changes must not require a separate approval.');
  $check($before === $manager->loadCharacter($id)->character_data, 'Planning must not persist.');
  $proposal = $plan['tool_result']['proposed_execution'];
  $call($proposal['tool_name'], $proposal['arguments']);
  $saved = $drafts->getDraft($id);
  $check($reference_context->invoke($controller, $preview_session, (string) $id) === '', 'Stale candidate choices must not be used after the canonical object changes.');
  $check($saved['revision'] === $draft['revision'] + 1, 'Apply must increment the revision.');
  $check($saved['payload']['basicInfo']['name'] === 'Original Workspace Fixture', 'Canonical identity must reflect edits.');
  $check($saved['payload']['wizard']['name'] === 'Original Workspace Fixture', 'Wizard mirror must reflect edits.');
  $check((int) $manager->loadCharacter($id)->status === 0, 'GM apply must not finalize.');
  try {
    $call($proposal['tool_name'], $proposal['arguments']);
    throw new \LogicException('A stale plan was accepted.');
  }
  catch (\RuntimeException $exception) {
    $check($exception->getCode() === 409, 'Stale plans must return revision conflicts.');
  }
  $call('apply_character_draft_updates', ['expected_revision' => $saved['revision'], 'updates' => ['name' => 'Dry Run']], TRUE);
  $check($drafts->getDraft($id)['revision'] === $saved['revision'], 'Dry runs must not mutate.');
  $automatic_parser = new class(NULL, \Drupal::service('logger.factory')) extends \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmIntentParser {
    public array $nextIntent = [];
    public function parse(string $utterance, array $context_snapshot, \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolRegistry $registry, string $surface_label): array {
      return $this->nextIntent;
    }
  };
  $automatic_harness = new \Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmHarnessService([$surface], $automatic_parser);
  $automatic_parser->nextIntent = [
    'type' => 'tool_call', 'tool_name' => 'plan_character_draft_updates',
    'arguments' => ['updates' => ['name' => 'Original Immediate Fixture']], 'reasoning' => '',
  ];
  $natural_request = [
    'schema_version' => 'editor-gm-request-v1',
    'tool_context' => ['tool_id' => 'character_creation', 'draft_id' => (string) $id, 'validation_profile' => 'editing'],
    'intent' => ['type' => 'natural_language', 'utterance' => 'Change only my name.'],
    'options' => ['dry_run' => TRUE],
  ];
  $automatic_harness->handle('character_creation', (string) $id, $natural_request);
  $check($drafts->getDraft($id)['revision'] === $saved['revision'], 'Natural-language dry runs must not auto-apply a plan.');
  $natural_request['options']['dry_run'] = FALSE;
  $immediate = $automatic_harness->handle('character_creation', (string) $id, $natural_request);
  $check($immediate['tool_result']['tool'] === 'apply_character_draft_updates' && !isset($immediate['tool_result']['proposed_execution']), 'Conversational plans must return the actual save, not an approval proposal.');
  $check($drafts->getDraft($id)['revision'] === $saved['revision'] + 1, 'Conversational changes must save exactly once.');
  $check($immediate['context_snapshot']['process_flow']['revision'] === $drafts->getDraft($id)['revision'], 'The flow must refresh from the saved canonical revision.');
  $expected_step = $drafts->validateData($drafts->getDraft($id)['payload'])['next_step'];
  $check(!empty($immediate['tool_result']['next_steps']) && str_starts_with($immediate['tool_result']['next_steps'][0], 'Step ' . $expected_step . ':'), 'After saving, guide the next native decision rather than skipping steps.');
  $saved = $drafts->getDraft($id);
  $invalid = $call('plan_character_draft_updates', ['updates' => ['free_boosts' => ['strength']]]);
  $check($invalid['tool_result']['blocking_errors'] !== [], 'Populated invalid boost counts must be reported.');
  $check($invalid['tool_result']['proposed_execution'] === NULL, 'Invalid plans must not expose approval.');
  $check($invalid['tool_result']['intent'] === 'validation_blocked' && $invalid['messages'][0]['level'] === 'error', 'Rejected proposals must be explicit validation failures, never success-shaped approvals.');
  $chat->postMessage((int) $preview_session['id'], 0, 'Game Master Agent', 'gm', '', 'Proposal failed native validation.', 'dialogue', 'private',
    ['editor_gm_response' => ['tool_result' => $invalid['tool_result']]], FALSE);
  $repair_context = $reference_context->invoke($controller, $preview_session, (string) $id);
  $check(str_contains($repair_context, 'blocking_errors') && str_contains($repair_context, 'Select exactly 4'), 'Proposal failures and candidate constraints must remain available for the next repair turn.');
  $publication = $call('validate_draft', ['profile' => 'publication']);
  $check(!$publication['tool_result']['valid'], 'Publication must reject incomplete drafts.');
  $queue = new \ReflectionMethod($harness, 'executeTaskQueue');
  $queued = $queue->invoke($harness, $harness->surface('character_creation'), (string) $id, 'editing', [
    ['id' => 'queued-change', 'tool_name' => 'apply_character_draft_updates',
      'arguments' => ['expected_revision' => $saved['revision'], 'updates' => ['name' => 'Queued Proposal']],
      'depends_on' => []],
    ['id' => 'queued-concept', 'tool_name' => 'plan_character_draft_updates',
      'arguments' => ['updates' => ['concept' => 'Original queued concept']],
      'depends_on' => ['queued-change']],
  ], '', [], [], '');
  $check($queued['tool_result']['tool'] === 'apply_character_draft_updates', 'Queued character changes must save without approval.');
  $check($drafts->getDraft($id)['revision'] === $saved['revision'] + 2, 'Each valid queued patch must use the freshly saved revision.');
  $check($drafts->getDraft($id)['payload']['concept'] === 'Original queued concept', 'The queue must continue after a valid save.');
  $saved = $drafts->getDraft($id);
  $blocked_queue = $queue->invoke($harness, $surface, (string) $id, 'editing', [
    ['id' => 'invalid-boost', 'tool_name' => 'plan_character_draft_updates',
      'arguments' => ['updates' => ['free_boosts' => ['strength']]], 'depends_on' => []],
    ['id' => 'dependent-name', 'tool_name' => 'plan_character_draft_updates',
      'arguments' => ['updates' => ['name' => 'Must Not Be Saved']], 'depends_on' => ['invalid-boost']],
  ], '', [], [], '');
  $check($blocked_queue['tool_result']['intent'] === 'validation_blocked', 'Invalid queued choices must stop before dependent writes.');
  $check($drafts->getDraft($id)['revision'] === $saved['revision'], 'Invalid queued changes and their dependents must not save.');
  $form = \Drupal::service('class_resolver')->getInstanceFromDefinition(CharacterCreationStepForm::class);
  $state = (new FormState())->set('step', 8)->set('character_id', $id)->setValues([
    'character_version' => $saved['revision'], 'roleplay_style' => 'balanced', 'portrait_generate' => 0,
  ]);
  $form_array = [];
  $form->validateForm($form_array, $state);
  $check($state->hasAnyErrors(), 'Final creation must reject earlier incomplete steps.');
  $state->clearErrors();
  $applied = $drafts->applyDraftUpdates($id, $saved['revision'], [
    'name' => 'Revised Workspace Fixture',
    'free_boosts' => ['strength', 'dexterity', 'constitution', 'intelligence'],
  ]);
  $payload = $applied['draft']['payload'];
  $check($payload['basicInfo']['name'] === 'Revised Workspace Fixture', 'Renames must replace existing canonical identity.');
  $check($payload['abilities']['dex'] === $payload['dexterity'], 'Canonical ability mirrors must reflect boosts.');
  $check($payload['resources']['hitPoints']['max'] === $payload['hit_points']['max'], 'Combat resource mirrors must agree.');
  $wizard_updates = [
    'class' => 'wizard',
    'class_key_ability' => 'intelligence',
    'arcane_thesis' => 'improved-familiar-attunement',
    'feat_selections' => ['improved-familiar-attunement' => [
      'name' => 'Original Companion', 'selected_familiar_type' => 'owl',
      'selected_familiar_abilities' => array_slice(array_keys(\Drupal\dungeoncrawler_content\Service\FamiliarService::ABILITY_CATALOG), 0, 3),
    ]],
  ];
  $wizard_choices = $drafts->validateData(array_replace($payload, $wizard_updates))['steps'][3]['choices'];
  $wizard_updates['cantrips'] = array_slice(array_keys($wizard_choices['cantrips']['options']), 0, 5);
  $wizard_updates['spells_first'] = array_slice(array_keys($wizard_choices['spells_first']['options']), 0, 2);
  $before_projection = $manager->loadCharacter($id)->character_data;
  $wizard_plan = $drafts->planUpdates($id, $wizard_updates);
  $check($wizard_plan['blocking_errors'] === [], 'Conditional class selections must validate: ' . json_encode($wizard_plan['blocking_errors']));
  $check($before_projection === $manager->loadCharacter($id)->character_data, 'Dependent payload planning must not persist.');
  $wizard = $drafts->applyDraftUpdates($id, $applied['draft']['revision'], $wizard_updates)['draft'];
  $check($wizard['payload']['familiar']['name'] === 'Original Companion', 'GM familiar choices must construct the canonical familiar.');
  $check($wizard['payload']['familiar']['abilities'] === $wizard_updates['feat_selections']['improved-familiar-attunement']['selected_familiar_abilities'], 'Projected familiar abilities must survive persistence.');
  $check($manager->getSelectedCantripIds($wizard['payload']) === $wizard_updates['cantrips'], 'GM spell choices must construct the canonical spell payload.');
  $equipment_ids = array_merge(...array_values(array_map(static fn(array $items): array => array_column($items, 'id'), $form->creationEquipmentCatalog())));
  $over_budget = $drafts->planUpdates($id, ['equipment_ids' => $equipment_ids]);
  $check($over_budget['blocking_errors'] !== [] && $over_budget['proposed_execution'] === NULL, 'Over-budget equipment must not expose approval.');
  try {
    $drafts->planUpdates($id, ['equipment_ids' => ['original-unknown-fixture']]);
    throw new \LogicException('Unknown starting equipment was accepted.');
  }
  catch (\InvalidArgumentException $exception) {
    $check(str_contains($exception->getMessage(), 'Unknown starting equipment'), 'Unknown equipment must fail explicitly.');
  }
  $catalog_items = array_merge(...array_values($form->creationEquipmentCatalog()));
  $affordable = array_values(array_filter($catalog_items, static fn(array $item): bool => $item['cost'] > 0 && $item['cost'] <= 15))[0];
  $equipped = $drafts->applyDraftUpdates($id, $wizard['revision'], ['equipment_ids' => [$affordable['id']]])['draft'];
  $check(array_column($equipped['payload']['inventory']['carried'], 'id') === [$affordable['id']], 'GM equipment must construct the native inventory.');
  $check(abs($manager::currencyDenominationsToGoldValue($equipped['payload']['inventory']['currency']) - (15 - $affordable['cost'])) < 0.001, 'Starting currency must match the native equipment projection.');
  $fighter = $drafts->applyDraftUpdates($id, $equipped['revision'], ['class' => 'fighter', 'class_key_ability' => 'strength'])['draft'];
  $check(empty($fighter['payload']['familiar']), 'Changing to a class without a familiar must remove the dependent familiar.');
  $check($manager::getSelectedCantripIds($fighter['payload']) === [], 'Changing to a noncaster must remove stale class spells.');
  echo "PASS: $checks character framework integration checks; fixtures rolled back.\n";
}
finally {
  $transaction->rollBack();
  \Drupal::currentUser()->setAccount($original_account);
}
