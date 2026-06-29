# Legacy Mobile Setup Scripts

These scripts have been **deprecated** and replaced by the consolidated `setup-forseti-mobile-dev.sh` script.

## Archived Scripts

- **setup-mobile.sh** - Original mobile app environment setup
  - Issue: Points to old directory path (`/stlouisintegration.com/amisafe-mobile`)
  - Replaced by: `setup-forseti-mobile-dev.sh`

- **setup-mobile-web.sh** - React Native Web preview setup
  - Issue: Incomplete, only handles web dependencies
  - Replaced by: `setup-forseti-mobile-dev.sh --skip-android`

- **setup-android-build.sh** - Android SDK installation
  - Issue: Only handles Android SDK, no dev tools
  - Replaced by: `setup-forseti-mobile-dev.sh --skip-web`

## Why Were They Replaced?

The new consolidated script (`setup-forseti-mobile-dev.sh`) provides:

✅ **All functionality** from the three scripts above
✅ **Additional features**: ESLint, Prettier, Jest, TypeScript, environment variables
✅ **Better error handling** and progress indicators
✅ **Flexible options** with command-line flags
✅ **Configuration verification** to ensure everything is set up correctly
✅ **Updated paths** pointing to correct project directory

## Migration

If you were using these old scripts:

```bash
# Old way (3 scripts)
./setup-mobile.sh
./setup-mobile-web.sh
./setup-android-build.sh

# New way (1 script)
./setup-forseti-mobile-dev.sh
```

See `../MOBILE_SCRIPTS_MIGRATION.md` for complete migration guide.

## Should These Be Deleted?

These scripts are kept for reference but should not be used for new setups. They may be deleted in a future cleanup.

**Use the new script instead:**
```bash
cd /home/keithaumiller/forseti.life/script
./setup-forseti-mobile-dev.sh --help
```
