export type Language = 'bg' | 'en';

export enum Role {
  ADMIN = 'ADMIN',
  USER = 'USER',
}

export interface User {
  id: string;
  firstName: string;
  lastName: string;
  email: string;
  passwordHash: string;
  role: Role;
  approvedCategories: string[]; // IDs of categories
  requestedCategories: string[]; // IDs of categories
  categoryExpiry: Record<string, number>; // CategoryID -> Timestamp (Unix ms) when access expires
  isSuspended: boolean;
}

export interface Category {
  id: string;
  nameBg: string;
  nameEn: string;
  price: number;
  questionCount: number;
  durationMinutes: number; // Exam duration (e.g. 60 min)
}

export interface Package {
  id: string;
  nameBg: string;
  nameEn: string;
  price: number;
  durationDays: number; // How many days access lasts
  categoryIds: string[];
}

export interface GlobalSettings {
  revolutLink: string;
  facebookLink?: string;
  announcement?: string;
}

export interface Question {
  id: string;
  categoryId: string;
  originalIndex: number; // For the 25% logic
  text: string;
  optionA: string;
  optionB: string;
  optionC: string;
  optionD: string;
  correctAnswer: 'A' | 'B' | 'C' | 'D'; 
  image?: string;
}

export interface TestSession {
  id: string;
  userId: string;
  categoryId: string;
  startTime: number;
  endTime?: number;
  score: number;
  totalQuestions: number;
  answers: Record<string, string>; // questionId -> selectedOption
  isCompleted: boolean;
  questions: Question[]; // The specific 60 questions generated
}

declare global {
  interface Window {
    XLSX: any;
  }
}