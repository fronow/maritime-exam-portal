
import { User, Category, Question, TestSession, Package, GlobalSettings, Role } from '../types';
import { INITIAL_CATEGORIES_RAW } from '../constants';

// CONFIGURATION
const API_URL = 'https://news.morskiizpit.com/api.php'; // Subdomain for testing

// Session token storage
const KEY_SESSION_TOKEN = 'maritime_session_token';

const getSessionToken = (): string | null => {
    return localStorage.getItem(KEY_SESSION_TOKEN);
};

const setSessionToken = (token: string | null) => {
    if (token) {
        localStorage.setItem(KEY_SESSION_TOKEN, token);
    } else {
        localStorage.removeItem(KEY_SESSION_TOKEN);
    }
}; 

// --- MOCK DATABASE (LOCAL STORAGE) ---
const MOCK_DB = {
    get: (key: string) => JSON.parse(localStorage.getItem(`mock_${key}`) || 'null'),
    set: (key: string, val: any) => localStorage.setItem(`mock_${key}`, JSON.stringify(val)),
    init: () => {
        if (!MOCK_DB.get('categories')) {
            const initialCats: Category[] = INITIAL_CATEGORIES_RAW.map((raw, idx) => {
                const parts = raw.split('|');
                return {
                    id: `cat-${idx}`,
                    nameBg: parts[0].trim(),
                    nameEn: parts[1] ? parts[1].trim() : parts[0].trim(),
                    price: 5,
                    questionCount: 0,
                    durationMinutes: 60
                };
            });
            MOCK_DB.set('categories', initialCats);
        }
        if (!MOCK_DB.get('users')) {
            MOCK_DB.set('users', [{
                id: '1',
                email: 'admin@maritime.com',
                passwordHash: 'admin123',
                firstName: 'Admin',
                lastName: 'User',
                role: 'ADMIN',
                approvedCategories: [],
                requestedCategories: [],
                categoryExpiry: {},
                isSuspended: false
            }]);
        }
        if (!MOCK_DB.get('packages')) MOCK_DB.set('packages', []);
        if (!MOCK_DB.get('settings')) MOCK_DB.set('settings', { revolutLink: '', facebookLink: '' });
        if (!MOCK_DB.get('questions')) MOCK_DB.set('questions', {}); 
    }
};

MOCK_DB.init();

