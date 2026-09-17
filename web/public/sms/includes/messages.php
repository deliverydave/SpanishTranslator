<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

function sms_lang(string $code): string
{
    $normalized = strtolower(trim($code));
    if (str_starts_with($normalized, 'es')) {
        return 'es';
    }
    if (str_starts_with($normalized, 'en')) {
        return 'en';
    }
    return $normalized !== '' ? $normalized : 'en';
}

function sms_in_lang(string $lang, string $en, string $es): string
{
    return sms_lang($lang) === 'es' ? $es : $en;
}

function sms_language_name(string $code, string $inLang): string
{
    $code = sms_lang($code);
    if (sms_lang($inLang) === 'es') {
        return $code === 'es' ? 'español' : 'inglés';
    }
    return $code === 'es' ? 'Spanish' : 'English';
}

function sms_disclosure(string $lang): string
{
    return sms_in_lang(
        $lang,
        'DeliveryDave jobsite texts. Msg & data rates may apply. Reply STOP to opt out, HELP for help.',
        'Textos de obra de DeliveryDave. Pueden aplicar tarifas de mensajes y datos. Responde STOP para cancelar, HELP para ayuda.',
    );
}

function sms_attribution(string $senderName, string $translated, string $sourceLang, string $targetLang): string
{
    $safe = str_replace(['"', '“', '”'], "'", trim($translated));
    $receive = sms_lang($targetLang);
    $sourceName = sms_language_name($sourceLang, $receive);
    $targetName = sms_language_name($targetLang, $receive);
    $line = sms_in_lang(
        $receive,
        sprintf(
            '%s: "%s" Original %s received and translated to %s by DeliveryDave.',
            $senderName,
            $safe,
            $sourceName,
            $targetName,
        ),
        sprintf(
            '%s: "%s" %s original recibido y traducido al %s por DeliveryDave.',
            $senderName,
            $safe,
            ucfirst($sourceName),
            $targetName,
        ),
    );
    return $line . "\n\n" . sms_disclosure($receive);
}

function sms_stop_confirm(string $lang): string
{
    return sms_in_lang(
        $lang,
        'You are opted out of DeliveryDave jobsite texts. No more messages will be sent. Reply START to rejoin.',
        'Cancelaste los textos de obra de DeliveryDave. No se enviarán más mensajes. Responde START para volver a unirte.',
    );
}

function sms_start_confirm(string $lang): string
{
    return sms_in_lang(
        $lang,
        'You are opted in to DeliveryDave jobsite texts. Reply STOP to opt out, HELP for help.',
        'Te suscribiste a los textos de obra de DeliveryDave. Responde STOP para cancelar, HELP para ayuda.',
    );
}

function sms_start_not_in_pair(string $lang): string
{
    return sms_in_lang(
        $lang,
        'This DeliveryDave number is only for a configured jobsite contact. Reply STOP to opt out, HELP for help.',
        'Este número de DeliveryDave es solo para un contacto de obra configurado. Responde STOP para cancelar, HELP para ayuda.',
    );
}

function sms_help_message(array $config, string $lang): string
{
    $email = (string)($config['helpEmail'] ?? 'contact@deliverydave.ai');
    $privacy = (string)($config['privacyUrl'] ?? 'https://translate.deliverydave.ai/sms/privacy.html');
    $terms = (string)($config['termsUrl'] ?? 'https://translate.deliverydave.ai/sms/terms.html');
    $en = "DeliveryDave jobsite texts. Help: {$email}\nPrivacy: {$privacy}\nTerms: {$terms}\nReply STOP to opt out, HELP for help. Msg & data rates may apply.";
    $es = "Textos de obra de DeliveryDave. Ayuda: {$email}\nPrivacidad: {$privacy}\nTérminos: {$terms}\nResponde STOP para cancelar, HELP para ayuda. Pueden aplicar tarifas de mensajes y datos.";
    if (sms_lang($lang) === 'both') {
        return $en . "\n\n" . $es;
    }
    return sms_in_lang($lang, $en, $es);
}

