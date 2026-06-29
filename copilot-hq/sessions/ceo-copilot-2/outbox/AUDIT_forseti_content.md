## Module: forseti_content
### Path: ./sites/forseti/web/modules/custom/forseti_content

**Files:** 56 total, 12 PHP files

### Blocker 1: HQ/Orchestrator Coupling
✅ PASS - No HQ/Orchestrator coupling found

### Blocker 2: Absolute File Paths
❌ FAIL - Found in:
  - ./sites/forseti/web/modules/custom/forseti_content/src/Controller/ForsetiPagesController.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Service/ForsetiPipelineStatusResolver.php
  - ./sites/forseti/web/modules/custom/forseti_content/forseti_content.routing.yml

### Blocker 3: Forseti/Site-Specific Hardcoding
⚠️  WARNING - Found in:
  - ./sites/forseti/web/modules/custom/forseti_content/forseti_content.libraries.yml
  - ./sites/forseti/web/modules/custom/forseti_content/src/Controller/AgentPowerFrameworkController.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Controller/ForsetiPagesController.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Controller/SafetyController.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Controller/ForsetiHomeController.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Controller/BatchEvaluationController.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Service/SafetyDimensionsServiceInterface.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Service/SafetyDimensionsService.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Service/AgentPowerService.php
  - ./sites/forseti/web/modules/custom/forseti_content/src/Service/ForsetiPipelineStatusResolver.php

### Blocker 4: Platform-Specific Logic
⚠️  WARNING - Found in:
  - ./sites/forseti/web/modules/custom/forseti_content/src/Controller/ForsetiPagesController.php

### Blocker 5: External Queue Coupling
✅ PASS - No external queue coupling found

### Blocker 6: Documentation
✅ PASS - Documentation exists:
  - ./sites/forseti/web/modules/custom/forseti_content/templates/archive/README.md
  - ./sites/forseti/web/modules/custom/forseti_content/README.md

