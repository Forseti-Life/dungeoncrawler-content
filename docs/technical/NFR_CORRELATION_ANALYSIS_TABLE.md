# NFR Correlation Analysis Table Design
**Created:** January 27, 2026  
**Purpose:** Design a denormalized longitudinal dataset for statistical correlation analysis of firefighter health outcomes

---

## Executive Summary

This document defines the design for `nfr_correlation_analysis` - a denormalized table that aggregates all NFR data into a single row per participant for statistical analysis. This enables:
- Correlation analysis between exposure and health outcomes
- Longitudinal tracking of participant data over time
- Simplified querying for research studies
- Machine learning model training datasets
- Exportable datasets for external statistical software (R, SPSS, SAS)

---

## Design Principles

### 1. **One Row Per Participant**
Each participant (unique `uid`) has exactly one row containing all current data aggregated from normalized tables.

### 2. **Denormalized Structure**
Unlike the normalized operational database, this table flattens relational data into columnar format for analytical queries.

### 3. **Derived Metrics**
Includes calculated fields (totals, averages, ratios) that don't exist in source tables but are valuable for analysis.

### 4. **Temporal Tracking**
Each record tracks when data was last updated to support longitudinal analysis.

### 5. **Null-Safe Design**
All fields allow NULL to represent missing or incomplete data (important for statistical analysis).

---

## Data Sources

The correlation table aggregates data from these NFR tables:

| Source Table | Rows | Data Type | Aggregation Strategy |
|--------------|------|-----------|---------------------|
| `nfr_user_profile` | 283 | Demographics | Direct 1:1 mapping |
| `nfr_questionnaire` | 301 | Health/Lifestyle | Direct 1:1 mapping |
| `nfr_work_history` | 359 | Employment | Aggregate multiple departments |
| `nfr_job_titles` | 351 | Job roles | Count by employment type |
| `nfr_incident_frequency` | 4,953 | Exposure data | Sum/average by incident type |
| `nfr_major_incidents` | 199 | Significant events | Count and categorize |
| `nfr_other_employment` | 140 | Non-fire work | Count hazardous exposures |
| `nfr_cancer_diagnoses` | 134 | Outcomes | Count, earliest diagnosis date |
| `nfr_family_cancer_history` | 211 | Family history | Count by relationship type |
| `nfr_consent` | 280 | Participation | Consent status |

---

## Table Schema: `nfr_correlation_analysis`

### System Fields (5)

```sql
id                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
uid                     INT UNSIGNED NOT NULL UNIQUE,
participant_id          VARCHAR(20),           -- NFR-YYMMDD-XXXX format
data_snapshot_date      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
questionnaire_complete  BOOLEAN DEFAULT FALSE
```

**Purpose:** Participant identification and data versioning

---

### Demographics (15 fields from nfr_user_profile + nfr_questionnaire)

```sql
-- Basic Demographics
age_at_enrollment       INT,                   -- Calculated from date_of_birth
sex                     VARCHAR(10),           -- male/female/other
date_of_birth           DATE,                  
country_of_birth        VARCHAR(100),
state_of_birth          VARCHAR(2),

-- Race/Ethnicity (stored as JSON in source, expanded here)
race_american_indian    BOOLEAN DEFAULT FALSE,
race_asian              BOOLEAN DEFAULT FALSE,
race_black              BOOLEAN DEFAULT FALSE,
race_hispanic           BOOLEAN DEFAULT FALSE,
race_middle_eastern     BOOLEAN DEFAULT FALSE,
race_pacific_islander   BOOLEAN DEFAULT FALSE,
race_white              BOOLEAN DEFAULT FALSE,
race_other              BOOLEAN DEFAULT FALSE,

-- Socioeconomic
education_level         VARCHAR(50),           -- CDC 7 categories
marital_status          VARCHAR(50),           -- CDC 7 categories
```

**Analysis Value:** Control variables for adjusting cancer risk correlations

---

### Anthropometric & Health Metrics (10 fields)

```sql
-- Body Measurements
height_inches           INT,
weight_pounds           INT,
bmi                     DECIMAL(5,2),          -- Calculated: weight/(height²) × 703
bmi_category            VARCHAR(20),           -- Underweight/Normal/Overweight/Obese

-- Lifestyle Factors (Section 6: Personal Health)
smoking_status          VARCHAR(50),           -- never/former/current
smoking_years           INT,                   -- Years smoked if former/current
alcohol_frequency       VARCHAR(50),           -- Drinking frequency
exercise_frequency      VARCHAR(50),           -- Weekly exercise frequency
diet_quality            VARCHAR(50),           -- Self-reported diet quality
sleep_hours_avg         DECIMAL(3,1),          -- Average hours per night
```

**Analysis Value:** Confounding variables for cancer risk (smoking especially critical)

---

### Work History Aggregates (20 fields)

```sql
-- Career Summary
total_fire_departments  INT DEFAULT 0,         -- Count from nfr_work_history
total_years_service     DECIMAL(5,2),          -- Sum of (end_date - start_date) across all depts
currently_active        BOOLEAN,               -- From nfr_user_profile.current_work_status
year_first_firefighter  INT,                   -- Earliest start_date year

-- Employment Type Breakdown (from nfr_job_titles)
years_career            DECIMAL(5,2) DEFAULT 0,
years_volunteer         DECIMAL(5,2) DEFAULT 0,
years_paid_on_call      DECIMAL(5,2) DEFAULT 0,
years_seasonal          DECIMAL(5,2) DEFAULT 0,
years_wildland          DECIMAL(5,2) DEFAULT 0,
years_military          DECIMAL(5,2) DEFAULT 0,

-- Job Roles (highest rank achieved)
highest_rank            VARCHAR(100),          -- Chief, Captain, Lieutenant, Firefighter, etc.
held_leadership_role    BOOLEAN DEFAULT FALSE, -- Battalion Chief or higher

-- Incident Response Summary
responded_to_incidents  BOOLEAN DEFAULT FALSE, -- Ever responded to fires/emergencies
total_incident_years    DECIMAL(5,2),          -- Years in incident-response positions

-- Geographic Work History
num_states_worked       INT DEFAULT 0,         -- Distinct states from nfr_work_history
primary_work_state      VARCHAR(2),            -- State with most years of service

-- Employment Gaps
num_employment_gaps     INT DEFAULT 0,         -- Gaps > 1 year between departments
longest_gap_months      INT,                   -- Longest time between fire service jobs

-- Current Status
years_since_retirement  INT,                   -- NULL if still active, years since last end_date
```

