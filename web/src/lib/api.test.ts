import { describe, expect, it } from "vitest";
import { looksVisionCapable, stripWrappingQuotes } from "./api";

describe("stripWrappingQuotes", () => {
  it("removes a surrounding quote pair", () => {
    expect(stripWrappingQuotes('"Hola Luis"')).toBe("Hola Luis");
  });
});

describe("looksVisionCapable", () => {
  it("treats gpt-4o-mini as vision-capable", () => {
    expect(looksVisionCapable("gpt-4o-mini")).toBe(true);
    expect(looksVisionCapable("gpt-3.5-turbo")).toBe(false);
  });
});
