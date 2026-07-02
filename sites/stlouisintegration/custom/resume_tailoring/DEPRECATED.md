# ⚠️ DEPRECATION NOTICE

## Module Status: DEPRECATED

**Effective Date**: January 28, 2026

**This module has been consolidated into the `job_application_automation` module.**

### Reason for Deprecation

The resume_tailoring module provided overlapping functionality with job_application_automation:
- Both modules handled AI-powered resume tailoring
- Both integrated with AWS Bedrock Claude
- Separate modules created maintenance overhead and confusion

### Migration Complete

All functionality has been successfully migrated to `job_application_automation`:

✅ **ResumeTailoringManager** → `job_application_automation\Service\ResumeTailoringService`
✅ **hook_entity_insert()** → Integrated into `job_application_automation.module`
✅ **AI Integration** → Consolidated service with unified logging
✅ **Automatic Tailoring** → Now part of core job posting workflow

### For Administrators

**Recommended Actions:**

1. **Disable this module**: Navigate to `/admin/modules` and uninstall `resume_tailoring`
2. **Verify job_application_automation is enabled**: Ensure the core module is active
3. **Test functionality**: Create a test job_posting node to verify automatic tailoring still works
4. **Remove module files** (optional): After uninstalling, you can safely delete this module directory

### For Developers

**Code Migration:**

Old service reference:
```php
$manager = \Drupal::service('resume_tailoring.manager');
$tailored = $manager->generateTailoredResume($resume_text, $job_title, $company, $job_description);
```

New service reference:
```php
$service = \Drupal::service('job_application_automation.resume_tailoring_service');
$tailored = $service->generateTailoredResume($resume_text, $job_title, $company, $job_description);
```

### Functionality Status

**Still Works:**
- ✅ Automatic resume tailoring when creating job postings
- ✅ Manual resume tailoring via job_application_automation interface
- ✅ AWS Bedrock Claude AI integration
- ✅ Original Resume lookup and processing

**No Longer Needed:**
- ❌ Separate resume_tailoring module
- ❌ Duplicate service definitions
- ❌ Redundant dashboard and settings forms

### Support

For questions or issues related to resume tailoring functionality, please refer to:
- **Module**: job_application_automation
- **Service**: `job_application_automation.resume_tailoring_service`
- **Documentation**: `/modules/custom/job_application_automation/README.md`

---

**This module will be removed from the codebase in a future cleanup.** All functionality continues to work through job_application_automation.
