# SpanishTranslator web app

Mobile-first website for bilingual texting with Luis. Open it in **Safari on an iPhone**. You do **not** need a Mac.

Draft English → polish in a chat → translate to Spanish → open Messages with Luis’s number and the Spanish body filled in. When Luis replies, paste the text or a screenshot.

The site cannot send SMS by itself or read the Messages inbox. You always tap Send in Messages.

**Public URL (default):** [https://translate.deliverydave.ai](https://translate.deliverydave.ai) — a subdomain of **deliverydave.ai** so the existing root site is not overwritten. `https://deliverydave.ai/translator/` is the fallback if you prefer a folder on the main site (see below). DNS and SSL are steps you run in your host’s panel; this repo has no domain credentials.

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

## Deploy on Hostinger (primary)

**deliverydave.ai is on Hostinger.** Ship a **static Vite build** (`web\dist\`) there. Do not point the root domain at this app.

| Choice | URL | When to use |
|---|---|---|
| **Recommended** | `https://translate.deliverydave.ai` | New subdomain + folder `public_html/translate` — no collision with the existing site |
| Fallback | `https://deliverydave.ai/translator/` | Subfolder on the main site if your plan makes subdomains awkward (needs a Vite `base` change; see below) |

OpenAI blocks browser calls (CORS). Hostinger uses `api/chat.php` plus `.htaccess` (both land in `dist/` on build). PHP + curl are on by default. **Do not** store your API key in hPanel — paste it in the app Settings.

This repo has no Hostinger or DNS passwords.

### Checklist

1. Build `web\dist\` on Windows.
2. In hPanel, create subdomain `translate` → folder `public_html/translate` → enable SSL.
3. Upload the **inside** of `dist\` (including hidden `.htaccess`).
4. Open `https://translate.deliverydave.ai` on the iPhone.

### 1. Build on Windows

```bat
cd web
npm install
npm run build
```

Windows Explorer: `SpanishTranslator\web\dist\`

Upload **that folder’s contents**, not `web` and not a nested extra `dist`. After upload, `public_html/translate/index.html` should exist.

Must include:

- `index.html`, `assets\`, `manifest.webmanifest`, icons
- `api\chat.php` (CORS proxy)
- `.htaccess` (rewrites `/api/chat` and passes the Authorization header)

### 2. Subdomain + DNS in Hostinger hPanel

1. Log in to [hPanel](https://hpanel.hostinger.com).
2. Open **deliverydave.ai**.
3. **Subdomains** (sometimes under *Domains* or *DNS / Zone Editor*).
4. Create **`translate`**. Custom folder: `public_html/translate` (create it if hPanel does not).
5. **SSL**: enable Let’s Encrypt / SSL for `translate.deliverydave.ai`. iPhone Safari needs HTTPS.

If hPanel did not add DNS: **DNS / Zone Editor** → **A** `translate` → same IPv4 as `deliverydave.ai`, or **CNAME** `translate` → `deliverydave.ai`. Leave TTL default. **Do not** edit the root `@` A record.

### 3. Upload `dist/` (File Manager or FTP)

**File Manager**

1. hPanel → **Files** → **File Manager**.
2. Enable **Show hidden files** (otherwise `.htaccess` will not upload).
3. Open `public_html/translate`.
4. Upload the *contents* of `web\dist`. Zip option: zip the files *inside* `dist`, upload, extract, delete the zip.

**FTP (FileZilla or Windows)**

- Host / user / password: hPanel → **Files** → **FTP Accounts** (this repo does not have them).
- Remote directory: `/public_html/translate`
- Upload the contents of `web\dist`, including `.htaccess`.

### 4. Open it on the iPhone

Visit `https://translate.deliverydave.ai`. In Safari: **Share → Add to Home Screen**.

If compose/translate returns a 404 on `/api/chat`, `.htaccess` did not upload or Apache rewrites are off. Upload `.htaccess` again (it is a hidden file — show hidden files in File Manager). If PHP is disabled, ask Hostinger support to enable PHP curl for that site.

### Optional: `/translator` on the root site

Upload `dist/` into `public_html/translator`. You would then need the built asset paths to include that prefix (`vite.config.ts` `base: '/translator/'`) — not the default. Prefer the subdomain unless you want to change `base`.

## Optional: Vercel or Netlify (custom domain on deliverydave.ai)

Use these only if you want a managed Node `/api/chat` proxy instead of Hostinger PHP. The public hostname should still be **`translate.deliverydave.ai`**. Do not put your OpenAI key in host environment variables — paste it in the app Settings.

This repo cannot log into Vercel, Netlify, or your DNS. You attach the domain yourself.

### Vercel → translate.deliverydave.ai

1. [vercel.com/new](https://vercel.com/new) → import `deliverydave/SpanishTranslator`.
2. **Root Directory** = `web`. Framework Vite, build `npm run build`, output `dist`. Deploy.
3. Project → **Settings → Domains** → add `translate.deliverydave.ai`.
4. Vercel shows a DNS record (usually a **CNAME** `translate` → `cname.vercel-dns.com`, sometimes an A record). Copy it exactly.
5. In Hostinger hPanel → **DNS / Zone Editor** for `deliverydave.ai`, add that record. Do **not** change the root `@` A record (that would move deliverydave.ai itself).
6. Wait for DNS (often minutes, sometimes up to a few hours). Vercel will issue HTTPS. Open `https://translate.deliverydave.ai`.

If `translate` already points at Hostinger `public_html/translate`, remove or replace that Hostinger A/CNAME first — a name can only point at one place.

### Netlify → translate.deliverydave.ai

1. New site → this GitHub repo → base directory `web`, publish `dist`, build `npm run build`.
2. Site → **Domain management** → **Add custom domain** → `translate.deliverydave.ai`.
3. Netlify shows a **CNAME** (often `translate` → `something.netlify.app`). Add that in Hostinger DNS the same way as above.
4. The checked-in serverless function is written for **Vercel**. On Netlify, `/api/chat` will 404 unless you add a Netlify Function. Prefer Hostinger (PHP already in `dist`) or Vercel.

### GitHub Pages

Static only — no `/api/chat` proxy. Skip unless you host the proxy somewhere else.

## Add to Home Screen

In Safari: **Share → Add to Home Screen**. The app has a web manifest, viewport, theme color, and apple-touch icon.

## Native iOS app (Plan B)

The SwiftUI project at the repo root still exists. It needs a Mac + Xcode. Use this `web/` app until then. See the root README.
