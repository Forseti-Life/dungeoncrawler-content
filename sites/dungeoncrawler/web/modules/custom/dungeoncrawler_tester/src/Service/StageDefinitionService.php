<?php

namespace Drupal\dungeoncrawler_tester\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides stage definitions for tester runs.
 */
class StageDefinitionService {

  use StringTranslationTrait;

  public function __construct(
    TranslationInterface $translation,
    private readonly string $appRoot,
    private readonly ConfigFactoryInterface $configFactory,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Return stage definitions (shared between dashboard and automation).
   */
  public function getDefinitions(): array {
    $root = dirname($this->appRoot);

    return [
      [
        'id' => 'precommit',
        'title' => $this->t('Pre-commit: lint/format + unit'),
        'description' => $this->t('Keep fast checks green before pushing.'),
        'commands' => [
          [
            'label' => $this->t('Unit suite'),
            'description' => $this->t('Runs the fast unit test suite to catch core logic regressions before commit.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--testsuite=unit'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --testsuite=unit',
          ],
        ],
      ],
      [
        'id' => 'functional-routes',
        'title' => $this->t('Functional routes/controllers'),
        'description' => $this->t('Public, admin, character, campaign, API endpoints.'),
        'commands' => [
          [
            'label' => $this->t('Routes'),
            'description' => $this->t('Checks route access/status behavior for functional route coverage.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Routes/'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Routes/',
          ],
          [
            'label' => $this->t('Controllers'),
            'description' => $this->t('Validates controller page responses, content rendering, and access behavior.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/',
          ],
          [
            'label' => $this->t('API group'),
            'description' => $this->t('Runs API-group tests for endpoint and payload behavior.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=api'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=api',
          ],
        ],
      ],
      [
        'id' => 'ui-smoke',
        'title' => $this->t('UI smoke test (/hexmap)'),
        'description' => $this->t('Hex map UI stage-gate checks: action buttons, movement, attack, and map controls.'),
        'commands' => [
          [
            'label' => $this->t('Hexmap UI stage-gate suite'),
            'description' => $this->t('Runs /hexmap UI smoke coverage for controls, movement, attack, and map UI signals.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/HexMapUiStageGateTest.php'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/HexMapUiStageGateTest.php',
          ],
        ],
      ],
      [
        'id' => 'character-workflow',
        'title' => $this->t('Character creation workflow'),
        'description' => $this->t('8-step wizard, validation, persistence.'),
        'commands' => [
          [
            'label' => $this->t('Workflow group'),
            'description' => $this->t('Exercises character creation workflow scenarios and validation rules.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=character-creation'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=character-creation',
          ],
        ],
      ],
      [
        'id' => 'entity-campaign',
        'title' => $this->t('Entity/campaign APIs'),
        'description' => $this->t('State validation, access, lifecycle.'),
        'commands' => [
          [
            'label' => $this->t('Entity lifecycle trio'),
            'description' => $this->t('Validates campaign state access, state validation, and entity lifecycle behavior.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', 'web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateAccessTest.php', 'web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateValidationTest.php', 'web/modules/custom/dungeoncrawler_tester/tests/src/Functional/EntityLifecycleTest.php'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateAccessTest.php web/modules/custom/dungeoncrawler_tester/tests/src/Functional/CampaignStateValidationTest.php web/modules/custom/dungeoncrawler_tester/tests/src/Functional/EntityLifecycleTest.php',
          ],
        ],
      ],
      [
        'id' => 'fixtures',
        'title' => $this->t('Cross-check fixtures'),
        'description' => $this->t('PF2e reference + character fixtures up to date.'),
        'commands' => [
          [
            'label' => $this->t('PF2e rules group'),
            'description' => $this->t('Checks PF2e fixture and rules-reference consistency assertions.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--group=pf2e-rules'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --group=pf2e-rules',
          ],
        ],
      ],
      [
        'id' => 'ci-gate',
        'title' => $this->t('CI gate'),
        'description' => $this->t('All suites green; failures auto-filed.'),
        'commands' => [
          [
            'label' => $this->t('Full suite with coverage'),
            'description' => $this->t('Runs full PHPUnit coverage pass used as the CI quality gate.'),
            'args' => ['./vendor/bin/phpunit', '--configuration', 'web/modules/custom/dungeoncrawler_tester/phpunit.xml', '--coverage-html', 'tests/coverage'],
            'cwd' => $root,
            'display' => 'cd sites/dungeoncrawler && ./vendor/bin/phpunit --configuration web/modules/custom/dungeoncrawler_tester/phpunit.xml --coverage-html tests/coverage',
          ],
        ],
      ],
      [
        'id' => 'signoff',
        'title' => $this->t('Release sign-off'),
        'description' => $this->t('No open ci-failure/testing-defect blocking issues.'),
        'commands' => [
          [
            'label' => $this->t('Review open defects'),
            'description' => $this->t('Manual release sign-off check: review open blocking defects in GitHub before release.'),
            'args' => [],
            'cwd' => $root,
            'display' => 'Open GitHub issues (ci-failure, testing-defect)',
            'link' => $this->buildSignoffIssuesLink(),
          ],
        ],
      ],
    ];
  }

  /**
   * Build repository-aware URL for release sign-off defects.
   */
  private function buildSignoffIssuesLink(): string {
    $repo = $this->resolveGithubRepo();
    $query = rawurlencode('is:issue is:open label:ci-failure label:testing-defect');
    return "https://github.com/{$repo}/issues?q={$query}";
  }

  /**
   * Resolve GitHub repository with config/env fallback.
   */
  private function resolveGithubRepo(): string {
    $testerRepo = trim((string) $this->configFactory->get('dungeoncrawler_tester.settings')->get('github_repo'));
    if ($testerRepo !== '') {
      return $testerRepo;
    }

    $aiRepo = trim((string) $this->configFactory->get('ai_conversation.settings')->get('github_repo'));
    if ($aiRepo !== '') {
      return $aiRepo;
    }

    $envRepo = trim((string) getenv('TESTER_GITHUB_REPO'));
    if ($envRepo !== '') {
      return $envRepo;
    }

    return 'keithaumiller/forseti.life';
  }

}
