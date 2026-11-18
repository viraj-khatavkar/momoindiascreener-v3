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
            $table->boolean('apply_ema')->default(false);
            $table->boolean('above_ema_200')->default(false);
            $table->boolean('above_ema_100')->default(false);
            $table->boolean('above_ema_50')->default(false);
            $table->boolean('above_ema_20')->default(false);
            $table->boolean('below_ema_200')->default(false);
            $table->boolean('below_ema_100')->default(false);
            $table->boolean('below_ema_50')->default(false);
            $table->boolean('below_ema_20')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn(['apply_ema', 'above_ema_200', 'above_ema_100', 'above_ema_50', 'above_ema_20', 'below_ema_200', 'below_ema_100', 'below_ema_50', 'below_ema_20']);
        });
    }
};
