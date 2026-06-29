## Module: copilot_agent_tracker
### Path: ./sites/forseti/web/modules/custom/copilot_agent_tracker

**Files:** 21 total, 11 PHP files

### Blocker 1: HQ/Orchestrator Coupling
❌ FAIL - Found in:
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Form/LlmManagementForm.php
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/LangGraphConsoleStubController.php

### Blocker 2: Absolute File Paths
❌ FAIL - Found in:
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Form/LlmManagementForm.php
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Form/OrgAutomationToggleForm.php
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Form/ReleaseManagementCycleForm.php
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/LangGraphConsoleStubController.php

### Blocker 3: Forseti/Site-Specific Hardcoding
⚠️  WARNING - Found in:
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/copilot_agent_tracker.info.yml
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/DashboardController.php
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/src/Controller/LangGraphConsoleStubController.php

### Blocker 4: Platform-Specific Logic
✅ PASS - No platform-specific logic found

### Blocker 5: External Queue Coupling
✅ PASS - No external queue coupling found

### Blocker 6: Documentation
✅ PASS - Documentation exists:
  - ./sites/forseti/web/modules/custom/copilot_agent_tracker/README.md

