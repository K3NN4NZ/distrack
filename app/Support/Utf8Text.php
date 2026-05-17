<?php

namespace App\Support;

final class Utf8Text
{
    /**
     * Common UTF-8 misinterpreted as Windows-1252 / Latin-1 sequences.
     *
     * @var array<string, string>
     */
    private const MOJIBAKE_MAP = [
        'Ã±' => 'ñ',
        'Ã‘' => 'Ñ',
        'Ã¡' => 'á',
        'Ã©' => 'é',
        'Ã­' => 'í',
        'Ã³' => 'ó',
        'Ãº' => 'ú',
        'Ã¼' => 'ü',
        'Ãœ' => 'Ü',
        'Ã‰' => 'É',
        'Ã¢' => 'â',
        'Ãª' => 'ê',
        'Ã´' => 'ô',
        'Ã§' => 'ç',
        'Ã˜' => 'Ø',
        'Ã¸' => 'ø',
        'Ã…' => 'Å',
        'Ã¥' => 'å',
        'Â·' => '·',
        'Â°' => '°',
        'Â«' => '«',
        'Â»' => '»',
        'â€™' => "'",
        'â€œ' => '"',
        'â€' => '"',
        'â€"' => '—',
        'â€“' => '–',
    ];

    /**
     * @var list<string>
     */
    private const MOJIBAKE_MARKERS = [
        'Ã',
        'Â',
        'â€™',
        'â€œ',
        'â€',
        'â€"',
        'â€"',
    ];

    public static function normalizeUtf8(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        if (! mb_check_encoding($value, 'UTF-8')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'Windows-1252, ISO-8859-1, UTF-8');

            if (is_string($converted) && $converted !== '') {
                $value = $converted;
            }
        }

        return $value;
    }

    public static function looksLikeMojibake(string $value): bool
    {
        foreach (self::MOJIBAKE_MARKERS as $marker) {
            if (str_contains($value, $marker)) {
                return true;
            }
        }

        return false;
    }

    public static function fixMojibake(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        $fixed = strtr($value, self::MOJIBAKE_MAP);

        if (self::looksLikeMojibake($fixed)) {
            $recovered = self::recoverUtf8MisinterpretedAsLatin1($fixed);

            if ($recovered !== null && $recovered !== $fixed) {
                $fixed = strtr($recovered, self::MOJIBAKE_MAP);
            }
        }

        return $fixed;
    }

    /**
     * Normalize encoding and repair common mojibake before persisting or displaying.
     */
    public static function prepareForStorage(?string $value): ?string
    {
        $value = self::normalizeUtf8($value);

        if ($value === null || $value === '') {
            return $value;
        }

        if (self::looksLikeMojibake($value)) {
            return self::fixMojibake($value);
        }

        return $value;
    }

    public static function repair(?string $value): ?string
    {
        return self::prepareForStorage($value);
    }

    public static function mojibakeScore(string $value): int
    {
        $score = 0;

        foreach (self::MOJIBAKE_MARKERS as $marker) {
            $score += substr_count($value, $marker);
        }

        return $score;
    }

    private static function recoverUtf8MisinterpretedAsLatin1(string $value): ?string
    {
        $recovered = @iconv('UTF-8', 'ISO-8859-1//IGNORE', $value);

        if (! is_string($recovered) || $recovered === '' || ! mb_check_encoding($recovered, 'UTF-8')) {
            return null;
        }

        if (self::mojibakeScore($recovered) >= self::mojibakeScore($value)) {
            return null;
        }

        return $recovered;
    }
}
