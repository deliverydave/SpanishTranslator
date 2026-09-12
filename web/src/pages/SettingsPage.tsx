import { useState } from "react";
import { Banner } from "../components/Banner";
import { ping } from "../lib/api";
import {
  clearApiKey,
  DEFAULT_API,
  getApiKey,
  loadApiSettings,
  loadContact,
  remembersKey,
  saveApiKey,
  saveApiSettings,
  saveContact,
} from "../lib/storage";

export function SettingsPage({ onChanged }: { onChanged: () => void }) {
  const contact = loadContact();
  const api = loadApiSettings();
  const existingKey = getApiKey();
  const [name, setName] = useState(contact.displayName);
  const [phone, setPhone] = useState(contact.phone);
  const [baseURL, setBaseURL] = useState(api.baseURL);
  const [model, setModel] = useState(api.model);
  const [apiKey, setApiKey] = useState(existingKey ? "••••••••" : "");
  const [showKey, setShowKey] = useState(false);
  const [remember, setRemember] = useState(remembersKey());
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [testing, setTesting] = useState(false);

  function saveRecipient() {
    saveContact({ displayName: name, phone });
    setStatus(`Saved ${name.trim() || "Luis"} as the default recipient.`);
    setError(null);
    onChanged();
  }

  function persistKey() {
    const value = apiKey.trim();
    saveApiSettings({ baseURL, model });
    if (value.startsWith("••")) {
      setStatus("API host/model saved. Key unchanged. Paste a new key to replace it.");
      setError(null);
      onChanged();
      return;
    }
    saveApiKey(value, remember);
    setApiKey(value ? "••••••••" : "");
    setStatus(
      value
        ? remember
          ? "API key saved on this device (localStorage)."
          : "API key kept for this tab only (sessionStorage)."
        : "API key cleared.",
    );
    setError(null);
    onChanged();
  }

  async function test() {
    setTesting(true);
    setError(null);
    try {
      if (apiKey.trim() && !apiKey.startsWith("••")) {
        saveApiKey(apiKey, remember);
      }
      saveApiSettings({ baseURL, model });
      const reply = await ping();
      setStatus(`Connected. Model replied: ${reply}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Connection failed.");
    } finally {
      setTesting(false);
    }
  }

  return (
    <>
      <header className="topbar">
        <span />
        <h1>Settings</h1>
        <span />
      </header>
      <main className="page">
        <section className="card">
          <div className="tiny">Default contact</div>
          <label className="field">
            <span>Name</span>
            <input
              type="text"
              autoComplete="name"
              value={name}
              onChange={(event) => setName(event.target.value)}
            />
          </label>
          <label className="field">
            <span>Phone number</span>
            <input
              type="tel"
              autoComplete="tel"
              placeholder="+1 555 555 1234"
              value={phone}
              onChange={(event) => setPhone(event.target.value)}
            />
          </label>
          <button className="primary" type="button" onClick={saveRecipient}>
            Save name & number
          </button>
        </section>

        <section className="card">
          <div className="tiny">Language model</div>
          <label className="field">
            <span>API key</span>
            <input
              type={showKey ? "text" : "password"}
              autoComplete="off"
              value={apiKey}
              onChange={(event) => setApiKey(event.target.value)}
              placeholder="sk-…"
            />
          </label>
          <label className="switch">
            <input
              type="checkbox"
              checked={showKey}
              onChange={(event) => setShowKey(event.target.checked)}
            />
            <span>Show key while editing</span>
          </label>
          <label className="switch">
            <input
              type="checkbox"
              checked={remember}
              onChange={(event) => setRemember(event.target.checked)}
            />
            <span>
              Remember on this device (localStorage). Unchecked = this tab only
              (sessionStorage). A shared phone can read a remembered key.
            </span>
          </label>
          <label className="field">
            <span>Base URL</span>
            <input
              type="url"
              value={baseURL}
              onChange={(event) => setBaseURL(event.target.value)}
              placeholder={DEFAULT_API.baseURL}
            />
          </label>
          <label className="field">
            <span>Model</span>
            <input
              type="text"
              value={model}
              onChange={(event) => setModel(event.target.value)}
              placeholder={DEFAULT_API.model}
            />
          </label>
          <div className="row">
            <button className="primary" type="button" onClick={persistKey}>
              Save API settings
            </button>
            <button
              className="secondary"
              type="button"
              disabled={testing}
              onClick={() => void test()}
            >
              {testing ? "Testing…" : "Test connection"}
            </button>
          </div>
          {existingKey ? (
            <button
              className="danger"
              type="button"
              onClick={() => {
                clearApiKey();
                setApiKey("");
                setStatus("API key removed from this browser.");
                onChanged();
              }}
            >
              Remove API key
            </button>
          ) : null}
        </section>

        {status ? <Banner text={status} tone="info" /> : null}
        {error ? <Banner text={error} /> : null}

        <section className="card">
          <div className="tiny">How this works</div>
          <p>1. Compose a text in English, then refine it until it sounds right.</p>
          <p>2. Translate to Spanish. Edit if you want, then open Messages — you tap Send.</p>
          <p>3. When Luis replies, paste the Spanish or a screenshot.</p>
          <p className="muted">
            OpenAI and most compatible hosts block browser calls (CORS). This app
            sends chat requests through a same-origin <code>/api/chat</code> proxy.
            The key is still yours: it is sent as a header and is not stored on the
            server.
          </p>
        </section>
      </main>
    </>
  );
}
