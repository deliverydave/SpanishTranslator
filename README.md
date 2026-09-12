# SpanishTranslator

iPhone app for bilingual texting with Luis. You draft in English, polish the wording in a chat, translate to natural Spanish, then open Apple Messages with Luis’s number and the Spanish text filled in. When Luis replies, you paste the Spanish or a screenshot of the bubble; the app reads it and shows clear English.

The app **cannot** send SMS by itself or read the Messages inbox. Those are Apple platform limits. You always tap Send in Messages.

Requires **iOS 17+**. Built with SwiftUI.

## Open the project

1. Clone this repo on a Mac.
2. Open `SpanishTranslator.xcodeproj` in Xcode 15.4 or later (Xcode 16+ recommended).
3. Select the **SpanishTranslator** scheme and an iPhone simulator or your device.
4. Set your **Signing Team** under the target’s *Signing & Capabilities* tab (required for a physical iPhone).
5. Run.

This repository was authored in an environment without `xcodebuild`. The project file, scheme, sources, assets, and privacy strings are complete so Xcode on a Mac can build it.

## First-run setup

### 1. API key (compose / polish)

Compose chat uses an **OpenAI-compatible** Chat Completions API. In the app:

1. Open **Settings**.
2. Paste your API key and tap **Save API key**. It is stored in the **Keychain**, not in the project or git.
3. Leave the default base URL `https://api.openai.com/v1` and model `gpt-4o-mini`, or point at another compatible host (for example OpenRouter’s `/v1` endpoint) and its model name.
4. Tap **Test connection** if you want a quick check.

Without a key you can still pick a contact, paste inbound text, and run on-device OCR. Polishing drafts needs the key. English ↔ Spanish translation uses the same API when a key is present; on **iOS 18+** it can fall back to Apple Translation if no key is saved.

### 2. Default contact (Luis)

In **Settings**, tap **Choose from Contacts** and pick Luis (or type a name and phone and tap **Save name & number**). That recipient is saved on-device and can be changed later. The send sheet also lets you change the contact.

## Happy paths

### Compose → Spanish → Messages

1. Open the **Compose** tab.
2. Type rough notes, for example: `25% chance of rain tomorrow. How about starting Monday?`
3. Tap the up arrow. The app returns a polished English text.
4. Send tweaks: `Good morning Luis` or `don’t say 25%, say seems like rain`.
5. The English draft card at the top always shows the current full message.
6. When it looks right, tap **Translate** (or **Translate to Spanish**).
7. Edit the Spanish if you want, then tap **Open Messages**.
8. Confirm the recipient and body, then tap **Send** in Messages.

If Messages composer is unavailable (common in Simulator), the Spanish is copied and the app tries the `sms:` URL so you can paste.

**Use as draft** skips the LLM and treats your typed English as the final draft, then you can still translate.

### Screenshot / paste → English

1. Open the **Inbox** tab.
2. Either:
   - Copy Luis’s Spanish in Messages and tap **Paste text**, or
   - Copy a screenshot / bubble image and tap **Paste image**, or
   - Tap **Choose screenshot** and pick a photo.
3. Vision OCR runs **on-device** (nothing is uploaded for reading the image).
4. Check and edit the extracted Spanish.
5. Tap **Translate to English**.

## Permissions

SpanishTranslator asks for as little as possible:

| Permission | When | Why |
|---|---|---|
| **Contacts** | You tap “Choose from Contacts” | Save Luis’s name and phone as the default recipient |
| **Photos** | Only if the system still prompts for the screenshot picker | Read Spanish from a Messages screenshot you pick. OCR is on-device |

It does **not** request microphone, location, or full Messages access. The limited photo picker (`PHPicker`) usually does not need a library prompt. Pasteboard text and images do not need a permission.

Purpose strings live in `SpanishTranslator/Info.plist` and the target build settings.

## Simulator vs device

| Feature | Simulator | Physical iPhone |
|---|---|---|
| Compose / polish chat | Yes (needs network + API key) | Yes |
| Translate | Yes | Yes |
| Vision OCR | Yes | Yes |
| Contacts picker | Yes (add a contact in the Simulator first) | Yes |
| Messages composer (`MessageUI`) | Usually **no** (`canSendText()` is false) | Yes, with Messages / iMessage signed in |
| Actually sending a text | No | You tap Send in Messages |

On a device, use a real phone number for Luis. Apple still will not let the app send without that tap.

## Project layout

```
SpanishTranslator.xcodeproj/     Xcode project + shared scheme
SpanishTranslator/
  SpanishTranslatorApp.swift     App entry, shared settings / contact / translation host
  ComposeView.swift              English refine chat
  SpanishSendSheet.swift         Editable Spanish + Messages
  InboxView.swift                Paste / screenshot → English
  SettingsView.swift             API key, contact, how-it-works
  LLMClient.swift                OpenAI-compatible Chat Completions client
  OCRService.swift               Vision text recognition
  Representables.swift           Contacts, MessageUI, PHPicker wrappers
  KeychainStore.swift            API key
  Info.plist                     Contacts / photos purpose strings
  PrivacyInfo.xcprivacy          UserDefaults reason
  Assets.xcassets                App icon + accent color
```

Bundle ID: `com.spanishtranslator.app`. Change it in the target if you need your own identifier.

## What v1 does not do

- Auto-send SMS or silently text Luis
- Read the Messages inbox
- A Share Extension (paste + photo picker cover the inbound path)
- Cloud sync of drafts or contacts (everything is local)

## Troubleshooting

- **“Add an API key”** — Settings → paste key → Save. Compose polish cannot run without it.
- **401 / 403** — wrong key, or the host rejected it. Check base URL (`…/v1`) and model name.
- **Messages composer missing** — run on a device, or use **Copy Spanish** and paste into Messages.
- **OCR empty** — crop closer to the bubble; avoid a dim or tiny screenshot.
- **Signing errors** — choose your team; the project ships with Automatic signing and no team id filled in.