**Analysis Value:** PRIMARY EXPOSURE VARIABLES - correlate service duration/type with cancer incidence

---

### Incident Exposure Metrics (40 fields)

**Critical Design Note:** These are the key exposure variables for cancer correlation analysis.

#### Structure Fire Exposure (6 fields)
```sql
-- Lifetime incident counts (sum across all jobs from nfr_incident_frequency)
total_structure_residential_fires   INT DEFAULT 0,
total_structure_commercial_fires    INT DEFAULT 0,
total_structure_fires               INT DEFAULT 0,  -- Sum of residential + commercial
avg_structure_fires_per_year        DECIMAL(6,2),   -- total / years_incident_response

-- Peak exposure years (highest annual frequency reported)
peak_structure_fire_year_category   VARCHAR(20),    -- e.g., "21-50", "more_than_50"
years_high_structure_exposure       INT DEFAULT 0,  -- Count of years with >20 fires/year
```

#### Vehicle Fire Exposure (3 fields)
```sql
total_vehicle_fires                 INT DEFAULT 0,
avg_vehicle_fires_per_year          DECIMAL(6,2),
years_high_vehicle_exposure         INT DEFAULT 0,  -- Count of years with >20/year
```

#### Wildland Fire Exposure (4 fields)
```sql
total_wildland_fires                INT DEFAULT 0,
avg_wildland_fires_per_year         DECIMAL(6,2),
years_wildland_service              DECIMAL(5,2),   -- Years in wildland firefighter roles
years_high_wildland_exposure        INT DEFAULT 0,
```

#### Hazmat Exposure (3 fields)
```sql
total_hazmat_incidents              INT DEFAULT 0,
avg_hazmat_per_year                 DECIMAL(6,2),
years_high_hazmat_exposure          INT DEFAULT 0,
```

#### Training Fire Exposure (3 fields)
```sql
total_training_fires                INT DEFAULT 0,  -- Live fire training
avg_training_fires_per_year         DECIMAL(6,2),
years_regular_training              INT DEFAULT 0,  -- Years with 6+ training fires/year
```

#### Other Incident Types (12 fields)
```sql
total_rubbish_fires                 INT DEFAULT 0,
total_medical_ems_calls             INT DEFAULT 0,
total_technical_rescue              INT DEFAULT 0,
total_arff_incidents                INT DEFAULT 0,  -- Aircraft rescue
total_marine_incidents              INT DEFAULT 0,
total_prescribed_burns              INT DEFAULT 0,
total_other_incidents               INT DEFAULT 0,

-- Aggregate metrics
total_all_incidents                 INT DEFAULT 0,   -- Sum of ALL incident types
avg_all_incidents_per_year          DECIMAL(7,2),
incident_diversity_score            INT DEFAULT 0,   -- Count of incident types with >0 exposure
primary_incident_type               VARCHAR(50),     -- Incident type with highest count
```

#### Major Incident Exposure (6 fields)
```sql
-- From nfr_major_incidents table
num_major_incidents                 INT DEFAULT 0,   -- Total count
num_traumatic_incidents             INT DEFAULT 0,   -- Death/injury witnessed
num_structural_collapse             INT DEFAULT 0,
num_mass_casualty                   INT DEFAULT 0,
years_since_last_major_incident     INT,
ptsd_symptoms_reported              BOOLEAN,         -- From questionnaire
```

#### Non-Fire Occupational Hazards (3 fields)
```sql
-- From nfr_other_employment
had_other_hazardous_jobs            BOOLEAN DEFAULT FALSE,
years_other_hazardous_work          DECIMAL(5,2) DEFAULT 0,
other_carcinogen_exposure           TEXT,            -- List of known carcinogens from other jobs
```

**Analysis Value:** These are the PRIMARY INDEPENDENT VARIABLES for cancer risk modeling

---

### Health Outcomes (25 fields)

#### Cancer Diagnoses (from nfr_cancer_diagnoses)
```sql
has_cancer_diagnosis                BOOLEAN DEFAULT FALSE,
num_cancer_diagnoses                INT DEFAULT 0,          -- Count of distinct cancers
age_first_cancer_diagnosis          INT,                    -- Age at earliest diagnosis
years_service_before_first_cancer   DECIMAL(5,2),          -- Service time before first cancer
earliest_cancer_type                VARCHAR(100),           -- Cancer type of first diagnosis
cancer_types_list                   TEXT,                   -- JSON array of all cancer types

-- Specific Cancer Types (binary flags for correlation analysis)
has_lung_cancer                     BOOLEAN DEFAULT FALSE,
has_colorectal_cancer               BOOLEAN DEFAULT FALSE,
has_prostate_cancer                 BOOLEAN DEFAULT FALSE,
has_breast_cancer                   BOOLEAN DEFAULT FALSE,
has_bladder_cancer                  BOOLEAN DEFAULT FALSE,
has_kidney_cancer                   BOOLEAN DEFAULT FALSE,
has_skin_cancer                     BOOLEAN DEFAULT FALSE,
has_brain_cancer                    BOOLEAN DEFAULT FALSE,
has_testicular_cancer               BOOLEAN DEFAULT FALSE,
has_leukemia                        BOOLEAN DEFAULT FALSE,
has_lymphoma                        BOOLEAN DEFAULT FALSE,
has_mesothelioma                    BOOLEAN DEFAULT FALSE,
has_thyroid_cancer                  BOOLEAN DEFAULT FALSE,
has_esophageal_cancer               BOOLEAN DEFAULT FALSE,
```

