<?php
// Cantum Studio: cookie-free visit statistics.
// Counters only (per day, per key). A visitor is a salted hash of IP + browser + day that is thrown away after the day ends,
// so nothing personal is stored. Lives in data/stats.sqlite (not tracked by git, so `git pull` never touches it).
// Every call is wrapped so a stats failure can never break a public page.

function stats_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) return $pdo;
    try {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) return $pdo = null;
        $pdo = new PDO('sqlite:' . dirname(__DIR__) . '/data/stats.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA busy_timeout = 1500');
        $pdo->exec('CREATE TABLE IF NOT EXISTS hits (day TEXT NOT NULL, k TEXT NOT NULL, n INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (day, k))');
        $pdo->exec('CREATE TABLE IF NOT EXISTS seen (day TEXT NOT NULL, h TEXT NOT NULL, PRIMARY KEY (day, h))');
        $pdo->exec('CREATE TABLE IF NOT EXISTS meta (k TEXT PRIMARY KEY, v TEXT)');
    } catch (Throwable $e) {
        $pdo = null;
    }
    return $pdo;
}

function stats_today(): string
{
    return (new DateTime('now', new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
}

function stats_is_bot(): bool
{
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|preview|fetch|monitor|lighthouse|headless|curl|wget|python|java|go-http|scrapy|axios|facebookexternalhit|whatsapp|telegram|discord|slack/i', $ua)) return true;
    return stripos($_SERVER['HTTP_SEC_PURPOSE'] ?? '', 'prefetch') !== false
        || stripos($_SERVER['HTTP_X_PURPOSE'] ?? '', 'preview') !== false;
}

/** True when this request should not be counted: bots, prefetches, Do Not Track, and the signed-in owner. */
function stats_skip(): bool
{
    return stats_is_bot() || ($_SERVER['HTTP_DNT'] ?? '') === '1' || !empty($_SESSION['uid']);
}

function stats_add(PDO $pdo, string $day, string $k, int $n = 1): void
{
    $pdo->prepare('INSERT INTO hits (day, k, n) VALUES (?, ?, ?) ON CONFLICT(day, k) DO UPDATE SET n = n + excluded.n')->execute([$day, $k, $n]);
}

/** One page view; the first view of the day by a visitor also counts them and where they came from. */
function stats_pageview(string $page): void
{
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || stats_skip() || !($pdo = stats_db())) return;
        $day = stats_today();
        $page = substr($page, 0, 120);
        stats_add($pdo, $day, 'views');
        stats_add($pdo, $day, 'page:' . $page);

        $salt = $pdo->query("SELECT v FROM meta WHERE k = 'salt'")->fetchColumn();
        if (!$salt) {
            $salt = bin2hex(random_bytes(16));
            $pdo->prepare("INSERT OR IGNORE INTO meta (k, v) VALUES ('salt', ?)")->execute([$salt]);
        }
        $h = hash('sha256', $salt . '|' . ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . $day);
        $ins = $pdo->prepare('INSERT OR IGNORE INTO seen (day, h) VALUES (?, ?)');
        $ins->execute([$day, $h]);
        if ($ins->rowCount() === 1) {
            stats_add($pdo, $day, 'visitors');
            $ref = strtolower((string)parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST));
            $ref = preg_replace('/^www\./', '', $ref);
            $own = preg_replace('/^www\./', '', strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')));
            stats_add($pdo, $day, 'ref:' . ($ref === '' || $ref === $own ? '(direct)' : substr($ref, 0, 80)));
            $pdo->prepare('DELETE FROM seen WHERE day < ?')->execute([$day]);
        }
    } catch (Throwable $e) {
    }
}

/** A WhatsApp click, with the place it came from (e.g. "pricing:Business"). */
function stats_click(string $source): void
{
    try {
        if (stats_skip() || !($pdo = stats_db())) return;
        $day = stats_today();
        stats_add($pdo, $day, 'wa');
        stats_add($pdo, $day, 'wa:' . substr($source, 0, 60));
    } catch (Throwable $e) {
    }
}

/** @return array{days: array<string, array<string,int>>, totals: array<string,int>} per-day and summed counters for the last $n days */
function stats_range(int $n): array
{
    $out = ['days' => [], 'totals' => []];
    $tz = new DateTimeZone('Asia/Jakarta');
    for ($i = $n - 1; $i >= 0; $i--) $out['days'][(new DateTime("-$i day", $tz))->format('Y-m-d')] = ['visitors' => 0, 'views' => 0, 'wa' => 0];
    if (!($pdo = stats_db())) return $out;
    $st = $pdo->prepare('SELECT day, k, n FROM hits WHERE day >= ?');
    $st->execute([array_key_first($out['days'])]);
    foreach ($st as $r) {
        if (isset($out['days'][$r['day']]) && array_key_exists($r['k'], $out['days'][$r['day']])) $out['days'][$r['day']][$r['k']] = (int)$r['n'];
        $out['totals'][$r['k']] = ($out['totals'][$r['k']] ?? 0) + (int)$r['n'];
    }
    return $out;
}
