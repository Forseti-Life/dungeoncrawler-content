# NFR Correlation Analysis User Guide

## Overview

The Correlation Analysis tool allows researchers to examine statistical relationships between any two variables in the National Firefighter Registry dataset. This powerful tool can help identify:

- **Risk factors** for cancer and other health outcomes
- **Dose-response relationships** between exposure and disease
- **Confounding variables** that need to be controlled in studies
- **Demographic patterns** in firefighter populations

## Accessing the Tool

Navigate to: **Admin > Reports > Report Builder** (`/admin/nfr/reports`)

*Requires: `view nfr reports` permission*

## Quick Start

1. **Select Variables**
   - **X-Axis (Independent)**: Usually the exposure or predictor (e.g., years of service, incident exposure)
   - **Y-Axis (Dependent)**: Usually the outcome (e.g., cancer diagnosis, health metrics)

2. **Apply Filters (Optional)**
   - Minimum data quality score
   - Sex/gender filter
   - Cancer diagnosis status

3. **Choose Method**
   - **Pearson**: For continuous variables with linear relationships
   - **Spearman**: For ordinal data or non-linear relationships

4. **Run Analysis** - Click "Run Analysis" button

5. **Export Data** - Click "Export Data (CSV)" to download raw data for external analysis

## Variable Categories

### Demographics (15 variables)
- Age at enrollment
- Sex/gender
- Race/ethnicity indicators
- Education level
- Marital status
- Birthplace information

### Health Metrics (10 variables)
- Height, weight, BMI
- Smoking status
- Alcohol consumption
- Exercise frequency
- Diet quality
- Sleep patterns

### Work History (20 variables)
- Total fire departments worked
- Total years of service
- Years by employment type (career, volunteer, etc.)
- Highest rank achieved
- Leadership roles
- States worked
- Incident response status

### Incident Exposure (40 variables)
- Total incidents by type:
  - Structure fires (residential, commercial)
  - Vehicle fires
  - Wildland fires
  - Hazmat incidents
  - Training fires
  - Medical/EMS calls
  - Technical rescue
- Average incidents per year
- Incident diversity score
- High exposure years

### Health Outcomes (25 variables)
- Cancer diagnosis (overall and by type):
  - Lung cancer
  - Colorectal cancer
  - Prostate cancer
  - Breast cancer
  - Bladder cancer
  - Kidney cancer
  - Skin cancer
- Age at first diagnosis
- Years service before cancer
- Family cancer history

## Interpreting Results

### Correlation Coefficient (r)

The correlation coefficient ranges from -1.0 to +1.0:

- **r = +1.0**: Perfect positive correlation
- **r = +0.7 to +0.9**: Strong positive correlation
- **r = +0.4 to +0.7**: Moderate positive correlation
- **r = +0.1 to +0.4**: Weak positive correlation
- **r = 0**: No correlation
- **r = -0.1 to -0.4**: Weak negative correlation
- **r = -0.4 to -0.7**: Moderate negative correlation
- **r = -0.7 to -0.9**: Strong negative correlation
- **r = -1.0**: Perfect negative correlation

### Statistical Significance (p-value)

- **p < 0.001**: Highly significant (strong evidence of relationship)
- **p < 0.01**: Very significant
- **p < 0.05**: Significant (standard threshold)
- **p ≥ 0.05**: Not statistically significant

**Important**: Statistical significance depends on both the strength of correlation AND sample size. Large samples can produce significant p-values even for weak correlations.

### Practical Significance

Consider both statistical AND practical significance:

- **Weak correlations (r < 0.3)** may be statistically significant but have limited practical value
- **Strong correlations (r > 0.7)** suggest important relationships worth investigating
- **Context matters**: A weak correlation may still be meaningful for rare diseases or important exposures

## Example Analyses

### Example 1: Service Duration and Cancer Risk

**Variables:**
- X: `total_years_service` (Total Years of Service)
- Y: `has_cancer_diagnosis` (Has Cancer Diagnosis)

**Method:** Spearman (outcome is binary)

**Interpretation:** Positive correlation would suggest increased cancer risk with longer service.

### Example 2: Structure Fire Exposure and Cancer

**Variables:**
- X: `total_structure_fires` (Total Structure Fires)
- Y: `has_lung_cancer` (Has Lung Cancer)

**Method:** Spearman

**Filters:** Minimum quality score = 80

**Interpretation:** Examines dose-response relationship between fire exposure and lung cancer specifically.

### Example 3: Age at Diagnosis and Service Duration

**Variables:**
- X: `total_years_service`
- Y: `age_first_cancer_diagnosis`

**Method:** Pearson (both continuous)

**Filters:** Cancer diagnosis = Cancer cases only