#### Family Cancer History (from nfr_family_cancer_history)
```sql
family_cancer_count                 INT DEFAULT 0,          -- Total family members with cancer
family_cancer_parents               INT DEFAULT 0,
family_cancer_siblings              INT DEFAULT 0,
family_cancer_children              INT DEFAULT 0,
family_cancer_score                 INT DEFAULT 0,          -- Weighted score (parents=2, siblings=1, children=1)
```

**Analysis Value:** These are the PRIMARY DEPENDENT VARIABLES for cancer risk modeling

---

### Temporal Tracking (6 fields)

```sql
enrollment_date                     DATE,                   -- When they first registered
profile_completed_date              DATE,
questionnaire_completed_date        DATE,
last_survey_update_date             DATE,
years_since_enrollment              DECIMAL(5,2),
data_completeness_percentage        DECIMAL(5,2),           -- % of required fields filled
```

**Analysis Value:** Enables longitudinal analysis and cohort stratification

---

### Data Quality Flags (5 fields)

```sql
data_quality_score                  INT DEFAULT 0,          -- 0-100 based on completeness
has_complete_work_history           BOOLEAN DEFAULT FALSE,
has_complete_incident_data          BOOLEAN DEFAULT FALSE,
has_complete_health_data            BOOLEAN DEFAULT FALSE,
exclude_from_analysis               BOOLEAN DEFAULT FALSE,  -- Manual flag for invalid data
```

**Analysis Value:** Filter low-quality records from statistical models

---

## Total Field Count: 169 Fields

- System: 5
- Demographics: 15
- Health Metrics: 10
- Work History: 20
- Incident Exposure: 40
- Health Outcomes: 25
- Family History: 5
- Temporal: 6
- Data Quality: 5

---

## Data Population Strategy

### Initial Population
```sql
-- Create population script: populate_correlation_table.php
-- For each uid in nfr_user_profile:
--   1. Insert/update base demographics
--   2. Aggregate work_history → calculate totals
--   3. Aggregate incident_frequency → sum/average by type
--   4. Aggregate cancer_diagnoses → flags and counts
--   5. Calculate derived metrics (BMI, years of service, etc.)
--   6. Calculate data quality score
```

### Update Strategy
- **Trigger-based:** Update correlation row when source tables change (nfr_questionnaire, nfr_cancer_diagnoses updates)
- **Scheduled:** Nightly cron job to recalculate aggregates for all users
- **On-demand:** Admin interface to rebuild specific user's correlation data

### Performance Considerations
- **Indexed fields:** uid, has_cancer_diagnosis, total_years_service, age_first_cancer_diagnosis
- **Partitioning:** Consider partitioning by enrollment_year for large datasets (>100k records)
- **Materialized view option:** Could implement as materialized view instead of table for real-time accuracy

---

## Statistical Analysis Use Cases

### 1. **Cancer Risk Correlation**
```sql
-- Example: Correlation between structure fire exposure and lung cancer
SELECT 
  total_structure_fires,
  avg_structure_fires_per_year,
  years_high_structure_exposure,
  has_lung_cancer,
  age_first_cancer_diagnosis,
  smoking_status
FROM nfr_correlation_analysis
WHERE data_quality_score >= 70
  AND total_years_service >= 5;
```

### 2. **Dose-Response Analysis**
```sql
-- Stratify cancer incidence by exposure quartiles
SELECT 
  CASE 
    WHEN total_structure_fires < 50 THEN 'Q1: Low'
    WHEN total_structure_fires < 150 THEN 'Q2: Medium'
    WHEN total_structure_fires < 300 THEN 'Q3: High'
    ELSE 'Q4: Very High'
  END AS exposure_quartile,
  COUNT(*) as n,
  SUM(has_cancer_diagnosis) as cancer_cases,
  ROUND(SUM(has_cancer_diagnosis) / COUNT(*) * 100, 2) as cancer_rate_percent
FROM nfr_correlation_analysis
WHERE exclude_from_analysis = FALSE
GROUP BY exposure_quartile;
```

### 3. **Multivariate Regression**
Export to R/Python for logistic regression:
```sql
SELECT 
  -- Outcome variable
  has_lung_cancer,
  
  -- Primary exposure variables
  total_structure_fires,
  years_high_structure_exposure,
  total_hazmat_incidents,
  
  -- Confounders
  age_at_enrollment,
  smoking_status,
  smoking_years,
  bmi_category,
  
  -- Effect modifiers
  years_career,
  years_volunteer
FROM nfr_correlation_analysis
WHERE data_completeness_percentage >= 80;
```

### 4. **Longitudinal Tracking**
```sql
-- Track cancer incidence over time since enrollment
SELECT 
  years_since_enrollment,
  COUNT(*) as total_participants,
  SUM(CASE WHEN has_cancer_diagnosis THEN 1 ELSE 0 END) as new_cancers,
  SUM(num_cancer_diagnoses) as total_diagnoses
FROM nfr_correlation_analysis
GROUP BY years_since_enrollment
ORDER BY years_since_enrollment;
```

---

## Implementation Phases

### Phase 1: Schema Creation (Week 1)
- [ ] Create table in nfr.install hook_schema()
- [ ] Add update hook to create table in existing databases
- [ ] Create indexes on key fields
- [ ] Test schema on development database

### Phase 2: Population Script (Week 2)
- [ ] Build PHP class: NFRCorrelationAnalysis.php
- [ ] Implement aggregation logic for each data source
- [ ] Create drush command: `drush nfr:populate-correlation`
- [ ] Test with small subset of users (10-20)
- [ ] Validate calculations against source data

### Phase 3: Maintenance & Updates (Week 3)
- [ ] Implement trigger functions for auto-updates
- [ ] Create nightly cron job
- [ ] Build admin UI to view/rebuild correlation data
- [ ] Add logging for data quality issues

