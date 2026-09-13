# SpanishTranslator

Bilingual texting helper for Luis. Draft in English, polish the wording, translate to natural Spanish, then open Apple Messages with Luis’s number and the Spanish text filled in. When Luis replies, paste the Spanish or a screenshot; the app shows clear English.

Nothing here can send SMS by itself or read the Messages inbox. You always tap Send in Messages.

## Current path: phone website (no Mac)

If you are on **Windows** (or any machine without Xcode), use the web app:

**[web/](web/)** — mobile-first Vite + React site. Open it in **Safari on your iPhone**.

```bat
cd web
npm install
npm run dev
```

Host the production site on **Hostinger** at **`https://translate.deliverydave.ai`** (static `web/dist` upload). Grok host/model are built in; put the xAI key once in Hostinger `api/config.local.php` (never in git). Details: **[web/README.md](web/README.md)**.

That is the supported way to use SpanishTranslator today.

## Plan B: native iPhone app (needs a Mac)

`SpanishTranslator.xcodeproj` is a complete SwiftUI iOS 17+ app with the same flows (Keychain for the API key, Contacts, MessageUI, Vision OCR). Keep it for later if you get a Mac or use a cloud Mac. It is **not** deleted by the web work.

To run it later:

1. Open `SpanishTranslator.xcodeproj` in Xcode 16+ on a Mac.
2. Choose your Apple Developer team under Signing.
3. Run on a physical iPhone.

Details that used to live here (permissions, simulator vs device) still apply to that project only.

## What both versions do

1. **Compose** — rough English notes → polished draft → tweaks until it sounds right.
2. **Translate** — English → conversational Spanish (editable).
3. **Send** — open Messages with the saved Luis number and Spanish body. You tap Send.
4. **Inbox** — paste Spanish or a screenshot → extract text → English.
5. **Settings** — Luis name + phone; OpenAI-compatible API key, base URL, model; test connection.

## Layout

```
web/                         ← use this (Windows + iPhone Safari)
  README.md                  Windows run + deliverydave.ai deploy
  src/                       Compose, Inbox, Settings
  api/chat.ts                Vite / Vercel CORS proxy
  public/api/chat.php        Hostinger CORS proxy (static + PHP)
SpanishTranslator.xcodeproj  ← Plan B (Mac / Xcode)
SpanishTranslator/           SwiftUI sources
```