async function mockApiCall(action: string, data: any): Promise<any> {
    // console.log(`[MOCK API] ${action}`, data); // Uncomment for debugging
    await new Promise(r => setTimeout(r, 200)); 

    const users: User[] = MOCK_DB.get('users') || [];
    const categories: Category[] = MOCK_DB.get('categories') || [];
    const packages: Package[] = MOCK_DB.get('packages') || [];
    const settings: GlobalSettings = MOCK_DB.get('settings') || {};
    const questionsMap: Record<string, Question[]> = MOCK_DB.get('questions') || {};

    switch (action) {
        case 'login': {
            const u = users.find(user => user.email === data.email && user.passwordHash === data.password);
            if (!u) throw new Error("Invalid credentials (Try: admin@maritime.com / admin123)");
            if (u.isSuspended) throw new Error("Account suspended");
            return u;
        }
        case 'register': {
            if (users.find(u => u.email === data.email)) throw new Error("Email exists");
            const newUser: User = {
                id: `user-${Date.now()}`,
                email: data.email,
                passwordHash: data.password,
                firstName: data.firstName,
                lastName: data.lastName,
                role: Role.USER,
                approvedCategories: [],
                requestedCategories: [],
                categoryExpiry: {},
                isSuspended: false
            };
            users.push(newUser);
            MOCK_DB.set('users', users);
            return newUser;
        }
        case 'get_initial_data':
            return { categories, packages, settings };
        case 'get_admin_data':
            return { users };
        case 'save_settings':
            MOCK_DB.set('settings', data.settings);
            return { success: true };
        case 'save_category': {
            const idx = categories.findIndex(c => c.id === data.category.id);
            if (idx !== -1) categories[idx] = data.category;
            else categories.push(data.category);
            MOCK_DB.set('categories', categories);
            return { success: true };
        }
        case 'save_package': {
            const idx = packages.findIndex(p => p.id === data.package.id);
            if (idx !== -1) packages[idx] = data.package;
            else packages.push(data.package);
            MOCK_DB.set('packages', packages);
            return { success: true };
        }
        case 'toggle_suspend': {
            const u = users.find(x => x.id === data.userId);
            if (u) {
                u.isSuspended = data.suspend;
                MOCK_DB.set('users', users);
            }
            return { success: true };
        }
        case 'request_access': {
            const u = users.find(x => x.id === data.userId);
            if (u) {
                const set = new Set([...u.requestedCategories, ...data.categoryIds]);
                u.requestedCategories = Array.from(set);
                MOCK_DB.set('users', users);
            }
            return { success: true };
        }
        case 'approve_request': {
            const u = users.find(x => x.id === data.userId);
            if (u) {
                u.requestedCategories = u.requestedCategories.filter(id => !data.categoryIds.includes(id));
                const appSet = new Set([...u.approvedCategories, ...data.categoryIds]);
                u.approvedCategories = Array.from(appSet);
                data.categoryIds.forEach((id: string) => {
                    u.categoryExpiry[id] = data.expiry;
                });
                MOCK_DB.set('users', users);
            }
            return { success: true };
        }
        case 'import_questions': {
            const newQs: Question[] = data.questions;
            if (!newQs.length) return { success: true };
            const catId = newQs[0].categoryId;
            questionsMap[catId] = newQs;
            MOCK_DB.set('questions', questionsMap);
            
            const cat = categories.find(c => c.id === catId);
            if (cat) {
                cat.questionCount = newQs.length;
                MOCK_DB.set('categories', categories);
            }
            return { success: true };
        }
        case 'generate_test': {
            const catId = data.category_id;
            const allQ = questionsMap[catId] || [];
            if (allQ.length === 0) {
                 const dummy: Question[] = [];
                 for(let i=0; i<60; i++) {
                     dummy.push({
                         id: `dummy-${i}`, categoryId: catId, originalIndex: i,
                         text: `Mock Question ${i+1} for Category ${catId}? (Import real questions in Admin Panel)`,
                         optionA: 'Answer A', optionB: 'Answer B', optionC: 'Answer C', optionD: 'Answer D',
                         correctAnswer: 'A'
                     });
                 }
                 return dummy;
            }

            const total = allQ.length;
            const chunkSize = Math.floor(total / 4);
            const selected: Question[] = [];
            
            // If total questions < 4, just return what we have (edge case)
            if (chunkSize === 0) return allQ.slice(0, 60);

            const chunk1 = allQ.slice(0, chunkSize);
            selected.push(...chunk1.sort(() => 0.5 - Math.random()).slice(0, 15));

            const chunk2 = allQ.slice(chunkSize, chunkSize * 2);
            selected.push(...chunk2.sort(() => 0.5 - Math.random()).slice(0, 15));

            const chunk3 = allQ.slice(chunkSize * 2, chunkSize * 3);
            selected.push(...chunk3.sort(() => 0.5 - Math.random()).slice(0, 15));

            const chunk4 = allQ.slice(chunkSize * 3);
            selected.push(...chunk4.sort(() => 0.5 - Math.random()).slice(0, 15));

            return selected;
        }
        default:
            throw new Error("Unknown mock action: " + action);
    }
}


// --- API CLIENT ---

let useMockFallback = false;

async function apiCall(action: string, data: any = {}, method: 'GET' | 'POST' = 'POST') {
    // If we already detected backend is down, use mock immediately
    if (useMockFallback) {
        return mockApiCall(action, data);
    }

    const url = API_URL;
    const sessionToken = getSessionToken();

    // New backend API format
    const requestBody = {
        action,
        data,
        session_token: sessionToken
    };

    const options: RequestInit = {
        method: 'POST', // Backend always expects POST
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestBody)
    };

    try {
        const res = await fetch(url, options);

        // Handle 404 (Dev mode) or HTML responses (Server errors)
        const contentType = res.headers.get("content-type");
        if (!res.ok || !contentType || !contentType.includes("application/json")) {
             console.warn(`[API] Backend unavailable (${res.status} ${res.statusText}). Switching to Mock Mode.`);
             useMockFallback = true;
             return mockApiCall(action, data);
        }

        const json = await res.json();

        // New backend returns {success: true/false, data: {...}, error: "..."}
        if (!json.success) {
            throw new Error(json.error || 'API request failed');
        }

        // Return the data field from the response
        return json.data;
    } catch (err: any) {
        // Network error (Backend not running) or JSON parse error
        console.warn(`[API] Connection failed for ${action}. Switching to Mock Mode.`);
        useMockFallback = true;
        // Retry logic with mock immediately so the user doesn't see an error
        return mockApiCall(action, data);
    }
}