### Phase 4: Analysis Tools (Week 4)
- [ ] Create export functionality (CSV, Excel, R data frame)
- [ ] Build basic statistical reports
- [ ] Document analysis workflows
- [ ] Create example queries for researchers

---

## Data Quality Considerations

### Missing Data Handling
- **Demographics:** Required for analysis - flag records with missing age/sex
- **Work History:** Some users may have incomplete history - calculate completeness score
- **Incident Data:** Frequency ranges (e.g., "21-50") need midpoint estimation for totals
- **Health Outcomes:** Self-reported cancer data may have recall bias

### Calculation Rules

#### Total Years of Service
```
For each job in nfr_job_titles:
  If currently_employed = TRUE:
    years = (TODAY - start_date) / 365.25
  Else:
    years = (end_date - start_date) / 365.25
  
total_years_service = SUM(years across all jobs)
```

#### Incident Totals from Frequency Ranges
```
Frequency mapping (use midpoint):
- "never" → 0
- "less_than_1" → 0.5 per year
- "1_5" → 3 per year
- "6_20" → 13 per year
- "21_50" → 35.5 per year
- "more_than_50" → 60 per year (conservative estimate)

For each incident type in nfr_incident_frequency:
  annual_estimate = midpoint * years_in_position
  total += annual_estimate
```

#### BMI Calculation
```
BMI = (weight_pounds / (height_inches²)) × 703

Categories:
- < 18.5: Underweight
- 18.5-24.9: Normal
- 25.0-29.9: Overweight
- ≥ 30.0: Obese
```

---

## Privacy & Ethics Considerations

### De-identification for Research
- Participant_id can be used instead of names
- Date of birth → Age at enrollment (reduces re-identification risk)
- Geographic granularity: State level only (not city/address)
- Free-text fields excluded (race_other, incident descriptions)

### Consent Requirements
- Users must have `research_participation = TRUE` in nfr_consent
- Add `exclude_from_analysis` flag for users who withdraw consent
- Document data usage in consent forms

### Data Access Controls
- Correlation table only accessible to:
  - System administrators
  - Approved researchers (separate database user with SELECT-only privileges)
  - Export requires audit logging

---

## Success Metrics

### Data Completeness Targets
- ≥80% of enrolled users have correlation record created
- ≥70% of correlation records have data_quality_score ≥ 60
- ≥50% of users with cancer diagnosis have complete exposure data

### Analysis Readiness
- Can export clean dataset (n ≥ 200) for statistical analysis
- Can stratify by key variables (age, service years, incident exposure)
- Can adjust for confounders (smoking, BMI)

### Performance Targets
- Correlation record rebuilds in < 5 seconds per user
- Full database rebuild (all users) in < 30 minutes
- Query performance: Analytical queries return in < 2 seconds

---

## Future Enhancements

### Version 2.0 Features
- **Time-series support:** Multiple snapshots per user (yearly) for true longitudinal analysis
- **Genetic markers:** If genetic testing added, include fields for cancer susceptibility genes
- **Exposure intensity:** Not just counts, but also duration per incident (hours at scene)
- **PPE compliance:** Track self-reported protective equipment usage
- **Department characteristics:** Merge with department-level data (size, region, response volume)

### Advanced Analytics
- **Machine learning:** Cancer risk prediction models
- **Survival analysis:** Time-to-cancer diagnosis (Cox regression)
- **Clustering:** Identify high-risk occupational profiles
- **Geographic analysis:** Regional cancer rate differences

---

## References

- [NFR Database Field Audit](../../sites/forseti/NFR_DATABASE_FIELD_AUDIT.md) - Source table documentation
- NIOSH Firefighter Cancer Registry - Similar data collection efforts
- CDC OMB No. 0920-1348 - Official data collection form structure
- Epidemiological study designs for occupational cancer research

---

## Correlation Analysis Results Cache Table

### Purpose
Pre-computed correlation results for all possible variable pairs to enable instant report generation without re-calculating statistics on every request. This table stores the results of correlation analyses run across the NFR dataset.

### Table Name: `nfr_correlation_results`

### Design Rationale
1. **Performance**: Computing correlations on-demand for 100+ variables = 5,000+ pairs is expensive
2. **Consistency**: All users see same results for given dataset snapshot
3. **Versioning**: Track results over time as dataset grows
4. **Auditability**: Record when/how each correlation was calculated
5. **Caching**: Results updated periodically (nightly/weekly) rather than on-demand

### Schema Design

```sql
CREATE TABLE nfr_correlation_results (
  -- Primary identification
  id INT AUTO_INCREMENT PRIMARY KEY,
  variable_x VARCHAR(100) NOT NULL COMMENT 'Independent variable field name',
  variable_y VARCHAR(100) NOT NULL COMMENT 'Dependent variable field name',
  
  -- Analysis parameters
  correlation_method VARCHAR(20) NOT NULL COMMENT 'pearson or spearman',
  dataset_version VARCHAR(50) NOT NULL COMMENT 'Date/version of source data',
  
  -- Filter criteria used
  min_quality_score INT DEFAULT NULL COMMENT 'Minimum data quality filter applied',
  sex_filter VARCHAR(1) DEFAULT NULL COMMENT 'M, F, or NULL for all',
  cancer_filter TINYINT DEFAULT NULL COMMENT '1=cancer only, 0=no cancer, NULL=all',
  
  -- Statistical results
  correlation_coefficient DECIMAL(10,8) NOT NULL COMMENT 'r value from -1 to +1',
  p_value DECIMAL(10,8) DEFAULT NULL COMMENT 'Statistical significance',
  sample_size INT NOT NULL COMMENT 'Number of records analyzed',
  
  -- Summary statistics for X variable
  x_mean DECIMAL(15,4) DEFAULT NULL,
  x_median DECIMAL(15,4) DEFAULT NULL,
  x_stddev DECIMAL(15,4) DEFAULT NULL,
  x_min DECIMAL(15,4) DEFAULT NULL,
  x_max DECIMAL(15,4) DEFAULT NULL,
  
  -- Summary statistics for Y variable
  y_mean DECIMAL(15,4) DEFAULT NULL,
  y_median DECIMAL(15,4) DEFAULT NULL,
  y_stddev DECIMAL(15,4) DEFAULT NULL,
  y_min DECIMAL(15,4) DEFAULT NULL,
  y_max DECIMAL(15,4) DEFAULT NULL,
  
  -- Metadata
  calculation_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When calculated',
  calculation_duration_ms INT DEFAULT NULL COMMENT 'Processing time in milliseconds',
  data_quality_notes TEXT DEFAULT NULL COMMENT 'Warnings or issues during calculation',
  
  -- Indexes for performance
  UNIQUE KEY unique_correlation (variable_x, variable_y, correlation_method, dataset_version, 
                                  COALESCE(min_quality_score, 0), 
                                  COALESCE(sex_filter, ''), 
                                  COALESCE(cancer_filter, -1)),
  KEY idx_variable_x (variable_x),
  KEY idx_variable_y (variable_y),
  KEY idx_correlation_strength (ABS(correlation_coefficient) DESC),
  KEY idx_significance (p_value ASC),
  KEY idx_dataset_version (dataset_version),
  KEY idx_timestamp (calculation_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Pre-computed correlation analysis results cache';
```

