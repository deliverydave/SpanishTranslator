import { describe, expect, it } from "vitest";
import { digitsPhone, smsHref } from "./sms";

describe("digitsPhone", () => {
  it("keeps a leading plus and strips other punctuation", () => {
    expect(digitsPhone("+1 (555) 123-4567")).toBe("+15551234567");
  });

  it("keeps local numbers without inventing a plus", () => {
    expect(digitsPhone("555-0100")).toBe("5550100");
  });
});

describe("smsHref", () => {
  it("uses iOS sms:number&body= style", () => {
    const href = smsHref("+15551234567", "¿Llueve mañana?");
    expect(href.startsWith("sms:+15551234567")).toBe(true);
    expect(href).toContain("body=");
    expect(href).toContain(encodeURIComponent("¿Llueve mañana?"));
  });

  it("still builds a body-only link without a phone number", () => {
    const href = smsHref("", "Hola");
    expect(href.includes("body=")).toBe(true);
    expect(href).toContain(encodeURIComponent("Hola"));
  });
});
