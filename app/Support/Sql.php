<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Tiny driver-aware SQL fragment builder so raw expressions work on MySQL/MariaDB (default)
 * and PostgreSQL (Render). Keep raw SQL out of controllers; add fragments here when needed.
 * Arguments are trusted column names/expressions only, never user input.
 */
final class Sql
{
    public static function driver(?string $connection = null): string
    {
        return DB::connection($connection)->getDriverName();
    }

    public static function isPgsql(): bool
    {
        return self::driver() === 'pgsql';
    }

    /** DATE expression: $date + ($monthsExpr) months [- $minusDays days]. */
    public static function addMonths(string $date, string $monthsExpr, int $minusDays = 0): string
    {
        if (self::isPgsql()) {
            $e = "($date + make_interval(months => ($monthsExpr)::int))";

            return '('.$e.($minusDays ? " - interval '$minusDays day'" : '').')::date';
        }
        $e = "DATE_ADD($date, INTERVAL ($monthsExpr) MONTH)";

        return $minusDays ? "DATE_SUB($e, INTERVAL $minusDays DAY)" : $e;
    }

    /** DATE expression: today + ($daysExpr) days. */
    public static function todayPlusDays(string $daysExpr): string
    {
        return self::isPgsql()
            ? "(CURRENT_DATE + ($daysExpr)::int)"
            : "DATE_ADD(CURDATE(), INTERVAL ($daysExpr) DAY)";
    }

    /** 'YYYY-MM' string of a date column. */
    public static function yearMonth(string $date): string
    {
        return self::isPgsql() ? "to_char($date, 'YYYY-MM')" : "DATE_FORMAT($date, '%Y-%m')";
    }

    /** Portable ORDER BY expression that sorts $column by the given value order (replacement for MySQL FIELD()). */
    public static function orderByList(string $column, array $values): string
    {
        $cases = collect(array_values($values))->map(fn ($v, $i) => 'WHEN '.DB::getPdo()->quote($v)." THEN $i")->implode(' ');

        return "CASE $column $cases ELSE ".count($values).' END';
    }
}
