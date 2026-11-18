<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Pengaturan resto: satu baris per instalasi. */
class Brand extends Model
{
    public const DEFAULT_ACCENT = '#0a1f4d';

    protected $table = 'brand';

    public $timestamps = false;

    protected $guarded = [];

    public static function current(): self
    {
        return self::query()->findOrFail(1);
    }

    /** Kode metode pembayaran yang diaktifkan resto. */
    public function enabledMethods(): array
    {
        return array_map('trim', explode(',', $this->enabled_methods));
    }

    /** Warna aksen resto. Hanya #rrggbb yang diterima; nilai lain jatuh ke biru gelap bawaan, jadi aman dipasang di CSS. */
    public function accent(): string
    {
        $color = (string) $this->accent_color;
        return preg_match('/^#[0-9a-f]{6}$/i', $color) === 1 ? strtolower($color) : self::DEFAULT_ACCENT;
    }

    /** Aksen sedikit lebih terang untuk keadaan hover. */
    public function accentHover(): string
    {
        [$red, $green, $blue] = sscanf($this->accent(), '#%02x%02x%02x');
        $lighten = fn (int $channel) => (int) round($channel + (255 - $channel) * 0.14);

        return sprintf('#%02x%02x%02x', $lighten($red), $lighten($green), $lighten($blue));
    }

    /** Warna teks di atas aksen: putih, atau gelap bila aksen terang (kontras WCAG). */
    public function onAccent(): string
    {
        [$red, $green, $blue] = sscanf($this->accent(), '#%02x%02x%02x');
        $linear = fn (int $channel) => (($c = $channel / 255) <= 0.03928) ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $luminance = 0.2126 * $linear($red) + 0.7152 * $linear($green) + 0.0722 * $linear($blue);

        return $luminance > 0.4 ? '#0a1226' : '#ffffff';
    }
}
