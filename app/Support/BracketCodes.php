<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Normalizes bracket labels for storage and matching (shared by admin + ranking).
 */
final class BracketCodes
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z]+$/', $value) === 1) {
            return 'Bracket '.Str::upper($value);
        }

        if (str_starts_with(Str::lower($value), 'bracket ')) {
            $suffix = trim(Str::after($value, ' '));

            return 'Bracket '.Str::upper($suffix);
        }

        return Str::of($value)->squish()->title()->toString();
    }

    /**
     * Build rank labels like A1, B2 from a normalized code such as "Bracket A".
     */
    public static function rankPrefixFromCode(string $normalizedBracketCode): string
    {
        $normalizedBracketCode = trim($normalizedBracketCode);

        if (preg_match('/^Bracket\s+([A-Za-z]+)$/i', $normalizedBracketCode, $matches) === 1) {
            return strtoupper($matches[1]);
        }

        $squished = Str::squish($normalizedBracketCode);

        return strtoupper(Str::substr($squished, 0, 1));
    }
}