**Interpretation:** Among cancer cases, does longer service correlate with earlier age at diagnosis?

### Example 4: BMI and Cancer Risk

**Variables:**
- X: `bmi` (Body Mass Index)
- Y: `has_cancer_diagnosis`

**Method:** Spearman

**Filters:** Quality score ≥ 90 (to ensure accurate height/weight data)

**Interpretation:** Examines whether BMI is a confounding variable in cancer risk.

## Advanced Usage

### Stratified Analysis

To examine correlations within subgroups:

1. Use **sex filter** to analyze males and females separately
2. Use **cancer filter** to examine patterns among cases vs. controls
3. Compare correlation coefficients between groups

### Multiple Comparisons

When running many correlations, apply Bonferroni correction:

- Divide your significance threshold (0.05) by the number of tests
- Example: 10 tests → use p < 0.005 as threshold

### Exporting for Advanced Analysis

The **Export Data (CSV)** button provides raw data for:

- **R statistical software**: For regression modeling, survival analysis
- **SPSS**: For advanced statistical tests
- **Python/pandas**: For machine learning and visualization
- **Excel**: For basic charts and pivot tables

Export includes:
- UID (de-identified)
- Both variable values
- Data quality score

## Limitations & Considerations

### Data Quality
- **Quality score** indicates completeness - higher is better
- Recommend minimum score of 70 for most analyses
- Score of 90+ indicates very complete data

### Sample Size
- Larger samples provide more reliable correlations
- Minimum recommended: n > 30 for meaningful results
- Subgroup analyses may have smaller samples

### Correlation ≠ Causation
- Correlation shows association, not causation
- Consider:
  - Confounding variables
  - Reverse causation
  - Selection bias
  - Temporal relationships

### Missing Data
- Records with NULL values for either variable are excluded
- May introduce selection bias if missing data is non-random

### Binary Variables
- For binary outcomes (has_cancer_diagnosis), Spearman is typically preferred
- Consider logistic regression for more sophisticated analysis

### Multiple Cancer Types
- Family-wise error rate increases with multiple tests
- Use Bonferroni or FDR correction for multiple comparisons

## Statistical Methods

### Pearson Correlation

**Formula:**
```
r = Σ[(Xi - X̄)(Yi - Ȳ)] / √[Σ(Xi - X̄)² × Σ(Yi - Ȳ)²]
```

**Assumptions:**
- Both variables are continuous
- Linear relationship
- Bivariate normal distribution
- No significant outliers

**Best for:**
- Continuous variables (age, BMI, years of service)
- Linear relationships
- Normally distributed data

### Spearman Rank Correlation

**Method:**
- Converts values to ranks
- Calculates Pearson correlation on ranks

**Assumptions:**
- Monotonic relationship (not necessarily linear)
- Ordinal or continuous data

**Best for:**
- Binary outcomes (has_cancer)
- Ordinal data (education level, rank)
- Non-linear relationships
- Data with outliers

## Data Sources

The correlation analysis table (`nfr_correlation_analysis`) aggregates data from:

1. **nfr_user_profile** - Basic demographics
2. **nfr_questionnaire** - Health and lifestyle data
3. **nfr_work_history** - Employment periods
4. **nfr_job_titles** - Specific positions
5. **nfr_incident_frequency** - Exposure data
6. **nfr_cancer_diagnoses** - Cancer outcomes
7. **nfr_family_cancer_history** - Family history
8. **nfr_consent** - Participation dates

**Update Frequency:**
Data should be refreshed periodically using:
```bash
drush nfr:correlation-rebuild
```

## Best Practices

1. **Start broad, then narrow**
   - Begin with general relationships (total service vs. any cancer)
   - Then examine specific types (structure fires vs. lung cancer)

2. **Use appropriate methods**
   - Pearson for continuous-continuous
   - Spearman for binary outcomes or ordinal data

3. **Apply quality filters**
   - Use minimum quality score to ensure data reliability
   - Higher thresholds for critical analyses

4. **Document your analyses**
   - Record which variables, filters, and methods you used
   - Note correlation coefficient and p-value
   - Export data and save for reproducibility

5. **Validate findings**
   - Compare with published research
   - Test with different filters/subgroups
   - Consider biological plausibility

6. **Report responsibly**
   - Always note correlation vs. causation distinction
   - Report confidence intervals if calculating
   - Acknowledge limitations

## Support & Questions

For technical assistance or questions about interpreting results:
- Contact: NFR Research Team
- Email: research@nfr.org
- Documentation: `/nfr/documentation/correlation-analysis`

## Version History

- **v1.0** (January 2026) - Initial release with 169 variables across 5 categories
- Dataset: 302 participants, 74 cancer cases, 93.9% average data quality

---

*Last updated: January 27, 2026*
