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
  $plan = $call('plan_character_draft_updates', ['updates' => ['name' => 'Original Workspace Fixture']]);
  $check($plan['tool_result']['requires_approval'], 'Plans must require approval.');
  $check($before === $manager->loadCharacter($id)->character_data, 'Planning must not persist.');
  $proposal = $plan['tool_result']['proposed_execution'];
  $call($proposal['tool_name'], $proposal['arguments']);
  $saved = $drafts->getDraft($id);
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
  $invalid = $call('plan_character_draft_updates', ['updates' => ['free_boosts' => ['strength']]]);
  $check($invalid['tool_result']['blocking_errors'] !== [], 'Populated invalid boost counts must be reported.');
  $check($invalid['tool_result']['proposed_execution'] === NULL, 'Invalid plans must not expose approval.');
  $publication = $call('validate_draft', ['profile' => 'publication']);
  $check(!$publication['tool_result']['valid'], 'Publication must reject incomplete drafts.');
  $queue = new \ReflectionMethod($harness, 'executeTaskQueue');
  $queued = $queue->invoke($harness, $harness->surface('character_creation'), (string) $id, 'editing', [
    ['id' => 'queued-change', 'tool_name' => 'apply_character_draft_updates',
      'arguments' => ['expected_revision' => $saved['revision'], 'updates' => ['name' => 'Queued Proposal']],
      'depends_on' => []],
  ], '', [], [], '');
  $check($queued['tool_result']['intent'] === 'proposed_execution', 'Queued natural-language changes must stop for approval.');
  $check($drafts->getDraft($id)['revision'] === $saved['revision'], 'Queued changes must not auto-apply.');
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
