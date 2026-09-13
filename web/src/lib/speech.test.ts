import { describe, expect, it } from "vitest";
import { pickVoice, preferredLangTag } from "./speech";

describe("pickVoice", () => {
  const voices = [
    { lang: "en-US", name: "Samantha" },
    { lang: "es-ES", name: "Monica" },
    { lang: "es-MX", name: "Paulina" },
  ];

  it("prefers Mexican Spanish when present", () => {
    expect(pickVoice(voices, "es")).toEqual({ lang: "es-MX", name: "Paulina" });
  });

  it("falls back to any es-* voice", () => {
    expect(pickVoice([{ lang: "es-AR", name: "Diego" }], "es")).toEqual({
      lang: "es-AR",
      name: "Diego",
    });
  });

  it("returns null when there is no match", () => {
    expect(pickVoice([{ lang: "fr-FR", name: "Thomas" }], "es")).toBeNull();
  });
});

describe("preferredLangTag", () => {
  it("uses Latin American Spanish first", () => {
    expect(preferredLangTag("es")).toBe("es-MX");
  });
});
