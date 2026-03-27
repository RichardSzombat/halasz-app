<?php

namespace App\Support;

use App\Models\BillableItem;

class BillableItemCatalog
{
    public static function defaults(): array
    {
        return [
            ['name' => 'Install', 'price' => 4200],
            ['name' => 'Módosítás', 'price' => 3600],
            ['name' => 'Hibajavítás', 'price' => 4200],
            ['name' => 'Szelfinstall', 'price' => 4200],
            ['name' => 'Kötés', 'price' => 1500],
            ['name' => 'UTP', 'price' => 1700],
            ['name' => 'IP+', 'price' => 1250],
            ['name' => 'Sűrítés 10m>', 'price' => 5200],
            ['name' => 'Sűrítés 10m<', 'price' => 8500],
            ['name' => 'Vételi hely', 'price' => 3000],
        ];
    }

    public static function orderedNames(): array
    {
        return array_column(self::defaults(), 'name');
    }

    public static function orderBySql(string $column = 'name'): string
    {
        $cases = collect(self::orderedNames())
            ->values()
            ->map(fn (string $name, int $index): string => "WHEN '{$name}' THEN {$index}")
            ->implode(' ');

        $fallbackIndex = count(self::orderedNames()) + 1;

        return "CASE {$column} {$cases} ELSE {$fallbackIndex} END";
    }

    public static function ensureSeeded(): void
    {
        foreach (self::defaults() as $item) {
            BillableItem::query()->updateOrCreate(
                ['name' => $item['name']],
                ['price' => $item['price']],
            );
        }
    }
}