### Field Specifications

#### Primary Identification (3 fields)
- **id**: Auto-incrementing primary key
- **variable_x**: Field name from nfr_correlation_analysis (e.g., 'total_years_service')
- **variable_y**: Field name from nfr_correlation_analysis (e.g., 'has_cancer_diagnosis')

#### Analysis Parameters (3 fields)
- **correlation_method**: 'pearson' or 'spearman'
- **dataset_version**: Format: 'YYYY-MM-DD' or 'YYYY-MM-DD_HH:MM' for dataset snapshot identification
- **min_quality_score**: Quality filter applied (0-100), NULL if no filter

#### Filter Criteria (2 fields)
- **sex_filter**: 'M', 'F', or NULL for all participants
- **cancer_filter**: 1 (cancer only), 0 (no cancer), NULL (all)

#### Statistical Results (3 fields)
- **correlation_coefficient**: Pearson r or Spearman rho (-1.0 to +1.0)
- **p_value**: Statistical significance (0.0 to 1.0)
- **sample_size**: Number of valid records used in calculation

#### Summary Statistics X (5 fields)
Statistics for the independent variable:
- **x_mean**: Arithmetic mean
- **x_median**: 50th percentile
- **x_stddev**: Standard deviation
- **x_min**: Minimum value
- **x_max**: Maximum value

#### Summary Statistics Y (5 fields)
Statistics for the dependent variable:
- **y_mean**: Arithmetic mean
- **y_median**: 50th percentile
- **y_stddev**: Standard deviation
- **y_min**: Minimum value
- **y_max**: Maximum value

#### Metadata (3 fields)
- **calculation_timestamp**: When result was computed
- **calculation_duration_ms**: Processing time for performance monitoring
- **data_quality_notes**: JSON or text notes about warnings, outliers, missing data issues

### Data Population Strategy

#### Full Matrix Population
For 100 available variables:
- **Total pairs**: 100 × 99 / 2 = 4,950 unique pairs (excluding self-correlation)
- **Methods**: 2 (Pearson + Spearman) = 9,900 base results
- **Filters**: 3 quality levels (70, 80, 90) × 3 sex options (All, M, F) × 3 cancer options (All, Yes, No) = 27 filter combinations
- **Total rows**: 9,900 × 27 = 267,300 pre-computed results

#### Incremental Update Strategy
1. **Nightly batch job**: Recalculate all correlations for dataset_version = today's date
2. **Invalidation**: Drop old versions older than 30 days to prevent table bloat
3. **Priority queue**: Calculate most-requested pairs first
4. **Parallel processing**: Split variable pairs across multiple workers

#### Population Command
```bash
# Full rebuild of all correlations
drush nfr:correlation-cache-rebuild

# Update only changed data
drush nfr:correlation-cache-update

# Rebuild specific variable pair
drush nfr:correlation-cache-rebuild-pair variable_x variable_y
```

### Query Patterns

#### Get Cached Result
```sql
SELECT 
  correlation_coefficient,
  p_value,
  sample_size,
  x_mean, x_stddev,
  y_mean, y_stddev
FROM nfr_correlation_results
WHERE variable_x = 'total_years_service'
  AND variable_y = 'has_cancer_diagnosis'
  AND correlation_method = 'spearman'
  AND dataset_version = '2026-01-27'
  AND min_quality_score = 70
  AND sex_filter IS NULL
  AND cancer_filter IS NULL
ORDER BY calculation_timestamp DESC
LIMIT 1;
```

#### Find Strongest Correlations
```sql
SELECT 
  variable_x,
  variable_y,
  correlation_coefficient,
  p_value,
  sample_size
FROM nfr_correlation_results
WHERE dataset_version = '2026-01-27'
  AND correlation_method = 'pearson'
  AND p_value < 0.05
  AND ABS(correlation_coefficient) >= 0.5
ORDER BY ABS(correlation_coefficient) DESC
LIMIT 20;
```

#### Find Significant Cancer Correlations
```sql
SELECT 
  variable_x,
  correlation_coefficient,
  p_value,
  sample_size
FROM nfr_correlation_results
WHERE variable_y = 'has_cancer_diagnosis'
  AND correlation_method = 'spearman'
  AND dataset_version = '2026-01-27'
  AND p_value < 0.01
  AND correlation_coefficient > 0
ORDER BY correlation_coefficient DESC;
```

### Performance Considerations

#### Storage Requirements
- **Row size**: ~250 bytes per record
- **Total size**: 267,300 rows × 250 bytes = ~67 MB for full cache
- **With 30-day history**: 67 MB × 30 = ~2 GB
- **Indexes**: Additional ~500 MB

