<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Config;

use Drupal\Tests\UnitTestCase;

/**
 * Validates Little Trouble template references stay fully anchored.
 *
 * @group dungeoncrawler_content
 * @group storyline
 */
class LittleTroubleTemplateIntegrityTest extends UnitTestCase {

  public function testLittleTroubleQuestObjectivesUseCanonicalRoomAndNpcAnchors(): void {
    $quests = $this->loadRows('dungeoncrawler_content_quest_templates/little_trouble_quest_templates.json');

    $enter_vault = $this->findRow($quests, 'template_id', 'ltba-enter-the-vault');
    $enter_objective = (array) (($enter_vault['objectives_schema'][0]['objectives'][0] ?? []));
    $this->assertSame('ltba-vault-entry', (string) ($enter_objective['destination_id'] ?? ''));
    $this->assertSame('ltba-vault-entry', (string) ($enter_objective['location_id'] ?? ''));
    $this->assertSame('ltba-hookclaw-vault-guide', (string) ($enter_objective['target'] ?? ''));

    $clear_tomb = $this->findRow($quests, 'template_id', 'ltba-clear-the-tomb');
    $hazard_objective = (array) (($clear_tomb['objectives_schema'][0]['objectives'][0] ?? []));
    $this->assertSame('ltba-tomb-hazard-room', (string) ($hazard_objective['location_id'] ?? ''));
    $this->assertSame('ltba-hookclaw-hazard-scout', (string) ($hazard_objective['target'] ?? ''));
  }

  public function testLittleTroubleRoomsExplicitlyDeclareTombNpcs(): void {
    $rooms = $this->loadRows('dungeoncrawler_content_rooms/little_trouble_room_templates.json');

    $vault_entry = $this->findRow($rooms, 'room_id', 'ltba-vault-entry');
    $vault_npc_ids = array_values(array_filter(array_map(
      static fn(array $npc): string => trim((string) ($npc['content_id'] ?? '')),
      array_values(array_filter((array) ($vault_entry['contents_data']['npcs'] ?? []), 'is_array'))
    )));
    sort($vault_npc_ids);
    $this->assertSame([
      'ltba-hookclaw-lookout',
      'ltba-hookclaw-vault-guide',
    ], $vault_npc_ids);

    $hazard_room = $this->findRow($rooms, 'room_id', 'ltba-tomb-hazard-room');
    $hazard_npc_ids = array_values(array_filter(array_map(
      static fn(array $npc): string => trim((string) ($npc['content_id'] ?? '')),
      array_values(array_filter((array) ($hazard_room['contents_data']['npcs'] ?? []), 'is_array'))
    )));
    $this->assertSame(['ltba-hookclaw-hazard-scout'], $hazard_npc_ids);
  }

  public function testGrandmasParlorTemplateOnlyExitsToAbsalomStreets(): void {
    $rooms = $this->loadRows('dungeoncrawler_content_rooms/little_trouble_room_templates.json');
    $parlor = $this->findRow($rooms, 'room_id', 'ltba-grandmas-house-parlor');
    $exits = array_values(array_filter((array) ($parlor['layout_data']['exits'] ?? []), 'is_array'));

    $exit_targets = array_values(array_map(
      static fn(array $exit): string => trim((string) ($exit['target_room_id'] ?? '')),
      $exits
    ));
    sort($exit_targets);

    $this->assertSame(['tpl_room_absalom_streets'], $exit_targets);
  }

