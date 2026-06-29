/**
 * Web Mock for StorageService
 * Uses localStorage for web preview
 */

class StorageService {
  private static instance: StorageService;

  static getInstance(): StorageService {
    if (!StorageService.instance) {
      StorageService.instance = new StorageService();
    }
    return StorageService.instance;
  }

  async initialize(): Promise<void> {
    console.log('[Web Mock] StorageService.initialize()');
    // Check if localStorage is available
    if (typeof localStorage === 'undefined') {
      throw new Error('localStorage is not available');
    }
    return Promise.resolve();
  }

  async setItem(key: string, value: string): Promise<void> {
    console.log(`[Web Mock] StorageService.setItem(${key})`);
    localStorage.setItem(key, value);
  }

  async getItem(key: string): Promise<string | null> {
    console.log(`[Web Mock] StorageService.getItem(${key})`);
    return localStorage.getItem(key);
  }

  async removeItem(key: string): Promise<void> {
    console.log(`[Web Mock] StorageService.removeItem(${key})`);
    localStorage.removeItem(key);
  }

  async clear(): Promise<void> {
    console.log('[Web Mock] StorageService.clear()');
    localStorage.clear();
  }

  async getAllKeys(): Promise<string[]> {
    console.log('[Web Mock] StorageService.getAllKeys()');
    return Object.keys(localStorage);
  }
}

const storageService = StorageService.getInstance();

export { StorageService, storageService };
export default storageService;
