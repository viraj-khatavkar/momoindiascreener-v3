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
        Schema::create('nse_indices', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->string('slug');
            $table->date('date');
            $table->decimal('open', 20, 2)->nullable();
            $table->decimal('high', 20, 2)->nullable();
            $table->decimal('low', 20, 2)->nullable();
            $table->decimal('close', 20, 2);
            $table->decimal('points_change', 20, 2)->nullable();
            $table->decimal('percentage_change', 20, 2)->nullable();
            $table->unsignedBigInteger('volume')->nullable();
            $table->decimal('turnover', 20, 2)->nullable();
            $table->decimal('price_to_earnings', 10, 2)->nullable();
            $table->decimal('price_to_book', 10, 2)->nullable();
            $table->decimal('dividend_yield', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('nse_indices');
    }
};
