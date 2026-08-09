<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteWorksheetItemsTable(nullableBillableItem: true);

            return;
        }

        Schema::table('worksheet_items', function (Blueprint $table) {
            $table->dropForeign(['billable_item_id']);
        });

        Schema::table('worksheet_items', function (Blueprint $table) {
            $table->foreignId('billable_item_id')->nullable()->change();
            $table->foreign('billable_item_id')->references('id')->on('billable_items')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('worksheet_items')->whereNull('billable_item_id')->delete();

        if (DB::getDriverName() === 'sqlite') {
            $this->rebuildSqliteWorksheetItemsTable(nullableBillableItem: false);

            return;
        }

        Schema::table('worksheet_items', function (Blueprint $table) {
            $table->dropForeign(['billable_item_id']);
        });

        Schema::table('worksheet_items', function (Blueprint $table) {
            $table->foreignId('billable_item_id')->nullable(false)->change();
            $table->foreign('billable_item_id')->references('id')->on('billable_items')->restrictOnDelete();
        });
    }

    private function rebuildSqliteWorksheetItemsTable(bool $nullableBillableItem): void
    {
        Schema::disableForeignKeyConstraints();

        try {
            Schema::dropIfExists('worksheet_items_tmp');

            Schema::create('worksheet_items_tmp', function (Blueprint $table) use ($nullableBillableItem) {
                $table->id();
                $table->foreignId('worksheet_id')->constrained()->cascadeOnDelete();

                $billableItemId = $table->foreignId('billable_item_id');

                if ($nullableBillableItem) {
                    $billableItemId->nullable();
                }

                $billableItemId->constrained()->restrictOnDelete();

                $table->string('item_name_at_time');
                $table->unsignedInteger('price_at_time');
                $table->unsignedInteger('quantity')->default(1);
                $table->timestamps();
            });

            DB::statement(<<<'SQL'
                INSERT INTO worksheet_items_tmp (
                    id,
                    worksheet_id,
                    billable_item_id,
                    item_name_at_time,
                    price_at_time,
                    quantity,
                    created_at,
                    updated_at
                )
                SELECT
                    id,
                    worksheet_id,
                    billable_item_id,
                    item_name_at_time,
                    price_at_time,
                    quantity,
                    created_at,
                    updated_at
                FROM worksheet_items
            SQL);

            Schema::drop('worksheet_items');
            Schema::rename('worksheet_items_tmp', 'worksheet_items');
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
