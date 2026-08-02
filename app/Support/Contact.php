<?php

namespace App\Support;

/**
 * Email and phone reduced to a comparable form.
 *
 * The users table stores whatever was typed — "+45 12 34 56 78", "0045 12345678"
 * and "12345678" are three distinct strings today — so anything that matches a
 * person by contact details has to normalise both sides first.
 */
class Contact
{
    public static function normalizeEmail(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtolower($value);
    }

    /**
     * A Danish number reduced to its 8 significant digits. Anything that does
     * not look Danish keeps its full digit string rather than being mangled.
     */
    public static function normalizePhone(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (strlen($digits) === 10 && str_starts_with($digits, '45')) {
            $digits = substr($digits, 2);
        }

        return $digits;
    }
}
