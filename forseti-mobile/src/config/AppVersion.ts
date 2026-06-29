/**
 * Centralized App Version Configuration
 * 
 * This is the single source of truth for all version information.
 * All other files should import from here instead of hardcoding versions.
 */

export const APP_VERSION = {
  // Base version from package.json
  VERSION: '1.0.3',
  
  // Build code (automatically incremented by build script)
  BUILD_CODE: 56,
  
  // Build date (automatically updated by build script)
  BUILD_DATE: '2026-01-22',
  
  // Combined display version
  get DISPLAY_VERSION(): string {
    return `v${this.VERSION}-${this.BUILD_CODE} (${this.BUILD_DATE})`;
  },
  
  // Short display version
  get SHORT_VERSION(): string {
    return `v${this.VERSION}-${this.BUILD_CODE}`;
  },
  
  // Version for API calls and logging
  get API_VERSION(): string {
    return `${this.VERSION}.${this.BUILD_CODE}`;
  }
};

export default APP_VERSION;