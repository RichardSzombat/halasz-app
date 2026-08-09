<?php

namespace Tests\Feature;

use App\Exports\WorksheetsExport;
use App\Models\BillableItem;
use App\Models\User;
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
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('worksheets.create'));

        $response->assertOk();
        $response->assertSee('Munkalap rögzítése');
        $response->assertDontSee('</div></div></button>', false);
        $response->assertSee('type="date"', false);
        $response->assertSee('max="'.now()->toDateString().'"', false);
    }

    public function test_create_page_shows_billable_items_in_expected_order(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('worksheets.create'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Install',
            'Módosítás',
            'Hibajavítás',
            'Szelfinstall',
            'Kötés',
            'UTP',
            'IP+',
            'Sűrítés 10m&gt;',
            'Sűrítés 10m&lt;',
            'Vételi hely',
        ], false);
    }

    public function test_edit_page_uses_separate_delete_form_to_avoid_nested_form_submission(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $this->actingAs($user);

        $worksheet = Worksheet::query()->create([
            'user_id' => $user->id,
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
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('worksheets.index'));

        $response->assertOk();
        $response->assertSee('id="from"', false);
        $response->assertSee('id="to"', false);
        $response->assertSee('type="date"', false);
        $response->assertSee('max="'.now()->toDateString().'"', false);
        $response->assertSee('value="'.now()->toDateString().'"', false);
        $response->assertSee(now()->locale('hu')->translatedFormat('Y. F'));
    }

    public function test_index_defaults_to_today_only_records(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        Worksheet::query()->create([
            'user_id' => $user->id,
            'worksheet_number' => 'MAI-MUNKALAP',
            'work_date' => now()->toDateString(),
            'note' => 'Mai',
        ]);

        Worksheet::query()->create([
            'user_id' => $user->id,
            'worksheet_number' => 'REGI-MUNKALAP',
            'work_date' => now()->subDay()->toDateString(),
            'note' => 'Régi',
        ]);

        $response = $this->get(route('worksheets.index'));

        $response->assertOk();
        $response->assertSee('MAI-MUNKALAP');
        $response->assertDontSee('REGI-MUNKALAP');
    }

    public function test_user_only_sees_own_worksheets_on_index(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        Worksheet::query()->create([
            'user_id' => $user->id,
            'worksheet_number' => 'SAJAT-MUNKALAP',
            'work_date' => now()->toDateString(),
            'note' => 'Saját',
        ]);

        Worksheet::query()->create([
            'user_id' => $otherUser->id,
            'worksheet_number' => 'MASIKE-MUNKALAP',
            'work_date' => now()->toDateString(),
            'note' => 'Másik',
        ]);

        $response = $this->get(route('worksheets.index'));

        $response->assertOk();
        $response->assertSee('SAJAT-MUNKALAP');
        $response->assertDontSee('MASIKE-MUNKALAP');
    }

    public function test_user_can_create_worksheet_with_multiple_distinct_items(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);

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

        $this->assertSame($user->id, $worksheet->user_id);
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

    public function test_user_can_create_worksheet_with_quantities_and_custom_items(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        $utp = BillableItem::query()->where('name', 'UTP')->firstOrFail();
        $install = BillableItem::query()->where('name', 'Install')->firstOrFail();

        $response = $this->post(route('worksheets.store'), [
            'worksheet_number' => 'MENNY-20260327-01',
            'work_date' => '2026.03.27',
            'redirect_to' => route('worksheets.index'),
            'items' => [
                [
                    'type' => 'catalog',
                    'billable_item_id' => $utp->id,
                    'quantity' => 3,
                ],
                [
                    'type' => 'catalog',
                    'billable_item_id' => $install->id,
                    'quantity' => 7,
                ],
                [
                    'type' => 'custom',
                    'custom_name' => 'Egyedi kiszállás',
                    'custom_price' => 12500,
                    'quantity' => 2,
                ],
            ],
        ]);

        $response->assertRedirect(route('worksheets.index'));

        $worksheet = Worksheet::query()->where('worksheet_number', 'MENNY-20260327-01')->firstOrFail();

        $this->assertDatabaseHas('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => $utp->id,
            'price_at_time' => $utp->price,
            'quantity' => 3,
        ]);
        $this->assertDatabaseHas('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => $install->id,
            'price_at_time' => $install->price,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => null,
            'item_name_at_time' => 'Egyedi kiszállás',
            'price_at_time' => 12500,
            'quantity' => 2,
        ]);

        $worksheet->load('items');

        $this->assertSame(($utp->price * 3) + $install->price + (12500 * 2), $worksheet->total_amount);
    }

    public function test_user_can_update_worksheet_with_multiple_distinct_items(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        $items = BillableItem::query()->take(3)->get();
        $worksheet = Worksheet::query()->create([
            'user_id' => $user->id,
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
            'user_id' => $user->id,
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

    public function test_user_can_update_quantities_and_custom_items(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        $user = User::factory()->create();
        $this->actingAs($user);

        $utp = BillableItem::query()->where('name', 'UTP')->firstOrFail();
        $install = BillableItem::query()->where('name', 'Install')->firstOrFail();
        $worksheet = Worksheet::query()->create([
            'user_id' => $user->id,
            'worksheet_number' => 'CUSTOM-SZERK-01',
            'work_date' => '2026-03-27',
            'note' => 'Régi',
        ]);

        $utpItem = $worksheet->items()->create([
            'billable_item_id' => $utp->id,
            'item_name_at_time' => $utp->name,
            'price_at_time' => $utp->price,
            'quantity' => 1,
        ]);
        $removedItem = $worksheet->items()->create([
            'billable_item_id' => $install->id,
            'item_name_at_time' => $install->name,
            'price_at_time' => $install->price,
            'quantity' => 1,
        ]);
        $customItem = $worksheet->items()->create([
            'billable_item_id' => null,
            'item_name_at_time' => 'Régi egyedi',
            'price_at_time' => 7000,
            'quantity' => 1,
        ]);

        $response = $this->put(route('worksheets.update', $worksheet), [
            'worksheet_number' => 'CUSTOM-SZERK-01',
            'work_date' => '2026.03.27',
            'note' => 'Frissítve',
            'redirect_to' => route('worksheets.index'),
            'items' => [
                [
                    'type' => 'catalog',
                    'worksheet_item_id' => $utpItem->id,
                    'billable_item_id' => $utp->id,
                    'quantity' => 4,
                ],
                [
                    'type' => 'custom',
                    'worksheet_item_id' => $customItem->id,
                    'custom_name' => 'Módosított egyedi',
                    'custom_price' => 9000,
                    'quantity' => 2,
                ],
                [
                    'type' => 'custom',
                    'custom_name' => 'Új egyedi',
                    'custom_price' => 500,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertRedirect(route('worksheets.index'));

        $this->assertDatabaseHas('worksheet_items', [
            'id' => $utpItem->id,
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => $utp->id,
            'quantity' => 4,
        ]);
        $this->assertDatabaseHas('worksheet_items', [
            'id' => $customItem->id,
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => null,
            'item_name_at_time' => 'Módosított egyedi',
            'price_at_time' => 9000,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('worksheet_items', [
            'worksheet_id' => $worksheet->id,
            'billable_item_id' => null,
            'item_name_at_time' => 'Új egyedi',
            'price_at_time' => 500,
            'quantity' => 1,
        ]);
        $this->assertDatabaseMissing('worksheet_items', [
            'id' => $removedItem->id,
        ]);
    }

    public function test_user_cannot_open_another_users_worksheet(): void
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($user);

        $worksheet = Worksheet::query()->create([
            'user_id' => $otherUser->id,
            'worksheet_number' => 'IDEGEN-01',
            'work_date' => '2026-03-27',
            'note' => 'Tiltott',
        ]);

        $this->get(route('worksheets.edit', $worksheet))->assertNotFound();
    }

    public function test_user_can_export_filtered_worksheets_to_xls(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        $user = User::factory()->create(['name' => 'Teszt Felhasználó']);
        $otherUser = User::factory()->create();
        $this->actingAs($user);
        Excel::fake();

        $install = BillableItem::query()->where('name', 'Install')->firstOrFail();
        $veteliHely = BillableItem::query()->where('name', 'Vételi hely')->firstOrFail();

        $firstWorksheet = Worksheet::query()->create([
            'user_id' => $user->id,
            'worksheet_number' => 'EXP-001',
            'work_date' => '2026-03-25',
            'note' => 'Első megjegyzés',
        ]);

        $secondWorksheet = Worksheet::query()->create([
            'user_id' => $user->id,
            'worksheet_number' => 'EXP-002',
            'work_date' => '2026-03-26',
            'note' => 'Második megjegyzés',
        ]);

        $hiddenWorksheet = Worksheet::query()->create([
            'user_id' => $otherUser->id,
            'worksheet_number' => 'EXP-999',
            'work_date' => '2026-03-26',
            'note' => 'Nem látható',
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

        $hiddenWorksheet->items()->create([
            'billable_item_id' => $veteliHely->id,
            'item_name_at_time' => $veteliHely->name,
            'price_at_time' => 3000,
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

            return $rows->count() === 7
                && $rows[0][0] === 'Teszt Felhasználó'
                && $rows[1][0] === 'Elszámolás 2026.03.25.-2026.03.26.'
                && in_array('Install + Vételi hely', $exportedItems, true)
                && ! in_array('Vételi hely', $exportedItems, true)
                && $rows[3][0] === 'Dátum'
                && $rows[6][3] === 'Kiválasztott időszak bevétele'
                && $rows[6][4] === '11 400 Ft';
        });
    }

    public function test_user_can_export_custom_items_and_quantities_to_xls(): void
    {
        $this->withoutVite();
        $this->seed(BillableItemSeeder::class);
        $user = User::factory()->create(['name' => 'Export Teszt']);
        $this->actingAs($user);
        Excel::fake();

        $utp = BillableItem::query()->where('name', 'UTP')->firstOrFail();
        $worksheet = Worksheet::query()->create([
            'user_id' => $user->id,
            'worksheet_number' => 'EXP-CUSTOM-001',
            'work_date' => '2026-03-27',
            'note' => 'Custom export',
        ]);

        $worksheet->items()->createMany([
            [
                'billable_item_id' => $utp->id,
                'item_name_at_time' => $utp->name,
                'price_at_time' => $utp->price,
                'quantity' => 3,
            ],
            [
                'billable_item_id' => null,
                'item_name_at_time' => 'Egyedi munka',
                'price_at_time' => 6000,
                'quantity' => 2,
            ],
        ]);

        $response = $this->get(route('worksheets.export', [
            'from' => '2026-03-27',
            'to' => '2026-03-27',
        ]));

        $response->assertOk();

        Excel::assertDownloaded('Elszamolas_2026-03-27_2026-03-27.xls', function (WorksheetsExport $export) use ($utp): bool {
            $rows = $export->collection();
            $expectedTotal = ($utp->price * 3) + (6000 * 2);

            return $rows->count() === 6
                && $rows[4][2] === 'UTP x3 + Egyedi munka x2'
                && $rows[4][4] === number_format($expectedTotal, 0, ',', ' ').' Ft'
                && $rows[5][4] === number_format($expectedTotal, 0, ',', ' ').' Ft';
        });
    }

    public function test_guest_is_redirected_to_login_page_from_worksheets(): void
    {
        $response = $this->get(route('worksheets.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_root_redirects_guest_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_root_redirects_authenticated_user_to_worksheets(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get('/');

        $response->assertRedirect(route('worksheets.index'));
    }

    public function test_login_page_contains_registration_call_to_action(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Bejelentkezés');
        $response->assertSee('Nincs még fiókod?');
        $response->assertSee(route('register'));
    }

    public function test_register_page_contains_login_call_to_action(): void
    {
        $response = $this->get(route('register'));

        $response->assertOk();
        $response->assertSee('Regisztráció');
        $response->assertSee('Regisztrációt követően a rendszer azonnal be is léptet.');
        $response->assertSee(route('login'));
    }

    public function test_user_can_register_and_is_logged_in(): void
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Új Felhasználó',
            'email' => 'uj@example.com',
            'password' => 'titkos-jelszo',
            'password_confirmation' => 'titkos-jelszo',
        ]);

        $response->assertRedirect(route('worksheets.index'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'uj@example.com',
        ]);
    }
}
