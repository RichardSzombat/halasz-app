<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('worksheet_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worksheet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billable_item_id')->constrained()->restrictOnDelete();
            $table->string('item_name_at_time');
            $table->unsignedInteger('price_at_time');
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worksheet_items');
    }
};
