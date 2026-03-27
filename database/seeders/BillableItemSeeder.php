<?php

namespace Database\Seeders;

use App\Support\BillableItemCatalog;
use Illuminate\Database\Seeder;

class BillableItemSeeder extends Seeder
{
    public function run(): void
    {
        BillableItemCatalog::ensureSeeded();
    }
}
