<?php
// Cantum Studio — shared bootstrap: DB, session, helpers.
session_start();

define('ROOT', dirname(__DIR__));
define('UPLOAD_DIR', ROOT . '/uploads');

// Base URL path of the site (works in /cantum/ or at domain root).
$base = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
if (basename(dirname($_SERVER['SCRIPT_NAME'])) !== 'admin') {
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
}
define('BASE', $base);

// Language: /id/... serves Indonesian, everything else English. No visible switcher; /id/ is linked via hreflang + sitemap only.
$reqPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
define('LANG', preg_match('#^' . preg_quote(BASE, '#') . '/id(/|$)#', $reqPath) ? 'id' : 'en');

/** Translated string (or array) for the current language. */
function t(string $key)
{
    static $S = null;
    if ($S === null) $S = require __DIR__ . '/strings.php';
    return $S[LANG][$key] ?? $S['en'][$key] ?? $key;
}
/** Public-page URL in the current language. */
function lurl(string $path = ''): string { return url((LANG === 'id' ? 'id/' : '') . $path); }

const SITE_NAME = 'Cantum Studio';
const SITE_URL = 'https://cantum-studio.site';
const SITE_TAGLINE = 'Web Design Studio in Bogor';
const SITE_DESC = 'Web design & development studio in Bogor, Indonesia — company profile websites, landing pages, portfolios and online stores. Jasa pembuatan website Bogor.';
const GEO = ['city' => 'Bogor', 'region' => 'Jawa Barat', 'region_code' => 'ID-JB', 'country' => 'ID', 'lat' => -6.5971, 'lng' => 106.8060];
const SERVICES = ['Company Profile Websites', 'Portfolio Websites', 'Landing Pages', 'Online Stores (E-commerce)', 'Website Redesign', 'SEO & Performance Optimization'];
// Indonesian names for the same services (same order) — used in structured data only, so Indonesian searches still match.
const SERVICES_ID = ['Website Company Profile', 'Website Portofolio', 'Landing Page', 'Toko Online', 'Redesain Website', 'SEO & Optimasi Performa Website'];
const CATEGORIES = ['Architecture', 'Interior', 'Product', 'Branding', 'Web', 'Other'];

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    // MySQL/MariaDB when config.php exists (see config.sample.php); otherwise the bundled SQLite file.
    $cfg = is_file(ROOT . '/config.php') ? (array)require ROOT . '/config.php' : [];
    if (($cfg['driver'] ?? '') === 'mysql') {
        $pdo = new PDO(
            'mysql:host=' . ($cfg['host'] ?? 'localhost') . ';dbname=' . $cfg['name'] . ';charset=utf8mb4',
            $cfg['user'] ?? '', $cfg['pass'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        foreach (mysql_schema() as $sql) $pdo->exec($sql);
        seed_admin($pdo);
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . ROOT . '/data/cantum.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec("CREATE TABLE IF NOT EXISTS projects (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE,
        category TEXT NOT NULL DEFAULT 'Other',
        year TEXT,
        location TEXT,
        description TEXT,
        cover_id INTEGER,
        sort_order INTEGER NOT NULL DEFAULT 0,
        published INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS images (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        filename TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (k TEXT PRIMARY KEY, v TEXT)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        number TEXT NOT NULL UNIQUE,
        token TEXT NOT NULL UNIQUE,
        client_name TEXT NOT NULL,
        client_email TEXT,
        client_phone TEXT,
        client_address TEXT,
        issue_date TEXT NOT NULL,
        due_date TEXT,
        tax_rate REAL NOT NULL DEFAULT 0,
        discount INTEGER NOT NULL DEFAULT 0,
        notes TEXT,
        status TEXT NOT NULL DEFAULT 'draft',
        paid_at TEXT,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
        description TEXT NOT NULL,
        qty REAL NOT NULL DEFAULT 1,
        price INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0
    )");
    seed_admin($pdo);
    return $pdo;
}

function seed_admin(PDO $pdo): void
{
    if (!$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
        $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)')
            ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    }
}

function db_driver(): string { return db()->getAttribute(PDO::ATTR_DRIVER_NAME); }

/** Insert-or-update a settings row (SQLite and MySQL syntax differ). */
function save_setting(string $k, string $v): void
{
    $sql = db_driver() === 'mysql'
        ? 'INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)'
        : 'INSERT INTO settings (k, v) VALUES (?, ?) ON CONFLICT(k) DO UPDATE SET v = excluded.v';
    db()->prepare($sql)->execute([$k, $v]);
}

/** @return string[] CREATE TABLE statements for MySQL/MariaDB (InnoDB, utf8mb4). */
function mysql_schema(): array
{
    $t = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(191) NOT NULL UNIQUE,
            category VARCHAR(100) NOT NULL DEFAULT 'Other',
            `year` VARCHAR(20) NULL,
            location VARCHAR(255) NULL,
            description TEXT NULL,
            cover_id INT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            published TINYINT NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )$t",
        "CREATE TABLE IF NOT EXISTS images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            filename VARCHAR(255) NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_images_project FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
        )$t",
        "CREATE TABLE IF NOT EXISTS settings (k VARCHAR(100) PRIMARY KEY, v TEXT NULL)$t",
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL
        )$t",
        "CREATE TABLE IF NOT EXISTS invoices (
            id INT AUTO_INCREMENT PRIMARY KEY,
            number VARCHAR(100) NOT NULL UNIQUE,
            token VARCHAR(64) NOT NULL UNIQUE,
            client_name VARCHAR(255) NOT NULL,
            client_email VARCHAR(255) NULL,
            client_phone VARCHAR(50) NULL,
            client_address TEXT NULL,
            issue_date VARCHAR(10) NOT NULL,
            due_date VARCHAR(10) NULL,
            tax_rate DOUBLE NOT NULL DEFAULT 0,
            discount BIGINT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            paid_at VARCHAR(10) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )$t",
        "CREATE TABLE IF NOT EXISTS invoice_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            description TEXT NOT NULL,
            qty DOUBLE NOT NULL DEFAULT 1,
            price BIGINT NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            CONSTRAINT fk_items_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        )$t",
    ];
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = db()->query('SELECT k, v FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    return $cache[$key] ?? $default;
}

