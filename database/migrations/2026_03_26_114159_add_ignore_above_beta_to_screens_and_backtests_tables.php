<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->decimal('ignore_above_beta')->default(100)->after('series_be');
        });

        Schema::table('backtests', function (Blueprint $table) {
            $table->decimal('ignore_above_beta')->default(100)->after('series_be');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn('ignore_above_beta');
        });

        Schema::table('backtests', function (Blueprint $table) {
            $table->dropColumn('ignore_above_beta');
        });
    }
};
