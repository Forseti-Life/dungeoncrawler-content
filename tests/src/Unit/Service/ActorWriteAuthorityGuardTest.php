<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Service;

use DungeoncrawlerContent\Tools\ActorAuthority\ActorAuthorityScanner;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 4) . '/tools/actor-authority/ActorAuthorityScanner.php';

/**
 * Freezes the actor writer set while DC-ACTOR-AUTHORITY-20261114 migrates it.
 *
 * The reviewed register must match the source exactly. A new direct writer,
 * generic mutation call or dynamic table write fails here until it is
 * deliberately registered with a disposition and migration slice; a removed
 * writer must also be removed from the register so it cannot silently return.
 *
 * @group dungeoncrawler_content
 * @group actor_authority
 */
class ActorWriteAuthorityGuardTest extends TestCase {

  private const ENTRY_DISPOSITIONS = ['actor_authority', 'domain_participant', 'not_actor'];

  private const DYNAMIC_DISPOSITIONS = ['actor_reachable', 'historical_migration', 'not_tracked_table', 'not_database'];

  private const SLICES = ['authority', 'S2', 'S3', 'S4', 'S5', 'S6', 'frozen', 'none'];

  public function testLiteralAndConstantTablesAreClassified(): void {
    $findings = (new ActorAuthorityScanner())->scanSource(<<<'PHP'
<?php
class Example {
  private const TABLE = 'dc_psychology';
  public function run($db) {
    $db->update('dc_campaign_characters')->execute();
    $db->insert(self::TABLE)->execute();
    $db->select('dc_canonical_actors', 'a');
    $db->delete('unrelated_table')->execute();
  }
}
PHP);

    $this->assertSame(
      [
        ['literal_table_write', 'dc_campaign_characters', 'core.actor_row'],
        ['literal_table_write', 'dc_psychology', 'domain.psychology'],
        ['literal_table_read', 'dc_canonical_actors', 'core.canonical_definition'],
      ],
      array_map(static fn(array $f): array => [$f['kind'], $f['subject'], $f['lane']], $findings),
    );
  }

  public function testDynamicTablesAreReviewObligationsNotOmissions(): void {
    $findings = (new ActorAuthorityScanner())->scanSource(<<<'PHP'
<?php
function run($db, $table) {
  foreach (['dc_campaign_characters'] as $table) {
    $db->delete($table)->execute();
  }
  $entity->delete();
  $db->select($table, 't');
}
PHP);

    $this->assertCount(1, $findings);
    $this->assertSame('dynamic_table_write', $findings[0]['kind']);
    $this->assertSame('unclassified.dynamic', $findings[0]['lane']);
    $this->assertSame(['dc_campaign_characters'], $findings[0]['tracked_tables_in_file']);
  }

  public function testRawSqlAndCommentsAreHandled(): void {
    $findings = (new ActorAuthorityScanner())->scanSource(<<<'PHP'
<?php
// $db->update('dc_campaign_characters') in a comment is not a writer.
function run($db, $table) {
  $db->query("UPDATE {dc_campaign_characters} SET level = 1");
  $db->query('DELETE FROM {' . $table . '} WHERE id = 1');
  $message = 'Please update the campaign from the menu.';
}
PHP);

    $this->assertSame(
      [['raw_sql_write', 'dc_campaign_characters'], ['dynamic_sql_write', 'DELETE FROM']],
      array_map(static fn(array $f): array => [$f['kind'], $f['subject']], $findings),
    );
  }

  public function testGenericMutationCallsKeepTheirReceiver(): void {
    $findings = (new ActorAuthorityScanner())->scanSource(<<<'PHP'
<?php
class Example {
  public function run() {
    $this->characterManager->updateCharacter(1, []);
    $this->roomStateService->setState('r', []);
  }
  public function updateCharacter() {}
}
PHP);

    $this->assertSame(
      ['characterManager->updateCharacter', 'roomStateService->setState'],
      array_column($findings, 'subject'),
    );
  }

  /**
   * The committed register equals what the source actually contains.
   */
  public function testRegisterMatchesProductionSourceExactly(): void {
    $register = $this->register();
    $actual = $this->productionRegister();
    $expected = array_map(static fn(array $file): array => $file['sites'], $register['files']);

    $differences = [];
    foreach (array_unique(array_merge(array_keys($actual), array_keys($expected))) as $file) {
      $have = $actual[$file] ?? [];
      $want = $expected[$file] ?? [];
      foreach (array_unique(array_merge(array_keys($have), array_keys($want))) as $site) {
        if (($have[$site] ?? 0) !== ($want[$site] ?? 0)) {
          $differences[] = sprintf('%s %s: source=%d register=%d', $file, $site, $have[$site] ?? 0, $want[$site] ?? 0);
        }
      }
    }

    $this->assertSame([], $differences, "Actor writer sites changed. Route new mutations through the actor authority, or update tools/actor-authority/writer-register.json with a reviewed disposition and slice.\n" . implode("\n", $differences));
    $this->assertSame(count($expected), $register['totals']['files']);
    $this->assertSame(array_sum(array_map('array_sum', $expected)), $register['totals']['write_sites']);
  }

