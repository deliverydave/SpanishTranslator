import { complete, looksVisionCapable } from "./api";
import { EXTRACT_SPANISH } from "./prompts";
import { loadApiSettings } from "./storage";

function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.onload = () => resolve(image);
    image.onerror = () => reject(new Error("Could not read that image."));
    image.src = src;
  });
}

export async function fileToDataUrl(file: Blob): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = () => reject(new Error("Could not read that file."));
    reader.readAsDataURL(file);
  });
}

export async function compressImage(file: Blob, maxEdge = 1280): Promise<string> {
  const original = await fileToDataUrl(file);
  const image = await loadImage(original);
  const longest = Math.max(image.width, image.height);
  const scale = longest > maxEdge ? maxEdge / longest : 1;
  const width = Math.max(1, Math.round(image.width * scale));
  const height = Math.max(1, Math.round(image.height * scale));
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext("2d");
  if (!ctx) return original;
  ctx.drawImage(image, 0, 0, width, height);
  return canvas.toDataURL("image/jpeg", 0.85);
}

function cleanOcrLines(text: string): string {
  const ignored = [
    "imessage",
    "text message",
    "delivered",
    "sms",
    "tapback",
    "edited",
  ];
  return text
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => {
      const lower = line.toLowerCase();
      if (!line) return false;
      if (ignored.includes(lower)) return false;
      if (lower.startsWith("delivered") || lower.startsWith("read ")) return false;
      return true;
    })
    .join("\n")
    .trim();
}

async function tesseractExtract(dataUrl: string): Promise<string> {
  const { createWorker } = await import("tesseract.js");
  const worker = await createWorker("spa+eng");
  try {
    const result = await worker.recognize(dataUrl);
    return cleanOcrLines(result.data.text);
  } finally {
    await worker.terminate();
  }
}

async function visionExtract(dataUrl: string): Promise<string> {
  const text = await complete([
    { role: "system", content: EXTRACT_SPANISH },
    {
      role: "user",
      content: [
        { type: "text", text: "Extract the Spanish SMS from this screenshot." },
        { type: "image_url", image_url: { url: dataUrl } },
      ],
    },
  ]);
  if (text === "NO_TEXT") return "";
  return text.trim();
}

export async function extractSpanishFromImage(
  file: Blob,
  onStatus?: (message: string) => void,
): Promise<{ text: string; source: string }> {
  const dataUrl = await compressImage(file);
  const settings = loadApiSettings();
  const canVision = looksVisionCapable(settings.model);

  if (canVision) {
    onStatus?.("Reading screenshot with the vision model…");
    const text = await visionExtract(dataUrl);
    if (text) return { text, source: "Vision model" };
  }

  onStatus?.("Reading screenshot with on-device OCR…");
  const text = await tesseractExtract(dataUrl);
  if (!text) {
    throw new Error(
      "No text was found in that image. Crop closer to the message bubble and try again.",
    );
  }
  return { text, source: "Tesseract OCR" };
}
