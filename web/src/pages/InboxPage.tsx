import { useRef, useState } from "react";
import { Banner } from "../components/Banner";
import { SpeakButton } from "../components/SpeakButton";
import { complete } from "../lib/api";
import { extractSpanishFromImage } from "../lib/ocr";
import { ES_TO_EN } from "../lib/prompts";
import { getApiKey } from "../lib/storage";

export function InboxPage() {
  const [spanish, setSpanish] = useState("");
  const [english, setEnglish] = useState("");
  const [source, setSource] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [copied, setCopied] = useState(false);
  const fileRef = useRef<HTMLInputElement>(null);

  async function pasteText() {
    setError(null);
    try {
      const text = (await navigator.clipboard.readText()).trim();
      if (!text) {
        setError("Nothing to paste. Copy Luis’s Spanish text first.");
        return;
      }
      setSpanish(text);
      setEnglish("");
      setSource("Pasted text");
    } catch {
      setError("Safari blocked clipboard access. Long-press in the Spanish box and choose Paste.");
    }
  }

  async function handleImage(file: Blob) {
    setBusy(true);
    setError(null);
    setEnglish("");
    try {
      const result = await extractSpanishFromImage(file, setStatus);
      setSpanish(result.text);
      setSource(result.source);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Could not read that screenshot.");
    } finally {
      setBusy(false);
      setStatus(null);
    }
  }

  async function pasteImage() {
    setError(null);
    try {
      const items = await navigator.clipboard.read();
      for (const item of items) {
        const type = item.types.find((value) => value.startsWith("image/"));
        if (type) {
          const blob = await item.getType(type);
          await handleImage(blob);
          return;
        }
      }
      setError("No screenshot on the clipboard. Copy the bubble image, or choose a photo.");
    } catch {
      setError("Could not read a clipboard image. Use Choose screenshot, or paste into the drop zone.");
    }
  }

  async function translate() {
    const text = spanish.trim();
    if (!text) {
      setError("Paste or read the Spanish first.");
      return;
    }
    if (!getApiKey()) {
      setError("Add an API key in Settings to translate.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const result = await complete([
        { role: "system", content: ES_TO_EN },
        { role: "user", content: text },
      ]);
      setEnglish(result);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Translation failed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <>
      <header className="topbar">
        <span />
        <h1>Inbox</h1>
        <button
          className="ghost"
          type="button"
          onClick={() => {
            setSpanish("");
            setEnglish("");
            setSource(null);
            setError(null);
            setCopied(false);
          }}
        >
          Clear
        </button>
      </header>

      <main className="page">
        <div className="card">
          <strong>Luis replied</strong>
          <p className="muted">
            Paste the Spanish text, or drop a screenshot of the Messages bubble.
            A vision model reads it when your key is set; otherwise Tesseract OCR
            runs in the browser.
          </p>
          {source ? <div className="tiny">{source}</div> : null}
        </div>

        <div
          className="dropzone"
          tabIndex={0}
          onPaste={(event) => {
            const file = [...event.clipboardData.files].find((item) =>
              item.type.startsWith("image/"),
            );
            if (file) {
              event.preventDefault();
              void handleImage(file);
            }
          }}
        >
          <p>
            <strong>Paste a screenshot here</strong>
            <br />
            <span className="muted">or choose a photo from your library</span>
          </p>
          <div className="row">
            <button className="secondary" type="button" onClick={() => void pasteText()}>
              Paste text
            </button>
            <button className="secondary" type="button" onClick={() => void pasteImage()}>
              Paste image
            </button>
          </div>
          <button
            className="primary"
            type="button"
            style={{ marginTop: 8, width: "100%" }}
            onClick={() => fileRef.current?.click()}
          >
            Choose screenshot
          </button>
          <input
            ref={fileRef}
            className="hidden-file"
            type="file"
            accept="image/*"
            onChange={(event) => {
              const file = event.target.files?.[0];
              if (file) void handleImage(file);
              event.target.value = "";
            }}
          />
        </div>

        <label className="field">
          <span>Spanish (editable)</span>
          <textarea
            value={spanish}
            onChange={(event) => setSpanish(event.target.value)}
            placeholder="Spanish from Luis…"
          />
        </label>

        {busy || status ? (
          <div className="working">{status || "Working…"}</div>
        ) : null}

        <button
          className="primary"
          type="button"
          disabled={!spanish.trim() || busy}
          onClick={() => void translate()}
        >
          Translate to English
        </button>

        {english ? (
          <div className="card sand">
            <div className="tiny">English</div>
            <div>{english}</div>
            <div className="row">
              <SpeakButton text={english} lang="en" label="Read aloud" variant="ghost" />
              <button
                className="ghost"
                type="button"
                onClick={() => {
                  void navigator.clipboard.writeText(english);
                  setCopied(true);
                }}
              >
                {copied ? "Copied" : "Copy"}
              </button>
            </div>
          </div>
        ) : null}

        {error ? <Banner text={error} /> : null}
      </main>
    </>
  );
}
