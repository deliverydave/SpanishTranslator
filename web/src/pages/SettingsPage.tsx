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
  const [showAdvanced, setShowAdvanced] = useState(false);
  const [status, setStatus] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [testing, setTesting] = useState(false);

  function saveRecipient() {
    saveContact({ displayName: name, phone });
    setStatus(`Saved ${name.trim() || "Luis"} as the default recipient.`);
    setError(null);
    onChanged();
  }

  function persistOverride() {
    const value = apiKey.trim();
    saveApiSettings({ baseURL, model });
    if (value.startsWith("••")) {
      setStatus("Optional override unchanged.");
      setError(null);
      onChanged();
      return;
    }
    saveApiKey(value, remember);
    setApiKey(value ? "••••••••" : "");
    setStatus(
      value
        ? "Browser key saved. This overrides the Hostinger server key on this device only."
        : "Browser key cleared. The app will use the server key.",
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
          <div className="tiny">Grok</div>
          <p className="muted">
            Host and model are built in (<code>{DEFAULT_API.baseURL}</code>,{" "}
            <code>{DEFAULT_API.model}</code>). The xAI key lives on the server in{" "}
            <code>api/config.local.php</code> — you do not paste it on each phone.
          </p>
          <button
            className="secondary"
            type="button"
            disabled={testing}
            onClick={() => void test()}
          >
            {testing ? "Testing…" : "Test connection"}
          </button>
        </section>

        <section className="card">
          <button
            className="ghost"
            type="button"
            onClick={() => setShowAdvanced((open) => !open)}
          >
            {showAdvanced ? "Hide advanced" : "Advanced (optional browser key)"}
          </button>
          {showAdvanced ? (
            <>
              <p className="muted">
                Only if you want this browser to use a different key than Hostinger.
              </p>
              <label className="field">
                <span>API key override</span>
                <input
                  type={showKey ? "text" : "password"}
                  autoComplete="off"
                  value={apiKey}
                  onChange={(event) => setApiKey(event.target.value)}
                  placeholder="Leave empty to use the server key"
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
                <span>Remember override on this device</span>
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
              <button className="secondary" type="button" onClick={persistOverride}>
                Save override
              </button>
              {existingKey ? (
                <button
                  className="danger"
                  type="button"
                  onClick={() => {
                    clearApiKey();
                    setApiKey("");
                    setStatus("Browser override removed. Using the server key.");
                    onChanged();
                  }}
                >
                  Remove browser key
                </button>
              ) : null}
            </>
          ) : null}
        </section>

        {status ? <Banner text={status} tone="info" /> : null}
        {error ? <Banner text={error} /> : null}

        <section className="card">
          <div className="tiny">How this works</div>
          <p>1. Compose a text in English, then refine it until it sounds right.</p>
          <p>2. Translate to Spanish. Speak it aloud if Luis is next to you, or open Messages.</p>
          <p>3. When Luis replies, paste the Spanish or a screenshot.</p>
        </section>
      </main>
    </>
  );
}