  /**
   * Every dynamic or generic site carries a reviewed disposition.
   */
  public function testEveryUnprovableSiteHasReviewedDisposition(): void {
    $register = $this->register();
    $missing = [];
    foreach ($register['files'] as $file => $entry) {
      $this->assertContains($entry['slice'], self::SLICES, $file);
      $reaches_actor = FALSE;
      foreach (array_keys($entry['sites']) as $site) {
        [$kind, $operation, $subject] = explode(':', $site, 3);
        if ($kind === 'mutation_entry_call') {
          $disposition = $register['entry_call_dispositions'][$file . '|' . $subject]['disposition'] ?? NULL;
          $allowed = self::ENTRY_DISPOSITIONS;
          $reaches_actor = $reaches_actor || $disposition !== 'not_actor';
        }
        elseif (str_starts_with($kind, 'dynamic_')) {
          $disposition = $register['dynamic_dispositions'][$file . '|' . $operation . '|' . $subject]['disposition'] ?? NULL;
          $allowed = self::DYNAMIC_DISPOSITIONS;
          $reaches_actor = $reaches_actor || $disposition === 'actor_reachable';
        }
        else {
          $reaches_actor = TRUE;
          continue;
        }
        if (!in_array($disposition, $allowed, TRUE)) {
          $missing[] = $file . ' ' . $site;
        }
      }
      // A file is exempt from migration only when nothing in it can reach an
      // actor store; historical update hooks are frozen instead.
      if ($entry['slice'] === 'none') {
        $this->assertFalse($reaches_actor, $file . ' reaches actor state but has no migration slice.');
      }
      elseif ($entry['slice'] !== 'frozen') {
        $this->assertTrue($reaches_actor, $file . ' has a migration slice but no actor-reaching site.');
      }
    }

    $this->assertSame([], $missing, 'Sites without a reviewed disposition.');
  }

  /**
   * The rules ledger covers the three schemas and names its open decisions.
   */
  public function testRulesDisparityLedgerIsConsistent(): void {
    $ledger = json_decode((string) file_get_contents($this->modulePath('tools/actor-authority/rules-disparity-ledger.json')), TRUE, 512, JSON_THROW_ON_ERROR);
    $ids = array_column($ledger['rows'], 'id');
    $this->assertSame($ids, array_unique($ids));
    foreach ($ledger['rows'] as $row) {
      $this->assertContains($row['status'], ['decided', 'needs_decision'], $row['id']);
      $this->assertNotSame('', trim($row['target_rule']), $row['id']);
      $this->assertNotEmpty($row['observed'], $row['id']);
      if ($row['status'] === 'needs_decision') {
        $this->assertArrayHasKey('blocks', $row, $row['id']);
      }
    }

    // The recorded level disparity must still describe the real schemas.
    $level = fn(string $schema, array $path): array => array_intersect_key(
      array_reduce($path, static fn(array $node, string $key): array => $node[$key], json_decode((string) file_get_contents($this->modulePath('config/schemas/' . $schema)), TRUE, 512, JSON_THROW_ON_ERROR)),
      ['minimum' => 0, 'maximum' => 0],
    );
    $this->assertSame(['minimum' => 1, 'maximum' => 20], $level('character.schema.json', ['properties', 'level']));
    $this->assertSame(['minimum' => -1, 'maximum' => 30], $level('canonical_actor.schema.json', ['definitions', 'state_data', 'properties', 'level']));
    $this->assertSame(['minimum' => -1, 'maximum' => 25], $level('creature.schema.json', ['properties', 'level']));
  }

  /**
   * Every source field has exactly one owner; an unmapped field blocks.
   */
  public function testFieldMapCoversSchemasAndObservedStores(): void {
    $map = json_decode((string) file_get_contents($this->modulePath('tools/actor-authority/field-map.json')), TRUE, 512, JSON_THROW_ON_ERROR);
    $fields = $map['fields'];
    foreach ($fields as $key => $field) {
      $this->assertMatchesRegularExpression('/^(document:(identity|build|narrative|defaults|provenance)|instance:(state|placement)|row:|domain:|derived$|remove:transport$)/', $field['target'], $key);
    }

    $schema_properties = [];
    $schemas = [
      'character.schema.json' => [[]],
      'creature.schema.json' => [[]],
      'canonical_actor.schema.json' => [[], ['definitions', 'state_data']],
    ];
    foreach ($schemas as $file => $pointers) {
      $schema = json_decode((string) file_get_contents($this->modulePath('config/schemas/' . $file)), TRUE, 512, JSON_THROW_ON_ERROR);
      foreach ($pointers as $pointer) {
        $node = array_reduce($pointer, static fn(array $carry, string $key): array => $carry[$key], $schema);
        $schema_properties = array_merge($schema_properties, array_keys($node['properties']));
      }
    }
    $observed = array_merge(...array_values($map['observed_keys']));
    $unmapped = array_values(array_diff(array_unique(array_merge($schema_properties, $observed)), array_keys($fields)));

    $this->assertSame([], $unmapped, 'Actor fields without an owner in tools/actor-authority/field-map.json.');
  }

  /**
   * @return array<string, mixed>
   */
  private function register(): array {
    return json_decode((string) file_get_contents($this->modulePath('tools/actor-authority/writer-register.json')), TRUE, 512, JSON_THROW_ON_ERROR);
  }

  /**
   * @return array<string, array<string, int>>
   */
  private function productionRegister(): array {
    $scanner = new ActorAuthorityScanner();
    $report = $scanner->scan(['module' => $this->modulePath('')]);
    $production = array_filter($report['findings'], static fn(array $finding): bool => !str_starts_with($finding['file'], 'tests/'));
    $register = [];
    foreach ($scanner->summarize($production)['write_register'] as $file => $sites) {
      $register[substr($file, strlen('module:'))] = $sites;
    }

    return $register;
  }

  private function modulePath(string $relative): string {
    return dirname(__DIR__, 4) . ($relative === '' ? '' : '/' . $relative);
  }

}
