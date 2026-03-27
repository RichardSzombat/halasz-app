<?php

namespace Tests\Feature;

use App\Exports\WorksheetsExport;
use App\Models\BillableItem;
use App\Models\Worksheet;
use Database\Seeders\BillableItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class WorksheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_loads_without_broken_markup(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);

        $response = $this->get(route('worksheets.create'));

        $response->assertOk();
        $response->assertSee('Munkalap rögzítése');
        $response->assertDontSee('</div></div></button>', false);
        $response->assertSee('type="date"', false);
        $response->assertSee('max="'.now()->toDateString().'"', false);
    }

    public function test_edit_page_uses_separate_delete_form_to_avoid_nested_form_submission(): void
    {
        $this->withoutVite();
        $worksheet = Worksheet::query()->create([
            'worksheet_number' => 'TESZT-SZERK-01',
            'work_date' => '2026-03-27',
            'note' => 'Szerkesztés',
        ]);

        $response = $this->get(route('worksheets.edit', $worksheet));

        $response->assertOk();
        $response->assertSee('id="delete-worksheet-form"', false);
        $response->assertSee('form="delete-worksheet-form"', false);
    }

    public function test_index_page_uses_native_date_filters_with_today_max(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);

        $response = $this->get(route('worksheets.index'));

        $response->assertOk();
        $response->assertSee('id="from"', false);
        $response->assertSee('id="to"', false);
        $response->assertSee('type="date"', false);
        $response->assertSee('max="'.now()->toDateString().'"', false);
    }

    public function test_user_can_create_worksheet_with_multiple_distinct_items(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);

        $items = BillableItem::query()->take(2)->get();

        $response = $this->post(route('worksheets.store'), [
            'worksheet_number' => 'TESZT-20260327-01',
            'work_date' => '2026.03.27',
            'note' => 'Teszt mentés',
            'redirect_to' => route('worksheets.index'),
            'items' => [
                [
                    'billable_item_id' => $items[0]->id,
                    'quantity' => 1,
                ],
                [
                    'billable_item_id' => $items[1]->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('worksheets.index'));

        $worksheet = Worksheet::query()->where('worksheet_number', 'TESZT-20260327-01')->firstOrFail();

        $this->assertDatabaseCount('worksheet_items', 2);
        $this->assertDatabaseHas('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => $items[0]->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => $items[1]->id,
            'quantity' => 1,
        ]);
    }

    public function test_user_can_update_worksheet_with_multiple_distinct_items(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);

        $items = BillableItem::query()->take(3)->get();
        $worksheet = Worksheet::query()->create([
            'worksheet_number' => 'ERED-01',
            'work_date' => '2026-03-27',
            'note' => 'Régi',
        ]);

        $worksheet->items()->create([
            'billable_item_id' => $items[0]->id,
            'item_name_at_time' => $items[0]->name,
            'price_at_time' => $items[0]->price,
            'quantity' => 1,
        ]);

        $response = $this->put(route('worksheets.update', $worksheet), [
            'worksheet_number' => 'ERED-01-FRISS',
            'work_date' => '2026.03.27',
            'note' => 'Frissítve',
            'redirect_to' => route('worksheets.index'),
            'items' => [
                [
                    'billable_item_id' => $items[1]->id,
                    'quantity' => 1,
                ],
                [
                    'billable_item_id' => $items[2]->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('worksheets.index'));

        $this->assertDatabaseHas('worksheets', [
            'id' => $worksheet->id,
            'worksheet_number' => 'ERED-01-FRISS',
        ]);

        $this->assertDatabaseHas('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => $items[1]->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => $items[2]->id,
            'quantity' => 1,
        ]);
        $this->assertDatabaseMissing('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => $items[0]->id,
        ]);
    }

    public function test_user_can_export_filtered_worksheets_to_xls(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        Excel::fake();

        $install = BillableItem::query()->where('name', 'Install')->firstOrFail();
        $veteliHely = BillableItem::query()->where('name', 'Vételi hely')->firstOrFail();
        $firstWorksheet = Worksheet::query()->create([
            'worksheet_number' => 'EXP-001',
            'work_date' => '2026-03-25',
            'note' => 'Első megjegyzés',
        ]);
        $secondWorksheet = Worksheet::query()->create([
            'worksheet_number' => 'EXP-002',
            'work_date' => '2026-03-26',
            'note' => 'Második megjegyzés',
        ]);

        $firstWorksheet->items()->createMany([
            [
                'billable_item_id' => $install->id,
                'item_name_at_time' => $install->name,
                'price_at_time' => 4200,
                'quantity' => 1,
            ],
            [
                'billable_item_id' => $veteliHely->id,
                'item_name_at_time' => $veteliHely->name,
                'price_at_time' => 3000,
                'quantity' => 1,
            ],
        ]);

        $secondWorksheet->items()->create([
            'billable_item_id' => $install->id,
            'item_name_at_time' => $install->name,
            'price_at_time' => 4200,
            'quantity' => 1,
        ]);

        $response = $this->get(route('worksheets.export', [
            'from' => '2026-03-25',
            'to' => '2026-03-26',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('Elszamolas_2026-03-25_2026-03-26.xls', function (WorksheetsExport $export): bool {
            $rows = $export->collection();
            $exportedItems = $rows->pluck(2)->filter()->values()->all();

            return $rows->count() === 3
                && in_array('Install + Vételi hely', $exportedItems, true)
                && $rows[2][3] === 'Kiválasztott időszak bevétele'
                && $rows[2][4] === '11 400 Ft';
        });
    }
}
