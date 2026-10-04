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
        Schema::table('backtests', function (Blueprint $table) {
            $table->boolean('apply_stop_loss')->default(false);
            $table->decimal('stop_loss_percentage', 5, 2)->default(10);
            $table->boolean('trail_stop_loss')->default(false);
            $table->string('stop_loss_proceeds')->default('wait_for_rebalance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backtests', function (Blueprint $table) {
            $table->dropColumn(['apply_stop_loss', 'stop_loss_percentage', 'trail_stop_loss', 'stop_loss_proceeds']);
        });
    }
};
