<?php

declare(strict_types=1);

namespace Drupal\dungeoncrawler_content\Service\EditorGm\Tool\Storyline;

use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolContext;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolDefinition;
use Drupal\dungeoncrawler_content\Service\EditorGm\EditorGmToolInterface;
use Drupal\dungeoncrawler_content\Service\EditorGm\StorylineBuilderGmToolContext;

/**
 * Adds existing library characters to the storyline's cast in one step.
 *
 * Looks each one up and saves the roster rows itself, so binding a named
 * character never needs a separate lookup turn.
 */
final class BindLibraryCharactersTool implements EditorGmToolInterface {

  public function definition(): EditorGmToolDefinition {
    return new EditorGmToolDefinition(
      'bind_library_characters',
      EditorGmToolDefinition::FAMILY_EXECUTION,
      "Add one or more characters that already exist in the character library to this storyline's cast, by name or by library actor ID. It looks each one up itself and saves the cast rows; no prior search is needed. Use it whenever the author asks to bind, add, attach or use an existing or named character. A name that fits several different library characters is not bound and its candidates are returned.",
      TRUE,
      'StorylineAuthoringDraftService::findLibraryCharacters() + applyDraftOperations()',
      [
        EditorGmToolDefinition::argument('expected_revision', 'integer', TRUE, 'Current grounded Storyline draft revision.'),
        EditorGmToolDefinition::argument('characters', 'array', TRUE, 'One entry per character: the name the author used, or a library actor ID. Strings.'),
        EditorGmToolDefinition::argument('location_id', 'string', FALSE, 'Room ID where the characters start. Omit to use each character\'s library home.'),
      ],
    );
  }

  public function execute(array $arguments, EditorGmToolContext $context): array {
    $context = StorylineBuilderGmToolContext::of($context);
    $expected_revision = EditorGmToolContext::requireInt($arguments, 'expected_revision');
    $requests = array_values(array_unique(array_map(
      static fn(mixed $name): string => is_string($name) && trim($name) !== '' ? trim($name) : throw new \InvalidArgumentException('argument_invalid:characters'),
      EditorGmToolContext::requireArray($arguments, 'characters'),
    )));
    if ($requests === []) {
      throw new \InvalidArgumentException('argument_invalid:characters');
    }
    if (array_key_exists('location_id', $arguments) && !is_string($arguments['location_id'])) {
      throw new \InvalidArgumentException('argument_invalid:location_id');
    }
    $location_id = trim($arguments['location_id'] ?? '');

    $draft = $context->draft();
    $payload = $draft['payload'];
    $roster = array_filter((array) ($payload['objects']['actors']['generation_specs'] ?? []), 'is_array');
    $in_cast = array_fill_keys(array_column($roster, 'actor_id'), TRUE);
    // IDs the story already names: an objective's target, the entry actor.
    $named_by_story = array_fill_keys(array_filter(array_merge(
      [(string) ($payload['entry_actor_id'] ?? '')],
      array_column(array_filter((array) ($payload['process_flow']['objective_completion_criteria'] ?? []), 'is_array'), 'target'),
    )), TRUE);

    $bound = [];
    $already = [];
    $unresolved = [];
    $operations = [];
    foreach ($requests as $request) {
      $matches = $context->drafts->findLibraryCharacters($request, FALSE, 50)['characters'];
      $exact = array_values(array_filter($matches, static fn(array $match): bool => $match['actor_id'] === $request));
      $token = static fn(string $value): string => trim((string) preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($value)));
      // A match on the name outranks a match in a role, place or description.
      $terms = array_filter(explode(' ', $token($request)));
      $by_name = array_values(array_filter($matches, static function (array $match) use ($terms, $token): bool {
        foreach ($terms as $term) {
          if (!str_contains($token($match['name']), $term)) {
            return FALSE;
          }
        }
        return TRUE;
      }));
      $candidates = $exact !== [] ? $exact : ($by_name !== [] ? $by_name : $matches);
      $names = array_unique(array_map(static fn(array $match): string => $token($match['name']), $candidates));
      if ($exact === [] && count($names) === 1) {
        // One person may have several library records. The record the
        // story already names is the one to bind.
        $same_person = array_values(array_filter(
          $context->drafts->findLibraryCharacters($candidates[0]['name'], FALSE, 50)['characters'],
          static fn(array $match): bool => $token($match['name']) === $names[0],
        ));
        $named = array_values(array_filter($same_person, static fn(array $match): bool => isset($named_by_story[$match['actor_id']]) || isset($in_cast[$match['actor_id']])));
        $candidates = count($named) === 1 ? $named : $candidates;
      }
      if (count($candidates) !== 1) {
        $unresolved[] = [
          'requested' => $request,
          'reason' => $candidates === [] ? 'No library character has this name or ID.' : 'Several library records fit. Name the actor_id to bind.',
          'candidates' => array_map(static fn(array $match): array => array_intersect_key($match, array_flip(['actor_id', 'name', 'role', 'level', 'home_location', 'description'])), $candidates),
        ];
        continue;
      }
      $character = $candidates[0];
      if (isset($in_cast[$character['actor_id']])) {
        $already[] = ['actor_id' => $character['actor_id'], 'name' => $character['name']];
        continue;
      }
      $in_cast[$character['actor_id']] = TRUE;
      $row = [
        'contact_id' => $character['actor_id'],
        'actor_id' => $character['actor_id'],
        'role' => $character['role'],
        'display_name' => $character['name'],
        'level' => $character['level'],
      ];
      $place = $location_id !== '' ? $location_id : $character['home_location'];
      if ($place !== '') {
        $row['location_id'] = $place;
      }
      $operations[] = ['type' => 'append', 'path' => '/objects/actors/generation_specs', 'value' => $row];
      $bound[] = $row + ['named_by_objectives' => isset($named_by_story[$character['actor_id']])];
    }

    $revision = $expected_revision;
    if ($operations !== []) {
      $context->drafts->applyDraftOperations(
        $context->draftId,
        $expected_revision,
        'bind-' . substr(hash('sha256', $expected_revision . json_encode($operations)), 0, 24),
        $operations,
      );
      $context->invalidate();
      $revision = (int) $context->draft()['revision'];
    }
    return [
      'bound' => $bound,
      'already_in_cast' => $already,
      'not_bound' => $unresolved,
      'draft_changed' => $operations !== [],
      'revision' => $revision,
    ];
  }

}
