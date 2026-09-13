import { DEFAULT_BASE_URL, DEFAULT_MODEL, isLegacyOpenAISettings } from "./defaults";

export type Contact = {
  displayName: string;
  phone: string;
};

export type ApiSettings = {
  baseURL: string;
  model: string;
};

export type ComposeSnapshot = {
  messages: Array<{
    id: string;
    role: "user" | "assistant";
    text: string;
  }>;
  currentDraft: string;
};

const CONTACT_KEY = "st.contact";
const API_KEY = "st.api";
const SECRET_SESSION = "st.apiKey";
const SECRET_LOCAL = "st.apiKey.remember";
const REMEMBER_FLAG = "st.rememberKey";
const COMPOSE_KEY = "st.compose";

export const DEFAULT_API: ApiSettings = {
  baseURL: DEFAULT_BASE_URL,
  model: DEFAULT_MODEL,
};

export const DEFAULT_CONTACT: Contact = {
  displayName: "Luis",
  phone: "",
};

function readJson<T>(store: Storage, key: string): T | null {
  try {
    const raw = store.getItem(key);
    if (!raw) return null;
    return JSON.parse(raw) as T;
  } catch {
    return null;
  }
}

export function loadContact(): Contact {
  return { ...DEFAULT_CONTACT, ...readJson<Contact>(localStorage, CONTACT_KEY) };
}

export function saveContact(contact: Contact): void {
  localStorage.setItem(
    CONTACT_KEY,
    JSON.stringify({
      displayName: contact.displayName.trim() || "Luis",
      phone: contact.phone.trim(),
    }),
  );
}

export function loadApiSettings(): ApiSettings {
  const stored = readJson<ApiSettings>(localStorage, API_KEY);
  if (!stored || isLegacyOpenAISettings(stored.baseURL ?? "", stored.model ?? "")) {
    return { ...DEFAULT_API };
  }
  return {
    baseURL: stored.baseURL?.trim() || DEFAULT_API.baseURL,
    model: stored.model?.trim() || DEFAULT_API.model,
  };
}

export function saveApiSettings(settings: ApiSettings): void {
  localStorage.setItem(
    API_KEY,
    JSON.stringify({
      baseURL: settings.baseURL.trim() || DEFAULT_API.baseURL,
      model: settings.model.trim() || DEFAULT_API.model,
    }),
  );
}

export function remembersKey(): boolean {
  return localStorage.getItem(REMEMBER_FLAG) === "1";
}

export function getApiKey(): string {
  return (
    sessionStorage.getItem(SECRET_SESSION) ||
    localStorage.getItem(SECRET_LOCAL) ||
    ""
  );
}

export function saveApiKey(raw: string, remember: boolean): void {
  const value = raw.trim();
  sessionStorage.removeItem(SECRET_SESSION);
  localStorage.removeItem(SECRET_LOCAL);
  localStorage.removeItem(REMEMBER_FLAG);
  if (!value) return;
  sessionStorage.setItem(SECRET_SESSION, value);
  if (remember) {
    localStorage.setItem(SECRET_LOCAL, value);
    localStorage.setItem(REMEMBER_FLAG, "1");
  }
}

export function clearApiKey(): void {
  sessionStorage.removeItem(SECRET_SESSION);
  localStorage.removeItem(SECRET_LOCAL);
  localStorage.removeItem(REMEMBER_FLAG);
}

export function loadCompose(): ComposeSnapshot {
  return (
    readJson<ComposeSnapshot>(localStorage, COMPOSE_KEY) ?? {
      messages: [],
      currentDraft: "",
    }
  );
}

export function saveCompose(snapshot: ComposeSnapshot): void {
  localStorage.setItem(COMPOSE_KEY, JSON.stringify(snapshot));
}

export function clearCompose(): void {
  localStorage.removeItem(COMPOSE_KEY);
}
