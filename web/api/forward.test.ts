import { describe, expect, it } from "vitest";
import { assertSafeBaseURL, handleChatRequest, normalizeBaseURL } from "./forward";

describe("normalizeBaseURL", () => {
  it("adds https and /v1 for api.openai.com", () => {
    expect(normalizeBaseURL("api.openai.com")).toBe("https://api.openai.com/v1");
  });

  it("strips a trailing chat/completions path", () => {
    expect(normalizeBaseURL("https://api.openai.com/v1/chat/completions")).toBe(
      "https://api.openai.com/v1",
    );
  });
});

describe("assertSafeBaseURL", () => {
  it("rejects http and localhost", () => {
    expect(() => assertSafeBaseURL("http://api.openai.com/v1")).toThrow(/https/);
    expect(() => assertSafeBaseURL("https://localhost/v1")).toThrow(/not allowed/);
    expect(() => assertSafeBaseURL("https://127.0.0.1/v1")).toThrow(/not allowed/);
    expect(() => assertSafeBaseURL("https://192.168.1.9/v1")).toThrow(/not allowed/);
  });

  it("allows OpenAI and OpenRouter", () => {
    expect(assertSafeBaseURL("https://api.openai.com/v1").hostname).toBe("api.openai.com");
    expect(assertSafeBaseURL("https://openrouter.ai/api/v1").hostname).toBe("openrouter.ai");
  });
});

describe("handleChatRequest", () => {
  it("requires POST and an API key", async () => {
    const missing = await handleChatRequest({
      method: "POST",
      payload: { messages: [{ role: "user", content: "hi" }] },
    });
    expect(missing.status).toBe(401);

    const wrong = await handleChatRequest({
      method: "GET",
      authorization: "Bearer sk-test",
      payload: { messages: [{ role: "user", content: "hi" }] },
    });
    expect(wrong.status).toBe(405);
  });
});
