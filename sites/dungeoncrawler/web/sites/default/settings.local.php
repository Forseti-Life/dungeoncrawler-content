<?php
/**
 * Dungeon Crawler - Local development settings.
 *
 * NOTE: The user/session tables here are local BASE TABLEs (not VIEWs).
 * The forseti and dungeoncrawler sites have SEPARATE sessions tables.
 * Session cookie isolation is handled via name_suffix in
 * sites/default/services.yml so the two localhost sites never collide.
 */
$dc_local_db_password = getenv('DB_PASSWORD') ?: ($databases['default']['default']['password'] ?? '');

$databases['default']['default'] = [
  'database' => 'dungeoncrawler_dev',
  'username' => 'drupal_user',
  'password' => $dc_local_db_password,
  'host' => '127.0.0.1',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

$databases['forseti']['default'] = [
  'database' => 'forseti_dev',
  'username' => 'drupal_user',
  'password' => $dc_local_db_password,
  'host' => '127.0.0.1',
  'port' => '3306',
  'driver' => 'mysql',
  'prefix' => '',
  'collation' => 'utf8mb4_general_ci',
];

/**
 * Hash salt must match the main Forseti site for shared sessions.
 */
$settings['hash_salt'] = 'lsV6IOGvHJOJ04VsQ_cy9aMNbRtyhVdBlP9b-KX9Xj43rhdN3x8sf8zCyJFaPmkFgAU0ZdTCpw';

/**
 * Cookie domain: $settings['cookie_domain'] is NOT read by Drupal 11 core.
 * Session cookie names are controlled via session.storage.options in
 * sites/default/services.yml (name_suffix: '_dc').
 */

$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
$settings['skip_permissions_hardening'] = TRUE;
$config['system.performance']['css']['preprocess'] = FALSE;
$config['system.performance']['js']['preprocess'] = FALSE;
$config['system.mail']['interface']['default'] = 'test_mail_collector';
ini_set('sendmail_path', '/bin/true');
