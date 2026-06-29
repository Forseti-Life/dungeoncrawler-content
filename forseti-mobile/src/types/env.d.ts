declare module '@env' {
  export const ENV: string;

  // API Configuration
  export const API_BASE_URL: string;
  export const API_TIMEOUT: string;

  // Feature Flags
  export const ENABLE_DEBUG_MODE: string;
  export const ENABLE_MOCK_DATA: string;
  export const ENABLE_ANALYTICS: string;

  // Map Configuration
  export const GOOGLE_MAPS_API_KEY: string;
  export const DEFAULT_LATITUDE: string;
  export const DEFAULT_LONGITUDE: string;

  // Auth Configuration
  export const AUTH_DOMAIN: string;
  export const AUTH_CLIENT_ID: string;

  // Other Settings
  export const LOG_LEVEL: string;
}
