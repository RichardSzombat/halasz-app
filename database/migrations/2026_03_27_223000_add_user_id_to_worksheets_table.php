<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worksheets', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();
        });

        $firstUserId = DB::table('users')->orderBy('id')->value('id');

        if ($firstUserId !== null) {
            DB::table('worksheets')
                ->whereNull('user_id')
                ->update(['user_id' => $firstUserId]);
        }
    }

    public function down(): void
    {
        Schema::table('worksheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
