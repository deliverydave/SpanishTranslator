# SpanishTranslator web app

Mobile-first website for bilingual texting with Luis. Open it in **Safari on an iPhone**. You do **not** need a Mac.

Draft English → polish in a chat → translate to Spanish → open Messages with Luis’s number and the Spanish body filled in. When Luis replies, paste the text or a screenshot.

The site cannot send SMS by itself or read the Messages inbox. You always tap Send in Messages.

## Run on Windows

You need [Node.js 18+](https://nodejs.org/) (LTS is fine).

```bat
cd web
npm install
npm run dev
```

Then either:

- On the same PC, open the URL Vite prints (usually `http://localhost:5173`).
- On your iPhone (same Wi‑Fi): Vite already listens on the LAN. On the PC, run `ipconfig` and find the wireless IPv4 address. On the phone open `http://THAT-IP:5173`.

`npm run test` runs unit tests. `npm run build` makes a production bundle in `web/dist`.

## First-run setup (in Safari)

1. Open **Settings**.
2. Enter Luis’s name and phone number → **Save name & number**.
3. Paste an OpenAI-compatible API key.
4. Leave **Base URL** as `https://api.openai.com/v1` and **Model** as `gpt-4o-mini`, or point at another compatible host (for example OpenRouter `https://openrouter.ai/api/v1`) and its model name.
5. Decide whether to **Remember on this device** (see below).
6. Tap **Save API settings**, then **Test connection**.

### API key storage (read this)

| Option | Where it lives | Tradeoff |
|---|---|---|
| Default (unchecked) | `sessionStorage` | Cleared when you close the Safari tab. Safer on a shared computer. |
| Remember on this device | `localStorage` | Survives reloads. Anyone with this iPhone/Safari profile can read it. This is **not** as safe as the iOS Keychain. |

The key is never committed to git. Chat calls go through a same-origin `/api/chat` proxy so the browser is not blocked by OpenAI CORS. The proxy forwards your `Authorization` header and does **not** keep the key on the server.

Do not check “remember” on a computer you do not trust.

## Happy paths

### Compose → Spanish → Messages

1. **Compose** — type rough notes (`25% chance of rain tomorrow. How about starting Monday?`) and tap ↑.
2. Send tweaks (`Good morning Luis`, `don’t say 25%, say seems like rain`).
3. The English draft card always shows the current full message. **Use as draft** skips the model and uses your typed English.
4. **Translate to Spanish**.
5. Edit the Spanish if you want. **Open Messages** uses an `sms:` link with Luis’s number and the encoded body. **Copy Spanish** is the fallback.
6. Tap **Send** in Messages.

On iPhone Safari the link looks like `sms:+1555…&body=…`. Android uses `sms:+1555…?body=…`.

### Inbox → English

1. **Inbox** — **Paste text**, or **Choose screenshot** / paste an image into the drop zone.
2. If a vision-capable model (default `gpt-4o-mini`) and an API key are set, the screenshot is sent to that model. Otherwise [Tesseract.js](https://tesseract.projectnaptha.com/) runs in the browser.
3. Edit the extracted Spanish, then **Translate to English**.

## Deploy to a public HTTPS URL (Vercel)

Safari on a phone will not call `http://your-pc:5173` once you leave home Wi‑Fi. A free [Vercel](https://vercel.com) project gives you `https://….vercel.app`.

You need your own Vercel account (GitHub login is the usual path). This repo does not include credentials.

1. Push this repository to GitHub (already the case if you are using the PR).
2. Go to [vercel.com/new](https://vercel.com/new) and import `deliverydave/SpanishTranslator`.
3. Set **Root Directory** to `web`.
4. Framework preset: Vite. Build command `npm run build`, output `dist`.
5. Deploy. Do **not** put your OpenAI key in Vercel environment variables for this MVP — each user pastes their key in Settings.

The `web/api/chat.ts` function is the CORS proxy. After deploy, open the `https://` URL in Safari. Share → **Add to Home Screen** for a PWA-style icon.

### Netlify

Same idea: base directory `web`, build `npm run build`, publish `dist`. Add a redirect so `/api/chat` is a Netlify Function if you use Netlify — the checked-in function is written for **Vercel**. Prefer Vercel unless you already use Netlify.

### GitHub Pages

Not a good fit. Pages is static only, so `/api/chat` would be missing and OpenAI CORS would block the browser.

## Add to Home Screen

In Safari: **Share → Add to Home Screen**. The app has a web manifest, viewport, theme color, and apple-touch icon.

## Native iOS app (Plan B)

The SwiftUI project at the repo root still exists. It needs a Mac + Xcode. Use this `web/` app until then. See the root README.
