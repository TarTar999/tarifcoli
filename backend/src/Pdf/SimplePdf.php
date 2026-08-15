<?php
declare(strict_types=1);

namespace LivraisonCm\Pdf;

use LivraisonCm\Text;

/**
 * Générateur PDF minimal, sans aucune dépendance Composer.
 *
 * Il ne gère que ce dont la facture a besoin : texte (Helvetica / Courier),
 * traits, rectangles, cercles, texte pivoté. L'origine est en HAUT À GAUCHE,
 * en points (1 pt = 1/72 pouce). A4 = 595 × 842 pt.
 */
final class SimplePdf
{
    public const A4_W = 595.28;
    public const A4_H = 841.89;

    private string $buf = '';
    private float $w;
    private float $h;

    /** Largeurs Helvetica / Helvetica-Bold pour les codes 32→126 (unités /1000). */
    private const W_REG = '278278355556556889667191333333389584278333278278556556556556556556556556556556278278584584584556999667667722722667611778722278500667556833722778667778722667611722667944667667611278278278469556333556556500556556278556556222222500222833556556556556333500278556500722500500500334260334584';
    private const W_BOLD = '278333474556556889722238333333389584278333278278556556556556556556556556556556333333584584584611975722722722722667611778722278556722611833722778667778722667611722667944667667611333278333584556333556611556611556333611611278278556278889611611611611389556333611556778556556500389280389584';

    private array $fonts = [
        'H'  => 'Helvetica',
        'HB' => 'Helvetica-Bold',
        'C'  => 'Courier',
        'CB' => 'Courier-Bold',
    ];

    public function __construct(float $w = self::A4_W, float $h = self::A4_H)
    {
        $this->w = $w;
        $this->h = $h;
    }

    // ---------------------------------------------------------------- mesures

    public function width(): float
    {
        return $this->w;
    }

    public function height(): float
    {
        return $this->h;
    }

    public function textWidth(string $s, float $size, string $font = 'H'): float
    {
        $s = Text::toWinAnsi($s);

        if ($font === 'C' || $font === 'CB') {
            return strlen($s) * 0.6 * $size;
        }

        $table = $font === 'HB' ? self::W_BOLD : self::W_REG;
        $total = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $c = ord($s[$i]);
            $w = ($c >= 32 && $c <= 126) ? (int) substr($table, ($c - 32) * 3, 3) : 556;
            $total += $w;
        }

