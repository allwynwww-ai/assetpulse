<?php

namespace App\Services;

use App\Models\PeriodLock;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Central check for finalised (locked) depreciation periods. Any transaction
 * dated inside a locked period is refused with a friendly validation error.
 */
class PeriodGuard
{
    public static function isLocked($date): bool
    {
        return $date !== null && PeriodLock::isLocked($date);
    }

    /** Last day of the latest locked period, if any. */
    public static function lockedThrough(): ?Carbon
    {
        $d = PeriodLock::where('is_locked', true)->max('period_end');

        return $d ? Carbon::parse($d) : null;
    }

    public static function assertOpen($date, string $field, string $what = 'This transaction'): void
    {
        if (static::isLocked($date)) {
            $lock = PeriodLock::where('is_locked', true)->where('period_start', '<=', Carbon::parse($date)->toDateString())
                ->where('period_end', '>=', Carbon::parse($date)->toDateString())->first();
            throw ValidationException::withMessages([
                $field => "{$what} is dated ".fmt_date($date).', which falls in the locked period '.($lock?->period_label ?? '').
                    '. Depreciation for that period has been finalised; choose a date after '.fmt_date(static::lockedThrough()).' or ask a Company Admin to unlock the period.',
            ]);
        }
    }
}
