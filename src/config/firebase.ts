/**
 * Firebase configuration flag (Phase 8).
 *
 * The app ships with NO real backend by default. When the VITE_FIREBASE_*
 * environment variables are present, this module lazily initialises the
 * Firebase app + (optionally) Auth/Firestore/Storage and exposes them; when
 * they are absent (the default demo build), every helper is a safe no-op so
 * the bundle stays lean and the demo keeps working on localStorage.
 *
 * To enable: copy .env.example -> .env.local and fill in the Firebase values,
 * then `npm run build`. No code changes are required.
 */
export interface FirebaseConfig {
  apiKey: string;
  authDomain: string;
  projectId: string;
  storageBucket: string;
  messagingSenderId: string;
  appId: string;
}

export function readFirebaseConfig(): FirebaseConfig | null {
  const cfg: FirebaseConfig = {
    apiKey: import.meta.env.VITE_FIREBASE_API_KEY || '',
    authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN || '',
    projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID || '',
    storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET || '',
    messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID || '',
    appId: import.meta.env.VITE_FIREBASE_APP_ID || '',
  };
  if (!cfg.apiKey || !cfg.projectId || !cfg.appId) return null;
  return cfg;
}

export const isFirebaseConfigured = readFirebaseConfig() !== null;

/**
 * Lazy accessor — resolves to the Firebase app instance when configured,
 * or `null` otherwise. Dynamically imports the firebase SDK so the public
 * bundle stays lean when Firebase is not in use.
 */
export async function getFirebaseApp(): Promise<any | null> {
  const cfg = readFirebaseConfig();
  if (!cfg) return null;
  try {
    // @ts-ignore - firebase SDK is an optional dependency
    const { initializeApp, getApps } = await import('firebase/app');
    if (getApps().length) return getApps()[0];
    return initializeApp(cfg);
  } catch {
    return null;
  }
}

export async function getFirebaseAuth(): Promise<any | null> {
  const app = await getFirebaseApp();
  if (!app) return null;
  try {
    // @ts-ignore - firebase SDK is an optional dependency
    const { getAuth } = await import('firebase/auth');
    return getAuth(app);
  } catch {
    return null;
  }
}

export async function getFirebaseDb(): Promise<any | null> {
  const app = await getFirebaseApp();
  if (!app) return null;
  try {
    // @ts-ignore - firebase SDK is an optional dependency
    const { getFirestore } = await import('firebase/firestore');
    return getFirestore(app);
  } catch {
    return null;
  }
}