/**
 * Centralized Error Handler
 * Global error handling and logging for the entire application
 */

import { DebugLogger } from '../components/DebugConsole';

// Configuration
export const ErrorHandlerConfig = {
  enabled: true, // Master switch for error handling
  logToConsole: true, // Log to native console
  logToDebugConsole: true, // Log to in-app debug console
  captureGlobalErrors: true, // Catch uncaught JS errors
  capturePromiseRejections: true, // Catch unhandled promise rejections
  verboseLogging: true, // Include full error details
};

/**
 * Standardized error logging function
 */
export const logError = (
  context: string,
  error: any,
  additionalInfo?: Record<string, any>
): void => {
  if (!ErrorHandlerConfig.enabled) return;

  const errorMessage = error instanceof Error ? error.message : String(error);
  const errorStack = error instanceof Error ? error.stack : undefined;
  const errorName = error instanceof Error ? error.name : typeof error;

  // Format error details
  const details: string[] = [`[${context}]`, `Error: ${errorMessage}`];

  if (ErrorHandlerConfig.verboseLogging) {
    details.push(`Type: ${errorName}`);
    if (errorStack) {
      details.push(`Stack: ${errorStack}`);
    }
    if (additionalInfo) {
      details.push(`Additional Info: ${JSON.stringify(additionalInfo, null, 2)}`);
    }
    if (error && typeof error === 'object' && !(error instanceof Error)) {
      try {
        details.push(`Raw Error Object: ${JSON.stringify(error, null, 2)}`);
      } catch {
        details.push('Raw Error Object: [Circular or non-serializable]');
      }
    }
  }

  const fullMessage = details.join('\n');

  // Log to native console
  if (ErrorHandlerConfig.logToConsole) {
    console.error(fullMessage);
  }

  // Log to in-app debug console (bypasses console.error override)
  if (ErrorHandlerConfig.logToDebugConsole) {
    DebugLogger.error(`❌ ${context}`, errorMessage);
    if (ErrorHandlerConfig.verboseLogging && errorStack) {
      DebugLogger.error('Stack:', errorStack);
    }
  }
};

/**
 * Standardized warning logging
 */
export const logWarning = (context: string, message: string): void => {
  if (!ErrorHandlerConfig.enabled) return;

  if (ErrorHandlerConfig.logToConsole) {
    console.warn(`[${context}] ${message}`);
  }

  if (ErrorHandlerConfig.logToDebugConsole) {
    DebugLogger.warn(`⚠️ ${context}`, message);
  }
};

/**
 * Standardized info logging
 */
export const logInfo = (context: string, message: string, data?: any): void => {
  if (!ErrorHandlerConfig.enabled) return;

  if (ErrorHandlerConfig.logToConsole) {
    console.log(`[${context}] ${message}`, data || '');
  }

  if (ErrorHandlerConfig.logToDebugConsole) {
    const fullMsg = data ? `${message} ${JSON.stringify(data)}` : message;
    DebugLogger.info(`ℹ️ ${context}`, fullMsg);
  }
};

/**
 * Initialize global error handlers
 */
export const initializeErrorHandlers = (): void => {
  if (!ErrorHandlerConfig.enabled) {
    console.log('[ErrorHandler] Error handling disabled by configuration');
    return;
  }

  // Global JavaScript error handler (React Native ErrorUtils)
  if (ErrorHandlerConfig.captureGlobalErrors && global.ErrorUtils) {
    const originalHandler = global.ErrorUtils.getGlobalHandler?.();

    global.ErrorUtils.setGlobalHandler((error: Error, isFatal?: boolean) => {
      logError('GLOBAL_JS_ERROR', error, { isFatal: !!isFatal });

      // Call original handler to preserve default behavior
      if (originalHandler) {
        originalHandler(error, isFatal);
      }
    });

    console.log('[ErrorHandler] Global JS error handler initialized');
  }

  // Unhandled promise rejection handler
  if (ErrorHandlerConfig.capturePromiseRejections) {
    const tracking = require('promise/setimmediate/rejection-tracking');

    tracking.enable({
      allRejections: true,
      onUnhandled: (id: number, error: Error) => {
        logError('UNHANDLED_PROMISE_REJECTION', error, { rejectionId: id });
      },
      onHandled: (id: number) => {
        if (ErrorHandlerConfig.verboseLogging) {
          logInfo('PROMISE_REJECTION', `Rejection ${id} was handled`);
        }
      },
    });

    console.log('[ErrorHandler] Promise rejection tracking initialized');
  }

  console.log('[ErrorHandler] Error handling framework initialized successfully');
};

/**
 * Disable all error handling (useful for testing)
 */
export const disableErrorHandling = (): void => {
  ErrorHandlerConfig.enabled = false;
  console.log('[ErrorHandler] Error handling disabled');
};

/**
 * Enable error handling
 */
export const enableErrorHandling = (): void => {
  ErrorHandlerConfig.enabled = true;
  console.log('[ErrorHandler] Error handling enabled');
};

/**
 * Configure error handling
 */
export const configureErrorHandling = (config: Partial<typeof ErrorHandlerConfig>): void => {
  Object.assign(ErrorHandlerConfig, config);
  console.log('[ErrorHandler] Configuration updated:', ErrorHandlerConfig);
};

/**
 * Export default for convenience
 */
export default {
  logError,
  logWarning,
  logInfo,
  initialize: initializeErrorHandlers,
  disable: disableErrorHandling,
  enable: enableErrorHandling,
  configure: configureErrorHandling,
  config: ErrorHandlerConfig,
};