function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE . '/' . ltrim($path, '/'); }
function upload_url(string $file): string { return url('uploads/' . rawurlencode($file)); }

function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'project';
}

function unique_slug(string $title, int $ignoreId = 0): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    $st = db()->prepare('SELECT COUNT(*) FROM projects WHERE slug = ? AND id != ?');
    while (true) {
        $st->execute([$slug, $ignoreId]);
        if (!$st->fetchColumn()) return $slug;
        $slug = $base . '-' . $i++;
    }
}

// ---- auth / csrf ----
function csrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf() . '">'; }
/** Converts php.ini sizes like "40M" to bytes. */
function ini_bytes(string $v): int
{
    $n = (int)$v;
    switch (strtolower(substr(trim($v), -1))) {
        case 'g': $n *= 1024;
        case 'm': $n *= 1024;
        case 'k': $n *= 1024;
    }
    return $n;
}

function check_csrf(): void
{
    // If the upload exceeds post_max_size PHP drops $_POST/$_FILES entirely; explain that instead of a token error.
    if (!$_POST && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        http_response_code(413);
        exit('<!doctype html><meta charset="utf-8"><body style="font:14px system-ui;padding:2rem;max-width:560px">'
            . '<p><strong>Upload terlalu besar.</strong></p><p>Total ukuran file melebihi batas server ('
            . e(ini_get('post_max_size')) . ' per kiriman, ' . e(ini_get('upload_max_filesize')) . ' per file). '
            . 'Kecilkan foto atau upload sebagian-sebagian, lalu coba lagi.</p><p><a href="javascript:history.back()">← Kembali</a></p>');
    }
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('Invalid request token. Go back and try again.');
    }
}
function require_login(): void
{
    if (empty($_SESSION['uid'])) {
        header('Location: ' . url('admin/login.php'));
        exit;
    }
}

// ---- image upload ----
/** Saves uploaded files from $_FILES[$field] (multiple). Returns [saved filenames, error messages]. */
function save_uploads(string $field): array
{
    $saved = [];
    $errors = [];
    if (empty($_FILES[$field]['name'][0])) return [$saved, $errors];
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $count = count($_FILES[$field]['name']);
    for ($i = 0; $i < $count; $i++) {
        $name = $_FILES[$field]['name'][$i];
        $err = $_FILES[$field]['error'][$i];
        $tmp = $_FILES[$field]['tmp_name'][$i];
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { $errors[] = "$name: melebihi batas server (" . ini_get('upload_max_filesize') . " per file). Kecilkan ukurannya."; continue; }
        if ($err !== UPLOAD_ERR_OK) { $errors[] = "$name: upload gagal (kode $err), coba lagi."; continue; }
        if ($_FILES[$field]['size'][$i] > 15 * 1024 * 1024) { $errors[] = "$name: lebih dari 15MB, kecilkan dulu."; continue; }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset($allowed[$mime]) || !@getimagesize($tmp)) { $errors[] = "$name: not a supported image (JPG, PNG, WebP, GIF)."; continue; }
        $fn = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
        if (!move_uploaded_file($tmp, UPLOAD_DIR . '/' . $fn)) { $errors[] = "$name: could not be saved."; continue; }
        $saved[] = $fn;
    }
    return [$saved, $errors];
}

function delete_image_file(string $fn): void
{
    $p = UPLOAD_DIR . '/' . basename($fn);
    if (is_file($p)) @unlink($p);
}

