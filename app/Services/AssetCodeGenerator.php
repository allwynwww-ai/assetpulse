<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetCodeSequence;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Per-company, per-prefix asset numbering, e.g. IMM-0001, MLD-0042.
 * Uses a row lock on asset_code_sequences so concurrent creates never collide.
 */
class AssetCodeGenerator
{
    public function preview(AssetCategory $category): string
    {
        $seq = AssetCodeSequence::where('prefix', $this->prefix($category))->value('last_number') ?? 0;

        return $this->format($category, $seq + 1);
    }

    public function next(AssetCategory $category): string
    {
        return DB::transaction(function () use ($category) {
            $prefix = $this->prefix($category);
            $seq = AssetCodeSequence::where('prefix', $prefix)->lockForUpdate()->first();
            if (! $seq) {
                $seq = AssetCodeSequence::create(['prefix' => $prefix, 'last_number' => 0]);
                $seq = AssetCodeSequence::whereKey($seq->id)->lockForUpdate()->first();
            }
            do {
                $seq->last_number++;
                $code = $this->format($category, $seq->last_number);
            } while (Asset::withTrashed()->where('asset_code', $code)->exists());
            $seq->save();

            return $code;
        });
    }

    protected function prefix(AssetCategory $category): string
    {
        return strtoupper($category->asset_code_prefix ?: $category->code);
    }

    protected function format(AssetCategory $category, int $n): string
    {
        $company = Tenant::company() ?? $category->company;
        $sep = $company?->asset_code_separator ?? '-';
        $pad = $company?->asset_code_padding ?: 4;

        return $this->prefix($category).$sep.str_pad((string) $n, $pad, '0', STR_PAD_LEFT);
    }
}
