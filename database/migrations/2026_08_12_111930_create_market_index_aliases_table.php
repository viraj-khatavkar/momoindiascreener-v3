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
        Schema::create('market_index_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('source_label');
            $table->string('normalized_label')->unique();
            $table->foreignId('market_index_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('suggested_market_index_id')->nullable()->constrained('market_indices')->nullOnDelete();
            $table->string('suggested_slug')->nullable();
            $table->string('status')->default('pending')->index();
            $table->string('sample_symbol')->nullable();
            $table->date('first_seen_on')->nullable();
            $table->date('last_seen_on')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('market_index_aliases');
    }
};
