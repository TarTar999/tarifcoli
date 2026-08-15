<?php
declare(strict_types=1);

namespace LivraisonCm;

final class Text
{
    /** Minuscules sans accents : sert à l'autocomplétion tolérante ("bepanda" trouve "Bépanda"). */
    public static function normalize(string $s): string
    {
        $s = strtr($s, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
            'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ä' => 'a',
            'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'Î' => 'i', 'Ï' => 'i', 'Ô' => 'o', 'Ö' => 'o',
            'Ù' => 'u', 'Û' => 'u', 'Ü' => 'u', 'Ç' => 'c',
            '\'' => ' ', '-' => ' ', '.' => ' ', ',' => ' ',
        ]);

        $s = strtolower($s);

        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** Majuscules qui respectent les accents (strtoupper casse l'UTF-8). */
    public static function upper(string $s): string
    {
        if (function_exists('mb_strtoupper')) {
            return mb_strtoupper($s, 'UTF-8');
        }

        return strtr(strtoupper($s), [
            'é' => 'É', 'è' => 'È', 'ê' => 'Ê', 'ë' => 'Ë', 'à' => 'À', 'â' => 'Â',
            'î' => 'Î', 'ï' => 'Ï', 'ô' => 'Ô', 'ö' => 'Ö', 'ù' => 'Ù', 'û' => 'Û',
            'ü' => 'Ü', 'ç' => 'Ç', 'œ' => 'Œ',
        ]);
    }

    /** Les polices PDF de base parlent WinAnsi, pas UTF-8. */
    public static function toWinAnsi(string $s): string
    {
        if (function_exists('mb_convert_encoding')) {
            $out = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if ($out !== false) {
                return $out;
            }
        }
        $out = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $s);

        return $out === false ? $s : $out;
    }

    public static function money(int $fcfa): string
    {
        return number_format($fcfa, 0, ',', ' ') . ' FCFA';
    }
}
