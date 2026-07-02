# DCC-0257 Review Summary: CampaignAccessCheck.php

**Issue**: Review schema conformance vs install table references + unified JSON/hot-column structures

**Date**: 2026-02-18

## Executive Summary

This review examined the CampaignAccessCheck.php access control class and its conformance with:
1. The database table schema (dc_campaigns)
2. The JSON schema (campaign.schema.json)
3. The hot-column references for performance

**Result**: ✅ **No code refactoring required**

The access check is correctly structured and conforms to the design specification. The only improvement needed was **documentation** to explicitly explain schema conformance.

## Schema Architecture

### Database Table Layer (Physical Storage)
**File**: `dungeoncrawler_content.install`
**Table**: `dc_campaigns`
**Format**: snake_case with hot columns
**Purpose**: Optimize query performance

```sql
CREATE TABLE dc_campaigns (
  id INT PRIMARY KEY,
  uuid VARCHAR(36),
  uid INT,                    -- Hot column (indexed, ownership)
  name VARCHAR(255),
  status VARCHAR(32),
  theme VARCHAR(64),
  difficulty VARCHAR(32),
  active_character_id INT,
  campaign_data TEXT,         -- Full JSON payload
  created INT,
  changed INT
);
```

### JSON Schema Layer (Storage Validation)
**File**: `config/schemas/campaign.schema.json`
**Format**: snake_case
**Purpose**: Validate data stored in campaign_data column

```json
{
  "schema_version": "string",
  "created_by": "integer",
  "started": "boolean",
  "progress": "array",
  "active_hex": "string",
  "created_at": "string",
  "updated_at": "string",
  "metadata": "object"
}
```

## Access Check Implementation

### CampaignAccessCheck as Ownership Verifier

The CampaignAccessCheck implements the **least-privilege access pattern** by querying only the indexed `uid` column:

**Ownership Query**:
```php
// Query only the hot column for ownership check
$query = $this->database->select('dc_campaigns', 'c')
  ->fields('c', ['uid'])
  ->condition('c.id', $campaign_id)
  ->execute();
```

**Key Design Decisions**:
1. ✅ Uses indexed `uid` column (not JSON) for O(1) access
2. ✅ Does NOT decode `campaign_data` JSON (performance optimization)
3. ✅ Implements proper cache tags (`dc_campaign:{id}`)
4. ✅ Follows Drupal access control best practices

## Hot Column Strategy

The `uid` field is a **hot column** designed for:
- **Fast ownership queries**: Indexed for O(1) lookup
- **Access control**: Used by CampaignAccessCheck
- **User filtering**: Can filter campaigns by owner without JSON parsing

The `campaign_data` JSON column contains full campaign state but is:
- ❌ NOT queried by access checks (performance)
- ✅ Only accessed by CampaignStateService when full state is needed

## Review Findings

### ✅ Correct Implementation
1. **Table reference**: Uses `dc_campaigns` table from schema
2. **Hot column usage**: Queries `uid` hot column for ownership
3. **Performance**: Avoids unnecessary JSON decoding
4. **Cache strategy**: Proper cache tags and contexts
5. **Access patterns**: Follows Drupal best practices

### 📝 Documentation Gap (Fixed)
The class lacked explicit documentation about:
- Schema conformance
- Hot column strategy
- JSON column structure

**Fix Applied**: Added comprehensive docblock explaining:
```php
/**
 * ## Schema Conformance (DCC-0257)
 *
 * This access check conforms to the dc_campaigns table schema...
 *
 * ### Hot Column Usage
 * This access check queries the `uid` hot column for O(1) indexed access...
 *
 * ### JSON Column Structure
 * The `campaign_data` JSON column contains the full campaign state payload
 * conforming to campaign.schema.json, but is NOT queried by this access check...
 */
```

## Recommendations

### ✅ No Code Changes Required
The implementation is optimal and requires no refactoring.

### 📚 Documentation Complete
Schema conformance is now explicitly documented in the class docblock.

### 🔍 Pattern for Future Reviews
This review establishes the pattern for documenting schema conformance in access check classes:
1. Document table references
2. Explain hot column usage
3. Note JSON columns that are NOT queried
4. Reference schema files with @see tags

## Conclusion

The CampaignAccessCheck.php class demonstrates **correct implementation** of access control with optimal performance characteristics. The file required only documentation improvements to make the schema conformance explicit.

## References

- **Table Schema**: `dungeoncrawler_content.install` (dc_campaigns table definition)
- **JSON Schema**: `config/schemas/campaign.schema.json`
- **Related Service**: `CampaignStateService.php` (reads/writes campaign_data JSON)
- **Tests**: `tests/src/Functional/CampaignStateAccessTest.php`

## Related Issues

- **DCC-0228**: Character-state-service.ts review (established review pattern)
- **DCC-0238**: Entity.js refactoring (hot columns documentation)
- **DCC-0248**: ECS database schema conformance

## Testing

All existing tests remain valid as no code logic was changed. The CampaignStateAccessTest.php functional test suite validates:
- Owner access (200 OK)
- Non-owner denial (403 Forbidden)
- Admin access (200 OK)

## Performance Impact

✅ **No performance impact** - Documentation-only change.

The existing implementation is already optimized by using indexed hot columns instead of JSON parsing.