function sms_unknown_reply(): string
{
    return "This DeliveryDave number is only for a jobsite contact. If this is a mistake, ignore this message. Reply STOP to opt out, HELP for help.\n\n"
        . 'Este número de DeliveryDave es solo para un contacto de obra. Si es un error, ignore este mensaje. Responde STOP para cancelar, HELP para ayuda.';
}

function sms_media_only_reply(string $lang): string
{
    return sms_in_lang(
        $lang,
        'Please send a text message. Photos and attachments are not translated. Reply STOP to opt out, HELP for help.',
        'Por favor envía un mensaje de texto. Las fotos y archivos no se traducen. Responde STOP para cancelar, HELP para ayuda.',
    );
}

function sms_dest_opted_out(string $lang, string $destName): string
{
    return sms_in_lang(
        $lang,
        "{$destName} has opted out of DeliveryDave jobsite texts. Your message was not forwarded.",
        "{$destName} canceló los textos de obra de DeliveryDave. Tu mensaje no se reenvió.",
    );
}

function sms_sender_opted_out_hint(string $lang): string
{
    return sms_in_lang(
        $lang,
        'You are opted out. Reply START to rejoin DeliveryDave jobsite texts, HELP for help.',
        'Cancelaste los mensajes. Responde START para volver a unirte a los textos de obra de DeliveryDave, HELP para ayuda.',
    );
}

function sms_translate_failed_owner(string $original): string
{
    $snippet = trim($original);
    if (mb_strlen($snippet) > 300) {
        $snippet = mb_substr($snippet, 0, 297) . '...';
    }
    return "DeliveryDave could not translate that jobsite text. Original:\n{$snippet}";
}

/**
 * User-facing copy used by smoke tests to prove this bridge never uses
 * stock / ticker / catalyst / ACap / investment language.
 *
 * @return list<string>
 */
function sms_all_static_copy(array $config = []): array
{
    $config = array_merge([
        'helpEmail' => 'contact@deliverydave.ai',
        'privacyUrl' => 'https://translate.deliverydave.ai/sms/privacy.html',
        'termsUrl' => 'https://translate.deliverydave.ai/sms/terms.html',
    ], $config);
    return [
        sms_disclosure('en'),
        sms_disclosure('es'),
        sms_attribution('Luis', 'See you on site at 7.', 'es', 'en'),
        sms_attribution('Dave', 'Nos vemos en la obra a las 7.', 'en', 'es'),
        sms_stop_confirm('en'),
        sms_stop_confirm('es'),
        sms_start_confirm('en'),
        sms_start_confirm('es'),
        sms_start_not_in_pair('en'),
        sms_start_not_in_pair('es'),
        sms_help_message($config, 'en'),
        sms_help_message($config, 'es'),
        sms_unknown_reply(),
        sms_media_only_reply('en'),
        sms_media_only_reply('es'),
        sms_dest_opted_out('en', 'Luis'),
        sms_dest_opted_out('es', 'Dave'),
        sms_sender_opted_out_hint('en'),
        sms_sender_opted_out_hint('es'),
        sms_translate_failed_owner('Llego a las 7.'),
    ];
}

function sms_jobsite_translate_prompt(string $sourceLang, string $targetLang): string
{
    $from = sms_language_name($sourceLang, 'en');
    $to = sms_language_name($targetLang, 'en');
    return <<<PROMPT
You translate jobsite and operations SMS for DeliveryDave, a construction delivery and project-management service (estimates, materials, schedules, invoices, and field coordination).

Translate the user's message from {$from} to {$to}.

Rules:
- Reply with ONLY the translated message text. No quotes, labels, alternatives, or commentary.
- Preserve names, phone numbers, dates, addresses, quantities, and meaning.
- Sound like a real text message, not a word-for-word translation.
- Use familiar tú for Spanish unless the source is clearly formal. Prefer Latin American Spanish.
- This is operations/jobsite communication only.
- NEVER introduce stock-market, ticker, catalyst, ACap, investment, trading, or financial-alert language. If the source has that wording, drop it and keep only any operational meaning.
PROMPT;
}

function sms_forbidden_market_regex(): string
{
    return '/\b(a\s*-?\s*cap|catalyst|ticker|nasdaq|nyse|securities|investment|stock\s*(alert|pick|tip)|trading\s*alert)\b/i';
}