#### Query Performance
- **Lookup by variable pair**: <5ms (indexed)
- **Sort by correlation strength**: <10ms (indexed on ABS(correlation_coefficient))
- **Filter by significance**: <10ms (indexed on p_value)

#### Update Performance
- **Single correlation**: ~10-50ms depending on sample size
- **Full rebuild (267k rows)**: ~2-4 hours with parallel processing
- **Incremental update**: ~15-30 minutes for changed variables only

### Data Quality Tracking

#### Quality Metrics Stored
- **sample_size**: Detect if too few records for meaningful analysis
- **calculation_duration_ms**: Identify slow calculations
- **data_quality_notes**: JSON format:
```json
{
  "warnings": [
    "High percentage of NULL values (35%)",
    "Outliers detected beyond 3 standard deviations",
    "Non-normal distribution may affect Pearson correlation"
  ],
  "excluded_records": 15,
  "exclusion_reasons": {
    "null_values": 10,
    "quality_filter": 5
  }
}
```

### Integration with Correlation Analysis Form

#### Form Workflow with Cache
1. User selects variables and filters
2. System checks cache for matching result:
   - **Cache hit**: Display result instantly (< 100ms)
   - **Cache miss**: Calculate on-demand, display, queue for caching
3. Display cache timestamp to user
4. Allow "Force Recalculate" option to bypass cache

#### Cache Key Generation
```php
$cache_key = sprintf(
  '%s_%s_%s_%s_%s_%s_%s',
  $variable_x,
  $variable_y,
  $correlation_method,
  $dataset_version,
  $min_quality_score ?? 'null',
  $sex_filter ?? 'null',
  $cancer_filter ?? 'null'
);
```

### Maintenance Operations

#### Cleanup Old Versions
```sql
-- Delete results older than 30 days
DELETE FROM nfr_correlation_results
WHERE calculation_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

#### Recompute Outdated Results
```sql
-- Find results based on old dataset version
SELECT DISTINCT variable_x, variable_y
FROM nfr_correlation_results
WHERE dataset_version < '2026-01-27'
GROUP BY variable_x, variable_y;
```

#### Identify Missing Pairs
```sql
-- Find variable pairs not yet calculated
SELECT v1.variable_name AS variable_x, v2.variable_name AS variable_y
FROM nfr_available_variables v1
CROSS JOIN nfr_available_variables v2
WHERE v1.variable_name < v2.variable_name -- Prevent duplicates
  AND NOT EXISTS (
    SELECT 1 FROM nfr_correlation_results r
    WHERE r.variable_x = v1.variable_name
      AND r.variable_y = v2.variable_name
      AND r.dataset_version = '2026-01-27'
  );
