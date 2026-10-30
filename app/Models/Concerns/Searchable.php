<?php

namespace App\Models\Concerns;

trait Searchable
{
    /** Columns used by the generic search box; override per model. */
    public static function searchable(): array
    {
        return ['code', 'name'];
    }

    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        return $query->where(function ($q) use ($term) {
            foreach (static::searchable() as $col) {
                $q->orWhere($this->qualifyColumn($col), 'like', '%'.$term.'%');
            }
        });
    }
}
