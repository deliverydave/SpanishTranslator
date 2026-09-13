import { useEffect, useState } from "react";
import { canSpeak, speakText, stopSpeaking, type SpeakLang } from "../lib/speech";

type Props = {
  text: string;
  lang: SpeakLang;
  label: string;
  variant?: "primary" | "secondary" | "ghost";
};

export function SpeakButton({ text, lang, label, variant = "secondary" }: Props) {
  const [speaking, setSpeaking] = useState(false);
  const [supported] = useState(canSpeak);
  const empty = text.trim().length === 0;

  useEffect(() => {
    return () => stopSpeaking();
  }, []);

  function toggle() {
    if (!supported || empty) return;
    if (speaking) {
      stopSpeaking();
      setSpeaking(false);
      return;
    }
    const utterance = speakText(text, lang);
    if (!utterance) return;
    setSpeaking(true);
    utterance.onend = () => setSpeaking(false);
    utterance.onerror = () => setSpeaking(false);
  }

  if (!supported) {
    return (
      <p className="muted tiny" style={{ textTransform: "none", fontWeight: 400 }}>
        This browser cannot read aloud. Try Safari on iPhone.
      </p>
    );
  }

  const className = variant === "ghost" ? "ghost" : variant;
  return (
    <button
      className={className}
      type="button"
      disabled={empty}
      onClick={toggle}
      aria-pressed={speaking}
    >
      {speaking ? "Stop" : label}
    </button>
  );
}
