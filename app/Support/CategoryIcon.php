<?php

namespace App\Support;

/** Picks an icon + tint for an asset category from its own code / name (keyword match). */
final class CategoryIcon
{
    private const RULES = [
        ['/mould|die/i', 'mould', 'amber'],
        ['/server|network/i', 'server', 'slate'],
        ['/computer|laptop|\bit\b|desktop/i', 'monitor', 'sky'],
        ['/vehicle|truck|car\b|fork/i', 'truck', 'orange'],
        ['/building|factory shed|civil/i', 'building', 'violet'],
        ['/electric|power|solar|substation/i', 'zap', 'teal'],
        ['/furniture|fixture/i', 'sofa', 'rose'],
        ['/tool|gauge|instrument|measur/i', 'ruler', 'emerald'],
        ['/auxiliar|chiller|dryer|robot|compressor/i', 'fan', 'sky'],
        ['/machin|plant|press|cnc/i', 'factory', 'brand'],
    ];

    /** @return array{0:string,1:string} [icon, tint classes] */
    public static function for(?object $category): array
    {
        $hay = trim(($category->name ?? '').' '.($category->code ?? ''));
        foreach (self::RULES as [$re, $icon, $c]) {
            if ($hay !== '' && preg_match($re, $hay)) {
                return [$icon, self::tint($c)];
            }
        }

        return ['package', self::tint('brand')];
    }

    public static function tint(string $c): string
    {
        return "bg-{$c}-500/10 text-{$c}-600 dark:text-{$c}-300";
    }
}