  public function testLittleTroubleStorylineAssetAndContactAnchorsCoverTombNpcs(): void {
    $storylines = $this->loadRows('dungeoncrawler_content_storylines/default_storyline_templates.json');
    $storyline = $this->findRow($storylines, 'template_id', 'little-trouble-in-big-absalom');
    $template_data = (array) ($storyline['template_data'] ?? []);

    $asset_refs = array_values(array_filter((array) ($template_data['asset_references'] ?? []), 'is_array'));
    $tomb_npc_refs = [];
    foreach ($asset_refs as $reference) {
      if (
        (string) ($reference['asset_type'] ?? '') === 'npc'
        && (string) ($reference['chapter_id'] ?? '') === 'the-tomb'
      ) {
        $tomb_npc_refs[] = (string) ($reference['asset_id'] ?? '');
      }
    }
    sort($tomb_npc_refs);
    $this->assertSame([
      'ltba-hookclaw-hazard-scout',
      'ltba-hookclaw-lookout',
      'ltba-hookclaw-vault-guide',
    ], $tomb_npc_refs);

    $contacts = array_values(array_filter((array) ($template_data['contacts'] ?? []), 'is_array'));
    $contacts_by_id = [];
    foreach ($contacts as $contact) {
      $entity_id = trim((string) ($contact['entity_id'] ?? ''));
      if ($entity_id !== '') {
        $contacts_by_id[$entity_id] = $contact;
      }
    }

    $this->assertSame('vault-entry', (string) ($contacts_by_id['ltba-hookclaw-vault-guide']['relationship_state']['scene_id'] ?? ''));
    $this->assertSame('vault-entry', (string) ($contacts_by_id['ltba-hookclaw-lookout']['relationship_state']['scene_id'] ?? ''));
    $this->assertSame('tomb-hazards', (string) ($contacts_by_id['ltba-hookclaw-hazard-scout']['relationship_state']['scene_id'] ?? ''));
  }

  public function testLittleTroubleCharacterTemplatesDefineTombNpcRows(): void {
    $characters = $this->loadRows('dungeoncrawler_content_characters/little_trouble_character_templates.json');
    $by_instance = [];
    foreach ($characters as $character) {
      if (!is_array($character)) {
        continue;
      }
      $instance_id = trim((string) ($character['instance_id'] ?? ''));
      if ($instance_id !== '') {
        $by_instance[$instance_id] = $character;
      }
    }

    $this->assertSame('ltba-vault-entry', (string) ($by_instance['ltba-hookclaw-vault-guide']['location_ref'] ?? ''));
    $this->assertSame('ltba-vault-entry', (string) ($by_instance['ltba-hookclaw-lookout']['location_ref'] ?? ''));
    $this->assertSame('ltba-tomb-hazard-room', (string) ($by_instance['ltba-hookclaw-hazard-scout']['location_ref'] ?? ''));
  }

  public function testLittleTroubleProgressionRoutesFromGrandmotherToTombBeforeTurnIn(): void {
    $storylines = $this->loadRows('dungeoncrawler_content_storylines/default_storyline_templates.json');
    $storyline = $this->findRow($storylines, 'template_id', 'little-trouble-in-big-absalom');
    $template_data = (array) ($storyline['template_data'] ?? []);

    $chapters = array_values(array_filter((array) ($template_data['chapters'] ?? []), 'is_array'));
    $chapter_ids = array_values(array_map(static fn(array $chapter): string => (string) ($chapter['chapter_id'] ?? ''), $chapters));
    $this->assertSame(['upstairs', 'the-tomb', 'homecoming'], $chapter_ids);

    $quest_nodes = array_values(array_filter((array) ($template_data['questline']['quest_nodes'] ?? []), 'is_array'));
    $nodes_by_id = [];
    foreach ($quest_nodes as $node) {
      $quest_id = trim((string) ($node['quest_id'] ?? ''));
      if ($quest_id !== '') {
        $nodes_by_id[$quest_id] = $node;
      }
    }

    $this->assertSame('the-tomb', (string) ($nodes_by_id['ltba-enter-the-vault']['chapter_id'] ?? ''));
    $this->assertSame('homecoming', (string) ($nodes_by_id['ltba-retrieve-hedge-trimmer']['chapter_id'] ?? ''));
    $this->assertSame(['ltba-enter-the-vault'], array_values($nodes_by_id['ltba-accept-the-task']['unlocks_to'] ?? []));
  }

  protected function loadRows(string $template_file): array {
    $path = dirname(__DIR__, 4) . '/config/examples/templates/' . $template_file;
    $payload = json_decode((string) file_get_contents($path), TRUE);
    $rows = is_array($payload['rows'] ?? NULL) ? $payload['rows'] : [];
    return array_values(array_filter($rows, 'is_array'));
  }

  protected function findRow(array $rows, string $field, string $expected): array {
    foreach ($rows as $row) {
      if ((string) ($row[$field] ?? '') === $expected) {
        return $row;
      }
    }
    $this->fail(sprintf('Row not found for %s=%s.', $field, $expected));
    return [];
  }

}
