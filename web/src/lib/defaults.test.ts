import { describe, expect, it } from "vitest";
import { DEFAULT_BASE_URL, DEFAULT_MODEL, isLegacyOpenAISettings, SPEECH_RATE } from "./defaults";

describe("Grok defaults", () => {
  it("uses xAI and grok-4.6", () => {
    expect(DEFAULT_BASE_URL).toBe("https://api.x.ai/v1");
    expect(DEFAULT_MODEL).toBe("grok-4.6");
  });

  it("treats old OpenAI localStorage as stale", () => {
    expect(isLegacyOpenAISettings("https://api.openai.com/v1", "gpt-4o-mini")).toBe(true);
    expect(isLegacyOpenAISettings("https://api.x.ai/v1", "grok-4.6")).toBe(false);
  });

  it("keeps speech slower than 1", () => {
    expect(SPEECH_RATE).toBeGreaterThanOrEqual(0.75);
    expect(SPEECH_RATE).toBeLessThanOrEqual(0.85);
  });
});
