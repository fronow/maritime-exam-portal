
import { Question } from '../types';
import { generateTest } from './storageService';

// Kept for compatibility with existing imports in UserPanel
// In the new architecture, generateTest in storageService ALREADY calls the server.
// So this is just a pass-through or redundancy.

export const generateTestFromServer = async (apiUrl: string, categoryId: string): Promise<Question[]> => {
  // We can ignore apiUrl param as it is hardcoded in storageService now
  const session = await generateTest(categoryId, 'temp-id');
  return session.questions;
};

export const syncWithDatabase = async (apiUrl: string, categoryMap: any): Promise<number> => {
    // No longer needed for client-side sync, but kept to prevent build errors
    return 0;
};
