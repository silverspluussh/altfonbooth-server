<?php

namespace App\Support;

final class Msisdn
{
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '233')) {
            $local = '0' . substr($digits, 3);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $local = $digits;
        } elseif (strlen($digits) === 9) {
            $local = '0' . $digits;
        } else {
            return null;
        }

        return preg_match('/^0\d{9}$/', $local) === 1 ? $local : null;
    }

    public static function toInternational(string $value): ?string
    {
        $local = self::normalize($value);

        return $local === null ? null : '233' . substr($local, 1);
    }

    /**
     * Canonical format for persisting a phone number.
     *
     * Ghanaian numbers are stored in the local form the USSD lookup queries
     * (0XXXXXXXXX). Anything else is reduced to digits so repeated lookups
     * stay consistent, with the trimmed input kept as a last resort.
     */
    public static function toStorage(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $local = self::normalize($trimmed);

        if ($local !== null) {
            return $local;
        }

        $digits = preg_replace('/\D+/', '', $trimmed) ?? '';

        return $digits !== '' ? $digits : $trimmed;
    }

    /**
     * Stored values a lookup should match, including the raw input so rows
     * written before normalization still resolve during rollout.
     */
    public static function storageCandidates(?string $value): array
    {
        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return [];
        }

        $candidates = [$trimmed];

        $storage = self::toStorage($trimmed);

        if ($storage !== null) {
            $candidates[] = $storage;
        }

        return array_values(array_unique($candidates));
    }
}
