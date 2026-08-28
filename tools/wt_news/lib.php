<?php
/**
 * Общие помощники для новостного бота War Thunder.
 */

const WT_UA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

function wt_state_path(): string
{
    return __DIR__ . '/posted.json';
}

/**
 * Читает .env из корня проекта, если переменных нет в окружении.
 */
function wt_env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    static $dotenv = null;
    if ($dotenv === null) {
        $dotenv = [];
        $file = dirname(__DIR__, 2) . '/.env';
        if (is_readable($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $line, 2);
                $dotenv[trim($k)] = trim($v, " \t\"'");
            }
        }
    }

    if (isset($dotenv[$key]) && $dotenv[$key] !== '') {
        return $dotenv[$key];
    }

    return $default;
}

function wt_http_get(string $url, int $timeout = 30): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_USERAGENT      => WT_UA,
        CURLOPT_HTTPHEADER     => ['Accept-Language: ru,en;q=0.8'],
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300) {
        return null;
    }

    return $body;
}

/** @return array{posted: array<string, array<string, string>>} */
function wt_load_state(): array
{
    $path = wt_state_path();
    if (!is_readable($path)) {
        return ['posted' => []];
    }

    $data = json_decode((string) file_get_contents($path), true);
    if (!is_array($data) || !isset($data['posted']) || !is_array($data['posted'])) {
        return ['posted' => []];
    }

    return $data;
}

function wt_save_state(array $state): void
{
    ksort($state['posted']);
    file_put_contents(
        wt_state_path(),
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
}

/**
 * Ключ дедупликации: URL без протокола, языкового префикса, query и якоря.
 */
function wt_dedup_key(string $url): string
{
    $url = strtolower(trim($url));
    $url = preg_replace('~^https?://~', '', $url);
    $url = preg_replace('~[?#].*$~', '', (string) $url);
    $url = preg_replace('~^www\.~', '', (string) $url);
    $url = preg_replace('~/(ru|en)/news/~', '/news/', (string) $url);
    $url = preg_replace('~-(ru|en)$~', '', (string) $url);

    return rtrim((string) $url, '/');
}

function wt_is_posted(array $state, string $url): bool
{
    return isset($state['posted'][wt_dedup_key($url)]);
}

function wt_meta(string $html, string $property): ?string
{
    $patterns = [
        '~<meta[^>]+(?:property|name)=["\']' . preg_quote($property, '~') . '["\'][^>]*content=["\']([^"\']*)["\']~i',
        '~<meta[^>]+content=["\']([^"\']*)["\'][^>]*(?:property|name)=["\']' . preg_quote($property, '~') . '["\']~i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return null;
}

function wt_clean_text(string $html): string
{
    $html = preg_replace('~<(script|style|noscript)[^>]*>.*?</\1>~is', ' ', $html);
    $html = preg_replace('~<br\s*/?>~i', "\n", (string) $html);
    $html = preg_replace('~</(p|div|li|h[1-6]|tr)>~i', "\n", (string) $html);
    $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xC2\xA0", ' ', $text);
    $text = preg_replace('~[ \t]+~', ' ', $text);
    $text = preg_replace('~\n\s*\n\s*\n+~', "\n\n", (string) $text);

    return trim((string) $text);
}
