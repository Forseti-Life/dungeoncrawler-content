<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use Drupal\Tests\UnitTestCase;

/**
 * Ensures per-template quest mirrors stay synchronized with canonical content.
 *
 * @group dungeoncrawler_content
 * @group quest
 */
class QuestTemplateMirrorParityTest extends UnitTestCase {

  /**
   * Verifies template mirror files exactly match canonical quest definitions.
   */
  public function testQuestTemplateMirrorsMatchCanonicalContent(): void {
    $base = dirname(__DIR__, 4);
    $canonical_path = $base . '/content/quest_templates.json';
    $canonical_templates = json_decode((string) file_get_contents($canonical_path), TRUE);
    $this->assertIsArray($canonical_templates, 'Canonical quest template file must decode to an array.');

    $canonical_by_id = [];
    foreach ($canonical_templates as $template) {
      $this->assertIsArray($template, 'Canonical quest template rows must be objects.');
      $template_id = trim((string) ($template['template_id'] ?? ''));
      $this->assertNotSame('', $template_id, 'Canonical template rows must define template_id.');
      $canonical_by_id[$template_id] = $template;
    }

    $mirror_paths = glob($base . '/templates/quests/*.json') ?: [];
    $mirror_by_id = [];
    foreach ($mirror_paths as $path) {
      $decoded = json_decode((string) file_get_contents($path), TRUE);
      $this->assertIsArray($decoded, basename($path) . ' must decode to an object.');
      $template_id = trim((string) ($decoded['template_id'] ?? ''));
      $this->assertNotSame('', $template_id, basename($path) . ' must define template_id.');
      $mirror_by_id[$template_id] = $decoded;
    }

    $canonical_ids = array_keys($canonical_by_id);
    $mirror_ids = array_keys($mirror_by_id);
    sort($canonical_ids);
    sort($mirror_ids);

    $this->assertSame(
      $canonical_ids,
      $mirror_ids,
      'Mirror file IDs must exactly match canonical template IDs. Run tools/sync_quest_template_mirrors.py.'
    );

    foreach ($canonical_by_id as $template_id => $canonical_template) {
      $this->assertArrayHasKey($template_id, $mirror_by_id);
      $this->assertSame(
        $canonical_template,
        $mirror_by_id[$template_id],
        sprintf('Mirror template %s does not match canonical content. Run tools/sync_quest_template_mirrors.py.', $template_id)
      );
    }
  }

}
