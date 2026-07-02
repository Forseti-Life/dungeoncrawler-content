# Architecture: Resume Tailoring Module

## System Overview

The Resume Tailoring module follows a layered architecture with clear separation of concerns:

```
┌─────────────────────────────────────┐
│         UI Layer (Forms, Pages)     │
├─────────────────────────────────────┤
│        Service Layer (Business)     │
├─────────────────────────────────────┤
│    Entity/Data Layer (Content)      │
├─────────────────────────────────────┤
│     Storage Layer (Database)        │
└─────────────────────────────────────┘
```

## Key Components

### 1. Controllers
- **ResumeController**: Main content management
- **ExportController**: Export/download operations
- **DashboardController**: User dashboard and analytics

### 2. Forms
- **ResumeForm**: Create/edit resume content
- **TailoringForm**: Customize for specific jobs
- **SettingsForm**: Module configuration

### 3. Services
- **ResumeTailoringService**: Core tailoring logic
- **ExportService**: Handle PDF/DOCX generation
- **MatchingService**: Job description matching

### 4. Entities
- **Resume**: Custom entity for resume content
- **ResumeTail**: Tracking of tailored versions

## Data Model

### Resume Entity

```
Resume
├── title (String)
├── body (Text with format)
├── owner (User reference)
├── created (Timestamp)
├── modified (Timestamp)
├── status (Boolean)
└── version (Integer)
```

### Tailoring Metadata

```
TailKey
├── job_title (String)
├── company (String)
├── matched_keywords (Array)
├── confidence_score (Float)
├── created (Timestamp)
└── export_format (String)
```

## Request Flow

### Creating a Resume

```
User Input
    ↓
ResumeForm (validation)
    ↓
ResumeController->store()
    ↓
Resume Entity (saved)
    ↓
Database
```

### Tailoring a Resume

```
User Input (job description)
    ↓
TailoringForm
    ↓
MatchingService->analyzeJobDescription()
    ↓
ResumeTailoringService->generateSuggestions()
    ↓
TailKey Entity (saved)
    ↓
Result (rendered)
```

### Exporting Resume

```
User Request (export)
    ↓
ExportController->download()
    ↓
ExportService->generate()
    ↓
PDF/DOCX File
    ↓
Send to Client
```

## Configuration

### settings.php Integration

```php
$settings['resume_tailoring_export_dir'] = 'sites/default/files/resumes';
$settings['resume_tailoring_max_versions'] = 5;
$settings['resume_tailoring_enable_ai'] = FALSE;
```

### .env Variables

```
RESUME_TAILORING_EXPORT_FORMAT=pdf
RESUME_TAILORING_MAX_VERSIONS=5
RESUME_TAILORING_ENABLE_AI=false
```

## Performance Considerations

1. **Caching**: Resume data cached for 1 hour
2. **Database Indexing**: Owner + status indexed for queries
3. **Lazy Loading**: Relationships loaded on demand
4. **Batch Operations**: Export processed in queue

## Security

- All user inputs sanitized via Drupal forms API
- File uploads restricted to safe formats
- Export files generated in secure temporary directory
- API credentials stored in environment variables
- Permission system enforced at controller level

## Extension Points

### Hooks

```php
// Alter tailoring suggestions
hook_resume_tailoring_suggestions_alter(&$suggestions, $resume, $job_description)

// Modify export options
hook_resume_export_formats_alter(&$formats)

// Track resume usage
hook_resume_applied($resume, $job_application)
```

### Services

To add custom export format:

```php
$service = \Drupal::service('resume_tailoring.export');
$service->addFormat('custom_format', new CustomExporter());
```

## Testing

- Unit tests: `/tests/Unit/`
- Functional tests: `/tests/Functional/`
- Coverage: >80%

## Known Limitations

1. PDF generation requires external library
2. Large resumes (>100 pages) may timeout
3. Real-time AI suggestions require API access
4. Batch exports limited to 50 at a time

## Future Enhancements

- OAuth integration for job board APIs
- Real-time collaboration features
- Advanced analytics dashboard
- Mobile app support
- Automated job matching