        return $total / 1000 * $size;
    }

    // ---------------------------------------------------------------- dessin

    public function text(float $x, float $y, string $s, float $size = 10, string $font = 'H', string $color = '#000000'): void
    {
        $this->buf .= sprintf(
            "BT %s /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n",
            $this->rgb($color, true),
            $font,
            $size,
            $x,
            $this->y($y) - $size * 0.78,
            $this->esc($s)
        );
    }

    public function textCenter(float $cx, float $y, string $s, float $size = 10, string $font = 'H', string $color = '#000000'): void
    {
        $this->text($cx - $this->textWidth($s, $size, $font) / 2, $y, $s, $size, $font, $color);
    }

    public function textRight(float $right, float $y, string $s, float $size = 10, string $font = 'H', string $color = '#000000'): void
    {
        $this->text($right - $this->textWidth($s, $size, $font), $y, $s, $size, $font, $color);
    }

    /** Texte pivoté autour de son point d'ancrage (angle en degrés, sens antihoraire). */
    public function textRotated(float $x, float $y, string $s, float $angle, float $size = 10, string $font = 'H', string $color = '#000000', bool $center = false): void
    {
        $a = deg2rad($angle);
        $dx = $center ? -$this->textWidth($s, $size, $font) / 2 : 0.0;

        $this->buf .= sprintf(
            "q %s %.4F %.4F %.4F %.4F %.2F %.2F cm BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET Q\n",
            $this->rgb($color, true),
            cos($a), sin($a), -sin($a), cos($a),
            $x, $this->y($y),
            $font,
            $size,
            $dx, -$size * 0.35,
            $this->esc($s)
        );
    }

    public function fillRect(float $x, float $y, float $w, float $h, string $color): void
    {
        $this->buf .= sprintf("%s %.2F %.2F %.2F %.2F re f\n", $this->rgb($color, true), $x, $this->y($y) - $h, $w, $h);
    }

    public function strokeRect(float $x, float $y, float $w, float $h, string $color, float $lw = 0.6): void
    {
        $this->buf .= sprintf(
            "%s %.2F w %.2F %.2F %.2F %.2F re S\n",
            $this->rgb($color, false), $lw, $x, $this->y($y) - $h, $w, $h
        );
    }

    public function line(float $x1, float $y1, float $x2, float $y2, string $color = '#000000', float $lw = 0.6, ?array $dash = null): void
    {
        $d = $dash ? sprintf('[%s] 0 d ', implode(' ', $dash)) : '[] 0 d ';
        $this->buf .= sprintf(
            "%s %.2F w %s%.2F %.2F m %.2F %.2F l S [] 0 d\n",
            $this->rgb($color, false), $lw, $d, $x1, $this->y($y1), $x2, $this->y($y2)
        );
    }

    public function circle(float $cx, float $cy, float $r, ?string $stroke = '#000000', ?string $fill = null, float $lw = 1.0): void
    {
        $k = 0.5522847498 * $r;
        $x = $cx;
        $y = $this->y($cy);

        $ops = '';
        if ($fill !== null) {
            $ops .= $this->rgb($fill, true) . ' ';
        }
        if ($stroke !== null) {
            $ops .= $this->rgb($stroke, false) . ' ';
        }

        $this->buf .= sprintf("%s%.2F w %.2F %.2F m ", $ops, $lw, $x + $r, $y);
        $this->buf .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c ", $x + $r, $y + $k, $x + $k, $y + $r, $x, $y + $r);
        $this->buf .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c ", $x - $k, $y + $r, $x - $r, $y + $k, $x - $r, $y);
        $this->buf .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c ", $x - $r, $y - $k, $x - $k, $y - $r, $x, $y - $r);
        $this->buf .= sprintf("%.2F %.2F %.2F %.2F %.2F %.2F c ", $x + $k, $y - $r, $x + $r, $y - $k, $x + $r, $y);

        if ($fill !== null && $stroke !== null) {
            $this->buf .= "B\n";
        } elseif ($fill !== null) {
            $this->buf .= "f\n";
        } else {
            $this->buf .= "S\n";
        }
    }

    /** Découpe un texte en lignes qui tiennent dans une largeur donnée. */
    public function wrap(string $s, float $maxWidth, float $size, string $font = 'H'): array
    {
        $mots = preg_split('/\s+/', trim($s)) ?: [];
        $lignes = [];
        $courante = '';

        foreach ($mots as $mot) {
            $essai = $courante === '' ? $mot : $courante . ' ' . $mot;
            if ($this->textWidth($essai, $size, $font) > $maxWidth && $courante !== '') {
                $lignes[] = $courante;
                $courante = $mot;
            } else {
                $courante = $essai;
            }
        }
        if ($courante !== '') {
            $lignes[] = $courante;
        }

        return $lignes;
    }

    // ---------------------------------------------------------------- sortie

    public function output(): string
    {
        $objets = [];

        $objets[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objets[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";

        $ressources = [];
        $n = 5;
        foreach ($this->fonts as $alias => $base) {
            $ressources[] = sprintf('/%s %d 0 R', $alias, $n);
            $objets[$n] = sprintf(
                '<< /Type /Font /Subtype /Type1 /BaseFont /%s /Encoding /WinAnsiEncoding >>',
                $base
            );
            $n++;
        }

        $objets[3] = sprintf(
            "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << %s >> >> /Contents 4 0 R >>",
            $this->w,
            $this->h,
            implode(' ', $ressources)
        );
        $objets[4] = "STREAM:" . $this->buf;

        ksort($objets);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objets as $id => $contenu) {
            $offsets[$id] = strlen($pdf);
            if (str_starts_with($contenu, 'STREAM:')) {
                $flux = substr($contenu, 7);
                $pdf .= $id . " 0 obj\n<< /Length " . strlen($flux) . " >>\nstream\n" . $flux . "endstream\nendobj\n";
            } else {
                $pdf .= $id . " 0 obj\n" . $contenu . "\nendobj\n";
            }
        }

        $total = count($objets) + 1;
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . $total . "\n0000000000 65535 f \n";
        for ($i = 1; $i < $total; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . $total . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";

        return $pdf;
    }

    // ---------------------------------------------------------------- privé

    private function y(float $y): float
    {
        return $this->h - $y;
    }

    private function esc(string $s): string
    {
        return strtr(Text::toWinAnsi($s), ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '']);
    }

    private function rgb(string $hex, bool $fill): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        return sprintf('%.3F %.3F %.3F %s', $r, $g, $b, $fill ? 'rg' : 'RG');
    }
}
