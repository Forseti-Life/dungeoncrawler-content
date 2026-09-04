<?php

namespace Drupal\Tests\dungeoncrawler_content\Unit\Schema;

use Drupal\Component\Serialization\Yaml;
use PHPUnit\Framework\TestCase;

/**
 * Locks the dungeon editor persistence, permissions, and route access model.
 *
 * @group dungeoncrawler_content
 */
class DungeonEditorMigrationTest extends TestCase {

  private function moduleRoot(): string {
    return dirname(__DIR__, 4);
  }

  private function install(): string {
    return (string) file_get_contents($this->moduleRoot() . '/dungeoncrawler_content.install');
  }

  /**
   * Every new table and hook is defined, and the hook block is contiguous.
   */
  public function testPersistenceAndHooksAreDefined(): void {
    $install = $this->install();

    foreach ([
      'dungeoncrawler_content_dungeon_versions',
      'dungeoncrawler_content_dungeon_editor_drafts',
      'dungeoncrawler_content_dungeon_editor_commands',
      'dungeoncrawler_content_editor_review_flags',
    ] as $table) {
      $this->assertStringContainsString(
        "\$schema['" . $table . "']",
        $install,
        $table . ' must be declared in dungeoncrawler_content_schema().'
      );
    }

    foreach (range(10193, 10198) as $number) {
      $this->assertStringContainsString(
        'function dungeoncrawler_content_update_' . $number . '(',
        $install,
        'Update hook ' . $number . ' must exist so the block stays contiguous.'
      );
    }
  }

  /**
   * The editor tables use created_at / updated_at, matching the room tables.
   *
   * Both conventions exist in this module. Mixing them inside one editor is a
   * reliable source of silent query bugs, so the choice is asserted.
   */
  public function testEditorTablesUseTheTimestampConventionOfTheRoomEditor(): void {
    $install = $this->install();
    $start = strpos($install, "\$schema['dungeoncrawler_content_dungeon_editor_drafts']");
    $end = strpos($install, "\$schema['dungeoncrawler_content_dungeon_editor_commands']");
    $this->assertIsInt($start);
    $this->assertIsInt($end);

    $block = substr($install, $start, $end - $start);
    $this->assertStringContainsString("'created_at'", $block);
    $this->assertStringContainsString("'updated_at'", $block);
    $this->assertStringNotContainsString("'created' =>", $block);
    $this->assertStringNotContainsString("'updated' =>", $block);
  }

  /**
   * The procedural dungeon payload is never touched by the editor migration.
   */
  public function testProceduralDungeonDataIsLeftAlone(): void {
    $install = $this->install();
    $start = strpos($install, 'function dungeoncrawler_content_update_10196(');
    $end = strpos($install, 'function dungeoncrawler_content_update_10197(');
    $block = substr($install, $start, $end - $start);

    $this->assertStringNotContainsString(
      "'dungeon_data'",
      $block,
      'dungeon_data holds procedurally generated dungeons and is not editor authority.'
    );
    $this->assertStringContainsString('published_version_id', $block);
  }

  /**
   * The audits record findings and never rewrite authored values.
   */
  public function testAuditsRecordFindingsWithoutRewritingData(): void {
    $install = $this->install();
    $start = strpos($install, 'function dungeoncrawler_content_update_10197(');
    $end = strlen($install);
    $block = substr($install, $start, $end - $start);

    foreach ([
      'edge_review_required',
      'port_edge_unverified',
      'schema_review_required',
      'canonical_actor',
    ] as $needle) {
      $this->assertStringContainsString($needle, $block, $needle . ' must appear in the audit hooks.');
    }

    foreach ([
      "->update('dungeoncrawler_content_room_versions')",
      "->update('dungeoncrawler_content_room_editor_drafts')",
      "->update('dc_canonical_actors')",
    ] as $forbidden) {
      $this->assertStringNotContainsString(
        $forbidden,
        $block,
        'An audit must never rewrite the data it audits. Auto-correcting would be a guess.'
      );
    }
  }

  /**
   * The edge audit derives direction from the single geometry authority.
   */
  public function testEdgeAuditUsesTheGeometryAuthority(): void {
    $install = $this->install();
    $this->assertStringContainsString(
      'RoomPlacementTransformer::neighbor(',
      $install,
      'The audit must use the one transform authority rather than a private copy of EDGE_DIRECTIONS.'
    );
    $start = strpos($install, 'function dungeoncrawler_content_update_10197(');
    $block = substr($install, $start);
    $this->assertStringNotContainsString(
      'EDGE_DIRECTIONS = ',
      $block,
      'EDGE_DIRECTIONS has exactly one home.'
    );
  }