function project_cover(array $p): ?string
{
    $st = db()->prepare('SELECT filename FROM images WHERE id = ?');
    if (!empty($p['cover_id'])) {
        $st->execute([$p['cover_id']]);
        if ($f = $st->fetchColumn()) return $f;
    }
    $st = db()->prepare('SELECT filename FROM images WHERE project_id = ? ORDER BY sort_order, id LIMIT 1');
    $st->execute([$p['id']]);
    return $st->fetchColumn() ?: null;
}

function abs_url(string $path = ''): string { return SITE_URL . '/' . ltrim($path, '/'); }

/** JSON-LD <script> blocks for a list of schema arrays. */
function json_ld(array $schemas): string
{
    $out = '';
    foreach ($schemas as $s) {
        $out .= '<script type="application/ld+json">' . json_encode($s, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . "</script>\n";
    }
    return $out;
}

/** The studio itself, as a local business — the core GEO/local-SEO entity. */
function org_schema(): array
{
    $sameAs = array_values(array_filter([setting('instagram') ?: 'https://www.instagram.com/cantumstudio/']));
    $org = [
        '@context' => 'https://schema.org',
        '@type' => ['ProfessionalService', 'LocalBusiness'],
        '@id' => abs_url('#studio'),
        'name' => SITE_NAME,
        'alternateName' => ['Cantum Studio Bogor', 'Cantum', 'Jasa Pembuatan Website Bogor Cantum Studio'],
        'url' => SITE_URL . '/',
        'description' => t('desc_home'),
        'slogan' => 'Websites that get seen, remembered and found. / Website yang dilihat, diingat, dan ditemukan.',
        'areaServed' => [
            ['@type' => 'City', 'name' => 'Bogor'],
            ['@type' => 'AdministrativeArea', 'name' => 'Jabodetabek'],
            ['@type' => 'Country', 'name' => 'Indonesia'],
        ],
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => GEO['city'], 'addressRegion' => GEO['region'], 'addressCountry' => GEO['country']],
        'geo' => ['@type' => 'GeoCoordinates', 'latitude' => GEO['lat'], 'longitude' => GEO['lng']],
        'knowsAbout' => ['Web design', 'Web development', 'Website development', 'Company profile website', 'Landing page', 'E-commerce website', 'SEO', 'UI/UX design', 'Pembuatan website', 'Jasa pembuatan website', 'Jasa web design', 'Pembuatan website company profile', 'Pembuatan toko online'],
        'knowsLanguage' => ['id', 'en'],
        'sameAs' => $sameAs,
        'makesOffer' => array_map(fn($n, $id) => ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $n, 'alternateName' => $id, 'areaServed' => 'Bogor, Indonesia', 'provider' => ['@id' => abs_url('#studio')]]], SERVICES, SERVICES_ID),
    ];
    if (setting('email')) $org['email'] = setting('email');
    if (setting('phone')) $org['telephone'] = setting('phone');
    return $org;
}

// ---- invoices ----
function rupiah($n): string { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

function id_date(?string $d): string
{
    if (!$d || !($t = strtotime($d))) return '';
    $m = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return date('j', $t) . ' ' . $m[(int)date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** @return array{subtotal:int,discount:int,tax:int,total:int} */
function invoice_totals(array $items, int $discount, float $taxRate): array
{
    $sub = 0;
    foreach ($items as $it) $sub += (int)round($it['qty'] * $it['price']);
    $discount = max(0, min($discount, $sub));
    $tax = (int)round(($sub - $discount) * $taxRate / 100);
    return ['subtotal' => $sub, 'discount' => $discount, 'tax' => $tax, 'total' => $sub - $discount + $tax];
}

function invoice_items(int $invoiceId): array
{
    $st = db()->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order, id');
    $st->execute([$invoiceId]);
    return $st->fetchAll();
}

/** draft | sent | paid | overdue (overdue is derived from the due date). */
function invoice_status(array $inv): string
{
    if ($inv['status'] === 'paid') return 'paid';
    if ($inv['status'] === 'sent' && $inv['due_date'] && $inv['due_date'] < date('Y-m-d')) return 'overdue';
    return $inv['status'];
}

function next_invoice_number(): string
{
    $prefix = 'INV-' . date('Y') . '-';
    $st = db()->prepare('SELECT number FROM invoices WHERE number LIKE ?');
    $st->execute([$prefix . '%']);
    $max = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $n) $max = max($max, (int)substr($n, strlen($prefix)));
    return $prefix . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

function origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . preg_replace('/[^A-Za-z0-9.:\-]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
}

function invoice_link(array $inv): string { return origin() . url('invoice.php?t=' . $inv['token']); }

function wa_number(string $phone): string
{
    $d = preg_replace('/\D+/', '', $phone);
    if ($d === '') return '';
    if ($d[0] === '0') $d = '62' . substr($d, 1);
    return $d;
}
