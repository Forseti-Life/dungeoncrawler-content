<?php

namespace Drupal\dungeoncrawler_tester\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for QA test user management.
 *
 * Creates and manages ephemeral QA test users (one per non-anonymous role)
 * used by drupal-qa-sessions.py to acquire session cookies for multi-role
 * URL permission audits. These users are created with random passwords and
 * authenticated via one-time login URLs (drush user:login), so no credentials
 * need to be stored or shared outside the Drupal state.
 *
 * Workflow:
 *   drush dctr:qa-users-ensure           → create/update qa_tester_<role> users
 *   drush user:login --uid=<uid>         → get one-time login URL
 *   drupal-qa-sessions.py follows URL    → captures Set-Cookie for audit
 *   drush dctr:qa-users-cleanup          → remove all qa_tester_ users
 */
class QaUserCommands extends DrushCommands {

  /**
   * Prefix for all QA test user accounts.
   */
  private const QA_USER_PREFIX = 'qa_tester_';

  /**
   * Roles that should never have QA test users created.
   */
  private const SKIP_ROLES = ['anonymous'];

  private LoggerChannelInterface $dcLogger;

  public function __construct(
    private EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct();
    $this->dcLogger = $loggerFactory->get('dungeoncrawler_tester');
  }

  /**
   * List all Drupal roles with permissions — use for syncing qa-permissions.json.
   *
   * @command dungeoncrawler_tester:qa-users:roles
   * @aliases dctr:qa-roles
   * @usage drush dctr:qa-roles
   * @usage drush dctr:qa-roles --format=json
   */
  public function listRoles(): void {
    $roles = Role::loadMultiple();
    $result = [];
    foreach ($roles as $rid => $role) {
      $result[] = [
        'rid' => $rid,
        'label' => $role->label(),
        'is_admin' => (bool) $role->isAdmin(),
        'permissions' => $role->getPermissions(),
      ];
    }
    $this->output()->writeln(json_encode($result, JSON_PRETTY_PRINT));
  }

  /**
   * Ensure QA test users exist for all (or specified) non-anonymous roles.
   *
   * Outputs JSON array: [{role, uid, name, mail, status}, ...].
   *
   * @command dungeoncrawler_tester:qa-users:ensure
   * @aliases dctr:qa-users-ensure
   * @option roles Comma-separated role IDs to ensure (default: all non-anonymous).
   * @usage drush dctr:qa-users-ensure
   * @usage drush dctr:qa-users-ensure --roles=dc_playwright_player,dc_playwright_admin
   */
  public function ensureUsers(array $options = ['roles' => '']): void {
    $requested = array_filter(array_map('trim', explode(',', (string) ($options['roles'] ?? ''))));
    $roles = Role::loadMultiple();
    $result = [];

    foreach ($roles as $rid => $role) {
      if (in_array($rid, self::SKIP_ROLES, TRUE)) {
        continue;
      }
      if ($requested && !in_array($rid, $requested, TRUE)) {
        continue;
      }

      $user = $this->ensureQaUser($rid);
      $result[] = [
        'role'   => $rid,
        'uid'    => (int) $user->id(),
        'name'   => $user->getAccountName(),
        'mail'   => $user->getEmail(),
        'status' => (int) $user->isActive(),
      ];
    }

    $this->output()->writeln(json_encode($result, JSON_PRETTY_PRINT));
  }

  /**
   * Remove all QA test users (cleanup after audit run).
   *
   * @command dungeoncrawler_tester:qa-users:cleanup
   * @aliases dctr:qa-users-cleanup
   * @usage drush dctr:qa-users-cleanup
   */
  public function cleanupUsers(): void {
    $storage = $this->entityTypeManager->getStorage('user');
    $uids = $storage->getQuery()
      ->condition('name', self::QA_USER_PREFIX, 'STARTS_WITH')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($uids)) {
      $this->output()->writeln('No QA test users found.');
      return;
    }

    $users = $storage->loadMultiple($uids);
    $removed = [];
    foreach ($users as $user) {
      $removed[] = $user->getAccountName();
      $user->delete();
    }

    $this->output()->writeln(sprintf(
      'Removed %d QA test users: %s',
      count($removed),
      implode(', ', $removed),
    ));
  }

  /**
   * Create or update a QA test user for a given role.
   */
  private function ensureQaUser(string $rid): User {
    $storage = $this->entityTypeManager->getStorage('user');
    $username = self::QA_USER_PREFIX . $rid;
    $mail = $username . '@qa.local';

    // Load existing QA user if present.
    $existing = $storage->loadByProperties(['name' => $username]);
    if ($existing) {
      /** @var User $user */
      $user = reset($existing);
      $changed = FALSE;

      if (!$user->isActive()) {
        $user->activate();
        $changed = TRUE;
      }

      // Ensure the target role is assigned (skip for 'authenticated' — auto-granted).
      if ($rid !== 'authenticated' && !$user->hasRole($rid)) {
        $user->addRole($rid);
        $changed = TRUE;
      }

      if ($changed) {
        $user->save();
      }
      return $user;
    }

    // Create a new QA test user. Password is random — we use OTL for auth.
    /** @var User $user */
    $user = User::create([
      'name'   => $username,
      'mail'   => $mail,
      'status' => 1,
      'pass'   => bin2hex(random_bytes(16)),
    ]);

    if ($rid !== 'authenticated') {
      $user->addRole($rid);
    }

    $user->save();

    $this->dcLogger->info(
      'Created QA test user @name (uid @uid) for role @rid',
      ['@name' => $username, '@uid' => $user->id(), '@rid' => $rid],
    );

    return $user;
  }

}