  /**
   * The four new permissions exist and are access restricted.
   */
  public function testPermissionsAreDeclaredAndRestricted(): void {
    $permissions = Yaml::decode(
      (string) file_get_contents($this->moduleRoot() . '/dungeoncrawler_content.permissions.yml')
    );

    foreach ([
      'edit canonical dungeoncrawler dungeons',
      'publish canonical dungeoncrawler dungeons',
      'edit canonical dungeoncrawler definitions',
      'access dungeoncrawler editor suite',
    ] as $permission) {
      $this->assertArrayHasKey($permission, $permissions, $permission . ' must be declared.');
      $this->assertTrue(
        $permissions[$permission]['restrict access'],
        $permission . ' must be access restricted.'
      );
      $this->assertNotEmpty($permissions[$permission]['description']);
    }

    $this->assertNotSame(
      $permissions['edit canonical dungeoncrawler dungeons'],
      $permissions['publish canonical dungeoncrawler dungeons'],
      'Authoring a layout and altering live navigation authority are separately grantable.'
    );
  }

  /**
   * Editor routes are protected by authentication as well as permission.
   *
   * Permission configuration alone is a single point of failure: one
   * misconfigured role grant would expose an authoring surface. The route
   * itself must also require a session.
   */
  public function testEditorRoutesCarryBothAuthenticationAndPermission(): void {
    $routes = Yaml::decode(
      (string) file_get_contents($this->moduleRoot() . '/dungeoncrawler_content.routing.yml')
    );

    $editor_permissions = [
      'edit canonical dungeoncrawler rooms',
      'publish canonical dungeoncrawler rooms',
      'edit canonical dungeoncrawler dungeons',
      'publish canonical dungeoncrawler dungeons',
      'edit canonical dungeoncrawler definitions',
      'access dungeoncrawler editor suite',
    ];

    $checked = 0;
    foreach ($routes as $name => $route) {
      $permission = $route['requirements']['_permission'] ?? NULL;
      if (!in_array($permission, $editor_permissions, TRUE)) {
        continue;
      }
      $checked++;
      $this->assertSame(
        'TRUE',
        $route['requirements']['_user_is_logged_in'] ?? NULL,
        $name . ' must require an authenticated session in addition to its permission.'
      );
    }

    $this->assertGreaterThan(0, $checked, 'At least one editor route must be under test.');
  }

  /**
   * Editor permissions are never granted to anonymous or authenticated.
   *
   * Every registered user holds the authenticated role, so granting an editor
   * permission to it would expose authoring to the entire user base.
   */
  public function testEditorPermissionsAreNotGrantedToBlanketRoles(): void {
    $editor_permissions = [
      'edit canonical dungeoncrawler rooms',
      'publish canonical dungeoncrawler rooms',
      'edit canonical dungeoncrawler dungeons',
      'publish canonical dungeoncrawler dungeons',
      'edit canonical dungeoncrawler definitions',
      'access dungeoncrawler editor suite',
    ];

    foreach (['anonymous', 'authenticated'] as $role) {
      foreach ([
        $this->moduleRoot() . '/config/install/user.role.' . $role . '.yml',
        $this->moduleRoot() . '/config/optional/user.role.' . $role . '.yml',
      ] as $path) {
        if (!file_exists($path)) {
          continue;
        }
        $granted = Yaml::decode((string) file_get_contents($path))['permissions'] ?? [];
        foreach ($editor_permissions as $permission) {
          $this->assertNotContains(
            $permission,
            $granted,
            $role . ' must never hold ' . $permission . '.'
          );
        }
      }
    }

    // The module must not ship a grant of any editor permission in any config
    // it installs, to any role. Grants are a deployment decision made by an
    // administrator, never something a module enables on install.
    $config_dir = $this->moduleRoot() . '/config';
    $scanned = 0;
    if (is_dir($config_dir)) {
      $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($config_dir));
      foreach ($iterator as $file) {
        if ($file->getExtension() !== 'yml' || !str_contains($file->getFilename(), 'user.role.')) {
          continue;
        }
        $scanned++;
        $granted = Yaml::decode((string) file_get_contents($file->getPathname()))['permissions'] ?? [];
        foreach ($editor_permissions as $permission) {
          $this->assertNotContains($permission, $granted, $file->getFilename() . ' must not grant ' . $permission);
        }
      }
    }
    $this->assertSame(
      0,
      $scanned,
      'This module ships no user.role config. If that changes, editor permission grants must be reviewed here.'
    );
  }

}
