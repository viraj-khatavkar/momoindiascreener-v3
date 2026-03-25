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
        Schema::create('backtest_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backtest_id')->constrained()->cascadeOnDelete();
            $table->string('symbol');
            $table->string('name')->nullable();
            $table->string('trade_type');
            $table->string('reason');
            $table->date('date');
            $table->unsignedInteger('quantity');
            $table->decimal('price', 20, 2);
            $table->decimal('gross_amount', 20, 2);
            $table->decimal('stt', 20, 2);
            $table->decimal('transaction_charges', 20, 2);
            $table->decimal('sebi_charges', 20, 2);
            $table->decimal('gst', 20, 2);
            $table->decimal('stamp_charges', 20, 2);
            $table->decimal('total_charges', 20, 2);
            $table->decimal('net_amount', 20, 2);

            $table->index(['backtest_id', 'date']);
            $table->index(['backtest_id', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backtest_trades');
    }
};
