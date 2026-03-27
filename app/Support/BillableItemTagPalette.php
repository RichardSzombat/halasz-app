<?php

namespace App\Support;

class BillableItemTagPalette
{
    private const FIXED_MAP = [
        'install' => 'bg-cyan-500/80 text-white border-cyan-300/20',
        'hibajavítás' => 'bg-emerald-500/80 text-white border-emerald-300/20',
        'szelfinstall' => 'bg-amber-500/85 text-slate-950 border-amber-200/30',
        'kötés' => 'bg-fuchsia-500/80 text-white border-fuchsia-300/20',
        'utp' => 'bg-sky-500/80 text-white border-sky-300/20',
        'sűrítés 10m>' => 'bg-lime-500/85 text-slate-950 border-lime-200/30',
        'sűrítés 10m<' => 'bg-rose-500/80 text-white border-rose-300/20',
        'vételi hely' => 'bg-violet-500/80 text-white border-violet-300/20',
    ];

    private const PALETTE = [
        'bg-cyan-500/80 text-white border-cyan-300/20',
        'bg-emerald-500/80 text-white border-emerald-300/20',
        'bg-amber-500/85 text-slate-950 border-amber-200/30',
        'bg-fuchsia-500/80 text-white border-fuchsia-300/20',
        'bg-sky-500/80 text-white border-sky-300/20',
        'bg-lime-500/85 text-slate-950 border-lime-200/30',
        'bg-rose-500/80 text-white border-rose-300/20',
        'bg-violet-500/80 text-white border-violet-300/20',
    ];

    public static function classesFor(string $name): string
    {
        $normalized = mb_strtolower(trim($name));

        if (isset(self::FIXED_MAP[$normalized])) {
            return self::FIXED_MAP[$normalized];
        }

        $index = abs(crc32($normalized)) % count(self::PALETTE);

        return self::PALETTE[$index];
    }

    public static function mapForNames(iterable $names): array
    {
        $map = [];

        foreach ($names as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $map[$name] = self::classesFor($name);
        }

        return $map;
    }
}
