# Hostinger main-site pages

These files belong on **deliverydave.ai** (`public_html/…`), not in the translator `dist` upload to `public_html/translate`.

## SMS consent

| | |
|---|---|
| In-repo | `hostinger-pages/sms-consent/index.html` (same HTML as `web/public/sms/consent/index.html`) |
| Replace on Hostinger | `public_html/sms-consent/index.html` |
| Live URL | https://deliverydave.ai/sms-consent/ |

The form POSTs (fetch + CORS) to `https://translate.deliverydave.ai/sms/enroll.php`, which writes `sms/data/pair.json` on the translator host. Twilio Account SID / Auth Token / Messaging Service SID (`MG…`) / xAI key stay in `public_html/translate/sms/config.local.php` — they are not on this page.

After you change the HTML here, copy it to `public_html/sms-consent/index.html` again. Uploading `web/dist` only updates `translate.deliverydave.ai`.