// --- AUTHENTICATION ---
export const loginUser = async (email: string, pass: string): Promise<User> => {
    const response = await apiCall('login', { email, password: pass });

    // Backend returns {user, session_token}
    if (response.session_token) {
        setSessionToken(response.session_token);
    }

    return response.user || response; // Support both old and new format
};

export const registerUser = async (data: any): Promise<User> => {
    const response = await apiCall('register', data);

    // Backend returns {user, session_token}
    if (response.session_token) {
        setSessionToken(response.session_token);
    }

    return response.user || response; // Support both old and new format
};

const KEY_USER_ID = 'maritime_user_id';
const KEY_USER_DATA = 'maritime_user_data_cache';

export const getCurrentUser = (): User | null => {
    const str = localStorage.getItem(KEY_USER_DATA);
    return str ? JSON.parse(str) : null;
};

export const setCurrentUser = (user: User | null) => {
    if (user) {
        localStorage.setItem(KEY_USER_ID, user.id);
        localStorage.setItem(KEY_USER_DATA, JSON.stringify(user));
    } else {
        localStorage.removeItem(KEY_USER_ID);
        localStorage.removeItem(KEY_USER_DATA);
        setSessionToken(null); // Clear session token on logout
    }
};

// --- DATA FETCHING ---
export const fetchInitialData = async () => {
    return await apiCall('get_initial_data', {}, 'GET');
};

export const fetchAdminData = async () => {
    const data = await apiCall('get_admin_data', {}, 'GET');
    return data.users || []; 
};

// --- SETTINGS ---
export const saveSettings = async (settings: GlobalSettings) => {
    await apiCall('save_settings', { settings });
    window.dispatchEvent(new Event('maritime_settings_updated'));
};

// --- ACTIONS ---
export const saveCategory = async (cat: Category) => {
    await apiCall('save_category', { category: cat });
};

export const savePackage = async (pkg: Package) => {
    await apiCall('save_package', { package: pkg });
};

export const requestAccess = async (userId: string, categoryIds: string[]) => {
    await apiCall('request_access', { userId, categoryIds });
};

export const approveRequest = async (userId: string, categoryIds: string[], expiry: number) => {
    await apiCall('approve_request', { userId, categoryIds, expiry });
};

export const toggleSuspendUser = async (userId: string, suspend: boolean) => {
    await apiCall('toggle_suspend', { userId, suspend });
};

export const importQuestionsToBackend = async (questions: Question[]) => {
    await apiCall('import_questions', { questions });
};

// --- TEST GENERATION ---
export const generateTest = async (categoryId: string, userId: string): Promise<TestSession> => {
    const questions = await apiCall('generate_test', { category_id: categoryId });
    
    return {
        id: `session-${Date.now()}`,
        userId,
        categoryId,
        startTime: Date.now(),
        score: 0,
        totalQuestions: questions.length,
        answers: {},
        isCompleted: false,
        questions: questions
    };
};

// --- SESSION STORAGE (Progress saving) ---
const KEY_SESSIONS = 'maritime_sessions';
export const getSessions = (userId?: string): TestSession[] => {
    const all: TestSession[] = JSON.parse(localStorage.getItem(KEY_SESSIONS) || '[]');
    if (userId) return all.filter(s => s.userId === userId);
    return all;
};

export const saveSession = (session: TestSession) => {
    const all = getSessions();
    const idx = all.findIndex(s => s.id === session.id);
    if (idx !== -1) all[idx] = session;
    else all.push(session);
    localStorage.setItem(KEY_SESSIONS, JSON.stringify(all));
};
