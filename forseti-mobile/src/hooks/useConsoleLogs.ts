/**
 * React Hook for Console Log Management
 * Provides easy access to existing DebugLogger system for upload functionality
 */

import { useState, useEffect, useCallback } from 'react';
import ConsoleLogService from '../services/logging/ConsoleLogService';

interface UseConsoleLogsReturn {
  logCount: number;
  isUploading: boolean;
  uploadLogs: () => Promise<boolean>;
  clearLogs: () => Promise<void>;
  getLogsAsString: () => Promise<string>;
  uploadError: string | null;
}

export const useConsoleLogs = (): UseConsoleLogsReturn => {
  const [logCount, setLogCount] = useState(0);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const logService = ConsoleLogService.getInstance();

  // Initialize service and update log count
  useEffect(() => {
    const initializeService = async () => {
      await logService.initialize();
      updateLogCount();
    };

    initializeService();

    // Update log count periodically
    const interval = setInterval(updateLogCount, 5000); // Every 5 seconds

    return () => {
      clearInterval(interval);
    };
  }, []);

  const updateLogCount = useCallback(async () => {
    const count = await logService.getLogCount();
    setLogCount(count);
  }, []);

  const uploadLogs = useCallback(async (): Promise<boolean> => {
    setIsUploading(true);
    setUploadError(null);

    try {
      const success = await logService.uploadLogs();
      if (!success) {
        setUploadError('Failed to upload logs');
      } else {
        updateLogCount(); // Update count after upload
      }
      return success;
    } catch (error) {
      setUploadError(error instanceof Error ? error.message : 'Unknown error');
      return false;
    } finally {
      setIsUploading(false);
    }
  }, [updateLogCount]);

  const clearLogs = useCallback(async () => {
    await logService.clearLogs();
    updateLogCount();
    setUploadError(null);
  }, [updateLogCount]);

  const getLogsAsString = useCallback(async () => {
    return await logService.getLogsAsString();
  }, []);

  return {
    logCount,
    isUploading,
    uploadLogs,
    clearLogs,
    getLogsAsString,
    uploadError,
  };
};
