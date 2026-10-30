<?php

if (! function_exists('inr')) {
    /** Format a number in Indian grouping (12,34,56,789.00) with optional ₹ symbol. */
    function inr($amount, int $decimals = 2, bool $symbol = true): string
    {
        $amount = (float) $amount;
        $neg = $amount < 0;
        $amount = abs($amount);
        $parts = explode('.', number_format($amount, $decimals, '.', ''));
        $int = $parts[0];
        $last3 = substr($int, -3);
        $rest = substr($int, 0, -3);
        if ($rest !== '' && $rest !== false) {
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int = $rest.','.$last3;
        }
        $out = $int.($decimals > 0 ? '.'.$parts[1] : '');

        return ($neg ? '-' : '').($symbol ? '₹' : '').$out;
    }
}

if (! function_exists('inr_short')) {
    /** Compact INR: ₹4.25 Cr, ₹12.4 L, ₹85.0 K */
    function inr_short($amount): string
    {
        $a = (float) $amount;
        $abs = abs($a);

        return match (true) {
            $abs >= 1e7 => '₹'.number_format($a / 1e7, 2).' Cr',
            $abs >= 1e5 => '₹'.number_format($a / 1e5, 2).' L',
            $abs >= 1e3 => '₹'.number_format($a / 1e3, 1).' K',
            default => '₹'.number_format($a, 0),
        };
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date($date, string $format = 'd M Y'): string
    {
        return $date ? \Illuminate\Support\Carbon::parse($date)->format($format) : '—';
    }
}

if (! function_exists('greeting')) {
    /** Time-of-day greeting in the app timezone (Asia/Kolkata): 05–11 morning, 12–16 afternoon, otherwise evening. */
    function greeting(?\DateTimeInterface $at = null): string
    {
        $h = (int) \Illuminate\Support\Carbon::instance($at ?? now())->setTimezone(config('app.timezone'))->format('G');

        return match (true) {
            $h >= 5 && $h < 12 => 'Good morning',
            $h >= 12 && $h < 17 => 'Good afternoon',
            default => 'Good evening',
        };
    }
}
