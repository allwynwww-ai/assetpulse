<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Sequential, per-company document numbers by financial year,
 * e.g. TRF/2526/0007, WO/2627/0012, DR/2526/0004.
 */
class DocumentNumber
{
    public static function next(string $modelClass, string $column, string $prefix, $date = null, int $fyStartMonth = 4): string
    {
        $d = Carbon::parse($date ?? now());
        $y = $d->month >= $fyStartMonth ? $d->year : $d->year - 1;
        $base = $prefix.'/'.substr((string) $y, 2).substr((string) ($y + 1), 2).'/';
        $last = $modelClass::withTrashed()->where($column, 'like', $base.'%')->lockForUpdate()->orderByDesc($column)->value($column);
        $n = $last ? ((int) substr($last, strlen($base))) + 1 : 1;

        return $base.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
