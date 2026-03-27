<?php

namespace App\Support;

class BillableItemTagPalette
{
    private const FIXED_MAP = [
        'install' => 'background-color:#0891b2;color:#ffffff;border-color:#67e8f9;',
        'módosítás' => 'background-color:#2563eb;color:#ffffff;border-color:#93c5fd;',
        'hibajavítás' => 'background-color:#059669;color:#ffffff;border-color:#6ee7b7;',
        'szelfinstall' => 'background-color:#d97706;color:#fff7ed;border-color:#fcd34d;',
        'kötés' => 'background-color:#c026d3;color:#ffffff;border-color:#f0abfc;',
        'utp' => 'background-color:#0284c7;color:#ffffff;border-color:#7dd3fc;',
        'ip+' => 'background-color:#475569;color:#ffffff;border-color:#cbd5e1;',
        'sűrítés 10m>' => 'background-color:#65a30d;color:#f7fee7;border-color:#bef264;',
        'sűrítés 10m<' => 'background-color:#e11d48;color:#ffffff;border-color:#fda4af;',
        'vételi hely' => 'background-color:#7c3aed;color:#ffffff;border-color:#c4b5fd;',
    ];

    private const PALETTE = [
        'background-color:#0891b2;color:#ffffff;border-color:#67e8f9;',
        'background-color:#059669;color:#ffffff;border-color:#6ee7b7;',
        'background-color:#d97706;color:#fff7ed;border-color:#fcd34d;',
        'background-color:#c026d3;color:#ffffff;border-color:#f0abfc;',
        'background-color:#0284c7;color:#ffffff;border-color:#7dd3fc;',
        'background-color:#65a30d;color:#f7fee7;border-color:#bef264;',
        'background-color:#e11d48;color:#ffffff;border-color:#fda4af;',
        'background-color:#7c3aed;color:#ffffff;border-color:#c4b5fd;',
    ];

    public static function styleFor(string $name): string
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

            $map[$name] = self::styleFor($name);
        }

        return $map;
    }
}
