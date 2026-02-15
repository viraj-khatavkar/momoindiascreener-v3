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
            $table->dropColumn(['ignore_top_beta', 'ignore_above_beta', 'ignore_top_volatility']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->boolean('ignore_top_beta')->after('series_be');
            $table->decimal('ignore_above_beta')->default(100)->after('ignore_top_beta');
            $table->boolean('ignore_top_volatility')->after('ignore_above_beta');
        });
    }
};
