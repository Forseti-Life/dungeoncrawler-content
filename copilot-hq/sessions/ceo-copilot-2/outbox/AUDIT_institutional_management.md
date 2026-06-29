## Module: institutional_management
### Path: ./sites/forseti/web/modules/custom/institutional_management

**Files:** 19 total, 3 PHP files

### Blocker 1: HQ/Orchestrator Coupling
✅ PASS - No HQ/Orchestrator coupling found

### Blocker 2: Absolute File Paths
❌ FAIL - Found in:
  - ./sites/forseti/web/modules/custom/institutional_management/src/Controller/InstitutionalController.php

### Blocker 3: Forseti/Site-Specific Hardcoding
⚠️  WARNING - Found in:
  - ./sites/forseti/web/modules/custom/institutional_management/institutional_management.routing.yml
  - ./sites/forseti/web/modules/custom/institutional_management/src/Controller/InstitutionalController.php
  - ./sites/forseti/web/modules/custom/institutional_management/institutional_management.libraries.yml
  - ./sites/forseti/web/modules/custom/institutional_management/institutional_management.info.yml

### Blocker 4: Platform-Specific Logic
✅ PASS - No platform-specific logic found

### Blocker 5: External Queue Coupling
✅ PASS - No external queue coupling found

### Blocker 6: Documentation
✅ PASS - Documentation exists:
  - ./sites/forseti/web/modules/custom/institutional_management/templates/README.md
  - ./sites/forseti/web/modules/custom/institutional_management/README.md

