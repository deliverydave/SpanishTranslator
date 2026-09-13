export const DEFAULT_BASE_URL = "https://api.x.ai/v1";
export const DEFAULT_MODEL = "grok-4.6";
export const SPEECH_RATE = 0.8;

export function isLegacyOpenAISettings(baseURL: string, model: string): boolean {
  const url = baseURL.toLowerCase();
  const name = model.toLowerCase();
  return url.includes("openai.com") || name === "gpt-4o-mini" || name.startsWith("gpt-3");
}
