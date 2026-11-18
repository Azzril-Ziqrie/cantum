<?php

namespace App\Services;

use App\Models\DiningTable;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/** URL dan gambar QR per meja. URL yang sama ditulis ke tag NFC. */
class TableQrService
{
    private const INK = '#0b0b0b';

    public function url(DiningTable $table): string
    {
        return route('menu.show', $table->token);
    }

    /** SVG berdiri sendiri berisi kode QR (dengan zona tenang) untuk URL meja. */
    public function svg(DiningTable $table): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
        ]);

        return str_replace('#000', self::INK, (new QRCode($options))->render($this->url($table)));
    }

    /** Stiker 800x800 berdiri sendiri (gaya stiker OnePay) untuk diunduh atau dicetak. */
    public function stickerSvg(DiningTable $table, string $brandName): string
    {
        $qr = $this->positioned($this->svg($table), x: 220, y: 146, size: 360);
        $label = e(mb_strtoupper('Meja ' . $table->label));
        $brand = e(mb_strtoupper($brandName));
        $font = 'font-family="JetBrains Mono, monospace"';

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="800" viewBox="0 0 800 800">
        <rect width="800" height="800" rx="64" fill="#fff"/>
        <rect x="14" y="14" width="772" height="772" rx="52" fill="none" stroke="#0b0b0b" stroke-width="3"/>
        <text x="400" y="86" text-anchor="middle" $font font-size="22" letter-spacing="4" fill="#77777a">$brand</text>
        <rect x="190" y="116" width="420" height="420" rx="28" fill="#f4f4f4"/>
        <rect x="210" y="136" width="380" height="380" rx="18" fill="#fff"/>
        $qr
        <text x="400" y="598" text-anchor="middle" $font font-size="42" letter-spacing="-2" fill="#0b0b0b">Scan untuk pesan</text>
        <text x="400" y="640" text-anchor="middle" $font font-size="20" fill="#77777a">Bayar QRIS atau virtual account</text>
        <rect x="290" y="672" width="220" height="62" rx="31" fill="#0b0b0b"/>
        <text x="400" y="713" text-anchor="middle" $font font-size="30" letter-spacing="2" fill="#fff">$label</text>
        <text x="400" y="765" text-anchor="middle" $font font-size="14" letter-spacing="3" fill="#a3a3a3">CANTUM ONEPAY</text>
        </svg>
        SVG;
    }

    /** Menyisipkan SVG QR sebagai elemen bersarang pada posisi dan ukuran tertentu. */
    private function positioned(string $svg, int $x, int $y, int $size): string
    {
        return preg_replace('/^<svg xmlns="[^"]*"/', "<svg x=\"$x\" y=\"$y\" width=\"$size\" height=\"$size\"", $svg, 1);
    }
}
