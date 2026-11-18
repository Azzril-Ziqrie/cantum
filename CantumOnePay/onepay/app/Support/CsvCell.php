<?php

namespace App\Support;

/** Sel CSV yang diawali = + - @ (atau tab/CR) dijalankan sebagai rumus oleh Excel; diberi awalan petik. */
final class CsvCell
{
    private const FORMULA_STARTS = "=+-@\t\r";

    public static function safe(mixed $cell): mixed
    {
        if (!is_string($cell) || $cell === '') {
            return $cell;
        }
        return strpbrk($cell[0], self::FORMULA_STARTS) !== false ? "'" . $cell : $cell;
    }
}
