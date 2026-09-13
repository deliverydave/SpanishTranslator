import { SPEECH_RATE } from "./defaults";

export type SpeakLang = "es" | "en";

const LANG_TAGS: Record<SpeakLang, string[]> = {
  es: ["es-MX", "es-US", "es-419", "es-ES", "es"],
  en: ["en-US", "en-GB", "en"],
};

export function canSpeak(): boolean {
  return typeof window !== "undefined" && "speechSynthesis" in window;
}

export function preferredLangTag(lang: SpeakLang): string {
  return LANG_TAGS[lang][0];
}

export function pickVoice(
  voices: Array<{ lang: string; name: string }>,
  lang: SpeakLang,
): { lang: string; name: string } | null {
  const wanted = LANG_TAGS[lang].map((tag) => tag.toLowerCase());
  const normalized = voices.map((voice) => ({
    voice,
    tag: voice.lang.replace("_", "-").toLowerCase(),
  }));

  for (const prefix of wanted) {
    const exact = normalized.find((entry) => entry.tag === prefix);
    if (exact) return exact.voice;
  }
  for (const prefix of wanted) {
    const starts = normalized.find((entry) => entry.tag.startsWith(prefix));
    if (starts) return starts.voice;
  }
  return null;
}

function loadVoices(): SpeechSynthesisVoice[] {
  return window.speechSynthesis.getVoices();
}

export function speakText(text: string, lang: SpeakLang): SpeechSynthesisUtterance | null {
  const trimmed = text.trim();
  if (!trimmed || !canSpeak()) return null;

  window.speechSynthesis.cancel();

  const utterance = new SpeechSynthesisUtterance(trimmed);
  utterance.lang = preferredLangTag(lang);
  utterance.rate = SPEECH_RATE;
  const match = pickVoice(loadVoices(), lang);
  if (match) {
    const live = loadVoices().find((voice) => voice.name === match.name && voice.lang === match.lang);
    if (live) utterance.voice = live;
    utterance.lang = match.lang.replace("_", "-");
  }
  window.speechSynthesis.speak(utterance);
  return utterance;
}

export function stopSpeaking(): void {
  if (canSpeak()) window.speechSynthesis.cancel();
}
