<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AssetAttachment extends TenantModel
{
    public const TYPES = ['invoice' => 'Invoice', 'photo' => 'Photo', 'warranty' => 'Warranty', 'insurance' => 'Insurance', 'manual' => 'Manual', 'other' => 'Other'];

    protected function casts(): array { return ['size' => 'integer']; }

    public static function searchable(): array { return ['title', 'original_name']; }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by')->withTrashed(); }

    public function getUrlAttribute(): string { return Storage::disk('public')->url($this->file_path); }
    public function getIsImageAttribute(): bool { return str_starts_with((string) $this->mime_type, 'image/'); }

    public function getSizeLabelAttribute(): string
    {
        $s = $this->size;

        return $s >= 1048576 ? round($s / 1048576, 1).' MB' : max(1, round($s / 1024)).' KB';
    }
}
