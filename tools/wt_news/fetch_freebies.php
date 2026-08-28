<?php
/**
 * Ищет халяву: промокоды, дропсы, раздачи, бесплатные наборы и скидки.
 * Смотрит официальные новости и русский форум War Thunder.
 *
 *   php tools/wt_news/fetch_freebies.php
 *   php tools/wt_news/fetch_freebies.php --min-score=4   # только настоящее бесплатное
 *   php tools/wt_news/fetch_freebies.php --no-forum
 *
 * Форумные темы — это слова игроков, а не официальный источник. Прежде чем
 * писать по ним пост, код или условия нужно подтвердить на warthunder.com
 * или в официальных соцсетях.
 */

require __DIR__ . '/lib.php';

$options   = getopt('', ['min-score::', 'limit::', 'lang::', 'no-forum', 'all']);
$minScore  = (int) ($options['min-score'] ?? 1);
$limit     = max(1, (int) ($options['limit'] ?? 25));
$lang      = ($options['lang'] ?? 'ru') === 'en' ? 'en' : 'ru';
$showAll   = array_key_exists('all', $options);
$withForum = !array_key_exists('no-forum', $options);

$state = wt_load_state();
$found = [];

// --- Официальные новости ---
$listHtml = wt_http_get("https://warthunder.com/{$lang}/news/");
if ($listHtml !== null) {
    foreach (wt_parse_news_list($listHtml, $lang, 'warthunder.com/news') as $item) {
        if (!$showAll && wt_is_posted($state, $item['url'])) {
            continue;
        }

        $hits = wt_freebie_hits($item['title'] . ' ' . $item['summary']);
        if ($hits === []) {
            continue;
        }

        // Дочитываем статью: в тексте обычно и лежат условия и сроки.
        $item = wt_enrich($item, 3000);
        $hits = wt_freebie_hits($item['title'] . ' ' . $item['summary'] . ' ' . ($item['body'] ?? ''));

        $score = wt_freebie_score($hits);
        if ($score < $minScore) {
            continue;
        }

        $found[] = [
            'kind'     => 'official',
            'source'   => 'warthunder.com/news',
            'url'      => $item['url'],
            'title'    => $item['title'],
            'date'     => $item['date'],
            'keywords' => $hits,
            'score'    => $score,
            'image'    => $item['image'] ?? null,
            'images'   => $item['images'] ?? [],
            'body'     => $item['body'] ?? '',
        ];
    }
}

// --- Форум: свежие темы и точечный поиск ---
if ($withForum) {
    $topics  = [];
    $latest  = wt_forum_json('/latest.json?order=created');
    foreach ($latest['topic_list']['topics'] ?? [] as $topic) {
        $topics[$topic['id']] = $topic;
    }

    foreach (['промокод', 'дропс', 'раздача', 'бесплатно'] as $query) {
        $search = wt_forum_json('/search.json?q=' . rawurlencode($query . ' after:' . date('Y-m-d', strtotime('-30 days'))));
        foreach ($search['topics'] ?? [] as $topic) {
            $topics[$topic['id']] = $topic;
        }
    }

    foreach ($topics as $topic) {
        $title = (string) ($topic['title'] ?? '');
        $hits  = wt_freebie_hits($title);
        if ($hits === []) {
            continue;
        }

        $score = wt_freebie_score($hits);
        if ($score < $minScore) {
            continue;
        }

        $url = 'https://forum.warthunder.ru/t/' . ($topic['slug'] ?? '') . '/' . ($topic['id'] ?? '');
        if (!$showAll && wt_is_posted($state, $url)) {
            continue;
        }

        $found[] = [
            'kind'        => 'forum',
            'source'      => 'forum.warthunder.ru',
            'url'         => $url,
            'title'       => $title,
            'date'        => substr((string) ($topic['last_posted_at'] ?? $topic['created_at'] ?? ''), 0, 10),
            'keywords'    => $hits,
            'score'       => $score,
            'posts_count' => $topic['posts_count'] ?? null,
            'unverified'  => true,
        ];
    }
}

usort($found, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
$found = array_slice($found, 0, $limit);

echo json_encode([
    'fetched_at' => date('c'),
    'count'      => count($found),
    'items'      => $found,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
