# SpanishTranslator web app

Mobile-first website for bilingual texting with Luis. Open it in **Safari on an iPhone**. You do **not** need a Mac.

Draft English → polish in a chat → translate to Spanish → open Messages with Luis’s number and the Spanish body filled in. When Luis replies, paste the text or a screenshot.

The phone website cannot send SMS by itself or read the Messages inbox — you always tap Send in Messages. A separate **Twilio webhook** on this same Hostinger folder (`sms/`) can bridge owner ↔ contact with Grok translation. Jobsite/ops only. See **[public/sms/README.md](public/sms/README.md)**.

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

Grok host and model are built in (`https://api.x.ai/v1`, `grok-4.6`). You do **not** paste an API key on each phone.

1. One time on Hostinger: create `api/config.local.php` with your xAI key (see deploy below).
2. On the phone, open **Settings** and save Luis’s name and phone number.
3. Optional: **Test connection**.

The key must never go in git, frontend JS, or the public `dist/` zip. It stays in the private PHP config on the server. Advanced Settings can still override the key in this browser only.

## Happy paths

### Compose → Spanish → Messages

1. **Compose** — type rough notes (`25% chance of rain tomorrow. How about starting Monday?`) and tap ↑.
2. Send tweaks (`Good morning Luis`, `don’t say 25%, say seems like rain`).
3. The English draft card always shows the current full message. **Use as draft** skips the model and uses your typed English.
4. **Translate to Spanish**.
5. Edit the Spanish if you want. If Luis is next to you, tap **Speak Spanish** — the phone reads the current Spanish out loud (your edits included). It does **not** send a text.
6. **Open Messages** is separate: an `sms:` link with Luis’s number and the encoded body. **Copy Spanish** is the fallback. Tap **Send** in Messages if you are texting.

On iPhone Safari the link looks like `sms:+1555…&body=…`. Android uses `sms:+1555…?body=…`.

### Inbox → English

1. **Inbox** — **Paste text**, or **Choose screenshot** / paste an image into the drop zone.
2. Screenshots go to Grok vision when the server key is set; otherwise [Tesseract.js](https://tesseract.projectnaptha.com/) runs in the browser.
3. Edit the extracted Spanish, then **Translate to English**.

## Read aloud

After **Translate to Spanish**, tap **Speak Spanish**. The phone speaks the text in the Spanish box (including edits) using the browser [Web Speech API](https://developer.mozilla.org/en-US/docs/Web/API/SpeechSynthesis) — no extra API key. It does **not** open Messages or send SMS.

The button becomes **Stop** while speaking, and stays disabled if the Spanish box is empty. Speech rate is slowed (~0.8) so Luis can follow in person. Safari on iPhone uses a Spanish voice when one is installed (`es-MX` preferred, then other `es-*`). Turn the ringer/silent switch off and raise volume if you hear nothing.

**Read aloud** is also on the English draft (Compose) and the English result (Inbox).

## Deploy on Hostinger (primary)

**deliverydave.ai is on Hostinger.** Ship a **static Vite build** (`web\dist\`) there. Do not point the root domain at this app.

| Choice | URL | When to use |
|---|---|---|
| **Recommended** | `https://translate.deliverydave.ai` | New subdomain + folder `public_html/translate` — no collision with the existing site |
| Fallback | `https://deliverydave.ai/translator/` | Subfolder on the main site if your plan makes subdomains awkward (needs a Vite `base` change; see below) |

xAI blocks browser calls (CORS). Hostinger uses `api/chat.php` plus `.htaccess` (both land in `dist/` on build). PHP + curl are on by default.

**One-time server key:** after you upload `dist`, create `api/config.local.php` on Hostinger (copy `api/config.local.php.example`). Put your xAI key there. Do not commit that file or leave it in the build folder you zip from Windows.

This repo has no Hostinger, DNS, or API-key secrets.

### Checklist

1. Build `web\dist\` on Windows.
2. In hPanel, create subdomain `translate` → folder `public_html/translate` → enable SSL.
3. Upload the **inside** of `dist\` (including hidden `.htaccess`).
4. Create `public_html/translate/api/config.local.php` from the example (xAI key). For the SMS bridge, also create `public_html/translate/sms/config.local.php` (see **[public/sms/README.md](public/sms/README.md)**). Redeploy `dist` anytime; **leave both config.local.php files in place**.
5. Open `https://translate.deliverydave.ai` on the iPhone.
6. Twilio webhook (after the number is on the DeliveryDave Messaging Service): `https://translate.deliverydave.ai/sms/twilio-webhook.php`

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
- `api\chat.php` and `api\config.local.php.example`
- `sms\` (Twilio webhook, `config.local.php.example`, privacy/terms). Copy `sms/config.local.php` on the server; never zip a real one from Windows.
- `.htaccess` (rewrites `/api/chat`, `/sms/twilio-webhook`, and `/sms/enroll`; blocks downloading `config.local.php`)

### 1b. Server API key (do this once)

1. In File Manager open `public_html/translate/api`.
2. Copy `config.local.php.example` to **`config.local.php`** (same folder as `chat.php`).
3. Edit `config.local.php` and set `'apiKey' => 'xai-…'` to your real xAI key.
4. Save. Confirm the file is **not** in git.

Safer alternative: put the same `config.local.php` **one level above** `public_html` (account root). `chat.php` looks there too.

Then redeploy later by uploading a fresh `dist` **without** deleting `api/config.local.php`.

### 1c. Twilio bilingual SMS bridge (Hostinger + Twilio)

The translator site still opens Apple Messages on the phone. The optional **jobsite SMS bridge** is a Hostinger PHP webhook at `sms/twilio-webhook.php` (same `dist` upload). It is operations/jobsite only.

- Webhook URL: `https://translate.deliverydave.ai/sms/twilio-webhook.php`
- Enroll URL: `https://translate.deliverydave.ai/sms/enroll.php` (consent form at https://deliverydave.ai/sms-consent/ — replace Hostinger `public_html/sms-consent/index.html` from `hostinger-pages/sms-consent/`)
- Copy `sms/config.local.php.example` → `sms/config.local.php` on the server (Twilio Account SID / Auth Token / **Messaging Service SID `MG…`** — not Campaign `CM…` — and xAI key). Owner/contact phones are enrolled on the consent page into `sms/data/pair.json`. Never commit `config.local.php`.
- Move **+14704704880** off **ACap Catalyst Alert** onto the **DeliveryDave** Messaging Service, then point inbound POST at that webhook.
- Local checks: `php sms-smoke.php` (or `npm run sms-smoke`).

Full steps: **[public/sms/README.md](public/sms/README.md)**.

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

Use these only if you want a managed Node `/api/chat` proxy instead of Hostinger PHP. The public hostname should still be **`translate.deliverydave.ai`**. Set **`XAI_API_KEY`** in the host’s environment (never in the frontend). Do not paste the key in Settings for normal use.

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
