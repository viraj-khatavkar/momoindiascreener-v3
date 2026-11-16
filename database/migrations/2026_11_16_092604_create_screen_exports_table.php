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
        Schema::create('screen_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('screen_id')->nullable()->constrained()->nullOnDelete();
            $table->string('screen_name');
            $table->json('settings');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('queued');
            $table->unsignedSmallInteger('total_dates')->default(0);
            $table->unsignedSmallInteger('processed_dates')->default(0);
            $table->unsignedSmallInteger('file_count')->default(0);
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedSmallInteger('warning_count')->default(0);
            $table->string('path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('screen_exports');
    }
};
