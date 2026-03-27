<?php

namespace App\Support;

use App\Models\BillableItem;

class BillableItemCatalog
{
    public static function defaults(): array
    {
        return [
            ['name' => 'Install', 'price' => 4200],
            ['name' => 'Hibajavítás', 'price' => 4200],
            ['name' => 'Szelfinstall', 'price' => 4200],
            ['name' => 'Kötés', 'price' => 1500],
            ['name' => 'UTP', 'price' => 1700],
            ['name' => 'Sűrítés 10m>', 'price' => 5200],
            ['name' => 'Sűrítés 10m<', 'price' => 8500],
            ['name' => 'Vételi hely', 'price' => 3000],
        ];
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
