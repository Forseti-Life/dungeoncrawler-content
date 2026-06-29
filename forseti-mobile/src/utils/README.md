# Forseti Mobile - Utilities

## Error Handling Framework

### ErrorHandler.ts

Centralized error handling and logging system for the entire application.

#### Configuration

```typescript
import ErrorHandler from './utils/ErrorHandler';

// Configure error handling
ErrorHandler.configure({
  enabled: true, // Master switch
  logToConsole: true, // Log to native console
  logToDebugConsole: true, // Log to in-app debug console
  captureGlobalErrors: true, // Catch uncaught JS errors
  capturePromiseRejections: true, // Catch unhandled promises
  verboseLogging: true, // Include stack traces
});
```

#### Usage

```typescript
import { logError, logWarning, logInfo } from './utils/ErrorHandler';

// Log errors
try {
  await someAsyncOperation();
} catch (error) {
  logError('MyComponent', error, {
    userId: 123,
    operation: 'someAsyncOperation',
  });
}

// Log warnings
logWarning('MyComponent', 'Deprecated API used');

// Log info
logInfo('MyComponent', 'Operation successful', { count: 10 });
```

#### Enable/Disable

```typescript
import ErrorHandler from './utils/ErrorHandler';

// Disable all error handling (e.g., during testing)
ErrorHandler.disable();

// Re-enable
ErrorHandler.enable();
```

#### Global Handlers

Error handlers are automatically initialized in `index.js`:

1. **Global JavaScript Errors**: Catches all uncaught JS exceptions
2. **Unhandled Promise Rejections**: Catches promises that reject without `.catch()`

All errors are:

- Logged to native console (if enabled)
- Logged to in-app Debug Console (if enabled)
- Include full stack traces in verbose mode
- Include additional context data

#### Debug Console Integration

The ErrorHandler works alongside DebugConsole.tsx to provide:

- Persistent logging across app crashes
- In-app log viewer with filtering
- AsyncStorage persistence for crash analysis

To view logs:

1. Open Settings screen
2. Navigate to Developer Tools
3. View Debug Console

#### Best Practices

1. **Use logError for exceptions**:

   ```typescript
   catch (error) {
     logError('ComponentName', error, { context: 'info' });
   }
   ```

2. **Use logWarning for non-critical issues**:

   ```typescript
   if (!optionalFeature) {
     logWarning('ComponentName', 'Optional feature unavailable');
   }
   ```

3. **Use logInfo for important events**:

   ```typescript
   logInfo('ComponentName', 'User logged in', { userId: user.id });
   ```

4. **Always include context**:

   ```typescript
   logError('BackgroundService', error, {
     step: 'startMonitoring',
     userId: currentUser?.id,
     timestamp: Date.now(),
   });
   ```

5. **Use consistent context strings**:
   - Format: `ComponentName` or `ServiceName:methodName`
   - Examples: `HomeScreen`, `LocationService:startWatching`, `useBackgroundMonitoring:toggle`

#### Troubleshooting

**Q: Not seeing errors in Debug Console?**

- Check `ErrorHandlerConfig.logToDebugConsole` is `true`
- Verify `ErrorHandlerConfig.enabled` is `true`
- Check if error occurred before ErrorHandler initialization

**Q: Too much logging?**

- Set `ErrorHandlerConfig.verboseLogging = false` to reduce stack traces
- Set `ErrorHandlerConfig.logToConsole = false` to only use in-app console

**Q: Need to debug ErrorHandler itself?**

- Check native console for initialization messages
- Look for `[ErrorHandler]` prefixed messages
- Verify `index.js` imports ErrorHandler and calls `initialize()`