```

### Benefits

1. **Instant Results**: Sub-second response time vs. 1-5 seconds per calculation
2. **Consistent Data**: Everyone sees same results for snapshot
3. **Historical Tracking**: Compare correlations over time as dataset grows
4. **Research Reproducibility**: Exact results preserved with version tracking
5. **Reduced Server Load**: No repeated calculations for same variable pairs
6. **Exploratory Analysis**: Users can browse pre-computed results

### Future Enhancements

1. **Confidence Intervals**: Store 95% CI for correlation coefficients
2. **Scatter Plot Data**: Cache binned data points for visualization
3. **Partial Correlations**: Control for confounding variables
4. **Subgroup Analysis**: Cache results for age groups, regions, etc.
5. **Time Series**: Track how correlations change as new data arrives
6. **API Access**: RESTful endpoint for external research tools

---

## Cluster Analysis Results Cache Table

### Purpose
Pre-computed k-means clustering results for common variable combinations to enable instant cluster visualization and comparison without re-running computationally expensive clustering algorithms.

### Table Name: `nfr_cluster_results`

### Design Rationale
1. **Computational Cost**: K-means with 300+ records, 10-20 variables, multiple k values, and iterations is CPU-intensive
2. **Elbow Method**: Users need results for k=2 through k=10 to find optimal cluster count
3. **Comparison**: Enable side-by-side comparison of different variable sets
4. **Reproducibility**: Consistent results since k-means uses random initialization
5. **Variable Sets**: Pre-compute common meaningful variable combinations (exposure profile, health profile, etc.)

### Schema Design

```sql
CREATE TABLE nfr_cluster_results (
  -- Primary identification
  id INT AUTO_INCREMENT PRIMARY KEY,
  variable_set_id VARCHAR(100) NOT NULL COMMENT 'Identifier for this variable combination',
  variable_set_hash VARCHAR(64) NOT NULL COMMENT 'SHA-256 hash of sorted variable list',
  variables_json TEXT NOT NULL COMMENT 'JSON array of variable names included',
  
  -- Clustering parameters
  k_value INT NOT NULL COMMENT 'Number of clusters (2-10)',
  max_iterations INT DEFAULT 100 COMMENT 'Maximum iterations allowed',
  dataset_version VARCHAR(50) NOT NULL COMMENT 'Date/version of source data',
  
  -- Filter criteria used
  min_quality_score INT DEFAULT NULL COMMENT 'Minimum data quality filter applied',
  sex_filter VARCHAR(1) DEFAULT NULL COMMENT 'M, F, or NULL for all',
  cancer_filter TINYINT DEFAULT NULL COMMENT '1=cancer only, 0=no cancer, NULL=all',
  
  -- Clustering results
  wcss DECIMAL(15,4) NOT NULL COMMENT 'Within-cluster sum of squares (quality metric)',
  iterations_to_converge INT NOT NULL COMMENT 'Actual iterations needed',
  converged TINYINT DEFAULT 1 COMMENT '1=converged, 0=stopped at max iterations',
  sample_size INT NOT NULL COMMENT 'Number of records clustered',
  
  -- Cluster distribution
  cluster_sizes_json TEXT NOT NULL COMMENT 'JSON array of cluster sizes [15, 42, 38, ...]',
  cluster_percentages_json TEXT NOT NULL COMMENT 'JSON array of percentages [15.8, 44.2, 40.0, ...]',
  
  -- Centroids (in original scale)
  centroids_json LONGTEXT NOT NULL COMMENT 'JSON array of centroid objects with variable:value pairs',
  
  -- Cluster assignments
  assignments_json LONGTEXT NOT NULL COMMENT 'JSON map of uid:cluster_id for all records',
  
  -- Cancer distribution by cluster
  cancer_by_cluster_json TEXT DEFAULT NULL COMMENT 'JSON map of cluster_id:cancer_count',
  
  -- Metadata
  calculation_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When calculated',
  calculation_duration_ms INT DEFAULT NULL COMMENT 'Processing time in milliseconds',
  data_quality_notes TEXT DEFAULT NULL COMMENT 'Warnings or issues during calculation',
  random_seed INT DEFAULT NULL COMMENT 'Random seed used for reproducibility',
  
  -- Indexes for performance
  UNIQUE KEY unique_clustering (variable_set_hash, k_value, dataset_version,
                                 COALESCE(min_quality_score, 0),
                                 COALESCE(sex_filter, ''),
                                 COALESCE(cancer_filter, -1)),
  KEY idx_variable_set_id (variable_set_id),
  KEY idx_k_value (k_value),
  KEY idx_wcss (wcss ASC),
  KEY idx_dataset_version (dataset_version),
  KEY idx_timestamp (calculation_timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Pre-computed k-means clustering results cache';
```

### Field Specifications

#### Primary Identification (4 fields)
- **id**: Auto-incrementing primary key
- **variable_set_id**: Human-readable identifier (e.g., 'exposure_profile', 'health_outcomes', 'custom_20260127_123456')
- **variable_set_hash**: SHA-256 hash of alphabetically sorted variable names for duplicate detection
- **variables_json**: JSON array: `["total_years_service", "fires_attended", "has_cancer_diagnosis"]`

#### Clustering Parameters (4 fields)
- **k_value**: Number of clusters (2-10)
- **max_iterations**: Maximum iterations allowed (typically 100)
- **dataset_version**: Format 'YYYY-MM-DD' for snapshot identification
- **random_seed**: Integer seed for reproducible initialization (NULL = random)

#### Filter Criteria (3 fields)
- **min_quality_score**: Quality filter applied (0-100), NULL if no filter
- **sex_filter**: 'M', 'F', or NULL for all participants
- **cancer_filter**: 1 (cancer only), 0 (no cancer), NULL (all)

#### Clustering Results (4 fields)
- **wcss**: Within-cluster sum of squares (lower = tighter clusters)
- **iterations_to_converge**: Actual iterations needed (1 to max_iterations)
- **converged**: 1 if algorithm converged, 0 if hit max iterations
- **sample_size**: Number of valid records used

#### Cluster Distribution (2 fields)
- **cluster_sizes_json**: `[15, 42, 38, 27, 13]` (counts)
- **cluster_percentages_json**: `[11.1, 31.1, 28.1, 20.0, 9.6]` (percentages)

#### Centroids (1 field)
- **centroids_json**: Array of centroid objects in original scale:
```json
[
  {"total_years_service": 18.5, "fires_attended": 245, "has_cancer_diagnosis": 0.23},
  {"total_years_service": 8.2, "fires_attended": 78, "has_cancer_diagnosis": 0.15}
]
```

#### Cluster Assignments (1 field)
- **assignments_json**: Map of user IDs to cluster IDs:
```json
{
  "42": 0,
  "87": 1,
  "123": 0,
  "456": 2
}
```

#### Cancer Distribution (1 field)
- **cancer_by_cluster_json**: Cancer case counts per cluster:
```json
{
  "0": 5,
  "1": 12,
  "2": 8
}
```

#### Metadata (4 fields)
- **calculation_timestamp**: When result was computed
- **calculation_duration_ms**: Processing time
- **data_quality_notes**: JSON notes about warnings/issues
- **random_seed**: Seed used (enables exact reproducibility)

### Data Population Strategy

#### Pre-defined Variable Sets
1. **exposure_profile**: Firefighting exposure variables (years, fires, hazmat, smoke)
2. **health_outcomes**: Health metrics (cancer, respiratory, cardiovascular, mental health)
3. **demographics**: Age, sex, ethnicity, education
4. **work_characteristics**: Employment type, department size, training, certifications
5. **incident_patterns**: Incident types, injuries, equipment failures
6. **comprehensive**: All 100+ variables (computationally expensive)

#### Full Population Matrix
For each pre-defined variable set:
- **K values**: 9 (k=2 through k=10) for elbow curve analysis
- **Filters**: 27 combinations (3 quality × 3 sex × 3 cancer)
- **Seeds**: 3 different random seeds per combination for stability testing
- **Total per set**: 9 × 27 × 3 = 729 results
- **Total for 5 sets**: 729 × 5 = 3,645 pre-computed results

#### Incremental Strategy
1. **Priority 1**: Common presets (exposure_profile, health_outcomes) with default filters
2. **Priority 2**: Comprehensive set for all k values
3. **Priority 3**: Custom user-requested variable sets (queued on-demand)
4. **Nightly job**: Recalculate all with new dataset_version
5. **Stability check**: Compare results across different random seeds

#### Population Command
```bash
# Full rebuild of all clustering results
drush nfr:cluster-cache-rebuild

# Rebuild specific variable set
drush nfr:cluster-cache-rebuild-set exposure_profile

# Generate elbow curve data (k=2 to k=10)
drush nfr:cluster-cache-elbow exposure_profile

# Custom variable set
drush nfr:cluster-cache-custom total_years_service,fires_attended,has_cancer_diagnosis
```

### Query Patterns

#### Get Cached Clustering Result
```sql
SELECT 
  k_value,
  wcss,
  iterations_to_converge,
  sample_size,
  cluster_sizes_json,
  centroids_json,
  assignments_json
FROM nfr_cluster_results
WHERE variable_set_id = 'exposure_profile'
  AND k_value = 5
  AND dataset_version = '2026-01-27'
  AND min_quality_score = 70
  AND sex_filter IS NULL
  AND cancer_filter IS NULL
ORDER BY calculation_timestamp DESC
LIMIT 1;
```

#### Get Elbow Curve Data
```sql
SELECT 
  k_value,
  wcss,
  sample_size
FROM nfr_cluster_results
WHERE variable_set_id = 'exposure_profile'
  AND dataset_version = '2026-01-27'
  AND min_quality_score IS NULL
ORDER BY k_value ASC;
```

#### Find Optimal K (Elbow Detection)
```sql
-- Calculate rate of WCSS decrease
SELECT 
  k_value,
  wcss,
  LAG(wcss) OVER (ORDER BY k_value) AS prev_wcss,
  wcss - LAG(wcss) OVER (ORDER BY k_value) AS wcss_decrease,
  (wcss - LAG(wcss) OVER (ORDER BY k_value)) / LAG(wcss) OVER (ORDER BY k_value) * 100 AS pct_decrease
FROM nfr_cluster_results
WHERE variable_set_id = 'exposure_profile'
  AND dataset_version = '2026-01-27'
ORDER BY k_value ASC;
```

#### Compare Cancer Distribution Across Clusters
```sql
SELECT 
  k_value,
  cluster_sizes_json,
  cancer_by_cluster_json
FROM nfr_cluster_results
WHERE variable_set_id = 'health_outcomes'
  AND dataset_version = '2026-01-27'
  AND k_value IN (3, 4, 5)
ORDER BY k_value ASC;
```

### Performance Considerations

#### Storage Requirements
- **Row size**: ~5-20 KB per record (varies with sample size and k)
- **Assignments JSON**: ~15 KB for 300 records
- **Centroids JSON**: ~500 bytes per cluster
- **Full cache**: 3,645 results × 10 KB avg = ~35 MB
- **With 30-day history**: 35 MB × 30 = ~1 GB

#### Query Performance
- **Lookup by variable set**: <10ms (indexed)
- **Elbow curve retrieval**: <20ms (9 rows for k=2-10)
- **Assignment lookup**: <50ms (parse JSON for specific uid)

#### Computation Performance
- **Single clustering**: 100ms - 5 seconds (depends on k, variables, sample size)
- **Full elbow curve (k=2-10)**: 1-45 seconds
- **Full preset rebuild**: ~10-30 minutes
- **All variable sets**: ~2-3 hours

### Data Quality Tracking

#### Quality Metrics Stored
```json
{
  "warnings": [
    "Empty cluster formed during iteration 15, reinitialized",
    "Algorithm reached max iterations without full convergence",
    "High WCSS indicates poor cluster separation"
  ],
  "normalization": {
    "method": "z-score",
    "variables_normalized": 8
  },
  "cluster_stability": {
    "different_seeds_compared": 3,
    "assignment_consistency": 0.92
  },
  "excluded_records": 12,
  "exclusion_reasons": {
    "null_values": 8,
    "quality_filter": 4
  }
}
```

### Integration with Cluster Analysis Form

#### Form Workflow with Cache
1. User selects variables and k value
2. Generate variable_set_hash from sorted variable list
3. Check cache for matching result:
   - **Cache hit**: Display instantly (< 200ms)
   - **Cache miss**: Calculate, display, save to cache
4. Show elbow curve if full k=2-10 range is cached
5. Display cache timestamp and allow "Force Recalculate"

#### Variable Set Hash Generation
```php
$variables = $_POST['variables'];
sort($variables); // Alphabetical order
$variable_set_hash = hash('sha256', implode('|', $variables));
```

### Maintenance Operations

#### Cleanup Old Versions
```sql
-- Delete results older than 30 days
DELETE FROM nfr_cluster_results
WHERE calculation_timestamp < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

#### Find Unstable Clusterings
```sql
-- Find variable sets with high WCSS variation across seeds
SELECT 
  variable_set_id,
  k_value,
  COUNT(*) as runs,
  AVG(wcss) as avg_wcss,
  STDDEV(wcss) as stddev_wcss,
  STDDEV(wcss) / AVG(wcss) * 100 as cv_pct
FROM nfr_cluster_results
WHERE dataset_version = '2026-01-27'
GROUP BY variable_set_id, k_value
HAVING COUNT(*) >= 3
  AND (STDDEV(wcss) / AVG(wcss)) > 0.1 -- CV > 10%
ORDER BY cv_pct DESC;
```

### Benefits

1. **Instant Clustering**: Sub-second response vs. 5-30 seconds computation
2. **Elbow Curves**: Pre-computed k=2-10 enables immediate optimal k selection
3. **Consistency**: Same clusters for all users viewing same dataset
4. **Reproducibility**: Random seed stored for exact result recreation
5. **Stability Testing**: Multiple seeds validate cluster quality
6. **Historical Tracking**: See how clusters evolve as dataset grows

### Future Enhancements

1. **Silhouette Scores**: Additional cluster quality metric
2. **Hierarchical Clustering**: Dendrogram results cache
3. **Cluster Profiling**: Statistical summaries of each cluster
4. **Visualization Data**: 2D/3D PCA projections for plotting
5. **Cluster Transitions**: Track how individuals move between clusters over time
6. **Anomaly Detection**: Identify outliers within clusters

---

## Approval & Review

**Author:** NFR Development Team  
**Technical Reviewer:** [Pending]  
**Medical Advisor Review:** [Pending - consult epidemiologist]  
**Privacy Officer Review:** [Pending - HIPAA compliance]  
**Approved for Implementation:** [Pending]

---

## Changelog

- **2026-01-27:** Initial design document created
- **2026-01-27:** Added correlation results cache table design
- **2026-01-27:** Added cluster results cache table design
