import { getApiKey, loadApiSettings } from "./storage";

export type TextMessage = {
  role: "system" | "user" | "assistant";
  content:
    | string
    | Array<
        | { type: "text"; text: string }
        | { type: "image_url"; image_url: { url: string } }
      >;
};

type ChatResponse = {
  choices?: Array<{ message?: { content?: string } }>;
  error?: { message?: string };
};

export function stripWrappingQuotes(text: string): string {
  let result = text.trim();
  if (result.startsWith('"') && result.endsWith('"') && result.length > 1) {
    result = result.slice(1, -1).trim();
  }
  return result;
}

export async function complete(
  messages: TextMessage[],
  temperature = 0.4,
): Promise<string> {
  const key = getApiKey();
  const settings = loadApiSettings();
  const headers: Record<string, string> = {
    "Content-Type": "application/json",
  };
  if (key) headers.Authorization = `Bearer ${key}`;

  const response = await fetch("/api/chat", {
    method: "POST",
    headers,
    body: JSON.stringify({
      baseURL: settings.baseURL,
      model: settings.model,
      messages,
      temperature,
    }),
  });

  let parsed: ChatResponse = {};
  try {
    parsed = (await response.json()) as ChatResponse;
  } catch {
    throw new Error("The API response could not be read.");
  }

  if (!response.ok) {
    const message =
      parsed.error?.message ||
      `API error ${response.status}. Check the server key (Hostinger config.local.php).`;
    throw new Error(message);
  }

  const text = parsed.choices?.[0]?.message?.content?.trim();
  if (!text) throw new Error("The model returned an empty reply.");
  return stripWrappingQuotes(text);
}

export async function ping(): Promise<string> {
  return complete(
    [
      {
        role: "user",
        content: "Reply with the single word pong and nothing else.",
      },
    ],
    0,
  );
}

export function looksVisionCapable(model: string): boolean {
  const name = model.toLowerCase();
  return [
    "gpt-4o",
    "gpt-4.1",
    "gpt-4.5",
    "gpt-5",
    "vision",
    "omni",
    "gemini",
    "claude",
    "grok",
    "llama-4",
  ].some((token) => name.includes(token));
}
