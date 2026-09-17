<?php
if (!defined('DELIVERYDAVE_SMS')) {
    http_response_code(403);
    exit('Forbidden');
}

function sms_strip_wrapping_quotes(string $text): string
{
    $result = trim($text);
    if (
        (str_starts_with($result, '"') && str_ends_with($result, '"') && strlen($result) > 1)
        || (str_starts_with($result, '“') && str_ends_with($result, '”') && strlen($result) > 1)
    ) {
        $result = trim(mb_substr($result, 1, mb_strlen($result) - 2));
    }
    return $result;
}

function sms_translate(array $config, string $text, string $sourceLang, string $targetLang): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (sms_lang($sourceLang) === sms_lang($targetLang)) {
        return $text;
    }

    if (!empty($config['dryRun'])) {
        return $text;
    }

    $apiKey = trim((string)($config['apiKey'] ?? ''));
    if ($apiKey === '') {
        throw new RuntimeException('Missing xAI API key');
    }

    $base = rtrim((string)($config['xaiBase'] ?? 'https://api.x.ai/v1'), '/');
    if (str_ends_with($base, '/chat/completions')) {
        $base = substr($base, 0, -strlen('/chat/completions'));
        $base = rtrim($base, '/');
    }
    $endpoint = $base . '/chat/completions';
    $model = (string)($config['xaiModel'] ?? 'grok-4.6');

    $attempt = function (string $prompt) use ($endpoint, $model, $apiKey, $text): string {
        $body = json_encode([
            'model' => $model,
            'temperature' => 0.2,
            'messages' => [
                ['role' => 'system', 'content' => $prompt],
                ['role' => 'user', 'content' => $text],
            ],
        ]);
        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 45,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            throw new RuntimeException($err ?: 'xAI request failed');
        }
        $parsed = json_decode($response, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($parsed) ? (string)($parsed['error']['message'] ?? $response) : (string)$response;
            throw new RuntimeException('xAI HTTP ' . $status . ': ' . $message);
        }
        $content = is_array($parsed) ? trim((string)($parsed['choices'][0]['message']['content'] ?? '')) : '';
        if ($content === '') {
            throw new RuntimeException('xAI returned an empty translation');
        }
        return sms_strip_wrapping_quotes($content);
    };

    $translated = $attempt(sms_jobsite_translate_prompt($sourceLang, $targetLang));
    if (preg_match(sms_forbidden_market_regex(), $translated)) {
        $translated = $attempt(
            sms_jobsite_translate_prompt($sourceLang, $targetLang)
            . "\nThe previous wording still looked like market/trading language. Translate again with strictly jobsite/operations wording only."
        );
    }
    return $translated;
}
