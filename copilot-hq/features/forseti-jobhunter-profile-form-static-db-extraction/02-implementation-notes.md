# Implementation Notes — Profile Form Static DB Extraction

## Commit
- Forseti repo: `6e5634668`
- Files: `UserProfileForm.php`, `UserProfileRepository.php`

## What was done

### UserProfileRepository.php — 15 new methods added
1. `resumeFileRegistered(int $jobSeekerId, int $fileId): bool`
2. `countResumesForJobSeeker(int $jobSeekerId): int`
3. `createResumeRecord(int $jobSeekerId, int $fileId, string $resumeName, int $isPrimary): int`
4. `updateResumeExtractedText(int $resumeId, string $extractedText): void`
5. `insertParsedDataRecord(int $uid, int $fileId, string $filePath, string $parsedData, string $status, ?string $errorMessage, int $timestamp): void`
6. `loadResumeRecord(int $resumeId, array $jobSeekerIds): array`
7. `getLatestParsedDataByFileId(int $uid, int $fileId): ?array`
8. `getResumeRowsForJobSeeker(array $jobSeekerIds): array` (returns `\stdClass[]` via `fetchAll()`)
9. `upsertParsedDataRecord(int $uid, int $fileId, string $filePath, string $parsedData, int $timestamp): void`
10. `saveConsolidatedProfileJson(int $uid, string $json): void`
11. `normalizeParsedDataStatuses(int $uid): void`
12. `countPendingParsedRecords(int $uid): int`
13. `getCompleteParsedFileIds(int $uid): array`
14. `getProfileFieldsForConsolidation(int $uid): ?array`
15. `updateProfileFields(int $uid, array $fields): void`

### UserProfileForm.php — 10 static call sites removed
- `refreshStep2Callback` (line ~1483): 5 repo calls replacing select/insert/update
- `extractTextSubmit` (line ~1771): $connection removed; helpers updated
- `parseJsonOnlySubmit` (line ~1817): $connection removed; helpers updated
- `consolidateSubmit` (line ~1868): $connection removed; `getLatestParsedDataByFileId` used
- `parseResumeSubmit` (line ~2002): $connection removed; `insertParsedDataRecord` used
- `deriveOnlinePresenceFromResumes` (line ~2701): `getResumeRowsForJobSeeker` + `updateResumeExtractedText` + `saveConsolidatedProfileJson`
- `buildConsolidatedJsonAndApplyToProfile` (line ~4520): `getConsolidatedProfileJsonRow` (existing) + `saveConsolidatedProfileJson` (new)
- `normalizeResumeParsedDataStatuses` (line ~4886): `normalizeParsedDataStatuses`
- `ensureResumeConsolidationUpToDate` (line ~4916): `countPendingParsedRecords` + `getCompleteParsedFileIds` + `getConsolidatedProfileJsonRow`
- `applyConsolidatedToProfileFields` (line ~5154): `getProfileFieldsForConsolidation` + `updateProfileFields`

### Private helpers refactored
- `loadResumeRecord($resume_id, $uid, $connection)` → `loadResumeRecord($resume_id, $uid)` — now delegates to repo
- `storeExtractedText($connection, $resume_id, ...)` → `storeExtractedText($resume_id, ...)` — now delegates to repo
- `storeParsedResults($connection, $uid, ..., $is_development, $filename)` → `storeParsedResults($uid, ..., $filename)` — `$is_development` removed (was always `false`); delegates to `upsertParsedDataRecord`

## Verification
- `grep -c 'Drupal::database()' UserProfileForm.php` → **0**
- `php -l UserProfileForm.php` → **No syntax errors**
- `drush cr` → **Cache rebuild complete**
