<?php
// Cantum Studio — live demo helpers. Each demo is one JSON file in data/demos/ (never served over HTTP);
// its uploads live in uploads/demo/<id>/. "sample" is a built-in demo so the pages work before anyone fills the form.
// Visitor files are temporary: a demo is deleted (JSON + uploads) DEMO_TTL seconds after it was created,
// or at once when the visitor presses "delete my demo".
const DEMO_ID_PATTERN = '/^[a-f0-9]{12}$/';
const DEMO_LAYOUTS = ['studio', 'gallery', 'market'];
const DEMO_TTL = 2 * 3600;

function demo_path(string $id): string { return ROOT . '/data/demos/' . $id . '.json'; }
function demo_upload_dir(string $id): string { return UPLOAD_DIR . '/demo/' . $id; }

function demo_defaults(): array
{
    return [
        'brand'    => 'Your Brand',
        'logo'     => null,        // filename inside uploads/demo/<id>/
        'hero'     => null,
        'layout'   => 'studio',
        'products' => [],          // [['name' => string, 'price' => int, 'image' => ?filename]]
        'created'  => time(),
    ];
}

function demo_save(string $id, array $cfg): void
{
    if (!is_dir(dirname(demo_path($id)))) mkdir(dirname(demo_path($id)), 0775, true);
    file_put_contents(demo_path($id), json_encode($cfg, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function demo_expires_at(array $cfg): int { return (int)($cfg['created'] ?? 0) + DEMO_TTL; }

function demo_expired(array $cfg): bool { return time() >= demo_expires_at($cfg); }

/** Wall-clock time in WIB (the studio's and its visitors' timezone), independent of the server's PHP timezone. */
function demo_clock(int $ts): string
{
    return (new DateTime('@' . $ts))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('H:i') . ' WIB';
}

/** Config for a demo id, or null when it doesn't exist or has expired (expired demos are deleted on the spot). */
function demo_load(string $id): ?array
{
    if ($id === 'sample') return demo_defaults();
    if (!preg_match(DEMO_ID_PATTERN, $id) || !is_file(demo_path($id))) return null;
    $cfg = json_decode((string)file_get_contents(demo_path($id)), true);
    if (!is_array($cfg)) return null;
    $cfg = array_replace(demo_defaults(), $cfg);
    if (demo_expired($cfg)) {
        demo_delete($id);
        return null;
    }
    demo_purge_expired();
    return $cfg;
}

/** Deletes a demo's JSON and every file the visitor uploaded for it. */
function demo_delete(string $id): void
{
    if (!preg_match(DEMO_ID_PATTERN, $id)) return;
    foreach (glob(demo_upload_dir($id) . '/*') ?: [] as $file) {
        if (is_file($file)) @unlink($file);
    }
    @rmdir(demo_upload_dir($id));
    @unlink(demo_path($id));
}

/** Sweeps every expired demo; runs at most every 10 minutes so no cron job is needed. */
function demo_purge_expired(): void
{
    $marker = ROOT . '/data/demos/.purged';
    if (is_file($marker) && time() - filemtime($marker) < 600) return;
    touch($marker);
    foreach (glob(ROOT . '/data/demos/*.json') ?: [] as $file) {
        $cfg = json_decode((string)file_get_contents($file), true);
        $id = basename($file, '.json');
        if (!is_array($cfg) || demo_expired($cfg)) demo_delete($id);
    }
}

function demo_asset_url(string $id, ?string $file): ?string
{
    return $file ? url('uploads/demo/' . $id . '/' . rawurlencode($file)) : null;
}

/** Placeholder catalogue for demos where the visitor skipped the product upload. */
function demo_sample_products(): array
{
    return [
        ['name' => 'Signature Piece',  'price' => 189000, 'image' => null],
        ['name' => 'Everyday Classic', 'price' => 129000, 'image' => null],
        ['name' => 'Limited Edition',  'price' => 249000, 'image' => null],
        ['name' => 'Starter Bundle',   'price' => 299000, 'image' => null],
        ['name' => 'Gift Set',         'price' => 349000, 'image' => null],
        ['name' => 'Best Seller',      'price' => 159000, 'image' => null],
    ];
}

// ---- uploads: every image is decoded and re-encoded (no EXIF/GPS, no embedded payloads), resized, and renamed ----
const DEMO_MAX_PRODUCTS = 6;
const DEMO_MAX_FILE = 5 * 1024 * 1024;
const DEMO_MAX_ACTIVE = 300;       // demos alive at once, so the disk can't be filled
const DEMO_MAX_PER_VISITOR = 4;

/** Decodes an uploaded JPG/PNG (WebP when GD supports it) into a GD image; null for anything else. */
function demo_read_image(string $tmp)
{
    $info = @getimagesize($tmp);
    if (!$info || $info[0] * $info[1] > 25000000) return null;   // refuse decompression bombs
    $readers = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $fn = $readers[$mime] ?? null;
    if (!$fn || $mime !== $info['mime'] || !function_exists($fn)) return null;
    $im = @$fn($tmp);
    if (!$im) return null;
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {   // phones store rotation in EXIF, which re-encoding drops
        $angle = [3 => 180, 6 => -90, 8 => 90][@exif_read_data($tmp)['Orientation'] ?? 1] ?? 0;
        if ($angle) $im = imagerotate($im, $angle, 0) ?: $im;
    }
    return $im;
}

/** Writes $im into $dir under a random name, longest edge at most $maxEdge; PNG keeps transparency, JPG is for photos. */
function demo_store_image($im, string $dir, int $maxEdge, bool $transparent): ?string
{
    $w = imagesx($im);
    $h = imagesy($im);
    $scale = min(1, $maxEdge / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    if ($transparent) {
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    } else {
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    }
    imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    $name = bin2hex(random_bytes(8)) . ($transparent ? '.png' : '.jpg');
    $ok = $transparent ? imagepng($out, $dir . '/' . $name, 6) : imagejpeg($out, $dir . '/' . $name, 85);
    return $ok ? $name : null;
}

/** Product name from an uploaded file name: "kopi-susu_01.jpg" becomes "Kopi susu 01". */
function demo_product_name(string $filename, int $n): string
{
    $name = trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', pathinfo($filename, PATHINFO_FILENAME)));
    $name = mb_substr($name, 0, 40);
    return $name !== '' ? mb_strtoupper(mb_substr($name, 0, 1)) . mb_substr($name, 1) : 'Product ' . $n;
}

/** Anonymous visitor fingerprint (salted hash of the IP), kept only inside the demo's own JSON and deleted with it. */
function demo_visitor(): string
{
    return substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . SITE_URL), 0, 16);
}

/** Count of live demos, and how many of them belong to $visitor. */
function demo_active_counts(string $visitor): array
{
    $all = $mine = 0;
    foreach (glob(ROOT . '/data/demos/*.json') ?: [] as $file) {
        $cfg = json_decode((string)file_get_contents($file), true);
        if (!is_array($cfg) || demo_expired($cfg)) continue;
        $all++;
        if (($cfg['visitor'] ?? '') === $visitor) $mine++;
    }
    return [$all, $mine];
}
