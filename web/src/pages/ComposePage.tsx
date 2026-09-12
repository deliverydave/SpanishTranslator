import { useEffect, useRef, useState } from "react";
import { Banner } from "../components/Banner";
import { SendSheet } from "../components/SendSheet";
import { complete } from "../lib/api";
import { COMPOSE_SYSTEM, EN_TO_ES } from "../lib/prompts";
import {
  clearCompose,
  getApiKey,
  loadCompose,
  loadContact,
  saveCompose,
  type Contact,
} from "../lib/storage";

type Turn = { id: string; role: "user" | "assistant"; text: string };

function uid() {
  return crypto.randomUUID();
}

export function ComposePage() {
  const [messages, setMessages] = useState<Turn[]>([]);
  const [currentDraft, setCurrentDraft] = useState("");
  const [input, setInput] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [working, setWorking] = useState(false);
  const [translating, setTranslating] = useState(false);
  const [spanish, setSpanish] = useState("");
  const [showSend, setShowSend] = useState(false);
  const [contact, setContact] = useState<Contact>(loadContact());
  const listRef = useRef<HTMLDivElement>(null);
  const hasKey = Boolean(getApiKey());

  useEffect(() => {
    const snapshot = loadCompose();
    setMessages(snapshot.messages);
    setCurrentDraft(snapshot.currentDraft);
    setContact(loadContact());
  }, []);

  useEffect(() => {
    saveCompose({ messages, currentDraft });
  }, [messages, currentDraft]);

  useEffect(() => {
    listRef.current?.lastElementChild?.scrollIntoView({ block: "end" });
  }, [messages, working]);

  async function polish() {
    const note = input.trim();
    if (!note || working) return;
    setError(null);
    setWorking(true);
    setInput("");
    const next = [...messages, { id: uid(), role: "user" as const, text: note }];
    setMessages(next);
    try {
      const polished = await complete([
        { role: "system", content: COMPOSE_SYSTEM },
        ...next.map((turn) => ({
          role: turn.role,
          content: turn.text,
        })),
      ]);
      setMessages((prev) => [
        ...prev,
        { id: uid(), role: "assistant", text: polished },
      ]);
      setCurrentDraft(polished);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not polish that draft.");
    } finally {
      setWorking(false);
    }
  }

  function useAsDraft() {
    const text = input.trim();
    if (!text) return;
    setCurrentDraft(text);
    setInput("");
  }

  async function translate() {
    const english = currentDraft.trim();
    if (!english) {
      setError("Polish an English draft before translating.");
      return;
    }
    setError(null);
    setTranslating(true);
    try {
      const result = await complete([
        { role: "system", content: EN_TO_ES },
        { role: "user", content: english },
      ]);
      setSpanish(result);
      setShowSend(true);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Translation failed.");
    } finally {
      setTranslating(false);
    }
  }

  return (
    <>
      <header className="topbar">
        <button
          className="ghost"
          type="button"
          disabled={messages.length === 0 && !currentDraft}
          onClick={() => {
            setMessages([]);
            setCurrentDraft("");
            setSpanish("");
            setError(null);
            clearCompose();
          }}
        >
          New
        </button>
        <h1>Compose</h1>
        <button
          className="ghost"
          type="button"
          disabled={!currentDraft.trim() || working || translating}
          onClick={() => void translate()}
        >
          {translating ? "…" : "Translate"}
        </button>
      </header>

      <main className="page">
        <div className="card contact-row">
          <div className="avatar">{(contact.displayName || "L").slice(0, 1)}</div>
          <div style={{ flex: 1 }}>
            <strong>{contact.displayName || "Luis"}</strong>
            <div className="muted">{contact.phone || "Add a number in Settings"}</div>
          </div>
          {!hasKey ? <span className="pill">API key needed</span> : null}
        </div>

        {currentDraft ? (
          <div className="card sand">
            <div className="tiny">English draft</div>
            <div>{currentDraft}</div>
          </div>
        ) : null}

        <div className="chat" ref={listRef}>
          {messages.length === 0 ? (
            <div className="card">
              <strong>Draft with Luis in English</strong>
              <p className="muted">
                Dump rough notes. The app polishes them into a text you actually
                want to send. Tweak until it sounds right, then translate to Spanish.
              </p>
              <p>
                <strong>You</strong>
                <br />
                25% chance of rain tomorrow. How about starting Monday?
              </p>
              <p>
                <strong>App</strong>
                <br />
                Hey Luis — about a 25% chance of rain tomorrow. Want to start Monday
                instead?
              </p>
            </div>
          ) : null}
          {messages.map((turn) => (
            <div key={turn.id} className={`bubble ${turn.role}`}>
              <div className="tiny" style={{ color: turn.role === "user" ? "rgba(255,255,255,0.8)" : undefined }}>
                {turn.role === "user" ? "You" : "Polished English"}
              </div>
              <div>{turn.text}</div>
            </div>
          ))}
          {working ? <div className="working">Polishing…</div> : null}
        </div>

        {error ? <Banner text={error} /> : null}

        {!hasKey ? (
          <Banner
            tone="info"
            text="Add an API key in Settings to polish drafts and translate."
          />
        ) : null}

        <div className="composer">
          <textarea
            rows={3}
            placeholder="Rough notes or tweaks…"
            value={input}
            onChange={(event) => setInput(event.target.value)}
            onKeyDown={(event) => {
              if (event.key === "Enter" && !event.shiftKey) {
                event.preventDefault();
                void polish();
              }
            }}
          />
          <button
            className="send"
            type="button"
            aria-label="Polish draft"
            disabled={!input.trim() || working || !hasKey}
            onClick={() => void polish()}
          >
            ↑
          </button>
        </div>
        <div className="row">
          <button
            className="secondary"
            type="button"
            disabled={!input.trim()}
            onClick={useAsDraft}
          >
            Use as draft
          </button>
          <button
            className="primary"
            type="button"
            disabled={!currentDraft.trim() || working || translating}
            onClick={() => void translate()}
          >
            {translating ? "Translating…" : "Translate to Spanish"}
          </button>
        </div>
      </main>

      {showSend ? (
        <SendSheet
          english={currentDraft}
          spanish={spanish}
          contact={contact}
          onSpanishChange={setSpanish}
          onClose={() => setShowSend(false)}
        />
      ) : null}
    </>
  );
}
