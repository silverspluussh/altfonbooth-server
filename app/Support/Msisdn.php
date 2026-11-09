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
}
