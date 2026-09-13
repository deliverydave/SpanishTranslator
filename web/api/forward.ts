export type ChatPayload = {
  baseURL?: string;
  model?: string;
  messages?: unknown;
  temperature?: number;
};

export type ChatResult = {
  status: number;
  body: Record<string, unknown>;
};

export const DEFAULT_BASE = "https://api.x.ai/v1";
export const DEFAULT_MODEL = "grok-4.6";

export function normalizeBaseURL(raw: string): string {
  let value = raw.trim();
  if (!value) value = DEFAULT_BASE;
  if (value.endsWith("/")) value = value.slice(0, -1);
  if (value.endsWith("/chat/completions")) {
    value = value.slice(0, -"/chat/completions".length);
  }
  if (value.endsWith("/")) value = value.slice(0, -1);
  if (!value.includes("://")) value = `https://${value}`;

  try {
    const url = new URL(value);
    const host = url.hostname.toLowerCase();
    if (
      (host === "api.openai.com" ||
        host.endsWith(".openai.com") ||
        host === "api.x.ai" ||
        host.endsWith(".x.ai")) &&
      !url.pathname.includes("v1")
    ) {
      value = `${value}/v1`;
    }
  } catch {
    // validated later
  }
  return value;
}

export function coerceUpstream(baseURL?: string, model?: string): { baseURL: string; model: string } {
  const url = normalizeBaseURL(baseURL || DEFAULT_BASE);
  let chosenModel = (model || DEFAULT_MODEL).trim() || DEFAULT_MODEL;
  const host = (() => {
    try {
      return new URL(url).hostname.toLowerCase();
    } catch {
      return "";
    }
  })();
  if (host.includes("openai.com") || chosenModel === "gpt-4o-mini" || chosenModel.startsWith("gpt-3")) {
    return { baseURL: DEFAULT_BASE, model: DEFAULT_MODEL };
  }
  return { baseURL: url, model: chosenModel };
}

export function assertSafeBaseURL(raw: string): URL {
  const normalized = normalizeBaseURL(raw);
  let url: URL;
  try {
    url = new URL(normalized);
  } catch {
    throw new Error("The API base URL looks invalid. Example: https://api.x.ai/v1");
  }
  if (url.protocol !== "https:") {
    throw new Error("The API base URL must use https.");
  }
  const host = url.hostname.toLowerCase();
  if (
    host === "localhost" ||
    host.endsWith(".localhost") ||
    host === "127.0.0.1" ||
    host === "0.0.0.0" ||
    host === "::1" ||
    host.endsWith(".local") ||
    host.endsWith(".internal")
  ) {
    throw new Error("That API host is not allowed.");
  }
  if (isPrivateIPv4(host)) {
    throw new Error("That API host is not allowed.");
  }
  return url;
}

function isPrivateIPv4(host: string): boolean {
  const parts = host.split(".");
  if (parts.length !== 4 || parts.some((p) => !/^\d+$/.test(p))) return false;
  const [a, b] = parts.map(Number);
  if (a === 10 || a === 127) return true;
  if (a === 192 && b === 168) return true;
  if (a === 172 && b >= 16 && b <= 31) return true;
  if (a === 169 && b === 254) return true;
  return false;
}

function serverApiKey(): string {
  const value =
    process.env.XAI_API_KEY?.trim() ||
    process.env.GROK_API_KEY?.trim() ||
    "";
  return value;
}

export async function handleChatRequest(input: {
  method?: string;
  authorization?: string | string[];
  payload: ChatPayload;
}): Promise<ChatResult> {
  const method = (input.method ?? "POST").toUpperCase();
  if (method === "OPTIONS") {
    return { status: 204, body: {} };
  }
  if (method !== "POST") {
    return { status: 405, body: { error: { message: "POST only" } } };
  }

  const authHeader = Array.isArray(input.authorization)
    ? input.authorization[0]
    : input.authorization;
  const fromBrowser = authHeader?.replace(/^Bearer\s+/i, "").trim();
  const apiKey = fromBrowser || serverApiKey();
  if (!apiKey) {
    return {
      status: 401,
      body: {
        error: {
          message:
            "No API key on the server. On Hostinger add api/config.local.php (see the example file). For local Vite set XAI_API_KEY.",
        },
      },
    };
  }

  const messages = input.payload.messages;
  if (!Array.isArray(messages) || messages.length === 0) {
    return { status: 400, body: { error: { message: "messages are required" } } };
  }

  const coerced = coerceUpstream(input.payload.baseURL, input.payload.model);

  let target: URL;
  try {
    target = assertSafeBaseURL(coerced.baseURL);
  } catch (error) {
    return {
      status: 400,
      body: { error: { message: error instanceof Error ? error.message : "Invalid base URL" } },
    };
  }

  const endpoint = `${target.toString().replace(/\/$/, "")}/chat/completions`;

  const upstream = await fetch(endpoint, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      Authorization: `Bearer ${apiKey}`,
    },
    body: JSON.stringify({
      model: coerced.model,
      messages,
      temperature: input.payload.temperature ?? 0.4,
    }),
  });

  const text = await upstream.text();
  let parsed: Record<string, unknown> = {};
  try {
    parsed = text ? (JSON.parse(text) as Record<string, unknown>) : {};
  } catch {
    parsed = { error: { message: text.slice(0, 240) || "Unexpected API response" } };
  }
  return { status: upstream.status, body: parsed };
}
