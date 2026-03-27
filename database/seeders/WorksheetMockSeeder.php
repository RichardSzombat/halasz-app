<?php

namespace Database\Seeders;

use App\Models\BillableItem;
use App\Models\Worksheet;
use App\Support\BillableItemCatalog;
use Illuminate\Database\Seeder;

class WorksheetMockSeeder extends Seeder
{
    public function run(): void
    {
        $billableItems = BillableItem::query()
            ->orderByRaw(BillableItemCatalog::orderBySql('name'))
            ->get();

        if ($billableItems->isEmpty()) {
            return;
        }

        $dates = [
            '2026-02-26',
            '2026-03-15',
            '2026-03-25',
            '2026-03-26',
            '2026-03-27',
        ];

        foreach ($dates as $dateIndex => $date) {
            for ($sequence = 1; $sequence <= 5; $sequence++) {
                $worksheetNumber = sprintf('MOCK-%s-%02d', str_replace('-', '', $date), $sequence);

                $worksheet = Worksheet::query()->updateOrCreate(
                    ['worksheet_number' => $worksheetNumber],
                    [
                        'work_date' => $date,
                        'note' => $this->buildNote($dateIndex, $sequence),
                    ],
                );

                $selectedItems = $this->buildItemsForWorksheet($billableItems->all(), $dateIndex, $sequence);
                $worksheet->items()->delete();

                foreach ($selectedItems as $itemConfig) {
                    $worksheet->items()->create([
                        'billable_item_id' => $itemConfig['item']->id,
                        'item_name_at_time' => $itemConfig['item']->name,
                        'price_at_time' => $itemConfig['item']->price,
                        'quantity' => $itemConfig['quantity'],
                    ]);
                }
            }
        }
    }

    private function buildItemsForWorksheet(array $billableItems, int $dateIndex, int $sequence): array
    {
        $count = count($billableItems);
        $start = (($dateIndex * 3) + ($sequence * 2)) % $count;
        $take = 2 + (($dateIndex + $sequence) % 3);
        $items = [];

        for ($offset = 0; $offset < $take; $offset++) {
            $item = $billableItems[($start + $offset) % $count];
            $items[] = [
                'item' => $item,
                'quantity' => (($dateIndex + $sequence + $offset) % 3) + 1,
            ];
        }

        return $items;
    }

    private function buildNote(int $dateIndex, int $sequence): string
    {
        $notes = [
            'Délelőtti kiszállás, minden lezárva.',
            'Több tételes munkalap, ügyfél helyszíni egyeztetéssel.',
            'Utólagos ellenőrzés és kiegészítő javítás.',
            'Gyors kiszállás, tiszta átadás.',
            'Vegyes feladatok, teljesítve a napi terv szerint.',
        ];

        return $notes[($dateIndex + $sequence - 1) % count($notes)];
    }
}
