import { useState } from "react";
import { smsHref, canOpenSms } from "../lib/sms";
import type { Contact } from "../lib/storage";
import { Banner } from "./Banner";

type Props = {
  english: string;
  spanish: string;
  contact: Contact;
  onSpanishChange: (value: string) => void;
  onClose: () => void;
};

export function SendSheet({
  english,
  spanish,
  contact,
  onSpanishChange,
  onClose,
}: Props) {
  const [copied, setCopied] = useState(false);
  const [note, setNote] = useState<string | null>(null);
  const canSend = spanish.trim().length > 0 && contact.phone.trim().length > 0;

  async function copySpanish() {
    try {
      await navigator.clipboard.writeText(spanish);
      setCopied(true);
      setNote("Spanish copied. Paste it into Messages if the link does not open.");
    } catch {
      setNote("Copy failed. Select the Spanish text and copy it manually.");
    }
  }

  function openMessages() {
    if (!canSend) {
      setNote("Add Luis’s phone number in Settings first.");
      return;
    }
    const href = smsHref(contact.phone, spanish);
    window.location.href = href;
    if (!canOpenSms()) {
      void copySpanish();
      setNote(
        "This browser cannot open Messages. Spanish was copied so you can paste it.",
      );
    }
  }

  return (
    <div className="sheet-backdrop" onClick={onClose} role="presentation">
      <div
        className="sheet"
        role="dialog"
        aria-label="Send to Luis"
        onClick={(event) => event.stopPropagation()}
      >
        <div className="topbar" style={{ position: "static", padding: 0, border: 0 }}>
          <h1>Send to Luis</h1>
          <button className="ghost" type="button" onClick={onClose}>
            Close
          </button>
        </div>

        <div className="card">
          <div className="tiny">English</div>
          <div>{english}</div>
        </div>

        <label className="field">
          <span>Spanish (editable)</span>
          <textarea
            value={spanish}
            onChange={(event) => onSpanishChange(event.target.value)}
          />
        </label>

        <div className="card contact-row">
          <div className="avatar">{(contact.displayName || "L").slice(0, 1)}</div>
          <div>
            <strong>{contact.displayName || "Luis"}</strong>
            <div className="muted tiny" style={{ textTransform: "none" }}>
              {contact.phone || "Add a phone number in Settings"}
            </div>
          </div>
        </div>

        {note ? <Banner text={note} tone="info" /> : null}

        <button className="primary" type="button" disabled={!canSend} onClick={openMessages}>
          Open Messages
        </button>
        <button className="secondary" type="button" onClick={() => void copySpanish()}>
          {copied ? "Copied" : "Copy Spanish"}
        </button>
        <p className="muted tiny" style={{ textTransform: "none", fontWeight: 400 }}>
          Safari opens Messages with Luis and this text filled in. You still tap Send.
          The site cannot send SMS by itself.
        </p>
      </div>
    </div>
  );
}
