<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * @param callable(string, string): array $send fn(to, body): result
 * @param callable(string, string, string): string $translate fn(text, sourceLang, targetLang)
 * @return array{status: string, forwarded: bool, sends: list<array{to: string, body: string}>}
 */
function sms_handle_inbound(array $post, array $config, callable $send, callable $translate): array
{
    $from = sms_e164((string)($post['From'] ?? ''));
    $body = trim((string)($post['Body'] ?? ''));
    $optOutType = (string)($post['OptOutType'] ?? '');
    $numMedia = (int)($post['NumMedia'] ?? 0);
    $twilioHandledKeyword = trim($optOutType) !== '';
    $keyword = sms_classify_keyword($body, $optOutType);
    $party = sms_party_for($config, $from);
    $lang = $party['lang'] ?? 'en';
    $sends = [];
    $queue = static function (string $to, string $text) use (&$sends, $send): void {
        if ($to === '' || trim($text) === '') {
            return;
        }
        $sends[] = ['to' => $to, 'body' => $text];
        $send($to, $text);
    };

    if ($from === '') {
        return ['status' => 'missing_from', 'forwarded' => false, 'sends' => $sends];
    }

    if ($keyword === 'stop') {
        sms_set_opted_out($config, $from, true);
        if (!$twilioHandledKeyword) {
            $confirm = $party
                ? sms_stop_confirm($lang)
                : sms_stop_confirm('en') . "\n\n" . sms_stop_confirm('es');
            $queue($from, $confirm);
        }
        return ['status' => 'opt_out', 'forwarded' => false, 'sends' => $sends];
    }

    if ($keyword === 'help') {
        if (!$twilioHandledKeyword) {
            $help = $party
                ? sms_help_message($config, $lang)
                : sms_help_message($config, 'en') . "\n\n" . sms_help_message($config, 'es');
            $queue($from, $help);
        }
        return ['status' => 'help', 'forwarded' => false, 'sends' => $sends];
    }

    if ($keyword === 'start') {
        if ($party) {
            sms_set_opted_out($config, $from, false);
            if (!$twilioHandledKeyword) {
                $queue($from, sms_start_confirm($lang));
            }
            return ['status' => 'opt_in', 'forwarded' => false, 'sends' => $sends];
        }
        if (!$twilioHandledKeyword) {
            $queue($from, sms_start_not_in_pair('en') . "\n\n" . sms_start_not_in_pair('es'));
        }
        return ['status' => 'start_unknown', 'forwarded' => false, 'sends' => $sends];
    }

    if (sms_is_opted_out($config, $from)) {
        if ($party) {
            $queue($from, sms_sender_opted_out_hint($lang));
        }
        return ['status' => 'sender_opted_out', 'forwarded' => false, 'sends' => $sends];
    }

    if (!$party) {
        if (!sms_unknown_reply_recent($config, $from)) {
            $queue($from, sms_unknown_reply());
            sms_mark_unknown_reply($config, $from);
        }
        return ['status' => 'unknown_from', 'forwarded' => false, 'sends' => $sends];
    }

    if ($body === '' && $numMedia > 0) {
        $queue($from, sms_media_only_reply($lang));
        return ['status' => 'media_only', 'forwarded' => false, 'sends' => $sends];
    }

    if ($body === '') {
        return ['status' => 'empty', 'forwarded' => false, 'sends' => $sends];
    }

    $dest = $party['other'];
    if (sms_is_opted_out($config, $dest['phone'])) {
        $queue($from, sms_dest_opted_out($lang, $dest['name']));
        return ['status' => 'dest_opted_out', 'forwarded' => false, 'sends' => $sends];
    }

    try {
        $translated = $translate($body, $party['lang'], $dest['lang']);
        $translated = trim((string)$translated);
        if ($translated === '') {
            throw new RuntimeException('empty translation');
        }
    } catch (Throwable $e) {
        error_log('DeliveryDave SMS translate failed: ' . $e->getMessage());
        if ($party['role'] === 'contact') {
            $queue($dest['phone'], sms_translate_failed_owner($body));
        } else {
            $queue($from, sms_in_lang(
                $lang,
                'DeliveryDave could not translate that jobsite text. Try again in a moment.',
                'DeliveryDave no pudo traducir ese texto de obra. Inténtalo de nuevo en un momento.',
            ));
        }
        return ['status' => 'translate_failed', 'forwarded' => false, 'sends' => $sends];
    }

    $outbound = sms_attribution($party['name'], $translated, $party['lang'], $dest['lang']);
    $result = $send($dest['phone'], $outbound);
    $sends[] = ['to' => $dest['phone'], 'body' => $outbound];
    if (is_array($result) && empty($result['ok']) && $party['role'] === 'owner') {
        $queue($from, sms_in_lang(
            $lang,
            'DeliveryDave could not deliver that jobsite text. Try again in a moment.',
            'DeliveryDave no pudo entregar ese texto de obra. Inténtalo de nuevo en un momento.',
        ));
        return ['status' => 'send_failed', 'forwarded' => false, 'sends' => $sends];
    }

    return ['status' => 'forwarded', 'forwarded' => true, 'sends' => $sends];
}
