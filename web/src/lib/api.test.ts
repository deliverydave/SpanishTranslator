import { describe, expect, it } from "vitest";
import { looksVisionCapable, stripWrappingQuotes } from "./api";

describe("stripWrappingQuotes", () => {
  it("removes a surrounding quote pair", () => {
    expect(stripWrappingQuotes('"Hola Luis"')).toBe("Hola Luis");
  });
});

describe("looksVisionCapable", () => {
  it("treats grok-4.6 as vision-capable", () => {
    expect(looksVisionCapable("grok-4.6")).toBe(true);
    expect(looksVisionCapable("gpt-3.5-turbo")).toBe(false);
  });
});
