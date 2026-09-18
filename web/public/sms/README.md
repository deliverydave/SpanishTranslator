# DeliveryDave bilingual SMS bridge

Twilio number **+1 (470) 470-4880** (configurable) sits between the **owner** and one **jobsite contact**. Each inbound SMS is translated and forwarded. This is operations/jobsite texting only — never stocks, tickers, catalysts, ACap, or investment alerts.

The A2P campaign is **APPROVED**. Move the number off the old **ACap Catalyst Alert** Messaging Service onto a **DeliveryDave** Messaging Service before you point the webhook here.

Public URLs (Hostinger, after you upload `web/dist`):

```
https://translate.deliverydave.ai/sms/twilio-webhook.php
https://translate.deliverydave.ai/sms/enroll.php
```

**Enroll owner + contact phones** on the live consent page, not by editing `config.local.php`:

```
https://deliverydave.ai/sms-consent/
```

That page POSTs to `enroll.php`, which writes `sms/data/pair.json`. The webhook uses **pair.json** for name / phone / language when it exists (it overrides the owner/contact defaults in `config.local.php`). Twilio and xAI secrets stay in `config.local.php` only — they are never collected on the web form.

`messagingServiceSid` must be a **Messaging Service** SID starting with **`MG`**. The A2P **Campaign** SID (`CM…`) is a different value and does not belong in that field.

## What it does

| From | Action |
|---|---|
| Contact | Translate into the owner language and SMS the owner |
| Owner | Translate into the contact language and SMS the contact |
| Unknown number | One polite bilingual reply; do not forward |
| `STOP` `STOPALL` `UNSUBSCRIBE` `CANCEL` `END` `QUIT` | Opt out **From**, confirm, do not forward |
| `HELP` `INFO` | Help text with `contact@deliverydave.ai` plus privacy/terms URLs; do not forward |
| `START` `YES` `UNSTOP` | Re-opt-in only if **From** is the configured owner or contact |

Keywords stay **English** even when the rest of the SMS is Spanish.

Forwarded example (owner receives English):

```
Luis: I'll be on site at 7.

Msg & data rates may apply. Reply STOP to opt out, HELP for help.
```

## Files (Hostinger PHP, same pattern as `api/chat.php`)

```
web/public/sms/
  twilio-webhook.php           POST from Twilio (validates X-Twilio-Signature)
  enroll.php                   POST from the consent page (CORS; writes pair.json)
  consent/index.html           in-repo copy of the consent form (see Hostinger below)
  config.local.php.example     copy to config.local.php on the server — never git
  privacy.html / terms.html    linked from HELP
  includes/                    signature, REST send, Grok translate, opt-out JSON, enroll
  data/optouts.json            created at runtime (blocked by .htaccess)
  data/pair.json               live owner/contact pair from consent enroll (blocked)
```

The same consent HTML also lives at `hostinger-pages/sms-consent/index.html` for the **main site**.

CLI tests live at `web/sms-smoke.php` (not uploaded with `dist`).

Translation uses **xAI** `https://api.x.ai/v1` model **grok-4.6**, same as `api/chat.php`. Outbound SMS uses the Twilio REST API (`Messages.json`), with `MessagingServiceSid` when set, otherwise `From` = `twilioNumber`.

## 1. Hostinger upload

This lives next to the translator site. After `npm run build` in `web/`, upload the **contents** of `web/dist` into `public_html/translate` as you already do. Confirm these exist on the server:

- `public_html/translate/sms/twilio-webhook.php`
- `public_html/translate/sms/enroll.php`
- `public_html/translate/sms/config.local.php.example`
- `public_html/translate/sms/privacy.html`
- `public_html/translate/sms/terms.html`
- `public_html/translate/sms/.htaccess` (show hidden files in File Manager)
- `public_html/translate/.htaccess` (rewrites `/sms/twilio-webhook` and `/sms/enroll`)

Then **once** on the server, copy the example config:

1. File Manager → `public_html/translate/sms`
2. Copy `config.local.php.example` → **`config.local.php`**
3. Fill in real Twilio `accountSid` / `authToken`, and the xAI key (or leave `apiKey` empty and reuse `public_html/translate/api/config.local.php`)
4. Set `messagingServiceSid` on the **server** `config.local.php` to the DeliveryDave Messaging Service SID (`MG…`). `config.local.php.example` comments that SID so you can paste it; do not assign it in git. Do **not** paste the A2P Campaign SID (`CM…`) here.
5. Leave `webhookUrl` as `https://translate.deliverydave.ai/sms/twilio-webhook.php` so signature checks match what Twilio signed
6. Leave owner + contact **phones empty** in `config.local.php`. Enroll them on https://deliverydave.ai/sms-consent/ so `data/pair.json` is the live pair.
7. Do **not** commit `config.local.php`. Redeploy `dist` without deleting this file.

### Consent page on the main site (required for enrollment)

`dist` upload does **not** update https://deliverydave.ai/sms-consent/. Copy the in-repo HTML onto the **root** Hostinger site:

1. Source: `hostinger-pages/sms-consent/index.html` (same file as `web/public/sms/consent/index.html`)
2. Destination: **`public_html/sms-consent/index.html`** (replace the current page)
3. Confirm the form posts to `https://translate.deliverydave.ai/sms/enroll.php` (browser fetch; CORS allows `https://deliverydave.ai`)

“No thanks — continue without SMS” stays on the page and does **not** call enroll. Enroll runs only when the optional consent checkbox is checked and both phones are present.

Make `public_html/translate/sms/data/` writable by PHP (`optouts.json`, `pair.json`, rate-limit JSON). `.htaccess` there denies HTTP downloads.

## 2. Move +14704704880 to the DeliveryDave Messaging Service

In [Twilio Console](https://www.twilio.com/console):

1. **Phone Numbers → Manage → Active numbers → +1 470 470 4880**
2. Remove it from **ACap Catalyst Alert** (Messaging Service sender pool, and/or the number’s Messaging Service dropdown).
3. Open or create a Messaging Service named **DeliveryDave** (jobsite / operations use). Associate it with the **approved DeliveryDave** A2P campaign — not the old ACap campaign.
4. **Sender Pool → Add Senders → +14704704880**
5. **Integration / Inbound Settings**
   - A message comes in: **Webhook**
   - Request URL: `https://translate.deliverydave.ai/sms/twilio-webhook.php`
   - HTTP POST
6. On the **phone number** itself, set **A MESSAGE COMES IN** to the same URL (or “Messaging Service”) so inbound cannot stay on the old ACap webhook.
7. **Opt-Out Management:** turn **Advanced Opt-Out off** so this app can send bilingual STOP/HELP/START replies. If you leave Advanced Opt-Out on, Twilio may send its own English reply first; this app still records STOP and will not forward.
8. Copy the Messaging Service SID (`MG…`) from the commented example into server `config.local.php` → `messagingServiceSid`. The Campaign SID (`CM…`) is not this field.

Save. Send a test SMS to +14704704880 from the contact phone and watch **Monitor → Logs → Errors** plus Hostinger error logs.

## 3. Smoke tests

On a machine with PHP 8.0+ (curl + mbstring):

```bash
php web/sms-smoke.php
```

Or from `web/`: `npm run sms-smoke`.

That checks E.164, keywords, jobsite-only copy, Twilio signatures, opt-out JSON, consent enroll validation, `pair.json` override of owner/contact routing, unknown From, and owner↔contact routing **without** calling Twilio or xAI.

After Hostinger + Twilio are configured, live checks:

1. From a random phone: you get one polite bilingual reply; the owner does **not** get it. A second text from that phone stays silent.
2. `HELP` → email `contact@deliverydave.ai` plus privacy/terms URLs. `STOP`/`HELP` stay English.
3. From the **contact**: a Spanish jobsite sentence arrives to the owner as `Luis: …` then a blank line and the English disclosure (`STOP` / `HELP` stay English).
4. From the **owner**: an English jobsite sentence arrives to the contact as `Dave: …` then a blank line and the Spanish disclosure, with `STOP` / `HELP` still in English.
5. `STOP` from the contact → confirm, no forward. A later owner text is not sent to the contact (owner is told they opted out). `START` from the contact resumes. `START` from a random phone does **not** join the pair.

## Config keys

| Key | Required | Notes |
|---|---|---|
| `accountSid` | yes | `AC…` |
| `authToken` | yes | used for REST send **and** `X-Twilio-Signature` |
| `messagingServiceSid` | no | `MG…` DeliveryDave service; recommended. Comment in `config.local.php.example`; assign only in server `config.local.php`. Not Campaign `CM…`. |
| `twilioNumber` | yes | default `+14704704880` |
| `owner.name/phone/lang` | default | live phones from `data/pair.json` after consent enroll |
| `contact.name/phone/lang` | default | live phones from `data/pair.json` after consent enroll |
| `apiKey` | yes* | xAI key; *or* the existing `api/config.local.php` key |
| `webhookUrl` | recommended | exact public webhook URL |
| `helpEmail` | no | default `contact@deliverydave.ai` |
| `privacyUrl` / `termsUrl` | no | default the pages in this folder |

Optional: set env `DELIVERYDAVE_SMS_CONFIG` to an absolute path if the PHP config lives outside the web root.

## Security

- Twilio POSTs are rejected unless `X-Twilio-Signature` matches (HMAC-SHA1 of the public URL + POST params, auth token as key).
- Consent enroll is rate-limited per IP, requires the checkbox + two valid E.164-ish phones, and never accepts Twilio/xAI secrets from the form.
- CORS on `enroll.php` allows `https://deliverydave.ai` (and `www` / `translate.deliverydave.ai`).
- `config.local.php`, `data/*.json`, and `includes/` are not HTTP-readable.
- No secrets belong in git, frontend JS, the consent page, or the `dist` zip.

Need help: [contact@deliverydave.ai](mailto:contact@deliverydave.ai).
