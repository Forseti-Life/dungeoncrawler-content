## Module: dungeoncrawler_tester
### Path: ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester

**Files:** 103 total, 69 PHP files

### Blocker 1: HQ/Orchestrator Coupling
✅ PASS - No HQ/Orchestrator coupling found

### Blocker 2: Absolute File Paths
❌ FAIL - Found in:
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Routes/PublicRoutesTest.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/src/Functional/Controller/HomeControllerTest.php

### Blocker 3: Forseti/Site-Specific Hardcoding
⚠️  WARNING - Found in:
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/drush.services.yml
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Form/TheTestToggleForm.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Form/CronAgentsControlForm.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Form/SdlcResetForm.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Form/TesterSettingsForm.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Form/DeadValueCloseForm.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Form/DashboardRunsForm.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Form/OpenIssuesImportForm.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Commands/QaUserCommands.php
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/src/Commands/OpenIssuesImportCommands.php

### Blocker 4: Platform-Specific Logic
✅ PASS - No platform-specific logic found

### Blocker 5: External Queue Coupling
✅ PASS - No external queue coupling found

### Blocker 6: Documentation
✅ PASS - Documentation exists:
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/PROCESS_FLOW.md
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/DOCUMENTATION_HOME.md
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/README.md
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/tests/TESTING_MODULE_README.md
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/TESTING.md
  - ./sites/dungeoncrawler/web/modules/custom/dungeoncrawler_tester/README.md

