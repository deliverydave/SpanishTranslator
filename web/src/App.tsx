import { useState } from "react";
import { ComposePage } from "./pages/ComposePage";
import { InboxPage } from "./pages/InboxPage";
import { SettingsPage } from "./pages/SettingsPage";

type Tab = "compose" | "inbox" | "settings";

export function App() {
  const [tab, setTab] = useState<Tab>("compose");
  const [settingsEpoch, setSettingsEpoch] = useState(0);

  return (
    <div className="app">
      {tab === "compose" ? <ComposePage key={settingsEpoch} /> : null}
      {tab === "inbox" ? <InboxPage key={settingsEpoch} /> : null}
      {tab === "settings" ? (
        <SettingsPage onChanged={() => setSettingsEpoch((value) => value + 1)} />
      ) : null}
      <nav className="tabs">
        <button
          className={tab === "compose" ? "tab active" : "tab"}
          type="button"
          onClick={() => setTab("compose")}
        >
          💬<small>Compose</small>
        </button>
        <button
          className={tab === "inbox" ? "tab active" : "tab"}
          type="button"
          onClick={() => setTab("inbox")}
        >
          📥<small>Inbox</small>
        </button>
        <button
          className={tab === "settings" ? "tab active" : "tab"}
          type="button"
          onClick={() => setTab("settings")}
        >
          ⚙️<small>Settings</small>
        </button>
      </nav>
    </div>
  );
}
